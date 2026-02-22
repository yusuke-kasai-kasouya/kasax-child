<?php
/**
 *[Path]: inc/core/matrix/class-1orchestrator.php
 */

namespace Kx\Matrix;

use Kx\Core\DynamicRegistry as Dy;
use \Kx\Utils\KxMessage as Msg;

/**
 * Class Orchestrator
 * 旧raretuの司令塔。ショートコードからの入力を受け取りパイプラインを回す。
 */
class Orchestrator {
    private $atts;
    private $origin_path;

    public function __construct($atts) {
        $this->atts = $atts;

        // 起点となる投稿の情報を一度だけ取得
        $path_index = Dy::get_path_index($this->atts['post_id']);
        $this->origin_path = $path_index ?? null;
    }

    public static function shortcode($atts) {
        Dy::trace_count('kxx_sc_count', 1);

        // 1. 旧仕様の属性を新仕様へ正規化 (shortcode_atts の前に行う)
        $atts = self::normalize_legacy_atts($atts);

        // 2. 属性の整理（デフォルト値の設定）
        $atts = shortcode_atts([
            'post_id'    => 0,
            'table'      => '',
            'where'      => '',
            'where_json' => '',
            'order'      => 'ASC',
            'sort'       => '',
            'sort_top'   => '',
            'sort_bottom'=> '',
            'label_type'=> '',
            'j'          => '',//旧：'sort_top'
            'je'         => '',//旧：'sort_bottom'
            'limit'      => -1,
        ], $atts);

        // 2. IDの補完
        if (empty($atts['post_id'])) {
            $atts['post_id'] = get_the_ID();
        }

        // 3. Orchestrator のインスタンス化と実行
        // ここで __construct($atts) が呼ばれる
        $matrix = new \Kx\Matrix\Orchestrator($atts);
        $ret = $matrix->run();
        Dy::trace_count('kxx_sc_count', -1);
        return $ret;
    }

    /**
     * マトリックス生成プロセスの実行
     * * 1. 再帰階層（depth）の管理と無限ループ防止
     * 2. クエリ実行による対象IDとコンテキストの取得
     * 3. データの収集・加工（Processor）
     * 4. 階層に応じたレンダリング（フル表示 or アウトライン）の切り替え
     *
     * @return string レンダリングされたHTML文字列、または空文字
     * @throws Exception プロセス中に発生した例外（finallyでカウントは保護される）
     */
    public function run() {
        // 1. カウントアップ（再帰階層の記録）
        $current_depth = Dy::trace_count('matrix_count', +1);

        if ($current_depth > 4) { // 3段階以上の入れ子は「無限ループ」の疑いあり
            Msg::error("Matrix：再帰制限を超過しました。");
            return '<div class="kx-matrix-error">Too many recursions.</div>';
        }

        try {
            $query    = new Query($this->atts);
            $ids      = $query->get_ids();
            $context  = $query->get_context();
            $virtuals = $query->get_virtuals() ?? []; // array(4) { [0]=> "2構成", ... }

            $is_special_matrix = ($context === 'timetable_matrix');

            // ids も virtuals も空、かつ特殊マトリックスでもない場合のみエラー
            if (empty($ids) && empty($virtuals) && !$is_special_matrix) {
                $title = $this->origin_path['full'] ?? 'Undefined Path';
                $str = "Matrix：idsおよび仮想階層なし。$title";
                Msg::error($str);
                return '<div style="color:red;">'.$str.'</div>';
            }

            // DataCollector: 実体IDがある場合のみデータを収集
            $collector = new DataCollector($ids, $this->atts);
            $collector->prepare_all($context);
            $collection_data = $collector->get_collection();

            // Processor: 仮想階層情報も渡すように拡張する
            // 第二引数以降の設計に合わせて調整してください
            $processor = new Processor($collection_data, $context, $this->atts);

            // 仮想階層データをセットするメソッド（またはコンストラクタ拡張）が必要
            if (method_exists($processor, 'set_virtuals') && !empty($virtuals)) {
                $processor->set_virtuals($virtuals);
            }
            $matrix    = $processor->build();

            // --- レンダリング分岐処理 ---
            if ($current_depth > 1) {
                // 2階層目（再帰呼び出し時）は軽量なアウトライン形式で出力
                if( $context !== 'timetable_matrix'){
                    $output = Renderer::render_outline($matrix, $this->atts['post_id']);
                }else{
                    $output = "━━━　SC：RARETU：{$context}　━━━";
                }

            } else {
                // 1階層目（初回呼び出し）はフルマトリックス形式で出力
                $output = Renderer::render($matrix, $context);
            }
        } catch (\Exception $e) {
            // 例外をキャッチした場合に赤い1行を返す
            return sprintf(
                '<div class="kx-matrix-fatal-error" style="color: #fff; background: #e74c3c; padding: 5px 10px; font-size: 0.8rem; border-radius: 4px;">❌ Matrix Error: %s</div>',
                esc_html($e->getMessage())
            );

        } finally {
            // 重要：処理終了時に必ずカウントを減らす（finallyにより早期return時も実行される）
            // これにより後続の独立したショートコードへの影響を排除する
            Dy::trace_count('matrix_count', -1);
        }

        return $output;
    }

    /**
     * 旧仕様の属性を新仕様の正規形式にマッピング・変換する
     */
    private static function map_legacy_attributes($atts) {
        if (empty($atts)) return [];

        // 1. 単純なキーの置き換えルール定義（旧 => 新）
        $key_map = [
            'table_name' => 'table',
            'index_t'    => 'limit',
            'j'          => 'sort_top',
            'je'         => 'sort_bottom',
        ];

        foreach ($key_map as $old_key => $new_key) {
            if (isset($atts[$old_key]) && !isset($atts[$new_key])) {
                $atts[$new_key] = $atts[$old_key];
            }
        }

        // 2. 特殊な値の加工ロジック

        // テーブル名の wp_ 接頭辞を除去
        if (!empty($atts['table'])) {
            $atts['table'] = str_replace('wp_', '', $atts['table']);
        }

        // conditions (旧) から where (新) への複雑な変換
        if (!empty($atts['conditions']) && empty($atts['where'])) {
            $atts['where'] = self::convert_legacy_conditions($atts['conditions']);
        }

        return $atts;
    }

    /**
     * 旧 conditions 構文のパース
     * 例: json=%タグ%A%,%タグ%B%  =>  tag:A OR B
     */
    private static function convert_legacy_conditions($conditions_str) {
        // カンマで分割（旧仕様は複数条件をカンマ区切りにしていたと想定）
        $segments = explode(',', $conditions_str);
        $tags = [];

        foreach ($segments as $segment) {
            // 「%タグ%内容%」というパターンを正規表現で抽出
            if (preg_match('/%タグ%(.*?)%/', $segment, $matches)) {
                $tags[] = trim($matches[1]);
            }
        }

        if (!empty($tags)) {
            // 新仕様： tag:A OR B 形式に結合
            return 'tag:' . implode(' OR ', $tags);
        }

        // 該当するパターンがない場合はそのまま返す
        return $conditions_str;
    }


    /**
     * 旧仕様の属性を新仕様の正規形式に変換する
     */
    public static function normalize_legacy_atts($atts) {
        if (!is_array($atts)) return $atts;

        $is_legacy = false;
        $legacy_found_keys = [];

        // --- 1. テーブル名の変換 (wp_kx_1 -> kx_1) ---
        if (!empty($atts['table_name'])) {
            if($atts['table_name'] === 'wp_kx_works' ){
                $table_name = 'shared';
            }else if($atts['table_name'] === 'wp_kx_shared_title' ){
                $table_name = 'shared';
            }else if($atts['table_name'] === 'wp_kx_1' ){
                $table_name = 'kx_1';
            }
            $atts['table'] = str_replace('wp_', '', $table_name ?? $atts['table_name']);
            $is_legacy = true;
            $legacy_found_keys[] = 'table_name';
        }

        // --- 2. 検索条件 (conditions) のパースと変換 ---
        if (!empty($atts['conditions']) && $atts['conditions'] !== -1 && empty($atts['where'])) {
            $atts['where'] = self::convert_conditions_to_where($atts['conditions']);
            $is_legacy = true;
            $legacy_found_keys[] = 'conditions';
        }

        if (!empty($atts['db']) && !empty($atts['db_like']) ) {
            $atts['table'] = 'shared';
            $atts['where'] = 'tag:'.preg_replace('/%/',' OR ',$atts['db_like']) ;
            $is_legacy = true;
            $legacy_found_keys[] = 'db';
        }

        // --- 警告メッセージの生成 ---
        if ($is_legacy) {
            // 識別用タイトルの生成
            $target_label = (!empty($atts['post_id'])) ? Dy::get_title($atts['post_id']) : get_the_title();

            // Titleの色をシアン(#00ffff)に変更して視認性を確保
            $title = "<strong style='color:#00ffff;'>【旧式SC検知】</strong><br><span style='color:#00ffff;'>{$target_label}</span>";

            // 推奨コードの組み立て
            $new_sc_parts = [];
            $display_keys = ['table', 'where', 'tougou'];
            foreach ($display_keys as $key) {
                if (isset($atts[$key]) && $atts[$key] !== '' && $atts[$key] !== -1) {
                    $new_sc_parts[] = sprintf('%s="%s"', $key, $atts[$key]);
                }
            }

            $suggested_sc = '[raretu ' . implode(' ', $new_sc_parts) . ']';

            // メッセージ出力：全体は黄色、Titleは水色、コード部分は背景色を少し変えるとより見やすいです
            Msg::warn(
                "{$title}<br>" .
                "検出： " . implode(', ', $legacy_found_keys) . "<br>" .
                "<code style='background:rgba(255,255,255,0.1); padding:2px 4px;'>" . esc_html($suggested_sc) . "</code>"
            );
        }

        return $atts;
    }

    /**
     * 旧: json=%タグ%A%,%タグ%B% -> 新: tag:A OR B
     */
    private static function convert_conditions_to_where($conditions_str) {
        if (empty($conditions_str)) return '';

        // カンマ区切りで分割
        $segments = explode(',', $conditions_str);
        $tags = [];

        foreach ($segments as $segment) {
            // 「%タグ%内容%」または「json=%タグ%内容%」を抽出
            if (preg_match('/%タグ%(.*?)%/', $segment, $matches)) {
                $tags[] = trim($matches[1]);
            }else if (preg_match('/json=%(.*?)%/', $segment, $matches)) {
                $tags[] = trim($matches[1]);
            }

        }

        if (!empty($tags)) {
            // 新仕様の構文 「tag:A OR B」に組み立て
            return 'tag:' . implode(' OR ', $tags);
        }

        return $conditions_str; // 変換不可ならそのまま返す
    }
}