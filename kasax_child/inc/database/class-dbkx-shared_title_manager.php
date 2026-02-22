<?php
/**
 * [Path]: inc/database/class-dbkx-shared_title_manager.php
 */

namespace Kx\Database;

use Kx\Core\SystemConfig as Su;
use Kx\Core\DynamicRegistry as Dy;
use \Kx\Database\dbkx1_DataManager as dbkx1;
use \Kx\Database\Hierarchy;
//use \Kx\Utils\KxMessage as Msg;

class dbkx_SharedTitleManager extends Abstract_DataManager {

    /**
     * テーブル名を取得（内部用）
     */
    protected static function t() {
        global $wpdb;
        return $wpdb->prefix . 'kx_shared_title';
    }


    /**
     * ContextManager からの呼び出しを受けて同期を実行（司令塔）
     * 系統（Β, γ, σ, δ）を跨いで「タイトル末尾」を基準に名寄せを行う
     */
    public static function sync($post_id) {
        // 1. ガード処理（二重処理・再帰ループ防止） [cite: 12]
        $lock_key = 'dbkx_shared_processed_' . (int)$post_id;
        if (Dy::get($lock_key)) return;
        Dy::set($lock_key, true);

        // 2. 解析データの準備（Dyからの取得と対象判定） [cite: 10, 11]
        $path_index = Dy::set_path_index($post_id);
        if (!self::is_sync_target($path_index)) return null;

        $parts = $path_index['parts'] ?? [];
        if (empty($parts)) return null;

        // 3. 系統（接頭辞）の判定とベースタイトルの抽出
        $prefix = $parts[0];
        $column_map = ['Β' => 'id_lesson', 'γ' => 'id_sens', 'σ' => 'id_study', 'δ' => 'id_data'];
        $target_column = $column_map[$prefix] ?? null;

        if (!$target_column) return null;

        // 接頭辞（系統符号）を除いた「概念名」をキーにする
        $base_title = implode('≫', array_slice($parts, 1));
        $label = self::extract_label($path_index);

        // 4. 同期データの構築（フェーズ別保存戦略） [cite: 12]
        $data = [
            'title'        => $base_title,
            $target_column => (int)$post_id,
            'label'        => $label
        ];

        if ($prefix === 'δ') {
            // 資料系統（δ）の場合は本文解析を含むペイロードを構築 [cite: 15]
            $sync_payload = self::build_sync_payload($post_id, $label, $base_title, $path_index);
            if ($sync_payload) {
                $data = array_merge($data, $sync_payload);
            }
        } else {
            // それ以外の系統は既存レコードからメタデータを継承（名寄せ）
            $existing = self::get_record_by_title($base_title);
            $data['date'] = $existing['date'] ?? 0;
            $data['tag']  = $existing['tag']  ?? null;
            $data['json'] = $existing['json'] ?? null;
        }

        // 5. DB書き込み（内部で Dirty Check を行う Abstract_DataManager 準拠を想定） [cite: 1, 15]
        return self::update_table($data, $post_id);
    }


    /**
     * 同期対象かどうかの検証
     */
    private static function is_sync_target($path_index) {
        if (!$path_index || !$path_index['valid']) return false;

        $arc_type = $path_index['type'] ?? '';

        // Root系 または List系 のいずれかに含まれていれば対象
        return isset(Su::SHARED_ROOT_TYPES[$arc_type]) || isset(Su::SHARED_LABEL_TYPES[$arc_type]);
    }





    /**
     * タイトル名からレコードをまるごと取得する
     * @param string $title 概念名
     * @param string $output ARRAY_A (連想配列) または OBJECT
     * @return array|object|null レコードが存在しない場合は null
     */
    public static function get_record_by_title($title, $output = ARRAY_A) {
        global $wpdb;
        $table = self::t();

        if (empty($title)) return null;

        // title は一意であることを前提に get_row を使用
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE title = %s", $title),
            $output
        );

        return $row ?: null;
    }


    /**
     * ラベル抽出
     */
    private static function extract_label($path_index) {
        $arc_type = $path_index['type'] ?? '';

        // List系に含まれている場合のみタイプ名をラベルとして返す
        if (isset(Su::SHARED_LABEL_TYPES[$arc_type])) {
            return $arc_type;
        }

        return '';
    }


    /**
     * コンテンツから日付とメタ情報を抽出し、DBカラムごとに成形して返す
     * 「＠概要」記事の場合は親記事へデータを委譲する
     */
    public static function build_sync_payload($post_id, $label, $base_title, $path_index) {

        // --- 1. 解析対象の決定（概要記事があればそこからデータを吸い上げる） ---
        $overview_from = dbkx1::get_overview_from($post_id);
        $is_overview_to = dbkx1::get_overview_to($post_id);

        $target_post_id = $overview_from ?: $post_id;
        $post = get_post($target_post_id);

        if (!$post) return [];

        $content = $post->post_content;
        $meta_data = [];

        // --- 2. メタ情報およびキャラクター特殊解析 ---
        if ($label) {
            $pickup_list = Su::get('system_internal_schema')['db_shared_json_works'] ?? [];
            $meta_data = self::extract_metadata($content, $pickup_list);

            // キャラクター系統かつ特定の条件下で作品レコードから日付を継承
            $is_prim_main = ($label === 'arc_shared_works_character_prim' && !$overview_from);
            $is_subs_overview = ($label === 'arc_shared_works_character_subs' && $is_overview_to && ($path_index['depth'] ?? 0) === 7);


            if ($is_prim_main || $is_subs_overview) {
                $parts = $path_index['parts'] ?? [];
                // 階層構造 [1:系統, 2:ジャンル, 3:作品名] を前提とする
                if (count($parts) >= 4) {
                    $target_title = "{$parts[1]}≫{$parts[2]}≫{$parts[3]}";
                    $parent_record = self::get_record_by_title($target_title);

                    $meta_data['work_title'] = $parts[3];
                    $meta_data['character']  = self::extract_earliest_date_from_json($parent_record['json'] ?? []);
                }
            }
        }

        // --- 3. 日付情報抽出とデータ統合 ---
        $date_info    = self::extract_date_optimized($content, $label);
        $primary_date = $date_info['primary'] ?? 0;
        $json_data    = array_merge($meta_data, $date_info['extended'] ?? []);

        // --- 4. 分岐：自分が「＠概要」記事だった場合（親への委譲処理） ---
        if ($is_overview_to && \Kx\Database\Hierarchy::get_parent($post_id) == $is_overview_to) {
            // 末尾の「≫概要名」を削除して親のタイトルを特定
            $parent_title = preg_replace('/≫[^≫]+$/u', '', $base_title);

            if ($parent_title !== $base_title) {
                self::update_parent_shared_data($parent_title, $is_overview_to, $primary_date, $json_data);
            }

            // 概要記事自身は Shared レコードを空にする（実体は親レコードに集約されるため）
            return [
                'date' => 0,
                'tag'  => '',
                'json' => [],
            ];
        }

        // --- 5. 本体記事の最終データ成形 ---
        // 概要記事からデータを吸い上げている場合は、マージ済みの最新JSONをDBから再取得
        if ($overview_from) {
            $current_record = self::get_record_by_title($base_title);
            if ($current_record) {
                $json_data = $current_record['json'];
            }
        }

        return [
            'date' => $primary_date,
            'tag'  => self::extract_tags($post_id),
            'json' => $json_data,
        ];
    }


    /**
     * JSONデータの中から8桁の数値（日付）をすべて探し、その中の最小値を返す
     * @param string|array $json_data JSON文字列または連想配列
     * @return int|null 最小の日付（8桁）、見つからない場合はnull
     */
    public static function extract_earliest_date_from_json($json_data) {
        // 文字列ならデコード、配列ならそのまま使用
        $data = is_string($json_data) ? json_decode($json_data, true) : $json_data;

        if (!is_array($data)) return null;

        $dates = [];

        // 再帰的に全ての値をチェック（ネストされていても対応可能）
        array_walk_recursive($data, function($value) use (&$dates) {
            // 1. 数値（または数値形式の文字列）であること
            // 2. 8桁であること (10000000 〜 99991231 等の範囲)
            if (is_numeric($value)) {
                $val_int = (int)$value;
                if ($val_int >= 10000000 && $val_int <= 99991231) {
                    $dates[] = $val_int;
                }
            }
        });

        if (empty($dates)) return null;

        // 最小値（最も古い日付）を返す
        return min($dates);
    }


    /**
     * 親記事のSharedレコードに対して、概要記事から吸い上げたデータを書き込む
     */
    private static function update_parent_shared_data($parent_title, $parent_id, $date, $json) {
        global $wpdb;
        $table = self::t();

        // 1. 親の既存データを取得
        $parent_data = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE title = %s", $parent_title),
            ARRAY_A
        );

        if (!$parent_data) {
            $parent_data = ['title' => $parent_title];
        }

        // 2. データのマージ
        $parent_data['date'] = $date ?: ($parent_data['date'] ?? 0);

        // JSONのマージ処理
        $existing_json = [];
        if (!empty($parent_data['json'])) {
            // DBから取得した直後は文字列なのでデコードが必要
            $existing_json = is_string($parent_data['json'])
                ? json_decode($parent_data['json'], true)
                : $parent_data['json'];
        }

        // 概要側のデータをマージ
        $merged_json = array_merge((array)$existing_json, (array)$json);

        // --- ★ここが重要：保存用にエンコードする ---
        $parent_data['json'] = wp_json_encode($merged_json, JSON_UNESCAPED_UNICODE);

        // 3. 更新時刻
        $parent_data['time'] = time();

        // 4. DB実行
        // $wpdb->replace は成功すると影響行数（新規1, 置換2）を返す
        $wpdb->replace($table, $parent_data);
    }



    /**
     * 最軽量：strpos を用いたメタ情報抽出
     */
    private static function extract_metadata($content, $pickup_list) {
        $results = [];
        if (empty($content) || empty($pickup_list)) return $results;

        foreach ($pickup_list as $key) {
            $needle = $key . '：';
            $pos = strpos($content, $needle);
            if ($pos === false) continue;

            $start = $pos + strlen($needle);
            $end = strpos($content, "\n", $start);

            $val = ($end === false) ? substr($content, $start) : substr($content, $start, $end - $start);
            $results[$key] = trim($val);
        }
        return $results;
    }

    /**
     * 最軽量・最適化版：日付情報（Date：）の抽出
     */
    private static function extract_date_optimized($content, $label) {
        $primary_date = 0;
        $extended_dates = [];
        $last_pos = 0;
        $needle = 'Date：';

        while (($pos = strpos($content, $needle, $last_pos)) !== false) {
            $start = $pos + strlen($needle);
            $end = strpos($content, "\n", $start);
            $line = ($end === false) ? substr($content, $start) : substr($content, $start, $end - $start);
            $line = trim($line);

            if ($line !== '') {
                $date_str = $line;
                $extracted_tag = '';

                // --- 【超高速ガード】2つ目の「：」の有無で分岐 ---
                $colon_pos = strpos($line, '：');

                if ($colon_pos !== false) {
                    // 「：」がある ＝ 拡張日付（ラベルあり）
                    if (!$label) {
                        // 作品解析不要なページなら、この行の解析（正規表現・パース）を全てスキップ
                        if ($primary_date !== 0) break; // 主日付が取れていれば終了
                        $last_pos = ($end === false) ? strlen($content) : $end;
                        continue;
                    }

                    // ラベルが必要な場合のみ、最小限の正規表現で分割
                    if (preg_match('/^([^：]+)：(.*)$/u', $line, $m)) {
                        $extracted_tag = trim($m[1]);
                        $date_str = trim($m[2]);
                    }
                }

                // 必要な行だけがここ（パース処理）に到達する
                $val = self::parse_date_string($date_str);

                if ($extracted_tag !== '') {
                    $extended_dates[$extracted_tag] = $val;
                } else {
                    $primary_date = $val;
                    // $labelがない（主日付のみで良い）場合は、ここで即座に終了
                    if (!$label) break;
                }
            }

            $last_pos = ($end === false) ? strlen($content) : $end;
        }

        $res = ['primary' => $primary_date];
        if ($label) $res['extended'] = $extended_dates;

        return $res;
    }

    /**
     * A/Bロジック振り分け
     */
    private static function parse_date_string($str) {
        $str = mb_convert_kana($str, 'n', 'UTF-8');
        $str = str_replace([',', ' '], '', $str);

        if (preg_match('/[億万]|年前|紀元前/u', $str)) {
            return self::logic_b_special($str);
        }
        return self::logic_a_standard($str);
    }

    /**
     * ロジックA：標準
     */
    private static function logic_a_standard($str) {
        $str = str_replace(['西暦', '-'], ['', '/'], $str);
        $pattern = '/^(-?\d+)(?:年|\/)?(\d{1,2})?(?:月|\/)?(\d{1,2})?(?:日)?/u';

        if (preg_match($pattern, $str, $m)) {
            return self::format_to_bigint(
                (int)$m[1],
                (isset($m[2]) && $m[2] !== '') ? (int)$m[2] : 0,
                (isset($m[3]) && $m[3] !== '') ? (int)$m[3] : 0
            );
        }
        return 0;
    }

    /**
     * ロジックB：特殊（歴史・宇宙）
     */
    private static function logic_b_special($str) {
        $year_num = self::convert_kanji_to_num($str);
        if ($year_num === 0) return 0;

        if (preg_match('/年前|紀元前/u', $str)) {
            $year_num = -abs($year_num);
        }
        return self::format_to_bigint($year_num, 99, 99);
    }

    /**
     * BIGINT形式合成 (Year * 10000 + MMDD)
     */
    private static function format_to_bigint($y, $m = 0, $d = 0) {
        $year = (int)$y;
        if ($year === 0) return 0;

        $month = ($m > 0 && $m <= 12) ? (int)$m : 99;
        $day   = ($d > 0 && $d <= 31) ? (int)$d : 99;

        $sign = ($year < 0) ? -1 : 1;
        $abs_year = abs($year);
        $combined = ($abs_year * 10000) + ($month * 100) + $day;

        return $combined * $sign;
    }

    /**
     * 漢字・単位の数値変換
     */
    private static function convert_kanji_to_num($str) {
        $str = mb_convert_kana($str, 'n', 'UTF-8');
        $str = str_replace([',', ' ', '　', '-', '西暦', '年前', '紀元前'], '', $str);

        $total = 0;
        if (preg_match('/(\d+)億/u', $str, $m)) {
            $total += (int)$m[1] * 100000000;
            $str = preg_replace('/' . $m[1] . '億/u', '', $str, 1);
        }
        if (preg_match('/(\d+)万/u', $str, $m)) {
            $total += (int)$m[1] * 10000;
            $str = preg_replace('/' . $m[1] . '万/u', '', $str, 1);
        }
        if (preg_match('/^\d+/u', $str, $m)) {
            $total += (int)$m[0];
        }
        return (int)$total;
    }


    /**
     * 最軽量を追求したメタ情報抽出
     */
    public static function extract_metadata_to_json($content, $json = []) {
        $pickup_list = Su::get('system_internal_schema')['db_shared_json_works'] ?? [];
        if (empty($pickup_list) || empty($content)) return $json;

        $_array = [];

        foreach ($pickup_list as $key) {
            $needle = $key . '：';

            // 1. 最速の strpos で出現位置を探す
            $pos = strpos($content, $needle);
            if ($pos === false) continue;

            // 2. 見つかった場合、その位置から行末までを切り出す
            // 行末（\n）までの長さを計算
            $line_end = strpos($content, "\n", $pos);

            if ($line_end === false) {
                // 文末までが値
                $line_content = substr($content, $pos + strlen($needle));
            } else {
                $line_content = substr($content, $pos + strlen($needle), $line_end - $pos - strlen($needle));
            }

            // 3. 値をクリーンアップして格納
            $_array[$key] = trim($line_content);
        }

        return array_merge($json, $_array);
    }





    /**
     * タグ情報の取得（dbkx1から取得）
     * dbkx1側で既に |tag| 形式に正規化されていることを前提とする
     */
    private static function extract_tags($post_id) {
        // dbkx1::get_tag が |タグA| |タグB| という文字列を返す場合
        return dbkx1::get_tag($post_id);
    }




    /**
     * 共有タイトルテーブルのメンテナンス（削除 ＆ 全同期）
     */
    public static function maintenance_sync_all() {
        global $wpdb;
        $table_shared = self::t();
        $table_kx0 = $wpdb->prefix . 'kx_0';

        // --- 1. 不要レコードの削除（JOINによる一括処理） ---

        /*
        // 全く存在しないタイトルを消す
        $wpdb->query("
            DELETE s FROM $table_shared s
            RIGHT JOIN $table_kx0 k ON s.title = k.title
            WHERE k.title IS NULL
        ");
        */

        // IDの参照先タイトルが不一致（ゴミデータ）のものを消す
        /*
        $id_cols = ['id_lesson', 'id_sens', 'id_study', 'id_data'];
        foreach ($id_cols as $col) {
            $wpdb->query("
                DELETE s FROM $table_shared s
                INNER JOIN $table_kx0 k ON s.$col = k.id
                WHERE s.$col > 0
                AND s.title NOT LIKE CONCAT('%%', k.title)
            ");
        }
            */

        // --- 2. 全件同期（Dyの定義に基づき動的に実行） ---

        $sync_types = Dy::get_shared_sync_types();
        if (empty($sync_types)) return 0;

        // SQLの IN 句用にサニタイズして連結
        $type_placeholders = implode(',', array_fill(0, count($sync_types), '%s'));

        $targets = $wpdb->get_col($wpdb->prepare("
            SELECT id FROM $table_kx0
            WHERE type IN ($type_placeholders)
        ", $sync_types));

        if ($targets) {
            foreach ($targets as $post_id) {
                // メンテナンス時は強制的に同期させるためフラグをクリア
                Dy::set('dbkx_shared_processed_' . $post_id, false);
                self::sync($post_id);
            }
        }

        return count($targets);
    }


    /**
     * SharedTitle レイヤーの不整合チェックおよびクリーンアップ
     * kx_0のtitleは階層パスを含むため、末尾一致またはクレンジング後の比較を行う
     */
    public static function maintenance_cleanup_shared_titles() {
        global $wpdb;
        $table_shared = self::t();
        $table_kx0 = $wpdb->prefix . 'kx_0';

        $results = $wpdb->get_results("SELECT * FROM {$table_shared}");

        $deleted_records = 0;
        $updated_records = 0;
        $str = '';

        foreach ($results as $row) {
            $has_changed = false;
            $pure_title = $row->title; // 例: "河野多惠子・短編の流儀"

            $target_ids = [
                'id_lesson' => (int)$row->id_lesson,
                'id_sens'   => (int)$row->id_sens,
                'id_study'  => (int)$row->id_study,
                'id_data'   => (int)$row->id_data,
            ];

            foreach ($target_ids as $col => $id) {
                if ($id === 0) continue;

                // 【修正】LIKE検索で、階層パスの末尾が一致するか、
                // あるいはパスそのものがタイトルと一致するかをチェック
                $is_valid = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table_kx0}
                     WHERE id = %d AND (title = %s OR title LIKE %s)",
                    $id,
                    $pure_title,
                    '%' . $wpdb->esc_like('≫' . $pure_title)
                ));

                if (!$is_valid) {
                    $target_ids[$col] = 0;
                    $has_changed = true;
                }
            }

            // 全IDが0になったかチェック
            $remaining_ids = array_filter($target_ids);

            if (empty($remaining_ids)) {
                $wpdb->delete($table_shared, ['title' => $pure_title]);
                $deleted_records++;
                $str .= "━DB kxSharedTitle：削除: [{$pure_title}] 全ID不整合<br>";
            } elseif ($has_changed) {
                $wpdb->update($table_shared, $target_ids, ['title' => $pure_title]);
                $updated_records++;
                $str .= "━DB kxSharedTitle：更新: [{$pure_title}] 一部ID解除<br>";
            }
        }

        return $str . "━DB kxSharedTitle：完了 (削除 {$deleted_records}件 / 更新 {$updated_records}件)";
    }

    /**
     * DBから生のデータを取得し、Dyキャッシュに格納する
     * * @param int $post_id
     * @return array|null
     */
    public static function load_raw_data($post_id) {
        return parent::load_raw_data_common($post_id, 'db_kx1');
    }



    /**
     * DB書き込み（集約更新）
     * 概念（title）をキーに、各系統のIDを保護しながら更新する
     */
    private static function update_table($data, $post_id) {
        global $wpdb;
        $table = self::t();
        $title = $data['title'] ?? '';

        if (empty($title)) return false;

        // 配列のままではDBに入れられないため、jsonカラムがあればエンコードする
        if (isset($data['json']) && is_array($data['json'])) {
            $data['json'] = wp_json_encode($data['json'], JSON_UNESCAPED_UNICODE);
        }

        $old_data = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE title = %s", $title),
            ARRAY_A
        );

        // 2. 他系統IDの保護
        // REPLACE INTO で既存レコードが消えるのを防ぐため、今回の更新対象外のIDを引き継ぐ
        if ($old_data) {
            foreach (['id_lesson', 'id_sens', 'id_study', 'id_data'] as $col) {
                if (!isset($data[$col]) && !empty($old_data[$col])) {
                    $data[$col] = $old_data[$col];
                }
            }
        }

        // 3. 変更チェック（Dirty Check）
        // timeを除外して、実質的なデータ（IDやdate, tag）に変化がなければ終了
        if ($old_data && !parent::has_changed($data, $old_data, ['time'])) {
            return false;
        }

        // 4. 同期時刻の付与
        $data['time'] = time();

        // 5. DB実行 (REPLACE)
        $result = $wpdb->replace($table, $data);

        // 6. キャッシュの更新
        // Shared Titleは特殊なため、今回の更新元IDの raw キャッシュとして保存
        Dy::set_content_cache($post_id, 'db_kx_shared', $data);

        return $result !== false;
    }
}