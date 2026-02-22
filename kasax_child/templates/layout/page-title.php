<?php
/**
 * @var int    $post_id
 * @var array  $color_config
 * @var array  $display_titles
 * @var array  $links
 * @var array  $breadcrumb
 * @var string $extra_metadata
 * @var string $last_modified
 * @var string $base_class
 */

$_ue_class = $base_class . ' kx_title_h1_ue';
$_bg_main  = "background-color:hsla(var(--kx-hue),var(--kx-sat),var(--kx-lum),var(--kx-alp));";
$_bg_ue    = "background-color:hsla(var(--kx-hue),var(--kx-sat),var(--kx-lum),1);";
?>

<div class="kx_title_h1" style="<?= $color_config['style_base'] . $_bg_main ?>">

    <div class="kx_title_h1_meta_row <?= $base_class ?>">

        <div class="meta_left">
            <span class="<?= $_ue_class ?>" style="<?= $color_config['style_base'] . $_bg_ue ?>">
                <?php foreach ($breadcrumb as $index => $item): ?>
                    <a href="<?= esc_url($item['url']) ?>" class="breadcrumb_individual_link">
                        <?= esc_html($item['label']) ?>
                    </a>
                    <?php if ($index < count($breadcrumb) - 1): ?>
                        <span class="breadcrumb_separator"> ≫ </span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </span>
        </div>

        <div class="meta_right">
            <span class="id_badge js_accordion_trigger">ID：<?= $post_id ?></span>
            <span class="clipboard_tool js_accordion_target">
                <?= \Kx\Utils\Toolbox::script_id_clipboard($post_id); ?>
            </span>
            <span class="modified_date">- mod: <?= esc_html($last_modified) ?> -</span>
        </div>
    </div>

    <h1>
        <div class="kx_title_h1_main __edit_color <?= $base_class ?>">

            <?php if ($display_titles['grandparent']): ?>
                <a href="<?= esc_url($links['grandparent_url']) ?>" class="title_link_sub">
                    <span class="sub_title_2"><?= esc_html($display_titles['grandparent']) ?></span>
                </a>
            <?php endif; ?>

            <?php if ($display_titles['parent']): ?>
                <a href="<?= esc_url($links['parent_url']) ?>" class="title_link_sub">
                    <span class="sub_title_1"><?= esc_html($display_titles['parent']) ?></span>
                </a>
            <?php endif; ?>

            <span class="main_title_text"><?= esc_html($display_titles['main']) ?></span>

            <?php if ($extra_metadata): ?>
                <span class="extra_info_text"><?= esc_html($extra_metadata) ?></span>
            <?php endif; ?>
        </div>
    </h1>
</div>

<style>
    .kx_title_h1_meta_row {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        font-size: 12px;
        color: #fff;
        margin: 0 1em -2px 0;
    }

    .breadcrumb_link { text-decoration: none; color: inherit; }
    .breadcrumb_link:hover { opacity: 0.8; }

    .kx_title_h1_ue {
        display: inline-block;
        padding: 2px 20px 2px 0.5em;
        border-radius: 0 0 30px 0;
        box-shadow: 2px 2px 5px rgba(0,0,0,0.25);
    }

    .kx_title_h1_main {
        line-height: 44px;
        padding-left: 1em;
    }

    /* タイトルリンク設定 */
    .title_link_sub { text-decoration: none; color: inherit; opacity: 0.7; transition: 0.2s; }
    .title_link_sub:hover { opacity: 1; text-decoration: underline; }

    .main_title_text { font-size: 28px; font-weight: bold; padding-right: 0.8em; }
    .sub_title_1     { font-size: 14px; padding-right: 0.6em; }
    .sub_title_2     { font-size: 16px; padding-right: 0.6em; }
    .extra_info_text { font-size: 18px; font-style: italic; opacity: 0.9; }

    .meta_right span { margin-left: 8px; }
</style>