<?php
/**
 * inc\utils\class-kx-template.php
 */

namespace Kx\Utils;

/**
 * KxTemplate
 * * システム全体の表示層（View）を統括するテンプレートエンジン・ユーティリティ。
 * WordPress標準の get_template_part では困難な「自由な変数受け渡し」と
 * 「出力バッファリング制御」をカプセル化し、コンポーネントの再利用性を最大化する。
 * * @package Kx\Utils
 * @since 1.0.0
 */
class KxTemplate {

    /**
     * テンプレートファイルを読み込み、処理を実行またはHTML文字列を返却する。
     *
     * 【$args の役割と仕組み】
     * 渡された連想配列を内部で extract() 処理し、キー名を変数名として展開する。
     * これにより、テンプレートファイル内では $args['data'] ではなく $data として
     * 直接データを参照でき、ロジックと表示（View）の完全な分離を可能にする。
     *
     * * 使い方：KxTemplate::get('components/raretu/bar', $args);
     *
     * @param string $template_name 'templates/' フォルダを起点とした相対パス（拡張子なし）。
     * @param array  $args          テンプレートに渡す変数の連想配列。例: ['id' => 123] と渡すと
     * テンプレート内では変数 $id (値: 123) が使用可能になる。
     * @param bool   $echo          true（デフォルト）: 即座に出力する。
     * false: バッファリングを行い、生成されたHTMLを文字列として返す。
     *
     * @return string|void $echoがfalseの場合はHTML文字列。trueの場合は標準出力に出力。
     */
    public static function get($template_name, $args = [], $echo = true) {
        $path = get_stylesheet_directory() . '/templates/' . $template_name . '.php';

        if (!file_exists($path)) {
            return "Template not found: {$template_name}";
        }

        // 変数展開（$args['title'] が テンプレート内で $title として使えるようにする）
        if (!empty($args)) {
            extract($args);
        }

        // 出力制御
        if (!$echo) {
            ob_start();
        }

        include $path; // get_template_part ではなく include を使うことで変数のスコープを制御しやすくする

        if (!$echo) {
            return ob_get_clean();
        }
    }
}