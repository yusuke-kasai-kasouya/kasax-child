<?php
/**
 * pages/batch/batch-shortcode-action.php
 * 本文内の tougou= を off_merge= に置換実行するスクリプト
 */

require_once('../../../../../wp-load.php');

$target_ids_raw = $_POST['target_ids'] ?? '';

if (empty($target_ids_raw)) {
    wp_die('対象IDが指定されていません。');
}

$target_ids = explode(',', $target_ids_raw);
$updated_count = 0;
$results = [];

$pattern     = '/\btougou=/';
$replacement = 'off_merge=';

foreach ($target_ids as $pid) {
    $pid = (int)$pid;
    $post = get_post($pid);
    if (!$post) continue;

    if (preg_match($pattern, $post->post_content)) {
        $new_content = preg_replace($pattern, $replacement, $post->post_content);

        // データベースを更新
        $updated_post = [
            'ID'           => $pid,
            'post_content' => $new_content,
        ];

        $res = wp_update_post($updated_post);

        if ($res && !is_wp_error($res)) {
            $updated_count++;
            $results[] = [
                'id'     => $pid,
                'status' => 'SUCCESS',
                'title'  => $post->post_title
            ];
        } else {
            $results[] = [
                'id'     => $pid,
                'status' => 'FAILED',
                'title'  => $post->post_title
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>Batch Execution Result | Kx System</title>
    <style>
        body { background: #1a1a1a; color: #ccc; font-family: monospace; line-height: 1.4; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; border: 1px solid #444; padding: 20px; background: #222; }
        h1 { font-size: 1.2rem; color: #00ffcc; border-bottom: 1px solid #00ffcc; padding-bottom: 5px; }
        .result-panel { background: #000; padding: 15px; border-left: 4px solid #00ffcc; margin-bottom: 20px; }
        .stat-val { color: #00ffcc; font-weight: bold; font-size: 1.2rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 0.85rem; }
        th, td { border: 1px solid #333; padding: 8px; text-align: left; }
        th { background: #2a2a2a; color: #00ffcc; }
        .status-success { color: #00ffcc; font-weight: bold; }
        .status-failed { color: #ff3333; font-weight: bold; }
    </style>
</head>
<body>

<div class="container">
    <h1>EXECUTION COMPLETED</h1>

    <div class="result-panel">
        UPDATED POSTS: <span class="stat-val"><?= $updated_count ?></span> / <?= count($target_ids) ?>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($results as $row): ?>
            <tr>
                <td><?= $row['id'] ?></td>
                <td><?= esc_html($row['title']) ?></td>
                <td class="status-<?= strtolower($row['status']) ?>"><?= $row['status'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>