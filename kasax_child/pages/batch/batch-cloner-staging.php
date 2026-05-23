<?php
/**
 * pages/batch/batch-cloner-staging.php
 * ツリー構成転写：実行処理
 */
require_once('../../../../../wp-load.php');
use Kx\Core\DynamicRegistry as Dy;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit('Direct access denied.');

$source_ids_raw = $_POST['source_ids'] ?? '';
$source_ids = array_filter(explode(',', $source_ids_raw));
$id_base = (int)($_POST['id_base'] ?? 0); // 前画面から受け取ったソースROOT ID
$target_root_id = (int)($_POST['target_root_id'] ?? 0);
$common_content = $_POST['common_content'] ?? '';

$replace_from = $_POST['replace_from'] ?? '';
$replace_to   = $_POST['replace_to'] ?? '';

$target_root_title = get_the_title($target_root_id);
if (!$target_root_title) {
    exit('Error: Invalid Target Root ID.');
}

$execute = isset($_POST['confirm_execute']);
$results = [];

/**
 * クローン後の新規記事タイトルを生成する。
 *
 * ソース記事から「≫」以降の末尾名称を抽出し、指定された置換ルールを適用した後、
 * 新しいRootタイトルと結合してフルタイトルを構成する。
 *
 * @param int|string $sid                 ソース対象のポストID。
 * @param string     $target_root_title   複製先Root記事のタイトル。
 * @param string     $from                タイトル末尾内の置換対象文字列（空文字可）。
 * @param string     $to                  タイトル末尾内の置換後文字列。
 * @return string                         生成された「新Root ≫ 置換済末尾」形式のタイトル。
 */
function generate_cloned_title($sid, $target_root_title, $from, $to) {
    $path_info = Dy::get_path_index($sid);
    $parts = $path_info['parts'] ?? [];
    $last_part = !empty($parts) ? end($parts) : get_the_title($sid);

    if ($from !== '') {
        $last_part = str_replace($from, $to, $last_part);
    }

    // 前後の空白をトリミングして「 ≫ 」で結合
    return trim($target_root_title) . '≫' . trim($last_part);
}

if ($execute) {
    set_time_limit(0);
    foreach ($source_ids as $sid) {
        // 【手法2】もしリストに残っていても、ソースROOT ID自体はスキップ
        if ((int)$sid === $id_base) continue;

        $new_title = generate_cloned_title($sid, $target_root_title, $replace_from, $replace_to);

        $new_post_id = wp_insert_post([
            'post_title'   => $new_title,
            'post_content' => $common_content,
            'post_status'  => 'publish',
            'post_type'    => 'post'
        ]);

        $results[] = [
            'status' => ($new_post_id && !is_wp_error($new_post_id)) ? 'SUCCESS' : 'FAILED',
            'title'  => $new_title,
            'id'     => (!is_wp_error($new_post_id)) ? $new_post_id : '-',
            'color'  => (!is_wp_error($new_post_id)) ? '#00ffcc' : '#ff3366'
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>Tree Cloner Staging | Kx System</title>
    <style>
        body { background: #1a1a1a; color: #ccc; font-family: monospace; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; border: 1px solid #444; padding: 20px; background: #222; }
        h1 { color: #00ffcc; font-size: 1.5rem; border-bottom: 1px solid #444; padding-bottom: 10px; }
        .staging-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; margin-top: 20px; }
        .staging-table th { background: #333; color: #ffcc00; text-align: left; padding: 10px; border: 1px solid #444; }
        .staging-table td { padding: 8px 10px; border: 1px solid #444; background: #111; }
        .btn-finalize {
            background: #442200; color: #ff9900; border: 1px solid #ff9900; padding: 15px;
            cursor: pointer; font-weight: bold; width: 100%; margin-top: 20px; font-size: 1.1rem;
        }
        .btn-finalize:hover { background: #663300; }
        .preview-box { background: #333; padding: 15px; border-left: 5px solid #ffcc00; margin: 10px 0; }
    </style>
</head>
<body>
<div class="container">
    <h1>CLONE EXECUTION STAGING</h1>

    <?php if ($execute): ?>
        <div style="color: #00ffcc; background: #003322; padding: 15px; border: 1px solid #00ffcc; margin-bottom: 20px; text-align: center;">
            <h2>CLONE COMPLETED</h2>
            <p><?= count($results) ?> 件の処理を完了しました。</p>
            <button onclick="window.close();" style="padding: 5px 15px; cursor:pointer;">閉じる</button>
        </div>
    <?php else: ?>
        <p>以下の構成で一括生成を実行します。</p>
        <div class="preview-box">
            TARGET ROOT: <strong><?= esc_html($target_root_title) ?></strong> (ID: <?= $target_root_id ?>)<br>
            <?php if ($replace_from !== ''): ?>
                TITLE REPLACE: <code><?= esc_html($replace_from) ?></code> → <code><?= esc_html($replace_to) ?></code>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <table class="staging-table">
        <thead>
            <tr>
                <th width="80">STATUS</th>
                <th>NEW POST TITLE</th>
                <th width="100">NEW ID</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$execute): ?>
                <?php foreach ($source_ids as $sid):
                    $preview_title = generate_cloned_title($sid, $target_root_title, $replace_from, $replace_to);
                ?>
                    <tr>
                        <td style="color: #888;">READY</td>
                        <td><?= esc_html($preview_title) ?></td>
                        <td style="color: #444;">-</td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <?php foreach ($results as $res): ?>
                    <tr>
                        <td style="color: <?= $res['color'] ?>;"><?= $res['status'] ?></td>
                        <td><?= esc_html($res['title']) ?></td>
                        <td><?= $res['id'] ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if (!$execute): ?>
        <form method="post">
            <input type="hidden" name="source_ids" value="<?= esc_attr($source_ids_raw) ?>">
            <input type="hidden" name="target_root_id" value="<?= $target_root_id ?>">
            <input type="hidden" name="common_content" value="<?= esc_attr($common_content) ?>">
            <input type="hidden" name="replace_from" value="<?= esc_attr($replace_from) ?>">
            <input type="hidden" name="replace_to" value="<?= esc_attr($replace_to) ?>">
            <input type="hidden" name="confirm_execute" value="1">
            <button type="submit" class="btn-finalize">この記事構成で一括生成を実行する ≫</button>
        </form>
    <?php endif; ?>

    <div style="margin-top: 20px; text-align: center;">
        <a href="javascript:history.back();" style="color: #888;">戻る</a>
    </div>
</div>
</body>
</html>