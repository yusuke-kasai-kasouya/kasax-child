<?php

/**
 * inc\utils\class-kx-taskboard.php
 *
 * 汎用作業ボード (TaskBoard)
 * 検索、メニュー、トップリストなどの標準的なボード構成を管理する。
 */

namespace Kx\Utils;

//use Kx\Core\SystemConfig as Su;
use Kx\Core\DynamicRegistry as Dy;
use Kx\Core\KxDirector as kx;
use Kx\Utils\KxMessage as MSG;
use Kx\Launcher\KxPostLauncher;
use Kx\Utils\KxTemplate;

/**
 * 作業ボード
 */
class TaskBoard {

    /** @var array 許可されたボードタイプ */
    const BOARD_TYPES  = ['search', 'menu', 'top', 'post_top'];

    private static $shortcode_host_id = 0;
    private static $shortcode_host_title = '';
    private static $shortcode_host_cat = [];

    /**
     * ショートコードエントリーポイント
     *
     * @param array|string|null $atts
     * @return string
     */
    public static function shortcode($atts) {
        if (is_admin()) {
            return '';
        }

        if ((Dy::get('trace')['kxx_sc_count'] ?? 0) > 0) {
            return '━━━　ShortCODE: Recursive Call Detected　━━━';
        }

        Dy::trace_count('kxx_sc_count', 1);

        $args = shortcode_atts([
            'type'         => '',
            'cat'          => '',
            'c'            => '',
            'cs'           => '',
            'c_clone'      => '',
            'text'         => '',
            'check_update' => '',
            'check_search' => '',
            'sys'          => '',
            't'            => '',
            'select_top'   => '',
            'select_c'     => '',
            'select_date'  => '',
            'select1'      => '',
            'select2_c'    => '',
            'select2'      => '',
            'test'         => '',
            'wfm_end'      => '',
            'f'            => '',
        ], $atts);

        $ret = self::render($args);

        Dy::trace_count('kxx_sc_count', -1);
        return (string)$ret;
    }

    /**
     * メインエントリーポイント
     *
     * @param array $args
     * @return string
     */
    public static function render(array $args) {
        self::$shortcode_host_id = get_the_ID();

        if (self::is_engine_active($args)) {
            return (string)self::render_board($args);
        }

        return '━━━　テンプレート：未実装/運用終了　━━━';
    }

    /**
     * エンジンを起動すべきかどうかの判定
     *
     * @param array $args
     * @return bool
     */
    private static function is_engine_active(array $args) {
        $type = (string)($args['type'] ?? '');
        return (!empty($type) && in_array($type, self::BOARD_TYPES, true));
    }

    /**
     * 配列構造に基づいたボードの描画実行
     *
     * @param array $args
     * @return string
     */
    private static function render_board(array $args) {
        $type = (string)($args['type'] ?? '');
        $blueprint = self::get_blueprint($type, $args);
        $structured_data = self::hydrate_data($blueprint, $args);

        $html = '';
        foreach ($structured_data as $key => $data) {
            $anchor_id = "section-" . str_replace('section_', '', (string)$key);

            // ① アウトライン抽出機構への登録
            if (!empty($data['use_outline'])) {
                $html .= (string)\Kx\Core\OutlineManager::add_from_loop(self::$shortcode_host_id, (string)($data['title'] ?? ''), $anchor_id);
            }

            // ② ジャンプ先となるHTML要素（アンカーID）の出力
            $html .= sprintf('<section id="%s" class="taskboard-unit">', esc_attr($anchor_id));
            $html .= self::render_component($data);
            $html .= '</section>';
        }
        return (string)$html;
    }

    /**
     * 構成定義（Blueprint）の取得
     *
     * @param string $type
     * @param array $args
     * @return array
     */
    private static function get_blueprint(string $type, array $args) {
        switch ($type) {
            case 'search':
                return self::get_page_search_schema($args);
            case 'menu':
                return self::get_page_menu_schema();
            case 'top':
                return self::get_page_top_schema();
            case 'post_top':
                return self::get_post_top_schema();
            default:
                // k0_top はここから削除されました。WorkStation を使用してください。
                return [];
        }
    }

    /**
     * 検索ページのスキーマ定義
     */
    private static function get_page_search_schema(array $args) {
        return [
            'section_search' => [
                'content_type'  => 'function',
                'callback'      => [\Kx\Utils\Toolbox::class, 'render_search_form'],
                'title'         => 'SEARCH',
                'args'          => $args,
                'use_outline'   => true,
            ]
        ];
    }

    /**
     * 固定ページ管理（menu）のスキーマ定義
     */
    private static function get_page_menu_schema() {
        $title = (string)Dy::get_title(self::$shortcode_host_id);
        $ids = kx::get_ids_by_title($title);
        $id = !empty($ids) ? $ids[0] : 0;

        return [
            'section_search' => [
                'content_type'  => 'function',
                'callback'      => [\Kx\Utils\Toolbox::class, 'category_search_box'],
                'title'         => 'SEARCH',
                'args'          => ['t' => 50],
                'use_outline'   => true,
            ],
            'host_post_title' => [
                'content_type'  => 'launcher',
                'data_source'   => 'host_title',
                'title'         => 'List',
                'args'          => ['t' => 60, 'id' => $id, 'mode' => 'link', 'ppp' => 1],
                'use_outline'   => true,
            ],
            'section_list' => [
                'content_type'  => 'launcher',
                'data_source'   => 'descendants',
                'title'         => '▽',
                'level'         => 3,
                'args'          => ['mode' => 'link'],
                'use_outline'   => false,
            ],
            'section_modified' => [
                'content_type'  => 'launcher',
                'data_source'   => 'post_search',
                'title'         => '更新履歴',
                'args'          => ['t' => 91, 'mode' => 'link', 'modified' => true],
                'use_outline'   => true,
            ]
        ];
    }

    /**
     * 固定ページ管理（top）のスキーマ定義
     */
    private static function get_page_top_schema() {
        return [
            'in' => [
                'content_type' => 'inserter',
                'title'        => "LIST",
                'use_outline'  => true,
                'args'         => [
                    'label' => "NEW＋",
                    'title' => "∬99",
                ],
            ],
            'section_list' => [
                'content_type' => 'launcher',
                'title'        => 'List(t=70)',
                'args'         => ['mode' => 'link', 'search' => '∬', 'depth' => 1, 't'      => 70],
                'use_outline'  => true,
            ],
        ];
    }

    /**
     * 投稿トップ管理（post_top）のスキーマ定義
     */
    private static function get_post_top_schema() {
        return [
            'in' => [
                'content_type' => 'inserter',
                'title'        => "LIST",
                'use_outline'  => true,
                'args'         => [
                    'label' => "NEW＋",
                ],
            ],
            'section_list' => [
                'content_type' => 'launcher',
                'data_source'  => 'descendants',
                'title'        => 'link',
                'level'        => 3,
                'args'         => ['mode' => 'link'],
                'use_outline'  => true,
            ],
        ];
    }

    /**
     * Blueprintに実データを注入する
     */
    private static function hydrate_data(array $blueprint, array $args) {
        foreach ($blueprint as $key => &$section) {
            if (!isset($section['args'])) {
                $section['args'] = [];
            }

            $source = $section['data_source'] ?? '';
            switch ($source) {
                case 'descendants':
                    $section['args']['ids'] = Dy::get_descendants(self::$shortcode_host_id);
                    break;
                case 'post_search':
                    $section['args']['search'] = Dy::get_title(self::$shortcode_host_id);
                    break;
                case 'host_post_title':
                    self::$shortcode_host_title = (string)Dy::get_title(self::$shortcode_host_id);
                    break;
                case 'category_info':
                    self::fetch_host_categories();
                    break;
            }
        }
        return $blueprint;
    }

    /**
     * 個別コンポーネントの出力
     */
    private static function render_component(array $data) {

        $type = (string)($data['content_type'] ?? 'ERROR');
        $level = (int)($data['level'] ?? 2);
        $tag = 'h' . max(1, min(6, $level));
        $title = (string)($data['title'] ?? 'Untitled Section');

        $html = sprintf('<%1$s>%2$s</%1$s>', $tag, esc_html($title));



        switch ($type) {
            case 'template':
                $name = $data['component'] ?? 'base-unit';
                $html .= (string)KxTemplate::get("taskboard/{$name}", $data, false);
                break;
            case 'function':
                if (!empty($data['callback']) && is_callable($data['callback'])) {
                    $html .= (string)call_user_func($data['callback'], $data['args'] ?? []);
                }
                break;
            case 'launcher':
                $html .= (string)KxPostLauncher::run($data['args'] ?? []);
                break;
            case 'inserter':
                $html .= (string)\Kx\Component\QuickInserter::render(
                    self::$shortcode_host_id,
                    $data['args']['title']   ?? null,
                    $data['args']['content'] ?? null,
                    $data['args']['label']   ?? null,
                    'taskboard'
                );
                break;
            default:
                $html .= (string)MSG::error("TaskBoard: '{$type}' is not supported.");
        }

        return (string)$html;
    }

    /**
     * ホスト記事のカテゴリ情報を取得・キャッシュする
     */
    private static function fetch_host_categories() {
        if (!empty(self::$shortcode_host_cat)) return;

        $categories = get_the_category(self::$shortcode_host_id);
        if ($categories && !is_wp_error($categories)) {
            foreach ($categories as $cat) {
                self::$shortcode_host_cat[] = [
                    'id'   => $cat->term_id,
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                ];
            }
        }
    }
}