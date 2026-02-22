<?php
/**
 * [Path]: inc\core\class-kx-dy-content-handler.php
 */


namespace Kx\Core;

use Su;
use Dy;
//use Kx\Core\DyStorage;

use \Kx\Database\dbkx0_PostSearchMapper as dbkx0;
use \Kx\Database\dbkx1_DataManager as dbkx1;
use \Kx\Database\Hierarchy;
use \Kx\Database\dbKxAiMetadataMapper;

/**
 * DyContentHandler
 * * DynamicRegistryにおけるコンテンツ（raw/ana/vis）の操作に特化したハンドラクラス。
 */
class DyContentHandler {


    /**
     * キャッシュからデータを取得する。存在しない場合は自動的に補充する。
     * * @param int    $post_id    投稿ID
     * @param string $sub_key    特定のサブキーのみ返したい場合に指定
     * @return mixed             指定されたデータ、または content[$post_id] 配列全体
     */
    public static function get_content_cache($post_id, $sub_key = null) {

        if(!$post_id) return;

        $entry = Dy::set_path_index($post_id );

        //echo $post_id;
        //echo '+';

        // 2. キャッシュが存在しない場合は補充
        if (!isset(Dy::get('content')[$post_id])) {

            // 重要：無限ループ防止のため、一時的に空配列を入れて「取得中」であることを示す
            DyStorage::update('content', [ $post_id => ['loading' => true] ]);

            $fetchedData = self::hydrate_id_context($post_id,$entry['wp_type']);

            if (!empty($fetchedData)) {
                DyStorage::update('content', [ $post_id => $fetchedData ]);
            } else {
                // データがない場合も、何度もDBを見に行かないよう null を入れておく
                DyStorage::update('content', [ $post_id => null ]);
            }
        }

        // 3. 値の返却
        $target = Dy::get('content')[$post_id] ?? null;
        // 取得中またはデータなしの場合はそのまま返す
        if (!$target || isset($target['loading'])) return $target;
        if (!$sub_key || !is_array($target)) return $target;

        // --- ここで階層の橋渡しを行う ---
        // 指定されたキーが raw に存在すればそれを返す
        if (isset($target['raw'][$sub_key])) {
            return $target['raw'][$sub_key];
        }

        $ana_map = [
            'time_0'            => ['raw', 'db_kx0', 'time'],
            'shared_json'       => ['raw', 'db_kx_shared', 'json'],
            'shared_date'       => ['raw', 'db_kx_shared', 'date'],
            'alert'             => ['raw', 'db_kx_hierarchy', 'alert'],

            'shared_ids'        => ['ana', 'shared', 'ids'],

            'ancestry'          => ['ana', 'node', 'ancestry'],
            'parent_id'         => ['ana', 'node', 'parent_id'],
            'descendants'       => ['ana', 'node', 'descendants'],
            'ai_score_deviation'  => ['ana', 'node', 'ai_score_deviation'],
            'ai_score'          => ['ana', 'node', 'ai_score'],
            'ai_score_stat'     => ['ana', 'node', 'ai_score_stat'],
            'ai_score_context'  => ['ana', 'node', 'ai_score_context'],
            'top_keywords'      => ['ana', 'node', 'top_keywords'],

            'virtual_descendants' => ['ana', 'node', 'virtual_descendants'],
        ];

        $dbkx1_mapping_json = Su::get('system_internal_schema')['dbkx1_ana_control_mapping_json'];
        foreach ($dbkx1_mapping_json as $json_key => $target_key) {

            $ana_map[$target_key] = ['ana', 'control', $target_key];
        }

        $dbkx1_mapping_columns = Su::get('system_internal_schema')['dbkx1_ana_control_mapping_columns'];
        foreach ($dbkx1_mapping_columns as $columns_key => $target_key) {
            // ana_mapに ['ana', 'control', 'ターゲットキー名'] の形式で追記
            $ana_map[$target_key] = ['ana', 'control', $target_key];
        }




        // 2階層構造 (visレイヤー)
        $vis_map = [
            'colormgr_id' => ['vis', 'atlas'],
        ];

        // 1. anaレイヤーの解決 (3段階参照)
        if (isset($ana_map[$sub_key])) {
            $m = $ana_map[$sub_key];
            return $target[$m[0]][$m[1]][$m[2]] ?? null;
        }

        // 2. visレイヤーの解決 (2段階参照)
        if (isset($vis_map[$sub_key])) {
            $m = $vis_map[$sub_key];
            return $target[$m[0]][$m[1]] ?? null;
        }

        // 3. 直接参照（または以前のルートキーへの互換性）
        return $target[$sub_key] ?? null;
    }


    /**
     * 【Setter型：ピンポイント注入】
     * 多階層（content > id > sub_key）の整合性を維持しながら部分更新を行う。
     *
     * @param int|string $post_id 投稿ID
     * @param string     $sub_key 保存先のレイヤー名または物理キー名
     * @param mixed      $data    注入する実データ
     */
    public static function set_content_cache($post_id, $sub_key, $data) {
        // 1. 現在の対象IDのデータを取得（なければデフォルトの三層構造を作成）
        // contentドメインから特定のIDのデータセットを抽出
        $content_all = Dy::get('content') ?: [];
        $item = $content_all[$post_id] ?? [
            'raw' => [],
            'ana' => [],
            'vis' => []
        ];

        // 物理キー（rawレイヤー直下に入るべき項目）の定義
        $physical_keys = ['db_kx0', 'db_kx1', 'db_kx_hierarchy','db_kx_ai', 'db_kx_shared'];

        // 2. 加工ロジックの適用
        // パターンA: 直接的な物理キー名（db_kx0等）が指定された場合 -> raw 配下へ
        if (in_array($sub_key, $physical_keys)) {
            $item['raw'][$sub_key] = $data;
        }
        // パターンB: 'raw' レイヤー全体へのマージ
        elseif ($sub_key === 'raw' && is_array($data)) {
            $item['raw'] = array_replace(
                (array)($item['raw'] ?? []),
                $data
            );
        }
        // パターンC: それ以外（ana, vis 階層への直接セット、または個別のレイヤー更新）
        else {
            $item[$sub_key] = $data;
        }

        // 3. 最終的なデータを DyStorage に戻す（このIDのスロットだけを更新）
        // 'content' ドメイン内の $post_id キーに対して $item をマッピング
        DyStorage::update('content', [ $post_id => $item ]);
    }



    /**
     * キャッシュ内の raw データのみをピンポイントで取得する。
     * 自動補充 (hydrate) を行わないため、sync処理中などの Dirty Check に適している。
     */
    public static function get_content_raw_cache($post_id, $sub_key) {

        // 補充はせず、今あるメモリ上のデータだけを見る
        if (isset(Dy::get('content')[$post_id]['raw'][$sub_key])) {
            return Dy::get('content')[$post_id]['raw'][$sub_key];
        }

        return null;
    }




    /**
     * 【Force Sync型：強制同期・再構成】
     * 内部構造を DyStorage ベースに刷新。
     * メモリ上のキャッシュの有無に関わらず、DBから最新値を読み直し、
     * 特定の投稿IDスロットを最新の $fetchedData で完全に置き換える。
     *
     * @param int $post_id 投稿ID
     * @return array|null 取得された最新データ
     */
    public static function set_content_refresh($post_id) {
        // self::init() は不要なため削除

        if (!$post_id) return null;

        $post_id = (int)$post_id;

        // DBから最新値を読み直す
        $fetchedData = self::hydrate_id_context($post_id);

        if (!empty($fetchedData)) {
            // DyStorage::update を使用して、この投稿IDのデータを最新値で上書き
            // ※ $post_id => $fetchedData という形式なので、このIDの中身が丸ごと差し替わる
            DyStorage::update('content', [ $post_id => $fetchedData ]);
        }

        return $fetchedData;
    }



    /**
     * 【Lazy Load型：存在チェック付きロード】
     * 指定されたIDのデータがメモリ上に「存在しない場合のみ」一括構築を行う。
     * 未ロードの記事を安全に初期化するための、高コスト処理（DBフェッチ/解析）の入り口。
     *
     * @param int    $post_id 投稿ID
     * @param string $type    記事種別（strat_root, Μ, σ 等）
     */
    public static function set_content_page($post_id, $type) {
        if (!$post_id) return;

        // 1. path_indexの確保（未取得なら取得を実行）
        // ※ Dyクラス側のパスインデックス管理機能に委譲
        Dy::set_path_index($post_id);

        // 2. 二重ロード防止：既に content ドメインに該当IDが存在するか確認
        $content_all = Dy::get('content') ?: [];
        if (isset($content_all[$post_id])) {
            return; // 既にロード済みの場合はスキップ
        }

        // 3. 重いDB・解析処理（Hydration）を実行
        // ※ 実際のフェッチロジックは Dy::hydrate_id_context を呼び出す
        $fetchedData = self::hydrate_id_context($post_id, $type);

        if (!empty($fetchedData)) {
            // 4. 三層構造（raw/ana/vis）を content レイヤーとして格納
            DyStorage::update('content', [ $post_id => $fetchedData ]);
        }
    }


    /**
     * 1.コンテンツキャッシュの空スキーマ（初期構造）を生成する
     * * [役割]: 全ての投稿データがこの構造を起点とすることで、
     * 未取得データへのアクセスによる PHP Warning を物理的に排除する。
     */
    private static function get_initial_content_schema() {
        return [
            // 物理層：DBの各テーブルからフェッチした生データ
            'raw' => [
                'db_kx0'          => null, // 投稿本文・基本属性
                'db_kx1'          => null, // 制御フラグ（GhostON等）
                'db_kx_hierarchy' => null, // 階層・家系図データ
                'db_kx_ai'        => null,
                'db_kx_shared'    => null, // 概念相関
                //'db_kx_works'     => null, // 作品固有データ
            ],
            // 論理層：システムによる「意味」の解析結果
            'ana' => [
                'node' => [
                    'parent'      => null,  // 親のタイトルパス
                    'parent_id'   => null,  // 実在する親のPostID
                    'is_folder'   => false, // 子孫が存在するか
                    'is_virtual'  => false, // 実体なき概念階層か
                    'descendants' => null,  // 子要素IDリスト
                    'virtual_descendants' => null,  // virtualの子要素
                    'warning'     => null,  // 整合性アラート
                ],
                'attr' => [
                    'series_id'  => null, // シリーズ番号 (例: 10)
                    'chara_id'   => null, // キャラクター番号 (例: 001)
                    'work_code'  => null, // 作品コード (例: ksy)
                    'work_id'    => null, // 作品番号 (例: 022)
                ],
            ],
            // 表示層：ColorManager等による装飾データ
            'vis' => [
                'atlas'  => null, // 色彩識別子
            ],
        ];
    }

    /**
     * ポストIDに基づく全レイヤーデータの一括取得とキャッシュ注入
     * 調整：raretuフラグを無視し、子孫の有無でフォルダ判定を行う
     */
    private static function hydrate_id_context($post_id, $type = 'post') {

        // 1. スロットの整理：物理データ(raw)と、システム解析用(暫定ルート)を明確に分ける
        $_array = self::get_initial_content_schema(); // スキーマの初期化を分離

        // 1b. --- page タイプの処理も新構造の $_array を引き継ぐ ---
        if( $type === 'page') {
            self::apply_visual_layer($post_id, $_array);
            return $_array;
        }

        // 2. 物理層のフェッチ（追加した関数を呼び出す）
        self::fetch_raw_db_layers($post_id, $_array);
        self::set_content_cache($post_id, 'raw', $_array['raw']);// 保存

        // 3a. kx0 の充填
        self::analyze_kx1_consolidated($post_id, $_array);

        // 3b. 概念・相関層 (shared) の充填
        self::analyze_concept_layer($post_id, $_array);

        // 3c. 論理階層層 (ana) の解析・充填
        self::analyze_hierarchy_layer($post_id, $_array);

        // 3d. AI層層 (ana) の解析・充填
        self::analyze_ai_layer($post_id, $_array);
        self::set_content_cache($post_id, 'ana', $_array['ana']);// 保存

        // 4. 表示装飾層 (vis) の算出・充填
        self::apply_visual_layer($post_id, $_array);
        self::set_content_cache($post_id, 'vis', $_array['vis']);// 保存

        // 5. 制作支援レイヤー (Production) のマッピング
        self::map_production_metadata($post_id, $_array);

        return $_array;
    }


    /**
     * 物理層 (raw) の一括フェッチと初期キャッシュ
     * DBの5つの主要テーブルから生データを取得し、Dyの 'raw' レイヤーに先行保存する
     */
    private static function fetch_raw_db_layers($post_id, &$_array) {

        global $wpdb;
        // --- 1. DBフェッチ実行 ---
        $_array['raw']['db_kx0'] = dbkx0::load_raw_data($post_id);
        $_array['raw']['db_kx1'] = dbkx1::load_raw_data($post_id);
        $_array['raw']['db_kx_hierarchy'] = Hierarchy::load_raw_data($post_id);
        $_array['raw']['db_kx_ai'] = dbKxAiMetadataMapper::load_raw_data($post_id);
        //var_dump($_array['raw']['db_kx_ai']);

        // --- 2. 共通タイトル (Shared/Works検索キー) の一時算出 ---
        $raw_title = $_array['raw']['db_kx0']['title'] ?? '';
        self::get_clean_shared_title($raw_title ,$_array); // 高速クレンジング関数へ
        $clean_title = $_array['ana']['shared']['title'] ?? null;

        // --- 3. 概念・相関層 (shared/works) ---
        if ($clean_title) {
            $shared_row = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM wp_kx_shared_title WHERE title = %s", $clean_title), ARRAY_A
            );
            $_array['raw']['db_kx_shared'] = $shared_row;

            // Worksキャッシュとの同期
            $current_shared = Dy::get('shared') ?: [];
            if (!isset($current_shared[$clean_title])) {
                /*
                $works_row = $wpdb->get_row(
                    $wpdb->prepare("SELECT * FROM wp_kx_works WHERE title = %s", $clean_title), ARRAY_A
                );
                */
                $current_shared[$clean_title] = [
                    'db_kx_shared' => $shared_row ?: [],
                    //'db_kx_works'  => $works_row ?: [],
                ];
                Dy::set('shared', $current_shared);
            }
            //$_array['raw']['db_kx_works'] = $current_shared[$clean_title]['db_kx_works'];
        }

        return;
    }


    /**
     * identifier_schema の定義に基づき、タイトルの接頭辞を高速に除去する
     */
    private static function get_clean_shared_title($title,&$_array) {
        if (empty($title)) return '';
        // 1. スキーマ全体を取得
        $schema = Su::get('identifier_schema');
        // 2. 共通接頭辞の 'shared' リストを取得
        $prefixes = $schema['common_prefixes']['shared'] ?? [];

        if (empty($prefixes)) return '';

        // 3. 先頭1文字を取得 (マルチバイト対応)
        $first_char = mb_substr($title, 0, 1);

        // 4. 接頭辞が含まれていれば、その1文字を削除して返す
        // ※ preg_replace よりも in_array + mb_substr の方が圧倒的に高速です
        if (in_array($first_char, $prefixes, true)) {
            $_array['ana']['shared']['title'] = mb_substr($title, 2);
        }
    }



    /**
     * 3a.kx1レイヤーの解析：統合元(consolidated_from)の抽出
     * * @param int   $post_id     投稿ID
     * @param array &$_array     コンテンツデータ構造
     * @param string $clean_title クレンジング済みタイトル
     */
    private static function analyze_kx1_consolidated($post_id, &$_array) {
        $post_id = (int)$post_id; // 型安全性の徹底

        // raw層からkx1のJSONデータを取得
        $db_kx1 = $_array['raw']['db_kx1'] ?? '';

        $maping_columns = Su::get('system_internal_schema')['dbkx1_ana_control_mapping_columns'];
            foreach ($maping_columns as $key) {
                if (!empty($db_kx1[$key])) {
                    $_array['ana']['control'][$key] = $db_kx1[$key];
                }
            }

        if (!empty($db_kx1['json'])) {

            $json_data = is_array($db_kx1['json']) ? $db_kx1['json'] : json_decode($db_kx1['json'], true);

            // "consolidated_from" が存在する場合、anaレイヤーへ格納
            $maping_json = Su::get('system_internal_schema')['dbkx1_ana_control_mapping_json'];

            foreach ($maping_json as $key) {
                if (isset($json_data[$key])) {
                    $_array['ana']['control'][$key] = $json_data[$key];
                }
            }
        }
    }

    /**
     * 3b. 概念・相関層 (shared/works) の解析
     * raw層のDBデータに基づき、論理層 (ana) へ意味をマッピングしキャッシュする
     */
    private static function analyze_concept_layer($post_id, &$_array) {
        $clean_title = $_array['ana']['shared']['title'] ?? null;

        // 1. 検索キー(clean_title)がない場合は解析不能として終了
        if (empty($clean_title)) {
            return;
        }

        // --- ana 層の初期化 (sharedスロットを確保) ---
        if (!isset($_array['ana']['shared'])) {
            $_array['ana']['shared'] = [
                'title' => $clean_title,
                'ids'   => [],
            ];
        }

        // 2. Sharedデータの解析 (rawからanaへ抽出)
        $db_shared = $_array['raw']['db_kx_shared'] ?? null;
        if ($db_shared) {
            // 型変換を行いながら、論理層の ids にマッピング
            $_array['ana']['shared']['ids'] = array_filter([
                'lesson' => isset($db_shared['id_lesson']) ? (int)$db_shared['id_lesson'] : null,
                'sens'   => isset($db_shared['id_sens'])   ? (int)$db_shared['id_sens']   : null,
                'study'  => isset($db_shared['id_study'])  ? (int)$db_shared['id_study']  : null,
                'data'   => isset($db_shared['id_data'])   ? (int)$db_shared['id_data']   : null
            ]);
        }

        // 4. 解析完了した ana レイヤーをキャッシュに保存
        self::set_content_cache($post_id, 'ana', $_array['ana']);

    }


    /**
     * 4.論理階層層 (ana) の解析・充填
     * db_kx_hierarchy の JSON を解析し、親IDの特定やフォルダ判定を行う。
     */
    private static function analyze_hierarchy_layer($post_id, &$_array) {
        $h_row = $_array['raw']['db_kx_hierarchy'] ?? null;
        if (!$h_row) {
            return;
        }

        // 基本情報の転記
        $_array['ana']['node']['parent'] = $h_row['parent_path'] ?? null;

        if (empty($h_row['json'])) {
            return;
        }

        $h_js = json_decode($h_row['json'], true);
        if (!is_array($h_js)) {
            return;
        }

        // --- 1. 親IDの解決 (Ancestry Analysis) ---
        if (!empty($h_js['ancestry'])) {
            $_array['ana']['node']['ancestry'] = $h_js['ancestry'];
            // 末尾から逆順にスキャンし、'virtual' ではない最初の ID を「実在する親」とする
            $ancestry_rev = array_reverse($h_js['ancestry'], true);
            $real_parent_id = null;

            foreach ($ancestry_rev as $p_id) {
                if ($p_id !== 'virtual' && !empty($p_id)) {
                    $real_parent_id = (int)$p_id;
                    break;
                }
            }

            $_array['ana']['node']['parent_id'] = $real_parent_id;

            // 直近の親が virtual だった場合のデバッグ用アラート
            if (end($h_js['ancestry']) === 'virtual' && $real_parent_id) {
                $_array['ana']['node']['warning'] = "Virtual parent bypassed. Inherited from: {$real_parent_id}";
            }
        }

        // --- 2. 子孫(Descendants Analysis) ---
        $descendants = $h_js['descendants'] ?? [];

        if (!empty($descendants)) {
            $_array['ana']['node']['descendants'] = array_unique($descendants);//array_unique($merged_descendants);
            $_array['ana']['node']['is_folder']   = true; // 子が存在するためフォルダとして扱う
        }

        $descendants = $h_js['virtual_descendants'] ?? [];

        if (!empty($descendants)) {
            $_array['ana']['node']['virtual_descendants'] = array_unique($descendants);//array_unique($merged_descendants);
        }

    }


    /**
     * 3d.AI層層 (ana) の解析・充填
     * db_kx_ai の JSON を解析し、親IDの特定やフォルダ判定を行う。
     */
    private static function analyze_ai_layer($post_id, &$_array) {

        $h_row = $_array['raw']['db_kx_ai'] ?? null;
        if (!$h_row) {
            return;
        }

        // 基本情報の転記
        $_array['ana']['node']['ai_score_stat'] = $h_row['ai_score_stat'] ?? null;
        $_array['ana']['node']['ai_score_context'] = $h_row['ai_score_context'] ?? null;
        $_array['ana']['node']['ai_score'] = $h_row['ai_score'] ?? null;
        $_array['ana']['node']['ai_score_deviation'] = $h_row['ai_score_deviation'] ?? null;

        if (empty($h_row['top_keywords'])) {
            return;
        }

        $h_js = json_decode($h_row['top_keywords'], true);

        $_array['ana']['node']['top_keywords'] = $h_js ?? [];
    }

    /**
     * 5. 表示装飾層 (vis) の算出・充填
     * タイトル、色相(Hue)、CSS変数、装飾クラスなどを確定させる。
     */
    private static function apply_visual_layer($post_id, &$_array) {
        // 1. タイトル情報の確定（path_index の更新と ana への反映を含む）
        // 内部で get_title() を呼び、path_index にキャッシュしつつ $_array を更新する既存の仕組みを活用
        //self::kxdy_title($post_id, $_array);

        // 2. ビジュアル（色・スタイル）の解決
        // 解析済みのタイトルを使用して ColorManager から装飾セットを取得
        $current_title = Dy::get_title($post_id);

        // ColorManager_ID を内部的に呼び出し、$_array['vis'] を充填する
        // ※ ColorManager_ID の引数仕様に合わせて調整
        self::ColorManager_ID($post_id, $_array, $current_title);
    }



    /**
     * 6. 制作支援レイヤー (Production) のマッピング（高速化版）
     */
    private static function map_production_metadata($post_id, &$_array) {
        $path_index = Dy::get_path_index($post_id);
        if (!isset($path_index)) return;

        $info = $path_index;
        $type = $info['type'];
        $genre = $info['genre'];
        $parts = $info['parts'];
        $markers = $info['markers'];

        if (empty($parts)) return;

        // 現在の共有キャッシュを取得
        $_production = Dy::get('prod_work_production') ?: [
            'series' => [],
            'PublishedWorks' => [],
            'archive' => []
        ];

        $update_payload = [];
        $update_flag = false;

        // 6A. prod_character_core 属性の処理 (既存チェック込み)
        if (isset($markers['prod_character']) && $markers['prod_character'] == 1) {
            // すでにこのキャラクタ情報がキャッシュにあるか確認
            $series_code = $parts[0];
            $chara_id = (isset($parts[1]) && mb_strpos($parts[1], 'c') === 0) ? mb_substr($parts[1], 1) : null;

            // キャッシュに未存在の場合のみ、ハンドラを動かす
            if (!isset($_production['series'][$series_code]['characters'][$chara_id])) {
                if (self::handle_prod_character_core($parts, $update_payload)) {
                    $update_flag = true;
                }
            }
        }

        // 6B. 型別の個別処理 (既存チェック込み)
        switch ($genre) {
            case 'prod_work_productions':
            case 'prod_work_production_log':
                $code_part = mb_substr($parts[2], 0, 3);
                $num_part  = mb_substr($parts[2], 3);
                // PublishedWorks に未存在の場合のみ処理
                if (!isset($_production['PublishedWorks'][$code_part][$num_part])) {
                    if (self::handle_type_prod_work_production($parts, $update_payload)) {
                        $update_flag = true;
                    }
                }
                break;

            case 'prod_character_relation':
                // 相関データも同様にチェック
                if (self::handle_type_prod_character_relation($parts, $update_payload)) {
                    $update_flag = true;
                }
                break;
        }

        // 6C. 反映（新しいデータがある場合のみ実行）
        if ($update_flag && !empty($update_payload)) {
            $_production = array_replace_recursive($_production, $update_payload);
            Dy::set('prod_work_production', $_production);

            // path_index の解析結果から ID だけを抽出してセット
            $path_info = Dy::get_path_index($post_id) ?? null;
            if ($path_info) {
                $parts = $path_info['parts'];
                // シリーズ: ∬10
                $_array['ana']['attr']['series_id'] = $parts[0];

                // キャラクター: c001 -> 001
                if (isset($parts[1]) && mb_strpos($parts[1], 'c') === 0) {
                    $_array['ana']['attr']['chara_id'] = mb_substr($parts[1], 1);
                }

                // 作品: Ksy022 -> work_code: ksy, work_id: 022
                if ($path_info['type'] === 'prod_work_production' && isset($parts[2])) {
                    $_array['ana']['attr']['work_code'] = strtolower(mb_substr($parts[2], 0, 3));
                    $_array['ana']['attr']['work_id']   = mb_substr($parts[2], 3);
                }
            }
        }
    }



    /**
     * 6A. prod_character_core属性の抽出
     */
    private static function handle_prod_character_core($parts, &$update_payload) {
        $series_code = isset($parts[0]) ? strtolower($parts[0]) : null;
        $p1 = strtolower($parts[1] ?? '');
        $chara_id = (mb_strpos($p1, 'c') === 0) ? mb_substr($p1, 1) : null;
        $master_chars = Su::get('wpd_characters');

        if ($series_code && $chara_id && isset($master_chars[$series_code][$chara_id])) {
            $update_payload['series'][$series_code]['characters'][$chara_id] = $master_chars[$series_code][$chara_id];
            return true;
        }
        return false;
    }



    /**
     * 6B1. 型判定：prod_work_production (制作作品)
     */
    private static function handle_type_prod_work_production($parts, &$update_payload) {

        if (!isset($parts[2]) || mb_strlen($parts[2]) <= 3) return false;

        $series_code = strtolower($parts[0]);
        $code_part   = strtolower(mb_substr($parts[2], 0, 3));
        $num_part    = mb_substr($parts[2], 3);

        if (!ctype_alpha($code_part)) return false;

        $master_works = Su::get('wpd_works');
        $work_data = ['series' => $series_code];

        if (isset($master_works[$code_part][$num_part])) {
            $work_data = array_replace_recursive($work_data, $master_works[$code_part][$num_part]);
        }
        $update_payload['PublishedWorks'][$code_part][$num_part] = $work_data;
        return true;
    }

    /**
     * 6B2. 型判定：prod_character_relation (キャラクター相関)
     */
    private static function handle_type_prod_character_relation($parts, &$update_payload) {
        if (!isset($parts[2])) return false;

        $series_code = strtolower($parts[0]);
        $raw_val = strtolower($parts[2]);
        $target_chara_id = str_replace(['＼c', 'c'], '', $raw_val);
        $master_chars = Su::get('wpd_characters');

        if (isset($master_chars[$series_code][$target_chara_id])) {
            $update_payload['series'][$series_code]['characters'][$target_chara_id] = $master_chars[$series_code][$target_chara_id];
            return true;
        }
        return false;
    }




    /**
     * ポストIDに基づき装飾情報を生成し、個体キャッシュとシステム全体キャッシュの両方に登録する。
     * * 実行内容:
     * 1. ColorManager を通じて 'std' タイプの色彩・スタイル情報を解決する。
     * 2. 渡された参照配列（$_array['vis']）に、atlas(ID), paint(CSS変数), traits(クラス) を格納する。
     * 3. システム全体の 'color_mgr' レジストリに、colormgr_id をキーとして装飾セットを保存する。
     * これにより、同一リクエスト内で同じ装飾セットを再利用可能にする。
     *
     * @param int   $post_id 対象のポストID
     * @param array $__array self::get('content')[$id] への参照配列
     * @return void
     */
    private static function ColorManager_ID($post_id, &$_array) {
        $mgr = ColorManager::get_by_id($post_id, 'std');

        if ($mgr && isset($mgr['colormgr_id'])) {
            // 新構造の vis レイヤーへ格納
            $_array['vis']['atlas']  = $mgr['colormgr_id'];

            // 共有メソッドでシステム全体キャッシュに登録
            Dy::register_to_color_mgr_cache($mgr);
        }
    }


}