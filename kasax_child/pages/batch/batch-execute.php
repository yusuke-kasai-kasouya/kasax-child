<?php
/**
 * pages/batch/batch-execute.php
 * 統計に基づいた動的ステップ制御版
 */

require_once('../../../../../wp-load.php');
use Kx\Batch\AdvancedProcessor;

$processor = new AdvancedProcessor();

// --- 動的ステップ制御ロジック ---
// text6 に 'first' という文字が含まれていれば初回とみなす（AdvancedProcessorの初期値）
$is_first_run = (strpos($processor->state['text6'] ?? '', 'first') !== false);
$step_size = $is_first_run ? 5 : 20;

// 1. 指定された件数で実行
$remaining_count = $processor->execute_step($step_size);

// 実行後、ステートを更新（初回が終わったら text6 の 'first' フラグを消す）
if ($is_first_run) {
    $processor->state['text6'] = str_replace('first', 'running', $processor->state['text6']);
    $processor->save_state();
}

// 2. 状態の取得
$state = $processor->state;
$ids_count = count($state['ids_array']);
$avg_time = (float)($state['text7'] ?? 0);

// 3. 予測時間の算出
$estimated_seconds = round($ids_count * $avg_time);
$estimated_minutes = round($estimated_seconds / 60, 1);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>Processing... (<?= $ids_count ?> remaining)</title>
    <?php if ($ids_count > 0): ?>
        <meta http-equiv="refresh" content="0.5; URL=batch-execute.php">
    <?php endif; ?>
    <style>
        body { background: #111; color: #00ffcc; font-family: monospace; padding: 20px; line-height: 1.2; }
        .console { border: 1px solid #333; background: #000; padding: 15px; min-height: 300px; box-shadow: inset 0 0 10px #004433; }
        .highlight { color: #fff; font-weight: bold; }
        .stat-line { margin-bottom: 10px; color: #888; border-bottom: 1px solid #222; padding-bottom: 5px; }
        .speed-up { color: #ffcc00; font-weight: bold; animation: blink 1s infinite; }
        @keyframes blink { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }
    </style>
</head>
<body>

<div class="console">
    <?php if ($ids_count > 0): ?>
        <div class="stat-line">
            STATUS: <span class="highlight">EXECUTING (Step: <?= $step_size ?>)</span>
            <?php if (!$is_first_run): ?><span class="speed-up">>> SPEED MODE ACTIVE</span><?php endif; ?><br>
            REMAINING: <span class="highlight"><?= $ids_count ?></span> items<br>
            ESTIMATED: <span class="highlight"><?= $estimated_minutes ?> min</span> (<?= $estimated_seconds ?> sec)<br>
            AVG_SPEED: <?= round($avg_time, 3) ?> sec/item
        </div>

        <div style="color: #666;">
            [Processing] <?= $step_size ?> items updated. Reloading...
        </div>

    <?php else: ?>
        <div style="text-align:center; padding:50px;">
            <h2 style="color: #ffff00;">✔ BATCH PROCESS COMPLETED.</h2>
            <p>平均速度: <?= round($avg_time, 4) ?>s で全件処理しました。</p>
            <a href="batch-preview.php" style="color:#00ffcc;">[ Back to Preview ]</a>
        </div>
    <?php endif; ?>
</div>

</body>
</html>