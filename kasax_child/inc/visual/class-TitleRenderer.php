<?php
namespace Kx\Visual;

use Kx\Core\SystemConfig as Su;
use Kx\Core\DynamicRegistry as Dy;
use Kx\Utils\KxTemplate;

class TitleRenderer {


    const ROOT_LABEL = 'Λ ROOT';

    public static function render($post_id) {
        $path_index = Dy::get_path_index($post_id);
        if (!$path_index) return '';

        $parts       = $path_index['parts']       ?? [];
        $parts_names = $path_index['parts_names'] ?? [];
        $ancestry    = Dy::get_content_cache($post_id, 'ancestry') ?? [];
        $color_mgr   = Dy::get_color_mgr($post_id);

        $depth = count($parts);
        $anc_values = array_values($ancestry);
        $anc_count  = count($anc_values);

        // --- 表示用タイトルの構築 (parts + parts_names) ---
        // $parts_names[i] がある場合は <名称> を付与するヘルパーを内部適用
        $display_labels = [];
        foreach ($parts as $i => $segment) {
            $name = $parts_names[$i] ?? null;
            $display_labels[$i] = $name ? "{$segment}〈{$name}〉" : $segment;
        }

        // メイン・親・祖父母の抽出
        $main_title   = end($display_labels) ?: Dy::get_title($post_id);
        $parent_title = ($depth >= 2) ? $display_labels[$depth - 2] : null;
        $grand_title  = ($depth >= 3) ? $display_labels[$depth - 3] : null;

        // URLの特定
        $parent_url      = ($anc_count >= 1) ? get_permalink($anc_values[$anc_count - 1]) : null;
        $grandparent_url = ($anc_count >= 2) ? get_permalink($anc_values[$anc_count - 2]) : null;

        // --- パンくずデータの構築 ---
        $breadcrumb = [];
        if ($depth <= 1) {
            // トップレベルの場合は ROOT のみ
            $breadcrumb[] = ['label' => self::ROOT_LABEL, 'url' => home_url('/')];
        } else {
            // 親階層までのループ
            for ($i = 0; $i < $depth - 1; $i++) {
                $breadcrumb[] = [
                    'label' => $display_labels[$i],
                    'url'   => isset($anc_values[$i]) ? get_permalink($anc_values[$i]) : home_url('/')
                ];
            }
        }

        $args = [
            'post_id'        => $post_id,
            'color_config'   => $color_mgr,
            'base_class'     => $color_mgr['class_array']['base'] ?? '',
            'last_modified'  => $path_index['modified'] ?? null,
            'display_titles' => [
                'main'        => $main_title,
                'parent'      => $parent_title,
                'grandparent' => $grand_title,
            ],
            'links' => [
                'parent_url'      => $parent_url,
                'grandparent_url' => $grandparent_url,
            ],
            'breadcrumb'     => $breadcrumb,
            'extra_metadata' => self::get_enhanced_metadata($post_id, $path_index['genre'] ?? null, $parts),
        ];

        return KxTemplate::get('layout/page-title', $args, true);
    }


    /**
     * タイトルのセグメントに名称を付随させる
     * 1. prefix_map (汎用) 優先
     * 2. contextual_definitions (文脈依存) 追記
     */
    private static function attach_prefix_name($segment, $prefix_map, $context_map, $parent_key = null) {
        if (empty($segment)) return '';

        // ≫ の左側をキーとして抽出
        $key = (mb_strpos($segment, '≫') !== false) ? mb_strstr($segment, '≫', true) : $segment;

        // --- 1. prefix_map の判定 (優先) ---
        // 子階層 (children) のチェック
        if ($parent_key && isset($prefix_map[$parent_key]['children'][$key])) {
            $name = $prefix_map[$parent_key]['children'][$key]['name'];
            return "{$segment}〈{$name}〉";
        }
        // 親階層 (prefixes) のチェック
        if (isset($prefix_map[$key])) {
            $name = $prefix_map[$key]['name'];
            return "{$segment}〈{$name}〉";
        }

        // --- 2. contextual_definitions の判定 ---
        // 文脈 (parent_key) が一致し、かつ現在のキーがマッピングに含まれるか
        echo $parent_key.'+';
        var_dump($context_map[]);
        if ($parent_key && isset($context_map[$parent_key])) {

            foreach ($context_map[$parent_key] as $context_keys => $id_map) {
                // "T,M" のようなカンマ区切りを配列化してチェック
                $valid_keys = explode(',', $context_keys);
                if (in_array($parent_key, $valid_keys) || true) { // 既に親が合致している前提

                    // 現在のセグメント(key)が 001 などのIDとして定義されているか
                    if (isset($id_map[$key])) {
                        // 作品名は配列の0番目を取得
                        $work_name = is_array($id_map[$key]) ? $id_map[$key][0] : $id_map[$key];
                        return "{$segment}〈{$work_name}〉";
                    }
                }
            }
        }

        return $segment;
    }



    /**
     * 投稿タイプに応じた拡張メタ情報の取得
     */
    private static function get_enhanced_metadata($post_id, $post_genre, $segments) {
        $info_list = [
            'prod_character_core',
            'prod_character_relation',
            'prod_work_productions'
        ];

        if (!in_array($post_genre, $info_list)) return '';

        $char_data = Dy::get_character($post_id);
        $label     = $char_data['name'] ?? '';

        switch ($post_genre) {
            case 'prod_character_relation':
                if (isset($segments[0], $segments[2])) {
                    $relation = Dy::get_char_attr($segments[0], $segments[2]) ?? [];
                    $label .= ' → ' . ($relation['name'] ?? '');
                }
                break;
            case 'prod_work_productions':
                $work = Dy::get_work($post_id);
                $label .= ' ： ' . ($work['title'] ?? '');
                break;
        }
        return $label;
    }

    /**
     * パスセグメントの配列から、それぞれの「名称」を特定して配列で返す
     * ContextRoot と GroupKeys の両方でカンマ区切り（複数マッチ）に対応
     */
    public static function resolve_segment_names(array $parts) {
        $prefix_data = Su::get('title_prefix_map');
        $prefix_map  = $prefix_data['prefixes'] ?? [];
        $context_map = $prefix_data['contextual_definitions'] ?? [];

        $resolved_names = [];
        $last_key = null;
        $ancestor_keys = [];

        foreach ($parts as $index => $segment) {
            $key = (mb_strpos($segment, '≫') !== false) ? mb_strstr($segment, '≫', true) : $segment;
            $name = null;

            // --- 1. prefix_map (汎用) ---
            if ($last_key && isset($prefix_map[$last_key]['children'][$key])) {
                $name = $prefix_map[$last_key]['children'][$key]['name'];
            } elseif (isset($prefix_map[$key])) {
                $name = $prefix_map[$key]['name'];
            }

            // --- 2. contextual_definitions (文脈依存：多重カンマ区切り対応) ---
            if (!$name && $index >= 2) {
                $context_root = $ancestor_keys[$index - 2] ?? null;

                if ($context_root) {
                    // $context_map のキー（例: "∫,∬01"）をループして判定
                    foreach ($context_map as $root_keys => $groups) {
                        $valid_roots = explode(',', $root_keys);

                        // 1. ルート（第1階層）が一致するか
                        if (in_array($context_root, $valid_roots)) {
                            // 2. グループ（第2階層：T,M等）をループして判定
                            foreach ($groups as $group_keys => $id_map) {
                                $valid_groups = explode(',', $group_keys);

                                if (in_array($last_key, $valid_groups)) {
                                    // 3. IDが一致するか
                                    if (isset($id_map[$key])) {
                                        $val = $id_map[$key];
                                        $name = is_array($val) ? ($val[0] ?? null) : $val;
                                        break 2; // ルートのループまで抜ける
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $resolved_names[] = $name;
            $ancestor_keys[]  = $key;
            $last_key         = $key;
        }

        return $resolved_names;
    }

}