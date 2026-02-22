<?php
/**
 * templates/matrix/matrix-batch-title.php
 * Matrixシステム用：一括処理・バッチ操作パネル
 */

use Kx\Core\DynamicRegistry as Dy;

// 1. データ準備
$ids = array_column($matrix['items'] ?? [], 'id');

$parent_id    = $matrix['post_id']??null;
if ($parent_id) {
    array_unshift($ids, $parent_id); // 先頭に追加
}

$str_ids = implode(',', $ids);

$path_index   = Dy::get_path_index($parent_id);
$parent_title = $path_index['full'] ?? '';
$title_end    = $path_index['last_part'] ?? '';

// 2. スタイル・色の取得
$colormgr   = Dy::get_color_mgr($parent_id);
$style_base = $colormgr['style_base'] ?? '';
$accent_color = "hsla(var(--kx-hue), 100%, 80%, 1)";
$text_dim     = "rgba(255, 255, 255, 0.5)";

// 3. URL生成
$replace_url = get_stylesheet_directory_uri() . '/pages/batch/batch-preview.php?' . http_build_query([
    'id_base'    => $parent_id,
    'title_base' => $parent_title,
    'ids'        => $str_ids,
    'title_end'  => $title_end
]);
?>

<div class="kx-batch-container">
    <div class="js_accordion_trigger kx-batch-trigger" style="<?= $style_base ?>">
        <span class="batch-icon">▽</span> BATCH OPERATIONS
    </div>

    <div class="js_accordion_target kx-batch-content" style="<?= $style_base ?> display: none;">
        <?php if (!empty($ids)): ?>
            <div class="kx-batch-grid">

                <div class="kx-batch-column">
                    <h4 class="kx-column-title">TITLE REPLACE</h4>
                    <div class="kx-info-row">
                        <span class="label">ROOT ID:</span>
                        <span class="value"><?= esc_html($parent_id) ?></span>
                    </div>
                    <div class="kx-info-row">
                        <span class="label">CURRENT END:</span>
                        <span class="value accent"><?= esc_html($title_end) ?></span>
                    </div>
                    <div class="kx-info-row">
                        <span class="label">TARGET IDS:</span>
                        <span class="value small dim"><?= esc_html($str_ids) ?></span>
                    </div>
                    <div class="kx-action-area">
                        <a href="<?= esc_url($replace_url) ?>" class="kx-btn-link" target="_blank">
                            LAUNCH REPLACE TOOL ≫
                        </a>
                    </div>
                </div>

                <div class="kx-batch-column border-left">
                    <h4 class="kx-column-title">CONSOLIDATION</h4>
                    <div class="kx-render-wrap">
                        <?php \Kx\Core\Kx_Consolidator::render_ui($parent_id); ?>
                    </div>
                </div>

            </div>
        <?php else: ?>
            <p class="dim" style="text-align:center; padding: 10px;">No items found in matrix.</p>
        <?php endif; ?>
    </div>
</div>

<style>
/* 構造設計 */
.kx-batch-container {
    margin: 5px 0;
    font-size: 0.75rem;
    letter-spacing: 0.05em;
    font-family: var(--kx-font-mono, monospace);
}

.kx-batch-trigger {
    cursor: pointer;
    text-align: right;
    padding: 0px 0px;
    font-weight: bold;
    color: <?= $accent_color ?>;
    /*border-bottom: 1px solid rgba(255,255,255,0.1);*/
    transition: opacity 0.2s;
    opacity: 0.125;
}
.kx-batch-trigger:hover { opacity: 0.8; }

.kx-batch-content {
    padding: 20px;
    border: 1px solid rgba(128,128,128,0.2);
    border-top: none;
    background: rgba(0,0,0,0.2);
}

/* グリッドレイアウト */
.kx-batch-grid {
    display: flex;
    gap: 30px;
    align-items: flex-start;
}

.kx-batch-column {
    flex: 1;
}

.kx-batch-column.border-left {
    border-left: 1px dashed rgba(255,255,255,0.1);
    padding-left: 30px;
}

/* タイポグラフィ */
.kx-column-title {
    margin: 0 0 15px 0;
    font-size: 0.8rem;
    color: <?= $accent_color ?>;
    border-left: 3px solid <?= $accent_color ?>;
    padding-left: 8px;
}

.kx-info-row {
    margin-bottom: 8px;
    display: flex;
}
.kx-info-row .label {
    width: 100px;
    color: <?= $text_dim ?>;
    flex-shrink: 0;
}
.kx-info-row .value { word-break: break-all; }
.kx-info-row .value.accent { color: #fff; font-weight: bold; }
.kx-info-row .value.dim { color: <?= $text_dim ?>; }
.kx-info-row .value.small { font-size: 0.7rem; line-height: 1.4; }

/* ボタン・アクション */
.kx-action-area { margin-top: 20px; }
.kx-btn-link {
    display: inline-block;
    padding: 8px 15px;
    border: 1px solid <?= $accent_color ?>;
    color: <?= $accent_color ?>;
    text-decoration: none;
    transition: all 0.3s;
}
.kx-btn-link:hover {
    background: <?= $accent_color ?>;
    color: #000;
}

/* アコーディオン制御 */
.js_accordion_trigger.is-opened .batch-icon {
    display: inline-block;
    transform: rotate(180deg);
}
</style>