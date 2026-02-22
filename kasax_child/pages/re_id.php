<?php
//3,4,6,7,8,9,10

require_once('../../../../wp-load.php');
global $wpdb;

// デフォルト範囲
$range_start = 1;
$range_end = 149;

// 範囲取得（POST優先）
if (!empty($_POST['range_start'])) {
    $range_start = max(1, intval($_POST['range_start']));
}
if (!empty($_POST['range_end'])) {
    $range_end = max($range_start, intval($_POST['range_end']));
} elseif (!empty($_GET['range_start'])) {
    $range_start = max(1, intval($_GET['range_start']));
}
if (!empty($_GET['range_end'])) {
    $range_end = max($range_start, intval($_GET['range_end']));
}


// 除外ID取得
$exclude_ids = [];
if (!empty($_POST['exclude_ids'])) {
    $exclude_ids = array_filter(array_map('intval', explode(',', $_POST['exclude_ids'])));
} elseif (!empty($_GET['exclude_ids'])) {
    $exclude_ids = array_filter(array_map('intval', explode(',', $_GET['exclude_ids'])));
}

$exclude_sql = '';
if (!empty($exclude_ids)) {
    $exclude_sql = 'AND ID NOT IN (' . implode(',', $exclude_ids) . ')';
}

// ID変更処理
if (!empty($_POST['change_id_from']) && !empty($_POST['change_id_to'])) {
    $from = intval($_POST['change_id_from']);
    $to = intval($_POST['change_id_to']);

    // SQL実行（直接ID変更）
    $wpdb->query("UPDATE {$wpdb->posts} SET ID = $to WHERE ID = $from");
    $wpdb->query("UPDATE {$wpdb->posts} SET post_parent = $to WHERE post_parent = $from");
    $wpdb->query("UPDATE {$wpdb->postmeta} SET post_id = $to WHERE post_id = $from");

    echo "<p style='color:green;'>ID $from → $to に変更しました。</p>";
}

// 投稿抽出（範囲を反映）
$results = $wpdb->get_results("
    SELECT ID, post_title
    FROM {$wpdb->posts}
    WHERE post_type = 'post'
      AND ID BETWEEN $range_start AND $range_end
      AND post_status != 'trash'
      $exclude_sql
");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>投稿ID一覧（ID変更付き）</title>
    <style>
        .modal {
            display: none;
            position: fixed;
            top: 30%;
            left: 40%;
            background: #fff;
            padding: 20px;
            border: 2px solid #333;
            z-index: 1000;
        }
        .overlay {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }
    </style>
</head>
<body>
    <h2>投稿一覧</h2>

    <form method="post" action="re_id.php">
        <label for="range_start">開始ID:</label>
        <input type="number" name="range_start" id="range_start" value="<?php echo esc_attr($range_start); ?>" min="1">
        &nbsp;
        <label for="range_end">終了ID:</label>
        <input type="number" name="range_end" id="range_end" value="<?php echo esc_attr($range_end); ?>" min="1">
        <br><br>
        <label for="exclude_ids">除外するID（カンマ区切り）:</label><br>
        <input type="text" name="exclude_ids" id="exclude_ids" value="<?php echo esc_attr(implode(',', $exclude_ids)); ?>" size="40">
        <br><br>
        <button type="submit">再表示</button>
    </form>

    <hr>

    <ul>
        <?php if ($results): ?>
            <?php foreach ($results as $post): ?>
                <?php $link = get_permalink($post->ID); ?>
                <li>
                    ID: <?php echo $post->ID; ?> -
                    <a href="<?php echo esc_url($link); ?>" target="_blank">
                        <?php echo esc_html($post->post_title); ?>
                    </a>
                    <button onclick="openModal(<?php echo $post->ID; ?>)">ID変更</button>
                </li>
            <?php endforeach; ?>
        <?php else: ?>
            <li>該当する投稿はありません。</li>
        <?php endif; ?>
    </ul>

    <!-- モーダルとオーバーレイ -->
    <div class="overlay" id="overlay" onclick="closeModal()"></div>
    <div class="modal" id="modal">
        <form method="post" action="re_id.php">
            <input type="hidden" name="change_id_from" id="change_id_from">
            <label for="change_id_to">新しいIDを入力:</label><br>
            <input type="number" name="change_id_to" id="change_id_to" required>
            <br><br>
            <button type="submit">変更実行</button>
            <button type="button" onclick="closeModal()">キャンセル</button>
        </form>
    </div>

    <script>
        function openModal(id) {
          document.getElementById('change_id_from').value = id;
          document.getElementById('change_id_to').value = id; // ← 初期値を元IDに設定
          document.getElementById('overlay').style.display = 'block';
          document.getElementById('modal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('overlay').style.display = 'none';
            document.getElementById('modal').style.display = 'none';
        }
    </script>
</body>
</html>
