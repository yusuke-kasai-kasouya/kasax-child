<?php
/**
 * [Path]: inc\component\class-KxLink.php
 * リファクタリング：ロジックの小分け・構造化版
 */

namespace Kx\Components;

use Kx\Core\DynamicRegistry as Dy;
use Kx\Core\TitleParser;
use Kx\Utils\KxTemplate;

class KxLink {
    /**
     * メインレンダリングメソッド
     */
    public static function render(int $post_id, array $args = []) {
        $path_index = Dy::get_path_index($post_id);
        if (empty($path_index) || !($path_index['valid'] ?? false)) {
            return '<div>NO-LINK</div>';
        }
        \Kx\Core\ContextManager::sync($post_id);

        $custom_text = $args['text'] ?? null;
        $context_path = '';
        $core_title = '';
        $meta_label = '';

        if ($custom_text) {
            $core_title = $custom_text;
        } else {
            // 基本となる名称（＠で分解されている場合は ＠より後ろの clean_name を優先）
            $base_name = $path_index['at_name'] ?? $path_index['last_part'] ?? '';

            // Dy系で新設された「解決済み名称」を取得
            $resolved_last = $path_index['last_part_name'] ?? '';

            // 【追記ロジック】解決済み名称があれば 〈 〉で後ろに付ける
            if (!empty($resolved_last) && $resolved_last !== $base_name) {
                $core_title = $base_name . "〈{$resolved_last}〉";
            } else {
                $core_title = $base_name;
            }

            // 親パスの構築（partsを使用）
            $parts = $path_index['parts'] ?? [];
            array_pop($parts);
            $context_path = !empty($parts) ? implode(' ≫ ', $parts) : '';

            // メタラベル解析
            $meta_label = self::resolve_meta_label($post_id);
        }

        // 更新時間の取得（小分けにした関数を呼び出し）
        $modified_ago = '';
        if (!empty($args['modified'])) {
            $modified_ago = self::get_modified_ago($post_id);
        }

        $colormgr = Dy::get_color_mgr($post_id);
        $traits   = $colormgr['style_array']['vars_only'] ?? '';

        $template_args = [
            'post_id'      => $post_id,
            'url'          => get_permalink($post_id),
            'index'        => $args['index'] ?? '',
            'context_path' => $context_path,
            'core_title'   => $core_title,
            'meta_label'   => $meta_label,
            'modified_ago' => $modified_ago,
            'traits'       => $traits,
            'custom_css'   => $args['class'] ?? '',
            'mode'         => $args['mode'] ?? 'standard',
            'has_custom'   => !empty($custom_text),
        ];

        return KxTemplate::get('components/common/link-box', $template_args, false);
    }

    /**
     * 更新日時を「〜前」の形式で取得
     */
    private static function get_modified_ago(int $post_id): string {
        $m_time = get_post_modified_time('U', false, $post_id);
        if (!$m_time) return '';

        $diff = human_time_diff($m_time, current_time('timestamp'));
        return sprintf('%s前', $diff);
    }

    /**
     * ポストタイプに応じたメタラベルを解決
     */
    private static function resolve_meta_label(int $post_id): string {
        if (!class_exists('\Kx\Core\TitleParser')) return '';

        if (TitleParser::is_type(['prod_work_production_log','prod_work_productions'], $post_id)) {
            $work = Dy::get_work($post_id);
            return $work['title'] ?? '';
        }

        if (TitleParser::is_type('prod_character_core', $post_id)) {
            $character = Dy::get_character($post_id);
            return $character['name'] ?? '';
        }

        return '';
    }
}