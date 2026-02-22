<?php
/**
 * @var array  $matrix
 * @var string $outline_content
 * @var string $overview
 * @var int    $post_id
 */

use Kx\Core\DynamicRegistry as Dy;

$colormgr = Dy::get_color_mgr($post_id);

// スタイル：左線あり、1.5emインデント
$style = $colormgr['style_array']['outline'] .
         //"border-left: 4px solid hsla(var(--kx-hue), var(--kx-sat), var(--kx-lum), 0.8); " .
         //"background: hsla(var(--kx-hue), var(--kx-sat), var(--kx-lum), 0.01); " .
         //"padding: 10px 10px 10px 0.5em; " .
         "border-radius: 4px;";
?>

<div>
    <?php
    /**
     * 冒頭でバッチパネルを読み込む
     * $matrix には Processor で加工された 'items'（ID群を含む）が渡されているため、
     * そのままサブテンプレートへ引き継ぐ。
     */
        echo \Kx\Utils\KxTemplate::get('matrix/matrix-batch-title', ['matrix' => $matrix], false);
    ?>
</div>

<div class="matrix-outline-container" style="<?= esc_attr($style) ?>">

    <div class="outline-list-section">
        <?php // OutlineManagerが生成したリスト（タイトル一覧）を表示 ?>
        <?= $outline_content ?>
    </div>
</div>

<?php if (!empty($overview)): ?>
    <hr>
    <div class="outline-overview-section" style="margin-bottom: 15px;">
        <?= $overview ?>
    </div>
<?php endif; ?>

<style>
/* ヘッダーをテーブル化 */
.matrix-outline-container{
    margin: 0em 1em;
}
.outline-overview-section{
    margin-top: 1em;
}
</style>