<?php
/**
 *[Path]: inc/core/class-kx-assets.php
 */

namespace Kx\Core;

//use Su;
use \Kx\Core\TitleParser;

/**
 * Assets 管理クラス
 * 2025-12-28 理想構成対応版
 */
class Assets {
    public static function init() {
        // フロントエンド読み込み
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_frontend'], 20);
        // 管理画面・ツール読み込み
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin']);
    }


    /**
     * フロントエンド専用の読み込み
     */
    public static function enqueue_frontend() {
        $uri = get_stylesheet_directory_uri() . '/assets/css';
        $path = get_stylesheet_directory() . '/assets/css';

        $ver = function($rel_path) use ($path) {
            return file_exists("$path/$rel_path") ? filemtime("$path/$rel_path") : '1.0.0';
        };

        // 基盤読み込み
        //self::enqueue_base_assets($uri, $ver);
        self::enqueue_directory("$path/Base", "$uri/Base", 'kx-base', [], $ver);

        // 3. 【Layout: Visual】フロント限定の装飾・アニメーション（a:hover拡大など）
        wp_enqueue_style('kx-layout-visual', "$uri/Layout/front-visual.css", ['kx-base-common-ui'], $ver('Layout/front-visual.css'));

        // 4. 【Layout: Width】解像度判定
        //$is_1920 = TitleParser::is_type('prod_work_production_log',get_the_ID());
        $is_1920 = \Kx\Utils\Toolbox::isWideLayoutDisplay(get_the_ID());

        $res_file = $is_1920 ? 'screen-1920.css' : 'screen-1440.css';
        wp_enqueue_style('kx-layout-width', "$uri/options/$res_file", ['kx-layout-visual'], $ver("options/$res_file"));

        // 5. 【Layout: Level】権限判定
        $level = (current_user_can('level_10') || current_user_can('level_7')) ? 'Lv-10' : 'Lv-0';
        wp_enqueue_style('kx-layout-level', "$uri/options/$level.css", ['kx-layout-width'], $ver("options/$level.css"));

        // 6. 【Theme】カラー
        self::enqueue_directory("$path/Theme", "$uri/Theme", 'kx-theme', ['kx-layout-visual'], $ver);

        // 7. 【Modules】特定テンプレート専用
        self::enqueue_directory("$path/Modules", "$uri/Modules", 'kx-mod', ['kx-layout-visual'], $ver);

        // 8. 【Admin】特定テンプレート専用（Adminフォルダ化している場合はそちらを読み込み）
        //if ( is_page_template('page-templates/ResizePage.php') ) {
        self::enqueue_directory("$path/Admin", "$uri/Admin", 'kx-admin-page', ['kx-mod-consolidator'], $ver);
        //}
    }



    /**
     * 管理画面（独自ページ ?page=kx）用の読み込み
     * front-visual.css を除外することでレイアウト崩れを防ぐ
     */
    public static function enqueue_admin($hook) {
        if ( isset($_GET['page']) && $_GET['page'] === 'kx' ) {
            $uri = get_stylesheet_directory_uri() . '/assets/css';
            $path = get_stylesheet_directory() . '/assets/css';

            $ver = function($rel_path) use ($path) {
                $full_path = "$path/$rel_path";
                return file_exists($full_path) ? filemtime($full_path) : '1.0.0';
            };

            // 1. 基盤の読み込み
            // ※依存関係を一度空にする（[]）ことで、Base側の不具合にAdminが巻き込まれるのを防ぎます。
            self::enqueue_directory("$path/Base", "$uri/Base", 'kx-admin-base', [], $ver);

            // 2. 管理専用の読み込み
            // ※依存関係を一度外してテストしてください。これで色が変われば依存関係（名前）のミスです。
            self::enqueue_directory("$path/Admin", "$uri/Admin", 'kx-admin-core', [], $ver);

            // テスト後、成功したら依存先を戻す
            // self::enqueue_directory("$path/Admin", "$uri/Admin", 'kx-admin-core', ['kx-admin-base-common-ui'], $ver);
        }
    }



    /**
     * 指定されたディレクトリ内のすべてのCSSファイルを自動的にスキャンし、一括エンキューする。
     *
     * 【機能概要】
     * 1. 引数で指定された物理パス内の `.css` ファイルを glob() で検索する。
     * 2. ファイル名（拡張子なし）をスラグとして、一意のハンドル名を生成する。
     * 3. 依存関係（$deps）を保持したまま、WordPressのキューに登録する。
     * 4. 各ファイルの最終更新日時をバージョン番号として付与し、キャッシュ問題を自動回避する。
     *
     * 【ハンドル名の生成規則】
     * ハンドル名 = $prefix + "-" + ファイル名(slug)
     * 例: Adminフォルダに admin-custom.css があり、prefixが 'kx-admin' の場合
     * => 'kx-admin-admin-custom'
     *
     * @param string   $dir_path   スキャン対象ディレクトリの絶対パス（例: get_stylesheet_directory() . '/assets/css/Base'）
     * @param string   $dir_uri    スキャン対象ディレクトリのURL（例: get_stylesheet_directory_uri() . '/assets/css/Base'）
     * @param string   $prefix     生成されるハンドル名の接頭辞。階層（Base, Layout等）を識別するために使用。
     * @param string[] $deps       依存するハンドル名の配列。このディレクトリ内の全ファイルが、指定されたハンドルを先に読み込むようになる。
     * @param callable $ver_func   バージョン生成用のクロージャ。相対パスを受け取り、filemtime等の更新日時を返す。
     *
     * @return void
     */
    private static function enqueue_directory($dir_path, $dir_uri, $prefix, $deps, $ver_func) {
        if ( !is_dir($dir_path) ) return;

        $files = glob("$dir_path/*.css");
        if ( !$files ) return;

        foreach ( $files as $file ) {
            $filename = basename($file);
            $slug = pathinfo($filename, PATHINFO_FILENAME);
            $handle = $prefix . '-' . $slug;

            $rel_path = str_replace(get_stylesheet_directory() . '/assets/css/', '', $file);

            wp_enqueue_style(
                $handle,
                $dir_uri . '/' . $filename,
                $deps,
                $ver_func($rel_path)
            );
        }
    }
}

// 初期化実行
Assets::init();