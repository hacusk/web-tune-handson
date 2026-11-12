<?php

namespace App\Queries;

use App\Models\Like;
use App\Models\Review;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReviewQuery
{
    /**
     * レビューに店舗名・投稿者名・いいね数を付ける。
     * 発展: ループ内で1件ずつ取ると、店舗詳細・ランキング・ユーザーページの SQL が増える。
     *
     * @param  Collection<int, Review>|iterable<Review>  $rows
     */
    public static function withDetails(iterable $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            // 意図的な遅さ: レビューごとに shops / users / likes を取りにいく。
            $shopName = Shop::query()->where('id', $row->shop_id)->value('name');
            $userName = User::query()->where('id', $row->user_id)->value('name');
            $likeCount = Like::query()->where('review_id', $row->id)->count();
            $result[] = [
                'id' => (int) $row->id,
                'shop_id' => (int) $row->shop_id,
                'shop_name' => $shopName,
                'user_id' => (int) $row->user_id,
                'user_name' => $userName,
                'rating' => (int) $row->rating,
                'body' => $row->body,
                'created_at' => (string) ($row->getRawOriginal('created_at') ?? $row->created_at),
                'like_count' => (int) $likeCount,
            ];
        }

        return $result;
    }

    public static function postReview(int $shopId, int $userId, int $rating, string $body): ?array
    {
        if (! Shop::query()->where('id', $shopId)->exists()) {
            return null;
        }
        if (! User::query()->where('id', $userId)->exists()) {
            return null;
        }

        $id = DB::table('reviews')->insertGetId([
            'shop_id' => $shopId,
            'user_id' => $userId,
            'rating' => $rating,
            'body' => $body,
            'created_at' => DB::raw('NOW()'),
        ]);

        $row = Review::query()->where('id', $id)->first();

        return self::withDetails([$row])[0];
    }

    /** @return 'ok'|'not_found'|'duplicate' */
    public static function likeReview(int $reviewId, int $userId): string
    {
        if (! Review::query()->where('id', $reviewId)->exists()) {
            return 'not_found';
        }
        if (! User::query()->where('id', $userId)->exists()) {
            return 'not_found';
        }

        try {
            DB::table('likes')->insert([
                'review_id' => $reviewId,
                'user_id' => $userId,
                'created_at' => DB::raw('NOW()'),
            ]);
        } catch (QueryException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                return 'duplicate';
            }
            throw $e;
        }

        return 'ok';
    }
}
