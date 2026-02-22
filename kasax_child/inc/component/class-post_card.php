<?php
/**
 * [Path]: inc\component\class-post_card.php
 */

namespace Kx\Component;

//use Kx\Core\SystemConfig as Su;
use Kx\Core\DynamicRegistry as Dy;
use Kx\Core\KxDirector as kx;
use Kx\Utils\KxTemplate;

class PostCard {

    /**
     * Undocumented function
     *
     */
    public static function render($post_id, $mode = 'standard', $extra_slots = []) {
        $cache = Dy::get_path_index($post_id);

        $last_name = $cache['last_part_name'];
        $last_name = $last_name? '：'.$last_name : '';

        $modified_time = $cache['modified'] ?? '';

        if ($modified_time) {
            $current_stamp  = current_time('timestamp');

            // 2. 数値化して現在時刻と比較
            $is_recent = ($current_stamp - strtotime($modified_time)) < 60;

            if ($is_recent) {
                // 3. 表示層(vis)のtraitsにボーダー表示用のクラスを追加
                // 例: 'recent-update-border' というCSSを付与
                $update_border = ' kx-recent_update_border';
            }
        }


        // 現状の基本運用に合わせる
        $colormgr = Dy::get_color_mgr($post_id);

        // style_base に背景色の指定を合成
        $paint = ($colormgr['style_base'] ?? '') . 'background-color:hsla(var(--kx-hue),var(--kx-sat),var(--kx-lum),var(--kx-alp));';

        // traits (class) も Dy から取得
        $traits = $colormgr['style_array']['vars_only'] ?? '';

        // --- スロットの生成と合成 ---
        $base_slots = self::get_slots($post_id, $mode);
        $final_slots = array_merge($base_slots, $extra_slots);

        if ($mode === 'line') {
            $excerpt = self::get_first_line_plain($post_id);
            $template_path = 'components/post/line';
        } else {
            $excerpt = self::get_formatted_excerpt($post_id);
            $template_path = 'components/post/card';
        }


        //統合概要の表示と非表示切り替え。
        if((Dy::get('trace')['kxx_sc_count'] ?? 0)  === 1){
            $mode = Kx::is_integrated($post_id) ? 'blind' : $mode;
        }

        $args = [
            'id'            => $post_id,
            'ghost_to'      => Dy::get_content_cache($post_id,'ghost_to') ?? null ,
            'mode'          => $mode,
            'title'         => ($cache['at_name'] ?? Dy::get_title($post_id)).$last_name,
            'paint'         => $paint,
            'traits'        => $traits,
            'slots'         => $final_slots,
            'update_border' => $update_border ?? '',
            'edit_url'      => get_edit_post_link($post_id),
            'excerpt'       => $excerpt,
        ];

        // モードに基づいてテンプレートファイルを切り替える
        // 例: 'line' なら components/post/line.php を、それ以外は components/post/card.php を呼ぶ
        $template_path = ($mode === 'line') ? 'components/post/line' : 'components/post/card';
        return KxTemplate::get($template_path, $args, false);
    }

    /**
     * ポストの属性に基づいたスロット（メタ情報タグ）の生成
     */
    private static function get_slots($post_id, $mode) {
        $slots = [];

        $path_index = Dy::get_path_index($post_id) ?? [];
        $type  = $path_index['type'] ?? '';
        //$genre = $path_index['genre'] ?? '';

        // --- 1. Shared (同期) ポストの処理 ---
        $shared_sync_types = Dy::get_shared_sync_types();
        if (in_array($type, $shared_sync_types)) {
            $shared_slots = \Kx\Utils\KxUI::get_shared_link_slots($post_id);
            $slots = array_merge($slots, $shared_slots);
        }


        // --- 2. Character Core (キャラクタ) の処理 ---
        if ($type === 'prod_character_core') {
            $char_data = Dy::get_character($post_id);
            if (!empty($char_data['name'])) {
                //$slots[] = "<span class='kx-slot--char' style='line-height: 1;'>{$char_data['name']}</span>";
            }
        }


        //alertチェック。
        $alert = (int)Dy::get_content_cache($post_id, 'alert');
        if( $alert === 1){
            $slots[] = "<span style='color:Green;'>TEMPLATE</span>";
        }else if( $alert === 2 ){
            $slots[] = "<span style='color:red;'>━━子階層あり━━</span>";
        }



        return $slots;
    }


    /**
     * 投稿内容から抜粋（または全文化）を取得する
     */
    private static function get_formatted_excerpt($post_id, $full_content_mode = false) {
        global $post, $more;

        // 現在の投稿をバックアップ（念のため）
        $original_post = $post;
        $post = get_post($post_id);
        setup_postdata($post);

        // moreタグの制御
        $more = $full_content_mode ? 1 : 0;

        // フィルタ適用（ショートコード展開など）
        $content = apply_filters('the_content', get_the_content(""));

        // 旧ロジックの継承：改行削除とトリミング
        $content = preg_replace("/\r\n|\r|\n/", "", trim($content));
        if (strpos($content, "<br />") === 0) {
            $content = mb_substr($content, 6);
        }

        // 復元
        wp_reset_postdata();
        if ($original_post) {
            $post = $original_post;
            setup_postdata($post);
        }

        return $content;
    }


    /**
     * DBから直接、最初の1行のみを取得する
     */
    private static function get_first_line_plain($post_id) {
        $post = get_post($post_id);
        if (!$post) return '';

        // 現在の投稿をバックアップ（念のため）
        $original_post = $post;
        $post = get_post($post_id);
        setup_postdata($post);

        // 1. コンテンツ取得（フィルタを通さない）
        $content = $post->post_content;
        $content = preg_replace('/\[.+?\]/s', '', $content);
        $content = strip_tags($content);
        $lines = preg_split("/\r\n|\r|\n/", ltrim($content));
        $first_line = isset($lines[0]) ? $lines[0] : '';
        $first_line = apply_filters('the_content', $first_line);

        // 2. ショートコードを削除（無限ループ・実行エラー防止）
        //$content = preg_replace('/\[.+?\]/s', '', $content);

        // 3. HTMLタグを削除
        //$content = strip_tags($content);

        // 4. 改行で分割し、最初の1行目だけを抽出
        //$lines = preg_split("/\r\n|\r|\n/", ltrim($content));
        //$first_line = isset($lines[0]) ? $lines[0] : '';



         wp_reset_postdata();
        if ($original_post) {
            $post = $original_post;
            setup_postdata($post);
        }

        return $first_line;
    }
}