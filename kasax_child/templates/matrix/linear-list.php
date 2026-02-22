<?php
/**
 * templates\matrix\linear-list.php
 * @var array $matrix
 */

use Kx\Core\DynamicRegistry as Dy;


?>
<div class="kx-matrix-container list-mode">

    <?php
    /**
     * 冒頭でバッチパネルを読み込む
     * $matrix には Processor で加工された 'items'（ID群を含む）が渡されているため、
     * そのままサブテンプレートへ引き継ぐ。
     */
        echo \Kx\Utils\KxTemplate::get('matrix/matrix-batch-title', ['matrix' => $matrix], false);

        //$last_year = $last_month = $last_day = '';
        \Kx\Utils\Time::reset_date_check();
    ?>


    <?php foreach ($matrix['items'] as $item): ?>

        <?php
            if( $item['type'] === 'virtual_flag' ) continue;
            $colormgr = Dy::get_color_mgr($item['id']);
            $traits   = $colormgr['style_array']['vars_only'] ?? '';

            // Matrixストレージから解析済みのタイムライン情報を取得
            $timeline = Dy::get_matrix($item['id'], 'timeline');

            // ★共通関数で日付が変わったかどうかを判定
            // 内部で $last_year 等の比較と更新を自動で行う
            $is_date_changed = $timeline ? \Kx\Utils\Time::check_date_changed($timeline) : false;

            // クラス名の決定
            $bar_class = $is_date_changed ? 'is-new-day' : 'is-same-day';

            // ソートラベルの生成
            $sort_label = '';
            if (!empty($item['temp_sort_val'])) {
                $val = (string)$item['temp_sort_val'];
                $short_val = substr($val, 0, -4);
                $sort_label = !empty($short_val) ? $short_val . '：' : '';
            }

            $path_index = Dy::get_path_index($item['id']);

            $last_name = $path_index['last_part_name'];
            $last_name = $last_name? '：'.$last_name : '';

            $time_slug = $path_index['time_slug'] ?? '';
        ?>


        <div class="bar-main-title">
            <?php echo \Kx\Core\OutlineManager::add_from_loop(get_the_ID(), esc_html($sort_label . $item['title'].$last_name), $item['id'] , ['time_slug' => $time_slug ]); ?>
        </div>

        <div class="kx-sort-header-bar <?= $bar_class ?>" style="<?= $traits ?>">
            <?php if ($timeline): ?>
                <?php
                    // 共通テンプレートの呼び出し
                    echo \Kx\Utils\KxTemplate::get('matrix/timeline-label', [
                        'timeline'  => $timeline,
                        'show_full' => $is_date_changed, // 日付が変わった時だけ月日を表示する
                        'suffix' => ''
                    ], false);
                ?>
            <?php endif; ?>
        </div>

        <?php
            // 新しい PostCard コンポーネントを呼び出す
            $current_depth = Dy::trace_count('matrix_count', +1);
            echo \Kx\Component\PostCard::render($item['id'], 'standard');
            $current_depth = Dy::trace_count('matrix_count', -1);
        ?>

    <?php endforeach; ?>

    <?php foreach ($matrix['virtual_descendants'] as $virtual_descendant): ?>

        <?php
            // 1. 親となるパスを正しく取得する
            // $matrix['items'] の中から適当な一つのアイテムの親パスを借りるか、
            // もし $matrix 自体が search_path を持っていればそれを使う
            $parent_path = '';
            if (!empty($matrix['items'])) {
                $p_idx = Dy::get_path_index($matrix['post_id']);
                // 親のパス（末尾に≫がついているはず）を取得
                $parent_path = $p_idx['full'] ?? '';
            }

            // 2. もし上の方法で取れない場合の安全策
            if (empty($parent_path)) {
                // 現在表示している仮想ノードのパスをレジストリから取得
                $current_node = Dy::get('current_virtual_node');
                $parent_path = ($current_node['full_path'] ?? '') ;
            }

            // 3. パスを結合（物理パスが入らないように注意）
            $v_full_path = $parent_path .'≫'. $virtual_descendant;

            // 4. URLを生成（home_url('/0/...') だと重複するので home_url('/hierarchy/...') にする）
            $v_url = home_url('/hierarchy/' . urlencode($v_full_path));

            echo \Kx\Core\OutlineManager::add_from_loop(get_the_ID(), esc_html($virtual_descendant.'【Virtual】'));
        ?>
        <div class="kx-sort-header-bar <?= $bar_class ?>" style="<?= $traits ?>">
        </div>

        <div class="kx-virtual-link-item" style="background: hsl(0 , 0% , 15%); padding: 10px; margin-bottom: 5px; border-radius: 0 0 10px 10px;">
            <a href="<?php echo $v_url; ?>" style="color: hsl(var(--kx-hue,0),50%,75%); text-decoration: none; display: flex; align-items: center;">
                <span style="margin-right: 10px;">📁</span>
                <div>
                    <span style="font-weight: bold;">Virtual：<?php echo esc_html($virtual_descendant); ?></span>
                    <div style="font-size: 0.8em; color: #aaa;"><?php echo esc_html($v_full_path); ?></div>
                </div>
            </a>
        </div>

    <?php endforeach; ?>

</div>
<style>
.kx-sort-header-bar {
    /* レイアウト設定 */
    margin-bottom: 0;
    display: flex;
    box-sizing: border-box;
    line-height: 1em;

    align-items: center;
    width: 100%;
    padding: 0px 12px;         /* 縦幅は薄く設定 */
    margin-top: 0px;
    margin-bottom : 0px;

    /* 背景と形状：上半分だけ丸み (Top-Left, Top-Right) */
    background-color:hsla(var(--kx-hue,0),var(--kx-sat,50%),var(--kx-lum,50%),1);
    border-radius: 8px 8px 0 0;

    /* 質感の向上：細い上境界線と微かな影 */
    border-top: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 -1px 2px rgba(0, 0, 0, 0.05);

    /* 文字装飾 */
    font-size: 11pt;
    font-weight: 600;
    color: #fff;               /* 基本は白、背景が明るい場合は要調整 */
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
    /*letter-spacing: 0.05em;*/
    overflow: hidden;
}

.kx-sort-header-bar .bar-content {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    opacity: 0.9;
}

/* 下に続くリンクエリア等がある場合の繋ぎ目調整 */
.kx-sort-header-bar + .item-link {
    display: block;
    border-top: none;
    padding-top: 0px;
}



/* 【目立たせる】新しい日のバー */
.kx-sort-header-bar.is-new-day {
    background-color: hsla(var(--kx-hue,0), 100%, 40%, 1);
    border-top: 2px solid hsla(var(--kx-hue,0), 100%, 80%, 1); /* 左端にアクセント */
    margin-top: 0px; /* 日付が変わるときは少し余白を開ける */
    font-size: 15pt;
}

/* 【目立たせない】同じ日のバー */
.kx-sort-header-bar.is-same-day {
    background-color: hsla(var(--kx-hue,0), 20%, 40%, 1);
    /*color: hsla(var(--kx-hue,0), var(--kx-sat,80%), var(--kx-lum,20%), 0.8);*/
    text-shadow: none;
    font-size: 11pt;
    border: 2px solid hsla(var(--kx-hue,0), var(--kx-sat,80%), var(--kx-lum,20%), 1);
}
.bar-main-title{
    margin-bottom: 3em;
}
</style>