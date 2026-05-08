<?php
/**
 * [Path]: inc\core\class-kx-director.php
 * 2025-12-27
 */

namespace Kx\Core;

/**
 * KxDirector: kasax_child 統合指揮クラス
 * * 【LLM向】このクラスは全主要コンポーネントへの最短アクセスを提供する。
 * テンプレートや他クラス内では `use Kx\Core\KxDirector as kx;` として利用。
 */
class KxDirector {

    /**
     * システム全体のショートコードを一括登録する
     *
     * @return void
     */
    public static function register_shortcodes() {

        add_shortcode( 'raretu',    [\Kx\Matrix\Orchestrator::class, 'shortcode' ] );
        add_shortcode( 'kx_tp',     [\Kx\Utils\TaskBoard::class,      'shortcode'] );
        add_shortcode( 'kx_ws',     [\Kx\Utils\WorkStation::class,    'shortcode'] );
        add_shortcode( 'anomaly',   [\Kx\Core\KxAiBridge::class,     'render_knowledge_gap_report'] );
        add_shortcode( 'kx',        [\Kx\Launcher\KxPostLauncher::class, 'run' ]);

        // ショートコードクラス (Kx\Core\ShortCode) への移行
        add_shortcode( 'ghost',                 [ShortCode::class, 'shortcode_ghost_renderer'] );
        add_shortcode( 'kx_format',             [ShortCode::class, 'shortcode_ghost_renderer'] ); // 旧ショートコード
        add_shortcode( 'dump',                  [ShortCode::class, 'dump_shortcode'] );
        add_shortcode( 'kx_age',                [ShortCode::class, 'renderTimelineAgeList'] );
        add_shortcode( 'kasax_index',           [ShortCode::class, 'outline_shortcode'] );

        add_shortcode( 'google_spreadsheets',   [ShortCode::class, 'kxsc_google_spreadsheets'] );
        add_shortcode( 'csv_spreadsheets',      [ShortCode::class, 'kxsc_csv_spreadsheets'] );
        add_shortcode( 'kasax_phpinfo',         [ShortCode::class, 'kxsc_Info_php'] );

        add_shortcode( 'get_text_file',         [ShortCode::class, 'get_text_file'] );
        add_shortcode( 'get_text_folder',       [ShortCode::class, 'get_text_files_in_folder'] );

        add_shortcode( 'full_scale_maintenance', [ShortCode::class, 'render_database_maintenance_panel'] );
    }

    /**
     * タイトルから投稿IDのリストを取得
     *
     * @param string $title
     * @return int[]
     */
    public static function get_ids_by_title(string $title): array {
        return (array)\Kx\Database\dbkx0_PostSearchMapper::get_ids_by_title($title);
    }

    /**
     * 投稿に関連付けられたショートコードを取得
     *
     * @param int|string $post_id
     * @return string
     */
    public static function get_short_code($post_id): string {
        return (string)\Kx\Database\dbkx1_DataManager::get_short_code($post_id);
    }

    /**
     * 投稿に関連付けられた羅列コードを取得
     *
     * @param int|string $post_id
     * @return string
     */
    public static function get_raretu_code($post_id): string {
        return (string)\Kx\Database\dbkx1_DataManager::get_raretu_code($post_id);
    }

    /**
     * 投稿のタイプ判定
     *
     * @param string|array $type_name 単一の文字列または配列
     * @param int|string|null $post_id
     * @param string $logic 'OR' | 'AND'
     * @return bool
     */
    public static function is_type($type_name, $post_id = null, string $logic = 'OR'): bool {
        // $type_name の前の "string" を削除しました。これで配列も受け取れます。
        return (bool)\Kx\core\TitleParser::is_type($type_name, $post_id, $logic);
    }

    /**
     * 統合された記事かどうかを判定
     *
     * @param int|string $post_id
     * @return bool
     */
    public static function is_integrated($post_id): bool {
        return (bool)\Kx\Utils\Toolbox::is_integrated($post_id);
    }

    /**
     * 指定タイトルの存在を確認し、リンクまたはインサーターを返す
     *
     * @param int    $base_post_id 起点となる投稿ID
     * @param string $target_title 検索対象のフルタイトル（階層パス込み）
     * @param array  $args         [text: 表示名, mode: KxLinkモード, content: 新規作成時の本文]
     * @return string 生成されたHTML
     */
    public static function render_smart_link(int $base_post_id, string $target_title, array $args = []): string {
        return (string)\Kx\Utils\Toolbox::render_smart_link($base_post_id, $target_title, $args);
    }

    /**
     * デバッグ用ダンプ出力
     *
     * @param mixed $args
     * @return string
     */
    public static function dump($args): string {
        return (string)\Kx\Utils\Toolbox::dump($args);
    }

    /**
     * カテゴリ検索ボックスの描画
     *
     * @param array $args
     * @return string
     */
    public static function category_search_box(array $args): string {
        return (string)\Kx\Utils\Toolbox::category_search_box($args);
    }

}

if (!class_exists('Kx')) {
    class_alias(\Kx\Core\KxDirector::class, 'Kx');
}