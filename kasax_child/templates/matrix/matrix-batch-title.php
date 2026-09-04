<?php
/**
 * templates/matrix/matrix-batch-title.php
 * Matrixシステム用：一括処理・バッチ操作パネル
 */

use Kx\Core\DynamicRegistry as Dy;

$parent_id    = $matrix['post_id'] ?? null;

// 1. 直下のみ（Direct）のIDリストを準備 (展開済みのマトリクスデータから取得するため高速)
$ids_direct = array_column($matrix['items'] ?? [], 'id');
if ($parent_id) {
    array_unshift($ids_direct, $parent_id); // 先頭に追加
}
$str_ids_direct = implode(',', $ids_direct);

// 2. 末端最下部まで（All Descendants）のIDリストの事前取得処理は、パフォーマンス向上のため廃止
// （pages/batch/batch-preview.php 側で選択時にのみ遅延実行します）

// 3. クローンツール用にROOT IDを除外したリストを作成 (直下のみを維持)
$ids_cloner     = array_diff($ids_direct, [$parent_id]);
$str_ids_cloner = implode(',', $ids_cloner);

$path_index   = Dy::get_path_index($parent_id);
$parent_title = $path_index['full'] ?? '';
$title_end    = $path_index['last_part'] ?? '';

// 4. スタイル・色の取得
$colormgr     = Dy::get_color_mgr($parent_id);
$style_base   = $colormgr['style_base'] ?? '';
$accent_color = "hsla(var(--kx-hue), 100%, 80%, 1)";
$text_dim     = "rgba(255, 255, 255, 0.5)";

// クローンツール用URL（従来どおり除外済みIDリストを渡す）
$cloner_url   = get_stylesheet_directory_uri() . '/pages/batch/batch-tree-cloner.php?' . http_build_query([
    'id_base'    => $parent_id,
    'title_base' => $parent_title,
    'ids'        => $str_ids_cloner,
    'title_end'  => $title_end
]);
?>

<div class="kx-batch-container">
    <div class="js_accordion_trigger kx-batch-trigger" style="<?= $style_base ?>">
        <span class="batch-icon">▽</span> BATCH OPERATIONS
    </div>

    <div class="js_accordion_target kx-batch-content" style="<?= $style_base ?> display: none;">
        <?php if (!empty($ids_direct)): ?>
            <div class="kx-batch-grid">

                <!-- 第1列：TITLE REPLACE -->
                <div class="kx-batch-column">
                    <h4 class="kx-column-title">TITLE REPLACE</h4>

                    <form action="<?= esc_url(get_stylesheet_directory_uri() . '/pages/batch/batch-preview.php') ?>" method="get" target="_blank" style="margin: 0;">
                        <input type="hidden" name="id_base" value="<?= esc_attr($parent_id) ?>">
                        <input type="hidden" name="title_base" value="<?= esc_attr($parent_title) ?>">
                        <input type="hidden" name="title_end" value="<?= esc_attr($title_end) ?>">
                        <input type="hidden" name="ids_direct" value="<?= esc_attr($str_ids_direct) ?>">

                        <div class="kx-info-row">
                            <span class="label">ROOT ID:</span>
                            <span class="value"><?= esc_html($parent_id) ?></span>
                        </div>
                        <div class="kx-info-row">
                            <span class="label">CURRENT END:</span>
                            <span class="value accent"><?= esc_html($title_end) ?></span>
                        </div>

                        <div class="kx-info-row" style="align-items: center;">
                            <span class="label">RANGE:</span>
                            <select name="replace_range" class="kx-select"
                                    style="background: #111; color: <?= $accent_color ?>; border: 1px solid rgba(255,255,255,0.2); font-family: inherit; font-size: 0.7rem; padding: 2px 5px; outline: none; cursor: pointer;">
                                <option value="all">ALL DESCENDANTS</option>
                                <option value="direct">DIRECT ONLY (<?= count($ids_direct) ?> items)</option>
                            </select>
                        </div>

                        <div class="kx-action-area">
                            <button type="submit" class="kx-btn-link" style="background: none; font-family: inherit; font-size: inherit; text-align: left; cursor: pointer;">
                                LAUNCH REPLACE TOOL ≫
                            </button>
                        </div>
                    </form>
                </div>

                <!-- 第2列：TREE CLONE & SHORTCODE DISABLE（2段構造） -->
                <div class="kx-batch-column border-left">
                    <!-- 上段：TREE CLONE -->
                    <div class="kx-batch-block">
                        <h4 class="kx-column-title">TREE CLONE</h4>
                        <div class="kx-info-row">
                            <span class="label">SOURCE ROOT:</span>
                            <span class="value"><?= esc_html($parent_id) ?></span>
                        </div>
                        <div class="kx-info-row">
                            <span class="label">UNIT COUNT:</span>
                            <span class="value accent"><?= count($ids_cloner) ?> units</span>
                        </div>
                        <div class="kx-info-row">
                            <span class="label">TARGET IDS:</span>
                            <span class="value small dim"><?= esc_html($str_ids_cloner) ?></span>
                        </div>
                        <div class="kx-action-area">
                            <a href="<?= esc_url($cloner_url) ?>" class="kx-btn-link" target="_blank">
                                SETUP TREE CLONE ≫
                            </a>
                        </div>
                    </div>

                    <!-- 下段：SHORTCODE DISABLE -->
                    <div class="kx-batch-block border-top">
                        <h4 class="kx-column-title">SHORTCODE DISABLE</h4>

                        <form action="<?= esc_url(get_stylesheet_directory_uri() . '/pages/batch/batch-shortcode-preview.php') ?>" method="get" target="_blank" style="margin: 0;">
                            <input type="hidden" name="id_base" value="<?= esc_attr($parent_id) ?>">
                            <input type="hidden" name="ids_direct" value="<?= esc_attr($str_ids_direct) ?>">

                            <div class="kx-info-row">
                                <span class="label">ROOT ID:</span>
                                <span class="value"><?= esc_html($parent_id) ?></span>
                            </div>
                            <div class="kx-info-row">
                                <span class="label">TARGET:</span>
                                <span class="value accent">tougou= ➔ off_merge=</span>
                            </div>

                            <div class="kx-info-row" style="align-items: center;">
                                <span class="label">RANGE:</span>
                                <select name="replace_range" class="kx-select"
                                        style="background: #111; color: <?= $accent_color ?>; border: 1px solid rgba(255,255,255,0.2); font-family: inherit; font-size: 0.7rem; padding: 2px 5px; outline: none; cursor: pointer;">
                                    <option value="all">ALL DESCENDANTS</option>
                                    <option value="direct">DIRECT ONLY (<?= count($ids_direct) ?> items)</option>
                                </select>
                            </div>

                            <div class="kx-action-area">
                                <button type="submit" class="kx-btn-link" style="background: none; font-family: inherit; font-size: inherit; text-align: left; cursor: pointer;">
                                    LAUNCH DISABLE TOOL ≫
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- 第3列：CONSOLIDATION -->
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

/* 中央列内部の2段組み用スタイル */
.kx-batch-block.border-top {
    margin-top: 25px;
    padding-top: 25px;
    border-top: 1px dashed rgba(255,255,255,0.1);
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
    color: red;
}

/* アコーディオン制御 */
.js_accordion_trigger.is-opened .batch-icon {
    display: inline-block;
    transform: rotate(180deg);
}
</style>