<?php

namespace App\Queries;

use App\Models\Like;
use App\Models\Review;
use App\Models\User;

class UserQuery
{
    public static function getUser(int $userId): ?array
    {
        $user = User::query()->where('id', $userId)->first(['id', 'name']);
        if ($user === null) {
            return null;
        }

        $reviewCount = Review::query()->where('user_id', $userId)->count();
        $receivedLikes = Like::query()
            ->join('reviews', 'reviews.id', '=', 'likes.review_id')
            ->where('reviews.user_id', $userId)
            ->count();

        $latest = Review::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return [
            'id' => (int) $user->id,
            'name' => $user->name,
            'review_count' => (int) $reviewCount,
            'received_likes' => (int) $receivedLikes,
            'reviews' => ReviewQuery::withDetails($latest),
        ];
    }
}
