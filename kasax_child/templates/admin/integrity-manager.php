<?php
/**
 * [Path]: templates/admin/integrity-manager.php
 */
if (!defined('ABSPATH')) exit;
?>

<div class="wrap">
    <h1 class="wp-heading-inline">System Data Integrity</h1>
    <p>データベースの再構築とインデックスの同期を行います。実行中はサーバーに負荷がかかるため、利用者の少ない時間帯を推奨します。</p>
    <hr class="wp-header-end">

    <div style="margin-top: 20px;">
        <?php
        // Toolboxから返されたパネルHTMLをそのまま出力
        echo $panel_html;
        ?>
    </div>
</div>