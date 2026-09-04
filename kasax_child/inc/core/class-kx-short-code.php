<?php
/**
 * [Path]: inc\core\class-kx-short-code.php
 * [Role]: システム内の全ショートコード（raretu, matrix等）の登録と実行時ハンドリングを行う。
 */
namespace Kx\Core;

use Kx\Core\SystemConfig as Su;
use Kx\Core\DynamicRegistry as Dy;
use Kx\Utils\Toolbox;

use \Kx\Database\dbkx0_PostSearchMapper as dbkx0;
use \Kx\Database\dbkx1_DataManager as dbkx1;

use \Kx\Utils\KxMessage as Msg;

class ShortCode {

    /**
     * dump ShortCODE。
     */
    public static function dump_shortcode( $atts ) {
        extract(shortcode_atts(array(
            'id'			=>	null,	//
            'level' => 0,
            'type' => 'content',
        ), $atts));

        $post_id = $id ?? get_the_ID() ?? '';

        $data = Dy::get($type);


        if( $type=='content'){
            $data_res =  $data[$post_id]?? $data;
        }
        elseif( $type=='work'){
            $data_res = $data;
        }
        elseif( $type=='path_index'){
            $data_res =  Dy::get_path_index($post_id) ?? $data;
        }
        elseif( $type=='TitleParser'){
            $data_res =  \Kx\Core\TitleParser::detect_type($post_id);
        }
        elseif( $type == 'prod_work_production' ){
            $data_res = Dy::get('prod_work_production');
        }
        elseif( $type == 'wpd_characters' ){
            $data_res = \Kx\Core\SystemConfig::get('wpd_characters');
        }
        elseif( $type == 'wpd_works' ){
            $data_res = \Kx\Core\SystemConfig::get('wpd_works');
        }
        elseif( $type == 'check' ){
            global $wpdb;
            $count_wp_posts = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = 'post' AND post_status = 'publish'");

            // 2. 独自テーブル wp_kx_0 のレコード数をカウント
            $count_kx_0 = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}kx_0");

            // 3. 独自テーブル wp_kx_hierarchy のレコード数をカウント
            $count_kx_hierarchy = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}kx_hierarchy");

            // 4. 仮想ノード（is_virtual = 1）の数をカウント
            $count_virtual = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}kx_hierarchy WHERE is_virtual = 1");

            $output = "### System Record Count\n";
            $output .= "* WordPress Posts (publish): " . number_format($count_wp_posts) . " 件\n";
            $output .= "* wp_kx_0 (Real Entity): " . number_format($count_kx_0) . " 件\n";
            $output .= "* wp_kx_hierarchy (Total Path): " . number_format($count_kx_hierarchy) . " 件\n";
            $output .= "* -- Virtual Nodes: " . number_format($count_virtual) . " 件\n";

            return nl2br($output);
        }
        else	{
            $data_res = $data;
        }

        $ret = '';
        $ret .= 'Type:'.$type.'<br>';
        $ret .= 'ID:'.$post_id.'<br>';

        $ret .= Toolbox::dump( $data_res ,$level);

        return $ret;
    }


        /**
         * Ghost系クローンレンダラー
         */
        public static function shortcode_ghost_renderer($atts) {
            // 1. 引数解析
            $args = shortcode_atts(['id' => ''], $atts);
            $target_id = intval($args['id']);
            $current_id = get_the_ID();

            $error_style = 'style="color:red; font-weight:bold;"';
            $error_label = "<span $error_style>【Ghost Error】</span>";

            // 2. 基本バリデーション
            if (!$target_id) {
                Msg::error("Ghost構成エラー: ID未指定 (Caller: $current_id)");
                return "$error_label ID未指定";
            }
            if ($target_id === $current_id) {
                Msg::error("Ghost構成エラー: 自己参照 (ID: $target_id)");
                return "$error_label 自己参照禁止";
            }

            // 3. 投稿の取得と存在確認
            $target_post = get_post($target_id);

            // DBに存在しない場合
            if (!$target_post) {
                Msg::error("Ghost不在: ID $target_id は見つかりません。");
                return "$error_label ☢参照先不在($target_id)☢";
            }

            // ゴミ箱にある場合
            if ($target_post->post_status === 'trash') {
                Msg::warn("Ghost警告: ゴミ箱内の記事を参照しています。(ID: $target_id)");
                return '<div class="__text_center __large __back_0 __color_white">■☣ trash: ' . $target_id . ' ☣■</div>';
            }

            // 4. 特殊処理：転送（多段Ghost）
            if (preg_match('/\[ghost.*id=[\'\"]?(\d+)[\'\"]?.*?\]/', $target_post->post_content, $matches)) {
                $next_id = intval($matches[1]);
                if ($next_id === $target_id) {
                    Msg::error("Ghost循環エラー: ID $target_id が自身を呼び出しています。");
                    return "$error_label 循環参照";
                }
                $modified_diff = \Kx\Utils\Time::get_modified_diff($target_post);
                $log_msg = "⟳ FX_Format: $target_id ⇒ $next_id";
                Toolbox::update_post(1, $current_id, "[ghost id=$next_id]", $modified_diff, $log_msg);
                return "$log_msg";
            }

            // 5. コンテンツのレンダリング
            global $post;
            $original_post = $post; // バックアップ
            $post = $target_post;
            setup_postdata($post);

            $raw_content = $target_post->post_content;


            if (!empty( Dy::get_flags($target_id , 'reference_flag') )) {
                // 【参照ON】moreタグを「仕切り線」に置換して全表示
                $hr = '<table><tr><td><HR class="__hr_more"></td><td width="6em"><span class="__color_gray __xxsmall"> more </span></td><td><HR class="__hr_more"></td></tr></table>';
                $processed_content = str_replace('', $hr, $raw_content);
            } else {
                $processed_content = preg_replace('/<!--more-->.*[\s\S]*?$/', '', $raw_content);
            }

            // apply_filtersの前にグローバル$postがセットされていることが重要
            $output = apply_filters('the_content', $processed_content);

            // 6. ポストデータの完全復元
            wp_reset_postdata();
            $post = $original_post;

            return $output;
        }


    /**
     * タイムライン年齢リスト描画ショートコード
     * [renderTimelineAgeList type="full" chara="101,102"]
     * * Dy::get_path_index の解析済みデータ（time_slug等）を最大限活用し、
     * エピソード時点での各キャラの相対年齢を算出・表示する。
     */
    public static function renderTimelineAgeList($atts) {
        // 1. 引数の正規化
        $atts = shortcode_atts([
            'type'     => 'full',
            'chara'    => '',
            'addition' => '',
        ], $atts);

        // 2. Dy/Su から解析済みデータとマスタを取得
        $post_id          = get_the_ID();
        $path_data        = Dy::get_path_index($post_id); // #2 の entry 配列が返る
        if (!$path_data) return "";

        $character_master = Su::get('wpd_characters');

        // 基本情報の変数化
        $page_title   = $path_data['full'];
        $path_parts   = $path_data['parts'];
        $root_context = $path_parts[0] ?? '';
        $time_slug    = $path_data['time_slug']; // 例: "10-11", "18"

        if (empty($character_master) || !isset($character_master[$root_context])) {
            return "";
        }
        $local_chars = $character_master[$root_context];

        // 3. 基準キャラ(Anchor)の解析
        // ※ここはタイトル内の「c番号」記号に依存するため正規表現を維持
        $highlight_style = '';
        $anchor_id       = null;
        $symbol_matches  = [];

        if (preg_match('/＼c(\d\w+)/', $page_title, $symbol_matches)) {
            $highlight_style = 'color:red;';
        } elseif (preg_match('/∬\d+≫c(\d\w+)/', $page_title, $symbol_matches)) {
            $highlight_style = 'color:aqua;';
        }
        $anchor_id = $symbol_matches[1] ?? null;

        // 4. 基準年(Timeline Base)の算出
        // 基準キャラの年齢差(age_diff)をベースにする
        $anchor_diff_raw = $local_chars[$anchor_id]['age_diff'] ?? 0;
        $anchor_diff     = ($anchor_diff_raw === 'zero') ? 0 : (int)$anchor_diff_raw;

        // Dy::set_path_index が抽出済みの time_slug から数値を抽出
        // 例: "10-11" なら 10、 "18" なら 18
        $elapsed_years = 10; // デフォルト
        if ($time_slug && preg_match('/^(\d+)/', $time_slug, $m)) {
            $elapsed_years = (int)$m[1];
        }
        $timeline_base = $anchor_diff + $elapsed_years;

        // 5. 抽出対象の決定
        $target_ids = $local_chars['set'][$atts['type']] ?? [];
        if (!empty($atts['chara'])) {
            $manual_ids = array_map('trim', explode(',', $atts['chara']));
            $target_ids = array_unique(array_merge($target_ids, $manual_ids));
        }
        if ($anchor_id && !in_array($anchor_id, $target_ids)) {
            $target_ids[] = $anchor_id;
        }

        // 6. 年齢計算と整形
        $render_data = [];
        foreach ($target_ids as $cid) {
            if (!isset($local_chars[$cid])) continue;

            $chara_info = $local_chars[$cid];
            $diff_raw   = $chara_info['age_diff'] ?? 0;
            $diff_val   = ($diff_raw === 'zero') ? 0 : (int)$diff_raw;

            $render_data[] = [
                'relative_age' => $timeline_base - $diff_val,
                'id'           => (string)$cid,
                'display_name' => $chara_info['name'] ?? 'Unknown',
            ];
        }

        // ソート
        usort($render_data, function($a, $b) {
            return $b['relative_age'] <=> $a['relative_age'];
        });

        // 7. 出力生成
        $style_label = 'margin:0 5px; display:inline-block; width:50px; text-align:right;';
        $style_name  = 'margin-left:5px; display:inline-block;';

        $out  = '<hr><div style="margin-left:10px; font-weight:bold;">年齢リスト</div>';
        $out .= "<div style='font-size:0.85em; color:#888; margin:0 0 8px 10px;'>Type: {$atts['type']} / Base: {$timeline_base}</div>";

        $is_anchor_shown = false;
        foreach ($render_data as $row) {
            $is_anchor = ($row['id'] === (string)$anchor_id);
            $row_css   = $is_anchor ? $highlight_style : '';
            if ($is_anchor) $is_anchor_shown = true;

            $out .= "<div style='{$row_css}'>";
            $out .= "<div style='{$style_label}'>{$row['relative_age']}</div>";
            $out .= "<div style='display:inline-block;'>：</div>";
            $out .= "<div style='{$style_name}'>{$row['display_name']}</div>";
            $out .= "</div>";
        }

        // 基準キャラがリスト漏れしていた場合の例外処理
        if (!$is_anchor_shown && $anchor_id) {
            $fb_name = $local_chars[$anchor_id]['name'] ?? 'Unknown';
            $fb_age  = $timeline_base - (int)($local_chars[$anchor_id]['age_diff'] ?? 0);
            $out .= "<div style='margin-left:20px; color:red;'>{$fb_name}（{$fb_age}）</div>";
        }

        $out .= '<hr>';
        return $out;
    }

    /**
     * [kasax_index] ショートコードによるアウトライン（目次）カードのレンダリング処理。
     *
     * @version    0.2026.0903
     * @updated    2026-09-03
     * @context    Dy::get_outline に既存スタックが存在する場合に早期 return してショートコードが空出力になる不具合を修正。未解析時のみ analyze_and_inject を呼び出すようガードを修正し、カード描画処理へ正常遷移させる。
     * @constraint ショートコードの引数シグネチャ（$atts）および OutlineManager::render の呼び出し条件・スタイル定義を破壊せず維持すること。
     *
     * @param array|string $atts ショートコード属性配列（'id' => WordPress標準の投稿ID (wp_posts.ID)）
     * @return string 生成されたアウトラインのHTML文字列（表示データがない、またはID不整合の場合は空文字）
     */
    public static function outline_shortcode($atts) {
        // 1. 引数の処理（$id を確実に取得できるように修正）
        $atts = shortcode_atts( array(
            'id'  => '',
        ), $atts );

        $post_id = !empty($atts['id']) ? (int)$atts['id'] : get_the_ID();

        if (!$post_id) return '';

        // 2. データの取得と解析（未解析の場合のみ解析を実行する）
        $post = get_post($post_id);
        if (!$post) return '';

        $check_dy = Dy::get_outline($post_id);

        // 【修正点】stack が空の場合のみ解析を実行し、すでに存在する場合はそのまま後続のレンダリングへ進む
        if (empty($check_dy['stack'])) {
            $raw_content = $post->post_content;
            \Kx\Core\OutlineManager::analyze_and_inject($raw_content, $post_id, 'sc');
        }

        // 3. スタイルの再現
        $colormgr = Dy::get_color_mgr($post_id);

        // 元の padding: 10px 10px 10px 0.5em; margin: 0 2em; を維持
        $style = ($colormgr['style_array']['outline'] ?? '') .
            "margin:0 2em;".
            "border-right: 1px solid hsla(var(--kx-hue), var(--kx-sat), var(--kx-lum), 0.8); " ;

        // 4. レンダリング
        Dy::trace_count('matrix_count', +1);
        $outline_content = \Kx\Core\OutlineManager::render($post_id, 'card', false);
        Dy::trace_count('matrix_count', -1);

        if (empty($outline_content)) {
            return '';
        }

        return sprintf(
            '<div class="matrix-outline-container" style="%s">%s</div>',
            esc_attr($style),
            $outline_content
        );
    }

    /**
     * google スプレッドシート用
     * id
     * name
     * size
     *
     * @param [type] $atts
     * @return void
     */
    public static function kxsc_google_spreadsheets($atts) {
        extract(shortcode_atts(array(
            'id'				=>	'',	//
            'name'			=>	'',
            'size'			=>	'',
        ), $atts));

        $arr_name	= explode(',',$name);
        $arr_size	= explode(',',$size);

        $data					= "https://spreadsheets.google.com/feeds/list/".$id."/od6/public/values?alt=json";


        $json 				= file_get_contents( $data );

        //echo $json;

        if( !$json ):

            $_error_title = '<span style=color:red;>ERROR　■■　' . get_the_title() . '　■■</span>';

            echo $_error_title;

            return $_error_title;

        endif;

        $json_decode	= json_decode($json);

        $names = $json_decode->feed->entry;

        $url	= 'https://docs.google.com/spreadsheets/d/'.$id.'/edit#gid=0';

        $ret .= '<div style="margin:0 0 0 10px;padding:0 10px 0 10px;border:1px solid #222;">';
        $ret .= '<div style="text-align:right;color:#555;"><a href='.$url.'>google_spreadsheets</a></div>';

        $ret .= '<div>';// style="border-bottom:solid 1px #fff;"

        $i=0;
        foreach ($arr_name as $gsx):
            $ret .= '<span style="display: inline-block;width:'.$arr_size[$i].'px;border-bottom:solid 1px #fff;">';
            $ret .= $gsx;
            $ret .= '</span>';
            $i++;
        endforeach;

        $ret .= '</div>';

        foreach ($names as $name):

            $i=0;
            foreach ($arr_name as $gsx):

                $ret .= '<span style="display: inline-block;width:'.$arr_size[$i].'px;">';
                $ret .= $name->{'gsx$'.$gsx.''}->{'$t'};
                $ret .= '</span>';
                $i++;

            endforeach;

            $ret .= "<br>";

        endforeach;

        $ret .= '</div><p>';

        return $ret;

    }


    /**
     * スプレッドシート関係
     *
     * @param [type] $atts
     * @return void
     */
    public static function kxsc_csv_spreadsheets($atts) {

        extract(shortcode_atts(array(
            'file'			=>	'no_file',	//
            'size'			=>	'100,50,200,50',
            'type'			=>	'',
        ), $atts));


        //サイズ
        $size = NULL;
        if( $type == 'works' )
        {
            $size = '40,10,500';
        }


        $_width_all = NULL;
        if( $size )
        {
            $_size_ARR = explode( ',' , $size);

            foreach( $_size_ARR as $value ):

                if( !empty( $_width_all ) )
                {
                    $_width_all = $_width_all + $value;
                }
                else
                {
                    $_width_all = $value;
                }

            endforeach;

            //微調整。
            $_width_all = $_width_all + 20;
        }

        $ret = NULL;

        //$file = 'D:\00_WP\CSV\\'.$file.'.csv';
        // SuクラスからCSVルートディレクトリを取得し、ファイル名と拡張子を結合
        $file = Su::get_path('dir_csv_root') . DIRECTORY_SEPARATOR . $file . '.csv';


        if( file_exists( $file ) )
        {
            $handle = fopen( $file, "r" );
        }
        else
        {
            //$handle = fopen( 'D:\00_WP\CSV\\no_file.csv' , "r" );
            // パスを取得してから結合し、オープンする
            $no_file_path = Su::get_path('dir_csv_root') . DIRECTORY_SEPARATOR . 'no_file.csv';
            $handle = fopen( $no_file_path, "r" );
            Msg::error("fileネームのミス");
            $ret = "fileネームのミス";
        }


        $ret .= '<table style="width:'. $_width_all .'px;">';
        $ret .= "\n";

        $_iy = 0;
        while ( ( $data = fgetcsv ( $handle, 1000, ",", '"' ) ) !== FALSE ) {

            $ret .= "\t<tr>\n";

                if( $_iy == 0 )
                {
                    $_style  =  ' style="background:hsl(0, 100%, 10%);';

                    if( !empty( $_ix ) )
                    {
                        $_style .=  ' width:'. $_size_ARR[ $_ix ] .'px;';
                    }

                    $_style .=  '"';
                }

                $_ix = 0;
                for ( $i = 0; $i < count( $data ); $i++ ) {

                    if( $_ix == 0 && $_iy != 0)
                    {
                        $_style  =  ' style="background:hsl(180, 100%, 10%);';

                        if( !empty( $_size_ARR[ $_ix ] ) ):

                            $_style .=  ' width:'. $_size_ARR[ $_ix ] .'px;';

                        endif;

                        $_style .=  '"';
                    }
                    elseif( $_iy != 0)
                    {
                        $_style  =  ' style="';

                        if( !empty( $_size_ARR[ $_ix ] ) )
                        {
                            $_style .=  ' width:'. $_size_ARR[ $_ix ] .'px;';
                        }

                        $_style .=  '"';
                    }

                    $ret .= "\t\t<td".$_style.">{$data[$i]}</td>\n";	//".$_size_ARR[ $_ix ]."

                    $_ix++;

                } //endfor

                $ret .= "\t</tr>\n";



                $_iy++;
        }

        $ret .= "</table>\n";

        fclose( $handle );

        return $ret;
    }



    /**
     * 2023-08-04
     *
     * @param [type] $atts
     * @return void
     */
    public static function kxsc_Info_php($atts) {
        return phpinfo();
    }



    /**
     * データベース再構築・システムメンテナンスパネル
     * ショートコード [full_scale_maintenance] により呼び出し
     */
    public static function render_database_maintenance_panel() {
        global $wpdb;

        $target_action = isset($_GET['kx_mode']) ? sanitize_text_field($_GET['kx_mode']) : '';
        $is_execution_requested = isset($_GET['run']) && $_GET['run'] === '1';

        $maintenance_actions = [
            'sync_kx0_basic'     => 'kx_0: 基本インデックス・時刻同期',
            'rebuild_kx0_type'   => 'kx_0: 属性（Type等）情報の再解析',
            'refresh_kx1_meta'   => 'kx_1: メタデータの制御層更新（差分）',
            'refresh_kx1_meta_full'   => 'kx_1: 強制全件メンテナンス。',
            'remap_hierarchy'    => 'kx_hierarchy: 階層構造（≫）の再マッピング',
            'sync_shared_title'  => 'kx_shared_title: 共有タイトル概念の同期',
        ];

        $html = '<div id="kx-maintenance-root" style="background:#f9f9f9; padding:25px; border:1px solid #ccc; border-radius:10px; color:#333; font-family:sans-serif;">';
        $html .= '<h3 style="margin-top:0; border-bottom:3px solid #0073aa; padding-bottom:12px;">🛠️ System Data Integrity Manager</h3>';

        // --- 実行処理完了後の表示セクション ---
        if ($is_execution_requested && array_key_exists($target_action, $maintenance_actions)) {
            set_time_limit(600);
            $status_label = $maintenance_actions[$target_action];

            try {
                $processed_count = 0;
                switch ($target_action) {
                    case 'sync_kx0_basic':    dbkx0::maintenance_full_sync(); break;
                    case 'rebuild_kx0_type':  dbkx0::maintenance_type_rebuild(); break;
                    case 'refresh_kx1_meta':  dbkx1::maintenance_run_all(); break;
                    case 'refresh_kx1_meta_full':  dbkx1::maintenance_run_all(true); break;
                    case 'sync_shared_title': \Kx\Database\dbkx_SharedTitleManager::maintenance_sync_all(); break;
                    case 'remap_hierarchy':
                        $table_kx0 = $wpdb->prefix . 'kx_0';
                        $entries = $wpdb->get_results("SELECT id, title FROM $table_kx0");
                        foreach ($entries as $entry) {
                            \Kx\Database\Hierarchy::sync(['id' => $entry->id, 'title' => $entry->title]);
                            $processed_count++;
                        }
                        break;
                }

                $html .= '<div style="color:green; font-weight:bold; background:#e7f7ed; padding:15px; border-radius:5px; border:1px solid #27ae60;">';
                $html .= '✅ 完了致しました: ' . $status_label . ($processed_count > 0 ? " ({$processed_count}件)" : "");
                $html .= '</div>';

            } catch (\Exception $e) {
                $html .= '<p style="color:red; background:#fff1f1; padding:10px; border:1px solid red;">⚠️ 実行エラー: ' . esc_html($e->getMessage()) . '</p>';
            }

            $html .= '<p style="margin-top:20px;"><a href="'.remove_query_arg(['run', 'kx_mode']).'" style="text-decoration:none; color:#0073aa; font-weight:bold;">← メンテナンスメニューに戻る</a></p>';
            $html .= '</div>';
            return $html;
        }

        // --- メニュー表示セクション ---
        // 処理中に表示を差し替えるためのコンテナ
        $html .= '<div id="kx-mnt-ui-wrapper">';
        $html .= '<p style="font-size:14px; color:#555; margin-bottom:20px;">実行したいメンテナンス項目を選択してください。</p>';
        $html .= '<div style="display:grid; grid-template-columns: 1fr; gap:12px;">';

        foreach ($maintenance_actions as $action_key => $label) {
            $url = add_query_arg(['kx_mode' => $action_key, 'run' => '1']);
            $html .= sprintf(
                '<button type="button" onclick="kx_execute_maintenance(\'%s\', \'%s\')"
                style="text-align:left; background:#fff; color:#0073aa; border:1px solid #0073aa; padding:14px 18px; border-radius:6px; cursor:pointer; font-size:14px; font-weight:500; transition:all 0.2s;">
                <span style="display:inline-block; margin-right:10px;">▶</span> %s
                </button>',
                esc_js($url),
                esc_js($label),
                $label
            );
        }
        $html .= '</div></div>';

        // UI書き換えJS
        $html .= '<script>
        function kx_execute_maintenance(url, label) {
            if (confirm("【確認】\n" + label + " を開始しますか？")) {
                // UIを即座に「処理中表示」に書き換える
                const wrapper = document.getElementById("kx-mnt-ui-wrapper");
                wrapper.innerHTML = `
                    <div style="color:#d63638; font-weight:bold; padding:20px; background:#fff; border:2px solid #d63638; border-radius:8px; text-align:center;">
                        <div style="font-size:24px; margin-bottom:10px;">🔄</div>
                        処理実行中: ${label}<br>
                        <span style="font-size:13px; color:#666; font-weight:normal;">大量のデータを処理しています。完了までブラウザを閉じないでください。</span>
                    </div>
                `;

                // 全体のカーソルを待機状態に
                document.body.style.cursor = "wait";

                // ページ遷移（実行開始）
                location.href = url;
            }
        }
        </script>';

        $html .= '</div>';
        return $html;
    }

    /**
     * 指定フォルダ内の全ファイルを統合し、VS Codeリンク付きで出力する
     *
     * 統合エンジン（consolidator）から呼び出された場合は、目次やHTMLタグを排除した
     * テキストデータのみを結合して返します。その際、merge_styleが'separated'に
     * 設定されていれば、ファイル境界にマークダウンの##（ファイル名）見出しを自動挿入します。
     *
     * @param array|string $atts {
     *     ショートコードの属性配列。
     *
     *     @type string $folder      対象フォルダ名。デフォルトは 'test'。
     *     @type string $path        ベースディレクトリ解決用のパスキー。デフォルトは 'dir_E_web_novel'。
     *     @type string $ext         検索対象ファイルの拡張子。デフォルトは 'md'。
     *     @type string $code        ファイルの文字コード。デフォルトは 'auto'。
     *     @type string $type        呼び出しタイプ。'consolidator' 指定時はRAWデータ結合のみを行う。
     *     @type string $merge_style 結合スタイル。'separated' で境界に見出しを挿入。デフォルトは 'simple'（そのまま結合）。
     * }
     * @return string レンダリング用HTML、または結合されたプレーンテキスト。
     */
    public static function get_text_files_in_folder($atts) {
        $options = shortcode_atts([
            'folder'      => 'test',
            'path'        => 'dir_E_web_novel',
            'ext'         => 'md',
            'code'        => 'auto', // 文字コード指定
            'type'        => '',
            'merge_style' => 'simple' // 統合時のマージスタイルを追加 (simple | separated)
        ], $atts);

        $base_dir = Su::get_path($options['path']);
        $target_dir = rtrim($base_dir, '/') . '/' . trim($options['folder'], '/') . '/';

        if (!is_dir($target_dir)) {
            return "<p style='color:red;'>Directory not found: {$target_dir}</p>";
        }

        // 1. ファイル一覧の取得と自然順ソート
        $pattern = $target_dir . "*." . $options['ext'];
        $files = glob($pattern);
        if (empty($files)) {
            return "<p>No .{$options['ext']} files found in: {$options['folder']}</p>";
        }
        natsort($files);


        // 1. フォルダ全体のパスをVSCリンク用に変換
        $vsc_folder_link = "vscode://file/" . ltrim(str_replace('\\', '/', $base_dir), '/');

        // 2. 冒頭のクイックリンクリスト（目次）の作成
        $navigation = "<div class='kx-folder-index' style='background:#151515; color:#d4d4d4; padding:20px; border:1px solid #222; border-radius:8px; margin-bottom:40px; box-shadow: 0 4px 10px rgba(0,0,0,0.3);'>";

        // ヘッダーに「Open Folder」ボタンを配置
        $navigation .= "<h2>📂" . esc_html($options['folder']) . "</h2>";
        $navigation .= "<span style='margin-top:0; border-bottom:1px solid #444; padding-bottom:10px; color:#4daafc; display:flex; justify-content:space-between; align-items:center;'>";
        $navigation .= "<span> </span>";
        $navigation .= "<a href='{$vsc_folder_link}' title='Open Folder in VS Code' style='font-size:0.8em; background:#3e3e3e; color:#ccc; padding:4px 10px; border-radius:4px; text-decoration:none; border:1px solid #555;'>Open Folder</a>";
        $navigation .= "</span>";

        $navigation .= "<ul style='list-style:none; padding:0; margin:0;'>";

        $combined_content = "";

        foreach ($files as $file_path) {
            $file_name = basename($file_path);
            $vsc_link = "vscode://file/" . ltrim(str_replace('\\', '/', $file_path), '/');
            $anchor_id = "section-" . sanitize_title($file_name);

            // リスト（ダークテーマ配色）
            // リストのリンクをアンカー(#)からVSCリンクへ変更
            $navigation .= "<li style='margin-bottom:8px; display:flex; align-items:center;'>";
            $navigation .= "📄 <a href='{$vsc_link}' title='Edit in VS Code' style='color:#4daafc; text-decoration:none; margin-left:8px; border-bottom:1px solid transparent;' onmouseover=\"this.style.borderBottom='1px solid #4daafc'\" onmouseout=\"this.style.borderBottom='1px solid transparent'\">" . esc_html($file_name) . "</a>";
            $navigation .= " <a href='#{$anchor_id}' title='Jump to Preview below' style='margin-left:auto; text-decoration:none; font-size:0.8em; color:#666;'>[Preview ↓]</a>";
            $navigation .= "</li>";


            if ($options['type'] === 'consolidator') {
                $raw = file_get_contents($file_path);

                // 文字コード変換（既存の code オプションを尊重）
                if ($options['code'] !== 'auto' && $options['code'] !== 'utf-8') {
                    $raw = mb_convert_encoding($raw, 'UTF-8', $options['code']);
                }

                // 修正：merge_styleが'separated'の場合、ファイル境界に見出しヘッダーを挿入
                if ($options['merge_style'] === 'separated') {
                    $combined_content .= "\n\n## " . $file_name . "\n" . $raw . "\n";
                } else {
                    $combined_content .= $raw . "\n";
                }
                continue;
            }


            // 2. 本文統合（セクション区切りをダークに）
            $combined_content .= "<section id='{$anchor_id}' style='margin-top:60px; border-top:1px solid #444; padding-top:30px;'>";
            $combined_content .= "<h2>". esc_html($file_name) ."</h2><span style='display:flex; justify-content:space-between; align-items:center; color:#4daafc; font-size:1.1em; border-left:4px solid #4daafc; padding-left:15px;'>";
            $combined_content .= "<span>" . esc_html($file_name) . "</span>";
            $combined_content .= "<a href='{$vsc_link}' style='font-size:0.8em; margin-left:15px; font-weight:normal; background:#264f78; color:#fff; padding:0px 12px; border-radius:4px; text-decoration:none;'>VSc Edit</a>";
            $combined_content .= "</span>";

            // 共通関数呼び出し（文字コード指定を渡す）
            $combined_content .= self::render_file_content_core($file_path, $options['code'], false);
            $combined_content .= "</section>";
        }

        $navigation .= "</ul></div>";


        $sc_count = Dy::get('trace')['kxx_sc_count'] ?? null;
        if (!empty($sc_count)) {
            return $navigation;
        }

        if ($options['type'] === 'consolidator') {
            $total_html = $combined_content;
        } else {
            $total_html = $navigation . $combined_content;
        }


        return OutlineManager::analyze_and_inject($total_html, get_the_ID(), 'sc');
    }

    /**
     * 指定されたテキストファイルを読み込み、Markdown変換して返すショートコード
     * (共通関数 render_file_content_core を使用)
     *
     * @param array $atts ショートコード属性
     * @return string 変換後のHTML
     */
    public static function get_text_file($atts)
    {
        // 1. 属性の初期値設定
        $options = shortcode_atts([
            'file' => 'S0000-Ksy_0000',
            'path' => 'dir_E_seisaku',
            'code'   => 'auto' // 文字コード指定を追加
        ], $atts);

        // 2. フルパスの構築
        $base_dir = Su::get_path($options['path']);
        $file_path = "{$base_dir}{$options['file']}.txt";

        // 3. ショートコードのネスト/再帰判定 (既存ロジック)
        $sc_count = Dy::get('trace')['kxx_sc_count'] ?? null;
        if (!empty($sc_count)) {
            return '━━ SC ━━';
        }

        // 4. 共通関数を呼び出してメインコンテンツを生成
        // ショートコード単体呼び出しの場合は、デバッグ情報(VScリンク)を含める
        $content_body = self::render_file_content_core($file_path,$options['code'], true);

        // 5. 目次/アウトラインの注入 (最終的なHTMLに対して実行)
        $content = OutlineManager::analyze_and_inject($content_body, get_the_ID(), 'sc');

        return $content;
    }

    /**
     * @param string $file_path
     * @param string $encoding 'auto', 'UTF-8', 'SJIS-win' など
     */
    private static function render_file_content_core($file_path, $encoding = 'auto', $with_vsc_link = true) {
        if (!file_exists($file_path)) {
            return "<p style='color:#ff6b6b;'>ファイルが見つかりません: {$file_path}</p>";
        }


        $header_info = "";
        if ($with_vsc_link) {
            $vscode_link = "vscode://file/" . str_replace('\\', '/', $file_path);
            $header_info = "<div class='vsc-link-area' style='font-size: 0.8em; margin-bottom: 10px; color: #888;'>";
            $header_info .= "File: <a href='{$vscode_link}' style='color: #4daafc; text-decoration: none;'>{$file_path}</a>";
            $header_info .= "</div>";
        }

        $raw_content = file_get_contents($file_path);

        // 文字コードの判定と変換
        if ($encoding === 'auto') {
            // 文字コードを推測（SJIS-win, UTF-8, EUC-JPの順にテスト）
            $detected = mb_detect_encoding($raw_content, "UTF-8, SJIS-win, EUC-JP", true);
            $utf8_content = mb_convert_encoding($raw_content, 'UTF-8', $detected ?: 'SJIS-win');
        } else {
            $utf8_content = mb_convert_encoding($raw_content, 'UTF-8', $encoding);
        }

        $utf8_content = trim($utf8_content);

        // ドット階層変換ロジック (既存)
        $markdown = preg_replace_callback('/^(\.+)\s*/m', function ($matches) {
            $dot_count = strlen($matches[1]);
            $heading_level = min($dot_count + 1, 6);
            return str_repeat('#', $heading_level) . ' ';
        }, $utf8_content);

        $parsedown = new \KxParsedown();
        $parsedown->setBreaksEnabled(true);
        $html = $parsedown->text($markdown);

        return "{$header_info}{$html}";
    }
}