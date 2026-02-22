<?php
/**
 * 子テーマ専用 template-parts/content-search.php
 * 2026-01-05 更新
 * タイトル置換ロジック、表示順カウント、編集リンク、および視覚的装飾を統合。
 *
 * @package kasax_child
 */

?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

  <?php // ☆改良点：検索結果を識別しやすくするための背景装飾と角丸レイアウト ?>
  <div style="background-color: hsla(180, 100%, 50%, .033); border-radius: 20px; padding-bottom: 10px;">

    <header class="entry-header">
      <div class="__padding_left20"><br>

        <?php
          echo '<a href="' . esc_url( get_permalink() ) . '">' . get_the_title() . '</a>';
        ?>

      </div>

      <?php
      // ☆改良点：置換タイトルとは別に、標準タイトルをh4（補助的）として表示
      the_title( sprintf( '<h4 class="entry-title" style="font-size: 0.9em; opacity: 0.7;"><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a></h4>' );
      ?>

      <div class="__text_right __edit_color __padding_right20" style="font-size: 0.8em;">
        <?php
        // 管理用の編集リンクと、表示順序のカウント
        echo '<a href="' . get_edit_post_link() . '">edit</a> : ';

        // KxDyクラスを使用した表示カウンタ（現在のページ内で何番目の記事か）
        KxDy::trace_count('kxx_content_count', 1);
        echo KxDy::get('trace')['kxx_content_count'] ?? 0;
        ?>
      </div>

      <?php if ( 'post' === get_post_type() ) : ?>
        <div class="entry-meta">
          <?php //kasax_posted_on(); ?>
        </div><?php endif; ?>
    </header><div class="entry-summary" style="padding: 0 20px;">
      <?php the_excerpt(); ?>
    </div></div><footer class="entry-footer">
    <?php //kasax_entry_footer(); ?>
  </footer></article>