<?php
/**
 * 子テーマ専用 search.php
 * 2026-01-05 改訂
 * 親テーマの最新構造をベースに、検索クエリの解析表示とヒット数セッション保存を統合。
 *
 * @package kasax_child
 */

get_header();
?>

  <?php // ☆改良点：サイドバー干渉による横スクロールを抑制 ?>
  <section id="primary" class="content-area" style="overflow: hidden;">
    <main id="main" class="site-main">

    <?php
        // ☆改良点：検索結果ページ上部にショートコードによる補助ナビゲーションを配置
        echo \Kx\Utils\Toolbox::render_search_form([]);
    ?>

    <?php if ( have_posts() ) : ?>

      <header class="page-header">
        <h2 class="page-title">
          <?php printf( esc_html__( 'Search Results for: %s', 'kasax' ), '<span>' . get_search_query() . '</span>' ); ?>
        </h2>


      </header><?php
      /* Start the Loop */
      while ( have_posts() ) :
        the_post();
        get_template_part( 'template-parts/content', 'search' );
      endwhile;

      the_posts_navigation();

    else :
      get_template_part( 'template-parts/content', 'none' );
    endif;
    ?>

    <?php
    // ☆改良点：ページ送り（アーカイブ等との整合性確保）
    if ( ! is_single() ) :
      echo "<hr>";
      echo previous_posts_link('＜＜＜Prevue');
      echo "&nbsp;&nbsp;&nbsp;";
      echo next_posts_link('Next＞＞＞');
    endif;
    ?>

    </main></section><?php
get_sidebar();
get_footer();