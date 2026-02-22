<?php
/**
 * 【司令塔：ルーティング定義】
 * routes/web.php
 *
 * このファイルはシステム全体の「案内図」です。
 * WordPressやブラウザからのリクエスト（URL）を解析し、
 * 適切な窓口（コントローラー）へ交通整理を行う役割を担います。
 * * 主な役割：
 * 1. エンドポイント（URL）の定義
 * 2. ミドルウェアによるアクセス制限（IP制限など）の適用
 * 3. 開発用デバッグツールのルート管理
 *
 * @package Routes
 */

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\kxSearchController;
use App\Http\Controllers\Api\KxSearchApiController;

/*
|--------------------------------------------------------------------------
| 1. API Endpoints (WordPress連携用)
|--------------------------------------------------------------------------
| #2の構成に基づき、死活監視と各検索エンドポイントを統合
*/
Route::middleware(['ip.limit'])->prefix('api/kx')->group(function () {

    // 死活監視
    Route::get('/ping', function() {
        return response()->json(['status' => 'online']);
    });

    // 高度検索。
    Route::get('/search-advanced', [KxSearchApiController::class, 'searchAdvancedIds']);


    // 本文キーワード検索
    Route::get('/search', [KxSearchApiController::class, 'search']);

    // タイトル完全一致によるID取得
    Route::get('/ids', [KxSearchApiController::class, 'getIds']);

    // 子階層のID取得
    Route::get('/hierarchy-ids', [KxSearchApiController::class, 'getHierarchyIds']);
});

/*
|--------------------------------------------------------------------------
| 2. Web Search Tool (テスト用Blade画面)
|--------------------------------------------------------------------------
*/
Route::get('/search-tool', [kxSearchController::class, 'index']);
Route::post('/search-tool', [kxSearchController::class, 'showResults']);

/*
|--------------------------------------------------------------------------
| 3. Debug & Maintenance (開発用)
|--------------------------------------------------------------------------
*/
Route::prefix('kx-debug')->group(function () {
    // 環境確認 (DB名など)
    Route::get('/env', function () {
        return [
            'db_name' => config('database.connections.mysql.database'),
            'wp_url' => config('services.wordpress.url'),
        ];
    });

    // DB接続テスト (wp_kx_0)
    Route::get('/db-test', function () {
        $data = DB::table('wp_kx_0')->limit(5)->get();
        return view('kx_view', ['items' => $data]);
    });
    // ※ /wp-connect は /api/kx/ping に役割を譲渡し削除
});
