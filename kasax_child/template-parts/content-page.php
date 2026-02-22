<?php
/**
 * 子テーマ専用 template-parts/content-page.php
 * 2026-01-05 更新
 * 親テーマ構造を維持しつつ、著者ID表示・外部H1制御・フッターサイドバーを統合。
 *
 * @package kasax_child
 */

// ☆改良点：管理用著者IDの表示（2023-08-30 継承）
// ログインユーザーや管理者にのみ特定の情報を出す際などに使用される独自関数

echo \Kx\Utils\Toolbox::updateAuthorIdByPostType();

?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="entry-header">
		<?php
			// ☆改良点：タイトル表示を外部ライブラリ（lib/html/h1.php）で制御（170924 継承）
			Kx\Visual\TitleRenderer::render( get_the_ID());
		?>
	</header><?php
	// 親テーマのサムネイル機能を継承
	//kasax_post_thumbnail();
	?>

	<div class="entry-content">
		<?php
		the_content();

		wp_link_pages(
			array(
				'before' => '<div class="page-links">' . esc_html__( 'Pages:', 'kasax' ),
				'after'  => '</div>',
			)
		);
		?>
	</div><?php
	// ☆改良点：固定ページ専用のフッターサイドバー・エリアを追加
	if ( is_active_sidebar( 'footer' ) ) : ?>
		<div class="entry-footer-sidebar">
			<?php dynamic_sidebar( 'footer' ); ?>
		</div>
	<?php endif; ?>
</article>