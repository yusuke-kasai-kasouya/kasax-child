<?php
/**
 * Simple Monotone Outline Template
 * components/navigation/outline.php
 *
 * @var array $items Dy['outline'][$post_id]['stack'] の中身
 */

if (empty($items)) return;

// h2の数に応じてゼロ埋め（01.）するか判定
$h2_count = count(array_filter($items, function($i) { return $i['level'] === 2; }));
$sprintf_on = ($h2_count >= 5);


$base_url = null;

$matrix_count = Dy::get('trace')['matrix_count'] ?? 0;
if($matrix_count >1  ){
    $base_url = get_permalink($post_id);
}

$style_grid = ($type === 'side_matrix_grid') ? 'margin: 0 0 0 1em' : '';

$h2_current_idx = 0; // 表示上のH2連番
$h3_current_idx = 0; // 表示上のH3枝番


?>
<nav class="kx-outline-container">
    <?php if($type==='side' || $type==='side_matrix_grid'): ?>
        <div class="kx-outline-header" style="<?= $style_grid ?>">
            <button onclick="window.scrollTo({top:0, behavior:'smooth'})" class="__a_hover" style="width:100%; font-size:10px; background:transparent; color:#888; border:1px solid #333; cursor:pointer;">↑ TOP</button>
        </div>
    <?php endif; ?>


    <ul class="kx-outline-list <?= $type ?>" style="<?= $style_grid ?>">
        <?php
            // --- ループ開始前の初期化 ---
            $h2_current_idx = 0; // 表示上のH2連番
            $h3_current_idx = 0; // 表示上のH3枝番
            foreach ($items as $key => $value):
                // 1. カウンターの更新
                if ($value['level'] === 2) {
                    $h2_current_idx++;       // 新しいH2が出たらカウントアップ
                    $h3_current_idx = 0;     // 枝番をリセット

                    // 5件以上のゼロ埋め判定(sprintf_on)を適用
                    $display_number = $sprintf_on ? sprintf('%02d', $h2_current_idx) : (string)$h2_current_idx;
                } else {
                    $h3_current_idx++;       // H3以降（枝番）をカウントアップ
                    // 親の連番を継承して "1.1" の形式を作成
                    $display_number = "{$h2_current_idx}.{$h3_current_idx}";
                }

                // --- 以下、表示処理（変更なし） ---
                $indent_level = $value['level'] - 2;
                $indent_style = $indent_level > 0 ? "margin-left: " . ($indent_level * 7) . "px;" : "";
                $js_target_class = !empty($value['entry_id']) ? "kx-target-post-title-" . $value['entry_id'] : "";
                $full_url = $base_url . '#' . $value['anchor'];
            ?>
                <li class="kx-outline-item depth-<?php echo $value['level']; ?>" style="<?php echo $indent_style; ?>">
                    <a href="<?php echo esc_url($full_url); ?>" class="kx-outline-link">
                        <span class="kx-outline-number">
                            <?php echo $display_number; // ここで作成した連番を出力 ?>
                        </span>
                        <span class="kx-outline-title <?php echo $js_target_class; ?>">
                            <?php echo $value['title']; ?>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
    </ul>
</nav>

<style>
.kx-outline-container {
    padding: 0px;
    font-size: 0.9rem;
    color: #333;
}
.kx-outline-header {
    padding-bottom: 5px;
}
.kx-outline-top-link {
    text-decoration: none;
    color: #000;
    font-weight: bold;
    font-size: 0.8rem;
}
.kx-outline-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.kx-outline-list.side {
    padding-bottom: 2.0rem !important;
}
.kx-outline-item {
    line-height: 1.4;
    padding: 1px 0;
}
.kx-outline-link {
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: nowrap !important;
    white-space: nowrap !important;
    overflow: hidden;

    text-decoration: none !important;
    color: #555;
    /* 強制的にフレックスボックスにし、折り返しを禁止 */

    gap: 5px;
    transition: color 0.2s;

    align-items: center;
    width: 100%;
}

.kx-outline-title {
    display: inline-block !important; /* flex子要素として機能しつつ改行させない */
    flex: 1 1 auto !important;
    min-width: 0;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    white-space: nowrap !important;
}

/* 注入した i (または span) の設定 */
.kx-outline-title i,
.kx-outline-title span {
    display: inline !important;
    margin-right: 1px;
}


/* ホバー時の設定 */
.kx-outline-link:hover {
    transform: scale(1.02);
    color: #84e2ff;
    text-decoration: underline;
}
/* タイトルを表示しているspanタグへの指定 */
.kx-outline-link span:last-child {
    /* タイトル部分 */
    display: block !important;
    flex: 1 1 auto !important;
    min-width: 0; /* 文字溢れカットに必須 */
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    white-space: nowrap !important;
}
.kx-outline-number {
    font-size: 0.75rem;
    font-family: monospace;
    color: hsla(0, 0%, 50%, 0.5);
    /* 番号の幅を固定し、絶対に縮ませず、改行させない */
    flex: 0 0 auto !important;
    display: inline-block !important;
    min-width: 1.1em; /* 01 などの2桁が収まる最小幅 */
    text-align: left;
    margin-right: 0px;
}
</style>