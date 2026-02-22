<?php
/**
 * app/Http/Controllers/kxSearchController.php
 */

namespace App\Http\Controllers;

use App\Services\KxKnowledgeSearchService;
use Illuminate\Http\Request;

class kxSearchController extends Controller
{
    protected $searchService;

    public function __construct(KxKnowledgeSearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    /**
     * 検索フォームの表示
     */
    public function index()
    {
        return view('search_form');
    }

    /**
     * 検索実行と結果表示 (Blade用)
     */
    public function showResults(Request $request)
    {
        $keyword = $request->input('keyword');
        $category_id = $request->input('category_id');

        // Serviceのロジックを呼び出し
        $results = $this->searchService->kxSearchContent($keyword, $category_id);

        return view('search_results', [
            'results' => $results,
            'keyword' => $keyword,
            'count'   => $results->count(),
        ]);
    }
}
