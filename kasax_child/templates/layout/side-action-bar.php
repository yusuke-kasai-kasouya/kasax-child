<?php
/**
 * templates/layout/side-action-bar.php
 * @var array $args
 */

use Kx\Core\DynamicRegistry as Dy;

// クラス側から渡された $args['panels'] をループ処理
$panels = $args['panels'] ?? [];
$post_id = $args['current_id'];

// カラーマネージャの取得
$color_data = Dy::get_color_mgr($post_id);
$style_base = $color_data['style_base'] ?? '';
?>

<?php foreach ($panels as $side_key => $panel): ?>
    <div id="<?= esc_attr($panel['dom_id']) ?>"
         class="kx-side-container <?= esc_attr($side_key) ?> <?= $panel['is_minimized'] ? 'is-minimized' : '' ?>"
         style="--accent: <?= esc_attr($panel['accent_color']) ?>;">

        <div class="kx-side-handle" onclick="toggleKxSide(this)">
            <div class="kx-handle-line" style="width:2px; height:50px;"></div>
            <?php if ($side_key === 'right' && !empty($panel['body_content'])): ?>
                <span class="kx-msg-dot"></span>
            <?php endif; ?>
        </div>

        <div class="kx-side-inner">
            <div class="kx-bar-header">
                <span class="kx-title-text" style="<?= esc_attr($style_base) ?> color:hsla(var(--kx-hue, 0), 100%, 40%, 1); font-family:monospace; font-weight:bold;">
                    <?= esc_html($panel['title']) ?>
                </span>
            </div>

            <div class="kx-bar-body">

                <?php if ($side_key === 'right'): ?>
                    <div class="kx-body-top">
                        <nav class="kx-mini-nav">
                            <?php foreach ($panel['header_content'] as $log): ?>
                                <div class="kx-msg-line type-<?= esc_attr($log['type'] ?? 'info') ?>">
                                    <?= $log ?>
                                </div>
                            <?php endforeach; ?>
                        </nav>
                    </div>

                    <div class="kx-body-main">

                        <?php if (is_array($panel['body_content']) && !empty($panel['body_content'])): ?>
                            <div class="<?= $panel['is_minimized'] ? '' : 'js_accordion_trigger' ?> __a_hover" style="display: flex;  justify-content: flex-end;margin:0 0.5em;opacity: 0.25;">
                                <?= $panel['is_minimized'] ? '' : '▼' ?>
                            </div>

                            <div class="kx-msg-stack <?= $panel['is_minimized'] ? '' : 'js_accordion_target' ?>">
                                <?php foreach ($panel['body_content'] as $log): ?>
                                    <div class="kx-msg-line type-<?= esc_attr($log['type'] ?? 'info') ?>">
                                        <?= $log ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                    </div>

                    <div class="kx-body-bottom">
                        <?php foreach ($panel['footer_content'] as $log): ?>
                            <div class="kx-msg-line type-<?= esc_attr($log['type'] ?? 'info') ?>">
                                <?= $log ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                <?php else: ?>
                    <div class="kx-body-main" style="flex: 0 0 100%;">
                        <div class="kx-tree-stack">
                            <?php if (is_array($panel['body_content'])): ?>
                                <?php foreach ($panel['body_content'] as $html_chunk): ?>
                                    <div class="kx-tree-item"><?= $html_chunk // HTMLをそのまま出力 ?></div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php endif; ?>

            </div>
        </div>
    </div>
<?php endforeach; ?>