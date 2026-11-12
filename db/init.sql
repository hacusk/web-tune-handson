SET NAMES utf8mb4;
USE ramen;

CREATE TABLE users (
 id INT AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(50) NOT NULL
);
CREATE TABLE shops (
 id INT AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(100) NOT NULL,
 description VARCHAR(200) NOT NULL
);
CREATE TABLE reviews (
 id INT AUTO_INCREMENT PRIMARY KEY,
 shop_id INT NOT NULL,
 user_id INT NOT NULL,
 rating INT NOT NULL,
 body VARCHAR(200) NOT NULL,
 created_at DATETIME NOT NULL,
 INDEX idx_reviews_shop (shop_id)
);
CREATE TABLE likes (
 id INT AUTO_INCREMENT PRIMARY KEY,
 review_id INT NOT NULL,
 user_id INT NOT NULL,
 created_at DATETIME NOT NULL,
 UNIQUE KEY uniq_likes_review_user (review_id, user_id)
);

CREATE TABLE digits (n INT PRIMARY KEY);
INSERT INTO digits VALUES (0),(1),(2),(3),(4),(5),(6),(7),(8),(9);
CREATE TABLE seq (n INT PRIMARY KEY);
INSERT INTO seq SELECT a.n+10*b.n+100*c.n+1000*d.n+10000*e.n+100000*f.n
FROM digits a CROSS JOIN digits b CROSS JOIN digits c CROSS JOIN digits d CROSS JOIN digits e
CROSS JOIN (SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2) f;

CREATE TABLE name_prefix (id INT PRIMARY KEY, v VARCHAR(20) NOT NULL);
INSERT INTO name_prefix VALUES
 (0,'麺処'),(1,'麺屋'),(2,'ラーメン'),(3,'製麺所'),(4,'家系'),
 (5,'魂麺'),(6,'らーめん'),(7,'煮干し'),(8,'味噌専門'),(9,'つけ麺');

CREATE TABLE name_area (id INT PRIMARY KEY, v VARCHAR(20) NOT NULL);
INSERT INTO name_area VALUES
 (0,'神保町'),(1,'中野'),(2,'五反田'),(3,'荻窪'),(4,'大久保'),
 (5,'三軒茶屋'),(6,'吉祥寺'),(7,'蒲田'),(8,'赤羽'),(9,'門前仲町'),
 (10,'高円寺'),(11,'下北沢'),(12,'錦糸町'),(13,'西新宿'),(14,'浅草橋'),
 (15,'武蔵小山'),(16,'自由が丘'),(17,'北千住'),(18,'日暮里'),(19,'浜松町');

CREATE TABLE name_yago (id INT PRIMARY KEY, v VARCHAR(20) NOT NULL);
INSERT INTO name_yago VALUES
 (0,'まるに'),(1,'よしの'),(2,'一徹'),(3,'篝'),(4,'風見鶏'),
 (5,'のどか'),(6,'くろがね'),(7,'半月'),(8,'虎徹'),(9,'茜屋'),
 (10,'しおん'),(11,'麦と卵'),(12,'蒼天'),(13,'ひばり'),(14,'金時'),
 (15,'やまびこ'),(16,'八幡'),(17,'すずらん'),(18,'白樺'),(19,'黒潮');

CREATE TABLE shop_desc (id INT PRIMARY KEY, v VARCHAR(200) NOT NULL);
INSERT INTO shop_desc VALUES
 (0,'清湯ベースに自家製麺。昼はサラリーマンで行列ができやすい。'),
 (1,'濃厚豚骨が看板。替え玉無料の時間帯あり。'),
 (2,'煮干しの効いた醤油。夜遅くまで開いている。'),
 (3,'味噌は赤・白・カレーの三種類。冬季限定が人気。'),
 (4,'鶏清湯のつけ麺専門。つけ汁の追い飯がおすすめ。'),
 (5,'塩そばが静かな人気。店内はカウンターのみ。'),
 (6,'二郎系インスパイア。野菜マシは覚悟してから頼むこと。'),
 (7,'海鮮系の特製が名物。週末は待ち時間長め。'),
 (8,'家系の定番スタイル。ライスとほうれん草を足しがち。'),
 (9,'坦々麺と汁なしが強い。辛さは店員に相談を。'),
 (10,'鴨だし蕎麦寄りのラーメン。あっさり好き向け。'),
 (11,'油そば専門。味玉トッピングが定番コース。'),
 (12,'スープカレー麺の異色店。ライス付きセットあり。'),
 (13,'手もみ麺にシンプル醤油。初めての人にも勧めやすい。'),
 (14,'黒味噌が自慢。ご飯が進む系。'),
 (15,'魚介と豚骨のWスープ。締めの雑炊も頼める。'),
 (16,'早朝営業あり。通勤前の一杯需要を拾っている。'),
 (17,'完全予約制の週末限定メニューあり。平日は通常営業。'),
 (18,'店舗改装直後。席数は少ないが回転は速い。'),
 (19,'地元常連が多い小さな店。観光客もウェルカム。');

CREATE TABLE user_style (id INT PRIMARY KEY, v VARCHAR(20) NOT NULL);
INSERT INTO user_style VALUES
 (0,'濃厚好き'),(1,'あっさり派'),(2,'味噌沼'),(3,'つけ麺巡る'),(4,'塩沼'),
 (5,'家系通'),(6,'二郎求道'),(7,'煮干シスト'),(8,'坦々愛好'),(9,'朝ラー勢');

CREATE TABLE user_suffix (id INT PRIMARY KEY, v VARCHAR(20) NOT NULL);
INSERT INTO user_suffix VALUES
 (0,'の太郎'),(1,'の花子'),(2,'のケン'),(3,'のミカ'),(4,'さん'),
 (5,'ウォーカー'),(6,'ランナー'),(7,'の一郎'),(8,'のユウ'),(9,'くん');

CREATE TABLE review_body (id INT PRIMARY KEY, v VARCHAR(200) NOT NULL);
INSERT INTO review_body VALUES
 (0,'スープがしっかりしていてリピート確定。'),
 (1,'麺の硬さがちょうど良い。また来たい。'),
 (2,'少し塩が強め。ライスがあると嬉しい。'),
 (3,'行列の長さほどの満足感はあった。'),
 (4,'味玉が想像以上に美味い。'),
 (5,'あっさりめで何杯でもいけそう。'),
 (6,'脂が重い日もある。日によってブレる。'),
 (7,'接客が気持ちよく、食後感が良い。'),
 (8,'辛さ控えめで食べやすかった。'),
 (9,'締めのご飯が進む。危険。'),
 (10,'期待しすぎたせいか普通に感じた。'),
 (11,'香りが良く、スープを飲み干した。'),
 (12,'麺量が多くて大満足。'),
 (13,'席が狭くて長居はしづらい。'),
 (14,'コスパが良く、学生にも優しい。'),
 (15,'夜の方が味が安定している印象。'),
 (16,'トッピングなしの素の一杯が一番うまい。'),
 (17,'友人に勧められて来店。期待以上。'),
 (18,'スープが薄く感じた。麺は良い。'),
 (19,'また並んででも食べたい味。');

INSERT INTO users (id, name)
SELECT s.n, CONCAT(st.v, su.v, ' ', s.n)
FROM seq s
JOIN user_style st ON st.id = MOD(s.n - 1, 10)
JOIN user_suffix su ON su.id = MOD(s.n * 3, 10)
WHERE s.n BETWEEN 1 AND 1000;

-- 1000番はレビュー0件（開店準備中）
INSERT INTO shops (id, name, description)
SELECT s.n,
  IF(s.n = 1000,
    'ラーメンRunner 試験店舗',
    CONCAT(p.v, ' ', a.v, ' ', y.v)),
  IF(s.n = 1000,
    '開店準備中。レビュー募集はまだ始まっていません。',
    d.v)
FROM seq s
JOIN name_prefix p ON p.id = MOD(s.n - 1, 10)
JOIN name_area a ON a.id = MOD(s.n * 3, 20)
JOIN name_yago y ON y.id = MOD(s.n * 7, 20)
JOIN shop_desc d ON d.id = MOD(s.n * 11, 20)
WHERE s.n BETWEEN 1 AND 1000;

-- レビュー20万件。店1〜999へほぼ均等（各店約200件）。1000番は付けない。
-- 星は店ごとに高評価の出やすさを変え、レビューごとに 1〜5 を振る（平均が 3.00 や数種に固まらないようにする）。
INSERT INTO reviews (id, shop_id, user_id, rating, body, created_at)
SELECT
  x.n,
  x.shop_id,
  1 + MOD(x.n * 7, 1000) AS user_id,
  CASE
    WHEN x.roll < (3 + x.q * 65 DIV 97 + MOD(x.shop_id, 3)) THEN 5
    WHEN x.roll < (20 + x.q * 55 DIV 97 + MOD(x.shop_id, 4)) THEN 4
    WHEN x.roll < (48 + x.q * 30 DIV 97 + MOD(x.shop_id, 3)) THEN 3
    WHEN x.roll < (85 + MOD(x.shop_id, 8)) THEN 2
    ELSE 1
  END AS rating,
  rb.v,
  DATE_ADD('2025-01-01', INTERVAL MOD(x.n, 365) DAY)
FROM (
  SELECT
    s.n,
    1 + MOD(s.n - 1, 999) AS shop_id,
    MOD(s.n * 19 + (1 + MOD(s.n - 1, 999)) * 7, 100) AS roll,
    MOD((1 + MOD(s.n - 1, 999)) * 7919, 97) AS q
  FROM seq s
  WHERE s.n BETWEEN 1 AND 200000
) x
JOIN review_body rb ON rb.id = MOD(x.n * 13, 20);

-- 人気に偏りを付ける。いいねするのはユーザー1〜900（901〜1000はベンチ用）
INSERT IGNORE INTO likes (id, review_id, user_id, created_at)
SELECT n + 1,
  1 + MOD((n * n DIV 200000) * 7919, 200000),
  1 + MOD(n, 900),
  DATE_ADD('2025-06-01', INTERVAL MOD(n, 180) DAY)
FROM seq WHERE n < 200000 ORDER BY n;

DROP TABLE review_body;
DROP TABLE user_suffix;
DROP TABLE user_style;
DROP TABLE shop_desc;
DROP TABLE name_yago;
DROP TABLE name_area;
DROP TABLE name_prefix;
DROP TABLE seq;
DROP TABLE digits;

ANALYZE TABLE shops, reviews, likes, users;

CREATE TABLE seed_ready (id INT PRIMARY KEY);
INSERT INTO seed_ready VALUES (1);
