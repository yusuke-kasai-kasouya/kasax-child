<?php
/**
 * 子テーマ専用 comments.php
 * 親テーマの構成をベースに、ナレッジベース用コメント変換ロジックを統合。
 * 2026-01-05
 *
 * @package kasax_child
 */
	return;

if ( post_password_required() ) {
	return;
}
?>

<div id="comments" class="comments-area">

	<?php if ( have_comments() ) : ?>
		<h2 class="comments-title">
			<?php
			$kasax_comment_count = get_comments_number();
			if ( '1' === $kasax_comment_count ) {
				printf(
					esc_html__( 'One thought on &ldquo;%1$s&rdquo;', 'kasax' ),
					'<span>' . wp_kses_post( get_the_title() ) . '</span>'
				);
			} else {
				printf(
					esc_html( _nx( '%1$s thought on &ldquo;%2$s&rdquo;', '%1$s thoughts on &ldquo;%2$s&rdquo;', $kasax_comment_count, 'comments title', 'kasax' ) ),
					number_format_i18n( $kasax_comment_count ),
					'<span>' . wp_kses_post( get_the_title() ) . '</span>'
				);
			}
			?>
		</h2>

		<?php the_comments_navigation(); ?>

		<ol class="comment-list">
			<?php
			// ☆改良点：標準の wp_list_comments を使用せず、ナレッジ管理用に直接ループを展開
			// 2024-02-14：コメントを「編集可能なメモ」として扱うための独自実装
			$_comments = get_comments( array( 'post_id' => get_the_ID(), 'order' => 'ASC' ) );

			foreach ( $_comments as $value ) :
				echo '<div class="kx-comment-item" style="margin-bottom: 20px; border-bottom: 1px dotted #ccc;">';

				// ☆改良点：管理画面へ飛ばずとも即座に修正できるよう「編集」リンクを配置
				echo '<a href="' . esc_url( get_edit_comment_link( $value->comment_ID ) ) . '" class="comment-edit-link">編集</a>';
				echo '<br>';

				// ☆改良点?
				echo $value->comment_content ;

				echo '</div>';
			endforeach;
			?>
		</ol>

		<?php
		the_comments_navigation();

		if ( ! comments_open() ) :
			?>
			<p class="no-comments"><?php esc_html_e( 'Comments are closed.', 'kasax' ); ?></p>
			<?php
		endif;

	endif; // have_comments()

	// ☆改良点：コメントフォームをナレッジ入力に特化してカスタマイズ
	$args = array(
		'title_reply'          => get_the_title(), // 記事タイトルをリプライタイトルに
		'title_reply_to'       => '%s に返信する',
		'cancel_reply_link'    => '取り消す',
		'label_submit'         => '送信する',
		'id_form'              => 'kx_comment_form',
		'id_submit'            => 'kx_submit',
		'comment_field'        => '<p><textarea id="comment" name="comment" cols="45" rows="8" aria-required="true"></textarea></p>',
		'must_log_in'          => '',
		'logged_in_as'         => '',
		'comment_notes_before' => '',
		'comment_notes_after'  => '',
		'fields'               => array(
			'author' => '', // 名前入力を省略
			'email'  => '', // メール入力を省略
			'url'    => '', // URL入力を省略
		),
	);

	comment_form( $args );
	?>

</div>