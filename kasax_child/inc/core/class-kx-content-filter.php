<?php
/**
 * [Path]: inc\core\class-kx-content-filter.php
 * [Role]: the_content 等のフィルターフックを通じ、統合同期やMarkdown変換を制御する。
 */

namespace Kx\Core;

use Dy;

class ContentFilter {

    /**
     * テンプレートリダイレクト時のコンテキスト同期
     *
     * ページ表示の直前に実行され、IDに基づいたパス解析やキャッシュ準備を行う。
     *
     * @return void
     */
    public static function ContextManager_template_redirect(): void {
        if (is_singular()) {
            $post_id = get_the_ID();
            if ($post_id) {
                ContextManager::sync($post_id);
            }
        }
    }

    /**
     * コンテンツ解析フィルタ（フェーズ1）
     *
     * Markdownコンパイルとアウトライン（目次）アンカーの注入を行う。
     *
     * @param string $text 変換前の投稿本文
     * @return string 変換後の投稿本文
     */
    public static function the_content_8(string $text): string {
        $post_id = get_the_ID();
        if(!$post_id) return $text; // voidではなく$textを返す

        // --- 1. ContextManager(必須) ---
        ContextManager::sync($post_id);

        // --- 2. コンパイルと解析 ---
        $final_text = \Kx\Core\ContentProcessor::compile($text,$post_id);

        // 解析とアンカー注入（この中で Dy::set される）
        return OutlineManager::analyze_and_inject($final_text, $post_id, 'a');
    }

    /**
     * コンテンツ統合フィルタ（フェーズ2：Orchestrator）
     *
     * 自分が「Target（出力先）」ならPull同期を、「Source（指示書）」ならPush同期を試みる。
     *
     * @param string $text 現在の投稿本文
     * @return string 同期処理後の投稿本文
     */
    public static function the_content_9(string $text): string {
        if (!is_main_query() || !in_the_loop()) {
            return $text;
        }

        $post_id = get_the_ID();
        if (!$post_id) {
            return $text;
        }

        // Pull型同期（自分がターゲットの場合）
        $text = self::pull_sync_to_target($post_id, $text);

        // Push型同期（自分がソースの場合）
        self::push_sync_from_source($post_id);

        return $text;
    }

    /**
     * Pull型同期：指示書（Source）からデータを集約し、自分（Target）を更新する
     *
     * @param int    $post_id 現在の投稿ID
     * @param string $text    現在のコンテンツ
     * @return string 更新・整形後のコンテンツ
     */
    public static function pull_sync_to_target(int $post_id, string $text): string {
        $source_id = Dy::get_content_cache($post_id, 'consolidated_from');
        if (!$source_id) {
            return $text;
        }

        $updated = \Kx\Core\Kx_Consolidator::run((int)$source_id, $post_id, ['dest' => 'db']);
        $link_html = self::generate_consolidated_link_ui((int)$source_id);

        if ($updated === true) {
            $latest_post = get_post($post_id);
            return apply_filters('the_content', $latest_post ? $latest_post->post_content : '');
        }

        return $link_html . $text;
    }

    /**
     * Push型同期：自分（Source）の変更を、出力先（Target）へ強制反映する
     *
     * @param int $post_id 現在の投稿ID（指示書側）
     * @return void
     */
    public static function push_sync_from_source(int $post_id): void {
        $target_id = Dy::get_content_cache($post_id, 'consolidated_to');
        if ($target_id) {
            \Kx\Core\Kx_Consolidator::run($post_id, (int)$target_id, ['dest' => 'db']);
        }
    }

    /**
     * 統合情報のリンクUI（HTML）を生成する内部ヘルパー
     *
     * @param int $source_id 統合元の投稿ID
     * @return string 生成されたHTML
     */
    private static function generate_consolidated_link_ui(int $source_id): string {
        $edit_url = get_permalink($source_id);
        $source_title = get_the_title($source_id);
        $text_color = '#fff';

        $colormgr = Dy::get_color_mgr($source_id);
        $style_base = $colormgr['style_base'] ?? '';

        $bg_style = 'background-color: hsla(var(--kx-hue), var(--kx-sat), var(--kx-lum), 0.5);';
        $border_style = 'border: 1px solid rgba(var(--kx-rgb), 0.2);';

        return sprintf(
            '<div class="kx-consolidated-link" style="%1$s">
                <a href="%2$s" style="%1$s %3$s color: %4$s; %5$s">
                    <span class="icon">⇄</span> 統合元: %6$s (ID:%7$d)
                </a>
            </div>',
            $style_base,
            esc_url($edit_url),
            $bg_style,
            esc_attr($text_color),
            $border_style,
            esc_html($source_title),
            $source_id
        );
    }

    /**
     * ブラウザタブタイトルの生成
     *
     * @return string
     */
    public static function browser_title(): string {
        if (isset($_GET['s'])) {
            return '” ' . get_search_query() . ' ” 検索';
        } elseif (isset($_GET['cat'])) {
            return 'カテゴリー';
        } elseif (isset($_GET['tag'])) {
            return 'タグ';
        }
        return \kx\Utils\Toolbox::generate_formatted_tab_title();
    }

    /**
     * タイトル末尾一致を優先する検索順位調整
     *
     * @param array     $clauses SQLクエリ句
     * @param \WP_Query $query   クエリオブジェクト
     * @return array
     */
    public static function Prioritize_title_endswith_search(array $clauses, \WP_Query $query): array {
        global $wpdb;

        if (is_admin() || !$query->is_main_query() || !$query->is_search()) {
            return $clauses;
        }

        $search_term = $query->get('s');
        if (empty($search_term)) {
            return $clauses;
        }

        $search_esc = $wpdb->esc_like($search_term);
        $clauses['fields'] .= $wpdb->prepare(",
            CASE
                WHEN {$wpdb->posts}.post_title LIKE %s THEN 1
                WHEN {$wpdb->posts}.post_title LIKE %s THEN 2
                WHEN {$wpdb->posts}.post_title = %s THEN 3
                ELSE 4
            END AS custom_relevance,
            CHAR_LENGTH({$wpdb->posts}.post_title) AS title_length",
            '%' . $search_esc,
            '%' . $search_esc . '%',
            $search_term
        );

        $clauses['orderby'] = "custom_relevance ASC, title_length ASC, {$wpdb->posts}.post_date DESC";
        return $clauses;
    }

    /**
     * フッターフック
     *
     * @return void
     */
    public static function footer_hook(): void {
        if (class_exists('\Kx\Utils\KxMessage') && !is_404()) {
            echo \Kx\Utils\KxMessage::render();
        }
    }

    /**
     * 仮想ノード用テンプレートの割り当て
     *
     * @param string $template オリジナルテンプレート
     * @return string
     */
    public static function virtual_node(string $template): string {
        $path = self::get_virtual_path_from_url();
        if (!$path) {
            return $template;
        }

        $hierarchy_data = \Kx\Database\Hierarchy::get_node_by_path($path);
        if ($hierarchy_data && (int)$hierarchy_data['is_virtual'] === 1) {
            Dy::set('current_virtual_node', $hierarchy_data);
            $virtual_template = get_stylesheet_directory() . '/templates/components/navigation/virtual-node.php';

            if (file_exists($virtual_template)) {
                global $wp_query;
                $wp_query->is_404 = false;
                return $virtual_template;
            }
        }
        return $template;
    }

    /**
     * URLから仮想パスを抽出
     *
     * @return string|null
     */
    private static function get_virtual_path_from_url(): ?string {
        $path = get_query_var('kx_virtual_path');
        if ($path) {
            return urldecode($path);
        }

        if (isset($_SERVER['REQUEST_URI']) && preg_match('/hierarchy\/([^\/?]+)/', $_SERVER['REQUEST_URI'], $matches)) {
            return urldecode($matches[1]);
        }
        return null;
    }
}