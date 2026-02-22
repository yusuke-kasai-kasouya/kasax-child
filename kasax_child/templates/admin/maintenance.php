<?php
/**
 * ダッシュボード テンプレート
 * * 取得済みデータ ($stats):
 * - recent_posts: 最近更新された投稿30件
 * - recent_pages: 最近更新された固定ページ5件
 * - error_logs: MySQLエラーログ解析結果 (has_error, today, time, raw_html)
 * - maintenance: システム診断結果の配列
 */


if (!defined('ABSPATH')) exit;
?>

<div class="wrap">
    <h1>kasax_child Maintenance</h1>

    <div class="welcome-panel" style="display: flex; gap: 20px; padding: 20px; margin-bottom: 20px; align-items: flex-start;">

        <div style="flex: 2; background: #fff; padding: 15px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <h3 style="border-bottom: 2px solid #f0f0f1; padding-bottom: 10px;">━ 最近更新された投稿 (Top 30)</h3>
            <div style="max-height: 600px; overflow-y: auto;">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 45px;">#</th>
                            <th style="width: 100px;">更新時期</th>
                            <th>タイトル (表示)</th>
                            <th style="width: 140px;">ID (編集リンク)</th>
                            <th style="width: 60px;">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $counter = 1;
                        foreach ($stats['recent_posts'] as $post) :
                            $edit_link = get_edit_post_link($post->ID);
                            $view_link = get_permalink($post->ID);
                            $delete_link = get_delete_post_link($post->ID, '', false);

                            // 最終更新時のタイムスタンプを取得
                            $modified_timestamp = get_post_modified_time( 'U', false, $post->ID );
                            // 現在時刻との差を人間が読める形式に変換
                            $time_diff = human_time_diff( $modified_timestamp, current_time( 'timestamp' ) );
                        ?>
                            <tr>
                                <td><?php echo $counter++; ?></td>

                                <td><?php echo $time_diff; ?>前</td>

                                <td>
                                    <a href="<?php echo esc_url($view_link); ?>" target="_blank">
                                        <strong><?php echo esc_html($post->post_title); ?></strong>
                                    </a>
                                </td>

                                <td>
                                    <a href="<?php echo esc_url($edit_link); ?>">
                                        <code>ID: <?php echo $post->ID; ?></code>
                                    </a>
                                </td>

                                <td>
                                    <?php if ($delete_link) : ?>
                                        <a href="<?php echo $delete_link; ?>" class="submitdelete" style="color: #a00;" onclick="return confirm('ID: <?php echo $post->ID; ?> の記事を削除してもよろしいですか？');">削除</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div style="flex: 0.8; display: flex; flex-direction: column; gap: 20px;">
            <div style="background: #fff; padding: 15px; border: 1px solid #ccd0d4;">
                <h3>━ 固定ページ (Top 5)</h3>
                <ul style="margin: 0; padding: 0; list-style: none;">
                    <?php foreach ($stats['recent_pages'] as $page) : ?>
                        <li style="padding: 8px 0; border-bottom: 1px solid #f0f0f1;">
                            <a href="<?php echo get_edit_post_link($page->ID); ?>" style="text-decoration: none;">
                                <code style="background: #f0f0f1;">ID: <?php echo $page->ID; ?></code> <?php echo esc_html($page->post_title); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>

    <div class="card" style="max-width: 100%; margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ccd0d4;">
        <h2 style="margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #eee;">
            <span class="dashicons dashicons-admin-tools"></span> システム診断結果
        </h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(400px, 1fr)); gap: 15px; margin-top: 15px;">
            <?php foreach ($stats['maintenance'] as $key => $result) :
                if ($key === 'hierarchy_maintenance') continue; // 巨大な項目はスキップ
            ?>
                <div style="padding: 12px; background: #f9f9f9; border: 1px solid #e5e5e5; border-radius: 2px;">
                    <h4 style="margin: 0 0 8px 0; color: #2271b1; font-size: 13px;">■ <?php echo esc_html(str_replace('_', ' ', $key)); ?></h4>
                    <div style="font-size: 12px; line-height: 1.5; background: #fff; padding: 8px; border: 1px solid #eee; max-height: 200px; overflow-y: auto;">
                        <?php echo $result; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>


    <?php if (isset($stats['maintenance']['hierarchy_maintenance'])) : ?>
        <div class="card" style="max-width: 100%; margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ccd0d4;">
            <h2 style="margin-top: 0; color: #d63638;">
                <span class="dashicons dashicons-networking"></span> HIERARCHY MAINTENANCE FULL REPORT
            </h2>
            <div style="margin-top: 15px; padding: 15px; background: #1d2327; color: #f0f0f1; font-family: 'Consolas', monospace; font-size: 12px; line-height: 1.6; border-radius: 4px;">
                <?php echo $stats['maintenance']['hierarchy_maintenance']; ?>
            </div>
        </div>
    <?php endif; ?>

</div>

<style>
    .submitdelete:hover { background: #d63638; color: #fff !important; border-radius: 3px; }
    code { font-family: 'Consolas', monospace; color: #2271b1; }
</style>