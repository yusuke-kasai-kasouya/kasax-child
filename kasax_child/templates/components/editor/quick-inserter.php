<?php
/**
 * QuickInserter
 * templates\components\editor\quick-inserter.php
 */
$uid = "qi-" . uniqid();
?>
<div id="wrapper-<?= $uid ?>" class="kx-qi-scope" style="display:inline-block; vertical-align: middle;">
    <button type="button" class="qi-trigger-btn <?= $class ?>"
            style="<?= $paint ?>"
            onclick="if(window.kxToggleQiPanel) kxToggleQiPanel('<?= $uid ?>');">
        <span class="qi-label"><?= esc_html($label) ?></span>
    </button>

    <div id="<?= $uid ?>" class="qi-floating-panel" style="display:none;">
        <div class="qi-inner">
            <div class="qi-header" style="cursor:pointer; display: flex; justify-content: space-between; align-items: center;" onclick="jQuery('#<?= $uid ?>').hide();">
                <span>Quick Creation (<?= esc_html($mode) ?>)</span>
                <span class="qi-close-x" style="font-size: 1.2rem; line-height: 1;">&times;</span>
            </div>

            <div class="qi-body">
                <div class="qi-title-stack">
                    <?php foreach ($title_parts as $part): ?>
                        <div class="qi-part-row">
                            <input type="text" class="qi-input-part" value="<?= esc_attr($part) ?>">
                            <span class="qi-delimiter">≫</span>
                        </div>
                    <?php endforeach; ?>
                    <div class="qi-part-row qi-main-row">
                        <input type="text" class="qi-input-part qi-input-last" value="<?= esc_attr($last_part) ?>" placeholder="メインタイトル">
                    </div>
                </div>
                <div class="qi-content-area">
                    <textarea class="qi-textarea" tabindex="2" placeholder="Content..."><?= esc_textarea($content) ?></textarea>
                </div>
            </div>
            <div class="qi-footer">
                <button type="button" class="qi-close-btn" tabindex="4" onclick="jQuery('#<?= $uid ?>').hide();">CANCEL</button>
                <button type="button" class="qi-save-btn" tabindex="3"
                        onclick="if(window.kxSubmitQuickInsert) kxSubmitQuickInsert('<?= $uid ?>', <?= (int)$parent_id ?>, '<?= esc_js($genre) ?>', '<?= wp_create_nonce('quick_insert_nonce') ?>');">SAVE</button>
            </div>
        </div>
    </div>
</div>

<?php
if (!defined('KX_QI_ASSETS_LOADED')):
    define('KX_QI_ASSETS_LOADED', true);
?>
<script type="text/javascript">
    (function($) {
        window.kxToggleQiPanel = function(uid) {
            var $panel = $('#' + uid);
            if (!$panel.parent().is('body')) {
                $panel.appendTo('body');
            }
            $('.qi-floating-panel').not($panel).hide();
            $panel.toggle();
        };

        window.kxSubmitQuickInsert = function(uid, parentId, genre, nonce) {
            var $wrapper = $('#wrapper-' + uid);
            var $panel = $('#' + uid);
            var $btn = $wrapper.find('.qi-trigger-btn');

            if ($btn.hasClass('is-completed')) return;

            var parts = [];
            $panel.find('.qi-input-part').each(function() {
                parts.push($(this).val());
            });
            var finalTitle = parts.join('≫');
            var finalContent = $panel.find('.qi-textarea').val();

            $panel.hide();

            var $loader = $('#loader').length ? $('#loader') : window.parent.jQuery('#loader');
            if($loader.length) $loader.show();

            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'insert_post_ajax',
                    title: finalTitle,
                    content: finalContent,
                    genre: genre,
                    parent_id: parentId,
                    _wpnonce: nonce
                },
                beforeSend: function() {
                    $btn.css('opacity', '0.5').find('.qi-label').text('⌛');
                }
            })
            .done(function(res) {
                if (res.success) {
                    $btn.addClass('is-completed').prop('disabled', true);
                    $btn.css({'background':'rgba(0,255,0,0.2)','border-style':'solid','color':'#4CAF50','border-color':'#4CAF50','opacity':'1'});
                    $btn.find('.qi-label').html('✔');
                } else {
                    alert('Error: ' + res.data);
                    $btn.css('opacity', '1').find('.qi-label').text('Retry');
                }
            })
            .fail(function() {
                alert('通信失敗');
                $btn.css('opacity', '1').find('.qi-label').text('Retry');
            })
            .always(function() {
                if($loader.length) $loader.hide();
            });
        };
    })(jQuery);
</script>

<style>
/* 1. 全体設定：他のCSSの影響を最小限にする */
.kx-qi-scope * {
    box-sizing: border-box;
}

/* 2. トリガーボタン */
.qi-trigger-btn {
    appearance: none;
    -webkit-appearance: none;
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(0,0,0,0.3);
    border-radius: 10px;
    color: inherit;
    cursor: pointer;
    display: inline-block;
    font-family: inherit;
    font-size: 0.85rem;
    line-height: 1.2;
    margin: 2px;
    padding: 2px 10px;
    text-align: center;
    text-decoration: none;
    vertical-align: middle;
}

.qi-trigger-btn:hover {
    border-color: hsla(180,100%,50%,1);
}

.qi-trigger-btn.is-completed {
    cursor: default !important;
}

/* 3. パネルと入力要素 */
.qi-floating-panel {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 5;
    width: 800px;
    background: #fff;
    border: 2px solid #333;
    border-radius: 8px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    color: #333;
}
.qi-inner { padding: 15px; }
.qi-header { font-weight: bold; margin-bottom: 10px; } /* 元コードに近い形を維持 */
.qi-title-stack { display: flex; flex-direction: column; gap: 5px; margin-bottom: 10px; }
.qi-part-row { display: flex; align-items: center; gap: 5px; }
.qi-input-part {
    flex: 1;
    border: 1px solid #ccc;
    padding: 4px;
    font-size: 0.9rem;
    border-radius: 4px;
    color: #000;
    background: #fff;
}
.qi-main-row { margin-top: 5px; border-top: 1px solid #eee; padding-top: 5px; }
.qi-input-last { border: 1px solid #2196F3; font-weight: bold; background: #f0f7ff; }
.qi-textarea {
    width: 100%;
    height: 300px;
    margin-top: 10px;
    border: 1px solid #ccc;
    border-radius: 4px;
    color: #000;
    background: #fff;
    resize: vertical;
}
.qi-footer { display: flex; justify-content: space-between; margin-top: 10px; }
.qi-save-btn, .qi-close-btn {
    padding: 5px 15px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.85rem;
}
.qi-save-btn { background: #333; color: #fff; border: none; }
.qi-close-btn { background: #eee; color: #333; border: 1px solid #ccc; }


/* ヘッダーバー：ホバー時に周囲を光らせる（青白系） */
.qi-header:hover {
    box-shadow: 0 0 8px hsla(180, 100%, 50%, 0.5);
    transition: box-shadow 0.2s ease-in-out;
}

/* 各ボタン：ホバー時に周囲を光らせる */
.qi-save-btn:hover,
.qi-close-btn:hover,
.qi-trigger-btn:hover {
    box-shadow: 0 0 8px hsla(180, 100%, 50%, 0.5);
    transition: box-shadow 0.2s ease-in-out;
}

/* 保存ボタンは少し強めに発光（黒ベースの影） */
.qi-save-btn:hover {
    box-shadow: 0 0 8px hsla(180, 100%, 50%, 0.5);
}

/* バツ印のスタイル調整 */
.qi-close-x {
    opacity: 0.5;
    padding-left: 10px;
}

.qi-header:hover .qi-close-x {
    opacity: 1;
}
</style>
<?php endif; ?>