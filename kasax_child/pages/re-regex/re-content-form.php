<?php
/**
 * pages/re-regex/re-content-form.php
 * 正規表現コンテンツ・プロセッサー：入力画面
 */

require_once('../../../../../wp-load.php');

// デフォルト値の設定
$default_regex_content = '//';
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>Content Processor | Kx System</title>
    <style>
        /* Yusukeさんの開発環境に合わせたDark Mode構成 */
        body { background: #121212; color: #d1d1d1; font-family: 'Consolas', 'Monaco', monospace; line-height: 1.6; padding: 30px; }
        .container { max-width: 900px; margin: 0 auto; border: 1px solid #333; padding: 30px; background: #1e1e1e; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }

        h1 { font-size: 1.4rem; color: #00e5ff; border-left: 5px solid #00e5ff; padding-left: 15px; margin-bottom: 30px; }

        .section-box { background: #252525; border: 1px solid #3d3d3d; padding: 20px; margin-bottom: 25px; border-radius: 4px; }
        .label { color: #00e5ff; font-weight: bold; font-size: 0.9rem; margin-bottom: 10px; display: block; text-transform: uppercase; }
        .desc { color: #888; font-size: 0.8rem; margin-bottom: 12px; }

        input[type="text"], textarea {
            background: #000; border: 1px solid #444; color: #00ff41; padding: 12px;
            width: 100%; box-sizing: border-box; font-family: 'Consolas', monospace; font-size: 1.1rem;
            outline: none;
        }
        input[type="text"]:focus, textarea:focus { border-color: #00e5ff; box-shadow: 0 0 5px #00e5ff; }

        .btn-proceed {
            background: #00363d; color: #00e5ff; border: 1px solid #00e5ff;
            padding: 15px; cursor: pointer; font-weight: bold; width: 100%; font-size: 1.1rem;
            transition: all 0.2s; margin-top: 10px;
        }
        .btn-proceed:hover { background: #00e5ff; color: #000; box-shadow: 0 0 15px #00e5ff; }

        .regex-hint { color: #888; background: #161616; padding: 15px; font-size: 0.8rem; border-radius: 4px; }
        code { color: #ffcc00; }
    </style>
</head>
<body>

<div class="container">
    <h1>RE-REGEX : CONTENT PROCESSOR</h1>

    <form method="get" action="re-preview.php">

        <div class="section-box">
            <span class="label">Step 1. Target Filter (Title Keyword)</span>
            <p class="desc">置換の対象とする記事群をタイトルで抽出します。前方一致や「≫」を含む指定が有効です。</p>
            <input type="text" name="title_base" placeholder="例：≫階層名 または 空白で全件" value="" autofocus>
        </div>

        <div class="section-box">
            <span class="label">Step 2. Content Regex Replacement</span>
            <p class="desc">本文（post_content）に対する正規表現パターンを入力してください。</p>

            <div style="margin-bottom: 15px;">
                <p class="desc" style="margin-bottom: 5px;">[ Pattern / From ]</p>
                <input type="text" name="content_from" value="<?= $default_regex_content ?>" placeholder="/pattern/flags">
            </div>

            <div>
                <p class="desc" style="margin-bottom: 5px;">[ Replacement / To ]</p>
                <textarea name="content_to" rows="5" placeholder="置換後の文字列または参照（$1, $2...）"></textarea>
            </div>
        </div>

        <div class="regex-hint">
            <strong>Kx System Regex Tips:</strong><br>
            ・改行を含む範囲一致: <code>/&lt;section&gt;.*?&lt;\/section&gt;/s</code><br>
            ・特定シンボルの保持: 置換後に <code>$1</code> 等を活用してください。<br>
            ・空欄にするとマッチした箇所が「削除」されます。
        </div>

        <input type="submit" class="btn-proceed" value="ANALYSIS & PREVIEW ≫">
    </form>

</div>

</body>
</html>