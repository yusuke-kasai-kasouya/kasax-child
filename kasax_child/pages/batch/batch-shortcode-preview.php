<?php
/**
 * pages/batch/batch-shortcode-preview.php
 * [raretu] ショートコードの tougou= 属性を一括停止(off_merge=へ置換)するためのプレビュー画面
 */

require_once('../../../../../wp-load.php');

use Kx\Core\DynamicRegistry as Dy;

// 1. パラメータの取得
$id_base       = $_GET['id_base'] ?? '';
$replace_range = $_GET['replace_range'] ?? 'all';
$ids_direct    = $_GET['ids_direct'] ?? '';

$ids = [];

// 2. 対象IDの算出
if ($replace_range === 'direct') {
    if (!empty($ids_direct)) {
        $ids = explode(',', $ids_direct);
    }
} elseif ($replace_range === 'all') {
    if (!empty($id_base)) {
        $descendants_all = Dy::get_descendants_all($id_base) ?: [];
        $ids = $descendants_all;
        // ROOT自身を除外し、下位フォルダのみにする場合は array_unshift を行わない
        // ※ルートも含める場合は array_unshift($ids, $id_base); を追加してください
    }
}

// 3. 該当投稿の検索とマッチングチェック
$matched_posts = [];
$pattern = '/\btougou=/';

if (!empty($ids)) {
    foreach ($ids as $pid) {
        $post = get_post($pid);
        if (!$post) continue;

        if (preg_match($pattern, $post->post_content)) {
            // マッチした箇所をプレビュー用に抽出
            preg_match_all('/\[raretu[^\]]*\btougou=[^\]]*\]/', $post->post_content, $matches);
            $matched_posts[] = [
                'id'      => $pid,
                'title'   => $post->post_title,
                'matches' => $matches[0] ?? []
            ];
        }
    }
}

$target_ids_string = implode(',', array_column($matched_posts, 'id'));
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>Shortcode Batch Preview | Kx System</title>
    <style>
        body { background: #1a1a1a; color: #ccc; font-family: monospace; line-height: 1.4; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; border: 1px solid #444; padding: 20px; background: #222; }
        h1 { font-size: 1.2rem; color: #ff9900; border-bottom: 1px solid #ff9900; padding-bottom: 5px; }
        .info-panel { background: #000; padding: 15px; border-left: 4px solid #ff9900; margin-bottom: 20px; }
        .stat-val { color: #ff9900; font-weight: bold; font-size: 1.2rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 0.85rem; }
        th, td { border: 1px solid #333; padding: 8px; text-align: left; }
        th { background: #2a2a2a; color: #ff9900; }
        .code-block { background: #111; border: 1px solid #333; padding: 4px; color: #00ffcc; font-size: 0.8rem; margin: 2px 0; }
        .btn-execute { background: #442200; color: #ff9900; border: 1px solid #ff9900; padding: 10px 40px; cursor: pointer; font-weight: bold; }
        .btn-execute:hover { background: #ff9900; color: #000; }
    </style>
</head>
<body>

<div class="container">
    <h1>Kx_Shortcode_Batch_Processor : PREVIEW</h1>

    <div class="info-panel">
        SEARCH SCOPE: <span class="stat-val"><?= count($ids) ?></span> items scanned<br>
        MATCHED TARGETS: <span class="stat-val"><?= count($matched_posts) ?></span> items found with 'tougou='<br>
        REPLACE RULE: <span style="color: #fff; font-weight: bold;">tougou= ➔ off_merge=</span>
    </div>

    <h3>MATCHED POSTS (<?= count($matched_posts) ?>)</h3>
    <?php if (!empty($matched_posts)): ?>
        <table>
            <thead>
                <tr>
                    <th style="width: 80px;">ID</th>
                    <th style="width: 250px;">Title</th>
                    <th>Detected Shortcodes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($matched_posts as $item): ?>
                <tr>
                    <td><?= $item['id'] ?></td>
                    <td><?= esc_html($item['title']) ?></td>
                    <td>
                        <?php foreach ($item['matches'] as $sc): ?>
                            <div class="code-block"><?= esc_html($sc) ?></div>
                        <?php endforeach; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <form method="post" action="batch-shortcode-action.php" style="margin-top: 30px; text-align: right;">
            <input type="hidden" name="target_ids" value="<?= esc_attr($target_ids_string) ?>">
            <input type="submit" class="btn-execute" value="EXECUTE DISABLE (tougou= -> off_merge=) ≫" onclick="return confirm('対象の投稿の tougou= 記述を off_merge= に変更します。よろしいですか？');">
        </form>
    <?php else: ?>
        <p style="padding: 20px; text-align: center; color: #888;">該当する [raretu ... tougou=...] 記述は見つかりませんでした。</p>
    <?php endif; ?>
</div>

</body>
</html>