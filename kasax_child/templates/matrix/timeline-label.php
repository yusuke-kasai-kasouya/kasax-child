<?php
/**
 * templates\matrix\timeline-label.php
 * タイムライン情報の共通表示パーツ
 * @var array $timeline 解析済みのタイムライン配列(age, month, day, time, grade)
 * @var bool  $show_full 全情報を表示するかどうか（行列形式ではtrue推奨）
 */
$show_full = $show_full ?? true;
if (empty($timeline)) return;
?>
<div class="bar-timeline-info">
    <span class="t-grade"><?= esc_html($timeline['grade']) ?></span>
    <span class="t-date">
        <?php if ($show_full && !empty($timeline['month'])): ?>
            <i class="t-month"><?= (int)$timeline['month'] ?>月</i>
        <?php endif; ?>

        <?php if ($show_full && !empty($timeline['day'])): ?>
            <?= esc_html($timeline['day']) ?>
        <?php endif; ?>

        <?php if (!empty($timeline['time'])): ?>
            <small class="t-time">
                <?= substr($timeline['time'], 0, 2) . ':' . substr($timeline['time'], 2, 2) ?>
            </small>
        <?php endif; ?>

        <?php if ($suffix): ?>
            <span class="t-suffix"><?= esc_html($suffix) ?></span>
        <?php endif; ?>
    </span>
</div>

<style>
    /* タイムライン情報の装飾 */
    .bar-timeline-info {
        display: flex;
        gap: 8px;
        font-family: 'Segoe UI', sans-serif;
        align-items: baseline;
    }

    .t-grade {
        background: hsla(0,0%,0%,.33);
        padding: 0 6px;
        border-radius: 10px;
        font-weight: bold;
        line-height: 1em;
    }

    .t-month {
        font-style: normal;
        font-size: 0.8em;
        margin-right: 2px;
    }

    .t-time {
        opacity: 0.7;
        margin-left: 4px;
        font-size: 0.85em;
    }

    .t-suffix{
        margin-left: 2em;
    }

</style>