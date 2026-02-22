<?php
/**
 * pages/batch/batch_form_regex.php
 * 自由検索・正規表現置換：入力画面
 */

require_once('../../../../../wp-load.php');

// デフォルト値の設定
$default_regex_title   = '//';
$default_regex_content = '//';
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>Regex Batch | Kx System</title>
    <style>
        body { background: #1a1a1a; color: #ccc; font-family: monospace; line-height: 1.4; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; border: 1px solid #444; padding: 20px; background: #222; }
        h1 { font-size: 1.2rem; color: #ffcc00; border-bottom: 1px solid #ffcc00; padding-bottom: 5px; }
        .section-box { background: #2a2a2a; border: 1px solid #333; padding: 15px; margin-bottom: 20px; }
        .label { color: #888; font-weight: bold; font-size: 0.85rem; }
        .desc { color: #666; font-size: 0.75rem; margin-bottom: 8px; }
        input[type="text"], textarea {
            background: #111; border: 1px solid #555; color: #fff; padding: 10px;
            width: 100%; box-sizing: border-box; font-family: monospace; font-size: 1rem;
        }
        .btn-preview {
            background: #443300; color: #ffcc00; border: 1px solid #ffcc00;
            padding: 10px 30px; cursor: pointer; font-weight: bold; width: 100%;
        }
        .btn-preview:hover { background: #ffcc00; color: #000; }
        .regex-hint { color: #aaa; background: #333; padding: 5px; font-size: 0.75rem; border-left: 3px solid #ffcc00; }
        .warning { color: #ff6666; font-size: 0.8rem; margin-top: 5px; }
    </style>
</head>
<body>

<div class="container">
    <h1>Kx_Advanced_Batch_Processor : REGEX ENTRY</h1>
    <p class="desc">WordPress全投稿を対象に、正規表現による検索置換を準備します。</p>

    <form method="get" action="all_title_change.php">
        <div class="section-box">
            <div class="label">1. BASE SEARCH (Keyword)</div>
            <div class="desc">まず、wp_postsを絞り込むためのキーワード（SQL LIKE検索）</div>
            <input type="text" name="title_base" placeholder="例：≫階層名 または 空白で全件" value="">
        </div>

        <div class="section-box">
            <div class="label">2. TITLE REPLACEMENT (Regex)</div>
            <div class="desc">タイトル置換：/正規表現/ 形式。文字列のみならスラッシュ不要。</div>
            <input type="text" name="title_from_regex" value="<?= $default_regex_title ?>" placeholder="/置換前/">
            <div style="margin-top:5px;">
                <input type="text" name="title_to" placeholder="置換後">
            </div>
            <p class="warning">※タイトル置換を行う場合は、必ず「/」で囲んでください。</p>
        </div>

        <div class="section-box">
            <div class="label">3. CONTENT REPLACEMENT (Regex)</div>
            <div class="desc">本文置換：/正規表現/ 形式。</div>
            <input type="text" name="content_from" value="<?= $default_regex_content ?>" placeholder="/置換前/">
            <div style="margin-top:5px;">
                <input type="text" name="content_to" placeholder="置換後">
            </div>
        </div>

        <div class="regex-hint">
            <strong>Regex Tips:</strong><br>
            ・数字: \d , 単語: \w (クラス内で自動エスケープ処理されます)<br>
            ・前方一致: /^.../ , 後方一致: /...$/<br>
            ・否定先読み: (?!...) などの高度なパターンも利用可能です。
        </div>

        <div style="margin-top: 20px;">
            <input type="submit" class="btn-preview" value="PREVIEW TARGETS ≫">
        </div>
    </form>

    <div style="text-align: center; margin-top: 15px;">
        <a href="all_title_change.php" style="color:#666; font-size:0.8rem;">[ Matrix Batch Mode ]</a>
    </div>
</div>

</body>
</html>