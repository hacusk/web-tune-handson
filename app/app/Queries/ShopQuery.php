<?php

namespace App\Queries;

use App\Models\Review;
use App\Models\Shop;

class ShopQuery
{
    public const SHOPS_PER_PAGE = 100;

    // 必修: このメソッドのSQL回数を減らしてください。戻り値と並び順は維持します。
    public static function listShops(int $page): array
    {
        $shops = Shop::query()
            ->orderBy('id')
            ->offset(($page - 1) * self::SHOPS_PER_PAGE)
            ->limit(self::SHOPS_PER_PAGE)
            ->get(['id', 'name', 'description']);

        $result = [];
        foreach ($shops as $shop) {
            // 意図的な遅さ: 店舗ごとにレビューを小さな塊で繰り返し取得し、アプリ側で件数・平均を出す。
            $reviewCount = 0;
            $ratingSum = 0.0;
            Review::query()
                ->where('shop_id', $shop->id)
                ->orderBy('id')
                ->chunkById(5, function ($chunk) use (&$reviewCount, &$ratingSum) {
                    foreach ($chunk as $review) {
                        $reviewCount++;
                        $ratingSum += $review->rating;
                    }
                });
            $result[] = self::toShopJson(
                (int) $shop->id,
                $shop->name,
                $shop->description,
                $reviewCount,
                $reviewCount === 0 ? null : $ratingSum / $reviewCount,
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
