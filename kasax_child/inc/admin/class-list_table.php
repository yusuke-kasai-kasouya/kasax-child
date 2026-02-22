<?php
/**
 * [Path]: inc\admin\list_table.php
 *
 */

namespace Kx\Admin;

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class KxListTable extends \WP_List_Table {
    private $target_table;

    /**
     * コンストラクタ
     * * 親クラスの初期化と表示対象テーブルの設定を行う。
     *
     * @param string $table_name 表示対象のデータベーステーブル名
     */
    public function __construct($table_name) {
        parent::__construct([
            'singular' => 'kx_data',
            'plural'   => 'kx_datas',
            'ajax'     => false
        ]);
        $this->target_table = $table_name;
    }


    /**
     * カラム定義の取得
     * * 表示対象のテーブルに応じて、テーブルヘッダー（列名）の配列を返す。
     *
     * @return array 連想配列（キー => ラベル）形式のカラム定義
     */
    public function get_columns() {
        if ($this->target_table === 'wp_kx_hierarchy') {
            // ... (Hierarchyの定義は維持)
            return [
                'full_path'   => '階層パス',
                'post_id'     => 'ID',
                'parent_path' => '親パス',
                'is_virtual'  => '仮想',
                'time'        => '更新日時'
            ];
        } elseif ($this->target_table === 'wp_kx_0') {
            // 新しい wp_kx_0 の構成に合わせて定義
            return [
                'title'         => 'タイトル',
                'id'            => 'ID',
                'type'          => '種別',
                'wp_updated_at' => 'WP更新日時'
            ];
        } elseif ($this->target_table === 'wp_kx_shared_title') {
            // 概念統合インデックス用のカラム定義
            return [
                'title'     => 'タイトル', // 左端に配置
                'id_lesson' => '教訓',
                'id_sens'   => '感性',
                'id_study'  => '研究',
                'id_data'   => 'データ',
                'time'      => '更新日時'
            ];
        }else if ($this->target_table === 'wp_kx_ai_metadata') {
            return [
                'post_id'           => 'PostID',
                'post_title'        => 'Title',
                'ai_score_deviation'=> '観測値',
                'ai_score'          => '総合スコア',
                'top_keywords'      => '重要キーワード',
                'ai_score_stat'     => '統計点',
                'ai_score_context'  => '文脈点',
                'post_modified'     => '投稿更新日',
                'last_analyzed_at'  => 'AI分析日時'
            ];
        } else {
            // wp_kx_1 など
            return [
                'title' => 'タイトル',
                'id'    => 'ID',
                'tag'  => 'タグ',
                'raretu_code'  => 'raretuコード',
                'time'  => '更新日時'
            ];
        }
    }


    /**
     * ソート可能なカラムの定義
     * * ユーザーがクリックして並び替えができる列と、そのSQL用キーを定義する。
     *
     * @return array ソート設定の配列
     */
    public function get_sortable_columns() {
        return [
            'full_path'     => ['full_path', true],
            'title'         => ['title', false],
            'id'            => ['id', false],
            'time'          => ['time', false],
            'wp_updated_at' => ['wp_updated_at', false] // ソート対象に追加
        ];
    }


    /**
     * 各カラムのデフォルト描画処理
     * * 特定のフォーマットが必要なカラム（JSON、UNIXスタンプ、側面ID等）の出力を制御する。
     *
     * @param array  $item        1行分のデータ（連想配列）
     * @param string $column_name 現在の列のキー名
     * @return string HTML出力される文字列
     */
    protected function column_default($item, $column_name) {
        switch ($column_name) {
            case 'json':
                $data = json_decode($item[$column_name], true);
                if (!$data) return '<span class="description">-</span>';
                $labels = [];
                if (!empty($data['GhostON'])) $labels[] = '👻' . esc_html($data['GhostON']);
                if (!empty($data['ShortCODE'])) $labels[] = '<code>[' . esc_html($data['ShortCODE']) . ']</code>';
                return implode(' ', $labels) ?: '<small>Data exists</small>';

            case 'time':
                // UNIXスタンプを日付形式に変換
                // UNIXスタンプを日付形式に変換
                $val = $item[$column_name];
                if (empty($val)) return '-';

                if (is_numeric($val)) {
                    // WordPressの設定に基づいた時刻表示（日本時間設定ならJSTになる）
                    return wp_date('Y/m/d H:i:s', (int)$val);
                }
                return esc_html($val);

            case 'wp_updated_at':
                // datetime型なので、空でなければそのまま表示（または秒を削る等の加工）
                return !empty($item[$column_name]) ? esc_html($item[$column_name]) : '-';

            case 'type':
                // 種別（post/page等）を見やすく表示
                return !empty($item[$column_name]) ? '<code>' . esc_html($item[$column_name]) . '</code>' : '-';

            // 4側面のID表示（0はグレーアウトして視認性を上げる）
            case 'id_lesson':
            case 'id_sens':
            case 'id_study':
            case 'id_data':
                $id = (int)$item[$column_name];
                return $id > 0 ? $id : '<span style="color:#ccc;">0</span>';

            default:
                return isset($item[$column_name]) ? esc_html($item[$column_name]) : '';
        }
    }


    /**
     * テーブル表示データの準備
     * * カラムヘッダーの設定、ソート、ページネーション、およびデータベースからのデータ取得を実行する。
     * * @return void
     */
    public function prepare_items() {
        global $wpdb;

        // 1. カラム情報の取得
        $columns  = $this->get_columns();
        $hidden   = []; // 非表示にしたいカラムがあればここに入れる
        $sortable = $this->get_sortable_columns();

        // 重要：この代入が漏れている、または $columns が空だと Fatal Error になります
        $this->_column_headers = [$columns, $hidden, $sortable];

        // --- 以下、既存のデータ取得処理 ---
        $per_page = 50;

        // テーブルごとにデフォルトの並び替えキーを決定
        $default_orderby = 'id'; // 基本は id

        if ($this->target_table === 'wp_kx_0') {
            $default_orderby = 'wp_updated_at';
        } elseif (in_array($this->target_table, ['wp_kx_1', 'wp_kx_hierarchy', 'wp_kx_shared_title'])) {
            $default_orderby = 'time';
        } elseif ($this->target_table === 'wp_kx_ai_metadata') {
            // ai_metadata 用のデフォルトキーを指定
            $default_orderby = 'ai_score';
        }

        $orderby = !empty($_GET['orderby']) ? esc_sql($_GET['orderby']) : $default_orderby;
        $order   = !empty($_GET['order']) ? esc_sql($_GET['order']) : 'DESC';
        $paged   = $this->get_pagenum();

        // データの取得
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM {$this->target_table}");
        $this->items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->target_table} ORDER BY $orderby $order LIMIT %d OFFSET %d",
            $per_page,
            ($paged - 1) * $per_page
        ), ARRAY_A);

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page
        ]);
    }

    /**
     * タイトルカラムの表示処理
     */
    public function column_post_title($item) {
        $post_id = $item['post_id'];
        $title = get_the_title($post_id);

        if (!$title || $title === '') {
            return '<span style="color:#999;">(タイトルなし)</span>';
        }

        // ついでに編集画面へのリンクも貼っておくと便利です
        $link = get_permalink($post_id);

        return sprintf(
            '<strong><a class="row-title" href="%s" target="_blank">%s</a></strong>',
            esc_url($link),
            esc_html($title)
        );
    }
}