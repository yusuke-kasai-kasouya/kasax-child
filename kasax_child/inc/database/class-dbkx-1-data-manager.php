<?php
/**
 * [Path]: inc/database/class-dbkx-1-data-manager.php
 * 制御・メタデータテーブル (kx_1) の管理クラス
 */

namespace Kx\Database;

use Kx\Core\DynamicRegistry as Dy;
use \Kx\Utils\KxMessage as Msg;
use \Kx\Database\Hierarchy;
use \Kx\Core\KxDirector as Kx;

class dbkx1_DataManager extends Abstract_DataManager {

    /**
     * テーブル名を取得（内部用）
     */
    protected static function t() {
        global $wpdb;
        return $wpdb->prefix . 'kx_1';
    }


    /**
     * ContextManager からの呼び出しを受けて同期を実行
     */
    public static function sync($post_id) {

        // 1. 再帰ガード (IDごとに独立)
        if (Dy::get('dbkx1_processed_' . $post_id)) {
            return;
        }
        Dy::set('dbkx1_processed_' . $post_id, true);

        // 2. set_path_index は Dy クラス内で get_post を行う。
        $path_index = Dy::set_path_index($post_id);
        if (!$path_index) return;

        //pageなど排除。
        if( !$path_index['valid'])return;

        // 3. 解析（ここで各カラムの値を決める）
        $data = self::analyze_post_data($post_id);

        // 4. データが実質空かどうかの判定
        // 基本の id, title, time 以外のカラムに有効な値があるかチェック
        if (self::is_record_empty($data)) {
            self::delete_record($post_id);
            return;
        }

        // 5. DB書き込み（一括更新）
        self::update_table($data,$path_index);

    }

    /**
     * tag を取得 (varchar)
     */
    public static function get_tag($post_id) {
        $data = self::load_raw_data($post_id);
        return $data['tag'] ?? null;
    }

    /**
     * has_tag フラグを取得 (tinyint: 1 or null)
     * そのポスト自体がタグの供給源（本文内にタグ記述あり）かどうかを返す
     */
    public static function get_has_tag($post_id) {
        $data = self::load_raw_data($post_id);
        // DBから 1 または null が返る。数値として扱いたい場合は (int) キャストも検討
        return $data['has_tag'] ?? null;
    }

    /**
     * short_code を取得 (varchar)
     */
    public static function get_short_code($post_id) {
        $data = self::load_raw_data($post_id);
        return $data['short_code'] ?? null;
    }

    /**
     * raretu_code を取得 (text)
     */
    public static function get_raretu_code($post_id) {
        $data = self::load_raw_data($post_id);
        return $data['raretu_code'] ?? null;
    }

    /**
     * ghost_to を取得 (int)
     */
    public static function get_ghost_to($post_id) {
        $data = self::load_raw_data($post_id);
        return isset($data['ghost_to']) ? (int)$data['ghost_to'] : null;
    }

    /**
     * consolidated_to を取得 (int)
     */
    public static function get_consolidated_to($post_id) {
        $data = self::load_raw_data($post_id);
        return isset($data['consolidated_to']) ? (int)$data['consolidated_to'] : null;
    }

    /**
     * consolidated_from を取得 (int)
     */
    public static function get_consolidated_from($post_id) {
        $data = self::load_raw_data($post_id);
        return isset($data['consolidated_from']) ? (int)$data['consolidated_from'] : null;
    }

    /**
     * overview_to を取得 (int)
     */
    public static function get_overview_to($post_id) {
        $data = self::load_raw_data($post_id);
        return isset($data['overview_to']) ? (int)$data['overview_to'] : null;
    }

    /**
     * overview_from を取得 (int)
     */
    public static function get_overview_from($post_id) {
        $data = self::load_raw_data($post_id);
        return isset($data['overview_from']) ? (int)$data['overview_from'] : null;
    }

    /**
     * flags を取得(string)
     */
    public static function get_flags($post_id) {
        $data = self::load_raw_data($post_id);
        return (string)($data['flags'] ?? '');
    }

    /**
     * json から ghost_from (配列) のみを取得
     */
    public static function get_ghost_from($post_id) {
        $data = self::load_raw_data($post_id);
        if (empty($data['json'])) return [];

        $json = is_array($data['json']) ? $data['json'] : json_decode($data['json'], true);
        $list = $json['ghost_from'] ?? [];

        return is_array($list) ? array_map('intval', $list) : [];
    }

    /**
     * 更新時刻 (time) を取得 (int)
     */
    public static function get_time($post_id) {
        $data = self::load_raw_data($post_id);
        return isset($data['time']) ? (int)$data['time'] : null;
    }

    /**
     * データの解析（kx1テーブルのカラム構成に準拠）
     */
    private static function analyze_post_data($post_id) {
        $path_index = Dy::get_path_index($post_id) ?? null;
        if (!$path_index) return null;

        // 1. 既存データのロードとJSONの正規化（配列化）
        $old_data = self::load_raw_data($post_id);
        $old_data['json'] = self::normalize_json_field($old_data['json'] ?? null);


        $post = get_post($post_id);
        $content = $post ? $post->post_content : '';

        // 2. 概要関係(overview_to/from)の判定と更新
        $overview_to = self::resolve_overview_relation($post_id, $path_index, $old_data);
        $flags = self::resolve_status_flags($post_id, $path_index, $old_data);

        // 3. 本文の一括解析（この中で転送元/先の紐付け update_ghost_from_relation も実行）
        $assets = self::parse_content_assets($post_id, $content);

        // 4. タグの最終決定
        $tag = self::decide_final_tag($post_id, $assets, $path_index, $old_data);

        // 5. 検証（整合性チェックと不要データのクレンジング）
        // $old_data を参照渡しで渡し、必要に応じて内部の値を書き換えます
        self::verify_and_clean_relations($post_id, $assets, $old_data);

        return [
            'id'                => $post_id,
            'title'             => $path_index['full'],
            'tag'               => array_key_exists('tag_override', $old_data) ? $old_data['tag_override'] : $tag,// tag_override があれば null、なければ $tag を採用
            'has_tag'           => $assets['has_tag'] ?? null,
            'short_code'        => $assets['short_code'],
            'raretu_code'       => $assets['raretu_code'],
            'ghost_to'          => $assets['ghost_to'] ?: null,//$assets['ghost_to'] ?: ($old_data['ghost_to'] ?? null),
            'consolidated_to'   => $assets['consolidated_to'] ?? null,
            'consolidated_from' => $old_data['consolidated_from'] ?? null,
            'overview_to'       => $overview_to,
            'overview_from'     => $old_data['overview_from'] ?? null,
            'flags'             => $flags,
            'json'              => json_encode($old_data['json'], JSON_UNESCAPED_UNICODE), // 検証後の json を使用
            'time'              => strtotime($path_index['modified']),
        ];
    }


    /**
     * JSONフィールドを配列に正規化する
     */
    private static function normalize_json_field($json_raw) {
        if (is_string($json_raw)) {
            return json_decode($json_raw, true) ?: [];
        }
        return is_array($json_raw) ? $json_raw : [];
    }



    /**
     * 概要関係の判定とリレーション更新を実行し、overview_to IDを返す
     */
    private static function resolve_overview_relation($post_id, $path_index, &$old_data) {
        $overview_to = $old_data['overview_to'] ?? null;

        // タイトル末尾が「＠概要」なら親子関係を構築
        if (str_ends_with($path_index['last_part'], '0＠概要') || str_ends_with($path_index['last_part'], '0＠統合概要')  ) {
            $parent_id = Hierarchy::get_parent($post_id);
            if ($parent_id) {
                $overview_to = $parent_id;
                $old_data['overview_to'] = $parent_id;

                // 親側の overview_from に自分を追加
                self::update_overview_from_relation($post_id, $parent_id);
                return $overview_to;
            }
        }
        return null;
    }

    /**
     * Undocumented function
     *
     */
    private static function resolve_status_flags(int $post_id, array $path_index, array $old_data): string{
        // 1. 既存のフラグを配列に分解（カンマ区切りを想定）
        // 空文字の場合は空の配列にする
        $current_flags = array_filter(explode(',', $old_data['flags'] ?? ''));

        // 2. 「統合概要」判定
        if (str_ends_with($path_index['full'], '統合概要')) {
            $current_flags[] = 'integrated';
        } else {
            // 配列から 'integrated' を探して削除する
            $current_flags = array_diff($current_flags, ['integrated']);
        }


        // 3. 重複を削除して、カンマで連結して返す
        // これなら最後にカンマが残る心配も、重複する心配もありません
        return implode(',', array_unique($current_flags));
    }


    /**
     * 親レコード(概要対象)の overview_from カラムに自身のIDを追加する
     * @param int $my_id 自身のid
     * @param int $parent_id 親の post_id (概要を貼られる側)
     */
    private static function update_overview_from_relation($my_id, $parent_id) {
        global $wpdb;
        $table = self::t();

        // 親データをロード
        $parent_data = self::load_raw_data($parent_id);
        if (!$parent_data) {
            Msg::warn("[$my_id]：親(ID:$parent_id)のkx_1レコードが存在しません。");
            return;
        }

        $old_from = $parent_data['overview_from'] ?? '';
        $from_ids = !empty($old_from) ? explode(',', $old_from) : [];

        // 既に入っていれば何もしない（Idle-Check）
        if (in_array((string)$my_id, $from_ids)) return;

        $from_ids[] = $my_id;
        // 重複除去と整形
        $new_from = implode(',', array_filter(array_unique($from_ids)));

        // update対象をoverview_fromのみに限定し、既存の他のカラムには触れない
        $wpdb->update(
            $table,
            [
                'overview_from' => $new_from,
                'time'          => time()
            ],
            ['id' => $parent_id],
            ['%s', '%d'],
            ['%d']
        );

        // 親のランタイムキャッシュのみを消去して再ロードを促す
        Dy::set_content_cache($parent_id, 'db_kx1', null);
        Msg::info("[$my_id] の親記事($parent_id)の概要紐付けを更新しました。");
    }


    /**
     * 本文の一括走査
     */
    private static function parse_content_assets($post_id, $content) {
        $results = [
            'tag_candidate'   => null,
            'has_tag'         => null,
            'short_code'      => null,
            'raretu_code'     => null,
            'consolidated_to' => null,
            'ghost_to'        => null,
        ];

        $pattern = '/\[(raretu|ghost|kx_format|kx_tp)(.*?)\]|^タグ：(.*)$/mu';

        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                //タグ。
                if (!empty($match[3])) {
                    $results['tag_candidate'] = trim($match[3]);
                    $results['has_tag'] = 1;
                    continue;
                }

                $code_name = $match[1];
                $params    = $match[2];

                switch ($code_name) {
                    case 'raretu':
                        $results['short_code'] = 'raretu';
                        $results['raretu_code'] = trim($params) ?: 'default';
                        if (preg_match('/tougou\s*=\s*["\']?(\d+)["\']?/', $params, $id_m)) {
                            $results['consolidated_to'] = $id_m[1];
                            self::update_consolidated_from_relation($post_id, $id_m[1]);
                        }
                        break;
                    case 'kx_format':
                    case 'ghost':
                        if (preg_match('/id\s*=\s*["\']?(\d+)["\']?/', $params, $id_m)) {
                            $results['ghost_to'] = $id_m[1];
                            self::update_ghost_from_relation($post_id, $id_m[1]);
                        }
                        break;
                    case 'kx_tp':
                        $results['short_code'] = 'kx_tp';
                        break;
                }
            }
        }
        return $results;
    }

    /**
     * タグ決定ロジック
     */
    private static function decide_final_tag($post_id, $assets, $path_index, $old_data) {
        $tag = $assets['tag_candidate'];

        // 本文にタグがない場合（親としての挙動） ---
        if (!$tag) {
            // 自分が「誰かからの概要(overview_from)」としてタグを供給されているポスト（親）である場合
            if (!empty($old_data['overview_from'])) {
                $child_id = (int)$old_data['overview_from'];

                // 子（供給元）が今でもタグを保持しているか、物理フラグで確認
                if (self::get_has_tag($child_id)) {
                    // 子がタグを持っているなら、親である自分のタグも維持する
                    return $old_data['tag'] ?? null;
                } else {
                    // 子がタグを失った（または関係が切れた）なら、親である自分のタグも消去する
                    return null;
                }
            }
            return null;
        }

        // --- 以下、供給元（子）としてのロジック ---

        $tag = self::normalize_tags($tag);

        // 1. 転送設定(ghost_to)がある場合はタグを持たない
        $g_id = $assets['ghost_to'] ?: ($old_data['ghost_to'] ?? null);
        if ($g_id) {
            self::update_ghost_from_relation($post_id, $g_id);
            return null;
        }

        // 2. ショートコードが存在する場合もタグを持たない
        if ($assets['short_code']) return null;

        // 3. 概要紐付けがある場合、概要側のレコードにタグを書き込み、自身はnull
        // ※ここで子が親にタグをプッシュし、親のsyncを誘発する
        $ov_id = $old_data['overview_to'] ?? null;
        if (!empty($ov_id)) {
            self::update_overview_tag($post_id, $ov_id, $tag);
            return null;
        }

        // 4. 自身が概要記事の場合はタグを保持、それ以外も基本保持
        return $tag;
    }


    /**
     * タグ文字列を正規化し、|タグA| |タグB| 形式に変換する
     */
    private static function normalize_tags($raw_tag_str) {
        if (empty($raw_tag_str)) return '';

        // 1. 区切り文字の正規化
        // 全角「、」「。」「，」「．」およびタブをすべて半角カンマに置換
        $search  = ['、', '。', '，', '．',"\t", '　'];
        $normalized = str_replace($search, ',', $raw_tag_str);

        // 2. 配列に分割（半角カンマまたは半角スペースで区切る）
        $tag_array = preg_split('/[, ]+/', $normalized, -1, PREG_SPLIT_NO_EMPTY);

        // 3. 各タグのクレンジング
        $clean_tags = [];
        foreach ($tag_array as $tag) {
            $tag = trim($tag);
            if ($tag !== '') {
                // タグ自体に「|」が含まれていると壊れるため除去
                $tag = str_replace('|', '', $tag);
                $clean_tags[] = "|{$tag}|";
            }
        }

        // 4. 重複排除
        $clean_tags = array_unique($clean_tags);

        // 5. スペース区切りで結合して返す
        return implode(' ', $clean_tags);
    }


    /**
     * リレーションの整合性検証とクレンジング
     */
    private static function verify_and_clean_relations($post_id, $assets, &$old_data) {

        // --- A. consolidated_from の検証 (単一ID) ---
        if (!empty($old_data['consolidated_from'])) {
            $src_id = (int)$old_data['consolidated_from'];
            $src_data = self::load_raw_data($src_id);
            if (!$src_data || (int)($src_data['consolidated_to'] ?? 0) !== (int)$post_id) {
                $old_data['consolidated_from'] = null;
                Msg::warn("Post{$post_id}→to{$src_id}のconsolidated_fromを削除します。");
            }
        }

        // --- B. overview_from の検証 (単一ID) ---
        if (!empty($old_data['overview_from'])) {
            $child_id = (int)$old_data['overview_from'];

            // 子側の設定で、転送先(overview_to)が自分(post_id)になっているか確認
            // syncを誘発しない軽量なメソッドを使用するのが正解
            $child_overview_to_id = self::get_overview_to($child_id);
            $is_overview_to = ((int)$child_overview_to_id === $post_id);

            // 階層構造として、今でも自分の直下の子であるか確認
            $children_ids = Hierarchy::get_children($post_id);
            $is_child = in_array($child_id, $children_ids);

            // どちらかの条件を満たさなくなれば、リレーションを解除
            if (!$is_child || !$is_overview_to) {
                $old_data['overview_from'] = null;
            }
        }

        // --- C. ghost_from の検証 (JSON配列内の多対一リレーション) ---
        // 自身(転送先)の json['ghost_from'] リストに含まれる各ID(転送元)をチェック
        if (!empty($old_data['json'])) {
            $json_data = is_array($old_data['json']) ? $old_data['json'] : json_decode($old_data['json'], true);

            if (!empty($json_data['ghost_from']) && is_array($json_data['ghost_from'])) {
                $valid_ghost_from = [];
                foreach ($json_data['ghost_from'] as $src_id) {
                    $src_data = self::load_raw_data((int)$src_id);
                    // 相手の ghost_to が自分を指している場合のみ保持
                    if ($src_data && (int)($src_data['ghost_to'] ?? 0) === $post_id) {
                        $valid_ghost_from[] = $src_id;
                    }
                }
                // 配列の中身が変わった（無効なIDが除去された）場合、jsonデータを更新
                if (count($valid_ghost_from) !== count($json_data['ghost_from'])) {
                    $json_data['ghost_from'] = !empty($valid_ghost_from) ? $valid_ghost_from : [];
                    $old_data['json'] = $json_data; // 参照渡しの old_data を更新
                }
            }
        }

        // --- D. タグの削除条件検証 ---
        if (empty($old_data['overview_from']) && !empty($assets['short_code'])) {
            $old_data['tag_override'] = null;
        }
    }


    /**
     * 統合先レコードの consolidated_from カラムに自身のIDを追加する
     */
    private static function update_consolidated_from_relation($my_id, $target_id) {
        global $wpdb;
        $table = self::t();

        if (!Dy::is_ID($target_id)) {
            $title = Dy::get_title($my_id);
            Msg::warn("[$title]：統合先(tougou)のID Error。ID:$target_id はWP上に存在しません。");
            return;
        }

        $target_data = self::load_raw_data($target_id);

        if (!$target_data) {
            // --- A. 新規作成ロジック ---
            $wpdb->insert(
                $table,
                [
                    'id'                => $target_id,
                    'consolidated_from' => (string)$my_id,
                    'time'              => time()
                ],
                ['%d', '%s', '%d']
            );
            Msg::info("統合先レコード(ID:$target_id)を新規作成し、統合元($my_id)を登録しました。");
        } else {
            // --- B. 既存更新ロジック ---
            $raw_from = $target_data['consolidated_from'] ?? '';
            // 空要素を排除して配列化
            $from_ids = !empty($raw_from) ? array_filter(explode(',', $raw_from)) : [];

            if (in_array((string)$my_id, $from_ids)) return;

            $from_ids[] = (string)$my_id;
            // ユニーク化 -> フィルタリング -> インデックス振り直し
            $new_from = implode(',', array_values(array_filter(array_unique($from_ids))));

            $wpdb->update(
                $table,
                [
                    'consolidated_from' => $new_from,
                    'time'              => time()
                ],
                ['id' => $target_id],
                ['%s', '%d'],
                ['%d']
            );
        }

        Dy::set_content_cache($target_id, 'db_kx1', null);
    }

    /**
     * 転送先レコードの json['ghost_from'] 配列に自身のIDを追加
     * レコードが存在しない場合は新規作成（Replace）を行う
     */
    private static function update_ghost_from_relation($my_id, $target_id) {

        // 1. ターゲットの既存データを取得
        $target_data = self::load_raw_data($target_id);

        // 2. レコード不在時のバリデーション
        if (!$target_data) {
            // WordPress側にIDの実体があるか確認
            if (!Dy::is_ID($target_id)) {
                $my_title = Dy::get_title($my_id);
                Msg::warn("[$my_id]：Ghost設定エラー。転送先ID:$target_id はWP内に存在しません。対象：$my_title");
                return;
            }
            // 実体はあるがkx1レコードがない場合は空配列で初期化
            $target_data = ['json' => '{}'];
        }

        // 3. JSON解析と更新判定
        $json = json_decode($target_data['json'] ?? '{}', true) ?: [];
        $ghost_from = (array)($json['ghost_from'] ?? []);

        if (in_array($my_id, $ghost_from)) return;

        $ghost_from[] = $my_id;
        $json['ghost_from'] = array_values(array_unique($ghost_from));
        $new_json_str = json_encode($json, JSON_UNESCAPED_UNICODE);

        // update_table ではなく $wpdb->update を使用する
        global $wpdb;
        $wpdb->update(
            self::t(),
            [
                'json' => $new_json_str,
                'time' => time()
            ],
            ['id' => $target_id],
            ['%s', '%d'],
            ['%d']
        );

        // キャッシュをクリアして、次に相手がロードされた時に最新のJSONが見えるようにする
        Dy::set_content_cache($target_id, 'db_kx1', null);
    }

    /**
     * 指定された概要IDのレコードに対し、タグを保存
     */
    private static function update_overview_tag($post_id, $overview_id, $tag) {
        global $wpdb;
        $table = self::t();

        // 1. データのロードと存在確認
        $ov_data = self::load_raw_data($overview_id);

        // 2. 既存データがある場合のIdle-Check (不変なら何もしない)
        if ($ov_data && ($ov_data['tag'] ?? '') === $tag) return;

        if (!$ov_data) {
            // --- A. レコードが存在しない場合は新規作成 ---
            $wpdb->insert(
                $table,
                [
                    'id'   => $overview_id,
                    'tag'  => $tag,
                    'time' => time()
                ],
                ['%d', '%s', '%d']
            );
            \Kx\Utils\KxMessage::info("[$post_id]：概要レコード($overview_id)を新規作成しタグ「$tag」を設定しました。");
        } else {
            // --- B. 既存更新 ---
            $wpdb->update(
                $table,
                ['tag' => $tag, 'time' => time()],
                ['id' => $overview_id],
                ['%s', '%d'],
                ['%d']
            );
            // 他のカラム（consolidated_from等）に触れず、tagのみをピンポイント更新するため安全
        }

        // 3. メモリ上の「記憶」をリセット
        Dy::set_content_cache($overview_id, 'db_kx1', null);
    }




    /**
     * 基本情報以外の有効なデータが存在するか判定
     */
    private static function is_record_empty($data) {
        // チェック対象から除外する基本キー
        $exclude_keys = ['id', 'title', 'time'];

        foreach ($data as $key => $value) {
            if (in_array($key, $exclude_keys)) continue;

            // 値が空（null, '', [], 0）でないものが一つでもあれば「空ではない」
            if (!empty($value)) {
                // flags は 0 がデフォルトなので、数値の 0 は「空」とみなす
                if ($key === 'flags' && (int)$value === 0) continue;

                // json はエンコード済みの文字列 '[]' や '{}' の場合も考慮
                if ($key === 'json' && ($value === '[]' || $value === '{}')) continue;

                return false;
            }
        }
        return true;
    }


    /**
     * kx_1テーブルのフルメンテナンス
     * kx_0の全レコードを走査し、必要に応じて同期・削除を実行
     * @param bool $force trueの場合、更新日時に関わらず全件強制再同期
     */
    public static function maintenance_full($force = false) {
        global $wpdb;
        $table_kx0 = $wpdb->prefix . 'kx_0';
        $table_kx1 = self::t();

        // 1. kx0から全記事のIDと更新日時を取得
        $posts = $wpdb->get_results("SELECT id, wp_updated_at FROM $table_kx0", ARRAY_A);

        if (empty($posts)) {
            Msg::info("メンテナンス対象が kx0 に存在しません。");
            return;
        }

        $count_total = count($posts);
        $count_sync = 0;

        Msg::info(($force ? "【強制】" : "【差分】") . "メンテナンスを開始します（全{$count_total}件）...");

        foreach ($posts as $p) {
            $post_id = (int)$p['id'];

            if (!$force) {
                // 差分チェックモード
                $kx0_modified = $p['wp_updated_at'];
                $kx1_modified = $wpdb->get_var($wpdb->prepare(
                    "SELECT wp_updated_at FROM $table_kx1 WHERE id = %d",
                    $post_id
                ));

                if ($kx0_modified === $kx1_modified) {
                    continue; // 日時が一致していればスキップ
                }
            }

            // 同期実行（強制モードなら無条件、差分モードなら日時不一致時のみここに到達）
            self::sync($post_id);
            $count_sync++;
        }

        Msg::info("メンテナンス完了: 実行件数 {$count_sync} / {$count_total} 件");
    }


    /**
     * kx_1テーブルの孤立チェック・クリーンアップ
     * kx_0に存在しないIDがkx_1にある場合、そのレコードを削除する
     */
    public static function maintenance_cleanup_isolated_records() {
        global $wpdb;
        $table_kx0 = $wpdb->prefix . 'kx_0';
        $table_kx1 = self::t();

        // 1. kx_1にあって kx_0にないIDをSQLで直接特定する（効率的）
        $isolated_ids = $wpdb->get_col("
            SELECT k1.id
            FROM $table_kx1 k1
            LEFT JOIN $table_kx0 k0 ON k1.id = k0.id
            WHERE k0.id IS NULL
        ");

        if (empty($isolated_ids)) {
            //Msg::info("孤立したレコードは見つかりませんでした。");
            return "━DB kx_1━COUNT1：0";
        }

        $count = 0;
        foreach ($isolated_ids as $id) {
            // 2. 共通の削除メソッドを呼び出し（キャッシュ破棄も含む）
            self::delete_record((int)$id);
            $count++;
        }

        return "━DB kx_1━COUNT1：[{$count}] 件";
        //Msg::info("クリーンアップ完了: {$count}件の孤立レコードを削除しました。");
    }


    /**
     * 相互リレーションの整合性チェックと修復
     */
    public static function maintenance_repair_relations() {
        global $wpdb;
        $table_kx1 = self::t();
        $table_kx0 = $wpdb->prefix . 'kx_0';

        // 1. 全レコード取得
        $rows = $wpdb->get_results("SELECT * FROM $table_kx1", ARRAY_A);
        $count_fixed = 0;

        foreach ($rows as $row) {
            $post_id = (int)$row['id'];
            $json = is_string($row['json']) ? json_decode($row['json'], true) : ($row['json'] ?? []);
            $changed = false;

            // a. ghost_from (JSON内) の実在チェック
            if (!empty($json['ghost_from'])) {
                $before_count = count($json['ghost_from']);
                // kx0に存在するIDだけにフィルタリング
                $placeholders = implode(',', array_fill(0, $before_count, '%d'));
                $exists = $wpdb->get_col($wpdb->prepare(
                    "SELECT id FROM $table_kx0 WHERE id IN ($placeholders)",
                    ...$json['ghost_from']
                ));

                $json['ghost_from'] = array_values(array_map('intval', $exists));
                if (count($json['ghost_from']) !== $before_count) $changed = true;
            }

            // b. 単一IDカラム (ghost_to 等) の実在チェック
            $id_columns = ['ghost_to', 'consolidated_to', 'overview_to'];
            foreach ($id_columns as $col) {
                if ($row[$col]) {
                    $still_exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_kx0 WHERE id = %d", $row[$col]));
                    if (!$still_exists) {
                        $row[$col] = null;
                        $changed = true;
                    }
                }
            }

            // 変更があれば保存
            if ($changed) {
                $row['json'] = json_encode($json, JSON_UNESCAPED_UNICODE);
                $wpdb->replace($table_kx1, $row);
                $count_fixed++;
            }
        }
        Msg::info("リレーション修復完了: {$count_fixed}件の不整合を修正しました。");
    }


    /**
     * テーブルの物理的最適化（デフラグメント）の実行
     * * MySQLの OPTIMIZE TABLE コマンドを発行し、テーブルの断片化を解消します。
     * * 【処理の詳細】
     * 1. 削除済みレコードが占めていた未使用領域（オーバーヘッド）を解放し、ディスク容量を削減します。
     * 2. インデックスページを再構成し、検索クエリのパフォーマンスを改善します。
     * 3. データの物理的な配置を連続させることで、ディスクI/O効率を向上させます。
     * * 【注意事項】
     * - 本処理中はテーブルがロックされる（または一時テーブルへのコピーが発生する）ため、
     * レコード数が多い場合は実行に時間がかかることがあります。
     * - REPLACE や DELETE を頻繁に行う本クラスの性質上、定期的な実行が推奨されますが、
     * 数万件規模のデータがない限り、毎日の実行は不要です（月1回〜週1回程度で十分です）。
     * * @return void
     */
    public static function maintenance_vacuum() {
        global $wpdb;
        $table = self::t();

        // MySQLのテーブル最適化コマンドを実行
        // InnoDBの場合は内部的に ALTER TABLE ... ENGINE=InnoDB にマッピングされる
        $wpdb->query("OPTIMIZE TABLE $table");

        Msg::info("テーブルの最適化（断片化解消）を完了しました。");
    }

    /**
     * 統合リレーション（consolidated_to/from）の全件修復
     * ※ 1対1（単一ID）設計に基づき、相手側の consolidated_from を自分(ID)で上書き更新する
     */
    public static function maintenance_consolid() {
        global $wpdb;
        $table = self::t();

        // 1. consolidated_to (統合先) が設定されているレコードをすべて抽出
        $targets = $wpdb->get_results(
            "SELECT id, consolidated_to FROM $table WHERE consolidated_to IS NOT NULL AND consolidated_to != 0",
            ARRAY_A
        );

        if (empty($targets)) {
            Msg::info("統合設定（consolidated_to）を持つレコードは見つかりませんでした。");
            return;
        }

        $count = 0;
        foreach ($targets as $row) {
            $my_id     = (int)$row['id'];
            $target_id = (int)$row['consolidated_to'];

            // 2. 相手側(統合先)の consolidated_from に自分をセット
            // 他のカラム（tagやjson等）を壊さないよう、ピンポイントでUPDATE
            $updated = $wpdb->update(
                $table,
                [
                    'consolidated_from' => $my_id,
                    'time'              => time()
                ],
                ['id' => $target_id],
                ['%d', '%d'],
                ['%d']
            );

            if ($updated !== false) {
                // 相手側のキャッシュをクリア（最新のリレーションが見えるようにする）
                $count++;
            }
        }

        Msg::info("統合リレーション修復完了: {$count} 件の相手側レコード(from)を更新しました。");
    }


    /**
     * 全てのメンテナンス処理を順番に実行する
     */
    public static function maintenance_run_all($force = false) {
        Msg::info("システムメンテナンスを開始します...");

        self::maintenance_full($force);           // 1. kx0との同期・更新
        self::maintenance_cleanup_isolated_records();   // 2. 孤立レコード削除
        self::maintenance_repair_relations(); // 3. リレーション不整合修復
        self::maintenance_vacuum();         // 4. 物理最適化
        self::maintenance_consolid();//統合系メンテ。

        Msg::info("全メンテナンス工程が正常に終了しました。");
    }




    /**
     * レコードの削除実行
     */
    private static function delete_record($post_id) {
        global $wpdb;
        $table = self::t();

        $wpdb->delete($table, ['id' => $post_id], ['%d']);

        // キャッシュも破棄
        Dy::set_content_cache($post_id, 'db_kx1', null);
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
     * DB書き込み（一括更新）
     */
    private static function update_table($data, $path_index) {
        global $wpdb;
        $table = self::t();
        $post_id = $data['id'];

        // 1. 既存データの取得（Dirty Check用）
        $old_data = self::load_raw_data($post_id);

        // 2. Dy (path_index) から最終更新日時をセット
        // NULLを許容する設計。Dyには 'modified' キーで格納されている
        $data['wp_updated_at'] = $path_index['modified'] ?? null;

        // 3. 変更チェック（Dirty Check）
        if (!parent::has_changed($data, $old_data, ['time'])) {
            return false;
        }

        // 4. 同期時刻（実行タイムスタンプ）の付与
        $data['time'] = time();

        // 5. DB実行
        $wpdb->replace($table, $data);

        // 6. キャッシュの更新
        Dy::set_content_cache($post_id, 'db_kx1', $data);

        return true;
    }

}

