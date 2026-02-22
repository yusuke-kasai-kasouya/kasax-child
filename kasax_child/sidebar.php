<?php
/**
 * 子テーマ専用 sidebar.php
 * 2026-01-05 改訂
 * 親テーマの標準サイドバー構造の直前に、独自関数 kx_html_side() による
 * ナレッジベース専用コンテンツを注入します。
 *
 * @package kasax_child
 */

// ☆改良点：サイドバーの最上部にシステム独自のHTML（メニューや動的リンク等）を出力
echo \Kx\Utils\Toolbox::html_side();

if ( ! is_active_sidebar( 'sidebar-1' ) ) {
	return;
}
?>

<aside id="secondary" class="widget-area">
	<?php dynamic_sidebar( 'sidebar-1' ); ?>
</aside>

