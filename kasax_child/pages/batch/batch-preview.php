<?php
/**
 * pages/batch/batch-preview.php
 * 修正点: 前回の実行速度(text7)をDBから取得し、今回の合計処理時間を予測表示する機能を追加
 */

require_once('../../../../../wp-load.php');

use Kx\Batch\AdvancedProcessor;
use Kx\Core\KxQuery;

// 1. パラメータ取得と初期化
$title_base = $_GET['title_base'] ?? '';
$title_mode = $_GET['title_mode'] ?? 'both';
$ids_raw    = $_GET['ids']        ?? '';
$title_end  = $_GET['title_end']  ?? '';

// 2. 正規表現置換用フォームからの流入対応
$input_title_from = $_GET['title_from_regex'] ?? ($_GET['title_from'] ?? '');
if (empty($input_title_from) && !empty($title_end)) {
    $input_title_from = '≫' . $title_end;
}


$input_title_to   = $_GET['title_to'] ?? $input_title_from;
if (empty($input_title_from) && !empty($title_end)) {
    $input_title_from = '≫' . $title_end;
    $input_title_to   = '≫' . $title_end; // ここも補完
}

// 3. IDリストの取得
$ids = [];
if (!empty($ids_raw)) {
    $ids = explode(',', $ids_raw);
} elseif (!empty($title_base)) {
    $query = new KxQuery([
        'search'     => $title_base,
        'title_mode' => $title_mode,
        'ppp'        => -1,
    ]);
    $ids = $query->get_ids();
}

if (!empty($ids) && is_array($ids)) {
    usort($ids, function($a, $b) {
        $title_a = get_the_title($a);
        $title_b = get_the_title($b);
        // mb_strlen でマルチバイト文字数を確認し比較
        return mb_strlen($title_a) <=> mb_strlen($title_b);
    });
}

// 4. 表示用の整理
$ids_string = is_array($ids) ? implode(',', $ids) : '';
$ids_count  = is_array($ids) ? count($ids) : 0;

// 5. 【追加】過去の実行速度から所要時間を予測
$processor = new AdvancedProcessor(); // 内部で refresh_state() が走り最新の text7 を取得
$avg_speed = (float)($processor->state['text7'] ?? 0); // 1件あたりの秒数

// 予測計算
$est_total_seconds = $ids_count * $avg_speed;
$est_total_minutes = ($est_total_seconds > 0) ? round($est_total_seconds / 60, 2) : 0;
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>Batch Preview | Kx System</title>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <style>
        body { background: #1a1a1a; color: #ccc; font-family: monospace; line-height: 1.4; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; border: 1px solid #444; padding: 20px; background: #222; }
        h1 { font-size: 1.2rem; color: #00ffcc; border-bottom: 1px solid #00ffcc; padding-bottom: 5px; }
        .info-panel { background: #000; padding: 15px; border-left: 4px solid #00ffcc; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .est-box { text-align: right; border-left: 1px solid #333; padding-left: 20px; }
        .stat-val { color: #00ffcc; font-weight: bold; font-size: 1.2rem; }
        .label { color: #ffcc00; font-weight: bold; }
        .filter-box { background: #002233; border: 1px solid #0077aa; padding: 15px; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 0.85rem; }
        th, td { border: 1px solid #333; padding: 8px; text-align: left; }
        th { background: #2a2a2a; color: #00ffcc; }
        .input-group { margin-top: 20px; background: #2a2a2a; padding: 15px; border: 1px solid #444; }
        input[type="text"], select { background: #111; border: 1px solid #555; color: #fff; padding: 8px; }
        .btn-execute { background: #004433; color: #00ffcc; border: 1px solid #00ffcc; padding: 10px 40px; cursor: pointer; font-weight: bold; }
        .btn-execute:hover { background: #00ffcc; color: #000; }
        .accordion-trigger { background: #333; color: #fff; padding: 10px; cursor: pointer; margin-top: 10px; text-align: center; border: 1px solid #444; }
        .accordion-target { display: none; }
    </style>
</head>
<body>

<div class="container">
    <h1>Kx_Advanced_Batch_Processor : PREVIEW</h1>

    <div class="filter-box">
        <form method="get">
            <span class="label">SEARCH:</span>
            <input type="text" name="title_base" value="<?= esc_attr($title_base) ?>" placeholder="キーワード" style="width:250px;">
            <select name="title_mode">
                <option value="both"   <?= $title_mode === 'both'   ? 'selected' : '' ?>>部分一致</option>
                <option value="prefix" <?= $title_mode === 'prefix' ? 'selected' : '' ?>>前方一致</option>
                <option value="suffix" <?= $title_mode === 'suffix' ? 'selected' : '' ?>>後方一致</option>
                <option value="exact"  <?= $title_mode === 'exact'  ? 'selected' : '' ?>>完全一致</option>
            </select>
            <input type="submit" value="検索" style="cursor:pointer; background: #0077aa; color: #fff; border: none; padding: 7px 15px;">
        </form>
    </div>

    <div class="info-panel">
        <div>
            TARGET_COUNT: <span class="stat-val"><?= $ids_count ?></span> items<br>
            <small style="color:#666;">Keyword: <?= esc_html($title_base ?: 'NONE') ?></small>
        </div>
        <div class="est-box">
            <span class="label">ESTIMATED TIME</span><br>
            約 <span class="stat-val"><?= $est_total_minutes ?></span> 分
            <small>(<?= round($avg_speed, 3) ?>s / item)</small>
        </div>
    </div>

    <h3>PREVIEW (First 5)</h3>
    <table>
        <thead><tr><th>ID</th><th>Current Title</th></tr></thead>
        <tbody>
            <?php
            $first_5 = array_slice($ids, 0, 5);
            foreach ($first_5 as $pid): ?>
            <tr><td><?= $pid ?></td><td><?= esc_html(get_the_title($pid)) ?></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($ids_count > 5): ?>
        <div class="accordion-trigger js_accordion_trigger">▼ VIEW ALL IDS (<?= $ids_count ?>)</div>
        <div class="accordion-target js_accordion_target">
            <table>
                <tbody>
                <?php foreach ($ids as $pid): ?>
                    <tr><td><?= $pid ?></td><td><?= esc_html(get_the_title($pid)) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <form method="post" action="batch-staging.php">
        <input type="hidden" name="ids" value="<?= esc_attr($ids_string) ?>">
        <div class="input-group">
            <label class="label">TITLE FROM / TO:</label><br>
            <input type="text" name="title_from" value="<?= esc_attr($input_title_from) ?>" style="width:45%;"> →
            <input type="text" name="title_to" value="<?= esc_attr($input_title_to) ?>" style="width:45%;">
        </div>
        <div class="input-group">
            <label class="label">REPLACE MODE:</label>
            <input type="radio" name="replace_mode" value="first" checked> 1回のみ
            <input type="radio" name="replace_mode" value="all"> 全置換
        </div>
        <div style="text-align: right; margin-top: 30px;">
            <input type="submit" class="btn-execute" value="START STAGING & NEXT ≫">
        </div>
    </form>
</div>

<script>
$(function() {
    $('.js_accordion_trigger').on('click', function() {
        $(this).toggleClass('is-opened');
        $('.js_accordion_target').slideToggle(100);
    });
});
</script>
</body>
</html>