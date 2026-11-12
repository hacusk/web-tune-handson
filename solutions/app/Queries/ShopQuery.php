<?php

namespace App\Queries;

use App\Models\Review;
use App\Models\Shop;

class ShopQuery
{
    public const SHOPS_PER_PAGE = 100;

    // 解答: そのページの店舗IDで whereIn + GROUP BY（COUNT/SUM）してまとめて集計し、Mapで対応づける（2回）。
    // レビュー0件の店舗は集計結果に現れないので、件数0・平均nullで補う。
    public static function listShops(int $page): array
    {
        $shops = Shop::query()
            ->orderBy('id')
            ->offset(($page - 1) * self::SHOPS_PER_PAGE)
            ->limit(self::SHOPS_PER_PAGE)
            ->get(['id', 'name', 'description']);

        if ($shops->isEmpty()) {
            return [];
        }

        $statsRows = Review::query()
            ->whereIn('shop_id', $shops->pluck('id'))
            ->selectRaw('shop_id, COUNT(*) as review_count, SUM(rating) as rating_sum')
            ->groupBy('shop_id')
            ->get()
            ->keyBy('shop_id');

        $result = [];
        foreach ($shops as $shop) {
            $stats = $statsRows->get($shop->id);
            $count = $stats ? (int) $stats->review_count : 0;
            $result[] = self::toShopJson(
                (int) $shop->id,
                $shop->name,
                $shop->description,
                $count,
                $count === 0 ? null : ((int) $stats->rating_sum) / $count,
            );
        }

        return $result;
    }

    public static function toShopJson(int $id, string $name, string $description, int $reviewCount, mixed $averageRating): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'description' => $description,
            'review_count' => $reviewCount,
            'average_rating' => $averageRating === null ? null : round((float) $averageRating, 2),
        ];
    }

    public static function getShop(int $shopId): ?array
    {
        $shop = Shop::query()->where('id', $shopId)->first(['id', 'name', 'description']);
        if ($shop === null) {
            return null;
        }

        $stats = Review::query()
            ->where('shop_id', $shopId)
            ->selectRaw('COUNT(*) as review_count, SUM(rating) as rating_sum')
            ->first();

        $latest = Review::query()
            ->where('shop_id', $shopId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $count = (int) $stats->review_count;

        return [
            ...self::toShopJson(
                (int) $shop->id,
                $shop->name,
                $shop->description,
                $count,
                $count === 0 ? null : ((int) $stats->rating_sum) / $count,
            ),
            'reviews' => ReviewQuery::withDetails($latest),
        ];
    }

    public static function searchShopReviews(int $shopId, int $rating): ?array
    {
        $exists = Shop::query()->where('id', $shopId)->exists();
        if (! $exists) {
            return null;
        }

        $rows = Review::query()
            ->where('shop_id', $shopId)
            ->where('rating', $rating)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return ReviewQuery::withDetails($rows);
    }
}
