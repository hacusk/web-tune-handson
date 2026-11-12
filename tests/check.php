<?php
// 正しさチェック。アプリとは独立に書いた素直なSQLで期待値を作り、APIの応答と完全一致するかを調べる。

declare(strict_types=1);

$base = getenv('CHECK_BASE_URL') ?: 'http://localhost:3000';

$host = getenv('DB_HOST') ?: '127.0.0.1';
$db = getenv('DB_DATABASE') ?: 'ramen';
$user = getenv('DB_USERNAME') ?: (getenv('DB_USER') ?: 'ramen');
$pass = getenv('DB_PASSWORD') ?: 'ramen';
$port = getenv('DB_PORT') ?: '3306';

$pdo = new PDO(
    "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",
    $user,
    $pass,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ],
);

function round2(mixed $v): ?float
{
    return $v === null ? null : round((float) $v, 2);
}

/** 件数0は null。AVG()の浮動小数ではなく SUM/COUNT でアプリ側と同じ丸めにする。 */
function averageFromSum(mixed $sum, mixed $count): ?float
{
    $n = (int) $count;
    if ($n === 0 || $sum === null) {
        return null;
    }

    return round(((int) $sum) / $n, 2);
}

function rows(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function reviewsWhere(PDO $pdo, string $where, array $params, string $order = 'r.created_at DESC, r.id DESC'): array
{
    $list = rows(
        $pdo,
        "SELECT r.id, r.shop_id, s.name AS shop_name, r.user_id, u.name AS user_name, r.rating, r.body, r.created_at,
           (SELECT COUNT(*) FROM likes l WHERE l.review_id = r.id) AS like_count
         FROM reviews r JOIN shops s ON s.id = r.shop_id JOIN users u ON u.id = r.user_id
         WHERE {$where} ORDER BY {$order} LIMIT 20",
        $params,
    );

    return array_map(static function (array $r): array {
        $r['id'] = (int) $r['id'];
        $r['shop_id'] = (int) $r['shop_id'];
        $r['user_id'] = (int) $r['user_id'];
        $r['rating'] = (int) $r['rating'];
        $r['like_count'] = (int) $r['like_count'];

        return $r;
    }, $list);
}

function expectedShops(PDO $pdo, int $page): array
{
    $list = rows(
        $pdo,
        "SELECT s.id, s.name, s.description,
           (SELECT COUNT(*) FROM reviews r WHERE r.shop_id = s.id) AS review_count,
           (SELECT SUM(r.rating) FROM reviews r WHERE r.shop_id = s.id) AS rating_sum
         FROM shops s ORDER BY s.id LIMIT 100 OFFSET ?",
        [($page - 1) * 100],
    );

    return array_map(static fn (array $s): array => [
        'id' => (int) $s['id'],
        'name' => $s['name'],
        'description' => $s['description'],
        'review_count' => (int) $s['review_count'],
        'average_rating' => averageFromSum($s['rating_sum'], $s['review_count']),
    ], $list);
}

function expectedShop(PDO $pdo, int $id): array
{
    $rows = rows(
        $pdo,
        "SELECT s.id, s.name, s.description, COUNT(r.id) AS review_count, SUM(r.rating) AS rating_sum
         FROM shops s LEFT JOIN reviews r ON r.shop_id = s.id WHERE s.id = ? GROUP BY s.id, s.name, s.description",
        [$id],
    );
    $s = $rows[0];

    return [
        'id' => (int) $s['id'],
        'name' => $s['name'],
        'description' => $s['description'],
        'review_count' => (int) $s['review_count'],
        'average_rating' => averageFromSum($s['rating_sum'], $s['review_count']),
        'reviews' => reviewsWhere($pdo, 'r.shop_id = ?', [$id]),
    ];
}

function expectedRanking(PDO $pdo): array
{
    $top = rows($pdo, 'SELECT review_id, COUNT(*) AS c FROM likes GROUP BY review_id ORDER BY c DESC, review_id LIMIT 10');
    $ids = array_map(static fn (array $t): int => (int) $t['review_id'], $top);
    $list = implode(',', $ids);

    return reviewsWhere($pdo, "r.id IN ({$list})", [], "FIELD(r.id, {$list})");
}

function expectedUser(PDO $pdo, int $id): array
{
    $u = rows($pdo, 'SELECT id, name FROM users WHERE id = ?', [$id])[0];
    $c = rows($pdo, 'SELECT COUNT(*) AS n FROM reviews WHERE user_id = ?', [$id])[0];
    $l = rows($pdo, 'SELECT COUNT(*) AS n FROM likes l JOIN reviews r ON r.id = l.review_id WHERE r.user_id = ?', [$id])[0];

    return [
        'id' => (int) $u['id'],
        'name' => $u['name'],
        'review_count' => (int) $c['n'],
        'received_likes' => (int) $l['n'],
        'reviews' => reviewsWhere($pdo, 'r.user_id = ?', [$id]),
    ];
}

function firstDiff(mixed $actual, mixed $want, string $path = 'data'): ?string
{
    if ($actual === $want) {
        return null;
    }
    // JSON numbers may become float; compare structurally via json
    if (json_encode($actual, JSON_UNESCAPED_UNICODE) === json_encode($want, JSON_UNESCAPED_UNICODE)) {
        return null;
    }
    if (is_array($actual) && is_array($want)) {
        $isList = array_is_list($want);
        if ($isList && (! array_is_list($actual) || count($actual) !== count($want))) {
            return "{$path} の件数: ".(array_is_list($actual) ? count($actual) : gettype($actual))."（期待 ".count($want)."）";
        }
        $keys = array_unique([...array_keys($want), ...array_keys($actual)]);
        foreach ($keys as $key) {
            $child = $isList ? "{$path}[{$key}]" : "{$path}.{$key}";
            $d = firstDiff($actual[$key] ?? null, $want[$key] ?? null, $child);
            if ($d !== null) {
                return $d;
            }
        }

        return null;
    }

    return "{$path}: ".json_encode($actual, JSON_UNESCAPED_UNICODE).'（期待 '.json_encode($want, JSON_UNESCAPED_UNICODE).'）';
}

function request(string $base, string $method, string $path, mixed $body = null): array
{
    $headers = '';
    if ($body !== null) {
        $payload = is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_UNICODE);
        $headers = "Content-Type: application/json\r\n";
    } else {
        $payload = null;
    }
    $ctx = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => $headers,
            'content' => $payload,
            'ignore_errors' => true,
            'timeout' => 30,
        ],
    ]);
    $raw = @file_get_contents($base.$path, false, $ctx);
    if ($raw === false && ! isset($http_response_header)) {
        throw new RuntimeException("request failed: {$method} {$path}");
    }
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $status = (int) $m[1];
    }
    $json = json_decode($raw === false ? 'null' : $raw, true);

    return [$status, $json];
}

function expectData(string $base, string $path, mixed $want, string $label = ''): void
{
    $label = $label !== '' ? $label : $path;
    [$status, $body] = request($base, 'GET', $path);
    if ($status !== 200) {
        throw new RuntimeException("{$label}: ステータス {$status}（期待 200）");
    }
    $diff = firstDiff($body['data'] ?? null, $want);
    if ($diff !== null) {
        throw new RuntimeException("{$label}: 結果不一致 {$diff}");
    }
    echo "PASS {$label}\n";
}

function expectStatus(string $base, string $method, string $path, int $want, mixed $body = null): void
{
    [$status] = request($base, $method, $path, $body);
    if ($status !== $want) {
        throw new RuntimeException("{$method} {$path}: ステータス {$status}（期待 {$want}）");
    }
}

try {
    [$initStatus] = request($base, 'POST', '/initialize');
    if ($initStatus !== 200) {
        throw new RuntimeException("POST /initialize: ステータス {$initStatus}");
    }

    expectData($base, '/api/shops', expectedShops($pdo, 1));
    foreach ([2, 10, 11] as $page) {
        expectData($base, "/api/shops?page={$page}", expectedShops($pdo, $page));
    }
    foreach ([1, 42, 999, 1000] as $id) {
        expectData($base, "/api/shops/{$id}", expectedShop($pdo, $id));
    }
    foreach ([[1, 5], [42, 3], [999, 1], [1000, 5], [1, 1]] as [$id, $rating]) {
        expectData($base, "/api/shops/{$id}/reviews?rating={$rating}", reviewsWhere($pdo, 'r.shop_id = ? AND r.rating = ?', [$id, $rating]));
    }
    expectData($base, '/api/ranking', expectedRanking($pdo));
    foreach ([1, 5, 1000] as $id) {
        expectData($base, "/api/users/{$id}", expectedUser($pdo, $id));
    }

    foreach (['/api/shops/1001', '/api/shops/1001/reviews?rating=1', '/api/users/1001', '/missing'] as $path) {
        expectStatus($base, 'GET', $path, 404);
    }
    foreach ([
        '/api/shops?page=0', '/api/shops?page=x', '/api/shops/abc', '/api/shops/1/reviews',
        '/api/shops/1/reviews?rating=0', '/api/shops/1/reviews?rating=6',
        '/api/shops/1/reviews?rating[]=1', '/api/users/0',
    ] as $path) {
        expectStatus($base, 'GET', $path, 400);
    }
    foreach (['{', [], ['user_id' => 950, 'rating' => 6, 'body' => 'x'], ['user_id' => 950, 'rating' => 3, 'body' => ''], ['user_id' => 950, 'rating' => 3, 'body' => str_repeat('x', 201)]] as $body) {
        expectStatus($base, 'POST', '/api/shops/7/reviews', 400, $body);
    }
    expectStatus($base, 'POST', '/api/shops/7/reviews', 404, ['user_id' => 99999, 'rating' => 3, 'body' => 'x']);
    expectStatus($base, 'POST', '/api/shops/1001/reviews', 404, ['user_id' => 950, 'rating' => 3, 'body' => 'x']);
    expectStatus($base, 'POST', '/api/reviews/99999999/likes', 404, ['user_id' => 950]);
    echo "PASS 不正入力・存在しないID\n";

    [$postStatus, $posted] = request($base, 'POST', '/api/shops/7/reviews', ['user_id' => 950, 'rating' => 4, 'body' => '正しさチェックの投稿']);
    if ($postStatus !== 201) {
        throw new RuntimeException("レビュー投稿: ステータス {$postStatus}（期待 201）");
    }
    $newReview = reviewsWhere($pdo, 'r.id = ?', [$posted['data']['id'] ?? 0])[0] ?? null;
    if ($newReview === null || firstDiff($posted['data'] ?? null, $newReview) !== null) {
        throw new RuntimeException('レビュー投稿の応答が不正: '.firstDiff($posted['data'] ?? null, $newReview));
    }
    expectStatus($base, 'POST', "/api/reviews/{$newReview['id']}/likes", 201, ['user_id' => 951]);
    expectStatus($base, 'POST', "/api/reviews/{$newReview['id']}/likes", 409, ['user_id' => 951]);
    expectData($base, '/api/shops', expectedShops($pdo, 1), '投稿後の /api/shops');
    expectData($base, '/api/shops/7', expectedShop($pdo, 7), '投稿後の /api/shops/7');
    expectData($base, '/api/shops/7/reviews?rating=4', reviewsWhere($pdo, 'r.shop_id = ? AND r.rating = ?', [7, 4]), '投稿後の /api/shops/7/reviews?rating=4');
    expectData($base, '/api/users/950', expectedUser($pdo, 950), '投稿後の /api/users/950');

    [$resetStatus] = request($base, 'POST', '/initialize');
    if ($resetStatus !== 200) {
        throw new RuntimeException("POST /initialize: ステータス {$resetStatus}");
    }
    expectData($base, '/api/shops/7', expectedShop($pdo, 7), '初期化後の /api/shops/7');
    expectData($base, '/api/ranking', expectedRanking($pdo), '初期化後の /api/ranking');

    echo "正しさチェック成功\n";
} catch (Throwable $e) {
    fwrite(STDERR, '正しさチェック失敗: '.$e->getMessage()."\n");
    exit(1);
}
