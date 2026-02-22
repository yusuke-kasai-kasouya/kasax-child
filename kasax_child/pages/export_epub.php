<?php
// WordPress環境ロード（パスは運用環境に応じて調整）
//* [Path]: inc\core\export_epub.php
require_once('../../../../wp-load.php');

// 投稿IDの受け取り
if (empty($_POST['id'])) {
    echo 'ERROR - 投稿IDが指定されていません。';
    exit;
}

$id = intval($_POST['id']);
$post = get_post($id);

if (!$post) {
    echo 'ERROR - 投稿が存在しません。';
    exit;
}

// タイトルと本文
$title = $post->post_title;
$delimiter = str_contains($title, '＠') ? '＠' : '≫';
$parts = explode($delimiter, $title);
$title = array_pop($parts); // end() の代わり。最後を取り出す

$content = apply_filters('the_content', $post->post_content);

// HTML構築
$html = <<<HTML
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>{$title}</title>
</head>
<body>
  <h1>{$title}</h1>
  <div>{$content}</div>
</body>
</html>
HTML;

// 一時HTMLファイル出力
//$tmp_html = tempnam(sys_get_temp_dir(), 'epub_input_') . '.html';
//$tmp_html = tempnam(sys_get_temp_dir(), 'epu');
$tmp_html = tempnam('D:/00_WP/Export/', 'epu');
rename($tmp_html, $tmp_html . '.html');
$tmp_html .= '.html';

// <span style="color:red">テキスト</span> のような指定を削除
$html = preg_replace('/style\s*=\s*"[^"]*color\s*:[^";]+;?[^"]*"/i', '', $html);


file_put_contents($tmp_html, $html);

// EPUB出力ファイルパス設定
$datetime = date('Ymd_His');
$save_dir = 'D:/00_WP/Export/';
if (!file_exists($save_dir)) {
    mkdir($save_dir, 0755, true);
}
$epub_file = $save_dir . "{$title}_ID{$id}_{$datetime}.epub";

// Pandocのコマンド（パスは環境に応じて変更）
//$pandoc = '"C:\\Program Files\\Pandoc\\pandoc.exe"';
$pandoc = '"C:\Users\kasai\AppData\Local\Pandoc\\pandoc.exe"';
$cmd = "{$pandoc} \"{$tmp_html}\" -o \"{$epub_file}\"";
exec($cmd, $out, $status);

// HTML出力ファイル名（タイトルベース）
$html_file = "{$save_dir}{$title}_ID{$id}_{$datetime}.html";
file_put_contents($html_file, $html);

echo "<pre>{$cmd}</pre>";

// 実行結果
if ($status === 0) {
    echo "✅ EPUB生成成功<br>";
    echo "保存場所: {$epub_file}";
} else {
    echo "❌ EPUB変換に失敗しました。";
}
?>