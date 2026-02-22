<?php
/**
 * templates/components/post/line.php
 * 1行集約型・タイトル非表示エディタ特化モード
 */
use \Kx\Component\Editor;

$mode_class = "kx-card--line";
?>

<div class="kx-card <?= $mode_class ?>" style="<?= $traits ?>; --kx-alp: 0.05;margin-bottom: 2px;">
    <div class="kx-line-container">
        <div class="kx-line_cell kx-line_cell--meta">
            <?php foreach($slots as $slot): ?>
                <span class="kx-card_slot"><?= $slot ?></span>
            <?php endforeach; ?>
        </div>

        <div class="kx-line_cell kx-line_cell--main">
            <div class="kx-line_excerpt">
                <?= $excerpt ?>
            </div>
        </div>

        <div class="kx-line_cell kx-line_cell--editor">
            <?= Editor::open($id, 'line', 'E'); ?>
        </div>
    </div>
</div>

<style>
    /* lineモード専用：1行に情報を凝縮 */
.kx-card--line {
    /*line-height: 1em;*/
    margin-bottom: 2px;
    border-radius: 2px 0px;
    border-left: 2px solid hsla(var(--kx-hue), var(--kx-sat), 50%, 0.5);
}

.kx-line-container {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 2px 10px;
    height: 24px; /* 高さを固定して密度を上げる */
    width: 100%;
}

/* 左端：メタ情報（固定幅または最小幅） */
.kx-line_cell--meta {
    flex-shrink: 0;
    display: flex;
    gap: 4px;
    font-size: 0.75rem;
}

/* 中央：本文（可変・溢れは三点リーダー） */
.kx-line_cell--main {
    flex: 1;
    min-width: 0;
    cursor: default;
}

.kx-line_excerpt {
    color: #eee;
    font-size: 0.9rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis; /* 1行制限の肝 */
}

/* 右端：編集ボタン（右寄せ） */
.kx-line_cell--editor {
    flex-shrink: 0;
    margin-left: auto;
}
</style>