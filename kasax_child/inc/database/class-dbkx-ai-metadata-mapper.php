<?php
/**
 * [Path]: inc/database/class-db-kx-ai-metadata-mapper.php
 * AI解析メタデータテーブル (kx_ai_metadata) の管理クラス
 */

namespace Kx\Database;

use Kx\Core\DynamicRegistry as Dy;

class dbKxAiMetadataMapper extends Abstract_DataManager {

    /**
     * テーブル名を取得
     */
    protected static function t() {
        global $wpdb;
        return $wpdb->prefix . 'kx_ai_metadata';
    }

    /**
     * キャッシュの同期（読み込み・レジストリ更新のみ）
     * DBへの書き込みは行わず、実行中のキャッシュ状態を整える
     */
    public static function sync($post_id, $data = []) {
        // 1. 再帰ガード
        if (Dy::get('db_ai_meta_processed_' . $post_id)) {
            return;
        }
        Dy::set('db_ai_meta_processed_' . $post_id, true);

        // 2. path_index から最新情報を取得
        $entry = Dy::get_path_index($post_id);
        if (!$entry || !$entry['valid']) {
            return;
        }

        // 3. DBの現状を確認
        $db_row = self::load_raw_data($post_id);

        //var_dump($db_row);

        // 4. キャッシュ用データの構築
        // $data が渡された場合はそれを優先し、なければDBの値を、それもなければ初期値をセット
        $save_data = [
            'post_id'           => $post_id,
            'post_modified'     => $entry['modified'],
            'top_keywords'      => $data['top_keywords'] ?? ($db_row['top_keywords'] ?? '{}'),
            'vector_status'     => $data['vector_status'] ?? ($db_row['vector_status'] ?? 0),
            'ai_score_stat'     => $data['ai_score_stat'] ?? ($db_row['ai_score_stat'] ?? 0),
            'ai_score_context'  => $data['ai_score_context'] ?? ($db_row['ai_score_context'] ?? 0),
            'ai_score'          => $data['ai_score'] ?? ($db_row['ai_score'] ?? 0),
            'ai_score_deviation'=> $data['ai_score'] ?? ($db_row['ai_score_deviation'] ?? 0),
        ];

        // 5. キャッシュ（DynamicRegistry）の更新のみ実行
        Dy::set_content_cache($post_id, 'db_kx_ai', $save_data);
    }

    /**
     * 特定のメタデータを取得
     */
    public static function get_ai_meta($post_id) {
        $raw = self::load_raw_data($post_id);

        // データがなければ sync (キャッシュ注入) を試行
        if (!$raw) {
            self::sync($post_id);
            $raw = self::load_raw_data($post_id);
        }

        return $raw;
    }

    /**
     * DBから生のデータを取得し、Dyキャッシュに格納する
     */
    public static function load_raw_data($post_id) {
        return parent::load_raw_data_common($post_id, 'db_kx_ai', 'post_id');
    }

    /**
     * 孤立チェック・クリーンアップ
     * 基幹テーブル (kx_0) に存在しないIDが AIテーブルにある場合、そのレコードを削除する
     */
    public static function maintenance_cleanup_isolated_records() {
        global $wpdb;
        $table_kx0 = $wpdb->prefix . 'kx_0';
        $table_ai  = self::t();

        // 1. AIテーブルにあって kx_0にないIDを特定
        $isolated_ids = $wpdb->get_col("
            SELECT ai.post_id
            FROM $table_ai ai
            LEFT JOIN $table_kx0 k0 ON ai.post_id = k0.id
            WHERE k0.id IS NULL
        ");

        if (empty($isolated_ids)) {
            return "━DB kx_ai_metadata━孤立レコード：0";
        }

        $count = 0;
        foreach ($isolated_ids as $id) {
            // 2. 削除実行
            self::delete_record((int)$id);
            $count++;
        }

        return "━DB kx_ai_metadata━孤立パージ：[{$count}] 件";
    }

    /**
     * AI解析フラグをリセット（未処理状態へ戻す）
     * 投稿保存時やタイトル変更時に呼び出し、Python側の再巡回を促す
     */
    public static function reset_vector_status(int $post_id) {
        global $wpdb;
        $table = self::t();

        // 1. DBのフラグを 0 に更新（レコードがなければ作成）
        $wpdb->query($wpdb->prepare("
            INSERT INTO {$table} (post_id, vector_status)
            VALUES (%d, 0)
            ON DUPLICATE KEY UPDATE vector_status = 0
        ", $post_id));
    }


    /**
     * レコードの物理削除とキャッシュ破棄
     */
    public static function delete_record($post_id) {
        global $wpdb;
        $table = self::t();

        $wpdb->delete($table, ['post_id' => $post_id], ['%d']);

        // キャッシュも破棄
        Dy::set_content_cache($post_id, 'db_kx_ai', null);
    }
}