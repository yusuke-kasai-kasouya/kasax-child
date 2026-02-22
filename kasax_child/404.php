<?php
/**
 * 子テーマ専用 404.php
 * 親テーマの最新構造を継承しつつ、ナレッジベース運用に最適化。
 * 2026-01-05
 *
 * @package kasax_child
 */

get_header();
?>

  <main id="primary" class="site-main">

    <section class="error-404 not-found">
      <header class="page-header">
        <h1 class="page-title"><?php esc_html_e( 'Oops! That page can&rsquo;t be found.', 'kasax' ); ?></h1>
      </header><div class="page-content">
        <p><?php esc_html_e( 'It looks like nothing was found at this location. Maybe try one of the links below or a search?', 'kasax' ); ?></p>

          <?php
          get_search_form();

          // ☆改良点：表示件数をデフォルトから30件に拡張。
          // 1.4万件のナレッジ群から最新の動向を提示し、エラーページをインデックスとして再利用します。
          the_widget( 'WP_Widget_Recent_Posts' , ['number' => 30] );
          ?>

          <?php /* // ☆改良点：以下の標準ウィジェットは、ナレッジベースのノイズを減らすため意図的に無効化しています。
          // 必要に応じてコメントアウトを解除して有効化してください。

          <div class="widget widget_categories">
            <h2 class="widget-title"><?php esc_html_e( 'Most Used Categories', 'kasax' ); ?></h2>
            <ul>
              <?php
              wp_list_categories(
                array(
                  'orderby'    => 'count',
                  'order'      => 'DESC',
                  'show_count' => 1,
                  'title_li'   => '',
                  'number'     => 10,
                )
              );
              ?>
            </ul>
          </div>

          $kasax_archive_content = '<p>' . sprintf( esc_html__( 'Try looking in the monthly archives. %1$s', 'kasax' ), convert_smilies( ':)' ) ) . '</p>';
          the_widget( 'WP_Widget_Archives', 'dropdown=1', "after_title=</h2>$kasax_archive_content" );

          the_widget( 'WP_Widget_Tag_Cloud' );
          */ ?>

      </div></section></main><?php
get_footer();