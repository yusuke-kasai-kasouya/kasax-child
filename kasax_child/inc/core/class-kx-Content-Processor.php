<?php
/**
 * [Path]: inc\core\class-kx-Content-Processor.php
 */

namespace Kx\Core;

use Kx\Core\SystemConfig as Su;
use Kx\Core\DynamicRegistry as Dy;
use Kx\Core\KxDirector as Kx;
use Kx\Utils\KxMessage as Msg;

/**
 * 投稿本文の変換ロジックを管理するクラス
 */
class ContentProcessor {

    /**
     * メイン変換エントリ
     */
    public static function compile($text, $post_id, $type = '') {
        if (empty($text)) return '';

        // エディタ表示時は置換を行わない
        if (!empty($_GET['action']) && $_GET['action'] == 'edit') {
            return $text;
        }

        if (Kx::get_short_code($post_id) && $type !== 'epub') {
            return $text;
        }

        // 1. 変換前処理
        $processed = self::pre_process($text);


        // 2. Markdownパース
        $parsed = self::parse_markdown($processed['text']);



        // 3. 変換後処理
        $final_text = self::post_process($parsed, $processed['math_stack'], $post_id);

        if ($type === 'epub') {
            return $final_text;
        }

        return '<span class="__kxad_content">' . $final_text . '</span>';
    }

    /**
     * 変換前処理：記号による見出し化とMathJax保護
     */
    private static function pre_process($text) {

        // 記号（■◆▼など）をMarkdownの見出し記号へ変換
        $text = self::convert_symbol_headings($text);

        $math_stack = [];
        // 数式保護：$~$を一時文字列へ退避
        $text = preg_replace_callback('/\$([\s\S]*?)\$/', function ($matches) use (&$math_stack) {
            $index = count($math_stack);
            $math_stack[] = $matches[0];
            return "＿MATHJAX＿TEMP＿NUMBER＿{$index}＿";
        }, $text);

        return [
            'text'       => $text,
            'math_stack' => $math_stack
        ];
    }

    /**
     * 記号による独自見出し記法をMarkdown形式へ変換
     */
    private static function convert_symbol_headings($text) {
        // パターンと置換後を分離して配列にする
        $patterns = [
            '/(^|\n|\])■(.*?)(\n|\s|<br \/>|　)/',
            '/(^|\n|\])◆(.*?)(\n|\s|<br \/>|　)/',
            '/(^|\n|\])▼(.*?)(\n|\s|<br \/>|　)/',
            '/(^|\n|\])□(.*?)(\n|\s|<br \/>|　)/',
            '/(^|\n|\])✚(.*?)(\n|\s|<br \/>|　)/',
            '/(^|\n|\])✤(.*?)(\n|\s|<br \/>|　)/'
        ];
        $replacements = [
            '$1##■$2$3',
            '$1###$2$3',
            '$1####$2$3',
            '$1#####$2$3',
            '$1#####$2$3',
            '$1######$2$3'
        ];

        // preg_replace は配列を受け取ることができ、内部で効率よく処理される
        return preg_replace($patterns, $replacements, $text);
    }

    /**
     * Markdownパース実行
     */
    private static function parse_markdown($text) {
        if (preg_match('/<(html|body)[\s>]/i', $text)) {
            return $text;
        }

        try {
            if (class_exists('KxParsedown')) {
                $parsedown = new \KxParsedown();
                $parsedown->setBreaksEnabled(true);
                return $parsedown->text($text);
            } else {
                Msg::warn('KxParsedown class not found.');
            }
        } catch (\Exception $e) {
            Msg::error(['Markdown Parse Error', $e->getMessage()]);
        }

        return $text;
    }

    /**
     * 変換後処理：テーブル装飾、数式復元、事後置換
     */
    private static function post_process($text, $math_stack, $post_id) {
        $path_index = Dy::get_path_index($post_id);

        // 1. テーブル装飾
        if (strpos($text, "<table") !== false) {
            $text = self::apply_table_styles($text);
        }

        // 2. リンク：URLの自動リンク化
        if (strpos($text, "http") !== false) {
            $text = self::convert_urls_to_links($text);
        }

        // タイムスタンプのカラー化 (クラス内メソッドを呼び出し)
        $text = self::apply_timestamp_coloring($text);

        $text = self::apply_contextual_regex_rules($text,$post_id);

        $text = self::apply_symbol_to_html_expansion($text, $post_id);

        $text = self::apply_color_styled_replacements($text, $post_id);


        // 独自・汎用置換ロジック (旧 kx_change_any_texts を内部実行)
        $text = self::apply_custom_replacements($text, $post_id);

        // 3. 数式の復元
        if (!empty($math_stack)) {
            foreach ($math_stack as $index => $formula) {
                $placeholder = "＿MATHJAX＿TEMP＿NUMBER＿{$index}＿";
                $text = str_replace($placeholder, $formula, $text);
            }
        }

        return $text;
    }

    /**
     * テーブル内の行に交互の背景色を適用する (DOM操作)
     */
    private static function apply_table_styles($text) {
        // UTF-8 エンティティ変換
        $text = mb_convert_encoding($text, 'HTML-ENTITIES', 'UTF-8');

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $text, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $tables = $dom->getElementsByTagName('table');

        foreach ($tables as $table) {
            $rows = $table->getElementsByTagName('tr');
            foreach ($rows as $index => $row) {
                if ($index === 0) {
                    $style = 'background-color: hsla(ヾ色相ヾ, 100%, 50%, 0.1);font-size: 14px;line-height: 2.0;';
                } elseif ($index % 2 === 0) {
                    $style = 'background-color: hsla(0, 0%, 50%, 0.1);font-size: 14px;line-height: 2.0;';
                } else {
                    $style = 'background-color: hsla(0, 0%, 50%, 0.01);font-size: 14px;line-height: 2.0;';
                }
                $row->setAttribute('style', $style);
            }
        }

        $result = $dom->saveHTML();
        return mb_convert_encoding($result, 'UTF-8', 'HTML-ENTITIES');
    }

    /**
     * 文中のURLを検索し、リンク（<a>タグ）に変換する
     */
    private static function convert_urls_to_links($text) {
        // すでにリンク化されているものや、タグの属性内にあるURLを除外するための正規表現
        // 🔗マークとurldecodeを適用するため preg_replace_callback を使用
        return preg_replace_callback(
            '/(?<!["\'(=])https?:\/\/[\w\/:%#\$&\?\(\)~\.=\+\-]+/i',
            function ($matches) {
                $url = $matches[0];
                $decoded_url = urldecode($url);
                // 🔗マークを付与し、aタグで囲む
                return '<a href="' . $url . '" target="_blank" rel="noopener">🔗' . $decoded_url . '</a>';
            },
            $text
        );
    }

    /**
     * タイムスタンプのカラー化置換 (旧 kx_change_any_texts_time)
     */
    private static function apply_timestamp_coloring($text) {
        // 来歴のポストでは、日時表記をしない判定
        preg_match('/p=(\d{1,})/', $_SERVER['REQUEST_URI'], $matches);
        $_raireki_on = (!empty($matches[1]) && preg_match('/≫来歴/', get_the_title($matches[1]))) ? 1 : null;
        unset($matches);

        // タイムスタンプの正規表現を取得
        $timestamp_preg = Su::get('system_internal_schema')['regex_patterns']['timestamp'];
        if (!$timestamp_preg || !preg_match_all($timestamp_preg, $text, $matches)) {
            return $text;
        }

        foreach ((array)$matches[0] as $_timestamp) {
            $formatted_timestamp = str_replace('_', '-', $_timestamp);

            try {
                $date_obj = new \DateTime($formatted_timestamp);
                $unix_time = $date_obj->format('U');
            } catch (\Exception $e) {
                continue; // 日付パース失敗時はスキップ
            }

            // カラー計算
            $color = self::calculate_time_color($unix_time);

            preg_match('/\d{2}(\d{2})-(\d{2})-(\d{2})/', $formatted_timestamp, $m);

            if (empty($_raireki_on)) {
                // $1:世紀, $2:年, $3:-, $4:月, $5:-, $6:日
                $replacement = '<span style="font-size:xx-small;opacity:0;display:inline-block;margin-right:-1.5em;margin-left:.75em;">$1</span>' .
                               '<span style="font-size:xx-small;color:hsla('. $color['h'] .','. $color['s'] .'%,'. $color['l'] .'%,.'. $color['a'] .');">$2_$4<span style="font-size:xx-small;opacity:0;display:inline-block;margin-right:-4px;">_</span>$6</span>';
            } else {
                $replacement = '';
            }

            $text = preg_replace(
                '/(\d{2})(' . $m[1] . ')(_|-)(' . $m[2] . ')(_|-)(' . $m[3] . ')/',
                $replacement,
                $text
            );
        }

        return $text;
    }
    /**
     * タイム差に基づくHSLAカラー計算 (旧 kx_time_color)
     */
    private static function calculate_time_color($modified_date) {
        $_time_margin = time() - $modified_date;
        $_time_day    = 60 * 60 * 24;
        $_time_p_day  = $_time_margin / $_time_day;
        $_time_year2  = $_time_day * 365 * 2;

        // 色相(H): 経過日数に応じて90(緑)から減少
        $_h = floor(90 - ($_time_p_day / 4));
        if ($_h < 0) $_h = 0;

        // 彩度(S): 2年で0(グレー)へ
        $_s = 100 - ($_time_margin / $_time_year2 * 100);
        if ($_s < 0) $_s = 0;

        // 明度(L)
        $_l = 50;

        // 透明度(A)
        if ($_time_p_day < 1) {
            $_a = 5;
        } else {
            $_a = 25;
        }

        // 5年以上経過
        if ($_time_p_day > (365 * 5)) {
            $_h = 240; // 青系
            $_s = 50;
            $_a = 75;
        }

        return [
            'h' => $_h,
            's' => $_s,
            'l' => $_l,
            'a' => $_a,
        ];
    }


    /**
     * ポストタイプ（type）に基づく正規表現置換の実行
     */
    private static function apply_contextual_regex_rules($text, $post_id) {
        $config = Su::get('ContentProcessor');
        $all_rules = $config['regex_replacement_rules'] ?? [];

        foreach ($all_rules as $entry) {
            if (empty($entry['type']) || empty($entry['rules'])) continue;

            // "global" 指定があるか、is_type が配列（デフォルトOR判定）で真を返せばマッチとみなす
            // $entry['type'] が 文字列 でも 配列 でもそのまま渡せる
            if ($entry['type'] === 'global' || kx::is_type($entry['type'], $post_id)) {
                $text = preg_replace(
                    array_keys($entry['rules']),
                    array_values($entry['rules']),
                    $text
                );
            }
        }

        return $text;
    }


    /**
     * ポストタイプ（type）に基づき、特定の記号を複雑なHTML構造へ展開する
     */
    private static function apply_symbol_to_html_expansion($text, $post_id) {
        $config = Su::get('ContentProcessor');
        $templates = $config['html_templates'] ?? [];
        $expansion_data = $config['symbol_expansion_rules'] ?? [];

        foreach ($expansion_data as $entry) {
            // 必須データの存在確認
            if (empty($entry['type']) || empty($entry['rules'])) continue;

            // kx::is_type が配列を直接受け取り、内部で論理判定を行う
            if ($entry['type'] === 'global' || kx::is_type($entry['type'], $post_id)) {
                foreach ($entry['rules'] as $pattern => $template_key) {
                    if (isset($templates[$template_key])) {
                        // テンプレートを使用して記号をHTMLへ展開
                        $text = preg_replace($pattern, $templates[$template_key], $text);
                    }
                }
            }
        }

        return $text;
    }

    /**
     * ポストタイプに基づき、スタイルを伴う置換を実行（最適化版）
     */
    private static function apply_color_styled_replacements($text, $post_id) {
        $config = Su::get('ContentProcessor');
        $all_rules = $config['color_replacement_rules'] ?? [];
        $style_map = $config['color_styles'] ?? [];
        $kakujyoshi = $config['preg_kakujyoshi'] ?? 'が|を|に|へ|と|より|から|で|や|の|も|は';

        foreach ($all_rules as $entry) {
            if (empty($entry['type']) || empty($entry['rules'])) continue;
            if ($entry['type'] !== 'global' && !kx::is_type($entry['type'], $post_id)) continue;

            foreach ($entry['rules'] as $pattern => $params) {
                $pattern = str_replace('ヾ格助詞ヾ', $kakujyoshi, $pattern);

                // --- テンプレート生成 (ループ外で1回だけ行う) ---
                $replacement_template = self::build_replacement_span($params, $style_map);

                /**
                 * [r]タグ回避とパターンの動的合成
                 * 1. 既存パターンのデリミタとフラグを分離
                 * 2. (*SKIP)(*F) を使い、[r...] を含む行をマッチング対象から完全に除外
                 * 3. マルチラインモード(m)を強制し、行単位での除外判定を最適化
                 */
                $delimiter = $pattern[0]; // 最初の一文字をデリミタとみなす
                $last_delimiter_pos = strrpos($pattern, $delimiter);
                $raw_pattern = substr($pattern, 1, $last_delimiter_pos - 1);
                $flags = substr($pattern, $last_delimiter_pos + 1);

                // 結合パターン: 「[r」で始まる行ならスキップ(SKIP)、それ以外でパターンにマッチ(raw_pattern)
                // ※元々のフラグに 'm' が含まれていなくても機能するよう明示的に付与
                $combined_pattern = "/^.*\[r\b.*$(*SKIP)(*F)|{$raw_pattern}/m{$flags}";

                $text = preg_replace($combined_pattern, $replacement_template, $text);
            }
        }
        return $text;
    }

    /**
     * スタイル済みSPANタグを組み立てる補助メソッド
     */
    private static function build_replacement_span($params, $style_map) {
        $replacement_text = $params[0];
        $style_keys = explode(',', $params[1]);
        $hsla    = $params[2] ?? [0, 100, 50, 1];
        $spacing = $params[3] ?? [0, 0, 0];
        $class   = $params[4] ?? '';

        $composed_style = '';
        foreach ($style_keys as $s_key) {
            if (isset($style_map[$s_key])) $composed_style .= $style_map[$s_key];
        }

        $vars = [
            'ヾ色相ヾ'   => $hsla[0] ?? 0,
            'ヾ彩度ヾ'   => $hsla[1] ?? 100,
            'ヾ明度ヾ'   => self::calculate_luminance($hsla[2] ?? 50),
            'ヾ透明度ヾ' => $hsla[3] ?? 1
        ];
        $composed_style = str_replace(array_keys($vars), array_values($vars), $composed_style);

        $m_right = (int)($spacing[0] ?? 0);
        $p_sides = (int)($spacing[1] ?? 0);
        $m_left  = (int)($spacing[2] ?? 0);
        $composed_style .= "margin-right:{$m_right}px; padding:0 {$p_sides}px; margin-left:{$m_left}px;";

        return "<span class=\"{$class}\" style=\"{$composed_style}\">{$replacement_text}</span>";
    }

    /**
     * 明度のアルファベット指定（a-e）を数値に変換する補助メソッド
     */
    private static function calculate_luminance($val) {
        if (is_numeric($val)) return $val;

        // ダークモード（d）基準の明度マップ
        $map = [
            'a' => 50, // 標準
            'b' => 75,
            'c' => 85,
            'd' => 90,
            'e' => 95,
        ];

        return $map[$val] ?? 50;
    }


    /**
     * 旧 kx_change_any_texts のロジックを統合
     */
    private static function apply_custom_replacements($text, $post_id) {
        // moreタグの置換
        $ad = '</p><table><tr><td><HR class="__hr_more"></td><td width="6em"><span class="__color_gray __xxsmall">　more　</span></td><td><HR class="__hr_more"></td></tr></table><p>';
        $text = preg_replace('/(<p>)?<span id="more-([0-9]+?)"><\/span>(.*?)(<\/p>)?/i', "$ad$0", $text);

        // 置換の読み込み
        $replace = [];

        $config = Su::get('ContentProcessor');
        $replace = $config['shorthand_expansions'] ?? [];

        // カラーマネージャー連携
        $colormgr = Dy::get_color_mgr($post_id);
        $vars = isset($colormgr['style_array']['vars_only']) ? $colormgr['style_array']['vars_only'] : '';
        $hue  = isset($colormgr['hue']) ? $colormgr['hue'] : '0';

        // 特殊プレースホルダーの注入
        $replace['∌']               = '';
        $replace['ヾ色置換ヾ']        = $vars;
        $replace['ヾ色置換・薄ヾ']    = $vars;
        $replace['ヾBASEヾ']          = $vars;
        $replace['ヾ色hsla普通ヾ']    = 'hsl(var(--kx-hue),var(--kx-sat),var(--kx-lum));';
        $replace['ヾ色hsla薄いヾ']    = 'hsla(var(--kx-hue),var(--kx-sat),var(--kx-lum),var(--kx-alp));';
        $replace['ヾ色相ヾ']          = $hue;

        $text = str_replace(array_keys($replace), $replace, $text);

        return $text;
    }


}