<?php
/**
 * [Path]: inc\core\class-kx-save-manager.php
 * [Role]: 投稿保存時のバリデーション、Dirty Check、および独自テーブル(kx_0, kx_1)との同期を管理する。
 */

namespace Kx\Core;

use Kx\Core\SystemConfig as Su;
use \Kx\Utils\Time;

class SaveManager {

    /**
     * 特定のPOSTリクエストに基づき、新しいWordPress投稿を自動作成する。
     * * 1. $_POST['create_virtual_post'] が存在し、タイトルが送信されているか確認。
     * 2. ユーザーが投稿編集権限（edit_posts）を持っているか検証。
     * 3. 同一タイトルの既存投稿がないか検索。
     * 4. 存在しない場合、ショートコード [raretu] を含む投稿を新規作成（公開状態）。
     * 5. 成功時、\Kx\Database\Hierarchy クラスを用いて外部テーブル等と同期。
     * 6. 二重送信防止のため、リフレッシュパラメータを付与してリダイレクト。
     *
     */
    public static function handleVirtualPostRequest(){
        if (isset($_POST['create_virtual_post']) && !empty($_POST['target_title'])) {
            if (!current_user_can('edit_posts')) return;

            $title = sanitize_text_field($_POST['target_title']);
            $existing_posts = get_posts([
                            'title'                  => $title,
                            'post_type'              => 'post',
                            'post_status'            => 'publish', // または 'any'
                            'posts_per_page'         => 1,
                            'update_post_term_cache' => false,
                            'update_post_meta_cache' => false,
                            'orderby'                => 'ID',
                            'order'                  => 'ASC',
                    ]);
                    $existing = !empty($existing_posts) ? $existing_posts[0] : null;

            if (!$existing) {
                $post_id = wp_insert_post([
                    'post_title'   => $title,
                    'post_content' => '[raretu]',
                    'post_status'  => 'publish',
                    'post_type'    => 'post',
                ]);

                if (!is_wp_error($post_id)) {
                    // 重要：ここで階層テーブルを同期
                    if (class_exists('\Kx\Database\Hierarchy')) {
                        \Kx\Database\Hierarchy::sync($post_id);
                    }

                    // 成功パラメータを付けてリダイレクト（二重送信防止）
                    $redirect_url = add_query_arg('kx_created', '1', wp_get_referer() ?: home_url());
                    wp_safe_redirect($redirect_url);
                    exit;
                }
            }
        }
    }

    /**
     * 保存介入
     *
     */
    public static function save_hook_8( $post_id ) {
        // 基本的なガード
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( wp_is_post_revision( $post_id ) ) return;

		if (is_object($post_id) && isset($post_id->ID)) {
			$post_id = (int)$post_id->ID;
		}

		// $post_id が空、または数値（あるいは数値形式の文字列）でなければ終了
		if (!$post_id || !is_scalar($post_id)) return null;

        // 第二引数にモードを渡して、保存処理（Dirty Check & DB Write）を走らせる
        ContextManager::sync( $post_id, 'save' );

        \kx\Database\dbKxAiMetadataMapper::reset_vector_status($post_id);
    }



    /**
     * 保存介入
     *
     * @param [type] $post_id
     * @return void
     */
    public static function save_hook_9( $post_id ){

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( wp_is_post_revision( $post_id ) ) return;

        delete_transient( 'kx_cache_the_content_pack_' . $post_id );

        $new_post = get_post( $post_id );

        self::save_post_Category(Su::get('title_preg')['array_add_category'] , $new_post->post_title , $post_id );
        self::save_post_Tag(Su::get('title_preg')['array_add_tag'], $new_post->post_title , $post_id );

        if( get_post_status( $post_id ) == 'publish'  ) //||!empty( get_the_title( $post_id ) )
        {
            //jsonバックアップ。
            //$file_json = 'D:\00_WP\CSV_backup\post_backup.json';
            // Suクラス（SystemConfig）からベースディレクトリを取得し、ファイル名を結合する
            $dir = Su::get_path('dir_backup_csv');

            // ディレクトリが存在しない場合に自動作成（1.4万件の整合性保護の一環）
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $file_json = $dir . DIRECTORY_SEPARATOR . 'post_backup.json';

            if ( ! file_exists( $file_json ) ) {
                $arr_json = [];
            } else {
                $json1	= file_get_contents( $file_json );
                $json2	= mb_convert_encoding(  $json1 , 'UTF8', 'ASCII,JIS,UTF-8,EUC-JP,SJIS-WIN'  );
                $arr_json = json_decode(  $json2 , true  );
            }

            $arr_json[ $post_id ] = [
                'post_title' 	 => $new_post->post_title ,
                'post_content' => $new_post->post_content,
                'time' => time(),
                //'date' => kx_time( '' , "Y-m-d H:i:s" ),
                'date' => Time::format( '', "Y-m-d H:i:s" ),
            ];

            //file_put_contents( $file_json , json_encode ( $arr_json , JSON_UNESCAPED_UNICODE ) );
            file_put_contents( $file_json , json_encode ( $arr_json , JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );


            //csvバックアップ。
            //$file = 'D:\00_WP\CSV_backup\\'. $post_id .'.csv';
            // ディレクトリパスを取得し、post_idと拡張子を結合する
            $file = Su::get_path('dir_backup_csv') . DIRECTORY_SEPARATOR . $post_id . '.csv';
            $fp 	= fopen( $file , 'w');
            fputcsv( $fp, [ $new_post->post_title , $new_post->post_content ]);
            fclose( $fp );
        }
    }
    /**
     * Category自動追記
     *
     * @param array $arr
     * @param string $title
     * @return void
     */
    private static function save_post_Category( $arr , $title , $post_id ){

        //　カテゴリー・新規追記
        foreach( $arr as $value ):

            $pattern	= $value[0];
            if( preg_match ( $pattern ,$title, $matches) )
            {
                $term = term_exists(	$matches[0]	, 'category');
                if( $term == 0 && $term == null)
                {
            require_once ( 'D:/00_WP/xampp/htdocs/0/wp-admin/includes/taxonomy.php' );

                    $args = array(
                        'cat_name' 							=> $matches[0],
                        'category_description'	=> 'AUTO',
                        'category_nicename' 		=> $matches[0],
                        'category_parent' 			=> $catnum,
                    );

                    wp_insert_category( $args );
                }
            }

        endforeach;
        unset( $value );


        /* 全てのカテゴリーをチェック */
        foreach( get_terms( "category", "fields=all&get=all" ) as $value ):

            /* カテゴリーと同じキーワードが含まれていたら、既存のカテゴリから新規のカテゴリに変更 */
            if(	preg_match(	'#^'.$value->name.'#i' ,	$title ))
            {
                wp_remove_object_terms( $post_id,1, 'category' );
            wp_add_object_terms( $post_id, $value->name, 'category' );
            }
            elseif(	preg_match(	'#〈'.$value->name.'〉#i' ,$title ))
            {
                wp_remove_object_terms( $post_id,1, 'category' );
            wp_add_object_terms( $post_id, $value->name, 'category' );
            }
            else
            {
                wp_remove_object_terms( $post_id, $value->name, 'category' );
            }

        endforeach;
        unset( $value );

        /* カテゴリーがない場合は、デフォルトに設定 */
        $catcheck = get_the_category( $post_id );
        if( is_array( $catcheck ) && !empty( $catcheck[0] ) && is_null( $catcheck[0] ) )
        {
            //旧型のCheck。2023/08/29
        wp_add_object_terms( $post_id , 1, 'category' );
        }
        elseif(is_array( $catcheck ) && empty( $catcheck[0] ) )
        {
            //2023年以降のCheck方法。2023-08-29
            wp_add_object_terms( $post_id , 1, 'category' );
        }
    }
    /**
     * 投稿タイトルに基づいてタグを自動的に追加・削除する関数。
     *
     * @param array $arr タグを追加するための正規表現パターンを含む配列。
     * @param string $title 投稿のタイトル。
     * @param int $post_id 投稿のID。
     *
     * 動作概要:
     * 1. `$arr` に指定された正規表現に基づいてタイトルを解析し、該当するタグを投稿に追加します。
     * 2. 既存のすべてのタグを取得し、一度投稿からすべて削除します。
     * 3. タイトルと一致する既存のタグを再度投稿に追加します。
     *
     * この関数は、タグ管理を自動化することで投稿の内容に合ったタグ付けを行い、検索性や分類を向上させる目的で使用されます。
     * 特記事項:
     * - `$arr` に指定された正規表現がなくても、既存のタグ名がタイトルに含まれている場合、そのタグは自動的に再追加されます。
     * - タグを完全にリセットして再構築するため、大量のタグが存在する場合にはパフォーマンスに注意が必要です。
     */
    private static function save_post_Tag( $arr , $title ,$post_id ){

        //　tag追記・配列指定型
        foreach( $arr as $value ):

            if( preg_match( $value[0] ,$title, $matches) )
            {
                preg_match( $value[1] ,$matches[0], $matches);
                wp_add_object_terms( $post_id, $matches[0], 'post_tag' );
            }

        endforeach;
        unset( $value );


        //全tagでforeachを回す。
        foreach( get_terms( "post_tag", "fields=all&get=all" ) as $value ):

            //id指定したpostのタグを順次消す。全部消える。2023-02-26。
            wp_remove_object_terms( $post_id , $value->name, 'post_tag' );

            if(	preg_match(	'#'.$value->name.'#i' ,$title ))
            {
                //一致条件だけ追記。
                wp_add_object_terms( $post_id, $value->name, 'post_tag' );
            }

        endforeach;
        unset( $value );
    }


    /**
     * 保存介入
     * タイトル記号正規化 ＋ 重複回避（〈2〉形式） ＋ 本文置換
     * * @param array $data 保存される投稿データ
     * @param array $postarr 編集画面等から渡される生の投稿データ（ID判定に必要）
     */
    public static function insert_post_data_content ( $data, $postarr ) { // 引数を追加

        foreach ( Su::get('add_save_conent') as $key => $v )
        {
            //「date」日付の場合。2023-02-26。
            if( $v[0] == 'date' )
            {
                $replace = '' . Time::format( 'tokyo', $v[1] ) . '';
            }
            else
            {
                if( !empty( $v[1] ) )
                {
                    $replace = $v[1];
                }
                else
                {
                    $replace = NULL;
                }
            }

            $data[ 'post_content' ] = preg_replace( $key , $replace, $data[ 'post_content' ] );

        }

        // --- 2. タイトルの記号正規化（既存処理） ---
        // 先に記号を統一してから重複判定にかける
        $data[ 'post_title' ] = str_replace(
            array_keys(Su::get('add_save_title')),
            array_values(Su::get('add_save_title')),
            $data['post_title']
        );



        // 追記：同名タイトル保存禁止・自動枝番付与（〈2〉形式）
        // ゴミ箱、自動保存、リビジョンの場合はスキップ
        if ( !in_array($data['post_status'], ['trash', 'inherit']) && $data['post_type'] !== 'revision' ) {
            global $wpdb;

            $original_title = $data['post_title'];
            $post_id        = isset($postarr['ID']) ? $postarr['ID'] : 0;
            $post_type      = $data['post_type'];

            // 重複チェッククエリ：タイトルそのまま、または末尾に〈数字〉がついているものを検索
            // 貴殿の仕様に合わせて「〈」と「〉」を使用
            //$title_pattern = '^' . preg_quote($original_title) . '($|【[0-9]+】$)';
            $title_pattern = '^' . preg_quote($original_title) . '($|〈[0-9]+〉$)';

            $query = "SELECT post_title FROM $wpdb->posts WHERE post_title REGEXP %s AND ID != %d AND post_type = %s AND post_status NOT IN ('trash', 'inherit') LIMIT 1";
            $duplicate_exists = $wpdb->get_var($wpdb->prepare($query, $title_pattern, $post_id, $post_type));

            if ($duplicate_exists) {
                $suffix = 2;
                while (true) {
                    // ここで枝番を「〈2〉」形式で生成
                    $new_title = $original_title . "〈{$suffix}〉";

                    $check_query = "SELECT ID FROM $wpdb->posts WHERE post_title = %s AND ID != %d AND post_type = %s LIMIT 1";
                    if (!$wpdb->get_var($wpdb->prepare($check_query, $new_title, $post_id, $post_type))) {
                        $data['post_title'] = $new_title;
                        $data['post_name']  = ''; // スラッグ再生成（重複回避）
                        break;
                    }
                    $suffix++;
                }
            }
        }
        return $data;
    }


    /**
     * 削除フック。
     * データベース削除。
     * publish_to_trashは、投稿がゴミ箱に移動する際に発生するアクションフック。2023-02-26ChatGPT。
     *
     */
    public static function trash_post_include( $post_ID ) {
        //echo 'TEST-OK-id:'. $post_ID->ID;
        //time_sleep_until (100);

        //$file_json = 'D:\00_WP\CSV_backup\post_delete.json';
        // 1. JSONファイルのパス（削除済みインデックス）
        $file_json = Su::get_path('dir_backup_csv') . DIRECTORY_SEPARATOR . 'post_delete.json';

        $json1	= file_get_contents( $file_json );
        $json2	= mb_convert_encoding(  $json1 , 'UTF8', 'ASCII,JIS,UTF-8,EUC-JP,SJIS-WIN'  );

        $arr_json = json_decode(  $json2 , true  );

        $arr_json[ $post_ID->ID ] = [
            'post_title' 	 => get_the_title( $post_ID->ID ) ,
            'time' => time(),
            //'date' => kx_time( '' , "Y-m-d H:i:s" ),
            'date' => Time::format( '', "Y-m-d H:i:s" ),
        ];

        file_put_contents( $file_json , json_encode ( $arr_json , JSON_UNESCAPED_UNICODE ) );


        //csvバックアップ。
        //$file = 'D:\00_WP\CSV_backup\\Delete'. $post_ID->ID .'.csv';
        // 2. 個別CSVファイルのパス（削除済み投稿データ）
        $file = Su::get_path('dir_backup_csv') . DIRECTORY_SEPARATOR . 'Delete' . $post_ID->ID . '.csv';

        $fp 	= fopen( $file , 'w');
        fputcsv( $fp, [ get_the_title( $post_ID->ID ) ]);
        fclose( $fp );

    }

}