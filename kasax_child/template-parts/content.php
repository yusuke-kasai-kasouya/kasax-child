<?php
/**
 * 子テーマ専用 template-parts/content.php
 * 2026-01-05 修正
 *
 * @package kasax_child
 */
?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
    <header class="entry-header">
        <?php
            if ( is_single() ) :
                //include __DIR__ . '/../lib/html/h1.php';
				//\Kx\Utils\KxTemplate::get('layout/page-title', [], true);
                Kx\Visual\TitleRenderer::render( get_the_ID());
            else :
                the_title( '<h3 class="entry-title"><a href="' . esc_url( get_permalink() ) . '" rel="bookmark">', '</a></h3>' );
            endif;

            if ( 'post' === get_post_type() ) :
        ?>
            <div class="entry-meta">
                <?php //kasax_posted_on(); ?>
            </div>
        <?php endif; ?>
    </header>

    <script>
        setTimeout(function() {
        var target = document.querySelector('.__js_show_content');
        if(target) target.className = 'entry-content';
        }, 10000);
    </script>

    <div class="entry-content __js_show_content">
        <?php
        if ( is_single() ) :
        //echo kx_add_content( get_the_ID() );
        echo '<div class="_kx_">';
        the_content();
        echo '</div>';

        wp_link_pages( array(
            'before' => '<div class="page-links">' . esc_html__( 'Pages:', 'kasax' ),
            'after'  => '</div>',
        ) );
        else :
        the_excerpt();
        echo '<div class="content_php"><hr class="hr003"><div align="right">';
        echo the_modified_date('Y/m/d G:i') . ' - ' . the_category(', ') . ' - ' . the_tags();
        echo '</div></div>';
        endif;
        ?>
        <hr class="__content_end">
        <div class="__absolute_displayArea displayArea __background_normal"></div>
        <div class="__absolute_displayArea_right displayArea_right __background_normal"></div>
    </div>

    <footer class="entry-footer">
        <div class="__js_show" style="font-size: 0.8em; margin-top: 20px;min-height:800px;">

        </div>
    </footer>
</article>