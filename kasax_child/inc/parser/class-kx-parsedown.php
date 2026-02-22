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
        $text = $this->customPreprocessing($text); // カスタム処理を追加
        return parent::text($text);
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


}
