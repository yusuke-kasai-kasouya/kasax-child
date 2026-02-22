<?php
/**
 * [Path]: inc\utils\class-kx-wp-tweak.php
 * [Role]: システムの動作に直接関わらない、WPの挙動調整（クッキー、リビジョン、管理画面UI）を管理する。
 */

namespace Kx\Utils;

class WpTweak {

    /**
     * リビジョンの削除。
     *
     */
    public static function delete_old_revisions() {
        global $wpdb;

        // リビジョンの保存期間を設定します。
        $days = 60;

        // リビジョンの保存期間を過ぎたリビジョンを削除します。
        $wpdb->query("
            DELETE FROM {$wpdb->prefix}posts
            WHERE post_type = 'revision'
            AND post_date < CURRENT_TIMESTAMP - INTERVAL $days DAY
        ");
    }

    /**
     * 非表示「～更新してください」
     *
     * @return void
     */
    public static function update_nag_hide() {
        remove_action( 'admin_notices', 'update_nag', 3 );
    remove_action( 'admin_notices', 'maintenance_nag', 10 );
    }

    /**
     * クッキーの有効期限を変更
     */
    public static function my_auth_cookie_expiration( $expirein)
    {
        return 315360000;
        // return 86400;    // 1日間有効  (秒数で指定)
        // return 15768000; // 半年間有効 (秒数で指定)
        // return 31536000; // 1年間有効  (秒数で指定)
    }
}