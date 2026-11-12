<?php

namespace App\Queries;

use App\Models\Review;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RankingQuery
{
    // 解答: 仕様の「最大5秒前の集計でよい」を使い、結果全体を短時間キャッシュする。
    public static function getRanking(): array
    {
        // file ドライバ（compose の CACHE_STORE=file）ならリクエストをまたげる
        return Cache::store('file')->remember('ranking', 5, static function (): array {
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
        });
    }
}
