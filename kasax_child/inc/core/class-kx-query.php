<?php
/**
 * [Path]: inc\core\class-kx-query.php
 */

namespace Kx\Core;

use Kx\Core\DynamicRegistry as Dy;
use \Kx\Utils\KxMessage as Msg;
use \Kx\Core\LaravelClient;

/**
 * Class KxQuery
 * * 創作支援システムにおける多層検索エンジン。
 * 独自DB(kx_0)、Laravel API、WP_Queryの三段階で投稿IDを抽出する。
 * タイトルに含まれる接頭辞や階層パスをワイルドカードで連結し、高速な属性検索を実現する。
 */
class KxQuery {
    private $args;
    private $post_ids;

    /**
     * KxQuery コンストラクタ
     * * @param array $atts ショートコード等からの検索属性
     */
    public function __construct($atts = []) {
        // 1. 引数の初期化と正規化（内部で TitleParser や identifier_schema を参照）
        $this->args = $this->parse_attributes($atts);
        // 3. 実行して結果をプロパティに保持
        // execute() 内で path_index を走査することで、WP_Query への負荷を軽減する
        $this->post_ids = $this->execute();
    }


    /**
     * 属性の正規化とデフォルト値の設定
     * * @param array $atts 生の属性配列
     * @return array 正規化済みの引数
     */
    private function parse_attributes($atts) {
        // 1. デフォルト値の定義
        $defaults = [
            // --- あなたの指定分 ---
            'search'     => '',
            'search_suffix' => null,

            'title_mode' => 'both',//一致モード (prefix:前方一致 | suffix:後方一致 | exact:完全一致 | both:部分一致)
            'cat'        => null,
            'cat_not'    => null,
            'tag'        => null,
            'tag_not'    => null,

            'type'      => null,
            'depth'      => null,


            // --- 運用上、必須級の項目 ---
            'ppp'        => -1,      // 取得件数 (posts_per_page)
            'orderby'    => 'date',  // 何順で並べるか
            'order'      => 'DESC',  // 降順・昇順

            // 追加
            'mode'       => 'auto',  // 降順・昇順
        ];

        // 2. WP標準の属性マージ
        $args = shortcode_atts($defaults, $atts);

        return $args;
    }

    /**
     * 検索の実行とフォールバック制御
     * * @return array 確定した投稿ID配列
     */
    public function execute() {
        //各テーブル間の同期確認。
        $is_synced = Dy::get_system('is_synced');

        //echo '++'.$this->args['depth'].'<br>';

        // --- 1. Local (kx_0) 検索 ---
        if ($is_synced && $this->args['mode'] !== 'wp') {
            if ($ids = $this->search_local()) {
                Msg::info("KxQuery: kx_0");
                return $this->finalize($ids,'kx_0');
            }
        } else if (!$is_synced) {
            // 同期されていない場合。
            \kx\Database\DB::maintenance_all_cleanup();
            Msg::caution("KxQuery:Not Synced：同期に齟齬あり。run_maintenance");
        }

        // --- 2. Laravel API 検索 ---
        // オンラインチェック
        $is_laravel_online = Dy::get_system('laravel_online') ?? false;
        if ($is_laravel_online) {
            if ($ids = $this->search_laravel()) {
                $title = Dy::get_title( get_the_ID());
                Msg::info("KxQuery:Laravel。$title");
                return $this->finalize($ids,'Laravel');
            }
        } else {
            Msg::notice("KxQuery: Laravel OFFline.");
        }

        // --- 3. WordPress (WP_Query) 検索 ---
        if ($ids = $this->search_wp()) {
            Msg::notice("KxQuery:WP_Query");
            return $this->finalize($ids,'WP_Query');
        }

        // 全てで見つからない場合
        Msg::caution("KxQuery: No results found for '" . ($this->args['search'] ?? 'none') . "'");
        return [];
    }

    /**
     * 独自高速テーブル kx_0 による詳細検索
     * 階層(depth)や種別(type)を SQL レベルで絞り込むことで、
     * PHP側の負荷を抑え ERR_CONNECTION_RESET を防止する。
     *
     * @return array|false ID配列または失敗時にfalse
     */
    private function search_local() {
        global $wpdb;

        $args = $this->args;
        $segments = [];

        // 1. LIKE検索用セグメントの構築
        if (!empty($args['cat'])) {
            $cat_name = get_cat_name($args['cat']) ?: '';
            if ($cat_name) $segments[] = $wpdb->esc_like($cat_name);
        }
        if (!empty($args['tag']))    $segments[] = $wpdb->esc_like($args['tag']);
        if (!empty($args['search'])) $segments[] = $wpdb->esc_like($args['search']);

        // 2. WHERE句の動的構築
        $where_clauses = [];
        $query_params  = [];

        if (!empty($segments)) {
            $esc_search = implode('%', $segments);
            $mode = $args['title_mode'] ?? 'both';

            switch ($mode) {
                case 'prefix': $like_val = $esc_search . '%'; break;
                case 'suffix': $like_val = '%' . $esc_search; break;
                case 'exact':  $like_val = $esc_search; break;
                default:       $like_val = '%' . $esc_search . '%'; break;
            }
            $where_clauses[] = "title LIKE %s";
            $query_params[]  = $like_val;
        }

        if (!empty($args['depth'])) {
            $where_clauses[] = "depth = %d";
            $query_params[]  = (int)$args['depth'];
        }

        if (!empty($args['type'])) {
            $where_clauses[] = "type = %s";
            $query_params[]  = $args['type'];
        }

        if (empty($where_clauses)) return false;

        // --- ORDER BY (並び替え) ---
        $sort_field = 'id'; // デフォルト
        if (!empty($args['orderby'])) {
            switch ($args['orderby']) {
                case 'modified':
                case 'date':
                    $sort_field = 'wp_updated_at'; // テーブル上のカラム名
                    break;
                case 'title':
                    $sort_field = 'title';
                    break;
            }
        }
        $order = (strtoupper($args['order'] ?? '') === 'ASC') ? 'ASC' : 'DESC';
        $order_sql = "ORDER BY {$sort_field} {$order}";

        // --- LIMIT (取得件数) ---
        $limit_sql = "";
        $ppp = (int)($args['ppp'] ?? -1);
        if ($ppp > 0) {
            $limit_sql = $wpdb->prepare("LIMIT %d", $ppp);
        }

        // 3. SQLの組み立て
        $table_name = $wpdb->prefix . 'kx_0';
        $where_sql  = implode(' AND ', $where_clauses);

        $query = $wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE {$where_sql} {$order_sql} {$limit_sql}",
            ...$query_params
        );

        // 4. 実行
        $ids = $wpdb->get_col($query);

        return !empty($ids) ? array_map('intval', $ids) : false;
    }


    /**
     * Laravel API を使用した高度な検索
     * * @return array APIから返却されたID配列
     */
    private function search_laravel() {
        return LaravelClient::search_advanced_ids_cached($this->args);
    }

    /**
     * WordPress 標準機能によるタイトル限定検索
     * * titles_where フィルタを書き換え、本文検索を排除して高速化する。
     * * @return array|false ID配列または失敗時にfalse
     */
    private function search_wp() {
        $args = $this->args;

        // WP_Queryの基本パラメータ構成
        $wp_args = [
            'post_type'      => 'post', // 必要に応じてカスタムポストタイプを指定
            'post_status'    => 'publish',
            'posts_per_page' => $args['ppp'],
            'orderby'        => $args['orderby'],
            'order'          => $args['order'],
            'fields'         => 'ids',  // IDのみ取得して高速化
        ];

        // カテゴリ・タグの絞り込み
        if (!empty($args['cat']))     $wp_args['category__in']     = explode(',', $args['cat']);
        if (!empty($args['cat_not'])) $wp_args['category__not_in'] = explode(',', $args['cat_not']);
        if (!empty($args['tag']))     $wp_args['tag__in']          = explode(',', $args['tag']);
        if (!empty($args['tag_not'])) $wp_args['tag__not_in']      = explode(',', $args['tag_not']);

        // --- タイトル検索の強制 (searchパラメータがある場合) ---
        if (!empty($args['search'])) {
            // 標準の 's' を使うと本文まで検索されるため、フックでSQLを書き換える
            $title_filter = function($where, $wp_query) use ($args) {
                global $wpdb;
                $search_term = $wpdb->esc_like($args['search']);

                // title_mode に応じた検索パターンの切り替え
                switch ($args['title_mode']) {
                    case 'prefix': // 前方一致
                        $where .= " AND {$wpdb->posts}.post_title LIKE '{$search_term}%'";
                        break;
                    case 'suffix': // 後方一致
                        $where .= " AND {$wpdb->posts}.post_title LIKE '%{$search_term}'";
                        break;
                    default:       // 部分一致 (both)
                        $where .= " AND {$wpdb->posts}.post_title LIKE '%{$search_term}%'";
                        break;
                }
                return $where;
            };

            add_filter('posts_where', $title_filter, 10, 2);
            $query = new \WP_Query($wp_args);
            remove_filter('posts_where', $title_filter); // 他のクエリに影響させない
        } else {
            $query = new \WP_Query($wp_args);
        }

        $ids = $query->posts;

        if (!empty($ids)) {
            Msg::info("search_wp: Found " . count($ids) . " posts.");
            return $ids;
        }

        return false;
    }

    /**
     * 公開用：取得済みID配列の返却
     * * @return array ID配列
     */
    public function get_ids() {
        return $this->post_ids ?? [];
    }

/**
     * ID配列の最終整形と属性フィルタリング
     */
    private function finalize($ids, $source = '') {
        if (!$ids || !is_array($ids)) return [];


        $ids = array_unique(array_map('intval', $ids));
        $ids = array_values(array_filter($ids));
        if (empty($ids)) return [];

        $target_depth  = !empty($this->args['depth']) ? (int)$this->args['depth'] : null;
        $target_type   = !empty($this->args['type'])  ? $this->args['type'] : null;
        $target_suffix = !empty($this->args['search_suffix']) ? $this->args['search_suffix'] : null;
        $limit = (!empty($this->args['ppp']) && $this->args['ppp'] !== -1) ? $this->args['ppp'] : 250;




        // フィルタリングが必要な条件がある場合
        if ($source !== 'kx_0' || $target_suffix !== null) {
            $filtered = [];
            foreach ($ids as $id) {
                $index = Dy::get_path_index($id);
                if (!$index) continue;

                // 1. 深さ判定
                if ($target_depth !== null && (int)$index['depth'] !== $target_depth) continue;

                // 2. タイプ判定
                if ($target_type !== null) {
                    if ($index['genre'] !== $target_type && $index['type'] !== $target_type) continue;
                }

                // 3. 特殊後方一致判定 (正規表現活用)
                if ($target_suffix !== null) {
                    $title_full = $index['full'];
                    $is_match = false;

                    switch ($target_suffix) {
                        case ':num:':   // 数字で終わる
                            $is_match = preg_match('/[0-9０-９]$/u', $title_full);
                            break;
                        case ':alnum:': // 英数字で終わる
                            $is_match = preg_match('/[a-zA-Z0-9ａ-ｚＡ-Ｚ０-９]$/u', $title_full);
                            break;
                        case ':alpha:': // 英字で終わる
                            $is_match = preg_match('/[a-zA-Zａ-ｚＡ-Ｚ]$/u', $title_full);
                            break;
                        default:        // 通常の文字列後方一致
                            $is_match = str_ends_with($title_full, $target_suffix);
                            break;
                    }

                    if (!$is_match) continue;
                }

                $filtered[] = $id;
            }
            $ids = $filtered;
        }



        // 250件制限
        if (count($ids) > $limit) {
            //if( $ppp === -1){$ppp = 250;}
            Msg::notice("KxQuery: $source results truncated to $limit.");
            $ids = array_slice($ids, 0, $limit);
        }

        $this->post_ids = $ids;
        return $this->post_ids;
    }
}