<?php
/**
 * [Path]: inc\component\class-QuickInserter.php
 */
namespace Kx\Component;

use Kx\Core\DynamicRegistry as Dy;
use Kx\Utils\KxTemplate;
use Kx\Utils\Time;

class QuickInserter {

    /**
     * クイックインサーターのレンダリング
     * * @param int    $parent_id   親ポストID
     * @param string $new_title   初期タイトル（≫区切り）
     * @param string $new_content 初期本文（空ならタイムスタンプを挿入）
     * @param string $label       ボタンラベル
     * @param string $mode        表示モード (shared|card|standard)
     * @param array  $args        追加引数 (genre, class, paint等)
     * @return string
     */
    public static function render($parent_id, $new_title, $new_content = '', $label = 'new', $mode = 'standard', $args = []) {
        // 1. 基本情報の取得
        $path_index = Dy::get_path_index($parent_id) ?? [];
        $genre      = $args['genre'] ?? ($path_index['genre'] ?? 'general');
        $paint      = $args['paint'] ?? '';

        // 2. モードに応じたスタイルとタイトルの調整

        switch ($mode) {
            case 'timetable_matrix':
                $paint .= 'opacity: 1';
                break;
            case 'shared':
                $paint .= 'opacity: 0.1;';
                break;

            case 'card':
                $new_title = ($path_index['full'] ?? '') . '(新規追加)';
                $paint    .= 'opacity: 0.5;';
                break;
            case 'matrix':
                $paint    .= 'opacity: 0.1;';
                break;
            case 'taskboard':
                $new_title = $new_title ?? Dy::get_title($parent_id).'≫新規';
                $paint    .= 'opacity: 1;';
                break;
            default:
                $paint .= 'opacity: 0.25;';
                break;
        }

        // 3. タイトルパーツの分割
        $title_parts = explode('≫', $new_title);
        $last_part   = array_pop($title_parts);

        // 4. 本文のデフォルト値（空ならタイムスタンプ）
        $content = $new_content ?: '＿' . \Kx\Utils\Time::format() . '＿';

        // 5. データの集約
        $data = [
            'parent_id'   => $parent_id,
            'genre'       => $genre,
            'label'       => $label,
            'mode'        => $mode,
            'title_parts' => $title_parts,
            'last_part'   => $last_part,
            'content'     => $content,
            'class'       => $args['class'] ?? '',
            'paint'       => $paint,
        ];

        return KxTemplate::get('components/editor/quick-inserter', $data, false);
    }
}