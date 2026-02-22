<?php
/**
 * Hierarchy クラス
 * [Path]: inc\database\Hierarchy.php
 *
 * 物語制作支援システムにおけるタイトルの「≫」区切りによる階層構造を
 * wp_kx_hierarchy テーブルにマッピング管理する。
 *
 * 仕様：wp_kx_hierarchyテーブルのupdate処理はsave_with_log()を経由すること。
 */

namespace Kx\Database;


use Kx\Core\DynamicRegistry as Dy;
use Kx\Core\KxDirector as kx;
use Kx\Database\DB;
//use Kx\Utils\Time; // 仕様書に基づき Time クラスを使用

class Hierarchy  extends Abstract_DataManager {

    private static $mt_list = [];

    /**
     * テーブル名を取得（内部用）
     */
    protected static function t() {
        global $wpdb;
        return $wpdb->prefix . 'kx_hierarchy';
    }

    /**
     * メインの同期処理
     * @param array  $args ['id' => ポストID]
     * @param string $type 'insert' | 'update' | 'id' 等
     */
    public static function sync($post_id) {
        // Phase 1: 前処理（バリデーションとコンテキスト取得）
        $context = self::prepare_sync_context($post_id);
        if (!$context['valid']) return;

        // Phase 2: 構造整理（不整合な旧レコードの削除）
        self::cleanup_obsolete_records($context);

        // Phase 3: 階層走査とノード処理
        $nodes = explode('≫', $context['current_path']);
        $ancestor_data = [];
        $current_path_accumulator = '';

        foreach ($nodes as $index => $node_name) {
            // 各階層ノードの解析と保存を委託
            $node_result = self::process_hierarchy_node(
                $index,
                $node_name,
                $context,
                $current_path_accumulator,
                $ancestor_data
            );

            // 次のループ（子階層）のためにパスと祖先リストを更新
            $current_path_accumulator = $node_result['current_path'];
            $ancestor_data = $node_result['next_ancestor_data'];
        }
    }

    /**
     * 実行に必要なデータ（Context）を揃え、バリデーションを行う
     */
    private static function prepare_sync_context($post_id) {
        global $wpdb;

        // IDの整数保証（鉄則） [cite: 9, 13]
        if (is_array($post_id)) {
            $post_id = isset($post_id['id']) ? (int)$post_id['id'] : 0;
        } else {
            $post_id = (int)$post_id;
        }

        if ($post_id <= 0) return ['valid' => false];

        // キャッシュおよびパスの取得
        $path_index = Dy::set_path_index($post_id); // [cite: 10]
        if (!$path_index['valid']) {
            self::purge_post_record($post_id, 'trash'); // [cite: 4]
            return ['valid' => false];
        }

        $kx0_cache = Dy::get_content_cache($post_id, 'db_kx0'); // [cite: 14]
        $current_path = (!empty($kx0_cache['title'])) ? $kx0_cache['title'] : Dy::get_title($post_id);

        return [
            'valid'        => true,
            'post_id'      => $post_id,
            'current_path' => $current_path,
            'table'        => $wpdb->prefix . 'kx_hierarchy', // [cite: 14]
            'path_index'   => $path_index
        ];
    }

    /**
     * 整合性管理：旧パスの削除（掃除）に専念する
     * * 実体化（is_virtual=0への昇格）は、後の process_hierarchy_node
     * および persist_node_data が一括して行うため、ここでは行わない。
     */
    private static function cleanup_obsolete_records($context) {
        global $wpdb;
        $table   = $context['table'];
        $post_id = $context['post_id'];
        $path    = $context['current_path'];

        // 1. 整合性保護：この ID に紐づく「現在のパス以外」のレコードを一括削除
        // タイトル変更（パス変更）時に、古い住所のレコードが残るのを防止する。
        // ※これを先に行うことで、DB内の ID 占有状況をクリーンにする。
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE post_id = %d AND full_path != %s",
            $post_id,
            $path
        ));
    }

    /**
     * 各ノード（階層）の解析・判定・保存
     */
    private static function process_hierarchy_node($index, $node_name, $context, $path_accumulator, $ancestor_data) {
        global $wpdb;
        $table   = $context['table'];
        $post_id = $context['post_id'];

        // 1. 現在の階層パスを確定
        $current_path = ($index === 0) ? $node_name : $path_accumulator . '≫' . $node_name;
        $parent_path  = ($index === 0) ? null : $path_accumulator;

        // 2. 既存データの取得
        $existing  = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE full_path = %s", $current_path));
        $json_data = ($existing && !empty($existing->json)) ? json_decode($existing->json, true) : [];

        // 3. ターゲットIDの決定（ここを強化）
        $is_final = ($current_path === $context['current_path']);

        if ($is_final) {
            // A: 最終ノードなら、処理中の post_id が主役
            $target_id = $post_id;
        } else {
            // B: 中間ノードの場合
            $target_id = ($existing->post_id ?? 0);

            // 【重要】DBが仮想(0)でも、実際には「概要記事」等として実体が存在しないか確認
            // これにより「子に概要がある場合のアラート」を抑制する
            if ($target_id <= 0) {
                $ids = kx::get_ids_by_title($current_path);
                $real_id = (!empty($ids) && is_array($ids)) ? $ids[0] : 0;

                if ($real_id) {
                    $target_id = $real_id;
                }
            }
        }

        // 4. JSON: hierarchy_role (最上位判定)
        if ($index === 0) {
            $json_data['hierarchy_role'] = 'origin';
        } else {
            unset($json_data['hierarchy_role']);
        }

        // 5. JSON: ancestry (祖先リスト) の構築
        $json_data['ancestry'] = $ancestor_data;

        // 6. 親への自分自身の登録（子孫リストの更新）
        // target_id が確定した状態で通知する
        if (!empty($parent_path)) {
            self::register_to_parent($parent_path, $target_id, $node_name, $table);
        }

        // 8. DB保存用データの構築
        // is_virtual は target_id の有無にのみ依存させる（昇格の自動化）
        $data = [
            'post_id'     => $target_id,
            'parent_path' => $parent_path,
            'level'       => $index + 1,
            'is_virtual'  => ($target_id > 0) ? 0 : 1,
            'alert'       => self::set_hierarchy_alert($target_id),
        ];

        // 9. JSON のクレンジングとエンコード
        $data = self::clean_json_id($json_data, $data, $post_id, $context['path_index']);

        // 10. Dirty Check を経て保存（persist_node_data は型変換比較を行うため安全）
        self::persist_node_data($existing, $data, $current_path, $target_id, $post_id);

        // 11. 次のループ（子階層）に引き継ぐ祖先データを作成
        $ancestor_data[$index + 1] = ($target_id > 0) ? (string)$target_id : "virtual";

        return [
            'current_path'        => $current_path,
            'next_ancestor_data'  => $ancestor_data
        ];
    }


    /**
     * 変更がある場合のみ DB 保存とキャッシュ更新を行う
     */
    private static function persist_node_data($existing, $data, $current_path, $target_id, $post_id) {
        $has_change = true;

        if ($existing) {
            $has_change = false;
            foreach ($data as $key => $value) {
                if (!property_exists($existing, $key) || (string)$existing->$key !== (string)$value) {
                    $has_change = true;
                    break;
                }
            }
        }

        if ($has_change) {
            $action = ($target_id > 0) ? 'u' : 'i';
            self::save_with_log($current_path, $data, $action);
        }

        // 処理中のメイン記事であれば Dy キャッシュを更新 [cite: 9, 12]
        if ($target_id === $post_id) {
            Dy::set_content_cache($post_id, 'raw', [
                'db_kx_hierarchy' => $data
            ]);
        }
    }




    /**
     * ツリー表示 + 投稿作成処理の実行
     */
    public static function get_full_tree_text($parent_path = NULL, $indent = "", $recursive = true) {
        $text = "";

        // 作成成功メッセージの表示（URLパラメータから判定）
        if ($parent_path === NULL && isset($_GET['kx_created'])) {
            $text .= '<div style="background:#4ec9b0; color:#000; padding:10px; margin-bottom:10px; border-radius:5px; font-weight:bold;">投稿を作成しました。</div>';
        }

        $children = self::get_children_by_parent_path($parent_path);
        $count = count($children);

        foreach ($children as $i => $child) {

            $status = get_post_status($child->post_id);
            if ($status === 'trash') {
                self::purge_post_record($child->post_id, 'trash');
                continue;
            }
            elseif ($child->is_virtual == 0 && $child->full_path !== Dy::get_title($child->post_id)) {
                // 第2引数を 'mismatch' にすることで、メタデータ(kx_db0)を保護しつつ掃除
                self::purge_post_record($child->post_id, 'mismatch');
                // 削除したため、このループの描画処理はスキップ
                continue;
            }


            $is_last = ($i === $count - 1);
            $branch = $is_last ? "└ " : "├ ";

            $json = !empty($child->json) ? json_decode($child->json, true) : [];
            $has_alert = self::check_hierarchy_alert($child->post_id);
            $full_path = $child->full_path;
            $nodes = explode('≫', $full_path);
            $display_name = end($nodes);

            $create_btn = "";


            // --- 【修正ポイント2】ボタンのHTML構成 ---
            if ($child->is_virtual || (int)$child->post_id === 0) {
    $color = '#f44747'; // Virtual: Red

    // フォームタグを含める
    $create_btn = ' <form method="post" style="display:inline; margin-left:8px;">';
    $create_btn .= '<input type="hidden" name="target_title" value="'.esc_attr($full_path).'">';
    $create_btn .= '<button type="submit" name="create_virtual_post" value="1" style="background:#cc0000; color:#fff; border:none; padding:1px 4px; cursor:pointer; font-size:9px; border-radius:3px;">＋ Create</button>';
    $create_btn .= '</form>';

    // --- 【追加】Virtual階層へのリンク実装 ---
    // JavaScript関数 kx_h_ajax を呼び出して、ターゲットID（今表示しているpre等）を書き換える
    // ※第3引数のtargetIdは、呼び出し元のJSから引き継ぐ必要があるため、
    // ここでは「現在の詳細表示を更新する」ためのリンクとして構築します。
    $ajax_link = 'hierarchy/' . urlencode($full_path);

    $label = '<a href="' . esc_url(admin_url($ajax_link)) . '"
                target="_blank"
                style="color:'.$color.'; text-decoration:none; border-bottom:1px dashed '.$color.';"
                title="この仮想階層を基点に展開">'
                . esc_html($display_name) . ' <span style="font-size:0.8em; opacity:0.8;">(virtual)</span>' .
             '</a>';
}
            elseif ($has_alert) {
                $color = '#dcdcaa';
                $url = get_permalink($child->post_id);
                $label = '<a href="' . esc_url($url) . '" target="_blank" style="color:'.$color.'; text-decoration:underline dotted;">' . esc_html($display_name) . ' (alert)</a>';
            }
            else {
                global $wpdb;
                $table = $wpdb->prefix . 'kx_hierarchy';
                $is_parent = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE parent_path = %s", $full_path));
                $color = ($is_parent > 0) ? '#4ec9b0' : '#569cd6';
                $url = get_permalink($child->post_id);
                $label = '<a href="' . esc_url($url) . '" target="_blank" style="color:'.$color.'; text-decoration:none;">' . esc_html($display_name) . '</a>';
            }

            $text .= '<div style="line-height:2.0; white-space:nowrap; font-family: monospace;">';
            $text .= '<span style="color:#555;">' . $indent . $branch . '</span>';
            $text .= '<span style="color:'.$color.'">' . $label . '</span>';
            $text .= $create_btn;
            $text .= '</div>';

            if ($recursive === true) {
                $text .= self::get_full_tree_text($child->full_path, $indent . ($is_last ? "　 " : "│ "), true);
            }
        }
        return $text;
    }


    /**
     * 指定されたポストの子階層（子孫）の post_id リストを取得する
     * * @param int $post_id 親のポストID
     * @return array 子孫の post_id リスト（文字列配列）。存在しない場合は空配列。
     */
    public static function get_children($post_id) {
        // 1. キャッシュまたはDBからデータをロード
        $data = self::load_raw_data($post_id);

        // 2. jsonカラムのデータを取得
        // load_raw_data 内で json_decode 済みの場合はその値を、
        // 文字列のままの場合は decode して処理する
        $json_data = [];
        if (isset($data['json'])) {
            $json_data = is_array($data['json'])
                ? $data['json']
                : json_decode($data['json'], true);
        }

        // 3. descendants キーに含まれる ID 配列を返す
        if (isset($json_data['descendants']) && is_array($json_data['descendants'])) {
            return $json_data['descendants'];
        }

        return [];
    }


    /**
     * 指定した親パスの直下の子要素を取得 (#1 用)
     */
    public static function get_children_by_parent_path($parent_path = NULL) {
        global $wpdb;
        $table = $wpdb->prefix . 'kx_hierarchy';
        $query = $parent_path === NULL
            ? "SELECT * FROM $table WHERE parent_path IS NULL"
            : $wpdb->prepare("SELECT * FROM $table WHERE parent_path = %s", $parent_path);

        return $wpdb->get_results($query);
    }


    /**
     * $post_id から親階層の post_id を取得する
     * * @param int $post_id 自身のポストID
     * @return int|null 親の post_id。存在しない場合は null、親が仮想階層の場合は 0
     */
    public static function get_parent($post_id) {
        // 1. 自身のデータをキャッシュ/DBからロード
        $my_data = self::load_raw_data($post_id);

        // 2. parent_path を取得
        $parent_path = $my_data['parent_path'] ?? null;

        if (empty($parent_path)) {
            return null; // 親がいない（ルート）場合
        }

        // 3. 親のデータを「フルパス」をキーにしてロード
        // 注意：load_raw_data_common は ID (post_id) 指定なので、
        // パス指定で取得するための軽量なラッパー、あるいは直接取得を行う
        $parent_data = self::get_data_by_path($parent_path);

        // 親のレコード自体がない場合は null、存在すればその ID を返す
        if ($parent_data === null) {
            return null;
        }

        return (int)($parent_data['post_id'] ?? 0);
    }

    /**
     * フルパスをキーに階層データをロードする（キャッシュ対応）
     * get_parent などで親の情報を辿る際に使用
     */
    public static function get_data_by_path($path) {
        global $wpdb;
        $table = self::t();

        // パスをキーにしたキャッシュがあればそれを返すロジックが望ましいが、
        // 最低限「DBからARRAY_A（連想配列）」で取得し、形式を統一する
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE full_path = %s", $path),
            ARRAY_A
        );
    }

    /**
     * 指定されたポストのアラート状態（alertカラムの値）を取得する
     * * @param int $post_id ポストID
     * @return int 0:正常, 1:raretuなし, 2:short_codeなし。レコード不在時は 0。
     */
    public static function get_alert($post_id) {
        // 1. キャッシュまたはDBからデータをロード
        // 内部で parent::load_raw_data_common を呼び出し、Dyにキャッシュされる
        $data = self::load_raw_data($post_id);

        // 2. alert カラムの値を返す
        // レコードが存在し、かつ alert カラムがあればその値を数値で返す
        if (isset($data['alert'])) {
            return (int)$data['alert'];
        }

        return 0;
    }


    /**
     * 階層構造におけるアラート状態（羅列コード不足等）を判定し数値を返す
     * * @param int $post_id
     * @return int 0:正常, 1:raretu_code不足, 2:short_code不足(実体未設定)
     */
    public static function set_hierarchy_alert($post_id) {
        if (!$post_id) return 0;

        // 子がいなければ、コンテンツに関わらず正常(0)
        if (!self::get_children($post_id)) return 0;

        // 子がいる場合のみ、kx1の内容を詳細チェック
        return self::check_from_kx1($post_id);
    }

    /**
     * wp_kx_1 から ShortCODE / raretu の設定状況を判定
     * * @param int $post_id
     * @return int 1 or 2 (不備あり), 0 (正常)
     */
    private static function check_from_kx1($post_id) {
        if (!kx::get_short_code($post_id)) return 2;
        if (!kx::get_raretu_code($post_id)) return 1;
        return 0;
    }



    /**
     * 階層構造におけるアラート状態を判定（外部・表示用）
     * * @param int $post_id 判定対象のポストID
     * @return bool アラートが必要な場合は true
     */
    public static function check_hierarchy_alert($post_id) {
        // 判定ロジックを呼び出し、0(正常)より大きければアラートありとみなす
        return (self::set_hierarchy_alert($post_id) > 0);
    }


    /**
     * JSONデータのクリーンアップと整合性チェック
     * * 1. descendants: wp_kx_0 に不在、またはパスが前方一致しないIDを排除
     * 2. ancestry: wp_kx_0 に不在のIDを "virtual" に置換
     * 3. save_with_log を通じて text カラム（日時+フラグ）を更新
     * * @param array $json_data  デコード済みのJSON配列
     * @param array $data       保存対象のレコードデータ (full_path 等を含む)
     * @return array クリーンアップ後の $data
     */
    private static function clean_json_id($json_data, $data,$post_id,$path_index) {
        global $wpdb;
        $kx0_table = $wpdb->prefix . 'kx_0';
        $my_path = Dy::get_title($post_id);

        $depth_chek = $path_index['depth'] + 1;

        unset($json_data['alert']);
        unset($json_data['raretu']);

        // descendants の検証
        if (
            !empty($json_data['descendants'])
            && is_array($json_data['descendants'])
            && ( $post_id == $data['post_id'] )
        ) {

            $cleaned_descendants = [];

            foreach ($json_data['descendants'] as $child_id) {

                $chil_path_index = Dy::set_path_index($child_id);

                if ($depth_chek == $chil_path_index['depth'] ) {

                    // 階層パスの前方一致チェック (かつての子を排除)
                    $child_title = Dy::get_title($child_id);
                    $expected_prefix = $my_path ;
                    if (strpos($child_title, $expected_prefix) === 0) {

                        $cleaned_descendants[] = (string)$child_id;
                    }
                }

            }
            $json_data['descendants'] = $cleaned_descendants;
        }


        if (
            !empty($json_data['virtual_descendants'])
            && is_array($json_data['virtual_descendants'])
            && ( $post_id == $data['post_id'] )
        ) {
            $_virtual_descendants = [];
            foreach( $json_data['virtual_descendants'] as $_title )
            {
                $target_path = $my_path.'≫'.$_title;
                $_table_name = $wpdb->prefix . 'kx_hierarchy';

                // 改修ポイント：post_id だけでなく is_virtual も取得、または存在確認
                $v_node = $wpdb->get_row($wpdb->prepare(
                    "SELECT post_id, is_virtual FROM $_table_name WHERE full_path = %s",
                    $target_path
                ));

                // DBにレコードが存在し、かつ仮想ノードである場合のみリストに残す
                // レコードが削除されていれば、ここを通らないので自動的に除外される
                if ( $v_node && (int)$v_node->is_virtual === 1 ) {
                    $_virtual_descendants[] = $_title;
                }
            }
            $json_data['virtual_descendants'] = $_virtual_descendants;
        }


        // ancestry の検証
        if (!empty($json_data['ancestry']) && is_array($json_data['ancestry'])) {
            foreach ($json_data['ancestry'] as $level => $parent_id) {
                if ($parent_id === 'virtual') continue;
                $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $kx0_table WHERE id = %d", $parent_id));
                if (!$exists) {
                    $json_data['ancestry'][$level] = "virtual";
                }
            }
        }

        // 最終データをJSON化してセット
        $data['json'] = json_encode($json_data, JSON_UNESCAPED_UNICODE);
        return $data;
    }



    /**
     * 親レコードに対し、自身を子（descendants または virtual_descendants）として登録
     */
    private static function register_to_parent($parent_path, $my_id, $my_name, $table) {
        global $wpdb;
        $parent = $wpdb->get_row($wpdb->prepare("SELECT json FROM $table WHERE full_path = %s", $parent_path));
        if (!$parent) return;

        $json = json_decode($parent->json, true) ?: [];
        // 変更があったかどうかを判定するフラグを初期化
        $updated = false;


        if ($my_id > 0) {
            // 実体がある場合：IDを descendants に追加
            $list = isset($json['descendants']) ? (array)$json['descendants'] : [];
            if (!in_array((string)$my_id, $list)) {
                $list[] = (string)$my_id;
                $json['descendants'] = array_values(array_unique($list));
                // IDが追加されたのでフラグを真にする
                $updated = true;

            }
        } else {
            // 実体がない場合：名前を virtual_descendants に追加
            $list = isset($json['virtual_descendants']) ? (array)$json['virtual_descendants'] : [];
            if (!in_array($my_name, $list)) {
                $list[] = $my_name;
                $json['virtual_descendants'] = array_values(array_unique($list));
                // 名前が追加されたのでフラグを真にする
                $updated = true;
            }
        }
        // フラグが true の場合（＝リストに未登録だった場合）のみ保存を実行
        if ($updated) {
            // 親の更新時も履歴を残す
            self::save_with_log($parent_path, ['json' => json_encode($json, JSON_UNESCAPED_UNICODE)], 'u');
        }

        // 親の更新時も履歴を残す
        //self::save_with_log($parent_path, ['json' => json_encode($json, JSON_UNESCAPED_UNICODE)], 'u');
    }



    /**
     * 指定されたベースタイトル以下の階層構造を修復する
     * @param string $base_title 起点となるタイトル
     * @param bool   $recursive  全下位階層をスキャンするかどうか
     */
    public static function repair_hierarchy($base_title, $recursive = false) {
        global $wpdb;
        $table = $wpdb->prefix . 'kx_0'; // 実体データがあるテーブル

        if ($recursive) {
            // 前方一致で指定タイトル以下の全ポストを取得
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT id, title FROM $table WHERE title LIKE %s",
                $base_title . '%'
            ));
        } else {
            // 直下の階層（次の≫まで）だけを対象にするなど、要件に応じた取得
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT id, title FROM $table WHERE title = %s",
                $base_title
            ));
        }

        if ($results) {
            foreach ($results as $row) {
                // 現在の最新ロジックである sync を呼び出すことで、
                self::sync(['id' => $row->id, 'title' => $row->title], 'update');
            }
        }
    }

    /**
     * メンテナンス用：DB整合性クリーンアップ
     * kx0 テーブルに存在しない実体レコードを hierarchy から削除する。
     * ※仮想フォルダ (post_id=0) は維持する。
     */
    public static function maintenance_cleanup() {
        global $wpdb;
        $table_h = self::t(); // wp_kx_hierarchy
        $table_0 = $wpdb->prefix . 'kx_0';

        $messages = [];

        // 1. 実体レコード (is_virtual=0) なのに kx0 に ID が存在しないものを抽出
        // 外部結合を行い、kx0側のIDが NULL のものを対象とする
        $targets = $wpdb->get_results("
            SELECT h.full_path, h.post_id
            FROM $table_h AS h
            LEFT JOIN $table_0 AS k0 ON h.post_id = k0.id
            WHERE h.is_virtual = 0
              AND h.post_id > 0
              AND k0.id IS NULL
        ");

        if (!empty($targets)) {
            foreach ($targets as $target) {
                // 削除実行
                $wpdb->delete($table_h, ['full_path' => $target->full_path], ['%s']);

                $messages[] = [
                    'type' => 'warn',
                    'text' => "整合性エラー: kx0に存在しないID {$target->post_id} を削除しました。 Path: {$target->full_path}"
                ];
            }
        }

        // 2. 逆に kx0 にはあるが hierarchy にないものは、本来 sync() で生成されるべきだが、
        // このクリーンアップでは「ゴミ出し」に専念するため、削除のみを行う。

        // 結果を KxMessage に通知
        if (empty($messages)) {
            \Kx\Utils\KxMessage::info("Hierarchy クリーンアップ完了: 整合性に問題はありませんでした。");
        } else {
            foreach ($messages as $msg) {
                \Kx\Utils\KxMessage::warn($msg['text']);
            }
        }
        return '━DB kxHierarchy━COUNT：'.count($messages);
    }


    /**
     * 仮想レコードのクリーンアップ
     * 子（実体・仮想）を持たない仮想フォルダを削除し、json内の不整合を修正する。
     */
    public static function maintenance_virtual_cleanup() {
        global $wpdb;
        $table = self::t();

        // 1. 仮想レコードを階層の深い順に取得
        $virtuals = $wpdb->get_results("SELECT * FROM $table WHERE is_virtual = 1 ORDER BY level DESC");

        $deleted_count = 0;
        $updated_count = 0;

        foreach ($virtuals as $row) {
            $json = json_decode($row->json, true) ?: [];
            $full_path = $row->full_path;

            // --- A. 子（実体/仮想レコード）の存在チェック ---
            // parent_path が自身の full_path であるレコードが1つでもあれば「子あり」
            $has_child_record = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE parent_path = %s",
                $full_path
            ));

            // --- B. json内の descendants チェック (不整合修正) ---
            $changed = false;

            // 1. virtual_descendants の精査
            if (!empty($json['virtual_descendants'])) {
                foreach ($json['virtual_descendants'] as $v_path => $v_data) {
                    // そのパスのレコードが実際に存在するか
                    $v_exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE full_path = %s", $v_path));
                    if (!$v_exists) {
                        unset($json['virtual_descendants'][$v_path]);
                        $changed = true;
                    }
                }
            }

            // 2. vdescendants (ID配列等) の精査
            if (!empty($json['vdescendants'])) {
                foreach ($json['vdescendants'] as $key => $child_id) {
                    // 実体IDの親パスが自分と一致するか確認
                    $current_parent = $wpdb->get_var($wpdb->prepare(
                        "SELECT parent_path FROM $table WHERE post_id = %d AND is_virtual = 0",
                        $child_id
                    ));
                    if ($current_parent !== $full_path) {
                        unset($json['vdescendants'][$key]);
                        $changed = true;
                    }
                }
                // 配列の詰め直し
                if ($changed) $json['vdescendants'] = array_values($json['vdescendants']);
            }

            // --- C. 最終判定：削除 or 更新 ---
            // 子レコードもなく、json内の子リストも空になった場合
            $has_any_descendant = (!empty($json['virtual_descendants']) || !empty($json['vdescendants']));

            if (!$has_child_record && !$has_any_descendant) {
                // 完全に子がいないのでレコード削除
                $wpdb->delete($table, ['full_path' => $full_path], ['%s']);
                $deleted_count++;
                \Kx\Utils\KxMessage::info("仮想削除: 子が不在のため {$full_path} を破棄しました。");
            } elseif ($changed) {
                // 子はあるが、json内の不整合を修正して保存
                $wpdb->update($table, ['json' => json_encode($json, JSON_UNESCAPED_UNICODE)], ['full_path' => $full_path]);
                $updated_count++;
                \Kx\Utils\KxMessage::notice("仮想修正: {$full_path} の子リストを同期しました。");
            }
        }

        return "━DB kxHierarchy━Virtual：COUNT: 削除 {$deleted_count}件 / 更新 {$updated_count}件";
    }

    /**
     * 指定したポストIDに関連する独自DBレコードを抹消し、ログを出力する
     * * @param int    $post_id 投稿ID
     * @param string $reason  削除理由 ('trash' | 'mismatch' | 'missing' 等)
     */
    public static function purge_post_record($post_id, $reason = 'trash') {
        if (!$post_id) return;

        global $wpdb;

        // 1. メタデータ層(wp_kx_1)の削除
        // 理由が 'trash'（ゴミ箱）の場合のみ実行。
        // パス不一致(mismatch)の場合はメタデータを残さないと、GhostON等の設定が消えてしまうため。
        if ($reason === 'trash' ) {
            DB::delete_by_unique_id($post_id, $wpdb->prefix . 'kx_0');
            DB::delete_by_unique_id($post_id, $wpdb->prefix . 'kx_1');
        }

        // 2. 論理構造層(wp_kx_hierarchy)の削除
        // パスが古い、または実体がないため、階層レコードは理由を問わず削除。
        self::delete_hierarchy_record($post_id);
        DB::delete_by_unique_id($post_id, $wpdb->prefix . 'kx_hierarchy');

        // 3. メモリキャッシュ(Dy)のクリア
        if (class_exists('\Kx\Core\DynamicRegistry')) {
            Dy::set_content_cache($post_id, 'raw', [
                'db_kx_hierarchy' => null
            ]);
        }

        // 4. 画面にログを残す
        $color = ($reason === 'trash') ? 'red' : 'orange';
        echo '<span style="color:'.$color.';">[post_id: '.$post_id.' | '.$reason.' Deleted]</span>';
    }



    /**
     * Undocumented function
     *
     */
    private static function save_with_log($path, $data, $action_type) {
        global $wpdb;
        $table = $wpdb->prefix . 'kx_hierarchy';

        // 1. 保存用メタデータの付与
        // $action_type は将来的に別のカラムに保存する場合のために引数としては残しておきます
        $data['time'] = time();

        // full_path が存在するか確認
        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE full_path = %s", $path));

        if ($exists) {
            // UPDATE実行
            $result = $wpdb->update($table, $data, ['full_path' => $path]);
        } else {
            // INSERT実行
            $data['full_path'] = $path;
            $result = $wpdb->insert($table, $data);
        }

        // 2. キャッシュ同期
        if ($result !== false) {
            $post_id = $data['post_id'] ?? null;
            if ($post_id) {
                Dy::set_content_cache($post_id, 'raw', [
                    'db_kx_hierarchy' => $data
                ]);
            }
        }

        return $result;
    }


    /**
     * 指定されたフルパスから階層情報を取得する
     * * @param string $path 検索するフルパス（例：Α≫Β）
     * @return array|null 該当レコード、存在しない場合はnull
     */
    public static function get_node_by_path(string $path): ?array {
        global $wpdb;
        $table = 'wp_kx_hierarchy'; // 物理テーブル名 [cite: 2]

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE full_path = %s", $path),
            ARRAY_A
        );

        return $row ?: null;
    }



    /**
     * DBから生のデータを取得し、Dyキャッシュに格納する
     * * @param int $post_id
     * @return array|null
     */
    public static function load_raw_data($post_id) {
        return parent::load_raw_data_common($post_id, 'db_kx_hierarchy','post_id');
    }



    /**
     * ゴミ箱入りのポストに関連する階層レコードを物理削除する
     * * @param int $post_id 投稿ID
     */
    public static function delete_hierarchy_record($post_id) {
        //echo $post_id;
        global $wpdb;
        $table = $wpdb->prefix . 'kx_hierarchy';

        // 該当IDのレコードを削除
        $wpdb->delete($table, ['post_id' => $post_id], ['%d']);
    }

}