<?php
/**
 * [Path]: templates/admin/hierarchy-manager.php
 * Hierarchy Table 管理ページ テンプレート
 */

if (!defined('ABSPATH')) exit;

$kx_created = isset($_GET['kx_created']) ? $_GET['kx_created'] : null;
$target_id  = isset($_GET['target_id'])  ? $_GET['target_id']  : null;

?>

<?php if ($kx_created === '1') : ?>
    <div class="notice notice-success is-dismissible" style="display:block; border-left-color: #46b450;">
        <p>
            <strong>実体化に成功しました。</strong>
            <?php if ($target_id) : ?>
                (作成されたID: <code><?php echo esc_html($target_id); ?></code>)
            <?php else : ?>
                (管理画面で新規ポストを確認してください。)
            <?php endif; ?>
        </p>
    </div>
<?php endif; ?>


<div class="wrap">
    <h1 class="wp-heading-inline">Hierarchy Table 管理</h1>
    <p>Virtual（実体なし）および Alert（整合性エラー）のノードを抽出しています。</p>

    <?php foreach ($groups as $root_name => $data) :
        $rows = $data['rows'];
        $c = $data['counts'];
    ?>
        <div class="postbox" style="margin-bottom: 10px; border: 1px solid #ccd0d4;">
            <div class="postbox-header" style="cursor: pointer; padding: 12px; background: #f6f7f7; display: flex; justify-content: space-between; align-items: center;">
                <h2 style="margin: 0; font-size: 14px;">
                    <strong>【<?php echo esc_html($root_name); ?>】</strong>
                    <span style="font-weight: normal; margin-left: 15px; font-size: 12px;">
                        <span style="color: #cc0000;">Virtual: <strong><?php echo $c['v']; ?></strong></span>
                        <span style="color: #856404; margin-left: 10px;">Alert2: <strong><?php echo $c['a2']; ?></strong></span>
                        <span style="color: #4a7d00; margin-left: 10px;">Alert1: <strong><?php echo $c['a1']; ?></strong></span>
                        <span style="color: #666; margin-left: 10px;">(計: <?php echo count($rows); ?>)</span>
                    </span>
                </h2>
                <span class="dashicons dashicons-arrow-down-alt2"></span>
            </div>

            <div class="inside" style="display: none; margin-top: 0; padding: 0;">
                <table class="wp-list-table widefat fixed striped" style="border: none; box-shadow: none;">
                    <thead>
                        <tr>
                            <th>Path / ID / Links</th>
                            <th style="width: 150px; text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row) :
                            $row_style = '';
                            if ($row['is_virtual']) {
                                $row_style = 'background-color: #fce4e4;';
                            } elseif ($row['alert'] == 2) {
                                $row_style = 'background-color: #fff9db;';
                            } elseif ($row['alert'] == 1) {
                                $row_style = 'background-color: #f4fce3;';
                            }
                        ?>
                            <tr style="<?php echo $row_style; ?>">
                                <td style="white-space: nowrap;">
                                    <strong style="margin-right:15px;"><?php echo esc_html($row['full_path']); ?></strong>

                                    <code style="margin-right:15px;">ID:<?php echo $row['post_id'] ?: '---'; ?></code>

                                    <?php if (!$row['is_virtual'] && !empty($row['post_id'])) : ?>
                                        <span style="font-size: 11px;">
                                            [<a href="<?php echo get_permalink($row['post_id']); ?>" target="_blank">表示</a>]
                                            [<a href="<?php echo get_edit_post_link($row['post_id']); ?>" target="_blank">編集</a>]
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td style="width: 150px; text-align: right;">
                                    <?php if ($row['is_virtual']) : ?>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="target_title" value="<?php echo esc_attr($row['full_path']); ?>">
                                            <button type="submit" name="create_virtual_post" value="1" class="button button-small" style="background:#cc0000; color:#fff; border:none; height:20px; line-height:1;">
                                                Create
                                            </button>
                                        </form>
                                    <?php elseif ($row['alert']) : ?>
                                        <span style="color:#856404; font-weight:bold; font-size:11px;">Alert: <?php echo $row['alert']; ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
document.querySelectorAll('.postbox-header').forEach(header => {
    header.addEventListener('click', function() {
        const inside = this.nextElementSibling;
        const icon = this.querySelector('.dashicons');
        if (inside.style.display === 'none') {
            inside.style.display = 'block';
            icon.classList.replace('dashicons-arrow-down-alt2', 'dashicons-arrow-up-alt2');
        } else {
            inside.style.display = 'none';
            icon.classList.replace('dashicons-arrow-up-alt2', 'dashicons-arrow-down-alt2');
        }
    });
});
</script>