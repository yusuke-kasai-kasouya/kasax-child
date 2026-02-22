<?php
/**
 * [Path]: inc/database/class-dbkx-0-post-search-mapper.php
 * 検索特化型テーブル (kx_0) の管理クラス
 */

namespace Kx\Database;

use Kx\Core\DynamicRegistry as Dy;

class dbkx0_PostSearchMapper extends Abstract_DataManager {

    /**
     * テーブル名を取得（内部用）
     */
    protected static function t() {
        global $wpdb;
        return $wpdb->prefix . 'kx_0';
    }


    /**
     * ContextManager からの呼び出しを受けて同期を実行
     */
    public static function sync($post_id) {

        // 1. 再帰ガード (同一リクエスト内での重複処理防止)
        if (Dy::get('dbkx0_processed_' . $post_id)) {
            return;
        }
        Dy::set('dbkx0_processed_' . $post_id, true);

        // 2. path_index から最新情報を取得
        // ※ Dy::set_path_index 内で $entry['depth'] も計算・保持されています
        $entry = Dy::get_path_index($post_id);
        if (!$entry || !$entry['valid']) {
            return;
        }

        // 3. DBの現状を確認（キャッシュがあればそこから取得）
        $db_row = self::load_raw_data($post_id);

        // 4. Dirty Check：WPの最終更新時刻が一致していれば書き込みをスキップ
        if ($db_row && isset($db_row['wp_updated_at']) && $db_row['wp_updated_at'] === $entry['modified']) {
            // DB書き込みは不要だが、実行中のキャッシュにデータがない場合は注入しておく
            Dy::set_content_cache($post_id, 'db_kx0', $db_row);
            return;
        }

        // --- 5. 保存用データの構築 (テーブル定義に完全準拠) ---
        $save_data = [
            'id'            => $post_id,
            'title'         => $entry['full'],
            'depth'         => $entry['depth'],
            'type'          => $entry['type'],
            'wp_updated_at' => $entry['modified'],
        ];

        // 6. UPSERT 実行 (ON DUPLICATE KEY UPDATE)
        global $wpdb;
        $table_name = self::t();
        $wpdb->query($wpdb->prepare(
            "INSERT INTO $table_name (id, title, depth, type, wp_updated_at)
             VALUES (%d, %s, %d, %s, %s)
             ON DUPLICATE KEY UPDATE
             title = VALUES(title),
             depth = VALUES(depth),
             type = VALUES(type),
             wp_updated_at = VALUES(wp_updated_at)",
            $save_data['id'],
            $save_data['title'],
            $save_data['depth'],
            $save_data['type'],
            $save_data['wp_updated_at']
        ));

        // --- 7. キャッシュの更新 ---
        // 次回の get_content_cache 時にDBを見に行かなくて済むよう、Dyに直接注入
        Dy::set_content_cache($post_id, 'db_kx0', $save_data);
    }


    /**
     * type や depth の変更も検知対象に含めた同期処理。メンテナンス用。
     * 既存データの階層深さ(depth)の一括補填などにも使用。
     */
    public static function sync_with_type_check($post_id , $mode = '') {

        // 1. path_index (最新の状態) を取得
        $entry = Dy::set_path_index($post_id , $mode);
        if (!$entry || !$entry['valid']) return;

        // 2. DBの現状を確認
        $db_row = self::load_raw_data($post_id);

        // 3. 拡張された Dirty Check (時刻、タイプ、深さを比較)
        $is_dirty = false;
        if (!$db_row) {
            $is_dirty = true; // 新規
        } else {
            // いずれかの値が現在のファイル（entry）とDB（db_row）で異なれば更新対象
            if (
                $db_row['wp_updated_at'] !== $entry['modified'] ||
                $db_row['type'] !== $entry['type'] ||
                (isset($db_row['depth']) && (int)$db_row['depth'] !== (int)$entry['depth']) ||
                !isset($db_row['depth']) // DB側がNULLの場合も更新対象とする
            ) {
                $is_dirty = true;
            }
        }

        // 変更がなければ、Dyキャッシュに現在の値をセットして終了
        if (!$is_dirty) {
            Dy::set_content_cache($post_id, 'db_kx0', $db_row);
            return;
        }

        // --- 4. 保存用データの構築 ---
        $save_data = [
            'id'            => $post_id,
            'title'         => $entry['full'],
            'depth'         => $entry['depth'],
            'type'          => $entry['type'],
            'wp_updated_at' => $entry['modified'],
        ];

        // 5. UPSERT 実行
        global $wpdb;
        $table_name = self::t();
        $wpdb->query($wpdb->prepare(
            "INSERT INTO $table_name (id, title, depth, type, wp_updated_at)
             VALUES (%d, %s, %d, %s, %s)
             ON DUPLICATE KEY UPDATE
             title = VALUES(title),
             depth = VALUES(depth),
             type = VALUES(type),
             wp_updated_at = VALUES(wp_updated_at)",
            $save_data['id'],
            $save_data['title'],
            $save_data['depth'],
            $save_data['type'],
            $save_data['wp_updated_at']
        ));

        // 6. キャッシュの更新
        Dy::set_content_cache($post_id, 'db_kx0', $save_data);
    }




    /**
     * 指定されたIDの正規化タイトルを取得
     */
    public static function get_title($post_id) {
        // 1. キャッシュ(raw層)を確認
        $raw = self::load_raw_data($post_id);

        // 2. キャッシュがない、または title が存在しない場合は sync を実行
        if (!$raw || !isset($raw['title'])) {
            self::sync($post_id);

            // sync 後に再度ロードして最新の状態を返す
            $raw = self::load_raw_data($post_id);
        }

        return $raw['title'] ?? '';
    }


    /**
     * タイトルから一致する全てのPost IDを取得する
     * * @param string $title 検索したい完全一致タイトル
     * @return int[] 一致したIDの配列。見つからない場合は空配列 [] を返す。
     */
    public static function get_ids_by_title($title) {
        global $wpdb;
        $table_kx0 = self::t();

        // get_col を使うと、特定カラム（id）を 1次元配列で取得できる
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM $table_kx0 WHERE title = %s",
            $title
        ));


        // get_colは該当なしで空配列を返すため、そのまま返却
        return array_map('intval', $ids); // 文字列型を数値型にキャストしておくと安全
    }


    /**
     * タイトルの前方一致で一致する全てのPost IDを取得する
     * * @param string $title_prefix 検索したいタイトルの開始文字列
     * @return int[] 一致したIDの配列。見つからない場合は空配列 [] を返す。
     */
    public static function get_ids_by_title_prefix($title_prefix) {
        global $wpdb;
        $table_kx0 = self::t();

        // LIKE句で使用するために、検索文字列の末尾に % を付与する
        // prepareの第2引数で % を含めた文字列を渡すのが安全な書き方です
        $query = $wpdb->prepare(
            "SELECT id FROM $table_kx0 WHERE title LIKE %s",
            $wpdb->esc_like($title_prefix) . '%'
        );

        $ids = $wpdb->get_col($query);

        return array_map('intval', $ids);
    }



    /**
     * 指定されたIDがシステム内に存在するか判定する（二段構えチェック）
     * 1. 高速な kx_0 テーブルを優先確認
     * 2. 見つからない場合のみ、念のため wp_posts テーブルを確認
     *
     * @param int $post_id
     * @return bool 存在すれば true
     */
    public static function is_id_exists($post_id) {
        global $wpdb;
        $post_id = intval($post_id);
        if ($post_id <= 0) return false;

        // --- Step 1: kx_0 (独自インデックス) を確認 ---
        $table_kx0 = self::t();
        $exists_in_kx0 = $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM $table_kx0 WHERE id = %d LIMIT 1",
            $post_id
        ));

        if ($exists_in_kx0) {
            return true;
        }

        // --- Step 2: wp_posts (オリジナル) を確認 ---
        // まだ sync されていない新着記事などの可能性があるため
        $exists_in_wp = $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM $wpdb->posts WHERE ID = %d LIMIT 1",
            $post_id
        ));

        if ($exists_in_wp) {
            // 見つかった場合は、次回の高速化のために sync しておくのも手です
            // self::sync($post_id);
            return true;
        }

        return false;
    }


    /**
     * kx_0内の重複タイトルを検出し、リンク付きのHTMLリストを返す
     *
     * @return string 実行結果のHTML
     */
    public static function maintenance_get_duplicate_titles() {
        global $wpdb;
        $table_kx0 = self::t();

        // 1. SQLで重複を高速抽出
        $results = $wpdb->get_results("
            SELECT title, COUNT(*) as count, GROUP_CONCAT(id) as ids
            FROM $table_kx0
            GROUP BY title
            HAVING count > 1
        ");

        if (empty($results)) {
            return "<p style='color:gray;'>━DB kx_0 Maintenance━重複なし</p>";
        }

        // 2. スクリプトの読み込み
        /*
        wp_enqueue_script(
            'javascript',
            get_stylesheet_directory_uri().'/../kasax_child/js/javascript.js',
            array( 'jquery' ),
            '1.0',
            true
        );
        */

        $count = count($results);
        $ret = "<div class='question' style='color:red;'>━DB kx_0 Maintenance━重複：{$count} 件</div>";
        $ret .= '<div class="answer" style="background-color: black; padding:10px; border:1px solid #444;">';

        foreach ($results as $row) {
            $ids = explode(',', $row->ids);
            $ret .= 'タイトル:<span style="color:red;"> ' . esc_html($row->title) . '</span> | 件数: ' . count($ids) . '<br>';
            $ret .= '投稿リンク:<br>';
            foreach ($ids as $id) {
                $link = get_permalink($id);
                // 変数名を $ret に統一
                $ret .= '<a href="' . $link . '" target="_blank" style="color:#00a0d2;">' . $link . '</a><br>';
            }
            $ret .= '<br>';
        }
        $ret .= '</div>';

        return $ret;
    }


    /**
     * 独自DBの整合性維持：WPから削除、または非公開（ゴミ箱等）になった投稿のインデックスを削除
     */
    public static function maintenance_orphan_cleanup() {
        global $wpdb;
        $table_kx0 = self::t();

        // --- A. Cleanup（幽霊・非公開・ゴミ箱の削除） ---
        // 以下のいずれかに該当するレコードを kx_0 から削除
        // 1. wp_postsに存在しない（物理削除済み）
        // 2. post_type が 'post' ではない
        // 3. post_status が 'publish' ではない（trash, draft, future, private等を除外）
        $deleted_count = $wpdb->query("
            DELETE kx FROM $table_kx0 kx
            LEFT JOIN $wpdb->posts wp ON kx.id = wp.ID
            WHERE wp.ID IS NULL
               OR wp.post_type != 'post'
               OR wp.post_status != 'publish'
        ");

        if ($deleted_count > 0) {
            return "<p style='color:blue;'>━DB kx_0━不整合/非公開レコード：{$deleted_count} 件をパージしました。</p>";
        } else {
            return "<p style='color:gray;'>━DB kx_0： クリーンな状態です（幽霊・非公開レコード無し）。</p>";
        }
    }



    /**
     * 独自DB（kx_0）をWPの現状に一括適合させる（一撃メンテナンス）
     */
    public static function maintenance_full_sync() {
        global $wpdb;
        $table_kx0 = self::t();

        // --- A. Cleanup（幽霊削除） ---
        // kx_0 にあって wp_posts にない ID を一括削除（SQL一発で完了）
        $wpdb->query("
            DELETE kx FROM $table_kx0 kx
            LEFT JOIN $wpdb->posts wp ON kx.id = wp.ID
            WHERE wp.ID IS NULL
        ");

        // --- B. Push Sync（不足分・更新分の同期） ---
        // 公開済みの全投稿IDを取得
        $post_ids = $wpdb->get_col("SELECT ID FROM $wpdb->posts WHERE post_type = 'post' AND post_status = 'publish'");

        foreach ($post_ids as $post_id) {
            // すでに実装済みの sync を呼ぶだけ。
            // 内部で Dirty Check が動くので、変更がない記事はスルーされ、非常に高速。
            self::sync($post_id);
        }
    }


    /**
     * type属性を含めた全データの再同期メンテナンス
     */
    public static function maintenance_type_rebuild() {
        global $wpdb;

        // 公開済み投稿のIDをすべて取得
        $post_ids = $wpdb->get_col("
            SELECT ID FROM $wpdb->posts
            WHERE post_type = 'post' AND post_status = 'publish'
        ");

        $count = 0;
        foreach ($post_ids as $post_id) {
            // typeチェック付きの同期を実行
            self::sync_with_type_check($post_id ,'maintenance');
            $count++;
        }
    }


    /**
     * DBから生のデータを取得し、Dyキャッシュに格納する
     * * @param int $post_id
     * @return array|null
     */
    public static function load_raw_data($post_id) {
        return parent::load_raw_data_common($post_id, 'db_kx0');
    }

}