<?php

/**
 * inc\utils\class-kx-taskboard.php
 *
 */
namespace Kx\Core;

use Kx\Core\SystemConfig as Su;
use Kx\Core\DynamicRegistry as Dy;
use Kx\Core\KxDirector as kx;
use Kx\Utils\KxMessage as MSG;
use Kx\Launcher\KxPostLauncher;
use Kx\Utils\KxTemplate;


/**
 * 作業ボード
 */
class TaskBoard {

    const BOARD_TYPES  = ['search','menu', 'top','post_top','k0_top'];

    private static $shortcode_host_id = '';
    private static $shortcode_host_title = '';
    private static $shortcode_host_cat = [];

    /**
     * ショートコード。
     *
     */
    public static function shortcode($atts){
        if ( is_admin() ) {
            return '';
        }

        if ( (Dy::get('trace')['kxx_sc_count'] ?? 0 ) > 0 ) {
            return '━━━　ShortCODE　━━━';
        }

        Dy::trace_count('kxx_sc_count', 1);

        $args = shortcode_atts([
            'type'					=>	'',			//タイプ

            'cat'						=>	'',			//カテゴリー
            'c'							=>	'',	//kousei3用・キャラ用//主人公番号
            'cs'						=>	'',	//kousei3用		//その他キャラ。"xxx,xxx"
            'c_clone'				=>	'',	//キャラ用。多重キャラ。他と設定を共有設定。
            'text'					=>	'',	//キャラ用
            'check_update'	=>	'',	//基本は0。入力すればON。自動リロードは"RELOAD"
            'check_search'	=>	'',	//基本は0
            'sys'						=>	'',	//システム系。例：k3normal

            't'							=>	'', //タイプ(キャラクター種類)

            //'select0'		  	=>	'',	//works DB 列（カラム）
            'select_top'		=>	'', //(σ|γ|Β|δ)の先頭選択。
            'select_c'		  =>	'',
            'select_date'	  =>	'',	//works DB date用SELECT ＿の後に年代（1～4桁）
            'select1'			  =>	'',	//works DB セレクトジャンル。
            'select2_c'		  =>	'',	//ANDによる複数。
            'select2'			  =>	'',	//works DB セレクト名前。

            'test'					=>	'',	//テストコード。
            'wfm_end'				=>	'',	//終了コード

            'f'							=>	'',	//filter	基本は1。保存型は削除。基本不使用。2022-01-30
        ], $atts);

        // 2. 新クラスの静的メソッドへ丸投げ
        // KxTemplate の仕様に基づき、必ず return で返す。
        $ret = self::render($args);

        Dy::trace_count('kxx_sc_count', -1);
        return $ret;
    }

    /**
     * メインエントリーポイント
     */
    public static function render($args) {

        self::$shortcode_host_id = get_the_ID();

        // 1. TaskBoardエンジンの適用対象かどうかを判定
        if (self::is_engine_active($args)) {
            return self::render_board($args);
        }

        // 2. 非対象
        return '━━━　テンプレート：未実装/運用終了　━━━';

    }

    /**
     * エンジンを起動すべきかどうかの判定ロジック
     * 「new」という主観を排除し、処理対象（Target）か否かで判定する
     */
    private static function is_engine_active($args) {

        // 特定のタイプ（menu等）は、このエンジンが管理する「対象」とみなす
        $managed_types = self::BOARD_TYPES;
        if (isset($args['type']) && in_array($args['type'], $managed_types, true)) {
            return true;
        }

        return false;
    }

    /**
     * 配列構造に基づいたボードの描画実行
     */
    private static function render_board($args) {

        $blueprint = self::get_blueprint($args['type'], $args);
        $structured_data = self::hydrate_data($blueprint, $args);

        $html = '';
        foreach ($structured_data as $key => $data) {
            // IDを生成（例: section-list）
            $anchor_id = "section-" . str_replace('section_', '', $key);

            if ($data['use_outline']) {
                // OutlineManagerにIDを渡す
                $html .= \Kx\Core\OutlineManager::add_from_loop(self::$shortcode_host_id, $data['title'], $anchor_id);
            }

            // HTML出力をラッパーで囲む
            $html .= sprintf('<section id="%s" class="taskboard-unit">', esc_attr($anchor_id));
            $html .= self::render_component($data);
            $html .= '</section>';
        }
        return $html;
    }

    /**
     * 構成定義（Blueprint）の取得
     */
    private static function get_blueprint($type, $args) {
        switch ($type) {
            case 'search':
                return self::get_page_search_schema($args);
            case 'menu':
                return self::get_page_menu_schema();
            case 'top':
                return self::get_page_top_schema();
            case 'post_top':
                return self::get_post_top_schema();
            case 'k0_top':
                return self::get_post_k0_top_schema();
            default:
                MSG::error("TaskBoard: Schema not found for type [{$type}]");
                return [];
        }
    }


    /**
     * Undocumented function
     *
     */
    private static function get_page_search_schema($args) {

    return [
            'section_search' => [
                'content_type'  => 'function',
                'callback'      => [\Kx\Utils\Toolbox::class,  'render_search_form'],
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
        $title = Dy::get_title(self::$shortcode_host_id) ?? 'error';
        $id =  kx::get_ids_by_title($title)[0];
        return [
            'section_search' => [
                'content_type'  => 'function',
                'callback'      => [\Kx\Utils\Toolbox::class,  'category_search_box'],
                'title'         => 'SEARCH',
                'args'          => [ 't'=> 50],
                'use_outline'   => true,
            ],

            'host_post_title' => [
                'content_type'  => 'launcher',
                'data_source'   => 'host_title',
                'title'         => 'List',
                'args'          => ['t' => 60,'id'=>$id ,'mode' => 'link' , 'ppp'=>1 ],
                'use_outline'   => true,
            ],

            'section_list' => [
                'content_type'  => 'launcher',
                'data_source'   => 'descendants', // 取得ロジックの識別子
                'title'         => '▽',
                'level'         => 3,
                'args'          => ['mode' => 'link'],
                'use_outline'   => false,
            ],
            'section_modified'  => [
                'content_type'  => 'launcher',
                'data_source'   => 'post_search',
                'title'         => '更新履歴',
                'args'          => ['t' => 91, 'mode' => 'link','modified' => true],
                'use_outline'   => true,
            ]
        ];
    }

    /**
     * 固定ページ管理（top）のスキーマ定義。単一ページ。
     */
    private static function get_page_top_schema() {
        return [
            'section_list'      => [
                'content_type'  => 'launcher',
                'title'         => 'List',
                'args'          => ['mode' => 'link','search' => '∬' , 'depth' => 1],
                'use_outline'   => true,
            ],
        ];
    }

    /**
     * 固定ページ管理（top）のスキーマ定義
     */
    private static function get_post_top_schema() {
        return [

            'in' =>[
                'content_type' => 'inserter',
                'title'        => "LIST",
                'use_outline'  => true,
                'args'         => [
                    'label'   => "NEW＋",
                ],
            ],

            'section_list' => [
                'content_type'  => 'launcher',
                'data_source'   => 'descendants', // 取得ロジックの識別子
                'title'         => 'link',
                'level'        => 3,
                'args'          => ['mode' => 'link'],
                'use_outline'   => true,
            ],
        ];
    }

    /**
     * 固定ページ管理（k0_top）のスキーマ定義
     * ループ処理を用いて動的にセクションを生成
     */
    private static function get_post_k0_top_schema() {
        // 1. 必要な情報の準備
        self::fetch_host_categories();
        $cat_data = self::$shortcode_host_cat[0] ?? ['id' => '', 'name' => 'Unknown'];
        $cat_id   = $cat_data['id'];
        $cat_name = $cat_data['name'];

        // 設定からプレフィックス（ksy, olf等）を取得
        $prod_works = Su::get('identifier_schema')['common_prefixes']['work_a'] ?? [];
        $blueprint  = [];

        // 2. WORKセクションの生成 (inserter + launcher)
        foreach ($prod_works as $work_key) {
            $uc_work = ucfirst($work_key);

            // 新規作成ボタン (inserter)
            $blueprint["i_{$work_key}"] = [
                'content_type' => 'inserter',
                'title'        => "WORK：{$uc_work}",
                'use_outline'  => true,
                'args'         => [
                    'title'   => "{$cat_name}≫c00NEW00≫{$work_key}0000000",
                    'content' => '[raretu]',
                    'label'   => "NEW＋{$uc_work}",
                ],
            ];

            // 既存リスト表示 (launcher)
            $blueprint["p_{$work_key}"] = [
                'content_type' => 'launcher',
                'title'        => "List：{$work_key}",
                'level'        => 3,
                'use_outline'  => false,
                'args'         => [
                    'mode'          => 'link',
                    'cat'           => $cat_id,
                    'search'        => $work_key,
                    'search_suffix' => ':num:',
                    'depth'         => 3,
                ],
            ];
        }

        // 3. CHARACTERセクションの生成 (0～9)
        for ($i = 0; $i < 10; $i++) {
            // キャラクター新規作成
            $blueprint["ci_{$i}"] = [
                'content_type' => 'inserter',
                'title'        => "CHARACTER：{$i}",
                'use_outline'  => true,
                'args'         => [
                    'title'   => "{$cat_name}≫c{$i}NEW00",
                    'content' => '[raretu]',
                    'label'   => "NEW＋C{$i}",
                ],
            ];

            // キャラクター別リスト
            $blueprint["cl_{$i}"] = [
                'content_type' => 'launcher',
                'title'        => "Char{$i} List",
                'level'        => 3,
                'use_outline'  => false,
                'args'         => [
                    'mode'   => 'link',
                    'search' => "{$cat_name}≫c{$i}",
                    'depth'  => 2,
                ],
            ];
        }

        // 4. 全体リストの追加
        $blueprint['ALL'] = [
            'content_type' => 'launcher',
            'data_source'  => 'descendants',
            'title'        => 'ALL LIST',
            'use_outline'  => true,
            'args'         => [
                'mode' => 'link',
            ],
        ];

        return $blueprint;
    }


    /**
     * 固定ページ管理（k0_top）のスキーマ定義
     */
    private static function get_post_k0_top_schema00() {
        self::fetch_host_categories();
        $title = self::$shortcode_host_cat[0]['name'];

        $prod_works = Su::get('identifier_schema')['common_prefixes']['work_a'];

        foreach($prod_works as $work_key){
            $array['i'.$work_key] = [
                'content_type'  => 'inserter',
                'data_source'   => '',
                'title'         => 'WORK：'.ucfirst($work_key),
                'args'          => [
                    'title'         => $title.'≫c00NEW00≫'.$work_key.'0000000' ,
                    'content'       => '[raretu]',
                    'label'         => 'NEW＋'.ucfirst($work_key),
                ],
                'use_outline'   => true,
            ];

            $array['p'.$work_key] = [
                'content_type'  => 'launcher',
                'data_source'   => '',
                'title'         => 'List：'.$work_key,
                'level'         => 3,
                'args'          => [
                        'mode' => 'link',
                        'cat'  => self::$shortcode_host_cat[0]['id'],
                        'search' => $work_key ,
                        'search_suffix' => ':num:',
                        'depth' => 3
                    ],
                'use_outline'   => false,
            ];
        }


        for ($i = 0; $i < 9; $i++) {
            $array['ci'.$i] = [
                'content_type'  => 'inserter',
                'data_source'   => '',
                'title'         => 'CHARACTER：'.$i,
                'args'          => [
                    'title' => $title.'≫c'.$i.'NEW00' ,
                    'content'       => '[raretu]',
                    'label'=>'NEW＋C'.$i
                    ],
                'use_outline'   => true,
            ];

            $array['cl'.$i] = [
                'content_type'  => 'launcher',
                'data_source'   => '',
                'title'         => 'Char'.$i.'List',
                'level'         => 3,
                'args'          => ['mode' => 'link','search' => $title.'≫c'.$i , 'depth' => 2],
                'use_outline'   => false,
            ];
        }

        $array['ALL'] = [
                'content_type'  => 'launcher',
                'data_source'   => 'descendants',
                'title'         => 'ALL LIST',
                'args'          => ['mode' => 'link'],
                'use_outline'   => true,
            ];


        return $array;
    }

    /**
     * Blueprintに実データを注入する
     */
    private static function hydrate_data($blueprint, $args) {

        foreach ($blueprint as $key => &$section) {
            // 1. 安全装置：argsが未定義なら空配列で初期化
            if (!isset($section['args'])) {
                $section['args'] = [];
            }

            // 2. data_source に基づくデータ注入
            $source = $section['data_source'] ?? '';

            switch ($source) {
                case 'descendants':
                    // 子孫記事のIDリストを取得
                    $section['args']['ids'] = Dy::get_descendants(self::$shortcode_host_id);
                    break;

                case 'post_search':
                    // 記事タイトルを検索クエリとして注入
                    $section['args']['search'] = Dy::get_title(self::$shortcode_host_id);
                    break;

                case 'host_post_title':
                    self::$shortcode_host_title = Dy::get_title(self::$shortcode_host_id);

                    break;

                case 'category_info':
                    // 3. カテゴリ情報の取得と注入（未取得の場合のみ実行）
                    self::fetch_host_categories();
                    break;

                default:
                    // 指定なし、または未知のソースの場合は何もしない
                    break;
            }
        }
        return $blueprint;
    }


    /**
     * 個別コンポーネントの出力
     */
    private static function render_component($data) {
        // 1. content_type の存在チェック
        // キーが存在しない、または空の場合は 'ERROR' モードに強制変更する
        $type = 'ERROR';
        if (isset($data['content_type'])) {
            // キーが存在する場合のみ中身を評価。空文字ならデフォルトの launcher に。
            $type = (!empty($data['content_type'])) ? $data['content_type'] : 'ERROR';
        }

        // 2. タイトルの出力
        // --- ここでヘッダーレベルを決定 ---
        $level = (isset($data['level']) && is_int($data['level'])) ? $data['level'] : 2;
        // 安全のため、h1〜h6の範囲に制限する
        $tag = 'h' . max(1, min(6, $level));

        $title = !empty($data['title']) ? $data['title'] : 'Untitled Section';

        // 動的なタグでHTMLを生成
        $html = sprintf('<%1$s>%2$s</%1$s>', $tag, esc_html($title));

        // 3. 処理の分岐
        switch ($type) {
            case 'template':
                $name = $data['component'] ?? 'base-unit';
                $html .= KxTemplate::get("taskboard/{$name}", $data, false);
                break;

            case 'function':
                if (!empty($data['callback']) && is_callable($data['callback'])) {
                    $html .= call_user_func($data['callback'], $data['args'] ?? []);
                } else {
                    $html .= MSG::error("TaskBoard: Callback function is not callable.");
                }
                break;

            case 'launcher':
                $html .= KxPostLauncher::run($data['args'] ?? []);
                break;
            case 'inserter':
                $html .= \Kx\Component\QuickInserter::render(
                    self::$shortcode_host_id,
                    $data['args']['title'] ?? null,
                    $data['args']['content']?? null,
                    $data['args']['label']?? null,
                    'taskboard'
                );
                break;
            case 'ERROR':
            default:
                // 設定漏れの場合の警告表示
                $error_msg = "TaskBoard Error: 'content_type' is not defined in this section.";
                $html .= MSG::error($error_msg);
                $html .= '<p style="color:red;">設定を確認してください。</p>';
                break;
        }

        return $html;
    }

    /**
     * ホスト記事のカテゴリ情報を取得・キャッシュする
     */
    private static function fetch_host_categories() {
        // すでに取得済みの場合は何もしない（キャッシュ利用）
        if (!empty(self::$shortcode_host_cat)) {
            return;
        }

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