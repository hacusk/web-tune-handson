-- 任意: 実測付き（出力はツリー形式。type / key / Extra の表ではない）
EXPLAIN ANALYZE SELECT id, shop_id, rating, body, created_at FROM reviews
WHERE shop_id = 1 AND rating = 5 ORDER BY created_at DESC, id DESC LIMIT 20;
