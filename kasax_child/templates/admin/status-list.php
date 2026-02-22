<?php
/**
 * ステータス一覧 テンプレート
 * [Path]: templates/admin/status-list.php
 */
if (!defined('ABSPATH')) exit;

/**
 * 安全策：$stats の構造チェック
 */
if (!isset($stats)) { $stats = []; }

if (!isset($stats['content'])) {
    $stats['content'] = [
        'post' => ['count' => 0, 'visible' => 0, 'raw' => 0, 'diff' => 0],
        'page' => ['count' => 0, 'visible' => 0, 'raw' => 0, 'diff' => 0]
    ];
}
if (!isset($stats['node_count'])) { $stats['node_count'] = 0; }
if (!isset($stats['virtual_nodes'])) { $stats['virtual_nodes'] = 0; }

// LaravelとErrorLogのデフォルト値を定義（データがない場合の保険）
if (!isset($stats['laravel'])) { $stats['laravel'] = null; }
if (!isset($stats['error_log'])) { $stats['error_log'] = null; }

$content = $stats['content'];
?>

<div class="wrap">
    <h1 class="wp-heading-inline">kasax_child ステータス</h1>

    <?php if (isset($stats['laravel'])) : $lv = $stats['laravel']; ?>
        <span id="laravel-status-badge" style="
            margin-left: 10px;
            padding: 2px 8px;
            border: 1px solid <?php echo esc_attr($lv['color']); ?>;
            border-radius: 4px;
            background: #fff;
            display: inline-flex;
            align-items: center;
            vertical-align: middle;
            color: <?php echo esc_attr($lv['color']); ?>;
            font-size: 12px;
            font-weight: 600;
        ">
            <span class="dashicons <?php echo esc_attr($lv['icon']); ?>" style="font-size: 16px; width: 16px; height: 16px; margin-right: 4px;"></span>
            <?php echo esc_html($lv['label']); ?>
        </span>
    <?php endif; ?>
    <hr class="wp-header-end">


    <?php if (!empty($stats['error_logs']['has_error'])) : ?>
        <div class="notice notice-error" style="margin: 20px 0; padding: 15px; border-left-width: 5px;">
            <h3 style="color: #d63638; margin: 0 0 10px 0;">
                <span class="dashicons dashicons-warning"></span> SQL ERROR 検知：<?php echo esc_html($stats['error_logs']['time']); ?>
            </h3>
            <div style="background: #1d2327; color: #72aee6; padding: 15px; font-family: 'Consolas', monospace; max-height: 250px; overflow-y: auto; border-radius: 4px; line-height: 1.5;">
                <?php echo $stats['error_logs']['raw_html']; ?>
            </div>
        </div>
    <?php else : ?>
        <div class="notice notice-success is-dismissible" style="margin: 20px 0;">
            <p><strong>SQL状況：</strong> 本日（<?php echo esc_html($stats['error_logs']['today']); ?>）のエラーログは検出されませんでした。</p>
        </div>
    <?php endif; ?>

    <div class="postbox" style="margin-top: 20px; border: 2px solid #0073aa;">
        <h2 class="hndle"><span>【重要】システム整合性チェック</span></h2>
        <div class="inside">
            <p>以下の3つの数値が一致している必要があります。不一致の場合、DBメンテナンスが必要です。</p>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>指標名</th>
                        <th>カウント</th>
                        <th>ソーステーブル</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>① WP投稿総数 (post)</td>
                        <td><?php echo number_format($content['post']['count']); ?></td>
                        <td><code>wp_posts</code></td>
                    </tr>
                    <tr>
                        <td>② メタデータ総数 (kx0)</td>
                        <td><?php echo number_format($stats['kx0_count']); ?></td>
                        <td><code>wp_kx_0</code></td>
                    </tr>
                    <tr>
                        <td>③ Hierarchy 実体ノード数</td>
                        <td><?php echo number_format($stats['real_nodes']); ?></td>
                        <td><code>wp_kx_hierarchy</code></td>
                    </tr>
                </tbody>
            </table>

            <?php
            // 整合性判定
            $is_synced = ($content['post']['count'] == $stats['kx0_count'] && $stats['kx0_count'] == $stats['real_nodes']);
            ?>

            <div style="margin-top: 15px; padding: 10px; background: <?php echo $is_synced ? '#ecf7ed' : '#fbeaea'; ?>; border-left: 4px solid <?php echo $is_synced ? '#46b450' : '#dc3232'; ?>;">
                <strong>診断：</strong>
                <?php if ($is_synced) : ?>
                    <span style="color: #46b450;">✔ 正常。すべての実体データが同期されています。</span>
                <?php else : ?>
                    <span style="color: #dc3232;">✘ 警告。データの乖離が検出されました。メンテナンスを実行してください。</span>
                <?php endif; ?>
            </div>
        </div>
    </div>


    <div id="dashboard-widgets-wrap">
        <div id="dashboard-widgets" class="metabox-holder">
            <div class="postbox-container-wrapper" style="display: flex; flex-wrap: wrap; gap: 1%;">
                <?php foreach (['post' => '投稿', 'page' => '固定ページ'] as $key => $label): ?>
                <div class="postbox-container" style="flex: 1; min-width: 300px;">
                    <div class="postbox">
                        <h2 class="hndle"><span>執筆統計：<?php echo $label; ?></span></h2>
                        <div class="inside">
                            <p style="font-size: 1.1em; color: #2271b1; margin-bottom: 5px;">
                                <span class="dashicons dashicons-edit"></span> <strong>人間側の見た目の文字数</strong>
                            </p>
                            <p style="font-size: 2.2em; margin: 0 0 15px 0; letter-spacing: -1px;">
                                <strong><?php echo number_format($content[$key]['visible']); ?></strong> <small style="font-size: 0.5em;">文字</small>
                            </p>

                            <hr>

                            <table style="width: 100%; color: #666; font-size: 0.95em; border-collapse: collapse;">
                                <tr>
                                    <td style="padding: 6px 0; border-bottom: 1px dashed #eee;">公開済み数:</td>
                                    <td style="text-align: right; padding: 6px 0; border-bottom: 1px dashed #eee;">
                                        <strong><?php echo number_format($content[$key]['count']); ?></strong> 件
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; border-bottom: 1px dashed #eee;">タグ込(DB容量):</td>
                                    <td style="text-align: right; padding: 6px 0; border-bottom: 1px dashed #eee;">
                                        <?php echo number_format($content[$key]['raw']); ?> 文字
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0;">文字装飾コスト(HTML):</td>
                                    <td style="text-align: right; color: #d63638;">
                                        +<?php echo number_format($content[$key]['diff']); ?> 文字
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card" style="max-width: 100%; margin-top: 20px; padding: 20px;">
        <h2>Hierarchy（Post ＋ 仮想ノード）</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 300px;">項目</th>
                    <th>現在の数値</th>
                    <th>備考</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Hierarchy 総ノード数</strong></td>
                    <td><?php echo number_format($stats['node_count']); ?></td>
                    <td>全ノードの合計</td>
                </tr>
                <tr>
                    <td><strong>仮想ノード数</strong></td>
                    <td><?php echo number_format($stats['virtual_nodes'] ?? 0); ?></td>
                    <td>実体を持たない構造定義用ノード</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>




<style>
    .postbox .inside { padding: 15px 20px; }
    .postbox h2.hndle { font-size: 1.1em; padding: 10px 15px; }
    .card h2 { margin-top: 0; font-weight: 600; }
</style>