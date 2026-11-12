EXPLAIN ANALYZE SELECT id, shop_id, user_id, rating, body, created_at FROM reviews
WHERE user_id = 1 ORDER BY created_at DESC, id DESC LIMIT 20;
