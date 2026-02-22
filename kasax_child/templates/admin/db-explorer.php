<?php
/**
 * [Path]: templates/admin/db-explorer.php
 */
use Kx\Admin\KxListTable;

// タブ制御
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'kx0';
$table_map = [
    'kx0'       => 'wp_kx_0',
    'kx1'       => 'wp_kx_1',
    'hierarchy' => 'wp_kx_hierarchy',
    'shared'    => 'wp_kx_shared_title', //
    'ai_meta'   => 'wp_kx_ai_metadata'
];
$target_table = $table_map[$active_tab] ?? 'wp_kx_hierarchy';

// リストテーブルの準備
$kx_table = new KxListTable($target_table);
$kx_table->prepare_items();
?>

<div class="wrap">
    <style>
        /* IDカラム：桁数が少ないので幅を絞る */
        .column-id, .column-post_id {
            width: 80px;
        }
        /* 日付カラム：19文字（Y/m/d H:i:s）に最適化 */
        .column-time, .column-wp_updated_at {
            width: 180px;
            white-space: nowrap; /* 折返し防止 */
        }
        /* 種別カラム：短い単語のみ */
        .column-type {
            width: 300px;
        }
        /* 仮想フラグなど */
        .column-is_virtual {
            width: 60px;
            text-align: center;
        }
        /* タイトルやパスは可変（残り全部）にするため指定しない */
    </style>


    <h1 class="wp-heading-inline">Kx Database Explorer</h1>
    <p class="description">物語の構造データ（Hierarchy）とインデックスデータ（kx_0/kx_1）を直接参照します。</p>
    <hr class="wp-header-end">

    <h2 class="nav-tab-wrapper">
        <a href="?page=db-explorer&tab=kx0" class="nav-tab <?php echo $active_tab == 'kx0' ? 'nav-tab-active' : ''; ?>">基礎インデックス (kx_0)</a>
        <a href="?page=db-explorer&tab=kx1" class="nav-tab <?php echo $active_tab == 'kx1' ? 'nav-tab-active' : ''; ?>">インデックス/JSON (kx_1)</a>
        <a href="?page=db-explorer&tab=hierarchy" class="nav-tab <?php echo $active_tab == 'hierarchy' ? 'nav-tab-active' : ''; ?>">階層構造 (Hierarchy)</a>
        <a href="?page=db-explorer&tab=shared" class="nav-tab <?php echo $active_tab == 'shared' ? 'nav-tab-active' : ''; ?>">概念統合 (Shared)</a>
        <a href="?page=db-explorer&tab=ai_meta" class="nav-tab <?php echo $active_tab == 'ai_meta' ? 'nav-tab-active' : ''; ?>">AI分析データ</a>
    </h2>

    <div id="poststuff">
        <div id="post-body" class="metabox-holder">
            <form method="get">
                <input type="hidden" name="page" value="db-explorer" />
                <input type="hidden" name="tab" value="<?php echo esc_attr($active_tab); ?>" />
                <?php
                $kx_table->display();
                ?>
            </form>
        </div>
    </div>
</div>
