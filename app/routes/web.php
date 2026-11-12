<?php

use App\Http\Middleware\MeasureQueries;
use App\Queries\InitializeQuery;
use App\Queries\RankingQuery;
use App\Queries\ReviewQuery;
use App\Queries\ShopQuery;
use App\Queries\UserQuery;
use App\Support\IntParam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->file(public_path('index.html'));
});

Route::get('/health', function () {
    DB::select('SELECT id FROM seed_ready');

    return response()->json(['ok' => true]);
});

Route::post('/initialize', function () {
    InitializeQuery::run();

    return response()->json(['ok' => true]);
});

Route::get('/api/shops', function (Request $request) {
    $page = $request->query('page') === null
        ? 1
        : IntParam::inRange($request->query('page'), 1, 10000);
    if ($page === null) {
        return response()->json(['error' => 'page は1以上の整数で指定してください'], 400);
    }

    return MeasureQueries::json($request, fn () => ShopQuery::listShops($page));
});

Route::get('/api/shops/{id}', function (Request $request, string $id) {
    $shopId = IntParam::inRange($id, 1, IntParam::MAX_ID);
    if ($shopId === null) {
        return response()->json(['error' => '店舗IDが不正です'], 400);
    }

    return MeasureQueries::json($request, fn () => ShopQuery::getShop($shopId));
});

Route::get('/api/shops/{id}/reviews', function (Request $request, string $id) {
    $shopId = IntParam::inRange($id, 1, IntParam::MAX_ID);
    $rating = IntParam::inRange($request->query('rating'), 1, 5);
    if ($shopId === null || $rating === null) {
        return response()->json(['error' => '店舗IDと rating（1〜5）を指定してください'], 400);
    }

    return MeasureQueries::json($request, fn () => ShopQuery::searchShopReviews($shopId, $rating));
});

Route::post('/api/shops/{id}/reviews', function (Request $request, string $id) {
    $shopId = IntParam::inRange($id, 1, IntParam::MAX_ID);
    $body = json_decode($request->getContent(), true);
    if (! is_array($body)) {
        $body = [];
    }
    $userId = IntParam::inRange($body['user_id'] ?? null, 1, IntParam::MAX_ID);
    $rating = IntParam::inRange($body['rating'] ?? null, 1, 5);
    $text = isset($body['body']) && is_string($body['body']) ? trim($body['body']) : '';
    if ($shopId === null || $userId === null || $rating === null || $text === '' || mb_strlen($text) > 200) {
        return response()->json(['error' => 'user_id、rating（1〜5）、body（1〜200文字）を指定してください'], 400);
    }

    return MeasureQueries::json(
        $request,
        fn () => ReviewQuery::postReview($shopId, $userId, $rating, $text),
        201,
    );
});

Route::post('/api/reviews/{id}/likes', function (Request $request, string $id) {
    $reviewId = IntParam::inRange($id, 1, IntParam::MAX_ID);
    $body = json_decode($request->getContent(), true);
    if (! is_array($body)) {
        $body = [];
    }
    $userId = IntParam::inRange($body['user_id'] ?? null, 1, IntParam::MAX_ID);
    if ($reviewId === null || $userId === null) {
        return response()->json(['error' => 'レビューIDと user_id を指定してください'], 400);
    }
    $result = ReviewQuery::likeReview($reviewId, $userId);
    if ($result === 'not_found') {
        return response()->json(['error' => 'Not found'], 404);
    }
    if ($result === 'duplicate') {
        return response()->json(['error' => '既にいいねしています'], 409);
    }

    return response()->json(['ok' => true], 201);
});

Route::get('/api/ranking', function (Request $request) {
    return MeasureQueries::json($request, fn () => RankingQuery::getRanking());
});

Route::get('/api/users/{id}', function (Request $request, string $id) {
    $userId = IntParam::inRange($id, 1, IntParam::MAX_ID);
    if ($userId === null) {
        return response()->json(['error' => 'ユーザーIDが不正です'], 400);
    }

    return MeasureQueries::json($request, fn () => UserQuery::getUser($userId));
});

Route::fallback(function () {
    return response()->json(['error' => 'Not found'], 404);
});
