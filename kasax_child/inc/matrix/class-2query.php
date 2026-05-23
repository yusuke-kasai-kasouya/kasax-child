<?php
/**
 * [Path]: inc/core/matrix/class-query.php
 * マトリックス（一覧表示）のデータ抽出ロジックを管理するクラス。
 * パスベースの検索、時系列解析、および外部DBテーブルからの動的取得をサポートする。
 */
namespace Kx\Matrix;

//use Kx\Core\SystemConfig as Su;
use Kx\Core\DynamicRegistry as Dy;
use Kx\Core\KxQuery;
//use Kx\Database\dbkx0_PostSearchMapper as dbkx0;
//use Kx\Core\ContextManager;
use \Kx\Utils\KxMessage as Msg;
//use Kx\Core\TitleParser as Tp;

class Query {
    /** @var array ショートコード属性 */
    private array $atts;

    /** @var int|string 投稿ID */
    private $post_id;

    /** @var array|null 解析されたパスインデックス */
    private ?array $origin_path;

    /** @var string 判定されたコンテキスト */
    private string $context = 'default_list';


    /**
     * Query 構造体
     *
     * @param array $atts ショートコード等から渡される属性配列
     */
    public function __construct($atts) {
        $this->atts = $atts;
        $this->post_id = $atts['post_id'];

        $path_index = Dy::get_path_index($this->atts['post_id']);
        $this->origin_path = $path_index ?? null;
        //echo '+1';

    }

    /**
     * 最終的に表示すべきID群を返す
     */
    public function get_ids() {
        // 1. 直接指定系
        if (!empty($this->atts['ids']) ) {
            return [];
        }

        // 2. 解析実行（結果は $this->context に保存される）
        $this->analyze_context();

        // 3. 保存された context を元に取得
        return $this->fetch_by_context($this->context);
    }

    /**
     * virtualな子をゲット。
     */
    public function get_virtuals() {
        if($this->context !== 'vertical_timeline' && $this->context !== 'default_list') return;

        return Dy::get_content_cache($this->post_id, 'virtual_descendants') ?: [];
    }

    /**
     * 判定されたコンテキストを外部（Orchestrator）へ渡す用
     */
    public function get_context() {
        return $this->context;
    }

    /**
     * コンテキスト（表示モードと取得ロジックの方向性）を解析
     */
    public function analyze_context() {

        // A. 直接DBテーブル指定がある場合（SQLクエリモード）
        if (!empty($this->atts['table'])) {
            $this->context = 'dynamic_table';
            return $this->context;
        }

        // B. パスベースの判定
        $path = $this->origin_path;

        // インデックスがない場合は、基本リストとして扱う
        if (!$path) {
            $this->context = 'default_list';
            Msg::error('Matrix：ERROR：$full_pathがありません');
            return $this->context;
        }

        // 1. ラテ欄・タイムライン型（TV番組表のような横軸あり）
        // 判定：フラグに 'prod_work_production_log' (制作来歴) があるか
        if (!empty($path['markers']['matrix_grid'])) {
            $this->context = 'timetable_matrix';
            return $this->context;
        }

        // 2. 通常型タイムライン（時系列の縦並びリスト）
        // 判定：ジャンルが 'prod_character_relation_log' (相関来歴)
        //      または 'prod_character_core_logs' (基幹来歴) の場合
        $genre = $path['genre'] ?? [];
        if ($genre === 'prod_character_relation_log' ||
            $genre === 'prod_character_core_log') {
            $this->context = 'vertical_timeline';
            return $this->context;
        }
        // 3. デフォルト：通常のリスト表示
        $this->context = 'default_list';
        return $this->context;
    }


    /**
     * コンテキストに基づき、実際に表示すべきPost ID群を抽出する
     *
     * @param string $context analyze_contextで判定された種別
     * @return array IDリスト
     */
    private function fetch_by_context($context) {

        // 1. 特殊コンテキストの早期処理
        if ($context === 'timetable_matrix') {
            return [];
        }

        if ($context === 'dynamic_table') {
            return $this->fetch_by_table_context();
        }


        // 2. 5秒以内のリロード（再検索）チェック
        // Transientを使用して、ユーザーの連続ブラウザ更新を検知
        $transient_key = 'kx_reloaded_' . $this->post_id;
        $is_reloaded   = get_transient($transient_key);

        if ($is_reloaded) {
            Msg::notice('Reload detected (5s). Fetching directly from DB.');
            // 同期を実行し、かつその結果（最新リスト）を一旦保持しておく
            $latest_ids = $this->fetch_ids_via_kx_query();
            if (!empty($latest_ids)) return $latest_ids; // 同期直後のデータが一番確実なので、ここで返しても良い
        }

        // 次回判定用にセット（5秒間有効）
        set_transient($transient_key, true, 5);

        // 3. キャッシュ(Dy::content)から子要素候補を取得
        $cached_ids = Dy::get_content_cache($this->post_id, 'descendants') ?: [];
        $cached_virtuals = Dy::get_content_cache($this->post_id, 'virtual_descendants') ?: [];

        // $cached_virtuals が空でない配列なら true (1)
        $is_virtual = !empty($cached_virtuals);


        if (empty($cached_ids)) {
            // 仮想階層がある場合は「実体IDなし」の警告を抑制する
            if (!$is_virtual) {
                Msg::caution('No cached descendants found. Rebuilding...');
            }
            return $this->fetch_ids_via_kx_query();
        }

        // 4. キャッシュされたIDの整合性チェック
        $final_ids = [];
        foreach ($cached_ids as $id) {
            // インデックスの状態を確認（最新の状態へ更新・取得）
            $entry = Dy::set_path_index($id);

            // 有効なインデックス（valid=true）を持つIDのみを抽出
            if ($entry && !empty($entry['valid'])) {
                $final_ids[] = $id;
            }
        }

        // 5. 整合性チェックの結果、有効なIDが一つもない場合
        if (empty($final_ids)) {
            $title = Dy::get_title($this->post_id);
            if(!$is_virtual)  Msg::caution("Cache mismatch or invalid entry in: [{$title}]. Rebuilding via KxQuery.");
            return $this->fetch_ids_via_kx_query();
        }



        return $final_ids;
    }


    /**
     * KxQueryを使用して、現在のパス（≫）に基づき子要素を物理検索する
     */
    private function fetch_ids_via_kx_query() {

        $path = $this->origin_path;
        $full_path = $path['full'] ?? '';

        //echo '+2';
        //var_dump( $full_path );

        if (empty($full_path)) {
            Msg::caution('Matrix：$full_pathがありません');
            return [];
        }


        // 現在のパスの直下（≫）にあるものを前方一致で検索
        $query = new KxQuery([
            'search'     => $full_path . '≫',
            'title_mode' => 'prefix',
            'mode' => 'matrix',
        ]);


        $ids = $query->get_ids()??[];

        //echo '++'.count($ids);
        //return[];


        // 取得したIDでデータを更新。
        if (!empty($ids)) {
            foreach ($ids as $id) {
                // 1. 各アイテムを同期（親子関係の再整理と親のdescendants更新）
                \Kx\Core\ContextManager::sync($id);

                // 2. インデックスを最新状態にしてメモリにロード
                Dy::set_path_index($id);
            }
        }

        // 3. syncの結果、正しく「直下の子」だけに絞り込まれた最新リストを取得
        return Dy::get_content_cache($this->post_id, 'descendants') ?: [];
    }

    /**
     * dynamic_table：動的に解決されたカラム名と JSON 検索に対応した取得ロジック
     * (Orchestrator メソッド)
     */
    private function fetch_by_table_context() {
        global $wpdb;

        // 1. 基本情報の確定と安全確認
        $table = $this->resolve_table_name($this->atts['table'] ?? 'kx_1');
        if (!$this->is_table_query_safe($table)) {
            return [];
        }

        $select_column = $this->determine_id_column($table);
        $query_parts = ["WHERE 1=1"];
        $bind_params = [];

        // 2. 標準カラム(where)のクエリ構築
        if (!empty($this->atts['where'])) {
            $this->build_standard_where_clause($table, $query_parts, $bind_params);
        }

        // 3. JSONカラム(where_json)のクエリ構築
        if (!empty($this->atts['where_json'])) {
            $this->build_json_where_clause($query_parts, $bind_params);
        }

        // 4. クエリの組み立てと実行
        return $this->execute_table_query($table, $select_column, $query_parts, $bind_params);
    }

    /**
     * クエリ実行の安全性を確認
     */
    private function is_table_query_safe($table) {
        $has_where = !empty($this->atts['where']);
        $has_json  = !empty($this->atts['where_json']);
        $limit_val = (isset($this->atts['limit'])) ? (int)$this->atts['limit'] : -1;

        if (!$has_where && !$has_json && ($limit_val <= 0 || $limit_val > 500)) {
            $title = Dy::get_title($this->post_id);
            Msg::error("Matrix Safety:{$title} 'table' mode requires 'where' or a strict 'limit' (max 500).");
            return false;
        }
        return true;
    }

    /**
     * determine_id_column
     * テーブルの命名規則に基づき、IDとして扱うべきカラム名を判定する。
     *
     * @param string $table テーブル名
     * @return string カラム名（id, id_lesson, id_sens等）
     */
    private function determine_id_column($table) {
        global $wpdb;
        if ($table === $wpdb->prefix . 'kx_0' || $table === $wpdb->prefix . 'kx_1') {
            return 'id';
        }

        $title_top = mb_substr($this->origin_path['parts'][0] ?? '', 0, 1);
        switch ($title_top) {
            case 'Β': return 'id_lesson';
            case 'γ': return 'id_sens';
            case 'σ': return 'id_study';
            case 'δ': return 'id_data';
            default:  return 'id_data';
        }
    }

    /**
     * build_standard_where_clause
     * WHERE属性を解析し、LIKEを用いたAND/OR検索クエリを構築する。
     *
     * @param string $table 対象テーブル
     * @param array &$query_parts SQLパーツ配列（参照渡し）
     * @param array &$bind_params プレースホルダ値配列（参照渡し）
     */
    private function build_standard_where_clause($table, &$query_parts, &$bind_params) {
        global $wpdb;
        $where_conds = $this->parse_where_string($this->atts['where']);

        foreach ($where_conds as $col => $val) {
            if (!$this->is_safe_column($table, $col)) continue;

            // 1. AND 検索の判定
            if (stripos($val, ' AND ') !== false) {
                $and_values = explode(' AND ', $val);
                foreach ($and_values as $v) {
                    $v = trim($v);
                    if ($v === '') continue;

                    // 各単語を個別の AND 条件として追加
                    $this->append_like_sub_query($col, $v, $query_parts, $bind_params, 'AND_ROOT');
                }
            }
            // 2. OR 検索の判定
            elseif (stripos($val, ' OR ') !== false) {
                $or_values = explode(' OR ', $val);
                $sub_queries = [];
                foreach ($or_values as $v) {
                    $v = trim($v);
                    if ($v === '') continue;

                    // サブクエリ配列に蓄積
                    $this->append_like_sub_query($col, $v, $sub_queries, $bind_params, 'OR_SUB');
                }
                if (!empty($sub_queries)) {
                    $query_parts[] = "AND (" . implode(' OR ', $sub_queries) . ")";
                }
            }
            // 3. 単一単語の検索
            else {
                $this->append_like_sub_query($col, $val, $query_parts, $bind_params, 'AND_ROOT');
            }
        }
    }

    /**
     * LIKE句の生成とパラメータバインド（共通ロジック）
     *
     * @param string $col カラム名
     * @param string $val 検索語
     * @param array &$container 追加先の配列（query_parts または sub_queries）
     * @param array &$bind_params バインドパラメータ配列
     * @param string $mode 'AND_ROOT' (AND句として追加) か 'OR_SUB' (OR用に句のみ生成) か
     */
    private function append_like_sub_query($col, $val, &$container, &$bind_params, $mode) {
        global $wpdb;

        // タグの境界判定 (|tag| の形式で検索するか)
        $is_wrapped = (strpos($val, '|') === 0 && substr($val, -1) === '|');
        $clean_val = $is_wrapped ? trim($val, '|') : $val;

        $sql_part = "`{$col}` LIKE %s";
        $param = $is_wrapped ? '%|' . $wpdb->esc_like($clean_val) . '|%' : '%' . $wpdb->esc_like($clean_val) . '%';

        if ($mode === 'AND_ROOT') {
            $container[] = "AND " . $sql_part;
        } else {
            $container[] = $sql_part;
        }
        $bind_params[] = $param;
    }

    /**
     * build_json_where_clause
     * JSON型カラム内のデータを検索するためのクエリを構築する。
     *
     * @param array &$query_parts SQLパーツ配列
     * @param array &$bind_params パラメータ配列
     */
    private function build_json_where_clause(&$query_parts, &$bind_params) {
        global $wpdb;
        $json_raw_conds = explode(',', $this->atts['where_json']);

        foreach ($json_raw_conds as $cond_pair) {
            $kv = explode(':', $cond_pair, 2);
            if (count($kv) !== 2) continue;

            $j_key = trim($kv[0]);
            $or_vals = explode('|', trim($kv[1]));
            $or_queries = [];

            foreach ($or_vals as $v) {
                $v = trim($v);
                if (preg_match('/^(\d{4})%$/', $v, $matches)) {
                    $or_queries[] = "JSON_EXTRACT(`json`, %s) BETWEEN {$matches[1]}0000 AND {$matches[1]}9999";
                    $bind_params[] = "$.{$j_key}";
                } else {
                    $or_queries[] = "JSON_UNQUOTE(JSON_EXTRACT(`json`, %s)) LIKE %s";
                    $bind_params[] = "$.{$j_key}";
                    $bind_params[] = (strpos($v, '%') !== false) ? $v : '%' . $wpdb->esc_like($v) . '%';
                }
            }
            if (!empty($or_queries)) {
                $query_parts[] = "AND (" . implode(' OR ', $or_queries) . ")";
            }
        }
    }

    /**
     * execute_table_query
     * 組み立てられたSQLを実行し、結果（IDの列）を取得する。
     *
     * @param string $table テーブル名
     * @param string $select_column 取得対象カラム
     * @param array $query_parts WHERE句のパーツ
     * @param array $bind_params バインドする値
     * @return array 取得結果のリスト
     */
    private function execute_table_query($table, $select_column, $query_parts, $bind_params) {
        global $wpdb;

        $sql = "SELECT `{$select_column}` FROM `{$table}` " . implode(' ', $query_parts);

        // 並び順
        $order_dir = (isset($this->atts['order']) && strtoupper($this->atts['order']) === 'DESC') ? 'DESC' : 'ASC';
        $sql .= " ORDER BY `{$select_column}` {$order_dir}";

        // リミット
        $limit = (isset($this->atts['limit']) && (int)$this->atts['limit'] > 0) ? (int)$this->atts['limit'] : 1000;
        $sql .= " LIMIT %d";
        $bind_params[] = $limit;

        return $wpdb->get_col($wpdb->prepare($sql, $bind_params)) ?: [];
    }

    /**
     * resolve_table_name
     * ショートコードでの別名を、実際のDBテーブル名（プレフィックス付き）に解決する。
     *
     * @param string $input_name 入力されたテーブル名
     * @return string 解決された物理テーブル名
     */
    private function resolve_table_name($input_name) {
        global $wpdb;

        // 1. エイリアス（別名）のマッピング定義
        $mapping = [
            'shared' => 'kx_shared_title', // shared と打てばこれに変換
            // 今後増える場合はここに追加
            // 'log' => 'kx_system_logs'
        ];

        // マッピングがあれば置換、なければそのまま
        $table_base = isset($mapping[$input_name]) ? $mapping[$input_name] : $input_name;

        // 2. prefix（wp_）の付与判定
        if (strpos($table_base, $wpdb->prefix) === 0) {
            return $table_base;
        }

        return $wpdb->prefix . ltrim($table_base, '_');
    }

    /**
     * WHERE句の指定文字列を解析し、カラムと値の連想配列に変換する
     * * 入力例: "tag:κ作家コア OR S1118共通, status:publish"
     * 出力例: ['tag' => 'κ作家コア OR S1118共通', 'status' => 'publish']
     *
     * @param string $str ショートコードの where 属性などに渡されたカンマ区切りの検索条件文字列
     * @return array 解析済みの連想配列（Key: カラム名, Value: 検索値/条件）
     */
    private function parse_where_string($str) {
        if (empty($str)) return [];
        $res = [];
        $pairs = explode(',', $str);
        foreach ($pairs as $pair) {
            $kv = explode(':', $pair, 2);
            if (count($kv) === 2) {
                $res[trim($kv[0])] = trim($kv[1]);
            }
        }
        return $res;
    }

    /**
     * is_safe_column
     * 指定されたカラムが対象テーブルに物理的に存在するか検証する。
     *
     * @param string $table テーブル名
     * @param string $col 検証するカラム名
     * @return bool 存在するならtrue
     */
    private function is_safe_column($table, $col) {
        global $wpdb;

        // キャッシュを利用してDB負荷を軽減
        static $table_columns = [];

        if (!isset($table_columns[$table])) {
            // テーブルに存在する実際のカラムリストを取得
            $columns = $wpdb->get_col("DESCRIBE {$table}");
            $table_columns[$table] = $columns ?: [];
        }

        return in_array($col, $table_columns[$table], true);
    }

    /**
     * 外部からテーブル検索ロジック (#2) を直接実行するためのエントリポイント
     * 既存の analyze_context() を通さずに fetch_by_table_context() を実行する
     */
    public function direct_fetch_table_ids() {
        // インスタンス作成時に渡された $atts を使って直接 #2 を実行
        return $this->fetch_by_table_context();
    }
}