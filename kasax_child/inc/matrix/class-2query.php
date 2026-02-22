<?php
/**
 *[Path]: inc/core/matrix/class-query.php
 */

namespace Kx\Matrix;

//use Kx\Core\SystemConfig as Su;
use Kx\Core\DynamicRegistry as Dy;
use Kx\Core\KxQuery;
//use Kx\Database\dbkx0_PostSearchMapper as dbkx0;
use Kx\Core\ContextManager;
use \Kx\Utils\KxMessage as Msg;
//use Kx\Core\TitleParser as Tp;

class Query {
    private $atts;
    private $post_id;
    private $origin_path;

    private $context = 'default_list'; // デフォルト

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
        $is_virtual = isset($cached_virtuals) ? 1 : $is_virtual;

        if (empty($cached_ids)) {
            if(!$is_virtual) Msg::caution('No cached descendants found. Rebuilding...');
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
     */
    private function fetch_by_table_context() {
        global $wpdb;

        // 1. テーブル名の確定
        $raw_table = $this->atts['table'] ?? 'kx_1';
        $table = $this->resolve_table_name($raw_table);

        // --- 【防衛策：全件取得の阻止】 ---
        $has_where = !empty($this->atts['where']);
        $has_json  = !empty($this->atts['where_json']);
        $limit_val = (isset($this->atts['limit'])) ? (int)$this->atts['limit'] : -1;

        // 条件が空で、かつ明示的なリミット（1〜500程度）が指定されていない場合はエラーとする
        if (!$has_where && !$has_json && ($limit_val <= 0 || $limit_val > 500)) {
            $title = Dy::get_title($this->post_id);
            Msg::error("Matrix Safety:{$title} 'table' mode requires 'where' or a strict 'limit' (max 500). Execution halted.");
            return [];
        }

        // 2. カラム判定ロジック
        $select_column = 'id';
        if ($table !== $wpdb->prefix . 'kx_0' && $table !== $wpdb->prefix . 'kx_1') {
            // 先頭一文字を確実に取得
            $title_top = mb_substr($this->origin_path['parts'][0] ?? '', 0, 1);

            switch ($title_top) {
                case 'Β': $select_column = 'id_lesson'; break;
                case 'γ': $select_column = 'id_sens';   break;
                case 'σ': $select_column = 'id_study';  break;
                case 'δ': $select_column = 'id_data';   break;
                default:  $select_column = 'id_data';   break;
            }
        }

        $query_parts = ["WHERE 1=1"];
        $bind_params = [];

        // 3. 通常の where 検索 (tag等)
        if (!empty($this->atts['where'])) {
            $where_conds = $this->parse_where_string($this->atts['where']);
            foreach ($where_conds as $col => $val) {
                if (!$this->is_safe_column($table, $col)) continue;

                if ($col === 'tag') {
                    // ショートコードで "tag:A OR B" と書けるようにする
                    if (stripos($val, ' OR ') !== false) {
                        // " OR " を正規表現の "|" に変換
                        $or_patterns = explode(' OR ', $val);
                        $clean_patterns = array_map(function($p) use ($wpdb) {
                            return $wpdb->esc_like(trim($p, '|% ')); // 余計な記号を掃除
                        }, $or_patterns);

                        $query_parts[] = "AND `{$col}` REGEXP %s";
                        $bind_params[] = implode('|', $clean_patterns);
                    } else {
                        // 通常の LIKE 検索（現状維持）
                        $clean_val = trim($val, '|% ');
                        $query_parts[] = "AND `{$col}` LIKE %s";
                        $bind_params[] = '%' . $wpdb->esc_like($clean_val) . '%';
                    }
                } else {
                    $query_parts[] = "AND `{$col}` LIKE %s";
                    $bind_params[] = '%' . $wpdb->esc_like(trim($val, '% ')) . '%';
                }
            }
        }

        // 4. JSON検索ロジック (where_json)
        if (!empty($this->atts['where_json'])) {
            $json_raw_conds = explode(',', $this->atts['where_json']);

            foreach ($json_raw_conds as $cond_pair) {
                $kv = explode(':', $cond_pair, 2);
                if (count($kv) !== 2) continue;

                $j_key = trim($kv[0]);
                $j_val = trim($kv[1]);

                // OR条件の分割（2023%|2024%|2025% 等）
                $or_vals = explode('|', $j_val);
                $or_queries = [];

                foreach ($or_vals as $v) {
                    $v = trim($v);

                    // 年指定のワイルドカード（例: 2023%）を数値範囲に変換
                    if (preg_match('/^(\d{4})%$/', $v, $matches)) {
                        $year = $matches[1];
                        $start = $year . "0000";
                        $end = $year . "9999";

                        // 数値として比較するSQLを組み立て
                        $or_queries[] = "JSON_EXTRACT(`json`, %s) BETWEEN {$start} AND {$end}";
                        $bind_params[] = "$.{$j_key}";
                    } else {
                        // 通常の文字列一致
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

        // 5. クエリ組み立て
        $sql_template = "SELECT `{$select_column}` FROM `{$table}` " . implode(' ', $query_parts);


        // 1. カラム名の決定（指定がなければ $select_column）
        $order_col = "`{$select_column}`";

        // 2. 並び順（ASC/DESC）の決定
        $order_dir = 'ASC'; // デフォルト
        if (!empty($this->atts['order'])) {
            $input_order = strtoupper($this->atts['order']);
            if (strpos($input_order, 'ASC') !== false) {
                $order_dir = 'ASC';
            }
        }

        // 3. SQL組み立て（カラム名 + 順序）
        $order_sql = "{$order_col} {$order_dir}";

        $limit = (isset($this->atts['limit']) && (int)$this->atts['limit'] > 0) ? (int)$this->atts['limit'] : 1000;

        $sql_template .= " ORDER BY {$order_sql} LIMIT %d";
        $bind_params[] = $limit;

        // 最終実行
        $prepared_sql = $wpdb->prepare($sql_template, $bind_params);
        $results = $wpdb->get_col($prepared_sql);

        return $results ? $results : [];
    }

    /**
     * ショートコードの入力を正式なテーブル名に解決する
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
     * カラム名の安全性を検証（物理カラムに存在するか）
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