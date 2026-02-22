<?php
/**
 * [Path]: inc\admin\admin-dashboard.php
 *
 */

namespace Kx\Admin;

//use Kx\Core\SystemConfig as Su;
use Kx\Database\DB;
//use Kx\Database\Hierarchy;


/**
 * 物語制作支援システム 管理画面コントローラー
 */
class Dashboard {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
    }


    /**
     * 左メニューへの登録：重複を完全に排除し、3項目に絞り込む
     */
    public function add_admin_menu() {
        //echo '++';

        $parent_slug = 'kx-dashboard';

        // 1. 親メニューを登録（これがメニューバーのメイン名になる）
        add_menu_page(
            '管理画面：kasax_child',
            'kasax_child管理',
            'manage_options',
            $parent_slug,
            [$this, 'render_status_page'],
            'dashicons-rest-api',
            2
        );

        // 2. 1番目のサブメニューを「名前なし」で登録（ここが肝です）
        // 親と同じスラッグ・同じ関数を指定し、メニュー名を「空文字列」にすることで
        // 親をクリックした時の遷移先を維持したまま、サブメニュー一覧からのみ消去できます。
        add_submenu_page(
            $parent_slug,
            '管理画面：kasax_child',
            'ステータス', // ← ここを空にする
            'manage_options',
            $parent_slug,
            [$this, 'render_status_page']
        );

        // 3. メンテナンス（2番目の位置を維持）
        add_submenu_page(
            $parent_slug,
            'メンテナンス',
            'メンテナンス',
            'manage_options',
            'maintenance',
            [$this, 'render_maintenance']
        );

        // 4. DBエクスプローラー（3番目の位置を維持）
        add_submenu_page(
            $parent_slug,
            'Kx Database Explorer',
            'DBエクスプローラー',
            'manage_options',
            'db-explorer',
            [$this, 'render_db_explorer_page']
        );

        // 5. Hierarchy Table管理（新規追加）
        add_submenu_page(
            $parent_slug,
            'Hierarchy Table管理',
            'Hierarchy管理',
            'manage_options',
            'kx-hierarchy-manager', // スラッグ
            [$this, 'render_hierarchy_manager'] // 呼び出すメソッド名
        );

        // 6. データ整合性マネージャー（追加）
        add_submenu_page(
            $parent_slug,
            'データ整合性マネージャー',
            'Data Integrity',
            'manage_options',
            'kx-integrity-manager',
            [$this, 'render_integrity_manager']
        );
    }


    /**
     * ステータス一覧（1ページ目/トップ）の描画
     * Dy::get_system() のキャッシュデータを利用して高速化
     */
    public function render_status_page() {

        // 全体を取得
        $sys = \Kx\Core\DynamicRegistry::get_system();

        //var_dump($sys);

        $content_stats  = $this->get_content_stats();
        $error_log_data = $this->get_mysql_error_stats();

        // 階層アクセスを安全にする（ integrity がなくても空配列を代入 ）
        $integrity = $sys['integrity'] ?? [];
        $counts    = $integrity['counts'] ?? [];
        $is_online = $sys['laravel_online'] ?? false;

        $stats_data = [
            'content'       => $content_stats,
            'node_count'    => ($counts['hierarchy_real'] ?? 0) + ($this->get_virtual_count() ?? 0),
            'virtual_nodes' => ($this->get_virtual_count() ?? 0),
            'real_nodes'    => ($counts['hierarchy_real'] ?? 0), // hierarchy_real がない場合のガード
            'kx0_count'     => ($counts['kx0'] ?? 0),            // kx0 がない場合のガード
            'is_synced'     => ($integrity['is_synced'] ?? false),
            'error_logs'    => $error_log_data,
            'laravel'       => [
                'is_active' => $is_online,
                'label'     => $is_online ? 'Laravel Online' : 'Laravel Offline',
                'color'     => $is_online ? '#46b450' : '#dc3232',
                'icon'      => $is_online ? 'dashicons-yes' : 'dashicons-no'
            ],
            'updated_at'    => $sys['updated_at'] ?? '---'
        ];

        $this->load_view('status-list.php', $stats_data);
    }


    /**
     * テンプレートの読み込み（データ受け渡し対応版）
     */
    private function load_view($file_name, $data = []) {
        $view_path = get_stylesheet_directory() . '/templates/admin/' . $file_name;

        if (file_exists($view_path)) {
            // 重要：配列のキーを変数名として展開する
            // これにより $data['content'] が $content としてテンプレート内で使えるようになります
            if (!empty($data)) {
                extract($data);
                // 念のため、テンプレート側が旧仕様（$stats['content']）を期待している場合のために定義
                $stats = $data;
            }

            include $view_path;
        } else {
            echo '<div class="notice notice-error"><p>Viewファイルが見つかりません: ' . esc_html($file_name) . '</p></div>';
        }
    }



    /**
     * 3. 独自DBエクスプローラー（統合画面）のレンダリング
     */
    public function render_db_explorer_page() {
        $this->load_view('db-explorer.php');
    }


    /**
     * maintenanceページのデータ。
     */
    public function render_maintenance() {
        // 最新の投稿30件を取得
        $recent_posts = get_posts([
            'post_type'      => 'post',
            'posts_per_page' => 30,
            'post_status'    => 'publish',
            'orderby'        => 'modified', // 更新日で並び替え
            'order'          => 'DESC',     // 降順（新しい順）
        ]);


        // 最新の固定ページ10件を取得
        $recent_pages = get_posts([
            'post_type'      => 'page',
            'posts_per_page' => 5,
            'post_status'    => 'publish',
            'orderby'        => 'modified', // 更新日で並び替え
            'order'          => 'DESC',     // 降順（新しい順）
        ]);

        // MySQLエラーログの取得と解析
        //$error_log_data = $this->get_mysql_error_stats();

        // システム診断（メンテナンス）結果の取得
        $maintenance_data = $this->get_maintenance_stats();



        // テンプレートに渡すデータ
        $stats = [
            'recent_posts'  => $recent_posts,
            'recent_pages'  => $recent_pages,
            'maintenance'   => $maintenance_data,
        ];

        // 読み込むビューファイル名を確認
        $view_path = get_stylesheet_directory() . '/templates/admin/maintenance.php';

        if (file_exists($view_path)) {
            // $stats 変数がテンプレート内で利用可能になります
            include $view_path;
        } else {
            echo '<div class="wrap"><h1>Error</h1><p>Viewファイルが見つかりません。</p></div>';
        }
    }

    /**
     * Hierarchy Table管理ページのレンダリング
     */
    public function render_hierarchy_manager() {
        global $wpdb;

        // 1. 投稿作成処理 (既存通り)
        if (isset($_POST['create_virtual_post']) && isset($_POST['target_title'])) {
            $this->handle_create_post(stripslashes($_POST['target_title']));
        }

        // 2. データの取得
        $results = $wpdb->get_results("
            SELECT * FROM wp_kx_hierarchy
            WHERE is_virtual = 1 OR alert != 0
            ORDER BY full_path ASC
        ", ARRAY_A);

        $grouped_data = [];
        foreach ($results as $row) {
            $path = $row['full_path'];
            $pos  = mb_strpos($path, '≫');
            $root = ($pos !== false) ? mb_substr($path, 0, $pos) : $path;

            if (!isset($grouped_data[$root])) {
                $grouped_data[$root] = [
                    'rows' => [],
                    'counts' => ['v' => 0, 'a1' => 0, 'a2' => 0]
                ];
            }

            $grouped_data[$root]['rows'][] = $row;

            // カウントアップ
            if ($row['is_virtual']) {
                $grouped_data[$root]['counts']['v']++;
            } elseif ($row['alert'] == 2) {
                $grouped_data[$root]['counts']['a2']++;
            } elseif ($row['alert'] == 1) {
                $grouped_data[$root]['counts']['a1']++;
            }
        }

        // ソート処理 (キーに対して)
        ksort($grouped_data, SORT_STRING | SORT_FLAG_CASE);

        // 各グループ内のソート
        foreach ($grouped_data as $root => &$data) {
            usort($data['rows'], function($a, $b) {
                return strnatcasecmp($a['full_path'], $b['full_path']);
            });
        }
        unset($data);

        $this->load_view('hierarchy-manager.php', ['groups' => $grouped_data]);
    }

    /**
     * 仮想ノードを実体化（投稿作成）
     */
    private function handle_create_post($title) {
        if (!current_user_can('manage_options')) return;

        // 投稿作成
        $post_id = wp_insert_post([
            'post_title'   => $title,
            'post_status'  => 'publish',
            'post_type'    => 'post',
        ]);

        // 成功判定
        if ($post_id && !is_wp_error($post_id)) {
            // 現在の管理画面のURLを取得
            $base_url = admin_url('admin.php');

            // パラメータを個別にセット
            $redirect_url = add_query_arg('page', 'kx-hierarchy-manager', $base_url);
            $redirect_url = add_query_arg('kx_created', '1', $redirect_url);
            $redirect_url = add_query_arg('target_id', $post_id, $redirect_url);

            // デバッグ用：念のためリダイレクト直前にMsgへ入れる（リダイレクトで消える可能性はあるが）
            \Kx\Utils\KxMessage::info("ID:{$post_id} を作成しました。");

            wp_redirect($redirect_url);
            exit;
        } else {
            \Kx\Utils\KxMessage::error("投稿の作成に失敗しました: " . $title);
        }
    }

    /**
     * MySQLエラーログを解析して結果を返す（内部用）
     */
    private function get_mysql_error_stats() {
        $logFilePath = 'D:/00_WP/xampp/mysql/data/mysql_error.log';
        $today = date('Y-m-d');
        $error_found = false;
        $error_time = '';
        $log_content = '';

        if (file_exists($logFilePath) && is_readable($logFilePath)) {
            $logFile = fopen($logFilePath, 'r');
            if ($logFile) {
                // 今日付の[ERROR]を検索するパターン
                $date_pattern = '/(' . preg_quote($today) . '.*)(\d{2}:\d{2}:\d{2}).*\[ERROR\]/';

                while (($line = fgets($logFile)) !== false) {
                    if (preg_match($date_pattern, $line, $matches)) {
                        $error_found = true;
                        $error_time = $matches[2];
                        // ログ内容を蓄積（セキュリティのためパスを一部隠蔽処理）
                        $line_colored = preg_replace('/\'[.]\\\wp0\\\.*bd\'/', '<span style="color:cyan;">$0</span>', $line);
                        $log_content .= esc_html($line_colored) . "\n<br>";
                    }
                }
                fclose($logFile);
            }
        }

        return [
            'has_error' => $error_found,
            'today'     => $today,
            'time'      => $error_time,
            'raw_html'  => $log_content,
        ];
    }



    /**
     * システム診断関数を一括実行し、結果を配列で返す
     */
    private function get_maintenance_stats() {
        $results = [];

        // 各診断関数の実行結果をラベル付きで格納
        // 注意：これらの関数が未定義の場合にエラーにならないよう function_exists でチェックしています
        $results['db_maintenance'] = DB::db_Maintenance();
        $results['title_mismatch'] = DB::check_title_tag_mismatch() ;
        $results['post_error_id']  = DB::get_Post_error_id() ;
        $results['trashed_posts']  = DB::list_trashed_posts_by_deleted_date();
        $results['integrity_mismatch'] = DB::check_db_integrity_mismatch();
        return $results;
    }



    /**
     * 投稿と固定ページの統計（読者用文字数 vs タグ込文字数）を取得
     */
    private function get_content_stats() {
        global $wpdb;
        $stats = [];

        foreach (['post', 'page'] as $type) {
            $posts = $wpdb->get_results($wpdb->prepare("
                SELECT post_content
                FROM $wpdb->posts
                WHERE post_type = %s AND post_status = 'publish'
            ", $type));

            $count = count($posts);
            $raw_chars = 0;     // タグ込み（DB上の文字数）
            $visible_chars = 0; // タグなし（読者が見る文字数）

            foreach ($posts as $p) {
                $content = $p->post_content;
                // 1. タグ込みをカウント
                $raw_chars += mb_strlen($content);
                // 2. タグを除去してカウント
                $visible_chars += mb_strlen(strip_tags($content));
            }

            $stats[$type] = [
                'count'   => $count,
                'raw'     => $raw_chars,
                'visible' => $visible_chars,
                'diff'    => $raw_chars - $visible_chars // タグ等の「装飾」にかかっているコスト
            ];
        }

        return $stats;
    }




    private function get_virtual_count() {
        global $wpdb;
        return $wpdb->get_var("SELECT COUNT(*) FROM wp_kx_hierarchy WHERE is_virtual = 1");
    }

    /**
     * 整合性マネージャーのレンダリング
     */
    public function render_integrity_manager() {
        // Toolboxクラスのメソッドを呼び出し、HTMLを受け取る
        $panel_html = \Kx\core\ShortCode::render_database_maintenance_panel();

        // 管理画面のラップ用テンプレートに流し込む
        $this->load_view('integrity-manager.php', ['panel_html' => $panel_html]);
    }

}

// インスタンス化
new Dashboard();