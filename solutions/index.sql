-- 店舗内の評価絞り込み：等価条件(shop_id, rating)の後に並び順(created_at, id)
CREATE INDEX idx_reviews_search ON reviews (shop_id, rating, created_at DESC, id DESC);
-- ユーザーページ：user_id で絞って新しい順。件数・もらったいいねの集計にも使われる
CREATE INDEX idx_reviews_user ON reviews (user_id, created_at DESC, id DESC);
