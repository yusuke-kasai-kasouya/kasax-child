<?php
/**
 *[Path]: inc/core/class-kx-color-manager.php
 */

namespace Kx\Core;
use Kx\Core\SystemConfig as Su;
use Kx\Core\DynamicRegistry as Dy;
use Kx\Core\TitleParser as Tp;

class ColorManager {

    /**
     * Undocumented function
     *
     * @param [type] $post_id
     * @param string $type
     * @return void
     */
    public static function get_by_id($post_id, $type = 'std') {

        $title = Dy::get_title($post_id) ?? get_the_title($post_id)?? null;
        return self::resolve($title, $type, $post_id);
    }

    /**
     * Undocumented function
     *
     * @param [type] $title
     * @param string $type
     * @return void
     */
    public static function get_by_context($title, $type = 'std') {

        return self::resolve($title, $type);
    }

    /**
     * タイトルや投稿ID、表示タイプに基づいて、最適な配色設定（CSS変数とクラスセット）を解決します。
     * * [ロジック概要]:
     * 1. visual_config.json の 'contexts' から固定プリセットを読み込み CSS変数化。
     * 2. タイトルの単語判定（match_context）により動的な Hue（色相）と Tone（トーン）を決定。
     * 3. $type（表示場所）に応じた追加スタイルとクラスセットを抽出・合成。
     *
     * @param string|null $title   判定対象の文字列（投稿タイトルなど）
     * @param string      $type    表示コンテキスト（'std', 'card', 'body' など）
     * @param int|null    $post_id 投稿ID（タイトルが渡されない場合の代替、または特定記事設定の取得用）
     * @return array {
     * @var string $colormgr_id 生成された設定の一意識別ID
     * @var int    $hue         決定された色相値
     * @var string $style_base  標準的な style 属性用文字列（背景色＋文字色）
     * @var array  $style_array CSS変数のみ、タイプ別追加スタイル、およびプリセット(contexts)の統合配列
     * @var array  $class_array 表示タイプに紐づく CSS クラス(traits)の配列
     * }
     */
    protected static function resolve($title, $type, $post_id = null) {
        $config = Su::get('visual_config');

        // A1. JSONから contexts（固定プリセット）部分を取得し、CSS変数文字列に変換
        $contexts_raw = $config['contexts'] ?? [];
        $contexts_rendered = [];

        foreach ($contexts_raw as $key => $value) {
            $contexts_rendered[$key] = sprintf(
                "--kx-hue:%s;--kx-sat:%s;--kx-lum:%s;",
                $value['hue'],
                $value['sat'],
                $value['lum']
            );
        }

        // 1. 既存の分岐ロジックで動的な色を決定
        $match    = self::match_context($title, $config, $post_id);
        $hue      = $match['hue'];
        $tone_key = $match['tone'];

        // 2. トーンデータの取得
        $t = $config['tones'][$tone_key] ?? $config['tones']['default'];

        // 3. style（paint）文字列の生成
        $style_base = sprintf(
            "--kx-hue:%s;--kx-sat:%s;--kx-lum:%s;--kx-alp:%s;color:%s;",
            $hue, $t['sat'], $t['lum'], $t['alp'], $t['txt_col']
        );

        $style_vars_only = sprintf(
            "--kx-hue:%s;--kx-sat:%s;--kx-lum:%s;--kx-alp:%s;",
            $hue, $t['sat'], $t['lum'], $t['alp']
        );

        // 4. Typeに基づく追加styleの取得と統合
        $extra_styles = $config['type_styles'][$type] ?? $config['type_styles']["default"] ?? [];

        // 配列の拡張（動的変数と固定コンテキストをマージ）
        $extra_styles['vars_only']  = $style_vars_only;
        $extra_styles['type_extra'] = $style_vars_only . ($extra_styles['base'] ?? '');
        $extra_styles['outline']    = $style_vars_only .
                                    "border-left: 4px solid hsla(var(--kx-hue), var(--kx-sat), var(--kx-lum), 0.8);".
                                    "background: hsla(var(--kx-hue), var(--kx-sat), var(--kx-lum), 0.01);".
                                    "padding: 10px 10px 10px 0.5em; " ;

        unset($extra_styles['base']);

        // 固定プリセットを style_array にマージ（後からマージすることでcontextsを優先）
        $extra_styles = array_merge($extra_styles, $contexts_rendered);

        // 5. Typeに基づく追加クラス（traits）の取得
        $extra_traits = $config['type_traits'][$type] ?? $config['type_traits']["default"] ?? [];

        return [
            'colormgr_id' => $hue . $tone_key . '_' . $type,
            'hue'         => $hue,
            'style_base'  => $style_base,
            'style_array' => $extra_styles,
            'class_array' => $extra_traits,
        ];
    }



    /**
     * タイトルから色相とトーンを導き出すエンジン
     */
    private static function match_context($title, $config, $post_id = null) {
        // 0. 初期値（どこにも該当しない場合）
        $res = [
            'hue'  => $config['default']['hue'] ?? 0,
            'tone' => $config['default']['tone'] ?? 'default'
        ];

        $parts = [];

        // --- ここから Dy 呼び出しロジック ---
        if ($post_id) {
            // エイリアスマップ経由で ana.path.parts (分割済み配列) を取得
            $cached_parts = Dy::get_path_index($post_id)['parts'] ?? explode('≫', $title);

            if (is_array($cached_parts)) {
                $parts = $cached_parts;
            }
        }

        if (empty($parts) && is_string($title)) {
            $sep = Tp::get_system('separator') ?? '≫';
            $parts = explode($sep, $title);
        }

        $prefix = mb_substr($title, 0, 1);

        // --- 2-A1. キャラクター判定（最優先・基礎色確定） ---
        $path_index = Dy::get_path_index($post_id)??[];
        $type = $path_index['type']??'';

        if ($type === 'prod_character_core' || $type === 'prod_character_relation') {

            // 判定に合致した属性を一括抽出
            $attr = Tp::get_attr($type, $post_id); // $parts は配列
            //var_dump($attr);
            //echo kx_dump($attr);

            // ターゲットコード（c または ＼c の後の数値）を特定
            // 関係性なら index:2 (＼c)、単体なら index:1 (c) を使用
            $target_code = ($attr[1] ?? null);


            if ($target_code) {
                $work_id = $attr[0]; // プレフィックス(∬)除去済みの値
                $char_match = self::match_context_character($work_id, $target_code, $config);

                if ($char_match) {
                    $res = $char_match; // 基礎色をキャラ色に
                }
            }
        }


        // --- 2-A2. 先頭記号(prefix)による判定（基礎色確定） ---
        // A1を通過していない（$resが初期値のまま）場合のみ実行
        if ($res['hue'] === $config['default']['hue'] && $res['tone'] === ($config['default']['tone'] ?? 'default')) {
            if (isset($config['prefix_patterns'][$prefix])) {
                $res = [
                    'hue'  => $config['prefix_patterns'][$prefix]['hue'],
                    'tone' => $config['prefix_patterns'][$prefix]['tone']
                ];
            } else {
                foreach ($config['prefix_patterns'] as $symbol => $data) {
                    if (mb_strpos($title, $symbol) !== false) {
                        $res = [
                            'hue'  => $data['hue'],
                            'tone' => $data['tone']
                        ];
                        break;
                    }
                }
            }
        }

        // --- 2-B. パーツ位置判定 (上書きロジック) ---
        // A1/A2で決まった $res を、パーツ位置の特定条件で書き換える
        $res = self::match_context_parts($prefix, $parts, $config, $res);
        $res = self::match_context_global($title, $config, $res);



        return $res;
    }



    /**
     * キャラクターコードから色相を導き出すエンジン
     * * @param string $work_id   作品ID（例: "04", "10"）
     * @param string $char_code 抽出されたキャラコード（例: "001", "15arg"）
     * @param array  $config    visual_config の配列
     */
    private static function match_context_character($work_id, $char_code, $config) {

        //$target_code = ltrim($char_code, 'c');

        // 1. キャラクター用デフォルト値の準備
        // 指定作品IDやパターンに該当しない場合はこの値が返る
        $res = [
            'hue'  => $config['default_character']['hue'] ?? 0,
            'tone' => $config['default_character']['tone'] ?? 'default'
        ];

        // 2. 作品別マスタの取得（なければ default_work を参照）
        $char_master = $config['character_logic'] ?? [];
        $patterns = $char_master[$work_id] ?? $char_master['default_work'] ?? [];

        // 3. パターンマッチング（前方一致）
        // JSONのキー（"1", "30"等）で始まるキャラコードを走査 [#3の条件反映]
        foreach ($patterns as $pattern => $data) {

            // char_code が $pattern から始まっているか判定
            if (strpos($char_code, (string)$pattern) === 0) {
                return [
                    'hue'  => $data['hue']  ?? $res['hue'],
                    'tone' => $data['tone'] ?? 'primary' // キャラは基本鮮やかに
                ];
            }
        }

        // 4. 何にもマッチしなかった場合はデフォルト値を返す
        return $res;
    }


    /**
     * 接頭辞と階層パーツの位置に基づく書き換え判定（一致パターン拡張版）
     */
    private static function match_context_parts($prefix, $parts, $config, $current_res) {
        $logic_list = $config['context_parts_logic'] ?? [];

        foreach ($logic_list as $logic) {
            // 1. 接頭辞フィルタ
            if (isset($logic['prefix']) && $prefix !== $logic['prefix']) continue;

            $idx = $logic['index'] ?? -1;
            $match_val = $logic['match'] ?? null;
            $match_type = $logic['match_type'] ?? 'full'; // デフォルトは完全一致

            if (!isset($parts[$idx])) continue;

            $target_part = $parts[$idx];
            $is_matched = false;

            // 2. マッチング種別の判定
            switch ($match_type) {
                case 'prefix': // 前方一致
                    $is_matched = (strpos($target_part, $match_val) === 0);
                    break;
                case 'suffix': // 後方一致
                    $is_matched = (str_ends_with($target_part, $match_val));
                    break;
                case 'full': // 完全一致
                default:
                    $is_matched = ($target_part === $match_val);
                    break;
            }

            // 3. 一致した場合のみ update 実行
            if ($is_matched) {
                if (isset($logic['update']['hue'])) {
                    $current_res['hue'] = $logic['update']['hue'];
                }
                if (isset($logic['update']['tone'])) {
                    $current_res['tone'] = $logic['update']['tone'];
                }
            }
        }

        return $current_res;
    }

    /**
     * タイトル全体の特定単語に基づくグローバル書き換え判定
     * 階層位置や接頭辞を無視して、キーワードの有無で最終決定を行う
     */
    private static function match_context_global($title, $config, $current_res) {
        $global_logic = $config['global_word_logic'] ?? [];

        foreach ($global_logic as $logic) {
            $match_val = $logic['match'] ?? null;
            $match_type = $logic['match_type'] ?? 'partial'; // デフォルトは部分一致
            $is_matched = false;

            if (empty($match_val)) continue;

            switch ($match_type) {
                case 'prefix': // 前方一致
                    $is_matched = (strpos($title, $match_val) === 0);
                    break;
                case 'suffix': // 後方一致
                    $is_matched = (str_ends_with($title, $match_val));
                    break;
                case 'full': // 完全一致
                    $is_matched = ($title === $match_val);
                    break;
                case 'partial': // 部分一致（どこかに含まれていればOK）
                default:
                    $is_matched = (str_contains($title, $match_val));
                    break;
            }

            if ($is_matched) {
                if (isset($logic['update']['hue']))  $current_res['hue']  = $logic['update']['hue'];
                if (isset($logic['update']['tone'])) $current_res['tone'] = $logic['update']['tone'];
            }
        }

        return $current_res;
    }

    /**
     * 表示タイプに応じたCSSクラスセットを返す
     */
    private static function get_traits_by_type($type, $config) {
        $type_traits = $config['type_traits'] ?? [];

        // 指定されたtypeがあれば返し、なければdefault、それもなければ空文字
        return $type_traits[$type] ?? ($type_traits['default'] ?? '');
    }
}