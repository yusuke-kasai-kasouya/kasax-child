<?php
/**
 * [Path]: inc\core\class-kx-content-filter.php
 * [Role]: the_content 等のフィルターフックを通じ、Ghost(幽霊)の召喚やMarkdown変換を制御する。
 */

namespace Kx\Core;

use Kx\Core\DynamicRegistry as Dy;

class ContentFilter {

    /**
     * サイトのデザインとしてのヘッダーが表示される前に、何らかのロジックを実行したい場合は、以下のフックを使います。
     * 用途: 特定の条件（ページIDなど）に基づいて、ヘッダーが読み込まれる直前に処理を挟む。
     *
     */
    public static function ContextManager_template_redirect(){
        if (is_singular()) {
            ContextManager::sync(get_the_ID());
        }
    }

    /**
     * contents介入
     * 司令塔（メインフィルタ）
     *
     * @param [type] $text
     * @return void
     */
    public static function the_content_8($text){
        //echo $text;

        $post_id = get_the_ID();
        if(!$post_id) return $text; // voidではなく$textを返す

        // キー名を新構造用に変更（旧キャッシュとの衝突回避のため推奨）
        $cache_key = 'kx_cache_the_content_pack_' . $post_id;

        // --- 1. ContextManager ---
        ContextManager::sync($post_id);

        /*
        // --- 2. キャッシュ判定
        $should_cache = self::the_content_cache_post( $post_id );

        // --- 3. キャッシュからの復元 ---
        $should_cache = null;//★★★★★ テスト中は機能アウト、本番はここを有効化
        if ( $should_cache ) {
            $cached_data = get_transient( $cache_key );
            if ( is_array($cached_data) && !empty($cached_data['html']) ) {

                // キャッシュからDyへアウトライン情報を復元（副作用として実行）
                if (!empty($cached_data['outline'])) {
                    OutlineManager::restore_to_dy($post_id, $cached_data['outline']);
                }
                return $cached_data['html'];
            }
        }
        */

        // --- 4. コンパイルと解析 ---
        //$final_text = kxad_the_content_compile( $text );
        $final_text = \Kx\Core\ContentProcessor::compile($text,$post_id);

        // 解析とアンカー注入（この中で Dy::set される）
        $final_text = OutlineManager::analyze_and_inject($final_text, $post_id, 'a');

        // --- 5. 保存 ---
        /*
        if ( $should_cache ) {
            // Dyから今回の解析結果を吸い出す
            $outline_data = OutlineManager::get_data_for_cache($post_id);

            $cache_pack = [
                'html'    => $final_text,
                'outline' => $outline_data,
            ];
            set_transient( $cache_key, $cache_pack, 60 * DAY_IN_SECONDS );
        }
        */

        return $final_text;

    }

    /**
     * 投稿表示時にコンテンツの統合（コンソリデート）を自動実行する
     * * 指定された投稿ID（consolidated_from）からデータを集約し、現在の投稿内容を更新します。
     * 更新が発生した場合は、DBから最新のコンテンツを再取得し、整形した状態で返します。
     * * @param string $text 現在表示しようとしている投稿本文
     * @return string 整形済みの投稿本文（更新時は最新版、それ以外は元の$text）
     */
    public static function the_content_9($text){
         // 1. メインクエリかつループ内であることを確認（サイドバーや管理画面での誤作動防止）
        if ( !is_main_query() || !in_the_loop() ) {
            return $text;
        }

        $post_id = get_the_ID();

        // 2. 統合元となるIDのキャッシュを取得
        $consolidated_from_id = Dy::get_content_cache($post_id, 'consolidated_from') ?? null;

        if ($consolidated_from_id) {
            /**
             * Kx_Consolidator::run
             * 内部でタイムスタンプを比較し、必要時のみ wp_update_post でDBを書き換える。
             * @return bool $updated 更新があった場合は true、不要だった場合は false を返す。
             */
            $updated = \Kx\Core\Kx_Consolidator::run($consolidated_from_id, $post_id,  ['dest' => 'db']);


            // --- 色の微調整用設定（ColorManagerのHSL変数に依存するため、文字色のみ指定） ---
            $text_color = '#fff'; // 文字色（必要に応じてHSL変数化も可能）

            $edit_url = get_permalink($consolidated_from_id);
            $source_title = get_the_title($consolidated_from_id);

            // ColorManagerから対象記事の色情報を取得
            $colormgr = Dy::get_color_mgr($consolidated_from_id);
            $style_base = $colormgr['style_base']; // "--kx-hue: 220; --kx-sat: 15%; ..." 等が含まれる

            // 背景色は指示通りHSL形式に統一。透明度（lum）などは必要に応じて微調整
            $bg_style = 'background-color: hsla(var(--kx-hue), var(--kx-sat), var(--kx-lum),0.5);';
            // 境界線もHSL変数を応用すると、より背景に馴染む（例: 輝度を-10%した色など）
            $border_style = 'border: 1px solid rgba(var(--kx-rgb), 0.2);';

            // style属性に直接変数を流し込む
            $link_html = sprintf(
                '<div class="kx-consolidated-link" style="%1$s">
                    <a href="%2$s" style="%1$s %3$s color: %4$s; %5$s">
                        <span class="icon">⇄</span> 統合元: %6$s (ID:%7$d)
                    </a>
                </div>',
                $style_base,                        // %1$s: 共通変数定義
                esc_url($edit_url),                 // %2$s
                $bg_style,                          // %3$s: HSL背景指定
                esc_attr($text_color),              // %4$s
                $border_style,                      // %5$s
                esc_html($source_title),            // %6$s
                (int)$consolidated_from_id          // %7$d
            );

            // --- 以下、既存の更新ロジックに結合 ---
            if ($updated === true) {
                $latest_post = get_post($post_id);
                $content = $latest_post->post_content;//$link_html .
                return apply_filters('the_content', $content);
            }
            $text = $link_html . $text;

        }

        // 更新がない、あるいは統合対象でない場合はそのままのテキストを返す
        return $text;

    }

    /**
     * ブラウザーのタブのタイトルを変更。
     * H1-Browser-タブ（フック）
     * @return void
     */
    public static function browser_title(){

        if( array_key_exists( 's' , $_GET ) ) //$_GET['s']
        {
            return	'” '.get_search_query ().' ” 検索';
        }
        elseif( array_key_exists(	'cat', $_GET) 	)	//$_GET['cat']
        {
            return	'カテゴリー';
        }
        elseif( array_key_exists(	'tag', $_GET) 	)	//	elseif(	$_GET['tag']	) :
        {
            return	'タグ';
        }
        else
        {
            return	\kx\Utils\Toolbox::generate_formatted_tab_title();
        }
    }

    /**
     * タイトル末尾一致を優先する検索順位調整
     *
     * 検索語がタイトルの末尾に一致する投稿に高いスコアを与え、
     * 検索結果の優先順位を制御する。条件に応じてカスタムスコアを付与し、
     * ORDER BY句に反映する。メインクエリの検索時にのみ適用。
     *
     * @param array     $clauses SQLクエリの各句（SELECT, WHERE, ORDER BYなど）
     * @param WP_Query  $query   検索クエリオブジェクト
     * @return array    修正後のクエリ句配列
     */
    public static function Prioritize_title_endswith_search( $clauses, $query ) {
        global $wpdb;

        if ( is_admin() || !$query->is_main_query() || !$query->is_search() ) {
            return $clauses;
        }

        $search_term = $query->get('s');
        if ( empty( $search_term ) ) return $clauses;

        // スコアリング: 文末一致 → スコア1、部分一致 → 2、完全一致 → 3、それ以外 → 4
        $search_esc = $wpdb->esc_like( $search_term );
        $clauses['fields'] .= ",
            CASE
                WHEN {$wpdb->posts}.post_title LIKE " . $wpdb->prepare( '%s', '%' . $search_esc ) . " THEN 1
                WHEN {$wpdb->posts}.post_title LIKE " . $wpdb->prepare( '%s', '%' . $search_esc . '%' ) . " THEN 2
                WHEN {$wpdb->posts}.post_title = " . $wpdb->prepare( '%s', $search_esc ) . " THEN 3
                ELSE 4
            END AS custom_relevance,
            CHAR_LENGTH({$wpdb->posts}.post_title) AS title_length";

        // スコア → タイトルの長さ（短い順）→ 投稿日（新しい順）
        $clauses['orderby'] = "custom_relevance ASC, title_length ASC, {$wpdb->posts}.post_date DESC";

        return $clauses;
    }

    /**
     * footer
     *
     */
    public static function footer_hook() {
        // すべての処理（ショートコード等）が終わった後に render を実行
        if (class_exists('\Kx\Utils\KxMessage') && !is_404()) {
            echo \Kx\Utils\KxMessage::render();
        }
    }


    /**
     * 仮想ノード用テンプレートの割り当て
     * * @param string $template オリジナルのテンプレートパス
     * @return string 決定されたテンプレートパス
     */
    public static function virtual_node($template) {

        $path = self::get_virtual_path_from_url();

        if (!$path) {
            return $template;
        }

        // デバッグ：ここを通っているか確認
        // die('Path found: ' . $path);

        $hierarchy_data = \Kx\Database\Hierarchy::get_node_by_path($path);

        if ($hierarchy_data && (int)$hierarchy_data['is_virtual'] === 1) {
            \Kx\Core\DynamicRegistry::set('current_virtual_node', $hierarchy_data);

            // パス修正（スラッシュに統一）
            $virtual_template = get_stylesheet_directory() . '/templates/components/navigation/virtual-node.php';

            if (file_exists($virtual_template)) {
                // WordPressに404ではないことを通知
                global $wp_query;
                $wp_query->is_404 = false;
                return $virtual_template;
            }
        }

        return $template;
    }

    /**
     * virtualパスのURL。
     */
    private static function get_virtual_path_from_url(): ?string {
        // 1. リライトルール経由の取得を試みる
        $path = get_query_var('kx_virtual_path');
        if ($path) return urldecode($path);

        // 2. 直URLからの抽出を試みる（バックアップ）
        if (preg_match('/hierarchy\/([^\/?]+)/', $_SERVER['REQUEST_URI'], $matches)) {
            return urldecode($matches[1]);
        }

        return null;
    }

}