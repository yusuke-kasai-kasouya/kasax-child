<?php
/**
 * pages/re-regex/re-preview.php
 * 本文の正規表現マッチングによる最終フィルタリング
 */

require_once('../../../../../wp-load.php');

$title_base   = $_GET['title_base']   ?? '';
$content_from = $_GET['content_from'] ?? '';
$content_to   = $_GET['content_to']   ?? '';

if (empty($content_from) || $content_from === '//') {
    die('ERROR: 有効な正規表現(Pattern)を入力してください。');
}

global $wpdb;
$table_kx0 = $wpdb->prefix . "kx_0";

// 1. まずタイトルで広めに抽出
$sql = "SELECT id, title FROM {$table_kx0} WHERE title LIKE %s ORDER BY id ASC";
$initial_results = $wpdb->get_results($wpdb->prepare($sql, '%' . $title_base . '%'));

$matched_results = [];
$matched_ids = [];

// 2. 本文をチェックし、正規表現にマッチするものだけを「実行対象」として残す
foreach ($initial_results as $row) {
    $post = get_post($row->id);
    if (!$post) continue;

    $old_content = $post->post_content;

    // 正規表現マッチング
    // マッチするかどうかだけを確認し、マッチした場合のみリストに加える
    if (@preg_match($content_from, $old_content)) {
        $new_content = @preg_replace($content_from, $content_to, $old_content);

        $matched_results[] = [
            'id'    => $row->id,
            'title' => $row->title,
            'before'=> $old_content,
            'after' => $new_content
        ];
        $matched_ids[] = $row->id;
    }
}

$total_match = count($matched_results);
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>RE-REGEX PREVIEW | Kx System</title>
    <style>
        body { background: #121212; color: #ccc; font-family: 'Consolas', monospace; padding: 20px; }
        .container { max-width: 1100px; margin: 0 auto; }
        h1 { color: #00e5ff; font-size: 1.2rem; border-bottom: 1px solid #333; padding-bottom: 10px; }

        .info-bar { background: #1e1e1e; border: 1px solid #00e5ff; padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .stat-val { color: #00e5ff; font-weight: bold; font-size: 1.1rem; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 100px; background: #1a1a1a; }
        th { background: #252525; color: #888; text-align: left; padding: 10px; font-size: 0.8rem; border-bottom: 2px solid #333; }
        td { padding: 12px; border-bottom: 1px solid #222; vertical-align: top; }

        .col-id { width: 60px; color: #666; font-size: 0.8rem; }
        .col-title { width: 250px; font-size: 0.85rem; color: #aaa; }

        .diff-container { display: flex; gap: 10px; }
        .diff-box { flex: 1; padding: 10px; font-size: 0.8rem; border-radius: 3px; white-space: pre-wrap; word-break: break-all; max-height: 250px; overflow-y: auto; border: 1px solid #333; }
        .before { background: #2d1a1a; color: #ff9999; }
        .after { background: #1a2d1a; color: #99ff99; border-color: #00ff41; }

        .highlight-match { color: #ffcc00; background: rgba(255, 204, 0, 0.2); font-weight: bold; }

        .action-area { position: sticky; bottom: 0; left: 0; right: 0; background: rgba(20,20,20,0.95); padding: 25px; border-top: 2px solid #00e5ff; text-align: center; backdrop-filter: blur(5px); }
        .btn-execute { background: #00e5ff; color: #000; border: none; padding: 18px 60px; font-weight: bold; cursor: pointer; font-size: 1.2rem; border-radius: 4px; box-shadow: 0 4px 15px rgba(0,229,255,0.3); }
        .btn-execute:hover { background: #fff; transform: translateY(-2px); }
    </style>
</head>
<body>

<div class="container">
    <h1>RE-REGEX PREVIEW : 置換対象の最終確認</h1>

    <div class="info-bar">
        <span>Title Filter: <span class="stat-val"><?= esc_html($title_base ?: '全件') ?></span></span>
        <span style="margin-left:20px;">Matched Posts: <span class="stat-val"><?= $total_match ?></span> / <?= count($initial_results) ?> 件</span>
        <span style="margin-left:20px;">Pattern: <code style="color:#ffcc00;"><?= esc_html($content_from) ?></code></span>
    </div>

    <?php if ($total_match > 0): ?>
        <table>
            <thead>
                <tr>
                    <th class="col-id">ID</th>
                    <th class="col-title">TITLE</th>
                    <th>CONTENT DIFF (Matched Only)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($matched_results as $res): ?>
                <tr>
                    <td class="col-id"><?= $res['id'] ?></td>
                    <td class="col-title"><?= esc_html($res['title']) ?></td>
                    <td>
                        <div class="diff-container">
                            <div class="diff-box before"><?= esc_html(mb_strimwidth($res['before'], 0, 500, "...")) ?></div>
                            <div class="diff-box after"><?= esc_html(mb_strimwidth($res['after'], 0, 500, "...")) ?></div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="action-area">
            <a href="javascript:history.back();" style="color:#888; text-decoration:none; margin-right:30px;">≪ 戻って修正</a>
            <form method="post" action="re-execute.php" style="display:inline;">
                <input type="hidden" name="ids" value="<?= implode(',', $matched_ids) ?>">
                <input type="hidden" name="content_from" value="<?= esc_attr($content_from) ?>">
                <input type="hidden" name="content_to" value="<?= esc_attr($content_to) ?>">
                <button type="submit" class="btn-execute">
                    EXECUTE UPDATE FOR <?= $total_match ?> POSTS ≫
                </button>
            </form>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 100px; background: #1a1a1a; border: 1px dashed #444;">
            <p style="font-size: 1.2rem; color: #ff6666;">正規表現にマッチする本文は見つかりませんでした。</p>
            <a href="javascript:history.back();" style="color: #00e5ff;">入力画面に戻る</a>
        </div>
    <?php endif; ?>
</div>

</body>
</html>