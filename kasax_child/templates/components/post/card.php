<?php
/**
 * templates/components/post/card.php
 */

use \Kx\Component\Editor;

$is_blind = ($mode === 'blind');
if($is_blind) $mode = 'standard';

if( $mode === 'matrix_editor_left' || $mode==='matrix_editor_right'){
    $card_mode = 'standard';
    $title_style = 'max-width: 200px;';
}else{
    $card_mode = $mode;
}

$mode_class = "kx-card--" . ($card_mode ?? 'standard');

$permalink_id = $ghost_to ?? $id;
?>

<div class="kx-card <?= $mode_class ?>" style="<?= $traits ?>">
    <header class="kx-card_header">

        <a href="<?= get_permalink($permalink_id) ?>">
            <div class="kx-card_cell kx-card_cell--title __a_hover kx-target-post-title-<?= $id ?>" style="<?= $traits ?><?= $title_style ?>">
                <?= $title ?>
            </div>
        </a>

        <div class="kx-card_cell kx-card_cell--meta">
            <?php foreach($slots as $slot): ?>
                <span class="kx-card_slot"><?= $slot ?></span>
            <?php endforeach; ?>
        </div>

        <div class="kx-card_cell kx-card_cell--editor">
            <?php
                if( $card_mode === 'standard'){
                    echo \Kx\Component\QuickInserter::render($id, '','',"＋",'card');
                }else{
                    echo Editor::open($id,'insert');
                }
            ?>

        </div>

        <div class="kx-card_cell kx-card_cell--editor">
            <?php echo Editor::open($id,$mode); ?>
        </div>
    </header>



    <?php if ($excerpt): ?>
        <?php if ($is_blind): ?>
            <details class="kx-blind-accordion">
                <summary class="kx-blind-summary" style="<?= $traits ?>">
                    <span class="kx-blind-icon">👁</span> ━━　内容表示　　━━
                </summary>
                <div class="kx-target-post-content kx-target-post-content-<?= $id ?><?= $update_border ?>">
                    <?= $excerpt ?>
                </div>
            </details>
        <?php else: ?>
            <div class="kx-target-post-content kx-target-post-content-<?= $id ?><?= $update_border ?>">
                <?= $excerpt ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>


</div>
<style>
/* ヘッダーをテーブル化 */
.kx-card
{
    margin-top: 0px;
    margin-bottom: 5em;
}
.kx-card_header {
    display: flex;
    align-items: center;  /* 垂直中央 */
    justify-content: space-between;
    width: 100%;
    gap: 3px;
    margin-top: 0;
    margin-bottom: 0;
    /*overflow: hidden;*/    /* はみ出し防止 */
    overflow: visible !important; /
}

/* タイトル部分：余ったスペースをすべて使う */
.kx-card--standard .kx-card_cell--title {
    border-radius: 0px 0px 100px 0px / 0px 0px 50px 0px;
    padding: 1px 50px 1px 5px ;
    font-size: 12pt;
    align-items: center;
}

.kx-card--overview .kx-card_cell--title {
    border-radius: 10px;
    padding: 1px 10px;
    font-size: 10pt;
}

.kx-card_cell--title {
    color:#fff ;
    display: flex;
    white-space: nowrap;  /* 折り返し防止（ラテ欄対応） */
    text-overflow: ellipsis;
    overflow: hidden;
    min-width: 0;         /* Flexbox内での縮小を許可 */
    border: 2px solid hsla(var(--kx-hue,0), var(--kx-sat,50%), var(--kx-lum,50%), 1);
    background-color: hsla(var(--kx-hue,0), var(--kx-sat,50%), var(--kx-lum,50%), 0.25);

}

/* メタ・ボタン部分：中身に合わせて伸縮 */
.kx-card_cell--meta {

    display: flex;
    flex: 1;
    align-items: center;
    gap: 10px;
    justify-content: flex-end;
}

.kx-card_cell--edtior {
    display: flex;
    align-items: center;
    flex-shrink: 0;       /* 編集ボタンが潰れないように固定 */

}

/* 編集ボタンユニット */
.kx-card_actions {
    display: flex;
    gap: 4px;
}

/* kxEdit 内部の干渉を徹底排除 */
.kx-action-unit {
    display: flex;
    align-items: center;
}
.kx-action-unit div {
    display: inline-block !important; /* 強制的に横並び */
    margin: 0 !important;
    padding: 0 !important;
}
.kx-card_body{
    margin-bottom: 5em;
}

.kx-recent_update_border{
    border-left: 1px solid hsla(150,100%,66%,1);


  /* アニメーションの設定: 名前 時間 挙動 終了時の状態維持 */
  animation: kx-fadeOutBorder 0s ease 60s forwards;
}

@keyframes kx-fadeOutBorder{
  to {
    border-left:none;
  }
}

.kx-target-post-content{
    overflow: hidden;
}




/* --- Blind (Accordion) Styles --- */
.kx-blind-accordion {
    width: 100%;
    margin-top: 3px;
    border: 1px solid hsla(var(--kx-hue), var(--kx-sat), 50%, 0.3);
    border-radius: 4px;
}

.kx-blind-summary {
    padding: 2px 12px;
    cursor: pointer;
    font-size: 0.85rem;
    color: #aaa;
    background-color: hsla(var(--kx-hue), var(--kx-sat), 10%, 0.5);
    list-style: none; /* デフォルトの三角を消す */
    outline: none;
    transition: background 0.2s;
}

.kx-blind-summary::-webkit-details-marker {
    display: none; /* Safari用 */
}

.kx-blind-summary:hover {
    background-color: hsla(var(--kx-hue), var(--kx-sat), 20%, 0.8);
    color: #fff;
}

.kx-blind-accordion[open] .kx-blind-summary {
    border-bottom: 1px solid hsla(var(--kx-hue), var(--kx-sat), 50%, 0.2);
    margin-bottom: 10px;
}

.kx-blind-icon {
    margin-right: 8px;
    opacity: 0.7;
}

/* 既存のcontentクラスへの干渉を調整 */
.kx-blind-accordion .kx-target-post-content {
    padding: 0 10px 10px 10px;
    animation: kx-fadeIn 0.3s ease;
}

@keyframes kx-fadeIn {
    from { opacity: 0; transform: translateY(-5px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>