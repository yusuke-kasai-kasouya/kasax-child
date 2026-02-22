<?php
/**
 * [Path]: inc\core\class-kx-consolidator.php
 */

namespace Kx\Core;

use Kx\Core\SystemConfig as Su;
use Kx\Core\DynamicRegistry as Dy;
use Kx\Core\KxDirector as kx;
use \Kx\Utils\KxMessage as Msg;
use Kx\Core\TitleParser as Tp;
use Kx\Utils\Toolbox;
use Exception;

class Kx_Consolidator {
    // 処理中かどうかを保持するスタティック変数
    private static $current_depth = 0;
    private static $max_depth = 4; // 4階層まで許可


    /**
     * 統合処理のメイン実行 (Orchestrator)
     *
     * @return string|false 成功時は生成されたテキスト、致命的失敗時は false
     */
    public static function run($source_id, $post_id, $args = []) {
        $source_id = (int)$source_id;
        $post_id = (int)$post_id;

        // --- DB保存 または EPUB出力 の場合は分割せず単純結合 ---
        $is_db_dest = (isset($args['dest']) && $args['dest'] === 'db');
        $is_epub    = (isset($args['ext']) && $args['ext'] === 'epub');

        $is_single_export = $args['single_export'] ?? false ;

        if ($is_db_dest || $is_epub) {
            $args['type']  = 'simple'; // 強制的にシンプル結合
            $args['split'] = false;    // 分割フラグを物理的に折る
            // ※ needs_splitting 内でも参照される $args['split'] を false にすることで分割を阻止
        }

        if (!self::validate_execution($source_id, $post_id, $args)) return false;


        self::$current_depth++;
        try {
            // --- IDの収集 ---
            if ($is_single_export) {
                // 単体保存モード：自分自身のIDだけを対象にする
                $ids = [$source_id];
            } else {
                // 統合モード：子記事や指示書に基づき全IDを収集
                $ids = self::collect_source_ids($source_id);
            }

            if (empty($ids)) {
                self::$current_depth--;
                return false;
            }

            $post_data = self::fetch_and_prepare_sources($ids, $source_id);
            if (empty($post_data)) {
                self::$current_depth--;
                return false;
            }

            $combined_text = self::generate_combined_text_by_type($post_data, $args);

            // 保存処理を実行し、その成否を確認
            $save_success = self::execute_output($post_data, $post_id, $args, $combined_text);

            // 判定関数による分岐
            // 分割が必要なら実行（ただし結果を return せず、処理だけさせる）
            if (self::needs_splitting($combined_text, $args)) {
                // 分割ファイル群を生成（内部で execute_output が複数回呼ばれる）
                self::run_split_strategy($post_data, $post_id, $args);
                // ※分割されたことをユーザーに知らせるメッセージを足しても良い
                Msg::info("AI制限により、追加で分割ファイルが生成されました。");
            }


            self::$current_depth--;

            // 保存に失敗した場合は false、成功ならテキストを返す
            return ($save_success) ? $combined_text : false;

        } catch (Exception $e) {
            self::$current_depth--;
            Msg::error("Consolidator Error: " . $e->getMessage());
            return false;
        }
    }




    /**
     * 'with_header:10104' のような文字列からIDを抽出するヘルパー
     */
    private static function extract_template_id($type_str) {
        if (strpos($type_str, ':') !== false) {
            return (int)explode(':', $type_str)[1];
        }
        return null;
    }



    /**
     * 1. 実行前のバリデーション・認証チェック
     * * @param int   $source_id 指示書ID
     * @param int   $post_id   出力先ID
     * @param array $args      引数
     * @return bool            実行可能ならtrue
     */
    private static function validate_execution($source_id, $post_id, $args) {
        $dest = $args['dest'] ?? 'db';

        // 1. 階層制限
        if (self::$current_depth >= self::$max_depth) {
            Msg::warn("Consolidator: ID[$post_id] 階層制限超過。");
            return false;
        }

        // 2. 必須パラメータ
        if (empty($source_id) || empty($post_id)) {
            Msg::warn("パレメーター不足");
            return false;
        }

        // 自己統合の特殊判定
        if ($source_id == $post_id) {
            if ($dest === 'db') {
                Msg::error("Consolidator: 自己上書き(DB)は禁止されています。");
                return false;
            }
            return true; // 自己統合(file等)は許可
        }

        // 3. 相互認証チェック
        ContextManager::sync($source_id);
        $authorized_to = Dy::get_content_cache($source_id, 'consolidated_to') ?? null;

        // リモート統合の認証
        if (!$authorized_to || $authorized_to != $post_id) {
            Msg::warn("相互認証：リモート統合の認証");
            return false;
        }

        return true;
    }


    /**
     * 2. 統合対象となる子記事IDリストを収集する
     * 2-1. Dyキャッシュの 'descendants' (固定リスト) を優先
     * 2-2. なければ 'raretuCODE' (動的クエリ) を解析して実行
     * * @param int $source_id 指示書ID
     * @return array 収集されたID配列
     */
    private static function collect_source_ids($source_id) {
        // 1. 固定IDリストがあるか確認
        $ids = Dy::get_content_cache($source_id, 'descendants') ?? null;

        if (empty($ids)) {
            // 2. 動的クエリ(raretuCODE)の取得と解析
            $raretuCODE = Dy::get_content_cache($source_id, 'raretu_code');
            if ($raretuCODE) {
                $raretuCODE_atts = shortcode_parse_atts($raretuCODE);

                // 自身を除外するロジックを含む既存の収集メソッドを呼び出し
                $ids = self::fetch_ids_by_query($raretuCODE_atts, $source_id);
            }
        }

        return is_array($ids) ? array_filter($ids) : [];
    }


    /**
     * 3. 子記事のデータを取得し、クリーニングとバリデーションを行う
     * * @param array $ids                 ソースとなる子記事のID配列
     * @param int   $source_id 指示書（Dyキャッシュ用）のID
     * @return array                     正規化された投稿データの配列
     */
    private static function fetch_and_prepare_sources($ids, $source_id) {
        $data = [];

        foreach ($ids as $id) {
            // 1. 子記事の初期化（再帰的な処理が必要な場合のフック用）
            ContextManager::sync($id);
            $title = Dy::get_title($id);
            $title = end(Dy::get_path_index($id)['parts']);

            // 2. 概要、Ghostの場合はidを置換。
            $_overview =  Dy::get_content_cache($id,'overview_from') ?? null;
            $_GhostON = Dy::get_content_cache($id,'ghost_to') ?? null;
            $_ShortCODE = Dy::get_content_cache($id,'short_code') ?? null;

            $_IntegratedOverview = Kx::is_integrated($id) ;

            $effective_id = $_overview ?? $_GhostON ?? $id;

            $post = get_post($effective_id);

            // 3. 基本チェック：存在しない、または公開済み(publish)以外はスキップ
            $is_overview_to =  Dy::get_content_cache($id,'overview_to') ?? null;
            if (!$post || get_post_status($id) !== 'publish') continue;
            if ($is_overview_to ) continue;
            if ($_IntegratedOverview ) continue;
            if ($_ShortCODE && $_ShortCODE == 'raretu' && !$_overview ) continue;



            // 4. コンテンツのクリーニング
            // 「タグ：...」から始まり、オプションで「___」区切り線を含む行を削除
            $content = $post->post_content;
            $pattern = '/^タグ：.*(?:\R___)?\R?/mu';
            $cleaned_content = preg_replace($pattern, '', $content);

            // 5. データのパッケージ化
            $data[] = [
                'id'      => (int)$effective_id,
                'title'   => $title,
                'content' => trim($cleaned_content), // 前後の余計な空行を排除
                'time'    => (int)get_post_modified_time('U', false, $effective_id),
            ];
        }

        // 5. 自然順（タイトル昇順）でソート
        usort($data, function($a, $b) {
            return strnatcmp($a['title'], $b['title']);
        });

        return $data;
    }

    /**
     * 4. 生成されたデータを指定の形式で出力・保存する (Executor)
     * * @param array  $post_data     整形済みのソースデータ配列
     * @param int    $post_id       出力先（Target）のポストID
     * @param array  $args          実行引数
     * @param string $combined_text 外部で生成済みの結合テキスト。指定があれば生成処理をスキップする。
     * @return bool                 保存・更新に成功した場合は true、失敗時は false
     */
    private static function execute_output(array $post_data, int $post_id, array $args, $combined_text = '') {
        $dest = $args['dest'] ?? 'db';

        // 1. テキストが渡されていない場合のみ生成
        if (empty($combined_text)) {
            $combined_text = self::generate_combined_text_by_type($post_data, $args);
        }

        if (empty($combined_text)) return false;

        // --- 2. 出力処理 ---
        if ($dest === 'db') {
            return self::save_to_db($post_id, $combined_text, $post_data);
        }

        if ($dest === 'file') {
            // プロンプトが含まれた $combined_text をそのまま保存
            self::save_to_text_file($post_id, $combined_text, $args);
            return true;
        }

        return false;
    }



    /**
     * 既存システムに影響を与えず #2 のロジックを利用する
     */
    private static function fetch_ids_by_query($atts, $exclude_id) {

    // 1. Query クラスに渡すための最小限の準備
        $atts['post_id'] = $exclude_id;

        // 2. 旧仕様属性を新仕様へ正規化（これを追加）
        // Orchestrator側で normalize_legacy_atts を public static にしておく必要があります
        $atts = \Kx\Matrix\Orchestrator::normalize_legacy_atts($atts);



        // 3. Query クラスをインスタンス化
        $query_engine = new \Kx\Matrix\Query($atts);

        // 4. 直接取得
        $ids = $query_engine->direct_fetch_table_ids();

        return !empty($ids) ? $ids : [];
    }



    /**
     * 分岐1: 単純結合
     */
    private static function merge_simple($post_data) {
        $contents = array_column($post_data, 'content');
        return implode("\n\n", $contents);
    }


    /**
     * 子記事用の構造化ユニット（IDとタイトルを付与）
     */
    private static function format_structured_unit($id, $title, $content) {
        $unit = "［ID_{$id} ］\n";
        $unit .= "Post_title: {$title}\n";
        $unit .= "内容:\n";
        $unit .= $content . "\n";
        return $unit;
    }

    /**
     * 子記事群の結合
     */
    private static function merge_structured($post_data) {
        $blocks = [];
        foreach ($post_data as $post) {
            $blocks[] = self::format_structured_unit($post['id'], $post['title'], $post['content']);
        }
        return implode("\n\n___\n", $blocks);
    }

    /**
     * ヘッダー（プロンプト）用の整形ユニット
     * メタ情報は付与せず、生テキストと区切り線を管理
     */
    private static function format_header_unit($content) {
        if (empty($content)) return '';

        $unit = trim($content) . "\n\n___\n";
        return $unit;
    }

    /**
     * フッター（最終指示）用の整形ユニット
     * LLMへの明示的なラベルとセパレーターを付与
     */
    private static function format_footer_unit($content) {
        if (empty($content)) return '';

        $unit = "\n\n___\n";
        $unit .= "＜Final_Instruction＞\n";
        $unit .= trim($content) . "\n"; // ← ここで終わっている
        return $unit;
    }



    /**
     * UI（ボタン一式）をレンダリングする
     */
    public static function render_ui($post_id, $mode = 'batch') {
        if (empty($post_id)) return;

        // 1. モードに応じた設定の切り分け
        if ($mode === 'single_post') {
            // 単体保存モードの設定
            $args = self::get_suggested_args($post_id, 'single');

            // 重要なフラグ：統合処理をスキップし、このIDのみを処理対象にする
            $args['type']  = 'simple';
            $args['split'] = false;
            $args['single_export'] = true; // エンジン側での判定用

            // ボタンの生成と出力（returnではなくechoで統一）
            return self::build_button_html($post_id, '単独保存', $args);

        }

        // 2. 統合系（batch）モードの判定ロジック
        // 現在の記事が「ソース（統合指示書）」か「ターゲット（出力先）」かを判定
        $context = Dy::get_content_cache($post_id, 'raretu_code') ? 'source' : 'target';

        $args = self::get_suggested_args($post_id, $context);
        $label = ($context === 'source') ? '統合指示を実行' : '最新データに同期';

        echo self::build_button_html($post_id, $label, $args);
    }

    /**
     * コンテキストに応じた推奨引数を取得
     */
    private static function get_suggested_args($post_id, $context) {
        return [
            'type' => ($context === 'target') ? 'structured' : 'simple',
            'dest' => 'file', // 今回は確実に動かすため file 固定
            'single_export' => false,
        ];
    }


    /**
     * 統合実行ボタンのHTMLを生成
     */
    private static function build_button_html($post_id, $label, $args) {
        $action_url = get_stylesheet_directory_uri() . '/pages/export-engine.php';

        // 1. プロンプト選択肢の構築
        $options = [
            'simple'     => '単純結合',
            'structured' => '構造化データ',
        ];

        $config = Su::get('consolidator');
        $prompts = $config['prompts'] ?? [];
        foreach ($prompts as $id => $data) {
            if ($id == 'default') continue;
            $options["with_header:{$id}"] = $data['name'];
        }

        // 2. AIモデル選択肢
        $ai_models = $config['ai_model_specs'] ?? [];

        // 3. サニタイズ（テキスト置換）レベルの定義
        $sanitize_levels = [
            0 => '無効 (Raw)',
            1 => 'Lv1: 緩め',
            2 => 'Lv2: 基準',
            3 => 'Lv3: 強め',
            4 => 'Lv4: パターン置換',
        ];

        // 自動判定の取得
        $_determine = self::determine_default_args($post_id);
        $args['type']  = $_determine['type'] ?? $args['type'] ?? 'simple';
        $args['color'] = $_determine['color'] ?? 'hsl(0, 0%, 50%)';

        // 単体書き出し（single_export）時の強制上書き設定
        if (!empty($args['single_export'])) {
            $args['type']  = 'simple';             // 構造化（階層収集）せず、その記事のみを対象に
            $args['color'] = 'hsl(200, 70%, 50%)'; // ボタン等の色を「単体用」に上書き（例：青系）
        }



        // --- HTML構築開始 ---
        $html = '<h3>統合ファイル保存</h3>';
        $html .= '<form method="post" action="' . esc_url($action_url) . '" target="_blank" style="margin-bottom:20px;">';
        $html .= '<input type="hidden" name="post_id" value="' . (int)$post_id . '">';
        $html .= '<input type="hidden" name="single_export" value="' . $args['single_export'] . '">';

        // --- テンプレート選択（判定カラー付き） ---
        $html .= '<div style="border-right: 5px solid ' . esc_attr($args['color']) . '; padding-left: 10px; margin-bottom:10px;">';
        $html .= '<label style="font-size:11px; display:block; color:#888;">プロンプト形式:</label>';
        $html .= '<select name="template_id" style="font-size:12px; padding:2px; width:220px;">';
        $current_selected_type = $args['type'];
        foreach ($options as $val => $name) {
            $selected = ((string)$val === (string)$current_selected_type) ? ' selected' : '';
            $html .= '<option value="' . esc_attr($val) . '"' . $selected . '>' . esc_html($name) . '</option>';
        }
        $html .= '</select>';
        $html .= '</div>';

        // --- AIモデル選択 ---
        $html .= '<div style="margin-bottom:10px;padding-left: 10px;">';
        $html .= '<label style="font-size:11px; display:block; color:#888;">分割形式:</label>';
        $html .= '<select name="ai_select" style="font-size:12px; padding:2px; width:220px;">';
        $default_ai = $args['ai_select'] ?? 'gemini';
        foreach ($ai_models as $key => $spec) {
            $selected_ai = ($key === $default_ai) ? ' selected' : '';
            $display_name = $spec['name'] ?? $key;
            $html .= '<option value="' . esc_attr($key) . '"' . $selected_ai . '>' . esc_html($display_name) . '</option>';
        }
        $html .= '</select>';
        $html .= '</div>';

        // --- サニタイズレベル選択 (新設) ---
        $html .= '<div style="margin-bottom:15px;padding-left: 10px;">';
        $html .= '<label style="font-size:11px; display:block; color:#888;">テキスト置換レベル:</label>';
        $html .= '<select name="sanitize_level" style="font-size:12px; padding:2px; width:220px; border-color:#aaa;">';

        // JSONの初期設定レベルを取得（初期値1）
        $proc_config = Su::get('text-processor');
        $default_lv = (int)($proc_config['sanitizer_settings']['level'] ?? 2);


        foreach ($sanitize_levels as $lv => $label_text) {
            $selected_lv = ($lv === $default_lv) ? ' selected' : '';
            $html .= '<option value="' . (int)$lv . '"' . $selected_lv . '>' . esc_html($label_text) . '</option>';
        }
        $html .= '</select>';
        $html .= '</div>';

        // --- 追記：出力形式（拡張子）選択 ---
        $html .= '<div style="margin-bottom:15px;padding-left: 10px;">';
        $html .= '<label style="font-size:11px; display:block; color:#888;">出力形式:</label>';
        $html .= '<select name="export_format" style="font-size:12px; padding:2px; width:220px; border-color:#aaa;">';
        $export_formats = ['txt' => 'Text (.txt)', 'md' => 'Markdown (.md)', 'epub' => 'eBook (.epub)'];
        foreach ($export_formats as $ext => $f_label) {
            $html .= '<option value="' . esc_attr($ext) . '">' . esc_html($f_label) . '</option>';
        }
        $html .= '</select>';
        $html .= '</div>';



        $html .= '<div style="padding-left: 20px;"><button type="submit" class="button button-primary">' . esc_html($label) . '</button></div>';
        $html .= '</form>';

        return $html;
    }


    /**
     * ポストの属性やタイトルから、デフォルトのプロンプト形式とカラーを判定する
     * * 暫定実装：将来的に JSON 側の設定へ移行予定
     *
     * @param int   ＄post_id  対象ポストID
     * @param array ＄ids      収集された子記事ID群
     * @return array { type: string, color: string }
     */
    public static function determine_default_args($post_id) {
        $config = Su::get('consolidator') ?? [];

        // デフォルト値の設定
        $_promptID = 'structured';
        $_color = 'hsl(150, 100%, 50%)'; // スプリンググリーン

        if ( Tp::is_type('phil_xampp_driven', $post_id)) {
            $_promptID = 'simple';
        }

        // --- A. IDによる直接マッチング (priority_id_map) ---
        $id_map = $config['priority_id_map'] ?? [];
        if (isset($id_map[$post_id])) {
            $_promptID = $id_map[$post_id];
            $_color = 'hsl(270, 100%, 50%)'; // マゼンタ系
        }

        // --- B. 文脈・型によるマッチング (context_type_map) ---
        if ($_promptID === 'structured') { // IDマップで見つかっていない場合のみ実行
            $type_map = $config['context_type_map'] ?? [];
            foreach ($type_map as $key => $val) {
                // Tp::is_type を用いて、パスの接頭辞や種別（works等）を判定
                if (class_exists('Kx\Core\TitleParser') && Tp::is_type($key, $post_id)) {
                    $_promptID = $val;
                    $_color = 'hsl(200, 100%, 50%)'; // シアン系
                    break; // 最初に見つかったものを優先
                }
            }
        }


        // --- C. 最終的な出力フォーマット成形 ---
    if ($_promptID === 'simple' || $_promptID === 'structured') {
        $type_value = $_promptID;
        if ($_promptID === 'simple') {
            $_color = 'hsl(30, 100%, 50%)'; // オレンジ
        }
    } else {
        // 数値ID（10104等）は with_header 形式に変換
        $type_value = "with_header:{$_promptID}";
        // ID指定の場合は少し色を強調（紫系）
        if (!isset($_color)) $_color = 'hsl(270, 100%, 60%)';
    }

        return [
            'type'  => $type_value,
            'color' => $_color,
            'ai_select' => 'gemini' // デフォルトAI
        ];
    }


    /**
     * 種別に応じてテキストを結合する
     */
    private static function generate_combined_text_by_type($post_data, $args) {
        $type_raw = $args['type'] ?? 'simple';

        // 'with_header:10101' のような形式を分解
        if (strpos($type_raw, 'with_header:') === 0) {
            list($mode, $prompt_id) = explode(':', $type_raw);

            // プロンプト記事の本文を取得
            $prompt_content = get_post_field('post_content', (int)$prompt_id);

            // プロンプト + 結合データ (Structured) を返す
            return $prompt_content . "\n\n" . self::merge_structured($post_data);
        }

        switch ($type_raw) {
            case 'structured': return self::merge_structured($post_data);
            default:           return self::merge_simple($post_data);
        }
    }


    /**
     * 生成されたテキストが制限値を超えているか判定する
     * * @param string $combined_text 生成済みテキスト
     * @param array  $args          実行引数（ai_selectを含む）
     * @return bool                 制限を超えている場合（分割が必要な場合）はtrue
     */
    private static function needs_splitting(string $combined_text, array $args) {

        // --- 0. 明示的な分割阻止ガード ---
        // $args['split'] が false の場合は、文字数に関わらず分割しない
        if (isset($args['split']) && $args['split'] === false) {
            return false;
        }

        if($args['ai_select'] == 'default'){
            return false;
        }

        // 1. JSON設定から制限値を取得
        $specs = Su::get('consolidator')['ai_model_specs'] ?? [];
        $ai_type = $args['ai_select'] ?? 'gemini';

        // 2. 指定されたモデルの設定を取得（なければdefault、それもなければ20000）
        $spec = $specs[$ai_type] ?? ($specs['default'] ?? ['max_length' => 20000]);
        $max_len = (int)$spec['max_length'];

        // 3. 現在のテキスト長を取得（マルチバイト対応）
        $current_len = mb_strlen($combined_text, 'UTF-8');

        // 4. 判定（上限を超えていれば true）
        if ($current_len > $max_len) {
            Msg::info("AI制限超過を検知: {$current_len} / {$max_len} 文字。分割保存に移行します。");
            return true;
        }

        return false;
    }



    /**
     * AI制限を超えた場合の分割・多段階出力戦略 (Sub-Orchestrator)
     */
    private static function run_split_strategy(array $post_data, int $post_id, array $args) {
        try {
            // 1. 準備：設定とデータの仕分け
            $config = self::get_split_config($args);
            $streams = self::split_post_data_by_exclusion($post_data, $config['exclude_ids']);
            $global_count = 1;



            // 2. 実行：フェーズごとに処理（この並びがファイル出力順になる）

            // Phase 1: メインデータの分割出力
            $global_count = self::output_main_chunks($streams['main'], $post_id, $args, $config, $global_count);

            // Phase 2: 個別除外ファイルの出力
            $global_count = self::output_excluded_units($streams['excluded'], $post_id, $args, $global_count);

            // Phase 3: 最終指示プロンプトの出力
            $processed_ids = array_column($post_data, 'id');
            self::output_final_instruction($post_id, $args, $config, $global_count, $processed_ids);

            Msg::info("分割戦略完了: 合計 " . ($global_count - 1) . " 個のパーツを生成しました。");
            return true;

        } catch (Exception $e) {
            Msg::error("Split Strategy Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 分割実行に必要な設定（JSON）を取得
     */
    private static function get_split_config(array $args) {
        $config = Su::get('consolidator');
        $template_id = self::extract_template_id($args['type'] ?? '');

        return [
            'exclude_ids'    => $config['exclude_ids'] ?? [],
            'prompt_config'  => $config['prompts'][$template_id] ?? [],
            'default_config' => $config['prompts']['default'] ?? [],
            'important'      => $config['important'] ?? [],
            'ai_specs'       => $config['ai_model_specs'][$args['ai_select'] ?? 'gemini'] ?? $config['ai_model_specs']['default'],
            'template_id'    => $template_id
        ];
    }

    /**
     * 除外IDリストに基づきデータを2系統に分離
     */
    private static function split_post_data_by_exclusion(array $post_data, array $exclude_ids) {
        $streams = ['main' => [], 'excluded' => []];
        foreach ($post_data as $data) {
            if (in_array($data['id'], $exclude_ids)) {
                $streams['excluded'][] = $data;
            } else {
                $streams['main'][] = $data;
            }
        }
        return $streams;
    }


    /**
     * メインデータのチャンク分割保存
     */
    private static function output_main_chunks(array $main_data, int $post_id, array $args, array $config, int $start_count) {
        if (empty($main_data)) return $start_count;

        // AIモデルのスペックに基づき、安全マージン 0.9 で制限設定
        $limit = (int)$config['ai_specs']['max_length'] * 0.9;

        // 設定のマージ：default をベースに個別設定を上書き
        $default_tpl = $config['default_config']['split_templates'] ?? [];
        $current_tpl = $config['prompt_config']['split_templates'] ?? [];
        $tpl = array_merge($default_tpl, $current_tpl);

        // 上記で作成したチャンク分割関数を呼び出し
        $chunks = self::create_manual_chunks($main_data, $limit);

            //echo kx_dump($default_tpl);
            //echo kx_dump($tpl);


        $count = $start_count;
        foreach ($chunks as $idx => $chunk) {
            $is_first = ($idx === 0);
            $chunk_ids_str = implode(',', array_map(fn($d) => "［ID_{$d['id']}］", $chunk));

            // マージ済み設定 $tpl から文言を取得（個別になければ default が使われる）
            $header = $is_first ? ($tpl['first1'] ?? "") : ($tpl['middle1'] ?? "");
            $footer = $tpl['first_middle_Footer'] ?? "";

            // ID置換
            $header = str_replace('［ID_x］', $chunk_ids_str, $header);

            // 組み立て：【Part x】、指示文（ヘッダー）、形式（フッター）、データ本体
            $full_prompt = "【Part {$count}】\n" . trim($header) . "\n\n" . trim($footer) . "\n\n" . self::merge_structured($chunk);

            $tmp_args = array_merge($args, [
                'sub_dir'   => 'split_parts',
                'part_name' => "{$args['ai_select']}_Part{$count}_Main"
            ]);
            self::execute_output($chunk, $post_id, $tmp_args, $full_prompt);
            $count++;
        }
        return $count;
    }

    /**
     * 記事データの配列を、指定された文字数制限（$limit）に基づいて
     * 複数のチャンク（グループ）に分割する
     */
    private static function create_manual_chunks(array $main_data, int $limit) {
        $chunks = [];
        $current_chunk = [];
        $current_len = 0;

        foreach ($main_data as $data) {
            // 出力時と同じ形式で文字数を計測
            $unit_text = self::format_structured_unit($data['id'], $data['title'], $data['content']);
            $unit_len = mb_strlen($unit_text, 'UTF-8');

            // 現在のチャンクが空でなく、かつ追加すると制限を超える場合は次のチャンクへ
            if (!empty($current_chunk) && ($current_len + $unit_len > $limit)) {
                $chunks[] = $current_chunk;
                $current_chunk = [];
                $current_len = 0;
            }
            $current_chunk[] = $data;
            $current_len += $unit_len;
        }

        // 最後の残りを追加
        if (!empty($current_chunk)) {
            $chunks[] = $current_chunk;
        }

        return $chunks;
    }

    /**
     * ID群をWhy/How/Whatおよび重要度で分類する
     */
    private static function classify_ids_for_special_task(array $ids, array $config) {
        $res = [
            'why' => [], 'how' => [], 'what' => [],
            'imp1' => [], 'imp2' => []
        ];

        // JSONから直接、または引数から重要度設定を取得
        $imp_config = $config['important'] ?? [];

        foreach ($ids as $id) {
            $num = (int)$id;
            // ゴールデンサークル分類
            if ($num >= 10 && $num <= 19) $res['why'][] = "［ID_{$id}］";
            elseif ($num >= 20 && $num <= 9999) $res['how'][] = "［ID_{$id}］";
            else $res['what'][] = "［ID_{$id}］";

            // 重要度分類
            foreach ($imp_config['1st'] ?? [] as $v) {
                if ($v == $id) $res['imp1'][] = "［ID_{$id}］";
            }
            foreach ($imp_config['2nd'] ?? [] as $v) {
                if ($v == $id || (preg_match('/^\d{5}$/', $id) && strpos($id, (string)$v) === 0)) {
                    $res['imp2'][] = "［ID_{$id}］";
                }
            }
        }
        return $res;
    }


    /**
     * 除外（巨大ファイル等）の個別保存
     */
    private static function output_excluded_units(array $excluded_data, int $post_id, array $args, int $start_count) {
        $count = $start_count;
        foreach ($excluded_data as $ex_data) {
            $text = "タスク：［ID_{$ex_data['id']}］以下を全体資料に追加せよ。\n\n";
            $text .= self::format_structured_unit($ex_data['id'], $ex_data['title'], $ex_data['content']);

            $tmp_args = array_merge($args, [
                'sub_dir'   => 'split_parts',
                'part_name' => "Part{$count}_Extra_ID{$ex_data['id']}"
            ]);
            self::execute_output([$ex_data], $post_id, $tmp_args, $text);
            $count++;
        }
        return $count;
    }



    /**
     * 最終指示書の保存（動的分類ロジック含む）
     */
    private static function output_final_instruction(int $post_id, array $args, array $config, int $count, array $processed_ids = []) {


        $template_id = $config['template_id'];
        if (!$template_id) return;

        $tpl = array_merge($config['default_config']['split_templates'] ?? [], $config['prompt_config']['split_templates'] ?? []);

        // 再取得せず、渡された（精査済みの）IDリストを使用する
        $ids = !empty($processed_ids) ? $processed_ids : self::collect_source_ids($post_id);

        // 分類実行
        $cls = self::classify_ids_for_special_task($ids, $config);

        $tasks = [];
        for ($i = 1; $i <= 5; $i++) {
            $key = "final_{$i}";
            if (empty($tpl[$key])) continue;
            $content = trim($tpl[$key]);

            switch ($i) {
                case 1:
                    $id_list = implode("\n", array_map(fn($id) => "［ID_{$id} ］", $ids));
                    $content = str_replace('［ID_番号］', "\n" . $id_list, $content);
                    break;
                case 4:
                    // テンプレートにラベルがなければ追記、あれば置換
                    $gc_text = "\nWhy：" . implode(',', $cls['why']) . "\nHow：" . implode(',', $cls['how']) . "\nWhat：" . implode(',', $cls['what']);
                    $content .= (strpos($content, 'Why：') === false) ? $gc_text : "";
                    break;
                case 5:
                    $imp_text = "\n最重要：" . implode(',', $cls['imp1']) . "\n重要：" . implode(',', $cls['imp2']);
                    $content .= (strpos($content, '最重要：') === false) ? $imp_text : "";
                    break;
            }
            $tasks[] = $content;
        }

        $text = "【Part {$count}：最終指示書】\n\n" . implode("\n\n", $tasks) . "\n\n合計：" . count($ids);
        $text .= "\n--------------------------------\n指示詳細：\n" . get_post_field('post_content', $template_id);


        self::execute_output([], $post_id, array_merge($args, ['sub_dir' => 'split_parts', 'part_name' => "Part{$count}_Final_Prompt"]), $text);
    }



    /**
     * 段階的サニタイズ処理 (数式保護機能付き)
     * * @param string $content 置換対象テキスト
     * @param array  $args    実行時引数
     * @return string 置換後テキスト
     */
    private static function apply_inclusive_sanitization($content, $args = []) {
        $config = Su::get('text-processor');
        $enabled = $config['sanitizer_settings']['enabled'] ?? false;
        if (!$enabled || empty($content)) return $content;

        $level = isset($args['sanitize_level'])
                 ? (int)$args['sanitize_level']
                 : (int)($config['sanitizer_settings']['level'] ?? 2);

        if ($level <= 0) return $content;

        // --- 1. 退避処理: LaTeX数式を保護 (Base64エンコード) ---
        // $...$ または $$...$$ で囲まれた部分を一時的に退避させる
        $content = preg_replace_callback('/\$.*?\$/s', function($matches) {
            return '__LATEX_SAFE__' . base64_encode($matches[0]) . '__';
        }, $content);

        // --- 2. 段階的サニタイズ実行 (この間、数式は保護されている) ---

        // Lv1: Inclusive
        if ($level >= 1) {
            $mapping = $config['level1'] ?? [];
            if (!empty($mapping)) $content = strtr($content, $mapping);
        }

        // Lv2: Compliance
        if ($level >= 2) {
            $mapping = $config['level2'] ?? [];
            if (!empty($mapping)) $content = strtr($content, $mapping);

            // 正規表現パターン置換 (タイムスタンプ等)
            $patterns = $config['pattern_filters_lv2'] ?? [];
            if (!empty($patterns)) {
                foreach ($patterns as $pattern => $replacement) {
                    $content = preg_replace($pattern, $replacement, $content);
                }
            }
        }

        // Lv3: Anonymization
        if ($level >= 3) {
            $mapping = $config['level3'] ?? [];
            if (!empty($mapping)) $content = strtr($content, $mapping);
        }

        // Lv4: Pattern Filters
        if ($level >= 4) {
            $patterns = $config['pattern_filters'] ?? [];
            foreach ($patterns as $pattern) {
                $content = preg_replace($pattern, '［MASKED_PATTERN］', $content);
            }
        }

        // --- 3. 復元処理: 退避させていた数式を元に戻す ---
        $content = preg_replace_callback('/__LATEX_SAFE__(.*?)__/', function($matches) {
            return base64_decode($matches[1]);
        }, $content);



        return $content;
    }


    /**
     * DB保存用サブメソッド：変更検知と更新を実行
     * * 改良点：保存先ポストの先頭にある「タグ：」行を保護し、新しい内容と合体させる。
     */
    private static function save_to_db(int $post_id, string $combined_text, array $post_data) {

        // --- 1. 現存する「タグ：」行の抽出と保護 ---
        $current_content = get_post_field('post_content', $post_id);
        $existing_tag_line = '';

        // 行頭の「タグ：」から改行（および任意で___）までをマッチング
        $tag_pattern = '/^タグ：.*(?:\R___)?\R?/mu';
        if (preg_match($tag_pattern, $current_content, $matches)) {
            $existing_tag_line = $matches[0];
        }

        // 保護したタグ行を、新しく生成されたテキストの先頭に結合
        $final_content = $existing_tag_line . $combined_text;

        // --- 2. 更新判定（時間軸） ---
        $target_time = (int)get_post_modified_time('U', false, $post_id);
        $needs_update = false;
        foreach ($post_data as $data) {
            if ($data['time'] > $target_time) {
                $needs_update = true;
                break;
            }
        }

        // --- 3. 内容比較（最終的な合体後の内容で比較） ---
        // md5比較対象を $final_content に変更
        $is_different = (strlen($final_content) !== strlen($current_content))
                        || (md5($final_content) !== md5($current_content));

        // 差分がなければスキップ
        if (!$needs_update && !$is_different) {
            return false;
        }

        // --- 4. 更新実行 ---
        $result = wp_update_post([
            'ID'           => $post_id,
            'post_content' => $final_content, // タグを維持したコンテンツで更新
        ]);

        $title = Dy::get_title($post_id)??null;

        if (!is_wp_error($result)) {
            Msg::info("Consolidator: ID[$post_id] ：『[$title]』を最新の状態に同期しました（タグを維持）。");
            return true;
        }

        return false;
    }



    /**
     * ファイル保存用ヘルパーメソッド
     */
    private static function save_to_text_file($post_id, $content, $args) {
        $post = get_post($post_id);
        $title = $post ? $post->post_title : 'no-title';

        // 分割保存時（Part1など）の識別子を取得
        $part_suffix = isset($args['part_name']) ? "_{$args['part_name']}" : "";

        $target_ext = $args['ext'] ?? 'txt';

        $options = [
            'use_time' => true,
            'use_id'   => true,
            'ext'      => $target_ext,
            'sub_dir'  => $args['sub_dir'] ?? '',
            // 識別子をプレフィックスに含めることでファイル名の重複を回避
            'prefix'   => 'WP'
        ];

        //置換
        $content = self::apply_inclusive_sanitization($content, $args);

        if ($target_ext === 'epub') {
            // Pandocが解釈しやすいよう最低限のHTMLタグで包む

            $content = Toolbox::convert_content_to_epub_html($content,$post_id, $title = 'no-title');
        }

        $meta = [
            'id'    => $post_id,
            'title' => $title . $part_suffix,
            'ext'   => $target_ext // #1へ渡す
        ];

        return Toolbox::save_text_to_local($content, $meta, $options);
    }


}