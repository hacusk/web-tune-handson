import http from 'k6/http';
import { check, fail } from 'k6';
import { Counter, Trend } from 'k6/metrics';

const BASE = __ENV.BASE_URL;
const FIXTURE = 'ramen-v4';

// --- 負荷のつまみ ----------------------------------------------------------
// 既定は少し重め。PCが苦しそうなら:
//   BENCH_VUS=2 sh scripts/bench before
// 計測時間だけ変えるなら:
//   BENCH_DURATION=20s sh scripts/bench before
// before / after は同じ値で揃える（compare が条件不一致で止まる）。
const DEFAULT_VUS = 4; // シナリオごとの同時ユーザー数（従来は 2）
const DEFAULT_DURATION = '30s';

function envInt(name, fallback) {
  const raw = __ENV[name];
  if (raw === undefined || raw === '') return fallback;
  const n = Number.parseInt(raw, 10);
  if (!Number.isFinite(n) || n < 1) {
    fail(`${name} は 1 以上の整数にしてください（今: ${raw}）`);
  }
  return n;
}

const VUS = envInt('BENCH_VUS', DEFAULT_VUS);
const DURATION = (__ENV.BENCH_DURATION && String(__ENV.BENCH_DURATION).trim()) || DEFAULT_DURATION;

// 利用者の種類ごとに独立して同時アクセスする（遅いページが他のページの計測を巻き込まない）
const USERS = {
  list: { vus: VUS, label: '一覧を眺める人' },
  browse: { vus: VUS, label: '店を調べて投稿する人' },
  social: { vus: VUS, label: '人気レビューにいいねする人' },
};
// 成功したリクエスト1件あたりの得点（重いページほど高い）
const POINTS = { shops: 3, shop: 1, search: 1, ranking: 3, user: 1, post: 5, like: 3 };
const ENDPOINTS = Object.keys(POINTS);
const SHOPS = 1000;
const EMPTY_SHOP = 1000;
const PER_PAGE = 100;

export const options = {
  scenarios: Object.fromEntries(Object.entries(USERS).map(([name, u]) =>
    [name, { executor: 'constant-vus', vus: u.vus, duration: DURATION, exec: name, gracefulStop: '10s' }])),
  setupTimeout: '120s',
  summaryTrendStats: ['avg', 'p(95)', 'count'],
  thresholds: { checks: ['rate==1'], http_req_failed: ['rate==0'] },
};
http.setResponseCallback(http.expectedStatuses({ min: 200, max: 299 }, 409));

const ms = Object.fromEntries(ENDPOINTS.map((e) => [e, new Trend(`ms_${e}`, true)]));
const sql = Object.fromEntries(ENDPOINTS.map((e) => [e, new Trend(`sql_${e}`)]));
const points = Object.fromEntries(Object.keys(USERS).map((u) => [u, new Counter(`score_${u}`)]));

let last = '';
let reported = 0;

function call(name, method, path, body) {
  const params = { timeout: '10s', tags: { name }, headers: { 'content-type': 'application/json' } };
  const res = method === 'GET' ? http.get(BASE + path, params) : http.request(method, BASE + path, body === undefined ? null : JSON.stringify(body), params);
  ms[name].add(res.timings.duration);
  last = `${method} ${path} → ${res.status}`;
  let json = null;
  try { json = res.json(); } catch (_) { /* 下の検証で失敗にする */ }
  if (json && json.meta && Number.isFinite(json.meta.query_count)) sql[name].add(json.meta.query_count);
  return { res, json };
}

function verify(user, label, ok, endpoint) {
  if (check(null, { [label]: () => ok })) {
    if (endpoint) points[user].add(POINTS[endpoint]);
  } else if (reported++ < 3) {
    console.error(`✗ ${label}: ${last}`);
  }
  return ok;
}

const isInt = (v) => Number.isInteger(v);
const DATETIME = /^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/;

function validReview(r) {
  return r && isInt(r.id) && isInt(r.shop_id) && isInt(r.user_id)
    && typeof r.shop_name === 'string' && r.shop_name.length > 0
    && typeof r.user_name === 'string' && r.user_name.length > 0
    && isInt(r.rating) && r.rating >= 1 && r.rating <= 5
    && typeof r.body === 'string' && r.body.length > 0 && DATETIME.test(r.created_at)
    && isInt(r.like_count) && r.like_count >= 0;
}

function newestFirst(list) {
  for (let i = 1; i < list.length; i++) {
    const a = list[i - 1], b = list[i];
    if (a.created_at < b.created_at || (a.created_at === b.created_at && a.id <= b.id)) return false;
  }
  return true;
}

function validStats(s, baseline) {
  if (!isInt(s.review_count) || s.review_count < baseline.review_count) return false;
  if (s.review_count === 0) return s.average_rating === null;
  return typeof s.average_rating === 'number' && s.average_rating >= 1 && s.average_rating <= 5;
}

function validShopShape(s) {
  return s && isInt(s.id) && typeof s.name === 'string' && s.name.length > 0
    && typeof s.description === 'string' && s.description.length > 0;
}

function validShops(data, page, baseline) {
  return Array.isArray(data) && data.length === PER_PAGE && data.every((s, i) => {
    const id = (page - 1) * PER_PAGE + i + 1;
    return validShopShape(s) && s.id === id && validStats(s, baseline[id - 1]);
  });
}

function validShop(data, id, baseline) {
  return validShopShape(data) && data.id === id && validStats(data, baseline[id - 1])
    && Array.isArray(data.reviews) && data.reviews.length === Math.min(20, data.review_count)
    && data.reviews.every((r) => validReview(r) && r.shop_id === id) && newestFirst(data.reviews);
}

function validRanking(data, baselineTop) {
  if (!Array.isArray(data) || data.length !== 10 || !data.every(validReview)) return false;
  if (data[0].like_count < baselineTop) return false;
  for (let i = 1; i < data.length; i++) {
    const a = data[i - 1], b = data[i];
    if (a.like_count < b.like_count || (a.like_count === b.like_count && a.id >= b.id)) return false;
  }
  return true;
}

function validUser(data, id) {
  return data && data.id === id && typeof data.name === 'string' && data.name.length > 0
    && isInt(data.review_count) && data.review_count >= 200
    && isInt(data.received_likes) && data.received_likes >= 0
    && Array.isArray(data.reviews) && data.reviews.length === 20
    && data.reviews.every((r) => validReview(r) && r.user_id === id) && newestFirst(data.reviews);
}

export function setup() {
  const init = http.post(`${BASE}/initialize`, null, { timeout: '60s' });
  if (init.status !== 200) fail(`POST /initialize が ${init.status} を返しました`);
  const shops = [];
  for (let page = 1; page <= SHOPS / PER_PAGE; page++) {
    const data = http.get(`${BASE}/api/shops?page=${page}`, { timeout: '30s' }).json('data');
    if (!Array.isArray(data) || data.length !== PER_PAGE) fail('初期データの取得に失敗しました');
    shops.push(...data.map((s) => ({ review_count: s.review_count })));
  }
  const ranking = http.get(`${BASE}/api/ranking`, { timeout: '30s' }).json('data');
  if (!Array.isArray(ranking) || ranking.length !== 10) fail('初期データの取得に失敗しました');
  return { shops, rankingTop: ranking[0].like_count };
}

// 乱数を使わず、VU番号と繰り返し回数から決まる順番でアクセスする（計測条件を揃えるため）
export function list(base) {
  // トップページ（1ページ目）が一番よく見られる
  const page = __ITER % 2 === 0 ? 1 : 1 + ((__VU + __ITER) % (SHOPS / PER_PAGE));
  const { res, json } = call('shops', 'GET', page === 1 ? '/api/shops' : `/api/shops?page=${page}`);
  verify('list', '店舗一覧', res.status === 200 && validShops(json && json.data, page, base.shops), 'shops');
}

export function browse(base) {
  const shopId = 1 + ((__VU * 37 + __ITER * 131) % SHOPS);
  const shown = call('shop', 'GET', `/api/shops/${shopId}`);
  const before = shown.json && shown.json.data;
  if (!verify('browse', '店舗詳細', shown.res.status === 200 && validShop(before, shopId, base.shops), 'shop')) return;

  const rating = 1 + (__ITER % 5);
  const { res, json } = call('search', 'GET', `/api/shops/${shopId}/reviews?rating=${rating}`);
  const data = json && json.data;
  verify('browse', 'レビュー絞り込み', res.status === 200 && Array.isArray(data) && data.length <= 20
    && (shopId !== EMPTY_SHOP || data.length === 0)
    && data.every((r) => validReview(r) && r.shop_id === shopId && r.rating === rating) && newestFirst(data), 'search');

  // 4回に1回は投稿し、直後の店舗詳細に反映されているか確かめる
  if (__ITER % 4 !== 3 || shopId === EMPTY_SHOP) return;
  const me = 900 + __VU;
  const text = `ベンチ投稿 vu${__VU} iter${__ITER}`;
  const posted = call('post', 'POST', `/api/shops/${shopId}/reviews`, { user_id: me, rating, body: text });
  const r = posted.json && posted.json.data;
  if (!verify('browse', 'レビュー投稿', posted.res.status === 201 && validReview(r) && r.shop_id === shopId && r.user_id === me && r.body === text && r.like_count === 0)) return;
  const after = call('shop', 'GET', `/api/shops/${shopId}`);
  const d = after.json && after.json.data;
  verify('browse', '投稿の反映', after.res.status === 200 && validShop(d, shopId, base.shops)
    && d.review_count >= before.review_count + 1 && d.reviews.some((x) => x.id === r.id), 'post');
}

export function social(base) {
  // ランキング → 投稿者のページ（ランキングの人か、たまたま見つけた人）→ そのレビューにいいね
  const top = call('ranking', 'GET', '/api/ranking');
  const ranked = top.json && top.json.data;
  if (!verify('social', '人気レビュー', top.res.status === 200 && validRanking(ranked, base.rankingTop), 'ranking')) return;

  const userId = __ITER % 2 === 0 ? ranked[(__ITER / 2) % ranked.length].user_id : 1 + ((__VU * 101 + __ITER * 17) % 1000);
  const shown = call('user', 'GET', `/api/users/${userId}`);
  const b = shown.json && shown.json.data;
  if (!verify('social', 'ユーザーページ', shown.res.status === 200 && validUser(b, userId), 'user')) return;

  // いいね → 直後のユーザーページに反映されているか（同じ人の2回目は409で正しい）
  const me = 900 + __VU;
  const review = b.reviews[(__ITER * 7 + __VU) % b.reviews.length];
  const liked = call('like', 'POST', `/api/reviews/${review.id}/likes`, { user_id: me });
  if (!verify('social', 'いいね', liked.res.status === 201 || liked.res.status === 409) || liked.res.status === 409) return;
  const after = call('user', 'GET', `/api/users/${userId}`);
  const a = after.json && after.json.data;
  const same = a && Array.isArray(a.reviews) && a.reviews.find((x) => x.id === review.id);
  verify('social', 'いいねの反映', after.res.status === 200 && validUser(a, userId)
    && a.received_likes >= b.received_likes + 1 && same && same.like_count >= review.like_count + 1, 'like');
}

const pad = (v, n) => String(v).padStart(n);

export function handleSummary(data) {
  const m = data.metrics;
  const passed = Object.values(m).every((x) => Object.values(x.thresholds || {}).every((t) => t.ok));
  const endpoints = {};
  for (const e of ENDPOINTS) {
    const t = m[`ms_${e}`];
    if (!t) continue;
    endpoints[e] = {
      count: t.values.count,
      avg_ms: t.values.avg,
      p95_ms: t.values['p(95)'],
      sql_avg: m[`sql_${e}`] ? m[`sql_${e}`].values.avg : null,
    };
  }
  const users = Object.fromEntries(Object.keys(USERS).map((u) => [u, m[`score_${u}`] ? m[`score_${u}`].values.count : 0]));
  const total = Object.values(users).reduce((a, b) => a + b, 0);
  const result = {
    fixture: FIXTURE, users: USERS, points: POINTS, duration: DURATION, passed,
    score: passed ? total : 0,
    score_by_user: users,
    requests: m.http_reqs ? m.http_reqs.values.count : 0,
    failed_checks: m.checks ? m.checks.values.fails : 0,
    endpoints,
  };
  const lines = [
    '',
    `負荷: 各シナリオ ${VUS} 人 × ${DURATION}（変更: BENCH_VUS / BENCH_DURATION）`,
    `スコア: ${result.score}${passed ? '' : '（失格：誤答またはエラーあり。上のログの ✗ を確認）'}`,
    ...Object.entries(users).map(([u, v]) => `  ${USERS[u].label.padEnd(16, '　')}${pad(v, 7)}`),
    '',
    `${'endpoint'.padEnd(10)}${pad('回数', 6)}${pad('平均ms', 10)}${pad('p95ms', 10)}${pad('SQL平均', 9)}`,
    ...Object.entries(endpoints).map(([e, v]) =>
      `${e.padEnd(10)}${pad(v.count, 8)}${pad(v.avg_ms.toFixed(1), 10)}${pad(v.p95_ms.toFixed(1), 10)}${pad(v.sql_avg === null ? '-' : v.sql_avg.toFixed(1), 10)}`),
    '',
  ];
  return {
    [`/results/${__ENV.LABEL}.json`]: JSON.stringify(result, null, 2),
    stdout: lines.join('\n') + '\n',
  };
}
