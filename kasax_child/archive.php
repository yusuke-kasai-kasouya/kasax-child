<?php
/**
 * 子テーマ専用 archive.php
 * 親テーマの最新構造を継承し、ヒット数表示・検索コンテキスト管理を統合。
 * 2026-01-05
 *
 * @package kasax_child
 */

get_header();
?>

  <?php // ☆改良点：サイドバー等の干渉による不要なスクロールを抑制するラッパー ?>
  <section style="overflow: hidden;">

    <main id="primary" class="site-main">

      <?php if ( have_posts() ) : ?>

        <header class="page-header">
          <?php
          // ☆改良点：タイトルのセマンティクス調整（h1からh2へ）
          the_archive_title( '<h2 class="page-title">', '</h2>' );
          the_archive_description( '<div class="archive-description">', '</div>' );
          ?>

          <?php // ☆改良点：1.4万件規模のDBにおいて現在の絞り込み結果数を可視化 ?>
          <p><?php echo $wp_query->found_posts; ?>件</p>

          <?php // ☆改良点：検索ヒット数をセッションに保持（他ページやシステムでの利用を想定） ?>
          <?php $_SESSION[ 'kensaku' ] = $wp_query->found_posts; ?>
        </header><?php
        /* Start the Loop */
        while ( have_posts() ) :
          the_post();

          /*
           * ☆改良点：親テーマの get_post_type() による自動判定を上書きし、
           * 全てのアーカイブ表示を 'search' 形式（リスト形式等）に統一。
           * 羅列（raretu）エンジンとの視覚的整合性を確保します。
           */
          get_template_part( 'template-parts/content', 'search' );

        endwhile;

        // ☆改良点：標準ナビゲーションに加え、独自の「Prevue/Next」リンクを明示的に配置
        the_posts_navigation();

        if ( ! is_single() ) :
          echo "<hr class='hr002'>";
          echo previous_posts_link('＜＜＜Prevue');
          echo "&nbsp;&nbsp;&nbsp;";
          echo next_posts_link('Next＞＞＞');
        endif;

      else :

        get_template_part( 'template-parts/content', 'none' );

      endif;
      ?>

    </main></section><?php
get_sidebar();
get_footer();