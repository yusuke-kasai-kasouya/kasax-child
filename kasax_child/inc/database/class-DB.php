<?php
/**
 * [Path]: inc/database/class-DB.php
 */

namespace Kx\Database;

use Kx\Core\SystemConfig as Su;
use \Kx\Database\dbkx0_PostSearchMapper as dbkx0;
use \Kx\Database\dbkx1_DataManager as dbkx1;
use \Kx\Database\Hierarchy;
use \Kx\Database\dbKxAiMetadataMapper;
use \Kx\Database\dbkx_SharedTitleManager as dbkx_Shared;
use \Kx\Utils\KxMessage as Msg;

class DB {

    /**
     * テーブルを作成。テーマ有効化時に実行
     *
     */
    public static function create_custom_tables() {
        global $wpdb; // WordPressのデータベースアクセスオブジェクト
        $charset_collate = $wpdb->get_charset_collate();

        // 作成したいテーブルの定義を配列で用意
        $tables = [
            'kx_0' => [
                'sql' => "id bigint(20) unsigned NOT NULL,
                    title varchar(255) NOT NULL,
                    depth int(3) DEFAULT NULL,
                    type varchar(50) DEFAULT NULL,
                    wp_updated_at datetime DEFAULT NULL,
                    PRIMARY KEY  (id),
                    KEY title (title(191)),
                    KEY depth (depth),
                    KEY type (type),
                    KEY wp_updated_at (wp_updated_at)"
            ],
            'kx_1' => [
                'sql' => "id mediumint(9) NOT NULL,
                    title varchar(255) NOT NULL,
                    tag varchar(255) DEFAULT NULL,
                    has_tag tinyint(1) DEFAULT NULL,
                    short_code varchar(50) DEFAULT NULL,
                    raretu_code text DEFAULT NULL,
                    ghost_to mediumint(9) DEFAULT NULL,
                    consolidated_to mediumint(9) DEFAULT NULL,
                    consolidated_from mediumint(9) DEFAULT NULL,
                    overview_to mediumint(9) DEFAULT NULL,
                    overview_from mediumint(9) DEFAULT NULL,
                    flags varchar(255) NOT NULL DEFAULT '',
                    json text DEFAULT NULL,
                    wp_updated_at datetime DEFAULT NULL,
                    time int(11) NOT NULL,
                    PRIMARY KEY  (id),
                    KEY title (title(191)),
                    KEY tag (tag(191)),
                    KEY has_tag (has_tag),
                    KEY short_code (short_code),
                    KEY ghost_to (ghost_to),
                    KEY consolidated_to (consolidated_to),
                    KEY consolidated_from (consolidated_from),
                    KEY overview_to (overview_to),
                    KEY overview_from (overview_from),
                    KEY flags (flags(191)),
                    KEY wp_updated_at (wp_updated_at),
                    KEY time (time)"
            ],
            'kx_shared_title' => [
                'sql' => "title varchar(255) NOT NULL,
                    id_lesson mediumint(9) NOT NULL DEFAULT 0,
                    id_sens mediumint(9) NOT NULL DEFAULT 0,
                    id_study mediumint(9) NOT NULL DEFAULT 0,
                    id_data mediumint(9) NOT NULL DEFAULT 0,
                    date int(8) unsigned NOT NULL DEFAULT 0,
                    tag varchar(500) DEFAULT NULL,
                    label varchar(50) DEFAULT NULL,
                    json text DEFAULT NULL,
                    time int(11) NOT NULL,
                    PRIMARY KEY  (title),
                    KEY id_lesson (id_lesson),
                    KEY id_sens (id_sens),
                    KEY id_study (id_study),
                    KEY id_data (id_data),
                    KEY date (date),
                    KEY tag (tag(191)),
                    KEY label (label)"
            ],
            'kx_hierarchy' => [
                'sql' => "full_path varchar(255) NOT NULL,
                        post_id mediumint(9) DEFAULT 0,
                        parent_path varchar(255) DEFAULT NULL,
                        level tinyint(4) NOT NULL,
                        is_virtual tinyint(1) DEFAULT 1,
                        alert tinyint(1) NOT NULL DEFAULT 0,
                        json text DEFAULT NULL,
                        time int(11) NOT NULL,
                        PRIMARY KEY (full_path),
                        KEY post_id (post_id),
                        KEY parent_path (parent_path(191)),
                        KEY level (level),
                        KEY is_virtual (is_virtual),
                        KEY alert (alert)"
            ],
            'kx_temporary' =>
            [
                'sql' => "type varchar(255) NOT NULL,
                        text1 varchar(255) NULL,
                        text2 varchar(255) NULL,
                        text3 varchar(255) NULL,
                        text4 varchar(255) NULL,
                        text5 varchar(255) NULL,
                        text6 varchar(255) NULL,
                        text7 varchar(255) NULL,
                        json text DEFAULT NULL,
                        text text DEFAULT NULL,
                        time int(11) NOT NULL,
                        PRIMARY KEY  (type)"
            ],
            'kx_ai_metadata' => [
                'sql' => "post_id bigint(20) unsigned NOT NULL,
                        post_modified datetime NOT NULL,
                        top_keywords longtext COLLATE utf8mb4_bin DEFAULT NULL,
                        vector_status tinyint(1) DEFAULT 0,
                        vector_data longtext COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
                        ai_score_stat float DEFAULT 0,
                        ai_score_context float DEFAULT 0,
                        ai_score float DEFAULT 0,
                        ai_score_deviation float DEFAULT 0,
                        last_vectorized_at datetime DEFAULT NULL,
                        last_analyzed_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY  (post_id),
                        KEY post_modified (post_modified),
                        KEY last_analyzed_at (last_analyzed_at),
                        KEY ai_score (ai_score),
                        KEY ai_score_deviation (ai_score_deviation),
                        KEY vector_data (vector_data(768)),
                        KEY last_vectorized_at (last_vectorized_at)"
            ]
        ];

        // テーブルごとに作成処理を実行
        foreach ($tables as $table_name => $table_data) {
                $full_table_name = $wpdb->prefix . $table_name;
                $sql = "CREATE TABLE IF NOT EXISTS $full_table_name ({$table_data['sql']}) $charset_collate;";
                require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
                dbDelta($sql);
        }
    }


    /**
     * メンテナンス系
     * タイトルとタグのミスマッチを検出。
     * 2024-06-27
     *
     * @param [type] $args
     * @return void
     */
    public static function check_title_tag_mismatch(){

        $_save = 1;

        $_category_ids = [ 510,1162 ,1191];

        //$_category_id = 510;//増えた場合はループ化。2024-06-27

        $str2 = '';
        foreach( $_category_ids as $_category_id):
            $args = [
                'cat'								=>	$_category_id,
                'posts_per_page'		=>	-1,
                'post_type'					=>	'post',	//投稿ページのみ
            ];

            $the_query = new \WP_Query( $args );

            $str = NULL;
            // ■The Loop■
            while ( $the_query->have_posts() ) :

                $the_query->the_post();

                $_title = get_the_title();
                $_id    = get_the_ID();

                preg_match('/∬\d{1,}≫(c\w{1,}\d)/', $_title , $matches);

                $_ok = NULL;
                $_tags = get_the_tags( $_id );

                if( is_array( $_tags ) )
                {
                    $_tag_name = NULL;
                    foreach( $_tags as $tag):

                        if( !empty( $matches[1] ) && $tag->name == $matches[1] )
                        {
                            $_ok = 'OK';
                        }
                        elseif( empty( $matches[1] ) )
                        {
                            echo 'キャラクターではない：';
                            echo $_title;
                            echo '<hr>';
                            //$_ok = 'OK';
                            $matches[1] = 'タイトルマッチせず';
                        }

                        $_tag_name .= $tag->name;
                        $_tag_name .= '<br>';

                    endforeach;
                }
                else
                {
                    $_tag_name .= 'tag_ELSE<br>';
                }

                if( empty( $_ok ))
                {
                    $str .= 'NG:';
                    $str .= $_id;
                    $str .= '<br>';
                    $str .= $_title;
                    $str .= '<br>matches:';
                    $str .= $matches[1];
                    $str .= '<br>';

                    $str .=  $_tag_name;

                    $str .= '<a href="';
                    $str .= get_permalink( $_id );
                    $str .=  '">LInk</a>';
                    $str .= '<hr>';

                    $_update_ids[] =  $_id;
                }

            endwhile;

            if( !empty( $str ))
            {
                $str2 = 'CATチェック、問題あり:'.$_category_A->name . '≫c'.'<br>';
                $str2 .= '<div style="color:red;">';
                $str2 .= count( $_update_ids );
                $str2 .= '件</div><hr>';
                $str2 .= $str;

                echo '<div style="color:red;">';
                echo count( $_update_ids );
                echo '件</div><hr>';



                if( !empty( $_save ) )
                {
                    $_reload = 0;
                    foreach( $_update_ids as $_id_up):
                        //テンプレートmenuからのアップデート用。2023-08-04
                        $post = get_post( $_id_up );

                        $my_post = array(
                            'ID'						=> $_id_up ,
                            'post_title'		=> get_the_title( $_id_up ),
                            'post_content'	=> $post->post_content,
                        ) ;
                        wp_update_post( $my_post ) ;
                        $_reload++;

                        if( $_reload == 8 )
                        {
                            echo 'ID：';
                            echo $_id_up;
                            echo '<br>';
                            echo $_reload;
                            $_reload = 0;
                            echo '<script type="text/javascript">window.location.reload();</script>';
                        }
                    endforeach;
                }
            }
            else
            {
                $_category = get_category( $_category_id );
                if ( $_category) {
                    $_category_name = $_category->name; // カテゴリー名を出力
                }
                $str2 .= 'CATチェック、問題なし:'. $_category_name.'<br>';
            }
            //以上・
        endforeach;

        return $str2;
    }



    /**
     * 存在してはいけないpostを検索。
     * plug-in用。
     * 2023-08-30
     *
     * @return void
     */
    public static function get_Post_error_id() {

        $the_query = new \WP_Query(
        [
            'post_type'      => 'post',
            'posts_per_page' => -1,
        ]	);


        if( empty( $the_query->found_posts ) )
        {
            //Error。
            return;
        }

        $i = 0;
        $str = '';
        // The Loop
        while ( $the_query->have_posts() ) :

            $the_query->the_post();
            $_id = get_the_ID();
            $_title = get_the_title( $_id );

            if( !preg_match( '/≫/' , $_title  ) && empty(Su::get('title_prefix_map')['prefixes'][ $_title ] ) )
            {
                $_array[] = $_id;
                $str .= $_id;

                $str .= '<div>';
                $str .= '<a href="'. get_permalink( $_id ) .'">'.get_the_title( $_id ).'</a>';

                $str .= '</a>';
                $str .= '</div>';
            }

            $_title_array = explode('≫',$_title);

            if( empty(Su::get('title_prefix_map')['prefixes'][ $_title_array[0] ] ) )
            {
                $_array[] = $_id;
                $str .= $_id;

                $str .= '<div>タイトル表記のエラー：';
                $str .= '<a href="'. get_permalink( $_id ) .'">'.get_the_title( $_id ).'</a>';

                $str .= '</a>';
                $str .= '</div>';

            }


            //書き換え用。
            if(
                get_post_type( $_id ) == 'post'
                && get_post_field( 'post_author' , $_id ) != 2
            )
            {
                $i++;

                if( $i == 30 )
                {
                    $str .= '
                        <script>
                        window.location.reload();
                        </script>
                    ';

                    break;
                }
                $str .= \Kx\Utils\Toolbox::updateAuthorIdByPostType( $_id  );
            }


        endwhile;

        if( !empty($_array  ) )
        {
            $str .= 'エラーpost数：';
            $str .= count( $_array );
            wp_reset_postdata();
        }
        else
        {
            $str .= 'エラーpost';
            $str .= 'なし';
        }

        return $str;
    }



    /**
     * ゴミ箱（trash）にある投稿を更新日時順にリスト表示する
     * * 仕様書「2. 主要コンポーネント」のDBレイヤーおよびUI補助に該当する関数。
     * WordPress標準のwp_postsテーブルを参照し、削除日時の近似値としてpost_modifiedを使用する。
     * * @global wpdb $wpdb WordPressデータベースオブジェクト
     * @return string HTML形式のリスト（h4およびul/liタグ）
     */
    public static function list_trashed_posts_by_deleted_date() {
        global $wpdb;
        $str = '';

        // SQLを少し修正：postmetaから削除日時を取得することを検討
        // ここではシンプルに、ゴミ箱にある記事を最新順で取得
        $trashed_posts = $wpdb->get_results("
            SELECT ID, post_title, post_modified
            FROM {$wpdb->posts}
            WHERE post_status = 'trash'
            ORDER BY post_modified DESC
        ");

        $str .= '<h4 class="__radius_kumi_top20">削除タイトル（直近の変更順）</h4>'; // 既存CSSクラスの活用

        if ($trashed_posts) {
            $str .= '<ul style="list-style: none; padding: 0;">';
            foreach ($trashed_posts as $post) {
                // 編集画面へのリンクを作成（誤って消したか確認するため）
                $edit_link = get_edit_post_link($post->ID);

                $str .= '<li style="margin-bottom: 5px;">'
                    . '<small style="color: #888;">' . esc_html($post->post_modified) . '</small> '
                    . '<strong>' . esc_html($post->post_title) . '</strong>'
                    //. ' <a href="' . esc_url($edit_link) . '" style="font-size: 10px;">[確認]</a>'
                    . '</li>';
            }
            $str .= '</ul>';
        } else {
            $str .= '<p>削除済み投稿はありません。</p>';
        }
        return $str;
    }




    /**
     * 独自テーブル(wp_kx_0)とwp_postsの整合性をチェックする
     * 独自テーブルに存在するが、WP側でゴミ箱(trash)にあるデータをリストアップする
     */
    public static function check_db_integrity_mismatch() {
        global $wpdb;

        $str = '';
        $str .= '<h4 class="__radius_kumi_top20" style="color: #c0c1ffff;">不整合チェック：ゴミ箱内の独自データ</h4>';

        // 独自テーブル(wp_kx_0)に存在するIDの中で、wp_postsで'trash'ステータスになっているものを取得
        // kx_0側にデータがあり、wp_posts側がtrashまたは削除済み（存在しない）ケースを網羅
        $mismatched_posts = $wpdb->get_results("
            SELECT kx.id, kx.title, p.post_status
            FROM wp_kx_0 AS kx
            LEFT JOIN {$wpdb->posts} AS p ON kx.id = p.ID
            WHERE p.post_status = 'trash'
            OR p.ID IS NULL
            ORDER BY kx.id DESC
        ");

        if ($mismatched_posts) {
            $str .= '<p style="font-size: 0.9em; color: #666;">※独自テーブルには残っていますが、WP標準側でゴミ箱に入っているか、投稿自体が消失しているデータです。</p>';
            $str .= '<ul style="background: #fff5f5; border: 1px solid #ffcccc; padding: 10px; list-style: none;">';

            foreach ($mismatched_posts as $post) {
                $status_label = ($post->post_status === 'trash') ? '[ゴミ箱]' : '[WP投稿なし]';

                $str .= '<li style="margin-bottom: 8px; border-bottom: 1px solid #eee; padding-bottom: 4px;">'
                    . '<span style="color: #e22; font-weight: bold; margin-right: 10px;">' . esc_html($status_label) . '</span>'
                    . '<small>ID: ' . esc_html($post->id) . '</small> | '
                    . '<strong>' . esc_html($post->title) . '</strong>'
                    . ' <a href="' . admin_url('post.php?post=' . $post->id . '&action=edit') . '" target="_blank" style="font-size: 11px; margin-left: 10px;">[WP編集画面]</a>'
                    . '</li>';
            }
            $str .= '</ul>';
            $str .= '<p style="color: #d63638; font-weight: bold;margin-left: 40px;">推奨アクション：不要なら独自テーブルからも削除、必要ならWP側で「復元」してください。</p>';
        } else {
            $str .= '<p style="color: #64b7faff;margin-left: 40px;">✔ データの不整合は見つかりませんでした。良好です。</p>';
        }

        return $str;
    }



    /**
     * dbメンテナンス起動。
     *
     * @return void
     */
    public static function db_Maintenance(){
        $ret = '';

        self::db_MaintenanceD_csv( "DB_daily_start" );
        if (class_exists('\Kx\Database\dbkx0_PostSearchMapper')) {
        $ret .= dbkx0::maintenance_orphan_cleanup();
        $ret .= dbkx0::maintenance_get_duplicate_titles();
        }

        //$ret .= k//x_db0( [ 'non' ] , 'Maintenance' )['string'];
        //$ret .= '<br>';
        $ret .= dbkx1::maintenance_cleanup_isolated_records();
        //$ret .= k//x_db1( [ 'non' ] , 'Maintenance' )['string'];
        $ret .= '<br>';
        $ret .= Hierarchy::maintenance_cleanup();
        $ret .= '<br>';
        $ret .= Hierarchy::maintenance_virtual_cleanup();
        $ret .= '<br>';
        $ret .= dbKxAiMetadataMapper::maintenance_cleanup_isolated_records();

        //$ret .= kx_db_Woks( [] , 'Maintenance');
        $ret .= '<br>';
        $ret .= dbkx_Shared::maintenance_cleanup_shared_titles();

        //$ret .= kx_db2( [ 'non' ] , 'Maintenance' )['string'];
        //$ret .= '<br>';
        //$ret .= '<br>';
        //$ret .= kx_db_MaintenanceD_sql();
        //$ret .= kxdbC_main( [] , 'Maintenance');


        self::db_MaintenanceD_csv( "DB_daily_END" );

        return $ret;
    }


    /**
     * データベースからデータを読み込む汎用関数
     *
     * 指定されたテーブルから、柔軟な条件指定（WHERE, LIKE, NOT INなど）でデータを取得します。
     * $wpdb->prepare を使用し、SQLインジェクション対策を標準で備えています。
     *
     * @param string       $table_name         読み込み対象のテーブル名。
     * @param array        $where              WHERE条件（['カラム名' => '値']）。値が配列の場合は 'OR' で結合されます。
     * @param string|array $select_columns     取得カラム名。文字列（'id, title'）または配列（['id', 'title']）で指定。
     * @param int|null     $limit              取得件数制限。nullの場合は全件取得。
     * @param string|null  $order_by           並び順（例: 'date DESC'）。
     * @param string       $condition_operator WHERE条件を結合する論理演算子（'AND' または 'OR'）。
     * @param bool         $use_like           trueで部分一致検索（LIKE '%値%'）を適用、falseで完全一致。
     * @param int|array    $exclude_ids        除外したいID（単一数値または数値の配列）。'id' カラムを対象に NOT IN 処理を行います。
     * * @return array 取得結果の配列（オブジェクトの配列）。データがない場合は空の配列 [] を返します。
     */
    public static function db_Read($table_name, $where = [], $select_columns = '*', $limit = null, $order_by = null, $condition_operator = 'AND', $use_like = false, $exclude_ids = []) {
        global $wpdb;

        // SELECT, FROM の構築
        $select_columns = is_array($select_columns) ? implode(',', $select_columns) : $select_columns;
        $query = "SELECT $select_columns FROM $table_name";

        $where_conditions = [];
        $query_params = [];

        // --- 既存の WHERE 条件構築 ---
        if (!empty($where)) {
            foreach ($where as $column => $value) {
                if (is_array($value)) {
                    $column_conditions = [];
                    foreach ($value as $v) {
                        $column_conditions[] = $use_like ? "$column LIKE %s" : "$column = %s";
                        $query_params[] = $use_like ? '%' . $v . '%' : $v;
                    }
                    $where_conditions[] = '(' . implode(" OR ", $column_conditions) . ')';
                } else {
                    $where_conditions[] = $use_like ? "$column LIKE %s" : "$column = %s";
                    $query_params[] = $use_like ? '%' . $value . '%' : $value;
                }
            }
        }

        // --- 【追加】自己ID（除外ID）の排除処理 ---
        if (!empty($exclude_ids)) {
            $exclude_ids = is_array($exclude_ids) ? array_map('intval', $exclude_ids) : [(int)$exclude_ids];
            // 「id」カラムが除外対象と仮定。もしカラム名が違うなら引数で渡すように要調整
            $placeholders = implode(',', array_fill(0, count($exclude_ids), '%d'));
            $where_conditions[] = "id NOT IN ($placeholders)";
            $query_params = array_merge($query_params, $exclude_ids);
        }

        if (!empty($where_conditions)) {
            $query .= " WHERE " . implode(" $condition_operator ", $where_conditions);
        }

        // ORDER, LIMIT は既存通り
        if (!empty($order_by)) $query .= " ORDER BY $order_by";
        if (!empty($limit)) {
            $query .= " LIMIT %d";
            $query_params[] = $limit;
        }

        $prepared_query = $wpdb->prepare($query, $query_params);
        return $wpdb->get_results($prepared_query);
    }





    /**
     * 指定したテーブルからIDを基準にレコードを物理削除する
     * * @param int    $post_id    削除対象のID（カラム名'id'に対応）
     * @param string $table_name テーブル名（接頭辞込み、または$wpdb->prefixを付与して呼び出すこと）
     * @return bool  削除成功か否か
     */
    public static function delete_by_unique_id($post_id, $table_name) {
        global $wpdb;

        // 1. 存在確認（無駄なクエリ発行を抑制）
        // ※$table を $table_name に修正
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE id = %d",
            $post_id
        ));

        if (!$exists) {
            return false;
        }

        // 2. 削除実行
        $result = $wpdb->delete(
            $table_name,
            ['id' => $post_id],
            ['%d']
        );

        return $result !== false;
    }


    /**
     * CSVファイルにメンテナンス情報を記録する関数
     *
     * 指定された文字列（イベント名など）と現在の日時をCSVファイルに追記します。
     * ファイルの競合を防ぐために、排他ロックをかけて安全に書き込みます。
     *
     * @param string $string 書き込むイベント名などの文字列
     * @return void
     */

    private static function db_MaintenanceD_csv( $string ){
        //var_dump( Su::get('paths')['dir_backup_csv']);
        $dir = Su::get('paths')['dir_backup_csv'];

        //echo $dir;


        //csv書き込み
        $file = $dir . DIRECTORY_SEPARATOR .'schedule.csv'; // 書き込むCSVファイルのパス
        //echo $file;

        //$file = "D:\\00_WP\\CSV_backup\\schedule.csv";


        $timestamp = date("Y-m-d H:i:s"); // 現在の時刻を取得
        $data = array($timestamp, $string); // CSVに書き込むデータ（時刻と固定文字列）

        // ファイルを「追記モード（a）」で開く
        $file_handle = fopen($file, "a");

        // ファイルが正常に開けたか確認
        if( $file_handle )
        {
            flock($file_handle, LOCK_EX);
            $result = fputcsv($file_handle, $data);
            flock($file_handle, LOCK_UN);
            fclose($file_handle);

            // 書き込み失敗時の通知
            if ($result === false) {
                Msg::error('CSV書き込みに失敗しました。');
            }
        } else {
            // ファイルオープン失敗時の通知
            Msg::error('CSVファイルのオープンに失敗しました。');
        }
    }

    /**
     * システム全域のデータベース整合性を一括メンテナンスする
     * * 負荷軽減および不整合ループ防止のため、Transient（キャッシュ）を利用した
     * 5秒間のクールタイム制限を設けている。
     *
     * @return string|void 実行結果メッセージ、またはクールタイム中の場合は空
     */
    public static function maintenance_all_cleanup() {

        // --- 1. 二重起動・連続実行の抑制（5秒ロック） ---
        $lock_key = 'kx_maintenance_lock';
        if (get_transient($lock_key)) {
            return; // 5秒以内なら何もしない
        }
        // 5秒間のロックをセット
        set_transient($lock_key, true, 5);

        $reports = [];

        // --- 2. 各レイヤーのクリーンアップ実行 ---

        // 基本インデックス（kx_0）の整理
        $reports[] = dbkx0::maintenance_orphan_cleanup();

        // 各系統テーブル（kx_1）の同期
        $reports[] = dbkx1::maintenance_cleanup_isolated_records();

        // 階層構造（Hierarchy）の整合性維持
        $reports[] = Hierarchy::maintenance_cleanup();

        $reports[] = dbKxAiMetadataMapper::maintenance_cleanup_isolated_records();

        // SharedTitle（概念統合レイヤー）の不整合チェック
        $reports[] = dbkx_Shared::maintenance_cleanup_shared_titles();

        // ログ出力（KxMessage等が利用可能な場合）
        $final_report = implode("<br>", array_filter($reports));

        return $final_report;
    }

}