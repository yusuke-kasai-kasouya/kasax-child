<?php
/**
 * [Path]: templates/matrix/timetable-board.php
 * $matrix: ['time_slots' => [...], 'characters' => [...], 'grid' => [...]]
 */


use Kx\Component\PostCard;
use Kx\Core\DynamicRegistry as Dy;

$post_id       = $matrix['post_id'] ?? [];
$time_slots    = $matrix['time_slots'] ?? [];
$characters    = $matrix['characters'] ?? [];
$grid          = $matrix['grid']       ?? [];
$editor_modes  = $matrix['editor_modes'] ?? '';//'matrix_editor_left'??'';
$hero_no       = $matrix['hero_no'] ?? '';
$hero_id       = $matrix['hero_id'] ?? '';

$base_age_diff = $characters[$hero_no]['age_diff'] ?? 0;

// キャラクター列数（時間バーが横断するスパン数）
$char_count = count($characters);
$last_year = $last_month = $last_day = '';

ob_start();
foreach ($characters as $char_no => $char_info):
    $name = $characters[$char_no]['name'];
    $colormgr = $characters[$char_no]['colormgr'];
    $root_id = $char_info['root_id'] ?? null;
    //var_dump($colormgr['style_base']);


    $is_fixed = (strpos((string)$char_no, '9') === 0);
    $col_class = $is_fixed ? 'fixed-col' : 'flex-col';
    ?>
    <div class="kx-header-cell <?= $col_class ?>" style="<?= $colormgr['style_base'] ?>">
        <?php if ($root_id): ?>
            <a href="<?= esc_url(get_permalink($root_id)) ?>" target="_blank" rel="noopener" class="char-link" style="text-decoration: none; color: inherit;">
                <span class="char-no">c<?= esc_html($char_no) . '：' . esc_html($name) ?></span>
            </a>

            <?php
                echo \Kx\Component\QuickInserter::render(
                    $post_id,
                    $char_info['new_title'],
                    null,
                    '＋',
                    'timetable_matrix'  
                );
             ?>



        <?php else: ?>
            <span class="char-no">c<?= esc_html($char_no) . '：' . esc_html($name) ?></span>
        <?php endif; ?>
    </div>
<?php
endforeach;
$header_cells_html = ob_get_clean(); // 変数に保存


// 現在のコンテキストから変数を準備（実際の実装に合わせて調整してください）
$path_index = Dy::get_path_index($post_id)??[];
$series_val = $path_index['parts'][0]; // $default_series
$hero_val   = $path_index['parts'][1]; // $default_hero
?>

<div class="kx-broadcast-wrapper">
    <div class="kx-broadcast-grid" style="--char-count: <?php echo $char_count; ?>;">

        <div id="kx-grid-header-anchor"></div>

        <div class="kx-grid-header"><?= $header_cells_html ?></div>

        <div id="kx-grid-header-sticky" class="kx-grid-header sticky-mode">
            <div class="header-content">
                <?= $header_cells_html ?>
            </div>
        </div>

        <?php
        // 外部変数の解決（テンプレート上部にあるべきもの）
        $series_val = $matrix['series_key'] ?? '';
        $hero_val   = $matrix['hero_no'] ?? '';
        ?>

        <?php foreach ($time_slots as $slot): ?>
            <?php
                $first_id  = null;
                $slot_name = ''; // 初期化
                $row_ids   = [];
                $slot_data = $grid[$slot] ?? [];

                // この時間帯のデータ解析（スロット名特定とID収集）
                foreach ($slot_data as $cell_data) {
                    if (!empty($cell_data['ids'])) {
                        if (!$first_id) {
                            $first_id  = $cell_data['ids'][0];
                            $slot_name = Dy::get_path_index($first_id)['at_name'] ?? '';
                        }
                        $row_ids = array_merge($row_ids, $cell_data['ids']);
                    }
                }
                $ids_param = implode(',', array_unique($row_ids));
            ?>

            <div class="kx-time-divider" style="grid-column: 1 / span <?php echo $char_count; ?>;">
                <div class="kx-divider-inner" style="display: flex; justify-content: space-between; align-items: center; padding: 0 12px; width: 100%;">

                    <div class="divider-left" style="display: flex; align-items: center; gap: 10px;">
                        <?php
                            echo \Kx\Core\OutlineManager::add_from_loop($post_id, esc_html($slot_name), $post_id);
                            $timeline = \Kx\Utils\Time::parse_slug($slot ?? '', $hero_id);
                            $is_date_changed = \Kx\Utils\Time::check_date_changed($timeline);

                            echo \Kx\Utils\KxTemplate::get('matrix/timeline-label', [
                                'timeline'  => $timeline,
                                'show_full' => $is_date_changed,
                                'suffix'    => $slot_name
                            ], false);
                        ?>
                    </div>

                    <div class="divider-right kx-batch-controls" style="display: flex; align-items: center; gap: 8px;">
                        <?php
                            $time_batch_url = get_stylesheet_directory_uri() . '/pages/batch/batch-time-replace.php?' . http_build_query([
                                'series' => $series_val,
                                'hero'   => $hero_val,
                                'time'   => $slot
                            ]);
                        ?>
                        <div class="kx-batch-actions" style="display: flex; gap: 6px;">
                            <?php if ($ids_param): ?>
                                <a href="<?= get_stylesheet_directory_uri(); ?>/pages/batch/batch-at-replace.php?ids=<?= $ids_param ?>"
                                   target="_blank" class="kx-action-link gray" title="ID一括置換">
                                    <span class="dashicons dashicons-admin-links"></span> Title
                                </a>
                            <?php endif; ?>

                            <a href="<?= esc_url($time_batch_url) ?>"
                               target="_blank" class="kx-action-link gold" title="時間スロット一括変換">
                                <span class="dashicons dashicons-clock"></span> TIME
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <?php foreach ($characters as $char_no => $char_info): ?>
                <?php
                    // 修正：セルデータを一度だけ取得し、そこからidsとgradeを分離
                    $cell_data = $grid[$slot][$char_no] ?? [];
                    $ids       = $cell_data['ids'] ?? [];
                    $grade     = $cell_data['grade'] ?? '';
                    $is_fixed  = (strpos((string)$char_no, '9') === 0);
                ?>
                <div class="kx-content-cell <?php echo $is_fixed ? 'fixed-col' : 'flex-col'; ?>">
                    <?php if (!empty($ids)): ?>
                        <?php foreach ($ids as $id): ?>
                            <div class="kx-matrix-card">
                                <?php echo PostCard::render($id, $editor_modes[$char_no], ['age' => $grade]); ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="kx-empty-cell">
                            <?php
                                $root_id   = $char_info['root_id'];
                                $new_title = Dy::get_title($root_id) . '≫' . $slot . '＠NEW';
                                echo \Kx\Component\QuickInserter::render($root_id, $new_title, '', $char_no . '━' . $slot . '＠NEW', 'matrix');
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
</div>

<style>
.kx-broadcast-wrapper {
    width: 100%;
    overflow-x: auto;
    background: #111;
    padding: 0px 8px 0  0;

    overflow-x: hidden;
    width: 100%;

}

.kx-broadcast-grid {
    display: grid;
    /* 1列目(時間用ラベル)を20px、残りをキャラクター列とする */
    grid-template-columns: repeat(var(--char-count), minmax(60px, 1fr));
    gap: 1px;
    background: #333; /* 枠線代わり */
    min-width: 800px;
}

/* ヘッダー固定 */
.kx-grid-header {
    display: contents;
}
.kx-header-cell {
    position: sticky;
    top: 0;
    z-index: 2;
    /*background: #222;*/

    padding: 10px 5px;
    text-align: center;
    font-weight: bold;
    border-bottom: 2px solid #444;

}

.sticky-mode .kx-header-cell,
.kx-header-cell {
    background: hsla(var(--kx-hue,0), var(--kx-sat,50%), var(--kx-lum,50%), 1);
    text-shadow:
    1px 1px 0 #000,   /* 右下 */
    -1px 1px 0 #000,  /* 左下 */
    1px -1px 0 #000,  /* 右上 */
    -1px -1px 0 #000; /* 左上 */

}



/* 時間表記バー：全列を跨ぐ */
.kx-time-divider {
    background: #2c3e50;
    color: #fff;
    padding: 4px 15px;
    font-size: 0.85rem;
    font-weight: bold;
    letter-spacing: 0.1em;
    position: sticky;
    left: 0;
}

/* コンテンツセル */
.kx-content-cell {
    background: #1a1a1a;
    min-height: 50px;
    padding: 0px;
    transition: all 0.2s ease;
}

/* 9系（左側）の固定幅設定 */
.fixed-col {
    min-width: 140px;
    /*background: #1d252c;*/
}

/* アコーディオン効果（hoverで広がる） */
.flex-col:hover {
    min-width: 250px;
    background: #252525;
    z-index: 3;
    position: relative;
    box-shadow: 0 0 20px rgba(0,0,0,0.8);
}

.kx-matrix-card {
    margin-bottom: 4px;
    border-radius: 3px;
    /*overflow: hidden;*/
}

/* 投稿がないセル */
.kx-empty-cell {
    height: 100%;
    text-align: right;
    /*opacity: 1;*/
}



/* 修正：display: contents をやめる */
/* 元のグリッドヘッダー：デザインを戻す */
.kx-grid-header:not(.sticky-mode) {
    display: contents;
}

/* 判定用アンカー：これの rect.bottom を JS で見る */
#kx-grid-header-anchor {
    grid-column: 1 / -1; /* 全列を跨ぐ */
    height: 0;
    visibility: hidden;
}

/* 固定ヘッダーのデザイン微調整 */
.kx-grid-header.sticky-mode {
    position: fixed;
    top: 60px; /* headerバーの下 */
    left: 249px;
    width: 100%;
    max-width: 1657px;
    background: #222;
    border: 1px solid #444;
    border-radius: 4px;
    z-index: 9999;
    padding: 0px;
    display: flex; /* 固定版はGridではなくFlexで横並びにする */
    justify-content: space-around;
    visibility: hidden;
    opacity: 0;
    transition: all 0.2s ease-in-out;
}

.kx-grid-header.sticky-mode.is-active {
    visibility: visible;
    opacity: 1;
    top: 28px;
}

/* 固定ヘッダー専用のコンテナ設定 */
.sticky-mode .header-content {
    display: flex;         /* 横並びにする */
    flex-direction: row;
    width: 100%;
    justify-content: space-around; /* 均等配置（または flex-start） */
    align-items: center;
}

/* 固定ヘッダー内の各セルの幅を調整 */
.sticky-mode .kx-header-cell {
    flex: 1;              /* 各セルを均等な幅にする */
    min-width: 0;         /* 突き抜け防止 */
    padding: 0px;
    border: 1px solid #444;
    /*background: transparent;*/
}






/* 親コンテナの調整 */
.kx-time-divider {
    border-bottom: 1px solid #333; /* 区切り線 */
    background: rgba(0, 0, 0, 0.2);
    padding: 4px 0;
}

/* 内部の1列レイアウト */
.kx-divider-inner {
    display: flex;
    justify-content: space-between; /* 左右に振り分け */
    align-items: center;           /* 垂直中央揃え */
    padding: 0 12px;
    width: 100%;
}

/* 左側：タイムライン部分 */
.divider-left {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #aaa;
    font-size: 13px;
}

/* 右側：バッチコントロール部分 */
.kx-batch-actions {
    display: flex;
    align-items: center;
    gap: 6px;
}

/* リンクデザイン（前回のクールな設定を維持） */
.kx-action-link {
    display: inline-flex;
    align-items: center;
    line-height: 1em;
    gap: 4px;
    padding: 0px 10px;
    border-radius: 4px;
    text-decoration: none !important;
    font-size: 11px;
    font-family: monospace;
    transition: all 0.2s;
    border: 1px solid transparent;
}

.kx-action-link.gray {
    color: #777;
    background: rgba(255, 255, 255, 0.03);
}
.kx-action-link.gray:hover {
    color: #ccc;
    background: rgba(255, 255, 255, 0.08);
    border-color: #444;
}

.kx-action-link.gold {
    color: #998855;
    background: rgba(153, 136, 85, 0.05);
}
.kx-action-link.gold:hover {
    color: #ffcc00;
    background: rgba(153, 136, 85, 0.15);
    border-color: #998855;
}

/* アイコン微調整 */
.kx-action-link .dashicons {
    font-size: 14px;
    width: 14px;
    height: 14px;
}

.kx-age{
    border-radius: 8px;
    padding:0px 5px;
    background: hsla(var(--kx-hue,0), var(--kx-sat,50%), var(--kx-lum,50%), 1);
    text-shadow:
    1px 1px 0 #000,   /* 右下 */
    -1px 1px 0 #000,  /* 左下 */
    1px -1px 0 #000,  /* 右上 */
    -1px -1px 0 #000; /* 左上 */
}

</style>
<script>
    window.addEventListener('scroll', function() {
        const anchor = document.getElementById('kx-grid-header-anchor');
        const stickyHeader = document.getElementById('kx-grid-header-sticky');

        if (!anchor || !stickyHeader) return;

        // アンカー（ヘッダーのすぐ上にある透明な線）の位置を確認
        const rect = anchor.getBoundingClientRect();

        // アンカーが画面上部に消えたら固定ヘッダーを表示
        if (rect.top < 0) {
            stickyHeader.classList.add('is-active');
        } else {
            stickyHeader.classList.remove('is-active');
        }
    });
</script>