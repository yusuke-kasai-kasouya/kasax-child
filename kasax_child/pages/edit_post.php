<?php
/**
 * [Path]: pages/edit_post.php
 */
// 編集の場合、外部から $post_id, $post_title, $post_content が渡されている前提
require_once( dirname(__DIR__) . '/../../../wp-load.php' );

if (isset($_GET['id'])) {
    $received_id = $_GET['id'];
}

use Kx\Core\DynamicRegistry as Dy;
use Kx\Utils\Time;


//echo get_the_title($id);
$post_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$edit_id = isset($_GET['edit_id']) ? intval($_GET['edit_id']) : 0;
$mode = $_GET['mode']??'';

$target_id = ($edit_id ?: $post_id) ?: 0;

$colormgr   = Dy::get_color_mgr($target_id);
$traits     = $colormgr['style_array']['vars_only'] ?? '';

$path_index = Dy::get_path_index($target_id);

//echo kx_dump($path_index);

$post_title_parent_dir = $path_index['parent_path'];// 親階層のパス。≫をつける。
$post_title_time_slug  = $path_index['time_slug'];   // 時間ベースの識別子
$post_title_at = $path_index['at_name']??'';


if($edit_id !== 0){
    $post= get_post($edit_id);
    $post_content = $post->post_content ?? '';

    $add_style = '';
}else{
    $post_content = '＿' . Time::format() . '＿';

    $post_title_at .= '（新規追加）';

    $add_style = "border: 3px solid red";
}

$row_style = '';
$ghost_text = '';
if( $post_id !== $edit_id){
    $row_style = 'visibility: hidden;height:0;margin:0;';
    $ghost_text = '<div>Ghost：タイトル編集禁止</div>';
}

?>

<div class="kx-editor-window-static" style="<?= $add_style ?>">
    <form method="post" action="save_post.php">
        <input type="hidden" name="post_id" value="<?= esc_attr($post_id) ?>">
        <input type="hidden" name="edit_id" value="<?= esc_attr($edit_id) ?>">
        <input type="hidden" name="mode" value="<?= esc_attr($mode) ?>">

        <header class="ed-header">
            <div class="js_accordion_trigger __a_hover">▼</div>
            <div class="js_accordion_target">
                <div class="ed-field-row">
                    <label class="ed-label" for="ed-title">親階層：</label>
                    <input type="text" name="post_title_parent_dir" id="ed-title"
                        value="<?= esc_attr($post_title_parent_dir) ?>"
                        placeholder="親階層" tabindex="1">
                </div>
            </div>
        </header>

        <div class="ed-title-row" style="<?= $row_style ?>">
            <input type="text" name="post_title_time_slug" class="ed-input-slug"
                value="<?= esc_attr($post_title_time_slug) ?>"
                placeholder="00-00" tabindex="1">


            <span class="ed-at-mark">＠</span>

            <input type="text" name="post_title_at" class="ed-input-at"
                value="<?= esc_attr($post_title_at) ?>"
                placeholder="タイトル" tabindex="1">

            <button type="submit" class="ed-btn-save-top ed-icon" tabindex="2" onclick="closeEditorImmediate()">⬇</button>
        </div>

        <?= $ghost_text ?>


        <div class="ed-main-container">
            <div class="ed-body">
                <textarea name="post_content" id="ed-content" placeholder="コンテンツ" tabindex="3" oninput="syncToParent(this.value)"><?= $post_content ?></textarea>
            </div>

            <aside class="ed-side-panel">
                <div class="ed-side-actions">
                    <button type="submit" class="ed-btn-save" onclick="closeEditorImmediate()">
                        <span class="ed-icon">⬇</span>
                    </button>
                </div>
            </aside>
        </div>
    </form>
</div>

<style>
/* 独立ページ用なので、モーダル用の fixed や overlay は除去してシンプルに */
body {<?= $traits ?> background-color: hsla(var(--kx-hue,0), 10%, 15%, 1);}
body {
    color: #fff;
    margin: 0; padding: 0px;
    overflow: hidden;
    overscroll-behavior: contain; /* スクロールが端に達した時に親要素が動くのを防ぐ */
}
.kx-editor-window-static {
    width: 100%;
    max-width: 1000px;
    margin: 0 auto;
    padding: 0px;
}
.ed-header input {
    width: 100%;
    background: hsl(0,100%,90%) ;
    padding: 5px; margin-bottom: 10px;
}

.ed-field-row {
    display: flex;
    align-items: center; /* 垂直方向の中央揃え */
    gap: 4px;            /* ラベルと入力欄の隙間 */
    margin-bottom: 5px;
}

.ed-label {
    white-space: nowrap; /* ラベルの改行を防止 */
    color: #ccc;
    font-size: 12px;
}

.ed-field-row input[type="text"] {
    flex-grow: 1;        /* 入力欄を残りスペース一杯に広げる */
    background: #333;
    color: #fff;
    border: 1px solid #555;
    padding: 4px 8px;
    border-radius: 3px;
}


/* タイムスラグ（幅を限定） */
.ed-input-slug {
    width: 120px;
    text-align: center;
}

/* ＠マーク */
.ed-at-mark {
    color: #888;
    font-size: 14px;
    user-select: none;
}

/* タイトル入力欄（最大化） */
.ed-input-at {
    flex-grow: 1; /* これで余白をすべて占有 */
}




/* 既存の ed-header input が 100% になっていたら干渉するので、限定的にするか上書き */
.ed-header input {
    width: auto; /* width: 100% を解除 */
    margin-bottom: 0;
}

/* メインコンテナを横並びに */
.ed-main-container {
    display: flex;
    flex: 1; /* 残りの高さをすべて占有 */
    overflow: hidden;

}

/* テキストエリア */
.ed-body {
    flex: 1;
    display: flex;
}

.ed-body textarea {
    width: 100%;
    height: 700px;
    padding: 10px;
    background: hsl(0,0%,90%); color: #000000;
    /*font-family: 'Consolas', 'Monaco', monospace; */
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans JP", sans-serif;
    font-size: 15px;
    line-height: 1.7;
    resize: none; outline: none;
}


/* サイドパネル */
.ed-side-panel {
    width: 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 10px 0;
    gap: 20px;

}

.ed-side-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
    width: 100%;
    padding: 0 5px;
    border: none;
}

/*ボタン */
/* 上部保存ボタン */
.ed-btn-save-top {
    background: #007cba; color: #fff;
    height: 100%;
    width: 20px;
    padding :3px  0;
    cursor: pointer;
    border-radius: 3px;
    flex-shrink: 0; /* ボタンが潰れないように固定 */
    text-align: center;
    border: none;
}


.ed-btn-save {
    background: hsl(200, 100%, 36%); color: #fff;
    border: none;
    padding: 5px 10px;
    cursor: pointer;
}

.ed-btn-save, .ed-btn-close{
    width: 100%;
    padding: 5px 0;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    color: #fff;
    text-align: center;
}
.ed-icon {
    font-size: 15px;}

.ed-btn-save-top:hover,
.ed-btn-save:hover {
    background:hsl(200, 100%, 75%);
}

.ed-btn-close { background: #444; }
.ed-btn-close:hover { background: #555; }



/* 補足情報エリア */

.ed-info-label {
    writing-mode: vertical-rl; /* 縦書きにしてスマートに */
    margin-top: 10px;
    letter-spacing: 2px;
}

/* 既存タイトルの調整 */
/* タイトル行のコンテナ */
.ed-title-row {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 5px 0px 5px 5px;
}


/* 共通入力スタイル */
.ed-title-row input[type="text"] {
    background: #333;
    color: #fff;
    border: 1px solid #555;
    padding: 6px 8px;
    border-radius: 3px;
    font-size: 14px;
}

.ed-input-at { flex: 1; }
.ed-title-row input { background: #333; color: #fff; border: 1px solid #555; padding: 5px; border-radius: 3px; }

/* アコーディオン */
.js_accordion_trigger { padding: 2px 10px; cursor: pointer; font-size: 10px; background: #333; }
.ed-field-row { padding: 10px; display: flex; align-items: center; gap: 10px; background: #2a2a2a; }

/* 削除ボタン：通常は隠しボタン状態 */
.ed-btn-delete {
    width: 100%;
    padding: 5px 0;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    background: transparent;
    color: #666;
    opacity: 0.2;
    transition: all 0.3s;
    margin-top: 20px;
}

.ed-btn-delete:hover {
    background: #d9534f;
    color: #fff;
    opacity: 1;
}


.__a_hover:hover{
	/*font-weight: bold;*/
	color:#fff;
	text-shadow:
	hsla(180,63%,50%,1) 0px 0px 1px,
	hsla(180,63%,50%,1) 0px 0px 2px,
	hsla(180,63%,50%,1) 0px 0px 4px,
	hsla(180,63%,50%,1) 1px 1px 0px,
	hsla(180,63%,50%,1) -1px 1px 0px,
	hsla(180,63%,50%,1) 1px -1px 0px,
	hsla(180,63%,50%,1)  -1px -1px 0px;
}

</style>

<script>
    /**
     * 親ページの表示をリアルタイムで更新する（軽量・直結ロジック）
     */
    function syncToParent(val) {
        try {
            // window.parent で親の document にアクセスし、該当クラスを全て取得
            const targets = window.parent.document.querySelectorAll('.kx-target-post-<?= $post_id ?>');

            if (targets.length > 0) {
                targets.forEach(el => {
                    // タグ除去なしでそのまま流し込む（あるいは innerText で安全に表示）
                    el.innerText = val;

                    // リアルタイム反映中であることがわかるよう、少しスタイルを変更（任意）
                    el.style.borderLeft = '3px solid #007cba';
                });
            }
        } catch (e) {
            // コンテキストの違いによるエラー抑制
        }
    }

    // ページ読み込み時にも一度実行して同期を確実にする
    window.onload = function() {
        syncToParent(document.getElementById('ed-content').value);
    };

    function closeEditorImmediate() {
        try {
            var topDoc = window.top.document;
            var tid = "<?= $post_id ?>"; // ターゲットID

            // 1. タイトルの即時書き換え
            var title = document.querySelector('input[name="post_title_at"]').value || '';
            var titleTargets = topDoc.querySelectorAll('.kx-target-post-title-' + tid);
            titleTargets.forEach(function(el) {
                el.innerText = '✅' + title + ' 🟢';
                el.style.color = '#00ff00';
                el.style.fontWeight = 'bold';
            });

            // 2. コンテンツ（本文）の即時書き換え
            var content = document.getElementById('ed-content').value;
            var contentTargets = topDoc.querySelectorAll('.kx-target-post-content-' + tid);

            contentTargets.forEach(function(el) {
                // 状態を示すクラスを付与（親のCSSで色を定義していれば反映される）
                el.classList.add('is-saving-temp');

                // 一時的なテキスト反映
                el.innerText = "🟥　保存中\n" + content + "🟥";
                el.style.whiteSpace = 'pre-wrap';
                //el.style.color = 'hsl(120, 100%, 95%)'; // 保存中であることを示す色（任意）
                //el.style.opacity = '0.9';
            });

            // 3. モーダルを閉じる等の既存処理
            if (window.frameElement) {
                if (window.parent.jQuery && window.parent.jQuery('#loader').length) {
                    window.parent.jQuery('#loader').show(); // 親のローダー表示
                }
                const modal = window.frameElement.closest('.kx-inline-editor');
                if (modal) {
                    modal.style.display = 'none'; // エディタを即座に隠す
                }
            }
        } catch (e) {
            console.error("Immediate close failed", e);
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const trigger = document.querySelector('.js_accordion_trigger');
        const target = document.querySelector('.js_accordion_target');

        if (trigger && target) {
            // 初期状態を非表示にする場合はここで設定（任意）
            target.style.display = 'none';

            trigger.addEventListener('click', function() {
                if (target.style.display === 'none') {
                    target.style.display = 'block';
                    this.textContent = '▲';
                    this.classList.add('is-active');
                } else {
                    target.style.display = 'none';
                    this.textContent = '▼';
                    this.classList.remove('is-active');
                }
            });
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        const slugInput = document.querySelector('.ed-input-slug');
        const form = document.querySelector('form[action="save_post.php"]') || document.querySelector('form');

        // 1. 最初のハイフン以降が長い場合のみ、さらに4桁ずつ区切るフォーマット関数
        function formatSlug(value) {
            const firstHyphenIndex = value.indexOf('-');

            // そもそもハイフンがないパターン（例: s00 など）はそのまま返す
            if (firstHyphenIndex === -1) {
                return value;
            }

            // 最初のハイフンを基準に前後を分割
            const prefix = value.substring(0, firstHyphenIndex);       // ハイフンより前（例: 13, 1181, f1）
            const suffixRaw = value.substring(firstHyphenIndex + 1);   // ハイフンより後ろ

            // 【修正】数字以外を消すのではなく、ハイフン（-）のみを除去してアルファベット等も維持する
            const suffixClean = suffixRaw.replace(/-/g, '');

            const parts = [];
            if (suffixClean.length > 0) {
                parts.push(suffixClean.slice(0, 4)); // 後半の最初の4文字（例: 1001, a001）
            }
            if (suffixClean.length > 4) {
                parts.push(suffixClean.slice(4, 8)); // 後半の次の4文字（例: 1300, b300）
            }

            // 後半をハイフンで結合し、前半部分と合体させる
            const formattedSuffix = parts.join('-');
            return prefix + '-' + formattedSuffix;
        }

        // 2. 送信直前に「最初のハイフン1個のみ」の元の形式に復元する関数
        function deformatSlugForSubmit(value) {
            const firstHyphenIndex = value.indexOf('-');
            if (firstHyphenIndex === -1) {
                return value;
            }

            const prefix = value.substring(0, firstHyphenIndex);
            const suffix = value.substring(firstHyphenIndex + 1);
            const cleanSuffix = suffix.replace(/-/g, ''); // 後半部分のハイフンのみをすべて除去

            return prefix + '-' + cleanSuffix;
        }

        if (slugInput) {
            // ページ読み込み時に初期整形を実行
            slugInput.value = formatSlug(slugInput.value);

            // 入力中のリアルタイムフォーマット処理（カーソル位置の保持機能付き）
            slugInput.addEventListener('input', function(e) {
                const input = e.target;
                const selectionStart = input.selectionStart;
                const oldValue = input.value;

                // 整形前にカーソルより左側にあった「ハイフン以外の文字」の数をカウント
                const nonHyphensBeforeCursor = oldValue.substring(0, selectionStart).replace(/-/g, '').length;

                // フォーマットを適用
                const newValue = formatSlug(oldValue);
                input.value = newValue;

                // 整形後にカーソルが正しい位置に戻るよう再計算
                let newCursorPos = 0;
                let nonHyphenCount = 0;
                for (let i = 0; i < newValue.length; i++) {
                    if (newValue[i] !== '-') {
                        nonHyphenCount++;
                    }
                    if (nonHyphenCount === nonHyphensBeforeCursor) {
                        newCursorPos = i + 1;
                        break;
                    }
                }
                if (nonHyphensBeforeCursor === 0) {
                    newCursorPos = 0;
                }
                input.setSelectionRange(newCursorPos, newCursorPos);
            });
        }

        // 3. 送信イベント時に値をデフォーマットして元に戻す
        if (form && slugInput) {
            form.addEventListener('submit', function() {
                slugInput.value = deformatSlugForSubmit(slugInput.value);
            });
        }

    });

    // 4. Ctrl + Return (Enter) ショートカットによる保存処理 (Windows / Linux 対応)
    document.addEventListener('keydown', function(e) {
        // Ctrl + Enter (Return) の判定
        if (e.ctrlKey && e.key === 'Enter') {
            e.preventDefault(); // 改行などのデフォルト挙動を防止

            // 保存ボタンを取得してクリックを発火（closeEditorImmediateおよびform submitイベントを網羅）
            const saveBtn = document.querySelector('.ed-btn-save') || document.querySelector('.ed-btn-save-top');
            if (saveBtn) {
                saveBtn.click();
            }
        }
    });
</script>