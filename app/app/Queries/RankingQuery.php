<?php

namespace App\Queries;

use App\Models\Review;
use Illuminate\Support\Facades\DB;

class RankingQuery
{
    // いいね数の多いレビュー上位10件。
    // 発展: 仕様上、最大5秒前の集計結果を返してもよい（毎回集計し直さなくてもよい）。
    public static function getRanking(): array
    {
        $ids = DB::table('likes')
            ->select('review_id')
            ->groupBy('review_id')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->orderBy('review_id')
            ->limit(10)
            ->pluck('review_id')
            ->all();

        if ($ids === []) {
            return [];
        }

        $byId = Review::query()->whereIn('id', $ids)->get()->keyBy('id');
        $ordered = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $ordered[] = $byId[$id];
            }
        }

        return ReviewQuery::withDetails($ordered);
    }
}
