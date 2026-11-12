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
     * 解答: 店舗名・投稿者名・いいね数を whereIn / GROUP BY でまとめて取り、Map で付ける（最大3回）。
     *
     * @param  Collection<int, Review>|iterable<Review>  $rows
     */
    public static function withDetails(iterable $rows): array
    {
        $rows = Collection::make($rows)->values();
        if ($rows->isEmpty()) {
            return [];
        }

        $shops = Shop::query()
            ->whereIn('id', $rows->pluck('shop_id')->unique()->all())
            ->pluck('name', 'id');
        $users = User::query()
            ->whereIn('id', $rows->pluck('user_id')->unique()->all())
            ->pluck('name', 'id');
        $likeCounts = Like::query()
            ->whereIn('review_id', $rows->pluck('id')->all())
            ->selectRaw('review_id, COUNT(*) as c')
            ->groupBy('review_id')
            ->pluck('c', 'review_id');

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'id' => (int) $row->id,
                'shop_id' => (int) $row->shop_id,
                'shop_name' => $shops[$row->shop_id] ?? null,
                'user_id' => (int) $row->user_id,
                'user_name' => $users[$row->user_id] ?? null,
                'rating' => (int) $row->rating,
                'body' => $row->body,
                'created_at' => (string) ($row->getRawOriginal('created_at') ?? $row->created_at),
                'like_count' => (int) ($likeCounts[$row->id] ?? 0),
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
