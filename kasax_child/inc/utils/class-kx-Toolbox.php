<?php

/**
 * inc\utils\class-kx-Toolbox.php
 *
 */

namespace Kx\Utils;

use Kx\Core\SystemConfig as Su;
use Kx\Core\DynamicRegistry as Dy;
use Kx\Core\KxDirector as kx;
use \Kx\Utils\KxMessage as Msg;

//use Kx\Core\ContextManager;
//use Kx\Core\OutlineManager;
//use \Kx\Database\dbkx0_PostSearchMapper as dbkx0;
//use \Kx\Database\dbkx1_DataManager as dbkx1;

class Toolbox {

    /**
     * dump
     *
     */
    public static function dump($data, $level = 0) {
        $indent = str_repeat("    ", $level);
        $output = "";

        // 最初に <pre> タグを追加
        if ($level === 0) {
            $output .= "<pre style='background: #272822; color: #f8f8f2; padding: 15px; border-radius: 5px; line-height: 1.5; font-family: Monaco, Consolas, monospace; text-align: left;'>";
        }

        if (is_array($data)) {
            if (empty($data)) {
                $output .= "[],\n";
            } else {
                $output .= "[\n";
                $child_indent = str_repeat("    ", $level + 1);

                $keyLengths = array_map(function($k) {
                    return strlen(is_string($k) ? "'$k'" : $k);
                }, array_keys($data));
                $maxKeyLen = max($keyLengths);

                foreach ($data as $key => $value) {
                    $output .= $child_indent;
                    $formattedKey = is_string($key) ? "'$key'" : $key;
                    $output .= str_pad($formattedKey, $maxKeyLen) . " => ";

                    if (is_array($value)) {
                        // 【修正点】戻り値を $output に結合する
                        $output .= self::dump($value, $level + 1);
                    } elseif (is_object($value)) {
                        // オブジェクトの場合：クラス名を表示しつつ、中身を配列として再帰処理
                        $className = get_class($value);
                        $output .= "<span style='color: #66d9ef;'>Object($className)</span> " . self::dump((array)$value, $level + 1);
                    } else {
                        if (is_string($value)) {
                            $output .= "<span style='color: #e6db74;'>'" . htmlspecialchars($value) . "'</span>";
                        } elseif (is_bool($value)) {
                            $output .= "<span style='color: #ae81ff;'>" . ($value ? 'true' : 'false') . "</span>";
                        } elseif (is_null($value)) {
                            $output .= "<span style='color: #ae81ff;'>null</span>";
                        } else {
                            $output .= "<span style='color: #ae81ff;'>{$value}</span>";
                        }
                        $output .= ",\n";
                    }
                }
                $output .= $indent . "]" . ($level === 0 ? "" : ",\n");
            }
        } else {
            $output .= var_export($data, true) . ",\n";
        }

        // 最後に </pre> タグを閉じる
        if ($level === 0) {
            $output .= "</pre>";
        }

        // 常に return する
        return $output;
    }



    /**
     * 統合概要フラグ（integrated）が立っているか確認
     */
    public static function is_integrated($post_id): bool {
        if (!$post_id) return false;

        // Dy からキャッシュを取得
        $flags = Dy::get_content_cache($post_id, 'flags');

        // Utils の汎用メソッドで判定
        return self::has_flag((string)$flags, 'integrated');
    }


    /**
     * コンマ区切りのフラグ文字列内に特定のフラグが存在するか判定
     */
    public static function has_flag(?string $flags_str, string $target): bool {
        if (empty($flags_str)) return false;

        $flags_array = array_map('trim', explode(',', $flags_str));
        return in_array($target, $flags_array, true);
    }






    /**
     * 判定ロジック（拡張時はここだけいじれば良い）
     *
     * @param int   $post_id
     * @param array $_KxDy   DynamicRegistry::get('content')[$post_id] の中身
     * @return bool キャッシュすべきなら true, すべきでないなら false
     */
    public static function the_content_cache_post( $post_id ) {

        $_KxDy = Dy::get('content')[$post_id];
        //if( $_KxDy){return false;}

        // 1. raretu（子孫要素）判定
        // 新構造では ana.node.descendants に集約されている
        $descendants = $_KxDy['ana']['node']['descendants'] ?? null;
        if ( !empty($descendants) && is_array($descendants) ) {
            return false;
        }

        //echo kx_dump(json_decode($_KxDy['raw']['db_kx1']['json'], true));

        if( isset($_KxDy['raw']['db_kx1']['json']) ){
            $json_raw = $_KxDy['raw']['db_kx1']['json'];
            $json_data = is_array($json_raw) ? $json_raw : json_decode($json_raw, true);

            // 2. ShortCODE判定 (rawレイヤーの db_kx1 を参照)
            if ( isset($json_data['ShortCODE']) && $json_data['ShortCODE'] === 'raretu' ) {
                return false;
            }

            // 3. GhostON判定 (rawレイヤーの db_kx1 を参照)
            if ( !empty($json_data['GhostON']) ) {
                return false;
            }

            // 4. tougou (統合) 判定: consolidated_from のチェック
            if ( isset($json_data['consolidated_from']) && !empty($json_data['consolidated_from']) ) {
                return false;
            }
        }

        $is_integrated = Kx::is_integrated($post_id) ;
        if( $is_integrated) return false;

        return true;
    }









    /**
     * SideとTemplateで利用。
     * 2023-09-09
     *
     * @param array $in
     * @return void
     */
    public static function category_search_box( $args ) {

        $_online = NULL;
        if( $args[ 't' ] == 24 )
        {
            $_width	= 240;
            $_css1		= '__side_search';
            $_size		= 16;
        }
        elseif( $args[ 't' ]	== 50 )
        {
            $_width	= 500;
            $_css1		= '__kx_search';
            $_size		= 50;
            $_online = '<div style="display:flex;justify-content: flex-end;">';
            $_online .= '<div id="laravel-status-badge" style="display: inline-block; padding: 0px 8px; margin-bottom: 0px; font-size: 11px; font-weight: bold; color: #fff; background-color: #19692c; border-radius: 4px; letter-spacing: 1px;">';
            $_online .= '● Laravel ON-LINE';
            $_online .= '</div>';
            $_online .= '</div>';
        }
        else
        {
            $_width	= 300;
            $_css1		= '__kx_search';
            $_size		= 24;
        }

        if( empty( $cat ) )
        {
            $_categories = get_the_category();
        }

        $is_online =  \Kx\Core\DynamicRegistry::get_system('laravel_online') ;

        if ($is_online) {

            return \Kx\Utils\KxTemplate::get('external/Laravel_search_cat', [
                'width'      => $_width,
                'size'       => $_size,
                'css_class'  => $_css1,
                'categories' => $_categories,
                'online'     => $_online,
            ], false); // 文字列として返す
        }


        $_categorys =$_categories;

        $ret  = '';

        $ret .= '<div id="search">';

        $ret .= '<form  style="vertical-align:bottom;display:table;" >';
        $ret .= '<input type="search" name="s" placeholder="search" size="'.$_size.'" class="__search">';
        $ret .= '<input type="submit" value="➡" alt="検索" title="検索" class="searchsubmit __search_button"  style="">';

        $ret .= '<div class="'.$_css1.'">Category</div>';

        foreach( $_categorys as $_category ):

            $ret .= '<table style="max-width:'.$_width.'px;"><tbody>';
            $ret .= '<tr><td  width="15">';
            $ret .= '<input type="checkbox" name="cat" value="'.$_category->term_id.'" checked></label>';
            $ret .= '</td><td>';
            $ret .= $_category->name;
            $ret .= '</td><td width="60">';
            $ret .= 'id:'. $_category->cat_ID .'';
            $ret .= '</td><td width="40">';
            $ret .= $_category->category_count;
            $ret .= 'p';
            $ret .= '</td></tr>';

            $ret .='</tbody></table>';

        endforeach;

        $ret .= '<div class="'.$_css1.'">tag</div>';
        $_tags = get_the_tags();

        if ( $_tags )
        {
            $_tr = 0;
            $ret .= '<table style="max-width:270px;"><tbody>';

            foreach ( $_tags as $_tag ):

                if( $_tr == 0)
                {
                    $ret .= '<tr><td width="33%">';
                }
                else
                {
                    $ret .= '<td  width="33%">';
                }


                $ret .= '<input type="checkbox"  name="tag" value="'.$_tag->name.'">';
                $ret .= $_tag->name;

                if( $_tr != 1 )
                {
                    $ret .= '</td>';
                    $_tr ++;
                }
                else
                {
                    $ret .='</td></tr>';

                    if( $_tr == 1 )
                    {
                        $_tr = 0;
                    }
                }

            endforeach;

            $ret .= '</tbody></table>';
            $ret .= '</select>';
        }

        $ret .= '</form>';
        $ret .= '</div>';

        return $ret;
    }


    /**
     * ブラウザタブ表示用に最適化されたタイトルを生成する。
     * ルート階層と記事名（＠なし）を優先し、中間を圧縮する。
     * * @return string
     */
    public static function generate_formatted_tab_title() {
        $post_id = get_the_ID();
        $path_index = Dy::get_path_index($post_id);

        if (empty($path_index) || empty($path_index['full'])) {
            return get_bloginfo('name');
        }

        // --- 設定のロード ---
        $config = Su::get('system_internal_schema')['generate_formatted_tab_title'];
        $max_len    = $config['max_lne'] ?? 26;    // 混合上限
        $max_mb_len = $config['max_mb_len'] ?? 20; // MB上限
        $shorthand  = $config['shorthand_definitions'] ?? [];
        $sep = ' ';

        // --- パーツの整理 ---
        $parts = $path_index['parts'];
        $count = count($parts);

        // 1. 各パーツの「＠」を除去し、略称を適用するクレンジング
        $clean_parts = array_map(function($part) use ($shorthand) {
            // ＠で分割して名称側を取得
            $name = (strpos($part, '＠') !== false) ? explode('＠', $part, 2)[1] : $part;
            // 略称適用
            return str_replace(array_keys($shorthand), array_values($shorthand), $name);
        }, $parts);

        // --- 構成パターンの判定 ---

        // A. 階層が浅い場合（1〜2層）
        if ($count <= 2) {
            $result = implode($sep, $clean_parts);
        }
        else {
            // B. 3層以上の場合： [最初] + [中間] + [最後]
            $first = $clean_parts[0];
            $last  = end($clean_parts);
            $middles = array_slice($clean_parts, 1, -1);

            // 中間層を短縮（各パーツ最大3文字+*）
            $short_middles = array_map(function($m) {
                return (mb_strlen($m) > 3) ? mb_substr($m, 0, 3) . '*' : $m;
            }, $middles);

            $result = $first . $sep . implode($sep, $short_middles) . $sep . $last;
        }

        // --- 最終文字数調整 ---

        // 既に制限内ならそのまま返す
        if (mb_strlen($result) <= $max_mb_len) {
            return $result;
        }

        // まだ長い場合、中間層をさらに削る（[最初]...[最後]）
        if ($count > 2) {
            $first = $clean_parts[0];
            $last  = end($clean_parts);
            $result = $first . '..' . $last;
        }

        // それでも制限を超えている場合は物理カット
        return (mb_strlen($result) > $max_mb_len)
            ? mb_substr($result, 0, $max_mb_len - 1) . '…'
            : $result;
    }







    /**
     * 検索フォームレンダリング（Laravel/Localハイブリッド）
     * * @param array $args t=1でタグを折りたたみ表示
     * @return string HTML content
     */
    public static function render_search_form($args) {
        $is_laravel_online = \Kx\Core\DynamicRegistry::get_system('laravel_online');

        if ($is_laravel_online) {
            Msg::info("Search Engine: Laravel API mode active.");
            return KxTemplate::get('external/Laravel_search_page', [], false);
        }

        $prefix_definitions = Su::get('title_prefix_map')['prefixes'] ?? [];

        // スタイル定義（ダークテーマ用）
        $style = '
        <style>
            .kx-search-dark { background: #1a1a1a; color: #e0e0e0; padding: 20px; border-radius: 8px; }
            .kx-search-field {
                background: #2d2d2d; border: 1px solid #444; color: #fff; padding: 10px;
                border-radius: 4px; transition: border-color 0.3s;
            }
            .kx-search-field:focus { border-color: var(--kx-hue, #0073aa); outline: none; }
            .kx-scroll-select {
                width: 100%; font-family: "Cascadia Code", "Courier New", monospace;
                background: #252525; color: #00ff66; /* ターミナル風の配色 */
                border: 1px solid #333; padding: 5px; cursor: pointer;
            }
            .kx-scroll-select option { padding: 4px 8px; border-bottom: 1px solid #333; }
            .kx-scroll-select option:hover { background: #3d3d3d; }
            .filter-label { color: #888; font-size: 0.85rem; margin: 15px 0 5px; display: block; text-transform: uppercase; }
            .tag-count { color: #ffad33; }
        </style>';

        $html = $style . '<div id="kx-search-container" class="kx-search-dark">';
        $html .= '<form method="get" action="' . esc_url(home_url('/')) . '">';

        // キーワード入力
        $html .= '<div style="display:flex; gap:10px;">';
        $html .= '    <input name="s" id="s" type="text" placeholder="Enter Knowledge Keywords..." class="kx-search-field" style="flex-grow:1;">';
        $html .= '    <button type="submit" class="kx-search-field" style="cursor:pointer; background:var(--kx-hue, #444);">SEARCH</button>';
        $html .= '</div>';

        // カテゴリー：スクロール選択
        $categories = get_categories(['taxonomy' => 'category', 'hide_empty' => 0]);
        if ($categories) {
            $html .= '<label class="filter-label">≫ Category hierarchy</label>';
            $html .= '<select name="cat" size="8" class="kx-scroll-select">';
            $html .= '<option value="" style="color:#aaa;">-- Select Context --</option>';

            $category_groups = [];
            foreach ($categories as $cat) {
                $label = '???';
                $order = 999;
                foreach ($prefix_definitions as $pattern => $def) {
                    if (preg_match('/^' . preg_quote($pattern, '/') . '/', $cat->name)) {
                        $label = $def['name'];
                        $order = array_search($pattern, array_keys($prefix_definitions));
                        break;
                    }
                }

                // フォーマット済みの文字列（人間可読性優先）
                $text = sprintf(
                    "%-10s | %-24s (%3d posts)",
                    "[$label]",
                    $cat->name,
                    $cat->category_count
                );
                $category_groups[$order][] = ['id' => $cat->term_id, 'text' => $text];
            }

            ksort($category_groups);
            foreach ($category_groups as $group) {
                foreach ($group as $item) {
                    $html .= sprintf('<option value="%s">%s</option>', $item['id'], esc_html($item['text']));
                }
            }
            $html .= '</select>';
        }

        // タグ：スクロール選択
        $tags = get_terms(['taxonomy' => 'post_tag', 'hide_empty' => 0]);
        if ($tags) {
            $html .= '<label class="filter-label">≫ Tag registry</label>';
            $html .= '<select name="tag" size="8" class="kx-scroll-select" style="color:#33adff;">';
            $html .= '<option value="" style="color:#aaa;">-- Select Attribute --</option>';

            foreach ($tags as $tag) {
                if (empty($tag->count)) {
                    wp_delete_term($tag->term_id, 'post_tag');
                    \Kx\Utils\KxMessage::notice("System: Purged orphan tag [{$tag->name}]");
                    continue;
                }
                $html .= sprintf(
                    '<option value="%s"># %-20s (%d)</option>',
                    esc_attr($tag->name),
                    $tag->name,
                    $tag->count
                );
            }
            $html .= '</select>';
        }

        $html .= '</form></div>';
        return $html;
    }


    /**
     * headerバーの制御ロジック
     * * @return string|void
     */
    public static function header_bar() {
        // 編集画面では表示しない
        if ( !empty( $_GET['action'] ) && $_GET['action'] == 'edit' ) {
            return;
        }

        $post_id    = get_the_ID();
        $path_index = Dy::get_path_index($post_id) ?? [];
        $cache      = Dy::get_content_cache($post_id);
        $colormgr   = Dy::get_color_mgr($post_id);

        // 親階層情報の取得
        $parent_id    = Dy::get_content_cache($post_id, 'parent_id');
        $parent_title = $parent_id ? Dy::get_title($parent_id) : '';
        $is_root      = ($path_index && isset($path_index['depth']) && $path_index['depth'] === 1);
        $warning      = $cache['ana']['node']['warning'] ?? null;

        // 上位シンボル（ナビゲーション）の構築
        $upper_symbol = '';
        if ( $is_root ) {
            $upper_symbol = sprintf('<a href="%s" style="color:red;">&nbsp;≪</a>', get_permalink(1));
        } elseif ( $parent_id ) {
            $style = $warning ? 'color: #ffca28;' : '';
            $label = '▲';
            $upper_symbol = sprintf(
                '<span class="__js_hover_UpperLINKq"><a href="%s" style="%s">&nbsp;%s　</a></span>' .
                '<span class="__js_hover_UpperLINKa">UPPER-LINK：%s</span>',
                get_permalink($parent_id), $style, $label, esc_html($parent_title)
            );
        }else if(($path_index['wp_type'] ?? '') === 'page'){
            $upper_symbol = '━';
        } else {
            $upper_symbol = '🟥';
        }

        // 外部コンポーネントの準備
        $menu   = wp_nav_menu([
            'menu'            => 'main',
            'echo'            => false,
            'container_class' => '__header_bar_container',
        ]);

        $editor = (!is_404()) ? \Kx\Component\Editor::open($post_id, 'header') : '';

        $is_1920 = \Kx\Utils\Toolbox::isWideLayoutDisplay( $post_id );
        $layout_class = $is_1920 ? '__is_wide_layout' : '__is_normal_layout';

        // テンプレートへ渡す引数
        $args = [
            'post_id'      => $post_id,
            'colormgr'     => $colormgr,
            'class'        => ($colormgr['class_array']['base'] ?? '') . ' ' . $layout_class,
            'upper_symbol' => $upper_symbol,
            'menu'         => $menu,
            'editor'       => $editor,
            'path_full'    => $path_index['full'] ?? '',
            'is_wide'      => $is_1920
        ];

        return KxTemplate::get('layout/header-bar', $args, false);
    }



    /**
     * サイドバー。分岐・選択
     *
     * @return void
     */
    public static function html_side() {
        $post_id = get_the_ID();
        $ret	= '';


        $path_index = Dy::get_path_index($post_id) ?? [];

        $width = (($path_index['type'] ?? '') === 'prod_work_production_log') ? 245 : 280;

        $ret .= '<div class="kx-sidebar __js_show" style="position:fixed;width: '.$width.'px;">';

        //固定ページ判定。2023-02-24
        if( is_page() )
        {
            $ret .= '<div style="text-align: center;" class="">';

            //ログイン判定。2023-02-24
            if( is_user_logged_in() ){

                //ログインユーザー情報取得。2023-02-24
                $user = wp_get_current_user();
                $ret .= 'Lv' . $user->get( 'wp_user_level' ) . '　';
                $ret .=  $user->get('user_login'); // 表示用の名前を取得
                $ret .=  '　-　logged in<BR>';

                $ret .= '<a href="' . wp_logout_url() . '">[Logout]</a>';

                $ret .= '<BR><a HREF="wp-admin/about.php">《Setting》</a>';
            }
            else
            {
                $ret .= '<div style="color:red;">Not logged in</div>';
                $ret .= '<a href="' . wp_login_url() . '">[Please log in]</a>';
            }
            $ret .= '</div>';
        }

        $ret .= '<div>';
        $ret .= \Kx\Core\OutlineManager::render($post_id,'side' ,false);
        //$ret .= kx_CLASS_outline(	[	't'	=>	'side'	] );
        $ret .= '</div>';

        $ret .= '</div>';

        return $ret;
    }



    /**
     * クリップボードにIDをコピー。
     * 2021-08-06
     *
     * @param int $id
     * @param string $type   link or それ以外。
     * @return void
     */
    public static function script_id_clipboard( $id , $type = null ){

        $class = '__js_copy_clipboard';

        $ret = NULL;
        $ret .= '<span class="__small" style="background:hsla(0,100%,100%,.1); border-radius:5px">';

        if( $type == 'link' )
        {
            $ret .= '<span class="__hidden">'.$id.'</span>';
            $ret .= '<a style="height:20px;padding:3px 10px 5px 10px;" class="' . $class . '">ID：'.$id.'</a>';
        }
        else
        {
            $ret .= '<button class="__btn0" tabindex="-1"></button>';//ダミー。これを入れておかないと機能がおかしくなる。2024-09-08

            //IDのコピー。2023-02-28
            $ret .= '<span class="__hidden">'.$id.'</span>';
            $ret .= '<button style="height:20px;padding:3px 10px 5px 10px;" class="' . $class . ' __btn0" tabindex="-1">ID：'.$id.'</button>';

            //formatショートコードのコピー。2023-02-28
            $ret .= '<span class="__hidden">[ghost id='.$id.' m='. get_the_title( $id ) .']</span>';
            $ret .= '<button style="height:20px;padding:3px 10px 5px 10px;" class="' . $class . ' __btn0" tabindex="-1">Ghost</button>';


            //kxショートコードのコピー。<p>とt=65 に変更。2023-03-30
            $ret .= '<span class="__hidden"><p>[kx t=60 id='.$id.' m='. get_the_title( $id ) .']</p></span>';
            $ret .= '<button style="height:20px;padding:3px 10px 5px 10px;" class="' . $class . ' __btn0" tabindex="-1">T60</button>';
        }

        $ret .= '</span>';


        return $ret;
    }

    /**
     * 投稿タイプに基づき投稿者IDを更新し、結果メッセージを返す。
     * * @param int|null $id 投稿ID。未指定時は現在の投稿ID。
     * @return string|null 変更があった場合はHTML文字列、不要または失敗時はnull。
     */
    public static function updateAuthorIdByPostType($id = null): ?string
    {
        $config = Su::get('system_internal_schema')['post_author_auto_sync'];

        // 追加：有効フラグのチェック
        if (!($config['is_enabled'] ?? false)) {
            return null;
        }

        // 1. IDの解決（元の empty 判定を継承しつつ簡潔に）
        $id = $id ?: get_the_ID();
        if (!$id) {
            return null;
        }

        // 2. 変更：JSONの type_map に基づきターゲットIDを解決
        $post_type = get_post_type($id);
        $targetAuthorId = $config['type_map'][$post_type] ?? null;

        // 3. エラー処理
        if ($targetAuthorId === null) {
            echo 'ERROR';
            return null;
        }

        // 4. 現在値の取得と変更チェック（!= 比較を維持しつつキャストで安定化）
        $currentAuthorId = get_post_field('post_author', $id);

        if ((int)$currentAuthorId !== $targetAuthorId) {
            // 5. 更新処理
            $result = wp_update_post([
                'ID'          => $id,
                'post_author' => $targetAuthorId,
            ]);

            if (is_wp_error($result)) {
                return null;
            }

            // 6. 返却文字列の構築（元の . 連結の順序と文字列を厳密に再現）
            return sprintf(
                '<div style="color:red;">データ置換：authorID：%s⇒⇒%d■Title：%s■ID：%s</div>',
                $currentAuthorId,
                $targetAuthorId,
                get_the_title($id),
                $id
            );
        }

        // 変更がない場合は null
        return null;
    }



    /**
     * 投稿の更新処理（多段Ghost自動置換用）
     * 実行時キャッシュ Dy を利用して、セッションレスかつ堅牢な更新管理を行う。
     *
     * @param int    $status       処理ステータス（1: 通常更新, 2: 異常時リロード提示）
     * @param int    $post_id      更新対象の投稿ID
     * @param string $new_content  更新する投稿本文
     * @param int    $diff_seconds 最終更新からの経過時間（秒）
     * @param string $log_msg      表示用のログメッセージ
     */
    public static function update_post(int $status, int $post_id, string $new_content, ?int $diff_seconds = null, ?string $log_msg = ''): void {
        // 1. 実行回数の管理 (Dy::trace_count)
        $current_run_count = Dy::trace_count('ghost_update_count', 1);

        $diff_seconds = $diff_seconds ?? 1000;
        $max_retries  = 3; // 1リクエスト内の上限
        $wait_time    = 5; // 最小インターバル（秒）

        // 2. ガード節
        if (is_admin() || (isset($_GET['action']) && $_GET['action'] === 'edit') || $post_id <= 0) {
            return;
        }

        // 3. 無限ループ・短時間更新の阻止
        if ($current_run_count > $max_retries) {
            Msg::error("自動更新停止: 回数上限({$max_retries})を超過しました。");
            return;
        }

        if ($status > 0 && $diff_seconds < $wait_time) {
            $stop_msg = "<div class='__text_center'>{$diff_seconds}秒差・連続更新ストップ🔃</div>";
            Msg::error(['OUT_echo_fixed' => $stop_msg, 'OUT_echo_top' => $stop_msg]);
            return;
        }

        // 4. メッセージ構築 (旧 kx_updat_message の統合)
        if ($status === 1) {
            // Dy を使用して実行時ログを蓄積
            $update_logs = Dy::get('ghost_update_logs') ?: [];
            $log_html = sprintf(
                '<div class="__large __margin_bottom8">🔃%d　%s</div>',
                $current_run_count,
                esc_html($log_msg)
            );
            $update_logs[] = $log_html;
            Dy::set('ghost_update_logs', $update_logs);

            // 表示用HTMLの構築
            echo '<div class="kxsc_update">';
            printf('<div class="__xlarge __margin_bottom8">更新中…%d件…………</div>', count($update_logs));
            foreach ($update_logs as $line) {
                echo $line;
            }
            echo '</div>';

        } elseif ($status === 2) {
            printf(
                '<div id="error-message5" class="__error_fixed_left_bottom__" style="cursor: pointer;" onclick="location.reload()">✦✦RELOAD!!! %d !✦✦</div>',
                esc_html($post_id)
            );
        }

        // 5. DB更新処理
        if (in_array($status, [1, 2], true)) {
            $update_data = [
                'ID'           => $post_id,
                'post_title'   => get_the_title($post_id),
                'post_content' => $new_content,
            ];

            $result = wp_update_post($update_data);

            // 6. 更新成功時のみリロード
            if ($result !== 0 && !is_wp_error($result) && $status === 1) {
                wp_enqueue_script(
                    'reload-legacy',
                    get_stylesheet_directory_uri() . '/../kasax_child/assets/js/legacy/reload.js',
                    ['jquery'],
                    '1.1',
                    true
                );
            }
        }
    }


    /**
     * 汎用テキストファイル保存関数（Markdown対応版）
     * * @param string $content  保存内容
     * @param array  $meta     { 'id': 識別子, 'title': タイトル }
     * @param array  $options  {
     * 'use_time': bool,
     * 'use_id': bool,
     * 'ext': string ('txt'|'md'), // 拡張子指定
     * 'sub_dir': string
     * }
     */
    public static function save_text_to_local(string $content, array $meta = [], array $options = []) {


        // 1. オプションの初期値設定（拡張子 ext を追加）
        $default_options = [
            'use_time' => true,
            'use_id'   => true,
            'ext'      => $meta['ext'] ?? 'txt', // デフォルトは .txt
            'sub_dir'  => '',
            'prefix'   => 'WPtexts'
        ];
        $opt = array_merge($default_options, $options);

        // 2. Suからベースディレクトリを取得
        $base_dir = Su::get_path('dir_export_all');
        echo $base_dir;
        if (empty($base_dir)) {
            Msg::error("保存失敗：dir_export_all が未定義です。");
            return false;
        }

        // 3. ファイル名の構築
        $name_parts = [];
        if (!empty($opt['prefix'])) $name_parts[] = $opt['prefix'];

        if ($opt['use_time']) {
            $datetime = new \DateTime('now', new \DateTimeZone('Asia/Tokyo'));
            $name_parts[] = $datetime->format("Ymd_His");
        }

        if ($opt['use_id']) {
            $id_str = !empty($meta['id']) ? "id：{$meta['id']}" : "id：unknown";
            $name_parts[] = $id_str;
        }

        if (!empty($meta['title'])) $name_parts[] = $meta['title'];

        // 拡張子の結合（ここを動的に変更）
        $extension = ltrim($opt['ext'], '.');
        $filename  = implode("_", $name_parts) . "." . $extension;

        // 禁止記号を置換
        $filename = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $filename);

        // 4. パスの解決と作成
        $save_path = rtrim($base_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!empty($opt['sub_dir'])) {
            $save_path .= trim($opt['sub_dir'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        }

        if (!file_exists($save_path)) {
            mkdir($save_path, 0755, true);
        }

        $full_file_path = $save_path . $filename;

        // 5. 保存効率（Idle-Check）
        if (file_exists($full_file_path) && file_get_contents($full_file_path) === $content) {
            Msg::info("保存スキップ：内容同一（{$filename}）");
            return $full_file_path;
        }

        // 6. 書き込み
        if (file_put_contents($full_file_path, $content) !== false) {
            Msg::info("ファイル保存完了：{$filename}");

            // --- ここから Pandoc 処理 ---
            if ($opt['ext'] === 'epub') {
                $pandoc_exe = "\"C:\\Program Files\\Pandoc\\pandoc.exe\"";

                // 1. デバッグ用：今保存した中身（HTML）を「.debug.html」としてコピー保存
                $debug_html_path = str_replace('.epub', '.debug.html', $full_file_path);
                copy($full_file_path, $debug_html_path);
                Msg::info("Debug HTML出力： " . basename($debug_html_path));

                // 2. 変換処理
                // 入力はHTML（$full_file_path）、出力も同じパス
                // ※もし上書きで失敗する場合は、出力を別パスにする必要があります
                $cmd = "{$pandoc_exe} -f html \"{$full_file_path}\" -o \"{$full_file_path}\"";

                exec($cmd, $output, $return_code);

                if ($return_code === 0) {
                    Msg::info("EPUB変換成功：{$filename}");
                } else {
                    // 失敗した場合、実行コマンドと出力をログに出すと原因がわかります
                    $error_detail = implode("\n", $output);
                    Msg::error("EPUB変換失敗 (Code: {$return_code})。詳細: {$error_detail}");
                }
            } else {
                Msg::info("ファイル保存完了：{$filename}");
            }


            return $full_file_path;
        }

        Msg::error("ファイル書き込み失敗：{$full_file_path}");
        return false;
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
        $default = [
            'text'    => 'リンク',
            'mode'    => 'right',
            'content' => '[raretu]',
            'label'   => '＋'
        ];
        $a = array_merge($default, $args);

        // KxQueryまたはdbkx0_PostSearchMapperを用いてIDを抽出
        // システムの鉄則：冗長なSQL発行を厳禁とする
        $ids = \Kx\Database\dbkx0_PostSearchMapper::get_ids_by_title($target_title);
        $count = count($ids);

        if ($count === 1) {
            // 実体が存在する場合：KxLinkを召喚
            return \Kx\Components\KxLink::render($ids[0], [
                'text' => $a['text'],
                'mode' => $a['mode']
            ]);
        } elseif ($count > 1) {
            // 重複エラー時：整合性保護のため警告を通知 [cite: 3, 50]
            \Kx\Utils\KxMessage::warn("重複タイトルを検知しました: {$target_title}");
            return '<span class="kx-error">ERROR: Duplicate</span>';
        } else {
            // 不在の場合：QuickInserterで新規作成窓口を提供
            return \Kx\Component\QuickInserter::render(
                $base_post_id,
                $target_title,
                $a['content'],
                $a['label'] . $a['text']
            );
        }
    }


    /**
     * 現在のページが1920pxワイドレイアウトを適用すべきタイプ（制作ログ等）かどうかを判定する
     * LLMフレンドリー名: isWideLayoutScreen / checkIs1920WidthType
     * * @param int|null $post_id 投稿ID（未指定時は現在のID）
     * @return bool 1920px対象ならtrue、そうでなければfalse
     */
    public static function isWideLayoutDisplay(?int $post_id = null): bool
    {
        $id = $post_id ?? get_the_ID();
        if (!$id) return false;

        // 特定のタイプ（prod_work_production_log：制作ログ等）であるかを判定
        return \Kx\Core\TitleParser::is_type('prod_work_production_log', $id);
    }



    /**
     * コンテンツをEPUB出力用のHTML構造に置換・変換する
     * * @param string $content 変換前のコンテンツ
     * @param string $title タイトル
     * @return string 完全なHTML構造の文字列
     */
    public static function convert_content_to_epub_html($content, $post_id,$title = 'no-title') {

        // 1. WordPressの自動整形を適用（改行を <p> や <br> に変換）
        // これにより、生テキストやMarkdown混じりの内容が正しいHTML構造になります


        //$content = kxad_the_content_compile($content, 'epub');
        $content = \Kx\Core\ContentProcessor::compile($text,$post_id, 'epub');
        $content = wpautop($content);

        // 2. インラインスタイルの除去（特に色の指定など、電子書籍でエラーになりやすいもの）
        $content = preg_replace('/style\s*=\s*"[^"]*color\s*:[^";]+;?[^"]*"/i', '', $content);



        // 3. HTML構築
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>{$title}</title>
</head>
<body>
  <h1>{$title}</h1>
  <div class="content">
    {$content}
  </div>
</body>
</html>
HTML;
        return $html;
    }
}