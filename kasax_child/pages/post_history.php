<?php
/**
 * [Path]: pages/post_history.php
 *
 * 指定された投稿IDから、作成日時、最終更新日時、およびリビジョン（更新履歴）を取得して表示するツールです。
 */

require_once('../../../../wp-load.php');
global $wpdb;

$post_id = null;
$post_data = null;
$revisions = [];
$error_message = '';

// GETまたはPOSTからIDを取得
if (isset($_REQUEST['post_id']) && $_REQUEST['post_id'] !== '') {
    $post_id = intval($_REQUEST['post_id']);

    if ($post_id > 0) {
        // 1. 親投稿（または指定の投稿データ）をデータベースから直接取得
        $post_data = $wpdb->get_row($wpdb->prepare("
            SELECT ID, post_title, post_type, post_status, post_date, post_modified
            FROM {$wpdb->posts}
            WHERE ID = %d
        ", $post_id));

        if ($post_data) {
            // 2. リビジョン（過去のアップデートタイミング履歴）を取得
            // 対象IDを親（post_parent）に持ち、post_typeが'revision'のデータを新しい順に取得します
            $revisions = $wpdb->get_results($wpdb->prepare("
                SELECT ID, post_date, post_author
                FROM {$wpdb->posts}
                WHERE post_parent = %d
                  AND post_type = 'revision'
                ORDER BY post_date DESC
            ", $post_id));
        } else {
            $error_message = "指定されたID（ID: {$post_id}）の投稿データが見つかりませんでした。";
        }
    } else {
        $error_message = "有効なIDを入力してください。";
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>投稿日時・更新履歴チェッカー</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            line-height: 1.6;
            max-width: 800px;
            margin: 30px auto;
            padding: 0 20px;
            color: #333;
        }
        h1, h2 {
            border-bottom: 2px solid #ccc;
            padding-bottom: 8px;
            color: #222;
        }
        .search-box {
            background: #f4f4f4;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 25px;
        }
        .search-box input[type="number"] {
            padding: 6px 10px;
            font-size: 16px;
            width: 150px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .search-box button {
            padding: 7px 15px;
            font-size: 16px;
            background: #0073aa;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .search-box button:hover {
            background: #006799;
        }
        .error {
            color: #d15b47;
            background: #fdf2f2;
            border: 1px solid #f8b9b9;
            padding: 10px;
            border-radius: 4px;
            font-weight: bold;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .info-table th, .info-table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        .info-table th {
            background-color: #f9f9f9;
            width: 30%;
        }
        .revision-list {
            background: #fafafa;
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 5px;
        }
        .revision-list ul {
            padding-left: 20px;
            margin: 10px 0;
        }
        .revision-list li {
            margin-bottom: 8px;
        }
        .info-note {
            font-size: 0.85em;
            color: #666;
            margin-top: 15px;
            border-top: 1px dashed #ccc;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <h1>投稿日時・更新履歴チェッカー</h1>

    <div class="search-box">
        <form method="get" action="">
            <label for="post_id"><strong>確認したい投稿ID:</strong></label>
            <input type="number" name="post_id" id="post_id" value="<?php echo esc_attr($post_id); ?>" min="1" required>
            <button type="submit">確認する</button>
        </form>
    </div>

    <?php if ($error_message): ?>
        <p class="error"><?php echo esc_html($error_message); ?></p>
    <?php endif; ?>

    <?php if ($post_data): ?>
        <h2>ID: <?php echo intval($post_data->ID); ?> の詳細データ</h2>
        <table class="info-table">
            <tr>
                <th>タイトル</th>
                <td><strong><?php echo esc_html($post_data->post_title ? $post_data->post_title : '(タイトルなし)'); ?></strong></td>
            </tr>
            <tr>
                <th>投稿タイプ</th>
                <td><code><?php echo esc_html($post_data->post_type); ?></code></td>
            </tr>
            <tr>
                <th>ステータス</th>
                <td><code><?php echo esc_html($post_data->post_status); ?></code></td>
            </tr>
            <tr>
                <th>新規登録日時 (作成日時)</th>
                <td><strong style="color: #2b78e4; font-size: 1.1em;"><?php echo esc_html($post_data->post_date); ?></strong></td>
            </tr>
            <tr>
                <th>最終更新日時</th>
                <td><strong style="color: #d15b47; font-size: 1.1em;"><?php echo esc_html($post_data->post_modified); ?></strong></td>
            </tr>
        </table>

        <h2>アップデートタイミング履歴（リビジョン一覧）</h2>
        <div class="revision-list">
            <?php if (!empty($revisions)): ?>
                <p>過去の更新記録が <strong><?php echo count($revisions); ?>件</strong> 見つかりました（降順）：</p>
                <ul>
                    <?php foreach ($revisions as $rev): ?>
                        <?php
                        // 更新者のユーザー名を取得
                        $author_info = get_userdata($rev->post_author);
                        $author_name = $author_info ? $author_info->display_name : '不明';
                        ?>
                        <li>
                            <strong><?php echo esc_html($rev->post_date); ?></strong>
                            <span style="color: #666; font-size: 0.9em;">
                                (リビジョンID: <?php echo intval($rev->ID); ?> / 更新ユーザー: <?php echo esc_html($author_name); ?>)
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="info-note">
                    ※ 取得された履歴はWordPressの「リビジョン（自動保存を含む）」機能に基づいています。WordPressの設定（<code>wp-config.php</code>における<code>WP_POST_REVISIONS</code>定数など）によっては、保存数が制限されているか、すべての更新履歴が残っていない場合があります。
                </p>
            <?php else: ?>
                <p>リビジョン履歴は見つかりませんでした。</p>
                <p class="info-note">
                    ※ この投稿が一度も更新されていないか、あるいは現在のシステムでリビジョン機能が無効化されている可能性があります。
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</body>
</html>