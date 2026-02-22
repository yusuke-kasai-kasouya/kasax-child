<?php
/**
 * 子テーマ専用 header.php
 * 2026-01-05 改訂
 * * 修正内容：
 * 1. site-brandingを削除し、トップバー（kx_header_bar）の上の隙間を解消。
 * 2. skip-linkのアンカーを旧環境に合わせ #content に修正。
 * 3. 2025年までに廃止された古い関数・CSS呼び出しを除去。
 *
 * @package kasax_child
 */

?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">

    <?php // デバイスサイズに応じたビューポートの動的制御 ?>
    <?php if(wp_is_mobile()) :?>
        <script type="text/javascript">
        if( screen.width <= 768 ){
            document.write('<meta name="viewport" content="width=device-width, initial-scale=1.0" />');
        } else {
            document.write('<meta name="viewport" content="width=1000" />');
        }
        </script>
    <?php else : ?>
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <?php endif ;?>

    <link rel="profile" href="https://gmpg.org/xfn/11">
    <?php wp_head(); ?>
</head>
<?php
    $colormgr = Kx\Core\DynamicRegistry::get_color_mgr(get_the_ID());
    $body_default = $colormgr['style_array']['body_default'] ?? '';
?>

<body <?php body_class(); ?> style="<?= $body_default ?>" >
<?php wp_body_open(); ?>

<?php // ページ読み込み演出用のローダー要素 ?>
<div id="loader"></div>

<div id="page" class="site">
  <a class="skip-link screen-reader-text" href="#content"><?php esc_html_e( 'Skip to content', 'kasax' ); ?></a>

  <header id="masthead" class="site-header" role="banner">
    <nav id="site-navigation" class="main-navigation" role="navigation">

        <?php // ━━━ここにサイトロゴ等が混ざると隙間ができるため、いきなり独自バーを出力━━━ ?>
        <?php echo \Kx\Utils\Toolbox::header_bar(); ?>

        <button class="menu-toggle" aria-controls="primary-menu" aria-expanded="false">
            <?php esc_html_e( 'Primary Menu', 'kasax' ); ?>
        </button>

        <?php
        wp_nav_menu(
            array(
            'theme_location' => 'menu-1',
            'menu_id'        => 'primary-menu',
            )
        );
        ?>
    </nav></header>
    <div id="content" class="site-content">