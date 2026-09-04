<?php
/**
 * inc\utils\class-kx-parsedown.php
 */
require_once __DIR__ . '/vendor/Parsedown.php';
require_once __DIR__ . '/vendor/ParsedownExtra.php';

/**
 * KxParsedownクラス
 * ParsedownExtraを拡張し、カスタムMarkdown処理を追加する。
 *
 * 主な機能:
 * - 太字テキストのカラー設定
 * - 見出しのスタイル変更
 *
 * @extends ParsedownExtra
 */
class KxParsedown extends ParsedownExtra {

    /**
     * Markdownテキストを処理し、カスタム前処理を適用した後に
     * ParsedownExtraのtextメソッドを呼び出す
     *
     * @param string $text 処理対象のMarkdownテキスト
     * @return string 処理後のHTMLテキスト
     */
    public function text($text) {
        $text = $this->customPreprocessing($text); // カスタム前処理（既存）
        $html = parent::text($text);               // ParsedownExtraの通常パースを実行
        return $this->restoreCodeEntities($html);  // 新設：コード内のマルチバイト文字を安全に復元
    }

    /**
     * テキストのカスタム前処理を行う
     * - 太字（**text**）のスタイルを変更
     * - 見出し（# で始まる行）のスタイルを変更
     * - Markdownの表には適用しないよう考慮
     *
     * @param string $text 処理対象のMarkdownテキスト
     * @return string 処理後のテキスト
     */
    private function customPreprocessing($text) {
        // すべての行を処理し、Markdownの表には適用しない

        $text = preg_replace('/\*\*(.*?)\*\*/', '<span style="color: hsl(ヾ色相ヾ, 50%, 70%);font-weight:bold;">$1</span>', $text);

			$lines = explode("\n", $text);
    		foreach ($lines as &$line) {
                // Markdownの見出しを判別（# で始まる行を対象）
                if (preg_match('/^#([^#].*)/', $line, $matches)) {
                $line = '<h1 style="color: hsl(ヾ色相ヾ, 50%, 70%); font-weight: bold;border: 1px solid hsla(ヾ色相ヾ,100%,80%,.5);">' . $matches[1] . '</h1>';
                }
    		}

        return implode("\n", $lines);//$text;
    }

    /**
     * Parsedownの変換後に呼び出し、<code> タグ（ブロック用・インライン用双方）の内部にある、
     * ParsedownExtraによって実体参照化・ダブルエスケープされたマルチバイト文字（◀や日本語など）を安全にUTF-8に復元する。
     *
     * @param string $html 処理対象のHTML
     * @return string 復元調整後のHTML
     */
    private function restoreCodeEntities($html) {
        // <code>タグの開始から終了までの範囲を最短一致でマッチ（大文字小文字無視、複数行対応）
        $pattern = '#(<code\b[^>]*>)(.*?)(</code>)#is';

        return preg_replace_callback($pattern, function ($matches) {
            $openTag  = $matches[1]; // <code class="...">
            $content  = $matches[2]; // タグの中身（コード本体）
            $closeTag = $matches[3]; // </code>

            // 1. Double escapeの復元:
            // 「&amp;#x...;」や「&amp;#...;」のようにダブルエスケープされた数値実体参照を「&#x...;」に復元
            $content = preg_replace('/&amp;(#x?[0-9a-fA-F]+;)/', '&$1', $content);

            // 2. 数値実体参照のみのピンポイントデコード:
            // 「&#x25c0;」や「&#3042;（あ）」などの数値実体参照のみを対象にデコードし、UTF-8の生文字（◀や日本語）に復元
            // ※「&lt;」「&gt;」「&amp;」などの名前付き実体参照はマッチしないため、HTMLとしての安全性やレイアウトはそのまま維持されます。
            $content = preg_replace_callback('/&#(x?[0-9a-fA-F]+);/', function ($entityMatches) {
                return html_entity_decode($entityMatches[0], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }, $content);

            return $openTag . $content . $closeTag;
        }, $html);
    }

}
