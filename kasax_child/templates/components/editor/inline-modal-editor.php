<?php
/**
 * [Path]: templates/components/editor/inline-modal.php
 *
 * @version 0.2026.0904
 * @updated 2026-09-04
 * @context エディタINFOアコーディオン内における投稿日時・更新日時の出力対応およびスタイル定義
 * @constraint 既存のDOM構成およびJavaScriptイベントを破壊しないこと
 *
 * @var int         $post_id      WP標準のPostID
 * @var int         $edit_id      編集対象ID（実体IDまたは本体ID）
 * @var string      $editor_mode  エディターの動作モード
 * @var string|null $layout_mode  配置制御用のレイアウトモード
 * @var string      $label        ボタンラベル
 * @var string      $title        モーダルタイトル
 * @var string      $paint        背景色等のインラインスタイル
 * @var string      $traits       CSSカスタム変数等のインラインスタイル
 * @var string      $info_label   インフォメーションラベル
 * @var string      $info_html    インフォメーションのリンク集HTML（日時情報を含む）
 * @var string      $save_html    保存ボタン周辺のUI（コンソリデータ）HTML
 */
$uid = "ed-container-{$post_id}-{$edit_id}";

// モード判定：sidebar_insert の場合は特別なクラスを付与
$is_sidebar_insert = ($editor_mode === 'sidebar_insert');
$modal_class = $is_sidebar_insert ? 'kx-inline-editor--fixed' : 'kx-inline-editor--absolute';


// 表示レイアウト用のモードを決定（layout_modeがあれば優先し、なければ従来のeditor_modeを使用）
$display_mode = isset($layout_mode) ? $layout_mode : $editor_mode;

// デフォルトは右寄せ (right:0)
$position_style = "right: 0px;width: 900px;";
if ($display_mode === 'matrix_editor_left') {
    $position_style = "left: 0px;width: 700px;"; // 左寄せ（右展開）
} else if($display_mode === 'matrix_editor_right' ){
    $position_style = "right: 0px;width: 700px;"; // 右寄せ（左展開）
} else if($display_mode === 'line' ) {
    $position_style = "right: 0px;width: 800px;";
} else if($display_mode === 'ghost' ) {
    $position_style = "right: 0px;width: 800px;";
}



if(!$edit_id){
    $title = '<span style="color:red;">━━━ 新規作成 ━━━ ━━━ ━━━ '.esc_html($title).' ━━━</span>';
}
?>

<div class="kx-editor-anchor" style="position: relative; display: inline-block; vertical-align: middle;">

    <div class="ed-trigger __a_hover" style="<?= $traits ?>" onclick="kxToggleEditor(this)"><?= $label ?></div>

    <div class="kx-inline-editor <?= $modal_class ?>" style="display:none; <?= $position_style ?>">
        <div class="ed-inner">
            <div class="ed-toolbar">
                <div class="ed-title-area" onclick="this.closest('.kx-inline-editor').style.display='none'">
                    <span class="ed-title-text">Edit: <?= $title ?></span>
                </div>

                <div class="ed-info-container">
                    <div class="ed-info-trigger js_accordion_trigger" title="Show Links">
                        <small>▼</small><?= esc_html($info_label) ?>
                    </div>
                    <div class="ed-info-content js_accordion_target">
                        <?= $info_html ?>

                        <?php if ($edit_id && $post_id === $edit_id) : ?>
                            <div class="ed-delete-zone" style="margin-top: 15px; padding-top: 10px; border-top: 1px dotted #555; text-align: right;">
                                <button type="button"
                                        class="ed-btn-delete-trigger"
                                        onclick="kxDeletePostAjax(<?= intval($edit_id) ?>)"
                                        style="background: transparent; border: 1px solid #555; color: #666; font-size: 10px; cursor: pointer; padding: 2px 5px; border-radius: 3px;">
                                    🗑 この記事をゴミ箱へ
                                </button>
                            </div>
                        <?php endif; ?>
                        <div>
                            <?php echo $save_html; ?>
                        </div>
                    </div>

                </div>

                <div class="ed-controls">
                    <button type="button" class="ed-reload-btn" onclick="kxReloadEditor(this)" title="Reload">⟲</button>
                    <button type="button" class="ed-close-x" onclick="this.closest('.kx-inline-editor').style.display='none'">×</button>
                </div>
            </div>
            <iframe data-src="<?= get_stylesheet_directory_uri() ?>/pages/edit_post.php?id=<?= $post_id ?>&edit_id=<?= $edit_id ?>&mode=<?= $editor_mode ?>"
                    style="width:100%; height:800px; border:none; background:#1a1a1a;margin:0;"></iframe>
        </div>
    </div>
</div>

<style>
/* 編集窓本体 */
.kx-inline-editor {
    position: absolute;
    top: -8px;
    /*right: 0px;*/
    /*width: 900px;*/
    background: #222;
    border: 1px solid hsla(var(--kx-hue,0), var(--kx-sat,50%), var(--kx-lum,50%), 1);
    border-top: 5px solid hsla(var(--kx-hue,0), 100%, 75%, 1);
    border-radius: 5px;
    box-shadow: 10px 10px 40px rgba(0,0,0,0.8);
    z-index: 4;
}

.ed-inner {
    display: flex;
    flex-direction: column;
}

.ed-toolbar {
    background-color: hsla(var(--kx-hue,0), 100%, 20%, 1);
    display: flex;
    align-items: stretch; /* 高さを揃えてクリック領域を確保 */
    border-bottom: 1px solid #444;
    height: 30px; /* ツールバーの高さを固定 */

}

/* タイトル領域を最大化 */
.ed-title-area {
    max-width: 80%;
    flex-grow: 1;
    display: flex;
    align-items: center;
    padding: 0 12px;
    cursor: pointer;
}
.ed-title-area:hover {
    background-color: rgba(255, 255, 255, 0.1);
}

.ed-title-text {
    font-size: 14px;
    color: #ccc;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    text-shadow:2px 2px 4px rgba(0,0,0,0.3);
    font-weight: bold;
}

/* 右側のコントロールボタン群 */
.ed-controls {
    display: flex;
    align-items: center;
    gap: 1px;
    padding-right: 5px;
}

.ed-close-x {
    width: 80px;
}

.ed-reload-btn, .ed-close-x {
    background: #444;
    color: #fff;
    border: none;
    cursor: pointer;
    height: 22px;
    padding: 0 8px;
    font-size: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.ed-reload-btn:hover, .ed-close-x:hover {
    background: #666;
}

.ed-trigger {
    background:hsla(0, 0%, 50%, 0.25);
    color: #ffffff;
    cursor: pointer;
    padding: 0px 8px;
    border-radius: 10px;
    border: 0px solid hsla(var(--kx-hue,0), var(--kx-sat,50%), var(--kx-lum,50%), 1);
    background-color: hsla(var(--kx-hue,0), var(--kx-sat,50%), var(--kx-lum,50%), 0.25);
}

.ed-info-container {
    /*position: relative;*/
    display: flex;
    align-items: center;
}

.ed-info-trigger {
    font-size: 11px;
    color: #fff;
    background: rgba(255,255,255,0.1);
    padding: 0 3px;
    height: 80%;
    display: flex;
    align-items: center;
    cursor: pointer;
    border-left: 1px solid #444;
    border-right: 1px solid #444;
    white-space: nowrap;
    border-radius: 10px;
    margin: 0 5px;
}
.ed-info-trigger:hover { background: rgba(255,255,255,0.2); }
.ed-info-trigger.is-opened { background: #444; color: orange; }

.ed-info-content {
    position: absolute;
    top: 30px; /* ツールバーの高さ直下 */
    left: 0;
    width: 100%;
    min-width: 300px;
    background: #333;
    border-bottom: 2px solid orange;
    padding: 10px;
    z-index: 10;
    box-shadow: 0 5px 15px rgba(0,0,0,0.5);
    font-size: 12px;
}
.ed-info-content a {
    display: inline-block;
    margin-right: 10px;
    color: #88ccff;
    text-decoration: none;
    padding: 2px 5px;

}
.ed-info-content a:hover { background: #444; border-color: #88ccff; }

/* 投稿日時・更新日時表示用スタイル */
.ed-info-content .ed-info-dates {
    display: inline-block;
    padding: 2px 5px;
    margin-top: 3px;
    border-left: 2px solid #555;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 2px;
}
</style>

<script>
// 二重定義エラーを防ぐガード
if (typeof kxToggleEditor === 'undefined') {
    window.kxToggleEditor = function(el) {
        const editor = el.nextElementSibling;
        if (editor && editor.classList.contains('kx-inline-editor')) {
            const iframe = editor.querySelector('iframe');
            if (iframe && (!iframe.src || iframe.src === 'about:blank')) {
                iframe.src = iframe.getAttribute('data-src');
            }
            editor.style.display = (editor.style.display === 'none') ? 'block' : 'none';
        }
    };
}

if (typeof kxReloadEditor === 'undefined') {
    window.kxReloadEditor = function(btn) {
        const inner = btn.closest('.ed-inner');
        const iframe = inner.querySelector('iframe');
        if (iframe && iframe.src) {
            iframe.src = iframe.src;
        }
    };
}

if (typeof kxDeletePostAjax === 'undefined') {
    window.kxDeletePostAjax = function(postId) {
        if (!postId || !confirm('この投稿をゴミ箱に移動しますか？')) return;

        jQuery('#loader').show();

        jQuery.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'trash_post_ajax',
                post_id: postId,
                _wpnonce: '<?php echo wp_create_nonce("delete-post_"); ?>' + postId
            }
        })
        .done(function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Error: ' + (response.data || '削除に失敗しました'));
            }
        })
        .fail(function() {
            alert('通信エラーが発生しました');
        })
        .always(function() {
            jQuery('#loader').hide();
        });
    };
}
</script>