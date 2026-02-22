<?php
/**
 * 子テーマ専用 page.php
 * 2026-01-05 再改訂
 * レイアウト維持のため、旧来の content-area (div) 構造を完全に復元。
 *
 * @package kasax_child
 */

get_header();
?>

  <?php // CSSとJSの制御に必須の div 構造を復活させる ?>
  <div id="primary" class="content-area __js_show">

    <main id="main" class="site-main" role="main">

      <?php
      while ( have_posts() ) :
        the_post();

        get_template_part( 'template-parts/content', 'page' );

        // コメント（ナレッジメモ）表示
        if ( comments_open() || get_comments_number() ) :
          comments_template();
        endif;

      endwhile; // End of the loop.
      ?>

    </main></div><?php
get_sidebar();
get_footer();