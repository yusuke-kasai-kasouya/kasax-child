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

            <?php // --- 1. エラー情報出力 --- ?>
            <div>
                <?php
                if( !empty( $_SESSION[ 'kxError' ][ 'count' ] )) {
                echo 'Error-COUNT：' . $_SESSION[ 'kxError' ][ 'count' ] . '<br>';
                foreach( $_SESSION[ 'kxError' ][ 'type' ] as $i => $value ) {
                    echo ($i+1) . '：' . $value . '<br>';
                }
                } else {
                echo 'Error-NO';
                }
                ?>
            </div>
            <hr>

            <?php // --- 2. Color情報出力 --- ?>
            <div>
                <?php
                if( is_array( $_SESSION[ 'color' ] ?? null ) ) {
                    echo '<div>Search：LIST-color</div>';
                    $i2 = 0;
                    foreach ( $_SESSION[ 'color' ] as $key => $_arr) {
                        $i2 += $_SESSION[ 'color' ][$key]['count'];
                    }
                    echo 'カウント'. count( $_SESSION[ 'color' ] ) . ' / ' . $i2 . '<hr>';
                }
                ?>
            </div>

            <?php // --- 3. Color2情報出力 --- ?>
            <div>
                <?php
                if( is_array( $_SESSION[ 'color2' ] ?? null ) ) {
                echo '<div>Search：LIST-color2</div>';
                $i = 0; $i2 = 0;
                foreach ( $_SESSION[ 'color2' ] as $key => $_v) {
                    $i++;
                    echo '<div>'.$i.'：'.$key.'：'.$_SESSION[ 'color2' ][$key]['count'].'件</div>';
                    $i2 += $_SESSION[ 'color2' ][$key]['count'];
                }
                echo '<br>作業数'.$i2;
                }
                ?>
            </div>

            <?php // --- 4. メモリ・検索リスト出力 --- ?>
            <?php if( !empty( $_SESSION[ 'kx_memory' ] ) && is_array( $_SESSION[ 'kx_memory' ] ) ): ?>
                <hr>
                <div>Search：LIST</div>
                <table>
                <?php $i=0; foreach ( $_SESSION[ 'kx_memory' ] as $key => $_v ): $i++; ?>
                    <?php preg_match( '/\d+/' , $key , $matches ); ?>
                    <tr>
                    <td width="20"><?php echo $i; ?></td>
                    <td width="100"><?php printf( "該当 %'5d 件", count( $_v )); ?></td>
                    <td width="150"><?php echo $key; ?></td>
                    <td width="80">検索<?php echo $_SESSION['kx_memory_count'][$key] ?? 0; ?>回</td>
                    <td><?php echo get_cat_name( $matches[0] ?? '' ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </table>
                <?php if( !empty( $_SESSION['kx_memory_count']['attention'] ) ): ?>
                <br>■注意：<?php echo $_SESSION['kx_memory_count']['attention']; ?>
                <?php endif; ?>
                <br>総表示数<?php echo $_SESSION['kx_memory_count']['all'] ?? 0; ?>
                <hr>
            <?php endif; ?>

        </div>
    </footer>
</article>