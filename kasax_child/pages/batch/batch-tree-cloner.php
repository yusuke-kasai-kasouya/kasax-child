<?php
/**
 * pages/batch/batch-tree-cloner.php
 * ツリー構成転写：Root指定・置換設定画面
 */
require_once('../../../../../wp-load.php');
use Kx\Core\DynamicRegistry as Dy;

$ids_raw = $_GET['ids'] ?? '';
$ids_array = array_filter(explode(',', $ids_raw));
$ids_count = count($ids_array);

// ソースRoot情報の取得
$source_root_id = $_GET['id_base'] ?? '';
$source_root_title = get_the_title($source_root_id);

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>Tree Cloner Setup | Kx System</title>
    <style>
        body { background: #1a1a1a; color: #ccc; font-family: monospace; padding: 20px; line-height: 1.5; }
        .container { max-width: 800px; margin: 0 auto; border: 1px solid #444; padding: 20px; background: #222; }
        h1 { color: #00ffcc; font-size: 1.5rem; margin-top: 0; border-bottom: 1px solid #444; padding-bottom: 10px; }
        .stat-val { color: #00ffcc; font-weight: bold; }
        .input-group { margin-top: 20px; background: #2a2a2a; padding: 15px; border: 1px solid #444; }
        label { color: #ffcc00; display: block; margin-bottom: 8px; font-weight: bold; }
        input[type="number"], input[type="text"], textarea {
            background: #111; border: 1px solid #555; color: #fff; padding: 10px; width: 100%; box-sizing: border-box; font-family: monospace;
        }
        .flex-row { display: flex; gap: 10px; align-items: center; }
        .item-list { margin-top: 15px; font-size: 0.8rem; color: #888; max-height: 180px; overflow-y: auto; background: #111; padding: 10px; border: 1px solid #333; }
        .btn-execute {
            background: #004433; color: #00ffcc; border: 1px solid #00ffcc; padding: 15px; cursor: pointer;
            font-weight: bold; width: 100%; margin-top: 20px; font-size: 1rem;
        }
        .btn-execute:hover { background: #006644; }
        .source-info { font-size: 0.9rem; margin-bottom: 10px; }
        .divider { border-top: 1px dashed #444; margin: 15px 0; }
    </style>
</head>
<body>
<div class="container">
    <h1>TREE STRUCTURE CLONER</h1>

    <div class="source-info">
        SOURCE ROOT: <span class="stat-val">ID [<?= esc_html($source_root_id) ?>]</span> <?= esc_html($source_root_title) ?><br>
        CLONE UNITS: <span class="stat-val"><?= $ids_count ?> items</span>
    </div>

    <form method="post" action="batch-cloner-staging.php">
        <input type="hidden" name="source_ids" value="<?= esc_attr($ids_raw) ?>">
        <input type="hidden" name="id_base" value="<?= esc_attr($source_root_id) ?>">

        <div class="input-group">
            <label>1. TARGET ROOT ID (複製先RootのID)</label>
            <input type="number" name="target_root_id" placeholder="例: 12345" required autofocus>
            <p style="font-size: 0.75rem; color: #888;">※新Rootタイトル ≫ 末尾タイトル の形式で複製されます。</p>
        </div>

        <div class="input-group">
            <label>2. TITLE REPLACE (末尾タイトルの共通置換)</label>
            <div class="flex-row">
                <input type="text" name="replace_from" placeholder="置換前（FROM）">
                <span style="color:#888;">≫</span>
                <input type="text" name="replace_to" placeholder="置換後（TO）">
            </div>
            <p style="font-size: 0.75rem; color: #888;">※抽出した末尾タイトルの一部を書き換えて複製する場合に使用します。</p>
        </div>

        <div class="input-group">
            <label>3. COMMON CONTENT (複製記事の本文内容)</label>
            <textarea name="common_content" rows="4" placeholder="一律で入力する内容（空でも可）"></textarea>
        </div>

        <div class="item-list">
            <strong>抽出対象（末尾のみ）:</strong><br>
            <?php
            foreach($ids_array as $id) {
                // Dy::get_path_index['parts'] から最後の要素を確実に取得
                $path_info = Dy::get_path_index($id);
                $parts = $path_info['parts'] ?? [];
                $last_part = !empty($parts) ? end($parts) : get_the_title($id);
                echo "・" . esc_html($last_part) . "<br>";
            }
            ?>
        </div>

        <input type="submit" class="btn-execute" value="複製構成を確認する ≫">
    </form>

    <div style="margin-top: 20px; text-align: center;">
        <a href="javascript:window.close();" style="color: #888; text-decoration: none;">キャンセル・閉じる</a>
    </div>
</div>
</body>
</html>