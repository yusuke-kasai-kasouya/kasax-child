<?php
/**
 * 【受付窓口：APIリクエスト制御】
 * app/Http/Controllers/Api/KxSearchApiController.php
 *
 * WordPressからのリクエストを最初に受け取る「総合受付」です。
 * 送られてきたデータ（キーワード、ID、検索モードなど）に不備がないか確認し、
 * 実際の複雑な検索作業は実行担当である「KxKnowledgeSearchService」に依頼します。
 * * 主な役割：
 * 1. WordPressからのリクエスト（GET/POST）の受信
 * 2. 入力データのバリデーション（必須チェックなど）
 * 3. サービス層への作業依頼と、結果のJSON形式での返却
 *
 * @package App\Http\Controllers\Api
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\KxKnowledgeSearchService;
use Illuminate\Http\Request;


/**
 * Class KxSearchApiController
 *
 * WordPressの独自テーマ（kxxクラス等）からのAPIリクエストを処理するコントローラー。
 * HTTPリクエストのバリデーション（入力チェック）を行い、
 * 実際の検索ロジックは KxKnowledgeSearchService に委ねる。
 * 2025-12-25
 *
 * @package App\Http\Controllers\Api
 */
class KxSearchApiController extends Controller {
    protected $searchService;

    public function __construct(KxKnowledgeSearchService $searchService) {
        $this->searchService = $searchService;
    }


    /**
     * API：キーワードによる本文検索
     * 本文またはタイトルにキーワードを含む記事を抽出し、抜粋データと共に返却します。
     *
     * @param Request $request [keyword: 検索語, category_id: 絞り込みID]
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request) {
        $keyword = $request->query('keyword');
        $category_id = $request->query('category_id');

        // キーワードがない場合は空を返す（またはエラー）
        if (empty($keyword)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Keyword is required'
            ], 400);
        }

        // サービスを呼び出して検索実行
        $results = $this->searchService->kxSearchContent($keyword, $category_id);

        return response()->json([
            'status' => 'success',
            'count'  => $results->count(),
            'keyword' => $keyword,
            'data'   => $results
        ]);
    }


    /**
     * API：高度なタイトル検索（IDのみ返却）
     * 前方一致・後方一致・除外指定などの詳細な条件でタイトルを検索し、ID配列のみを返却します。
     *
     * @param Request $request [title, title_mode, title_exclude, category_id, tag_id 等]
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchAdvancedIds(Request $request) {
        // 全てのクエリパラメータをサービスに渡す
        $ids = $this->searchService->kxSearchIdsAdvanced($request->all());

        return response()->json([
            'status' => 'success',
            'ids'    => $ids,
            'count'  => $ids->count()
        ]);
    }


    /**
     * API：タイトル完全一致によるID特定
     * 指定されたタイトルと正確に一致する記事のIDを返却します。
     *
     * @param Request $request [title: 検索タイトル]
     * @return \Illuminate\Http\JsonResponse
     */
    public function getIds(Request $request) {
        $title = $request->query('title');

        if (!$title) {
            return response()->json(['error' => 'Title is required'], 400);
        }

        $ids = $this->searchService->searchIds($title);

        // とりあえず echo (レスポンス) する
        // WordPress側で扱いやすいよう、カンマ区切りの文字列などで返すのも手です
        return response()->json([
            'ids' => $ids,
            'count' => $ids->count()
        ]);
    }


    /**
     * API：子階層の記事ID一覧取得
     * 指定したタイトルを親（接頭辞）に持つ、階層構造下の記事ID群を取得します。
     *
     * @param Request $request [title: 親となる記事タイトル]
     * @return \Illuminate\Http\JsonResponse
     */
    public function getHierarchyIds(Request $request) {
        $title = $request->query('title');

        if (empty($title)) {
            return response()->json(['status' => 'error', 'message' => 'Title is required'], 400);
        }

        $ids = $this->searchService->getChildHierarchyIds($title);

        return response()->json([
            'status' => 'success',
            'data'   => [
                'parent_title' => $title,
                'ids'          => $ids,
                'count'        => $ids->count()
            ]
        ]);
    }
}
