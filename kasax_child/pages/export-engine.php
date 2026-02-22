<?php
/**
 * [Path]: pages/export-engine.php
 */

require_once( dirname(__DIR__) . '/../../../wp-load.php' );

if ( !current_user_can('edit_posts') ) {
    wp_die('権限がありません。');
}

$source_id   = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
$post_id     = $source_id;
$template_id = $_POST['template_id'] ?? 'simple';
$ai_select   = $_POST['ai_select'] ?? 'gemini';

if (!$source_id) {
    wp_die('IDが指定されていません。');
}

$combined_text = '';
$save_status   = 'Processing...';

try {
    $args = [
        'type'      => $template_id,
        'dest'      => 'file',
        'single_export' => $_POST['single_export'] ?? false,
        'ext'       => $_POST['export_format'] ?? 'txt', // UIからの選択値（txt/md/epub）を受け取る
        'sub_dir'   => '', // 必要に応じてフォルダ名を指定可能
        'ai_select' => $ai_select,
        'sanitize_level' => (int)($_POST['sanitize_level'] ?? 0), // 新設
        'text_change' => isset($_POST['checkbox_text_change']),
    ];

    // run() は成功時に生成されたテキスト(string)を返し、致命的失敗時に false を返す
    $result = \Kx\Core\Kx_Consolidator::run($source_id, $post_id, $args);

    if ($result === false) {
        $combined_text = "エラー：統合処理またはファイル保存に失敗しました。ログを確認してください。";
        $save_status   = "FAILED";
    } else {
        $combined_text = $result;
        $save_status   = "SUCCESS (Local File Saved)";
    }

} catch (Exception $e) {
    $combined_text = "Error: " . $e->getMessage();
    $save_status   = "Exception Occurred.";
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>Export Preview</title>
    <style>
        body { background: #1a1a1a; color: #ccc; font-family: 'Cascadia Code', Menlo, monospace; padding: 20px; line-height: 1.6; }
        .meta { border-bottom: 1px solid #444; margin-bottom: 20px; padding-bottom: 10px; font-size: 12px; color: #888; }
        .status { color: #00ff7f; font-weight: bold; }
        .error { color: #ff5555; }
        textarea { width: 100%; height: 80vh; background: #222; color: #00ff7f; border: 1px solid #444; padding: 15px; font-size: 14px; outline: none; resize: none; }
    </style>
</head>
<body>
    <div class="meta">
        ID: <?php echo $source_id; ?> |
        Mode: <span><?php echo esc_html($template_id); ?></span> |
        Status: <span class="<?php echo ($result === false) ? 'error' : 'status'; ?>"><?php echo $save_status; ?></span>
    </div>

    <textarea readonly spellcheck="false"><?php echo esc_textarea($combined_text); ?></textarea>
</body>
</html>