<?php
/**
 * [Path]: inc\core\class-kx-director.php
 * 2025-12-27
 */

namespace Kx\Core;

//use Kx\Core\ShortCode;      // ショートコード登録・実行

//use Kx\Core\SystemConfig as Su;
//use Kx\Core\DynamicRegistry as Dy;
//use Kx\Utils\KxMessage as Msg;

/**
 * KxDirector: kasax_child 統合指揮クラス
 * * 【LLM向】このクラスは全主要コンポーネントへの最短アクセスを提供する。
 * テンプレートや他クラス内では `use Kx\Core\KxDirector as kx;` として利用。
 */
class KxDirector {

    /**
     * システム全体のショートコードを一括登録する
     */
    public static function register_shortcodes() {

        add_shortcode( 'raretu',    [\Kx\Matrix\Orchestrator::class, 'shortcode' ] );
        add_shortcode( 'kx_tp',     [\Kx\Core\TaskBoard::class,      'shortcode'] );
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


    public static function get_ids_by_title(string $title) {
        return \Kx\Database\dbkx0_PostSearchMapper::get_ids_by_title($title);
    }

    public static function get_short_code($post_id) {
        return \Kx\Database\dbkx1_DataManager::get_short_code($post_id);
    }

    public static function get_raretu_code($post_id) {
        return \Kx\Database\dbkx1_DataManager::get_raretu_code($post_id);
    }


    public static function is_type($type_name, $post_id = null, $logic = 'OR') {
        return \Kx\core\TitleParser::is_type($type_name, $post_id, $logic);
    }

    public static function is_integrated($post_id): bool {
        return \Kx\Utils\Toolbox::is_integrated($post_id);
    }

    /**
     * 指定タイトルの存在を確認し、リンクまたはインサーターを返す（汎用教養関数）
     *
     * @param int    $base_post_id 起点となる投稿ID
     * @param string $target_title 検索対象のフルタイトル（階層パス込み）
     * @param array  $args         [text: 表示名, mode: KxLinkモード, content: 新規作成時の本文]
     * @return string 生成されたHTML
     */
    public static function render_smart_link(int $base_post_id, string $target_title, array $args = []): string {
        return \Kx\Utils\Toolbox::render_smart_link($base_post_id, $target_title, $args);
    }

    //dump
    public static function dump($args) {
        return \Kx\Utils\Toolbox::dump($args);
    }

    //旧：kx_category_search
    public static function category_search_box($args) {
        return \Kx\Utils\Toolbox::category_search_box($args);
    }

}

if (!class_exists('Kx')) {
    class_alias(\Kx\Core\KxDirector::class, 'Kx');
}