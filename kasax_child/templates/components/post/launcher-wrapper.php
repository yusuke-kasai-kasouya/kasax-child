<?php
/**
 * [Path]: templates/components/post/launcher-wrapper.php
 * * @var array  $ids           ID配列
 * @var string $header_label  生成されたラベルHTML
 * @var string $traits        CSS変数群 (--kx-hue:90;...)
 * @var string $mode          表示モード
 * @var array  $data          解析済みオプション
 */
use Kx\Components\KxLink;
use Kx\Component\PostCard;

$current_ids = $ids ?? [];
$is_numbered = $data['is_numbered'] ?? false;
$is_modified = $data['is_modified'] ?? false
?>

<div class="kx-launcher-container kx-mode-<?php echo esc_attr($mode); ?>">

    <?php if (!empty($header_label) && ($data['has_header'] ?? false)): ?>
        <header class="kx-launcher-header kx-header-bar" style="<?php echo esc_attr($traits ?? ''); ?>">
            <div class="kx-header-inner">
                <?php echo $header_label; ?>
            </div>
        </header>
    <?php endif; ?>

    <div class="kx-launcher-body">
        <?php
        foreach ($current_ids as $index => $post_id) :
            if ($mode === 'card') {
                $render_mode = $args['card_mode'] ?? $mode;
                $args_card = [];
                echo PostCard::render((int)$post_id, $render_mode, $args_card);
            } else {
                $link_args = $args;
                if ($is_numbered) {
                    $link_args['index'] = $index + 1;
                }
                if ($is_modified) {
                    $link_args['modified'] = true;
                }
                echo KxLink::render((int)$post_id, $link_args);
            }
        endforeach;
        ?>
    </div>
</div>

<style>
/* 独自のヘッダーバウスタイル */
.kx-header-bar {
    /* 細めのバー設定 */
    padding: 2px 12px;
    font-size: 0.85rem;
    line-height: 1.4;

    /* 上半分に丸み */
    border-radius: 8px 8px 0 0;

    /* traitsからの背景色反映 */
    background-color: hsla(var(--kx-hue), var(--kx-sat), var(--kx-lum), var(--kx-alp));

    /* 下線の装飾（アクセント） */
    border-bottom: 1px solid hsla(var(--kx-hue), var(--kx-sat), var(--kx-lum), 0.5);

    margin-bottom: 4px;
    display: inline-block; /* ラベルの長さに合わせる場合。全幅なら block */
    width: 100%;
}

.kx-header-inner {
    color: #fff; /* 基本白文字、必要に応じて traits 側で上書き */
    font-weight: 500;
}

.kx-label-type {
    opacity: 0.7;
    font-size: 0.75rem;
    margin-right: 2px;
}

.kx-label-sep {
    margin: 0 6px;
    opacity: 0.3;
}
</style>