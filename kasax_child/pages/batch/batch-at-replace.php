<?php
/**
 * pages/batch/batch-at-replace.php
 * 「＠」以降の一括置換専用入口
 */
require_once('../../../../../wp-load.php');

$ids_raw = $_GET['ids'] ?? '';
$ids_array = array_filter(explode(',', $ids_raw));
$ids_count = count($ids_array);

// 置換対象：全角の「＠」から末尾まで
$default_from = '/＠.*$/';
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>Quick ＠ Replace | Kx System</title>
    <style>
        body { background: #1a1a1a; color: #ccc; font-family: monospace; padding: 20px; }
        .container { max-width: 700px; margin: 0 auto; border: 1px solid #444; padding: 20px; background: #222; }
        .stat-val { color: #00ffcc; font-weight: bold; font-size: 1.2rem; }
        .input-group { margin-top: 20px; background: #2a2a2a; padding: 15px; border: 1px solid #444; }
        input[type="text"] { background: #111; border: 1px solid #555; color: #fff; padding: 10px; width: 100%; box-sizing: border-box; }
        .btn-execute { background: #004433; color: #00ffcc; border: 1px solid #00ffcc; padding: 15px; cursor: pointer; font-weight: bold; width: 100%; margin-top: 20px; }
        .prefix-display { font-size: 1.5rem; margin-right: 10px; color: #ffcc00; }
    </style>
</head>
<body>
<div class="container">
    <h1>＠ 一括置換モード</h1>
    <p>対象件数: <span class="stat-val"><?= $ids_count ?></span> 件</p>

    <form id="at-replace-form" method="post" action="batch-staging.php">
        <input type="hidden" name="ids" value="<?= esc_attr($ids_raw) ?>">
        <input type="hidden" name="replace_mode" value="all">

        <div class="input-group">
            <label style="color: #ffcc00;">1. 置換対象（正規表現）</label><br>
            <input type="text" name="title_from" value="<?= esc_attr($default_from) ?>" readonly style="color: #666; cursor: not-allowed;">
            <p style="font-size: 0.8rem;">※タイトル末尾の全角「＠」以降をすべて検出します。</p>
        </div>

        <div class="input-group">
            <label style="color: #ffcc00;">2. 新しい接尾辞（自動で＠がつきます）</label><br>
            <div style="display: flex; align-items: center;">
                <span class="prefix-display">＠</span>
                <input type="text" id="display_to" value="" placeholder="例: 回想" required autofocus>
            </div>
            <input type="hidden" name="title_to" id="actual_title_to" value="">
        </div>

        <input type="submit" class="btn-execute" value="実行準備画面へ進む ≫">
    </form>

    <div style="margin-top: 20px; text-align: center;">
        <a href="javascript:history.back();" style="color: #888;">キャンセル</a>
    </div>
</div>

<script>
document.getElementById('at-replace-form').addEventListener('submit', function(e) {
    const inputVal = document.getElementById('display_to').value;
    const actualInput = document.getElementById('actual_title_to');

    // 先頭が既に全角「＠」または半角「@」で始まっているかチェック
    if (inputVal.startsWith('＠') || inputVal.startsWith('@')) {
        // 既に記号がある場合は、全角に統一してそのままセット
        actualInput.value = '＠' + inputVal.substring(1);
    } else {
        // 記号がない場合は全角「＠」を頭に付ける
        actualInput.value = '＠' + inputVal;
    }
});
</script>

</body>
</html>