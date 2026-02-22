<?php
/**
 * [Path]: pages/fetch_excerpt.php
 */

define('SHORTINIT', false); // trueにすると軽くなるが関数が制限されるため今回はfalseのまま
define('WP_USE_THEMES', false);
require_once( dirname(__DIR__) . '/../../../wp-load.php' );

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($id > 0) {
    $post = get_post($id);
    if ($post) {
        // p_hyouji.php の実績ロジックをそのまま使用
        setup_postdata($post);

        // 抜粋表示（moreなし）の状態を取得
        $content = apply_filters('the_content', get_the_content(""));

        echo $content;

        wp_reset_postdata();
    }
}
exit;