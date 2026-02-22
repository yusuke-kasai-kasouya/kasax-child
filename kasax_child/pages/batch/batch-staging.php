<?php
/**
 * pages/batch/batch-staging.php
 * バッチ処理：実行準備（Staging）および初期化
 */

require_once('../../../../../wp-load.php');

use Kx\Batch\AdvancedProcessor;

// 1. セキュリティチェック（空送信防止）
if (empty($_POST['ids'])) {
    die('ERROR: 対象IDが見つかりません。');
}

// 2. インスタンス生成
$processor = new AdvancedProcessor();

// 3. POSTデータの整形
$title_from = $_POST['title_from'] ?? '';
$title_to   = $_POST['title_to']   ?? '';
$replace_mode = $_POST['replace_mode'] ?? 'first';

if (isset($_POST['checkbox']) && $_POST['checkbox'] == '1') {
    $title_from = $_POST['Title_ALL1'];
    $title_to   = $_POST['Title_ALL2'];
}

$ids_array = explode(',', $_POST['ids']);

// 4. パラメータの集約
$params = [
    'title_from'   => $title_from,
    'title_to'     => $title_to,
    'ids_array'    => $ids_array,
    'replace_mode' => $replace_mode,
    'content_from' => $_POST['content_from'] ?? '',
    'content_to'   => $_POST['content_to']   ?? '',
];

// 5. ステートの初期化とDB保存
try {
    $processor->init_state($params);
    $success = true;
} catch (\Exception $e) {
    $success = false;
}

if (!$success) {
    die('ERROR: DBへの実行準備の書き込みに失敗しました。');
}

// 6. 置換シミュレーション関数の定義
$simulate_replace = function($current_title) use ($title_from, $title_to, $replace_mode) {
    if (empty($title_from)) return $current_title;

    // スラッシュで囲まれている場合は正規表現として扱う
    if (preg_match('/^\/.*\/$/', $title_from)) {
        $pattern = $title_from;
        $limit = ($replace_mode === 'first') ? 1 : -1;
        return preg_replace($pattern, $title_to, $current_title, $limit);
    } else {
        // 通常の文字列置換
        if ($replace_mode === 'first') {
            $pos = strpos($current_title, $title_from);
            return ($pos !== false) ? substr_replace($current_title, $title_to, $pos, strlen($title_from)) : $current_title;
        } else {
            return str_replace($title_from, $title_to, $current_title);
        }
    }
};

$execute_url = 'batch-execute.php';
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>Staging Complete | Kx Batch</title>
    <style>
        body { background: #1a1a1a; color: #00ffcc; font-family: monospace; padding: 40px 20px; margin: 0; }
        .msg-box { border: 1px solid #00ffcc; padding: 30px; background: #222; text-align: center; box-shadow: 0 0 20px rgba(0,255,204,0.2); max-width: 600px; margin: 0 auto 40px; }
        .loader { margin: 15px 0; color: #888; }
        .btn-start { display: inline-block; margin-top: 20px; padding: 12px 30px; border: 1px solid #00ffcc; color: #00ffcc; text-decoration: none; font-weight: bold; font-size: 1.1rem; }
        .btn-start:hover { background: #00ffcc; color: #000; }

        /* 比較テーブルのスタイル */
        .preview-area { max-width: 1200px; margin: 0 auto; background: #111; border: 1px solid #333; padding: 20px; }
        .preview-title { color: #ffcc00; border-bottom: 1px solid #444; padding-bottom: 10px; margin-bottom: 15px; font-size: 1rem; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; color: #ccc; }
        th, td { border: 1px solid #333; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #222; color: #888; width: 45%; }
        .id-col { width: 80px; text-align: center; color: #666; }
        .after-title { color: #fff; }
        .diff-mark { color: #ffcc00; margin: 0 5px; }
        tr:hover { background: #1a1a1a; }
        .no-change { color: #555; }
    </style>
</head>
<body>

<div class="msg-box">
    <div style="font-size: 1.2rem; font-weight: bold;">[ READY ]</div>
    <div class="loader">DB Staging Successful.</div>
    <div style="color: #ccc; font-size: 0.8rem; margin-bottom: 10px;">
        対象件数: <?= count($ids_array) ?> 件<br>
        置換条件: <span style="color:#ffcc00;"><?= esc_html($title_from) ?></span> → <span style="color:#ffcc00;"><?= esc_html($title_to) ?></span>
    </div>

    <a href="<?= $execute_url ?>" class="btn-start">EXECUTE BATCH PROCESS NOW ≫</a>
</div>

<div class="preview-area">
    <div class="preview-title">FINAL PREVIEW: TITLE CHANGE LIST</div>
    <table>
        <thead>
            <tr>
                <th class="id-col">ID</th>
                <th>BEFORE (Current)</th>
                <th>AFTER (Simulated)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ids_array as $id):
                $old_title = get_the_title($id);
                $new_title = $simulate_replace($old_title);
                $has_change = ($old_title !== $new_title);
            ?>
            <tr class="<?= $has_change ? '' : 'no-change' ?>">
                <td class="id-col"><?= $id ?></td>
                <td><?= esc_html($old_title) ?></td>
                <td class="after-title">
                    <?php if ($has_change): ?>
                        <?= esc_html($new_title) ?>
                    <?php else: ?>
                        <span style="color:#444;">(No Change)</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>