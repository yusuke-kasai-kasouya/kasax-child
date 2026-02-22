<?php
/**
 * [Path]: inc\core\class-kx-ajax-handler.php
 * [Role]: フロントエンドからのAjaxリクエストを受信し、各コアクラスへ仲介する。
 */

namespace Kx\Core;

class AjaxHandler {

    /**
     * Ajaxハンドラ（func_db.php 内）
     */
    public static function hierarchy_ajax_handler() {
        $mode = isset($_GET['mode']) ? $_GET['mode'] : '';
        $base = isset($_GET['base']) ? $_GET['base'] : '';

        if (empty($base)) {
            echo "ベースタイトルが空です。";
            wp_die();
        }

        // 1. メンテ機能の実行
        if ($mode === 'repair_sub') {
            \Kx\Database\Hierarchy::repair_hierarchy($base, false);
        } elseif ($mode === 'repair_all') {
            \Kx\Database\Hierarchy::repair_hierarchy($base, true);
        }

        // --- 修正ポイント：再帰フラグの判定を「full」時も true にする ---
        // $mode が 'full'（子階層表示ボタン）または 'repair_all' の場合に再帰表示（全下位表示）とする
        $recursive = ($mode === 'full' || $mode === 'repair_all');

        // 3. ツリー生成を呼び出す
        echo \Kx\Database\Hierarchy::get_full_tree_text($base, "", $recursive);

        wp_die();
    }

    /**
     * 統合系
     *
     */
    public static function ajax_consolidate_action(){
        // 1. セキュリティチェック
        check_ajax_referer('kx_consolidator_nonce');

        $source_id = isset($_POST['source_id']) ? (int)$_POST['source_id'] : 0;
        $post_id   = isset($_POST['post_id'])   ? (int)$_POST['post_id']   : 0;
        $args      = isset($_POST['args'])      ? $_POST['args']           : [];

        if (!$source_id || !$post_id) {
            wp_send_json_error('IDが正しくありません。');
        }

        // 2. 実行
        // ここで Kx_Consolidator::run が呼ばれ、内部で save_to_text_file が実行される
        $result = \Kx\Core\Kx_Consolidator::run($source_id, $post_id, $args);

        // 3. レスポンス（ファイル保存の場合は run は false を返す設計なのでメッセージで判断）
        wp_send_json_success(['message' => 'ファイル保存処理をリクエストしました。']);

    }

    /**
     * エディタ用。
     */
    public static function  ajax_trash_post(){
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        // 【検証用】Nonceチェックをコメントアウトして403が消えるか確認
        // check_ajax_referer('delete-post_' . $post_id, '_wpnonce');

        // 権限があるか、かつログインしているか
        if (is_user_logged_in() && current_user_can('delete_post', $post_id)) {
            if (wp_trash_post($post_id)) {
                wp_send_json_success('Trashed successfully');
            } else {
                wp_send_json_error('Failed to trash post');
            }
        } else {
            wp_send_json_error('Permission denied');
        }
    }

    /**
     * AJAXハンドラ：新規投稿作成
     */
    public static function ajax_my_insert_post_handler() {
        // 1. セキュリティチェック
        if (!check_ajax_referer('quick_insert_nonce', '_wpnonce', false)) {
            wp_send_json_error('不正なアクセスです(Nonce Error)');
        }

        // 2. データの取得と洗浄
        $title   = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';
        $genre   = isset($_POST['genre']) ? sanitize_text_field($_POST['genre']) : '';
        $parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;

        if (empty($title)) {
            wp_send_json_error('タイトルが空です');
        }

        // 3. 投稿作成
        $post_data = [
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => 'publish',
            'post_type'    => 'post', // 必要なら階層に合わせて変更
            'post_author'  => get_current_user_id(),
        ];

        $new_id = wp_insert_post($post_data);

        if (!is_wp_error($new_id) && $new_id > 0) {
            // 4. ジャンル(タクソノミー)の紐付け
            if ($genre) {
                wp_set_object_terms($new_id, $genre, 'genre');
            }

            // 5. 成功レスポンス
            wp_send_json_success(['id' => $new_id]);
        } else {
            wp_send_json_error('WordPress保存失敗: ' . $new_id->get_error_message());
        }
    }


    /**
     * Undocumented function
     *
     * @return void
     */
    public static function handle_load_ai_links() {
        $post_id = intval($_POST['post_id']);

        $list = ["Β", "γ", "σ", "δ"];

        // クエリパラメータ等は必要に応じて Dy (DynamicRegistry) などから取得
        $results = [
            'whitelist' => \Kx\Core\KxAiBridge::get_similar_posts($post_id, 20, ['whitelist' => $list]) ?? [],
            'blacklist' => \Kx\Core\KxAiBridge::get_similar_posts($post_id, 20, ['blacklist' => $list]) ?? [],
        ];

        // 透明度計算用のクロージャ（または直接記述）
        $get_opacity = function($score) {
            return ($score / 100) + 0.3; // 例
        };

        $html = '';
        foreach ($results as $type => $scored_lists) {
            foreach ($scored_lists as $item) {
                $score = $item['score'];
                // KxLink等を使ってHTMLを生成 [cite: 92]
                $html .= sprintf(
                    "<div style='opacity: %s;'>%s</div>",
                    $get_opacity($score),
                    \Kx\Components\KxLink::render($item['post_id'], ['index' => round($score)])
                );
            }
            if ($type === 'whitelist' && !empty($results['blacklist'])) {
                $html .= '<hr>';
            }
        }

        echo $html;
        wp_die();
    }


}