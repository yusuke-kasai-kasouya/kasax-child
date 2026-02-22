<?php
/**
 * [Path]: templates\components\common\link-box.php
 * @var string $context_path, $core_title, $url, $meta_label, $modified_ago, $traits, $custom_css, $mode, $index
 * @var bool $has_custom
 */

// モードに応じたクラス付与
$mode_class = ($mode === 'right') ? 'is-right-aligned' : 'is-standard';
?>

<div class="kx-link <?= esc_attr($custom_css) ?> <?= $mode_class ?>" style="<?= esc_attr($traits) ?>">
    <a href="<?= esc_url($url) ?>" class="kx-link-wrapper">

        <div class="kx-link-layout">

            <?php if (isset($index) && $index !== ''): ?>
                <span class="kx-link-index"><?= esc_html($index) ?></span>
            <?php endif; ?>

            <?php if (!empty($modified_ago)): ?>
                <span class="kx-link-modified"><?= esc_html($modified_ago) ?></span>
            <?php endif; ?>

            <div class="kx-link-content">
                <?php if (!$has_custom && $context_path): ?>
                    <span class="kx-link-context"><?= esc_html($context_path) ?></span>
                <?php endif; ?>

                <span class="kx-link-core"><?= esc_html($core_title) ?></span>
            </div>

            <?php if (!$has_custom && $meta_label): ?>
                <span class="kx-link-badge-char"><?= esc_html($meta_label) ?></span>
            <?php endif; ?>

            <span class="kx-link-chevron"></span>
        </div>

    </a>
</div>

<style>
.kx-link {
    --link-hue: var(--kx-hue, 210);
    --link-sat: var(--kx-sat, 60%);
    --link-lum: var(--kx-lum, 50%);

    --c-core: hsla(var(--link-hue), var(--link-sat), var(--link-lum), 1);
    --c-wrapper: hsla(var(--link-hue), var(--link-sat), var(--link-lum), 0.4);
    --c-text: hsla(var(--link-hue), 20%, 20%, 0.9);

    transition: all 0.2s ease;
    height: 1.4em;
}

.kx-link.is-standard { margin: 3px 1em; }
.kx-link.is-right-aligned { text-align: right; margin: 3px 0em; }

.kx-link.is-right-aligned .kx-link-wrapper {
    border-radius: 20px 0 0 20px;
    border-right: none;
    margin-left: auto;
    width: fit-content;
}

.kx-link-wrapper {
    display: block;
    text-decoration: none;
    background: var(--c-wrapper);
    border: 1px solid hsla(var(--link-hue), var(--link-sat), var(--link-lum), 1);
    border-radius: 20px;
    position: relative;
    overflow: hidden;
}

.kx-link-wrapper:hover {
    border: 1px solid hsla(180,100%,50%);
}

.kx-link-layout {
    display: flex;
    line-height: 1.0em;
    align-items: center;
    gap: 8px; /* index, modified, contentの間の間隔 */
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 1), -1px -1px 2px rgba(0, 0, 0, 1);
}

.is-standard .kx-link-layout { padding: 0px 10px; }
.is-right-aligned .kx-link-layout { padding: 0px 0px 0 10px; }

/* 連番：左端固定 */
.kx-link-index {
    flex-shrink: 0;
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    font-weight: 800;
    color: #fff;
    min-width: 1.8em;
    text-align: center;
    border-right: 1px solid rgba(255, 255, 255, 0.2);
    padding-right: 5px;
}

/* 更新時間：Indexの横に控えめに配置 */
.kx-link-modified {
    flex-shrink: 0;
    font-size: 10px;
    color: hsla(0, 0%, 100%, 1);
    background: hsla(0, 0%, 0%, 0.33);
    padding: 1px 5px;
    font-family: 'Inter', sans-serif;
    letter-spacing: -0.02em;
    min-width: 4em;
    text-align: end;
}

.kx-link-content {
    flex-grow: 1;
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
}

.kx-link-context {
    font-size: 11px;
    color: hsla(0, 0%, 100%, 0.5);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.kx-link-core {
    font-size: 14px;
    font-weight: 500;
    color: #fff;
    background: var(--c-core);
    padding: 2px 12px;
    border-radius: 20px;
    white-space: nowrap;
}

.kx-link-badge-char {
    flex-shrink: 0;
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: #fff;
    font-size: 15px;
    font-weight: 400;
    padding: 1px 8px;
    border-radius: 5px;
}

.is-standard .kx-link-chevron {
    flex-shrink: 0;
    width: 6px;
    height: 6px;
    border-top: 2px solid #ccc;
    border-right: 2px solid #ccc;
    transform: rotate(45deg);
    margin-left: 4px;
}
</style>