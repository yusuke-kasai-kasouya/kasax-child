<?php
/**
 * [Path]: inc/launcher/class-kx-post-launcher.php
 */

namespace Kx\Launcher;

use Kx\Core\DynamicRegistry as Dy;
use Kx\Core\KxQuery;
use Kx\Utils\KxTemplate;
use \Kx\Utils\KxMessage as Msg;




class KxPostLauncher {

    /**
     * ショートコード [kx] の実行司令塔
     */
    public static function run($atts) {
        // 1. 引数の正規化
        $args = shortcode_atts([
            't'       => '60',
            'id'      => '',
            'ids'     => '',
            'search'  => '',
            'all'     => '',
            'cat'     => '',
            'cat_not' => '',
            'tag'     => '',
            'tag_not' => '',
            'text'    => '',
            'text_c'  => '',
            'ppp'     => '',
            'orderby' => '',
            'order'   => '',
            'sys'     => '',
            'mode'    => 'card',
            'type'  => '',
            'depth' => '',
            'search_suffix' => '',
            'modified' => false,
        ], $atts);

        // 2. 詳細なコンテキスト解析
        $data = self::parse_context_options($args);

        // 機能停止(abort)フラグが立っている場合は何も返さない（またはエラーを返す）
        if ($data['abort']) {
            return '<span style="color:red">━━機能停止━━</span>';
        }

        // 3. ID配列の確定
        $ids = $data['ids'] ?? null;

        // 指定がない場合は KxQuery で検索を実行
        if (is_null($ids)) {
            $query = new KxQuery($args);
            $ids = $query->get_ids();
        }

        // --- 有効な投稿のみに絞り込む (Validation) ---
        if (!empty($ids)) {
            $ids = Dy::validate_ids($ids);
        }

        if (!empty($ids) && count($ids) > 1 && $args['modified'] === false ) {
            $ids = self::sort_ids_by_title($ids);
        }


        // 4. ガードクローズ
        if (empty($ids)) {
            if (current_user_can('manage_options')) {
                Msg::caution("KxPostLauncher：該当する投稿（ids）が見つかりませんでした。");
            }
            return '<span style="color:red;">━　KxPostLauncher：n/a　━</span>';
        }

        // 5. 動的な番号振り判定
        // linkモードかつ複枚数ある場合にフラグを立てる
        if ($args['mode'] === 'link' && count($ids) > 1) {
            $data['is_numbered'] = true;
            $data['has_header'] = true;
            if( $args['modified']) $data['is_modified'] = true;
        }



        // 6. 付随情報の生成
        $header_label = $data['has_header'] ? self::generate_header_label($args) : '';

        $colormgr = Dy::get_color_mgr(get_the_ID());
        $traits   = $colormgr['style_array']['vars_only'] ?? '';
        //echo kx_dump($colormgr);

        // 7. 出力（テンプレートへ委譲）
        return KxTemplate::get('components/post/launcher-wrapper', [
            'ids'          => $ids,
            'header_label' => $header_label,
            'traits'       => $traits,
            'mode'         => $args['mode'],
            'data'         => $data,
            'args'         => $args
        ], false);
    }



    /**
     * ID配列をタイトルのフルパス（full）順にソートする
     * * @param array $ids 投稿IDの配列
     * @return array ソート済みのID配列
     */
    private static function sort_ids_by_title(array $ids) {
        // DynamicRegistry を利用

        usort($ids, function($a, $b) {
            // fullタイトルを取得（内部でキャッシュされる）
            $title_a = Dy::get_path_index($a, 'full') ?: '';
            $title_b = Dy::get_path_index($b, 'full') ?: '';

            return strcmp($title_a, $title_b);
        });

        return $ids;
    }

    /**
     * コンテキスト解析と引数の正規化
     */
    private static function parse_context_options(array &$args) {
        $data = [
            'ids'         => null,
            'is_numbered' => false,
            'has_header'  => false,
            'layout'      => '',
            'abort'       => false, // 処理停止フラグ
        ];

        // --- ID / IDs の統合処理 ---
        $manual_ids = [];
        if (!empty($args['ids'])) {
            $ids_raw = is_array($args['ids']) ? $args['ids'] : explode(',', $args['ids']);
            $manual_ids = array_map('trim', $ids_raw);
        }
        if (!empty($args['id'])) {
            $manual_ids[] = trim($args['id']);
        }
        if (!empty($manual_ids)) {
            $data['ids'] = array_values(array_unique(array_filter(array_map('intval', $manual_ids))));
        }

        // --- 検索文字列の正規化 ---
        if (!empty($args['search'])) {
            $args['search'] = str_replace('＞', '≫', $args['search']);
        }

        // --- t値によるロジック分岐 ---
        $t = isset($args['t']) ? (int)$args['t'] : 0;

        // 40～59：機能停止
        if ($t >= 40 && $t <= 59) {
            $data['abort'] = true;
            return $data;
        }

        // 10～19、60～69：ppp（検索数）を 1 に固定
        if (($t >= 10 && $t <= 19) || ($t >= 60 && $t <= 69)) {
            $args['ppp'] = 1;
        }

        // --- モード・pppマップの設定 ---
        if ($t >= 10 && $t < 40) {
            $args['mode'] = 'card';
            $args['card_mode'] = ($t >= 30) ? 'line' : 'card';
        }
        elseif ($t >= 60 && $t <= 99) {
            $args['mode'] = 'link';

            // 91～93：ループ制限（ppp）の上書き
            $ppp_map = [91 => 10, 92 => 20, 93 => 30];
            if (isset($ppp_map[$t])) {
                $args['ppp'] = $ppp_map[$t];
                $args['orderby'] = 'modified';
            }
        }

        return $data;
    }



    /**
     * 検索条件（$args）に基づき、表示用のヘッダーラベルを生成する
     * * @param array $args 正規化済みの引数
     * @return string HTMLラベル文字列
     */
    private static function generate_header_label(array $args) {
        $labels = [];

        // 1. 階層パス (search)
        if (!empty($args['search'])) {
            // ＞ を ≫ に正規化し、視認性を高める
            $path = str_replace('＞', '≫', $args['search']);
            $labels[] = '<span class="kx-label-type">検索:</span> ' . esc_html($path);
        }

        // 2. カテゴリ (cat)
        if (!empty($args['cat'])) {
            // ID指定かスラッグ指定かにかかわらず、ラベル化
            $labels[] = '<span class="kx-label-type">CAT:</span> ' . esc_html(get_cat_name($args['cat']) );
        }

        // 3. タグ (tag)
        if (!empty($args['tag'])) {
            $labels[] = '<span class="kx-label-type">タグ:</span> ' . esc_html($args['tag']);
        }

        // 条件が複数ある場合はセパレーターで連結
        if (empty($labels)) {
            return "";
        }

        return implode(' <span class="kx-label-sep">/</span> ', $labels);
    }
}