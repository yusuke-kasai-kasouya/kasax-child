<?php
/**
 * pages/batch/batch-time-replace.php
 */
require_once('../../../../../wp-load.php');

global $wpdb;

// 1. 環境に合わせたテーブル名を取得
$table_name = $wpdb->prefix . "kx_0";

// 2. パラメータ取得
$series = $_GET['series'] ?? "";
$hero   = $_GET['hero']   ?? "";
$time   = $_GET['time']   ?? "";

$search_raw = trim("{$series} {$hero} ≫{$time}＠");

// 3. AND検索（スペースを % に置換）
$words = preg_split('/[\s　]+/u', $search_raw, -1, PREG_SPLIT_NO_EMPTY);
$like_val = '%' . implode('%', $words) . '%';

// 4. 直接SQL実行
$safe_like_val = addslashes($like_val);
$sql = "SELECT id FROM {$table_name} WHERE title LIKE '{$safe_like_val}'";
$ids = $wpdb->get_col($sql);
$ids_count = count($ids ?: []);
$ids_raw = implode(',', $ids ?: []);
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>Time Batch Setup</title>
    <style>
        body { background: #1a1a1a; color: #ccc; font-family: monospace; padding: 20px; }
        .container { max-width: 700px; margin: 0 auto; border: 1px solid #444; padding: 20px; background: #222; }
        .info-box { background: #000; padding: 15px; border-left: 4px solid #ffcc00; margin-bottom: 20px; }
        .stat-val { color: #00ffcc; font-weight: bold; font-size: 1.2rem; }
        .btn-execute { background: #443300; color: #ffcc00; border: 1px solid #ffcc00; padding: 15px; cursor: pointer; font-weight: bold; width: 100%; margin-top: 20px; }
        input[type="text"] { background: #111; border: 1px solid #555; color: #fff; padding: 10px; width: 100%; box-sizing: border-box; }
        .readonly-input { background: #1a1a1a !important; color: #666 !important; cursor: not-allowed; }
    </style>
</head>
<body>
<div class="container">
    <h1>時間一斉変換：準備</h1>

    <div class="info-box">
        <span style="color: #ffcc00;">検索ワード:</span> <?= esc_html($search_raw) ?><br>
        <span style="color: #ffcc00;">該当件数:</span> <span class="stat-val"><?= $ids_count ?></span> 件
    </div>

    <?php if ($ids_count > 0): ?>
        <form id="time-replace-form" method="get" action="batch-preview.php">
            <input type="hidden" name="ids" value="<?= esc_attr($ids_raw) ?>">

            <div style="margin-top:20px;">
                <label style="color:#888;">置換対象（正規表現）:</label><br>
                <input type="text" name="title_from_regex" value="/≫<?= esc_attr($time) ?>＠/" readonly class="readonly-input">
            </div>

            <div style="margin-top:20px;">
                <label style="color:#ffcc00; font-weight:bold;">新しい時間スロットを入力:</label><br>
                <div style="display: flex; align-items: center; gap: 5px;">
                    <span style="font-size: 1.5rem;">≫</span>
                    <input type="text" id="new_time_val" placeholder="例: 12-16b" required autofocus style="font-size: 1.2rem;">
                    <span style="font-size: 1.5rem;">＠</span>
                </div>
                <input type="hidden" name="title_to" id="actual_title_to" value="">
            </div>

            <input type="submit" class="btn-execute" value="プレビュー画面で最終確認 ≫">
        </form>
    <?php else: ?>
        <p style="color: #ff6666;">× 対象データが見つかりません。</p>
        <a href="javascript:history.back();" style="color: #888;">[ 戻る ]</a>
    <?php endif; ?>
</div>

<script>
document.getElementById('time-replace-form').addEventListener('submit', function(e) {
    const val = document.getElementById('new_time_val').value;
    const target = document.getElementById('actual_title_to');

    // 置換後の文字列を組み立てる
    // ここで / を絶対に入れない（純粋な文字列として結合）
    const finalString = '≫' + val + '＠';

    // 値をセット
    target.value = finalString;

    // デバッグ：念のためコンソールで確認（不要なら消してください）
    console.log('Replacing to:', target.value);
});
</script>
</body>
</html>