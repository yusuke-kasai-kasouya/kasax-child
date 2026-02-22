<?php
/**
 * 子テーマ専用 single.php
 * 2026-01-05 再改訂
 * レイアウト崩れ（サイドバー消失）を防ぐため、旧来の content-area 構造を維持。
 *
 * @package kasax_child
 */

get_header();
?>

  <?php // サイドバーとの並び順を制御する外枠 div を復活させる ?>
  <div id="primary" class="content-area">

    <div class="__tyousei">

      <main id="main" class="site-main" role="main">
        <?php
        while ( have_posts() ) :
          the_post();

          /* 投稿フォーマット（get_post_format）に基づいてコンテンツを表示 */
          get_template_part( 'template-parts/content', get_post_format() );

          /* 標準の前後ナビゲーションは非表示（2020-12-06～） */
          // the_post_navigation();

          /* コメントテンプレート（ナレッジメモ）をシングルページでも表示 */
          if ( comments_open() || get_comments_number() ) :
            comments_template();
          endif;

        endwhile; // End of the loop.
        ?>
      </main></div></div><?php
get_sidebar();
get_footer();