<?php
/**
 * pages/re-regex/re-execute.php
 * 正規表現コンテンツ・プロセッサー：実行エンジン
 */

require_once('../../../../../wp-load.php');

// 1. パラメータ取得（POSTまたはGET）
$ids_str      = $_REQUEST['ids'] ?? '';
$content_from = $_REQUEST['content_from'] ?? '';
$content_to   = $_REQUEST['content_to']   ?? '';
$offset       = isset($_REQUEST['offset']) ? (int)$_REQUEST['offset'] : 0;
$success_count = isset($_REQUEST['success']) ? (int)$_REQUEST['success'] : 0;

if (empty($ids_str) || empty($content_from)) {
    die('ERROR: 実行パラメータが不足しています。');
}

$ids_array = explode(',', $ids_str);
$total_ids = count($ids_array);

// 2. 実行設定：1ステップあたりの処理件数
$step_limit = 50;
$current_batch = array_slice($ids_array, $offset, $step_limit);

// 3. 処理開始
foreach ($current_batch as $post_id) {
    $post = get_post($post_id);
    if (!$post) continue;

    $old_content = $post->post_content;

    // 正規表現置換
    $new_content = @preg_replace($content_from, $content_to, $old_content);

    if ($new_content !== null && $new_content !== $old_content) {
        // WordPressのDB更新
        $wpdb->update(
            $wpdb->posts,
            ['post_content' => $new_content],
            ['ID' => $post_id]
        );
        $success_count++;

        // キャッシュクリア（念のため）
        clean_post_cache($post_id);
    }
}

// 4. 進捗管理とリダイレクト
$next_offset = $offset + $step_limit;
$is_finished = ($next_offset >= $total_ids);

?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>RE-REGEX EXECUTING... | Kx System</title>
    <style>
        body { background: #000; color: #00ff41; font-family: 'Consolas', monospace; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .console-box { border: 1px solid #00ff41; padding: 40px; background: #050505; width: 600px; box-shadow: 0 0 20px rgba(0, 255, 65, 0.2); }
        h2 { font-size: 1.1rem; margin-top: 0; color: #fff; }
        .progress-bar { height: 10px; background: #111; border: 1px solid #333; margin: 20px 0; position: relative; }
        .progress-fill { height: 100%; background: #00ff41; transition: width 0.3s; }
        .status-text { font-size: 0.9rem; margin-bottom: 5px; }
        .finish-msg { color: #00e5ff; font-weight: bold; }
    </style>
    <?php if (!$is_finished): ?>
    <script>
        // 次のバッチへ自動リダイレクト
        setTimeout(function() {
            window.location.href = "re-execute.php?ids=<?= $ids_str ?>&content_from=<?= urlencode($content_from) ?>&content_to=<?= urlencode($content_to) ?>&offset=<?= $next_offset ?>&success=<?= $success_count ?>";
        }, 500);
    </script>
    <?php endif; ?>
</head>
<body>

<div class="console-box">
    <?php if (!$is_finished): ?>
        <h2>SYSTEM: EXECUTING CONTENT REPLACEMENT</h2>
        <div class="status-text">
            Processing: <?= min($next_offset, $total_ids) ?> / <?= $total_ids ?> items...<br>
            Current Success: <?= $success_count ?>
        </div>
        <div class="progress-bar">
            <div class="progress-fill" style="width: <?= ($next_offset / $total_ids) * 100 ?>%;"></div>
        </div>
        <div style="font-size: 0.8rem; color: #666;">※ブラウザを閉じずにそのままお待ちください。</div>
    <?php else: ?>
        <h2 class="finish-msg">PROCESS COMPLETED.</h2>
        <div class="status-text" style="color: #fff;">
            対象総数: <?= $total_ids ?> 件<br>
            更新成功: <?= $success_count ?> 件<br><br>
            全ての置換処理が完了しました。
        </div>
        <div style="margin-top: 20px;">
            <a href="re-content-form.php" style="color: #00e5ff; text-decoration: none;">[ 新しい処理を開始する ]</a>
        </div>
    <?php endif; ?>
</div>

</body>
</html>