<?php
/**
 * 【実行作業員：ビジネスロジック・検索エンジン】
 * app/Services/KxKnowledgeSearchService.php
 *
 * システムの「知恵袋」であり、実務を一手に引き受ける実行部隊です。
 * コントローラー（受付）から渡された指示に基づき、WordPressデータベースの
 * 複雑な検索、除外判定、データ加工などの高度なロジックを遂行します。
 *
 * 主な役割：
 * 1. データベース（wp_posts等）への直接的なクエリ構築と実行
 * 2. タイトルの一致・除外（前方一致・後方一致等）の計算ロジック
 * 3. カテゴリやタグなどのタクソノミーに基づいた絞り込み処理
 * 4. 検索結果の加工（IDのみの抽出や抜粋文の生成など）
 *
 * @package App\Services
 */


namespace App\Services;

use Illuminate\Support\Facades\DB;

use App\Repositories\KxPostRepository;
/**
 * Class KxKnowledgeSearchService
 * * ナレッジベース（WordPress）の検索に関するビジネスロジックを管理するサービス。
 * コントローラーからのリクエストを受け取り、Repositoryを介したデータ取得の前後に、
 * ナレッジベース特有の整形処理（正規表現による置換など）を行う役割を担う。
 * 「調整ロジック」2025-12-25
 * * @package App\Services
 */
class KxKnowledgeSearchService {
    protected $postRepo;

    public function __construct(KxPostRepository $postRepo) {
        $this->postRepo = $postRepo;
    }

    /**
     * キーワードとカテゴリによる本文検索（抜粋データ付き）
     * 本文とタイトルを対象に検索し、キーワード周辺のテキストを抽出して返却します。
     * * @param string $keyword 検索キーワード（スペース区切りでAND、先頭 - で除外）
     * @param int|null $category_id 絞り込むカテゴリID
     * @return \Illuminate\Support\Collection 記事データ一式（ID, タイトル, 抜粋, リンク）
     */
    public function kxSearchContent($keyword, $category_id = null)
    {
        // 1. キーワードの分割
        $raw_words = preg_split('/\s+/u', trim($keyword));

        $include_words = [];
        $exclude_words = [];

        foreach ($raw_words as $w) {
            if (mb_substr($w, 0, 1) === '-') {
                $exclude_words[] = mb_substr($w, 1);
            } else {
                $include_words[] = $w;
            }
        }

        // ★修正1: 並び替え基準となる「最初の単語」を定義
        $firstWord = !empty($include_words) ? $include_words[0] : null;

        // 2. ベースクエリの構築
        $query = DB::table('wp_posts')
            ->where('wp_posts.post_status', 'publish')
            // ★修正2: post_modified（更新日）を select に追加
            ->select('wp_posts.ID', 'wp_posts.post_title', 'wp_posts.post_content', 'wp_posts.post_modified');

        // 3. カテゴリ絞り込み
        if (!empty($category_id)) {
            $query->join('wp_term_relationships', 'wp_posts.ID', '=', 'wp_term_relationships.object_id')
                ->join('wp_term_taxonomy', 'wp_term_relationships.term_taxonomy_id', '=', 'wp_term_taxonomy.term_taxonomy_id')
                ->where('wp_term_taxonomy.taxonomy', 'category')
                ->where('wp_term_taxonomy.term_id', $category_id);
        }

        // 4. AND検索と除外検索
        foreach ($include_words as $word) {
            $query->where(function($q) use ($word) {
                $q->where('wp_posts.post_content', 'LIKE', "%{$word}%")
                ->orWhere('wp_posts.post_title', 'LIKE', "%{$word}%");
            });
        }
        foreach ($exclude_words as $word) {
            $query->where('wp_posts.post_content', 'NOT LIKE', "%{$word}%");
        }

        // 5. 並び替え
        if ($firstWord) {
            $query->orderByRaw("
                CASE
                    WHEN wp_posts.post_title LIKE ? THEN 1
                    WHEN wp_posts.post_title LIKE ? THEN 2
                    ELSE 3
                END ASC
            ", [
                '%' . $firstWord,
                '%' . $firstWord . '%'
            ]);
        }

        // 更新日が新しい順を第2優先に
        $query->orderBy('wp_posts.post_modified', 'desc');

        // 6. 結果の取得と加工
        return $query->get()->map(function ($post) use ($include_words) {
            $content = strip_tags($post->post_content);
            $pos = null;
            foreach ($include_words as $w) {
                $p = mb_strpos($content, $w);
                if ($p !== false) {
                    $pos = $p;
                    break;
                }
            }

            if ($pos === null) {
                $excerpt = mb_substr($content, 0, 60);
            } else {
                $start = max(0, $pos - 30);
                $excerpt = mb_substr($content, $start, 60);
            }

            $wp_base_url = config('services.wordpress.url');

            return [
                'id'      => $post->ID,
                'title'   => $post->post_title,
                'excerpt' => $excerpt,
                'link'    => $wp_base_url . '?p=' . $post->ID,
            ];
        });
    }

    /**
     * 【高度な絞り込み版】記事IDのみを高速返却
     * タイトルの一致モード指定や、特定タグ・カテゴリの包含・除外をSQLレベルで高速実行します。
     * * @param array $params [title, title_mode, title_exclude, category_id, tag_id 等]
     * @return \Illuminate\Support\Collection 該当する記事IDの配列
     */
    public function kxSearchIdsAdvanced(array $params)
    {
        // WordPress特有のキー名をLaravel用へ内部変換
        $params['title'] = $params['title'] ?? ($params['search'] ?? null);
        $params['category_id'] = $params['category_id'] ?? ($params['cat'] ?? null);

        $query = DB::table('wp_posts')
            ->where('post_status', 'publish')
            ->select('wp_posts.ID');

        // --- 1. タイトルの一致・除外 ---
        if (!empty($params['title'])) {
            $mode = $params['title_mode'] ?? 'both';
            $this->applyTitleCondition($query, $params['title'], $mode, false);
        }
        if (!empty($params['title_exclude'])) {
            $exMode = $params['title_exclude_mode'] ?? 'both';
            $this->applyTitleCondition($query, $params['title_exclude'], $exMode, true);
        }

        // --- 2. カテゴリ・タグの絞り込み (一致・除外) ---
        $this->applyTaxonomyCondition($query, $params['category_id'] ?? null, false);
        $this->applyTaxonomyCondition($query, $params['category_exclude_id'] ?? null, true);
        $this->applyTaxonomyCondition($query, $params['tag_id'] ?? null, false);
        $this->applyTaxonomyCondition($query, $params['tag_exclude_id'] ?? null, true);

        // 並び替えを削った最速仕様
        return $query->pluck('ID');
    }


    /**
     * 内部関数：タイトル検索条件（LIKE/NOT LIKE）の動的構築
     * 前方一致・後方一致・部分一致・完全一致の切り替えと除外判定を制御します。
     * * @param \Illuminate\Database\Query\Builder $query クエリビルダー
     * @param string $text 検索する文字列
     * @param string $mode 一致モード（prefix, suffix, exact, both）
     * @param bool $isExclude trueの場合は除外条件（NOT）として適用
     */
    private function applyTitleCondition($query, $text, $mode, $isExclude)
    {
        $operator = $isExclude ? 'NOT LIKE' : 'LIKE';
        if ($mode === 'exact') {
            $query->where('post_title', ($isExclude ? '!=' : '='), $text);
        } else {
            $pattern = match($mode) {
                'prefix' => "{$text}%",
                'suffix' => "%{$text}",
                default  => "%{$text}%",
            };
            $query->where('post_title', $operator, $pattern);
        }
    }

    /**
     * 内部関数：タクソノミー（カテゴリ・タグ）条件の適用
     * サブクエリを使用して、特定の用語を含む（または含まない）記事をフィルタリングします。
     * * @param \Illuminate\Database\Query\Builder $query クエリビルダー
     * @param int|null $id タームタクソノミーID
     * @param bool $isExclude trueの場合は除外条件（whereNotIn）として適用
     */
    private function applyTaxonomyCondition($query, $id, $isExclude)
    {
        if (empty($id)) return;
        $subQuery = DB::table('wp_term_relationships')->where('term_taxonomy_id', $id)->select('object_id');
        $isExclude ? $query->whereNotIn('wp_posts.ID', $subQuery) : $query->whereIn('wp_posts.ID', $subQuery);
    }

    /**
     * タイトル完全一致による記事IDの取得
     * 指定されたタイトルの記事IDをリポジトリ経由で直接取得します。
     * * @param string $rawTitle 検索対象のタイトル文字列
     * @return \Illuminate\Support\Collection 該当する記事IDの配列
     */
    public function searchIds(string $rawTitle) {
        // 将来的に kxx クラスのように、ここで $rawTitle を整形する
        // 例: $cleanTitle = preg_replace('/[0-9].*》/', '', $rawTitle);

        return $this->postRepo->findIdsByTitle($rawTitle);
    }

    /**
     * 指定タイトルを親とする子階層の記事ID群を取得
     * WordPressのタイトル命名規則（例：親タイトル≫子タイトル）に基づいた階層を解析します。
     * * @param string $currentTitle 親となる記事のタイトル
     * @return \Illuminate\Support\Collection 子階層の記事ID配列
     */
    public function getChildHierarchyIds(string $currentTitle) {
        // 「現タイトル≫」という接頭辞を作成
        $prefix = $currentTitle . '≫';

        // リポジトリに検索を依頼
        return $this->postRepo->findIdsByHierarchy($prefix);
    }
}
