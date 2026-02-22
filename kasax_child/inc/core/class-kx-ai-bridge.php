<?php
/**
 * [Path]: inc\core\class-kx-ai-bridge.php
 */

namespace Kx\Core;

use Dy;
use Kx\Utils\KxMessage as Msg;

/**
 * AI解析メタデータとWordPress投稿を仲介するブリッジクラス
 */
class KxAiBridge {

    /**
     * 現在の投稿と類似したキーワードを持つ投稿IDとスコアのリストを取得する
     */
    public static function get_similar_posts(int $post_id, int $limit = 10,$args = []): array {
        global $wpdb;

        // --- 1. 引数の準備 ---
        // 例: $args = ['whitelist' => ['Β', 'γ'], 'blacklist' => ['σ']]
        $whitelist = isset($args['whitelist']) ? (array)$args['whitelist'] : [];
        $blacklist = isset($args['blacklist']) ? (array)$args['blacklist'] : [];

        // --- 2. 基準投稿のメタデータを取得（ベクトルも含む） ---
        $base_data = self::get_ai_metadata($post_id);
        if (!$base_data || empty($base_data['keywords'])) {
            Msg::notice ("AI LINK: No keywords {$post_id}");
            return [];
        }

        // --- 修正ポイント：連想配列のキー（単語）を抽出 ---
        $target_keywords = array_keys($base_data['keywords']);

        // 2. SQLによる高速な「候補」の絞り込み
        $table_name = $wpdb->prefix . 'kx_ai_metadata';
        $conditions = [];
        foreach ($target_keywords as $kw) {
            $conditions[] = $wpdb->prepare("top_keywords LIKE %s", '%' . $wpdb->esc_like($kw) . '%');
        }

        if (empty($conditions)) return [];

        $where_sql = implode(' OR ', $conditions);
        $query = "
            SELECT post_id, top_keywords, vector_data, ai_score
            FROM {$table_name}
            WHERE post_id != %d
            AND ({$where_sql})
            LIMIT 100
        ";

        $results = $wpdb->get_results($wpdb->prepare($query, $post_id), ARRAY_A);

        if (empty($results)) return [];

        // 3. PHP側での精密なスコアリング（高速化版）
        $scored_list = [];
        foreach ($results as $row) {
            $parts = Dy::get_path_index($row['post_id'], 'parts');
            $prefix = isset($parts[0]) ? $parts[0] : '';

            // A. ブラックリスト判定: 該当したら即座に除外
            if (!empty($blacklist) && in_array($prefix, $blacklist, true)) {
                continue;
            }

            // B. ホワイトリスト判定: 指定がある場合、含まれていなければ除外
            if (!empty($whitelist) && !in_array($prefix, $whitelist, true)) {
                continue;
            }

            $row_keywords = json_decode($row['top_keywords'], true);
            if (!is_array($row_keywords)) continue;

            // 1. 相手側のベクトルをデコード（SQLでSELECT済みであること）
            $row_vector = json_decode($row['vector_data'], true);

            // --- 高速化された類似度計算を使用 ---
            $similarity = self::calculate_similarity_score($base_data['keywords'], $row_keywords);

            // 2. ベクトル類似度（コサイン類似度）の計算を追加
            $vec_sim = 0.0;
            if (!empty($base_data['vector']) && !empty($row_vector)) {
                $vec_sim = self::calculate_cosine_similarity($base_data['vector'], $row_vector);
            }

            $is_vector = false;
            if( $vec_sim !== 0.0){
                $is_vector = true;
            }

            // --- ハイブリッド・スコアリング ---
            // ベクトル(100点満点ベース) ＋ キーワードボーナス(log)
            $hybrid_base = ($vec_sim * 100) + (log($similarity + 1) * 10);

            // 最後に ai_score (記事の信頼性/充実度) を掛けて最終スコアとする
            $total_score = $hybrid_base * ((float)$row['ai_score'] * 0.01);


            $scored_list[] = [
                'post_id' => (int)$row['post_id'],
                'score'   => round($total_score, 2),
                'is_vector' => $is_vector
            ];
        }

        // 4. スコア順にソート（降順）
        usort($scored_list, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        // 5. 指定件数で切り出し
        return array_slice($scored_list, 0, $limit);
    }

    /**
     * 特定のポストのAIメタデータを取得（キャッシュ対応）
     */
    public static function get_ai_metadata(int $post_id): ?array {
        global $wpdb;

        $cache_key = "ai_meta_{$post_id}";
        $cached = Dy::get('system')['ai_cache'][$cache_key] ?? null;
        if ($cached) return $cached;

        $table_name = $wpdb->prefix . 'kx_ai_metadata';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT top_keywords, vector_data, ai_score FROM {$table_name} WHERE post_id = %d",
            $post_id
        ), ARRAY_A);

        if (!$row) return null;

        $data = [
            'keywords' => json_decode($row['top_keywords'], true),
            'vector'   => json_decode($row['vector_data'], true) ?: [],
            'ai_score' => (float)$row['ai_score']
        ];

        $system = Dy::get('system');
        $system['ai_cache'][$cache_key] = $data;
        Dy::set('system', $system);

        return $data;
    }




    /**
     * 二つのキーワード集合間の類似度スコアを算出する（高速化版）
     * * [修正内容]:
     * 2重ループを廃止し、isset()によるハッシュマップ引き当てに変更。
     * 計算量を O(N*M) から O(N) に削減。
     */
    private static function calculate_similarity_score(array $base, array $target): float {
        $score = 0.0;

        // base: {"単語A": 10, "単語B": 5}
        // target: {"単語A": 8, "単語C": 3}

        foreach ($base as $word => $base_count) {
            // target側に同じ単語が存在するかO(1)でチェック
            if (isset($target[$word])) {
                // 両方の出現頻度を掛け合わせてスコア加算
                $score += ($base_count * $target[$word]);
            }
        }

        return (float)$score;
    }

    /**
     * AIスコアと統計スコアの乖離（ギャップ）を分析し、異常値を抽出してテーブル表示するショートコード。
     * * [概要]
     * 統計的体裁(ai_score_stat)とAI文脈評価(ai_score_context)の差分を計算し、
     * 「内容が薄い(thin)」または「隠れたお宝(treasure)」記事をリストアップします。
     * * @param array|string $atts {
     * ショートコード属性
     * @type string $type      抽出タイプ。'thin' (統計 > AI: 見た目倒し) または 'treasure' (AI > 統計: お宝)。デフォルト 'thin'。
     * @type int    $limit     表示件数。デフォルト 20。
     * @type float  $threshold 乖離度のしきい値。この値以上の差がある記事を抽出。デフォルト 15。
     * }
     * @return string HTML形式の比較テーブル、または該当なしのメッセージ。
     */
    public static function render_knowledge_gap_report( $atts ): string {
        global $wpdb;
        $a = shortcode_atts([
            'type' => 'thin', // 'thin' か 'treasure'
            'limit' => 20,
            'threshold' => 15 // 乖離のしきい値
        ], $atts);

        $operator = ($a['type'] === 'thin') ? '>' : '<';
        $order = ($a['type'] === 'thin') ? 'DESC' : 'ASC';

        // 乖離（diff）を計算。statからcontextを引く。
        // 正の値が大きければ「見た目倒し(thin)」、負の値が大きければ「お宝(treasure)」
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT post_id, ai_score_stat, ai_score_context,
                (ai_score_stat - ai_score_context) as diff
            FROM wp_kx_ai_metadata
            HAVING diff $operator %f
            ORDER BY diff $order
            LIMIT %d
        ", ($a['type'] === 'thin' ? $a['threshold'] : -$a['threshold']), $a['limit']));

        if (!$results) return '<p>該当する記事は見つかりませんでした。</p>';

        $html = '<table class="kx-anomaly-table"><tr><th>ID</th><th>タイトル</th><th>統計</th><th>文脈</th><th>乖離度</th></tr>';
        foreach ($results as $res) {
            $link =  \Kx\Components\KxLink::render($res->post_id );
            $title = get_the_title($res->post_id);
            $diff_display = round($res->diff, 2);
            $html .= "<tr>
                <td>{$res->post_id}</td>
                <td>{$link}</td>
                <td>{$res->ai_score_stat}</td>
                <td>".round($res->ai_score_context, 2)."</td>
                <td><strong>{$diff_display}</strong></td>
            </tr>";
        }
        $html .= '</table>';
        return $html;

    }

    /**
     * 二つのベクトル間のコサイン類似度を算出する
     * 1.0 に近いほど似ている。-1.0〜1.0 の範囲。
     */
    private static function calculate_cosine_similarity(array $vec1, array $vec2): float {
        $dot_product = 0.0;
        $mag1 = 0.0;
        $mag2 = 0.0;

        foreach ($vec1 as $i => $v1) {
            $v2 = $vec2[$i] ?? 0;
            $dot_product += $v1 * $v2;
            $mag1 += $v1 ** 2;
            $mag2 += $v2 ** 2;
        }

        $denominator = sqrt($mag1) * sqrt($mag2);
        return ($denominator == 0) ? 0 : $dot_product / $denominator;
    }

}