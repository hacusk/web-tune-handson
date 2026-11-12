<?php

namespace App\Queries;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class InitializeQuery
{
    // ベンチマーク開始時に呼ばれ、ベンチが追加したデータを消して初期データに戻す。
    // キャッシュや集計用の列を追加した場合は、ここで作り直すこと。
    public static function run(): void
    {
        DB::table('likes')->where('id', '>', 200000)->delete();
        DB::table('reviews')->where('id', '>', 200000)->delete();
        Cache::store('file')->forget('ranking');
    }
}
