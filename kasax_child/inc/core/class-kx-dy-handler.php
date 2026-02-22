<?php
/**
 * [Path]: inc\core\class-kx-dy-handler.php
 * DyDomainHandler::: 各ドメインロジックの基底抽象クラス
 */

namespace Kx\Core;

use Su;
use Dy;

use \Kx\Database\dbkx0_PostSearchMapper as dbkx0;
//use \Kx\Database\dbkx1_DataManager as dbkx1;
//use \Kx\Database\Hierarchy;

use \Kx\Utils\KxMessage as Msg;

//use Kx\Core\SystemConfig as Su;

abstract class DyDomainHandler {

    /**
     * システムステータスの取得（安全なデフォルト値付き）
     */
    public static function get_system($key = null) {
        $data = Dy::get('system');

        // integrityキーがない＝まだ正しくセットアップされていないと判断する
        if (empty($data) || !isset($data['integrity'])) {
            $data = self::set_system();
        }

        if ($key === null) return $data;

        switch ($key) {
            case 'is_synced':
                return $data['integrity']['is_synced'] ?? false;
            case 'integrity':
                return $data['integrity'] ?? ['is_synced' => false, 'counts' => []];
            case 'laravel_online':
                return $data['laravel_online'] ?? false;
            case 'updated_at':
                return $data['updated_at'] ?? '-';
            default:
                return $data[$key] ?? null;
        }
    }
    /**
     * システム全体のステータス（整合性等）をセット
     */
    public static function set_system() {

        $cache = Dy::get('system');
        if ($cache) return $cache;

        global $wpdb;

        // WP標準の投稿（公開済みのみ）をカウント
        $count_wp = (int) $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->posts}
            WHERE post_type = 'post'
            AND post_status = 'publish'
        ");

        // 1. 各テーブルのカウント取得（実体ノードの検証）
        // hierarchy: 実体ノード (is_virtual = 0)
        $count_h = (int) $wpdb->get_var("SELECT COUNT(*) FROM wp_kx_hierarchy WHERE is_virtual = 0");
        // kx0: 基本レコード
        $count_0 = (int) $wpdb->get_var("SELECT COUNT(*) FROM wp_kx_0");


        // 2. 整合性チェック (三者が一致しているか)
        $is_synced = ($count_h === $count_0 && $count_0 === $count_wp);

        // 3. データ構造の構築
        $data = [
            'laravel_online' => LaravelClient::is_laravel_online(),
            'integrity' => [
                'is_synced' => $is_synced, // 是非
                'counts' => [
                    'wp'            => $count_wp,
                    'kx0'            => $count_0,
                    'hierarchy_real' => $count_h,
                ],
                'diff' => [
                    'h_vs_0' => $count_h - $count_0,
                    '0_vs_wp' => $count_0 - $count_wp
                ]
            ],
            'updated_at' => current_time('mysql')
        ];

        //var_dump($data);
        //echo '++';

        Dy::set('system', $data);
        return $data;
    }


    /**
     * ポストIDからタイトルを取得する（キャッシュ・ファースト）
     * 1. path_index (最速メモリキャッシュ) を確認
     * 2. なければ 独自DB (kx_0) から高速取得
     * 3. それでもなければ WP標準から取得
     * * ※ 同時に path_index に full, parts, depth をキャッシュする
     *
     * @param mixed $post_id 投稿ID (int または idを含むarray)
     * @return string タイトル文字列（失敗時は空文字）
     */
    public static function get_title($post_id) {

        // --- 0. 型ガード：配列で渡された場合の救済と整数化 ---
        if (is_array($post_id)) {
            $post_id = $post_id['id'] ?? $post_id[0] ?? null;
        }
        $post_id = ($post_id !== null) ? (int)$post_id : 0;

        if ($post_id <= 0) {
            //echo get_the_title();
            Msg::error( "Dy：get_title：Error：ID無し:ID：[$post_id]");
            return '';
        }

        // --- A. path_index からの取得を試みる ---
        $path_index = Dy::set_path_index($post_id) ?: [];


        if (isset($path_index['full'])) {
            return $path_index['full'];
        }

        // --- B. 独自DB (kx_0) からの取得を試みる ---
        $title = dbkx0::get_title($post_id);

        // --- C. 最終救済措置：WP標準から直接取得 ---
        if (!$title) {
            Msg::error( "Dy：get_title：Error：path_index無し：[$post_id]");
            $title = get_the_title($post_id);
        }

        if (!$title) {
            Msg::error( "Dy：get_title：Error：post_idに該当なし");
            return '';
        }
        return $title;
    }


    /**
     * キャッシュから特定の投稿に関連付けられたカラーマネージャー情報を取得する
     * 内部構造を DyStorage ベースに刷新。
     *
     * @param int $post_id 投稿ID
     * @param string $type 取得タイプ（デフォルト: 'std'）
     * @return array|null カラー情報配列、または見つからない場合はnull
     */
    public static function get_color_mgr($post_id, $type = 'std') {
        $post_id = (int)$post_id;
        if (!$post_id) return null;

        // 1. 安全な Getter を経由して atlas_id を取得
        // すでに多重ネストしている場合はここで null が返るはず
        $atlas_id = Dy::get_content_cache($post_id, ($type === 'std' ? 'atlas' : $type));

        // 2. 図鑑 (color_mgr) から取得を試みる
        if ($atlas_id) {
            $_all_mgrs = Dy::get('color_mgr');
            if (isset($_all_mgrs[$atlas_id])) {
                return $_all_mgrs[$atlas_id];
            }
        }

        // 3. 生成と登録
        $mgr = \Kx\Core\ColorManager::get_by_id($post_id, $type);
        if ($mgr) {
            // 図鑑に登録
            self::register_to_color_mgr_cache($mgr);

            // --- 重要：content 側の保存処理を Setter に委ねる ---
            // 直接 DyStorage::update を叩かず、構造を知っている set_content_cache を使う
            $target_key = ($type === 'std') ? 'atlas' : $type;

            // vis レイヤーに colormgr_id を注入
            Dy::set_content_cache($post_id, 'vis', [ $target_key => $mgr['colormgr_id'] ]);
        }

        return $mgr;
    }

    /**
     * 生成された ColorManager のデータをシステムキャッシュ(color_mgr)に登録する
     * * @param array $mgr ColorManager::get_by_id から返された配列
     * @return void
     */
    public static function register_to_color_mgr_cache($mgr) {
        if (!$mgr || !isset($mgr['colormgr_id'])) {
            return;
        }

        $_color_mgr = Dy::get('color_mgr') ?: [];

        // すでに同じ colormgr_id（例: 200midnight_kx30）があれば登録をスキップ
        if (!isset($_color_mgr[$mgr['colormgr_id']])) {
            $_color_mgr[$mgr['colormgr_id']] = $mgr;
            Dy::set('color_mgr', $_color_mgr);
        }
    }

    /**
     * 特定のPostIDのフラグ状態を取得する
     * @param int $post_id
     * @param string|null $key 特定のフラグ名。nullの場合はそのIDの全フラグを返す。
     * @return mixed 1 | null | array
     */
    public static function get_flags($post_id, $key = null) {
        $post_id = (int)$post_id;

        // get() 内部で init() が走るので安全
        $_flags = Dy::get('flags') ?: [];

        if (!isset($_flags[$post_id])) {
            return null;
        }

        if ($key) {
            return $_flags[$post_id][$key] ?? null;
        }

        return $_flags[$post_id];
    }
    /**
     * 特定のPostIDに対して実行時の動的フラグ（または任意のデータ）をセットする
     * * [運用ルール]
     * ・セット時は $value を指定可能。省略した場合は 1 がセットされる。
     * ・判定時は PHP の「ゆるい比較」を利用することを推奨。
     * 奨: if (KxDy::get_flags($id, 'rendering')) { ... }
     * ・フラグを削除する場合は、unset を推奨（PHPDoc参照）。
     * * @param int    $post_id 投稿ID
     * @param string $key     フラグ名・キー名
     * @param mixed  $value   格納する値（デフォルトは 1）
     * @return void
     */
    public static function set_flags($post_id, $key, $value = 1) {
        $current = Dy::get('flags', $post_id) ?: [];
        $current[$key] = $value;
        DyStorage::update('flags', [ $post_id => $current ]);
    }
    /**
     * 特定のPostIDに紐づくフラグ（またはデータ）を完全に削除する
     * * 0 を代入するのではなく、配列の要素自体を削除(unset)することで
     * メモリを解放し、isset() 等の判定を初期状態に戻します。
     *
     * @param int    $post_id 投稿ID
     * @param string $key     削除したいフラグ名
     * @return bool  削除に成功した（要素が存在していた）場合は true
     */
    public static function unset_flags($post_id, $key) {
        $post_id = (int)$post_id;
        if (!$post_id) return false;

        $_flags = Dy::get('flags') ?: [];

        if (isset($_flags[$post_id][$key])) {
            unset($_flags[$post_id][$key]);

            // その投稿のフラグが空になったら、投稿IDのキー自体も掃除する（オプション）
            if (empty($_flags[$post_id])) {
                unset($_flags[$post_id]);
            }

            Dy::set('flags', $_flags);
            return true;
        }

        return false;
    }

    /**
     * アウトライン情報の取得
     * @param int $post_id 投稿ID
     * @return array 構造化されたデータ（なければ初期値を返す）
     */
    public static function get_outline($post_id) {
        // 1. デフォルト値の定義
        $default = [
            'stack'        => [],
            'processed'    => [],
            'prefix_count' => 1,
            'id'           => 0,
        ];

        // 2. 基本的なバリデーション（IDがない、またはデータが配列でない場合はすぐ返す）
        $data = Dy::get('outline');
        if (!$post_id || !is_array($data)) {
            return $default;
        }

        // 3. 指定された ID のデータを取得（存在しない場合は null を考慮）
        $post_data = $data[$post_id] ?? null;

        if (!$post_data || !is_array($post_data)) {
            return $default;
        }

        // 4. キーが欠落している場合に備えてマージして返す
        return array_merge($default, $post_data);
    }
    /**
     * アウトライン情報の保存
     * @param int $post_id 投稿ID
     * @param array $data ['stack' => [], 'processed' => [], 'prefix_count' => 1] の構造
     */
    public static function set_outline($post_id, $data) {
        $all = Dy::get('outline') ?: [];

        // 数値キーを維持するために直接指定
        $all[(int)$post_id] = $data;

        // Dyの「中身」を丸ごと差し替える
        // [ $post_id => $data ] と包まず、全量配列を渡す
        Dy::set('outline', $all);
    }



    /**
     * 指定されたIDの情報を取得する
     * * @param int    $post_id 投稿ID
     * @param string $key     特定のキーのみ取得したい場合に指定
     * @param mixed  $default 値が存在しない場合のデフォルト値
     * @return mixed         配列全体、または特定のキーの値
     */
    public static function get_info($post_id, $key = '', $default = null) {
        if (!$post_id) return $default;

        $info_all = Dy::get('info');
        $my_info = $info_all[$post_id] ?? null;

        if (!$my_info) {
            return $default;
        }

        if ($key === '') {
            return $my_info;
        }

        return $my_info[$key] ?? $default;
    }
    /**
     * 指定されたIDに付随する情報をセットする（上書き・追記）
     * * @param int   $post_id 投稿ID
     * @param array $data    保存したい情報の配列
     * @param bool  $merge   既存のinfoにマージするかどうか（falseならそのIDのinfoを完全上書き）
     * @return void
     */
    public static function set_info($post_id, array $data, $merge = true) {
        if (!$post_id) return;

        $info_all = Dy::get('info') ?: [];

        if ($merge && isset($info_all[$post_id])) {
            // 既存の配列とマージ（新しいデータで上書きしつつ古いデータも残す）
            $info_all[$post_id] = array_merge($info_all[$post_id], $data);
        } else {
            // 新規セット、または完全上書き
            $info_all[$post_id] = $data;
        }

        Dy::set('info', $info_all);
    }



    /**
     * ポストIDから該当するキャラクターの設定値を取得する
     * 'genre' が 'prod_character_relation' の場合は、関係対象のデータを取得する
     *
     * @param int $post_id
     * @return array|null キャラクター情報
     */
    public static function get_character($post_id) {
        // 1. パス解析済みデータ（path_index）を取得
        $entry = Dy::get_path_index($post_id);
        if (!$entry || empty($entry['parts'])) return null;

        $parts = $entry['parts'];
        $genre = $entry['genre'] ?? '';
        $series_key = $parts[0]; // "∬10"


        // 2. 抽出対象の識別子（slug）を決定
        /*
        if ($genre === 'prod_character_relation' && isset($parts[2])) {
            // relationパターンの場合: 3番目のパーツ（＼c1m00）を使用
            // 「＼c」や「c」を取り除いてIDを抽出
            $char_slug = $parts[1];
            $char_no = preg_replace('/[^0-9a-z]/u', '', str_replace('c', '', $char_slug));
        } else {
            // coreパターンの場合: 2番目のパーツ（c001）を使用
            $char_slug = $parts[1] ?? '';
            $char_no = str_replace('c', '', $char_slug);
        }
        */
        $char_slug = $parts[1] ?? '';
        $char_no = str_replace('c', '', $char_slug);

        if (!$char_no) return null;
        //echo $char_no.$series_key;
        // 3. 設定データ全体を取得
        $prod_data = Dy::get('prod_work_production');
        //echo kx_dump($prod_data);
        //var_dump( $prod_data['series'][$series_key]['characters'][$char_no]);

        // 4. 指定されたシリーズ内のキャラクターデータを返却
        return $prod_data['series'][$series_key]['characters'][$char_no] ?? null;
    }



    /**
     * ポストIDから作品設定を取得
     * タイトル構造: ∬10(0) ≫ c103(1) ≫ Ksy022(2)
     * * @param int $post_id
     * @return array|null 作品データに 'full_key' (例: ksy022) を加えた配列
     */
    public static function get_work($post_id) {
        $parts = Dy::get_path_index($post_id, 'parts');
        if (count($parts) < 3) return null;


        $work_slug = $parts[2]; // "Ksy022"

        // アルファベット(ksy)と数字(022)に分解
        if (!preg_match('/^([a-zA-Z]+)([0-9]+)$/', $work_slug, $matches)) {
            return null;
        }


        $prefix  = strtolower($matches[1]); // "ksy"
        $work_no = $matches[2];             // "022"

        $prod_data = Dy::get('prod_work_production');
        $work_data = $prod_data['PublishedWorks'][$prefix][$work_no] ?? null;

        if (!$work_data) return null;

        // 作品識別キー(ksy022)を配列にマージして返却
        $full_key = $prefix . $work_no;
        $final_work_data = ['full_key' => $full_key] + $work_data;

        return $final_work_data;
    }


    /**
     * 特定のPostIDのMatrix設定を取得する
     * * @param int         $post_id 投稿ID
     * @param string|null $key     特定のキー。nullの場合はそのIDの全マトリクスを返す。
     * @return mixed      取得したデータ、存在しない場合は空配列またはnull
     */
    public static function get_matrix($post_id, $key = null) {
        $post_id = (int)$post_id;
        $_matrix = Dy::get('matrix') ?: [];

        if (!isset($_matrix[$post_id])) {
            return $key ? null : [];
        }

        if ($key) {
            return $_matrix[$post_id][$key] ?? null;
        }

        return $_matrix[$post_id];
    }
    /**
     * 特定のPostIDに対してMatrix設定（配列データ）を蓄積・保存する
     * * @param int    $post_id 投稿ID
     * @param string $key     マトリクス内の識別キー（例: 'layout', 'relation'）
     * @param mixed  $data    保存するデータ（配列やオブジェクト等）
     */
    public static function set_matrix($post_id, $key, $data) {
        $post_id = (int)$post_id;
        if (!$post_id) return;

        // get() 経由で init() を担保しつつ、現在のマトリクスを取得
        $_matrix = Dy::get('matrix') ?: [];

        if (!isset($_matrix[$post_id])) {
            $_matrix[$post_id] = [];
        }

        // 指定されたキーにデータを格納
        $_matrix[$post_id][$key] = $data;

        // Dyメインストレージへ書き戻し
        Dy::set('matrix', $_matrix);
    }


    /**
     * 特定の投稿の直下の子孫（次階層の投稿ID）を取得する
     * キャッシュがあればそれを返し、なければ KxQuery で高速検索を行う
     *
     * @param int $post_id 親となる投稿ID
     * @return int[] 子孫投稿のID配列
     */
    public static function get_descendants($post_id) {
        if (!$post_id = (int)$post_id) return [];

        // 1. キャッシュチェック
        $cached_ids = Dy::get_content_cache($post_id, 'descendants');
        if (is_array($cached_ids)) {
            return $cached_ids;
        }

        // 2. パスインデックスを取得して検索条件を構成
        $path_index = Dy::get_path_index($post_id);
        if (!$path_index) return [];

        // 親のパスを前方一致させつつ、階層を一つ深く指定
        $query = new KxQuery([
            'search'     => $path_index['full'], // 親のフルパス（例：≫第1章）
            'title_mode' => 'prefix',            // 前方一致を明示（より安全）
            'depth'      => (int)$path_index['depth'] + 1,
        ]);

        $ids = $query->get_ids();

        // 3. 結果をキャッシュに保存して返却
        // ※ set_content_cache の実装に合わせて適切に保存してください
        // self::set_content_cache($post_id, 'descendants', $ids);

        return $ids;
    }


    /**
     * 作品シリーズと番号からキャラクター属性を取得する（識別子の自動クレンジング付）
     * * @param string|int $series シリーズ識別子 (例: 'Μ', 'Β')
     * @param string|mixed $num  キャラクター番号（'c'や'＼c'が付与されていても自動除去）
     * @return array キャラクター属性配列
     */
    public static function get_char_attr($series, $num) {
        // 1. 識別子のクレンジングと正規化
        // 文字列化してから、先頭の「＼c」または「c」を正規表現で一括除去
        $num_clean = preg_replace('/^(＼c|c)/u', '', (string)$num);

        $series = (string)$series;
        $num    = $num_clean;

        // 2. 実行時キャッシュ（Dy）からの取得：重複計算の禁止
        $domain  = 'prod_work_production';
        $storage = Dy::get($domain) ?? [];

        if (isset($storage[$series][$num])) {
            return $storage[$series][$num];
        }

        // 3. 静的定義（Su）からのフェッチ
        $master_chars = Su::get('wpd_characters');

        // 該当データがない場合はデフォルト値を参照
        $data = $master_chars[$series][$num]
                ?? $master_chars['default']
                ?? [];

        // 4. キャッシュの更新：既存の階層構造を維持してマージ
        if (!isset($storage[$series])) {
            $storage[$series] = [];
        }
        $storage[$series][$num] = $data;
        Dy::set($domain, $storage);

        return $data;
    }




    /**
     * ID配列を走査し、システム上の有効な投稿（公開済み等）のみを抽出する
     * * @param array $ids 投稿IDの配列
     * @return array 有効なIDのみの配列
     */
    public static function validate_ids(array $ids) {
        if (empty($ids)) return [];

        return array_values(array_filter($ids, function($post_id) {
            // get_path_index 内で set_path_index が呼ばれ、'valid' が確定する
            return Dy::get_path_index($post_id, 'valid') === true;
        }));
    }

    /**
     * 指定されたIDがシステム管理下にあるか判定する
     *
     * @param int $post_id
     * @return bool
     */
    public static function is_ID($post_id) {
        if (!$post_id) return false; // IDがなければ存在しない(false)

        // 1. キャッシュ全体を取得
        $path_index = Dy::get_path_index($post_id);

        // 2. キャッシュ内にキーが存在するか直接チェック
        if (isset($path_index)) {
            return true;
        }

        // 3. キャッシュになければDB（二段構えチェック）へ
        return dbkx0::is_id_exists($post_id);
    }


    /**
     * 同期対象の全タイプをマージして取得（メンテナンス用）
     */
    public static function get_shared_sync_types() {
        return array_keys(array_merge(Su::SHARED_ROOT_TYPES, Su::SHARED_LABEL_TYPES));
    }


    /**
    * trace カウントを増減させる
    * @param string $key   カウントしたいキー名 (例: 'kxx_sc_count')
    * @param int    $delta 増分 (1 でプラス、-1 でマイナス)
    */
    public static function trace_count($key, $delta = 1) {
        $_trace = Dy::get('trace');

        if (!is_array($_trace)) { $_trace = []; }

        // もし $delta が「厳密に 0」ならリセット処理にする
        if ($delta === 0) {
            $_trace[$key] = 0;
        } else {
            // 通常の増減処理
            if (!isset($_trace[$key])) {
                $_trace[$key] = 0;
            }
            $_trace[$key] += $delta;
        }

        // マイナスガード
        if (($_trace[$key] ?? 0) < 0) {
            $_trace[$key] = 0;
        }

        Dy::set('trace', $_trace);
        return $_trace[$key];
    }


}