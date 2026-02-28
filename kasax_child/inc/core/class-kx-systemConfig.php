<?php
/**
 * [Path]: inc\core\SystemConfig.php
 * どこからでもアクセス可能
 */

namespace Kx\Core; // 1. 名前空間の定義

use \kx\Utils\KxMessage;

class SystemConfig {

    private static $instance = null;
    private static $settings = [];
    private static $paths = [];
    //path_indexのフラグ。
    const BOOLEAN_MARKERS  = [
        'prod_work_production_log' =>'matrix_grid',
        //'prod_character_relation',
        //'prod_character_core_logs',
        'prod_character_core'=> 'prod_character',
    ];

    //Sharedのルート。
    const SHARED_ROOT_TYPES = [
        'arc_shared_root' => true,
    ];

    //Sharedのlabel。
    const SHARED_LABEL_TYPES = [
        'arc_shared_history' => true,
        'arc_shared_works_character_prim' => true,
        'arc_shared_works_character_subs' => true,
        'arc_shared_works_prim'   => true,
        'arc_shared_works_subs'   => true,
        'arc_shared_person'  => true,
    ];

    //path_indexのタイプ。
    const PRIORITY_TYPES = [
        'arc_shared_history',
        'arc_shared_works_character_prim',
        'arc_shared_works_character_subs',
        'arc_shared_works_prim',
        'arc_shared_works_subs',
        'arc_shared_person',
        'arc_shared_root',
        'prod_work_production_log',
        'prod_work_production_logs',
        //'prod_character_relation_logs',
        //'prod_character_core_logs',
        'prod_character_relation',
        'prod_character_core',
        'prod_root',
        'material_root',
        'test_root',
        'strat_root',
        'phil_root',
        'sys_root',
        'XXX_root',
    ];


    private $title_preg =
    [
        'array_add_category' => //自動追記カテゴリーpublic $kxst_add_category =
        [
            'works'	 =>['/∬\w{1,}/'],
            'char'	 =>['/∬\w{1,}≫c/'  ],
        ],

        'array_add_tag'    => //public $kxst_add_tag =* 自動追記タグ
        [
            'char'	=>[ '/∬\w{1,}≫c\d\w{1,}\d/' , '/c\d\w{1,}\d/' ],
            'Idea'	=>[ '/∮≫S\d{4}/'            , '/S\d{4}/' ],
        ],
    ];

    //保存介入。タイトル。KxSu::get('add_save_title')
    private $add_save_title =  [
        '/' => '／' ,
        '(' => '〈' ,
        ')' => '〉' ,
        '+' => '＋' ,
    ];

    //保存介入系の置換。
    private $add_save_conent =     [
        //'検索文字'	=>[ '置換タイプ'	, '置換文字' 				],

        '/\d{4}(-|_)\d{2}(-|_)\d{2}＠＠ｔ/' =>[ 'date' ,'Y_m_d'  ],		//タイムスタンプ
        '/＠＠ｔ/'							=>[ 'date' , 'Y_m_d' ],		//タイムスタンプ
        //'/＠＠ｈ/'						=>[ 'date' , 'Y_m_d_H:i:s' 	],		//タイムスタンプ
        '/(?<!『)≫(?!』)/'				   =>[ ''	  , '＞'    ],		 //特殊文字
        '/<p><\/p>/'						=>[ ''	   , ''      ],	//削除
        '/\n　/' 															=> "\n",
    ];




    /**
     * コンストラクタ
     * * クラス内のプライベートプロパティを静的プロパティ self::$settings にマッピングし、
     * システム全体で共有可能な不変の設定セットを構築する。
     */
    private function __construct() {
        self::$settings =
        [
            'BOOLEAN_MARKERS'    => self::BOOLEAN_MARKERS,
            'SHARED_ROOT_TYPES'  => self::SHARED_ROOT_TYPES,
            'SHARED_LABEL_TYPES' => self::SHARED_LABEL_TYPES,
            'PRIORITY_TYPES'     => self::PRIORITY_TYPES,
            'title_preg'      => $this->title_preg,
            'add_save_title'  => $this->add_save_title,
            'add_save_conent' => $this->add_save_conent,
        ];
    }


    /**
     * システム構成の自動初期化
     * * 1. paths.json に基づく物理パスの解決と確定（Genetic Layer）
     * 2. シングルトンインスタンスの生成による基本設定のロード
     * 3. 外部ファイル（PHP/JSON）およびディレクトリ（スキャン）からのデータ統合
     * を一括で行い、クラスを即時利用可能な状態にする。
     */
    private static function init() {
        // --- 手順 A: まず paths.json を読み込んで static 変数を埋める ---
        $json_path = get_stylesheet_directory() . '/data/json/paths.json';

        if (file_exists($json_path)) {
            $data = json_decode(file_get_contents($json_path), true);

            $base_dir = get_stylesheet_directory();

            // ここで self::$paths を確定させる
            self::$paths = array_map(function($path) use ($base_dir) {
                return str_replace('BASE_DIR', $base_dir, $path);
            }, $data);
        }

        // --- 手順 B: その後、インスタンス化とデータロードを行う ---
        if (self::$instance === null) {
            self::$instance = new self();

            $PhpDir = self::get_path('php');

            // 2. 外部PHP設定ファイルの読み込み。configのみ。
            $config_path = $PhpDir . '/config.php';
            if (file_exists($config_path)) {
                $extra_settings = require $config_path;
                if (is_array($extra_settings)) {
                    // 既存のsettingsにマージ
                    self::$settings = array_merge(self::$settings, $extra_settings);
                }
            }


            //phpファイル読み込み。
            self::load_PHP_Data('ContentProcessor', $PhpDir . "/ContentProcessor.php");


            // 手順Aが終わっているので、ここでは get_path('json') が値を返します
            $jsonDir = self::get_path('json');

            self::load_Json_Data('paths',                  $jsonDir . "/paths.json");
            self::load_Json_Data('visual_config',          $jsonDir . '/visual_config.json');
            self::load_Json_Data('title_prefix_map',       $jsonDir . '/title_prefix_map.json');
            self::load_Json_Data('identifier_schema',      $jsonDir . '/identifier_schema.json');
            self::load_Json_Data('system_internal_schema', $jsonDir . '/system_internal_schema.json');
            self::load_Json_Data('consolidator',           $jsonDir . '/consolidator.json');
            self::load_Json_Data('text-processor',         $jsonDir . '/text-processor.json');


            // 2. ディレクトリ自動スキャン・ロードの追加
            self::load_Directory_Json('wpd_characters', $jsonDir . '/wpd_characters');
            self::load_Directory_Json('wpd_works',      $jsonDir . '/wpd_works');
        }
    }



    /**
     * 設定値の取得
     * * self::init() を介してシステムの初期化を保証した上で、
     * 指定されたキーに紐付く設定値（配列または文字列）を返す。
     * * @param string $key 設定キー名
     * @return mixed 該当する設定値。存在しない場合は空文字。
     */
    public static function get($key) {
        self::init(); // ← ここで初期化してから取り出す
        return self::$settings[$key] ?? '';
    }


    /**
     * 指定したキーのパスを取得
     */
    public static function get_path($key, $default = '') {
        self::init(); // ← ここで初期化してから取り出す
        return self::$paths[$key] ?? $default;
    }


    /**
     * JSONファイルを読み込み設定に統合する共通メソッド
     *
     * @param string $key 設定キー (例: 'characters')
     * @param string $filePath JSONファイルのパス
     */
    private static function load_Json_Data($key, $filePath) {
        if (file_exists($filePath))
        {
            $data = self::json_arr($filePath);

            // データが配列の場合のみ設定に統合
            if (is_array($data))
            {
                self::$settings[$key] = $data;
            } else {
                // JSONデコードエラーハンドリング
                KxMessage::error("JSON デコードに失敗しました: " . $filePath);

                error_log("JSON デコードに失敗しました: " . $filePath);
            }
        }
        else
        {
            // ファイルが見つからない場合のエラーハンドリング
            KxMessage::error("JSON ファイルが見つかりません: " . $filePath);
            error_log("JSON ファイルが見つかりません: " . $filePath);
        }
    }


    /**
     * PHPファイル（配列を返すもの）を読み込み、settingsに統合する
     * @param string $key  統合後のキー名
     * @param string $path ファイルパス
     */
    private static function load_PHP_Data($key, $path) {
        if (file_exists($path)) {
            // ファイルを読み込み（ContentProcessor.php は配列を return している前提）
            $data = include $path;
            if (is_array($data)) {
                self::$settings[$key] = $data;
            }
        }
    }




    /**
     * キャラクターデータの構造を旧形式(#1)から新形式(#2)に変換する
     * 余計な階層を作らず、シリーズキー直下にフラットに展開する
     */
    private static function convert_character_structure($old_data) {
        $new_data = [];

        foreach ($old_data as $series_key => $series_content) {
            // シリーズキーの正規化（小文字化）
            $normalized_series_key = mb_strtolower($series_key);

            $converted_series = [];

            if (is_array($series_content)) {
                foreach ($series_content as $key => $raw) {

                    // 1. 特殊設定（set, sample等）はそのまま保持
                    if (in_array($key, ['set', 'info', 'sample', 'full', 'full_plus'])) {
                        $converted_series[$key] = $raw;
                        continue;
                    }

                    // 2. すでに連想配列（新形式）ならそのまま保持
                    if (isset($raw['name'])) {
                        $converted_series[$key] = $raw;
                        continue;
                    }

                    // 3. 旧形式（数値添え字配列）を連想配列に変換
                    if (is_array($raw) && isset($raw[0])) {
                        // 学歴データの解析と構造化
                        $edu_raw = isset($raw[3]) ? explode(',', $raw[3]) : [];
                        $education = [
                            'start'    => isset($edu_raw[0]) ? (int)$edu_raw[0] : null, // 開始年齢
                            'end'      => isset($edu_raw[1]) ? (int)$edu_raw[1] : null, // 終了年齢
                            'country'  => isset($edu_raw[2]) ? (int)$edu_raw[2] : 'JP',    // 入学月（デフォルト4月）
                        ];

                        $converted_series[$key] = [
                            'name'       => $raw[0],
                            'age_diff'   => $raw[2] ?? null,
                            'education'  => $education, // 構造化した学歴を格納
                            'short_name' => $raw[4] ?? null,
                            'info'       => $raw[5] ?? null,
                        ];

                        // 教育情報の数値キャスト
                        $converted_series[$key]['education'] = array_map(function($v) {
                            return is_numeric($v) ? (int)$v : $v;
                        }, $converted_series[$key]['education']);
                    } else {
                        // それ以外の形式（不明なメタデータ等）もそのまま保持
                        $converted_series[$key] = $raw;
                    }
                }
            }

            // シリーズキーの直下に変換後の内容を格納
            $new_data[$normalized_series_key] = $converted_series;
        }

        return $new_data;
    }


    /**
     * 作品データの構造を旧形式から新形式に変換する (wpd_works用)
     */
    private static function convert_work_structure($old_data) {
        $new_data = [];

        foreach ($old_data as $group_key => $group_content) {
            // ksy, ygs 等のグループキーを小文字化
            $normalized_group_key = mb_strtolower($group_key);

            // formatキーは定義体なのでそのまま保持
            if ($normalized_group_key === 'format') {
                $new_data[$normalized_group_key] = $group_content;
                continue;
            }

            $converted_group = [];
            if (is_array($group_content)) {
                foreach ($group_content as $work_id => $raw) {

                    // すでに連想配列ならそのまま
                    if (isset($raw['title'])) {
                        $converted_group[$work_id] = $raw;
                        continue;
                    }

                    // 旧形式（数値添え字配列）を連想配列に変換
                    if (is_array($raw) && isset($raw[0])) {
                        $converted_group[$work_id] = [
                            'title'         => $raw[0],          // 作品名
                            'hero_no'       => $raw[1] ?? null,  // 主人公No
                            'members'       => isset($raw[2]) ? explode(',', $raw[2]) : [], // 対応キャラ
                            'timeline_from' => $raw[3] ?? null,  // 表示時期（以降）
                            'timeline_to'   => $raw[4] ?? null,  // 表示時期（まで）
                            'note'          => $raw[5] ?? null,  // 補足
                            // seriesについてはTitleParser等の判定で後から動的に付与することを想定
                        ];
                    } else {
                        $converted_group[$work_id] = $raw;
                    }
                }
            }
            $new_data[$normalized_group_key] = $converted_group;
        }

        return $new_data;
    }





    /**
     * 指定ディレクトリ内の全JSONをスキャンし、一つのキーに統合する
     * @param string $key        統合後のキー名 (例: 'wpd_characters')
     * @param string $dirPath    対象ディレクトリパス
     */
    private static function load_Directory_Json($key, $dirPath) {




        // 読み取り権限までチェック
        if (!is_dir($dirPath) || !is_readable($dirPath)) {
            return;
        }

        $merged_data = [];
        $files = glob(rtrim($dirPath, '/') . '/*.json');

        // globがfalseを返すケース（権限エラー等）への対処
        if ($files === false) {
            return;
        }

        sort($files); // 読み込み順序の安定化

        foreach ($files as $file) {
            $data = self::json_arr($file);

            if (is_array($data)) {
                // マージ順序を安定させるため、array_replace_recursiveを活用
                $merged_data = array_replace_recursive($merged_data, $data);
            } elseif ($data === null) {
                // 解析失敗時にエラーログを吐くことで、設定ミスを早期発見できる
                error_log("SystemConfig Error: Invalid JSON structure in " . $file);
            }
        }

        if (!empty($merged_data)) {
            if ($key === 'wpd_characters') {
                $merged_data = self::convert_character_structure($merged_data);
            }
            if ($key === 'wpd_works') {
                $merged_data = self::convert_work_structure($merged_data);
            }

            self::$settings[$key] = $merged_data;
        }
    }



    /**
     * JSONファイルを読み込み、配列に変換する
     * * @param string $file_path JSONファイルのパス
     * @return array|null 変換後の配列（失敗時はnull）
     */
    private static function json_arr( $file_path ) {
        // 1. ファイルの読み込み
        $json = file_get_contents( $file_path );
        if ($json === false) {
            return null;
        }

        // 2. 改行コードの削除
        $json = preg_replace( '/(\r\n|\n|\r)/', '', $json );

        // 3. 文字コードの変換（UTF-8へ）
        $json = mb_convert_encoding( $json, 'UTF-8', 'ASCII,JIS,UTF-8,EUC-JP,SJIS-WIN' );

        // 4. デコードして配列で返す
        return json_decode( $json, true );
    }

}


// 短縮名 Su:: でアクセス可能にする
if (!class_exists('Su')) {
    class_alias(\Kx\Core\SystemConfig::class, 'Su');
}

// 既存の KxSu:: 呼び出し（1.4万件の互換性）を維持する
if (!class_exists('KxSu')) {
    class_alias(\Kx\Core\SystemConfig::class, 'KxSu');
}