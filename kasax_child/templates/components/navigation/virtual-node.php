<?php
/**
 * templates\components\navigation\virtual-node.php
 */

namespace Kx\View;
use Kx\Core\DynamicRegistry as Dy;

$node = Dy::get('current_virtual_node');
$full_path = $node['full_path'] ?? '';
$json_data = json_decode($node['json'] ?? '{}', true);

// 先祖(Ancestry)の最後のIDを取得
$ancestry_list = $json_data['ancestry'] ?? [];
$ancestry_id = end($ancestry_list);

get_header();
?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">
        <article class="kx-virtual-node" style="background: #1a1a1a; color: #e0e0e0; min-height: 80vh;">
            <header class="virtual-header" style="background: #252525; padding: 30px; border-bottom: 1px solid #333; box-shadow: 0 4px 10px rgba(0,0,0,0.3);">
                <div class="virtual-badge" style="display: inline-block; background: #3d3d00; color: #ffea00; padding: 2px 10px; border-radius: 4px; font-size: 0.75em; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; border: 1px solid #665c00; margin-bottom: 10px;">
                    Virtual Node
                </div>
                <h1 class="entry-title" style="color: #ffffff; margin: 0; font-size: 2em; text-shadow: 0 2px 4px rgba(0,0,0,0.5);">
                    <?php echo esc_html(basename(str_replace('≫', '/', $full_path))); ?>
                </h1>
                <p class="kx-path-info" style="font-family: monospace; font-size: 0.8em; color: #888; margin-top: 10px;">
                    <span style="color: #555;">Path:</span> <?php echo esc_html($full_path); ?>
                </p>
            </header>

            <div class="entry-content" style="padding: 30px; line-height: 1.8;">
                <nav class="kx-hierarchy-nav" style="margin-bottom: 40px;">
                    <?php if (!empty($ancestry_id)): ?>
                        <div class="nav-label" style="color: #777; font-size: 0.8em; margin-bottom: 8px;">Parent Layer</div>
                        <a href="<?php echo get_permalink($ancestry_id); ?>" style="color: #4da6ff; text-decoration: none; font-size: 1.1em; display: flex; align-items: center;">
                            <span style="margin-right: 8px;">↑</span> <?php echo Dy::get_title($ancestry_id); ?>
                        </a>
                    <?php elseif (!empty($node['parent_path'])): ?>
                        <div class="nav-label" style="color: #777; font-size: 0.8em; margin-bottom: 8px;">Parent Layer (Virtual)</div>
                        <a href="<?php echo home_url('/0/hierarchy/' . urlencode($node['parent_path'])); ?>" style="color: #4da6ff; text-decoration: none; font-size: 1.1em; display: flex; align-items: center;">
                            <span style="margin-right: 8px;">↑</span> <?php echo esc_html($node['parent_path']); ?>
                        </a>
                    <?php endif; ?>
                </nav>

                <section class="kx-child-nodes">
                    <h3 style="border-left: 4px solid #4da6ff; padding-left: 15px; font-size: 1.2em; margin-bottom: 20px;">Contains</h3>

                    <ul style="list-style: none; padding: 0;">
                        <?php
                        // 1. 仮想の子階層 (virtual_descendants)
                        $v_descendants = $json_data['virtual_descendants'] ?? [];
                        foreach ($v_descendants as $v_slug):
                            $v_path = $full_path . '≫' . $v_slug;
                            $v_url = home_url('/0/hierarchy/' . urlencode($v_path));
                        ?>
                            <li style="background: #2d3d2d; margin-bottom: 8px; border-radius: 4px; border-left: 4px solid #a6ff4d;">
                                <a href="<?php echo $v_url; ?>" style="display: block; padding: 12px 20px; color: #e0e0e0; text-decoration: none;">
                                    <span style="color: #a6ff4d; margin-right: 10px;">📁</span>
                                    <?php echo esc_html($v_slug); ?>
                                    <span style="font-size: 0.7em; color: #666; margin-left: 10px;">(Virtual)</span>
                                </a>
                            </li>
                        <?php endforeach; ?>

                        <?php
                        // 2. 実体の子記事 (descendants)
                        $descendants = $json_data['descendants'] ?? [];
                        foreach ($descendants as $child_id):
                        ?>
                            <li style="background: #2d2d2d; margin-bottom: 8px; border-radius: 4px;">
                                <a href="<?php echo get_permalink($child_id); ?>" style="display: block; padding: 12px 20px; color: #ccc; text-decoration: none;">
                                    <span style="color: #4da6ff; margin-right: 10px;">📄</span>
                                    <?php echo get_the_title($child_id); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>

                        <?php if (empty($v_descendants) && empty($descendants)): ?>
                            <p style="color: #555; font-style: italic;">この階層に直属する要素はありません。</p>
                        <?php endif; ?>
                    </ul>
                </section>
            </div>
        </article>
    </main>
</div>

<?php
get_sidebar();
get_footer();