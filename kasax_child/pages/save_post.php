<?php
/**
 * [Path]: pages/save_post.php
 * Update / Insert 兼用ハンドラ（3分割タイトル対応）
 */

require_once( dirname(__DIR__) . '/../../../wp-load.php' );

if ( !current_user_can('edit_posts') ) {
    wp_die('権限なし');
}



// 1. データの受け取り
$post_id= isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
$edit_id= isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;
$content = $_POST['post_content'] ?? '';

// --- タイトルの再構成ロジック ---

$p_dir  = isset($_POST['post_title_parent_dir']) ? trim($_POST['post_title_parent_dir']) : '';
$t_slug = isset($_POST['post_title_time_slug'])   ? trim($_POST['post_title_time_slug'])   : '';
$at_nm  = isset($_POST['post_title_at'])          ? trim($_POST['post_title_at'])          : '';





// 1. 親階層の正規化（末尾の ≫ を除去して重複防止）
$p_dir = str_replace(['＞', '≫＠'], ['≫', '≫'], $p_dir);
//$p_dir = rtrim($p_dir, '≫');
$p_dir = preg_replace('/≫+$/u', '', $p_dir); // これが安全

// 2. 結合処理
$final_title = $p_dir;

// タイムスラグの処理
if ($t_slug !== '') {
    // 親階層があれば ≫ で繋ぐ
    $final_title .= ($final_title !== '' ? '≫' : '') . ltrim($t_slug, '≫');
}



// 本体タイトルの処理
if ($at_nm !== '') {
    // 先頭にある「＠」や「≫」を正規表現で安全に除去（マルチバイト対応）
    $at_nm_clean = preg_replace('/^[＠≫]+/u', '', $at_nm);

    if ($final_title !== '') {
        $separator = ($t_slug !== '') ? '＠' : '≫';
        $final_title .= $separator . $at_nm_clean;
    } else {
        $final_title = $at_nm_clean;
    }
}


// 最終チェック：全体が空ならデフォルト値を設定（念のため）
if ($final_title === '') {
    $final_title = '(無題)';
}

//echo $final_title;


// --- 3. 実行用データの組み立て ---
$post_data = [
    'post_title'     => $final_title,
    'post_content'   => $content,
    'comment_status' => 'closed',
    'post_status'    => 'publish',
];





if ( $edit_id > 0 ) {
    $post_data['ID'] = $edit_id;
    $result = wp_update_post($post_data, true);
    $mode = "UPDATED";
} else {
    $result = wp_insert_post($post_data, true);
    $mode = "INSERTED";
}

$is_error = is_wp_error($result);

//var_dump($is_error);

$display_content = preg_replace("/\r\n|\r|\n/", " ", trim(strip_tags($content)));


?>
<?php if ($_POST['mode'] === 'header'  ): ?>
    <script>
        (function() {
            try {
                // 1. まずはモーダルを閉じる（親のjQueryが使えるなら）
                if (window.parent && window.parent.jQuery) {
                    window.parent.jQuery('.kx-inline-editor').hide();
                    window.parent.jQuery('#loader').show();
                }

                // 2. 最も外側のメイン画面（top）をリロードする
                window.top.location.reload();

            } catch (e) {
                // 安全装置：何があっても一番外側をリロード
                window.top.location.reload();
            }
        })();
    </script>

<?php elseif ( !$is_error ): ?>
    <script>
        (function() {
            try {
                var parentWin = window.parent;
                var $ = parentWin.jQuery;
                var postId = "<?= $post_id?>";
                var editId = "<?= $edit_id?>";

                if ($) {
                    var $content = $('.kx-target-post-content-' + postId, parentWin.document);
                    var $loader = $('#loader', parentWin.document); // 親のLoaderを取得

                    // ガード句：ターゲットが見つからない場合に叫ばせる
                    if ($content.length === 0) {
                        console.error("更新対象が見つかりません！ IDを確認してください: ", postId);

                        // 変数 $loader が定義済みならそれを使う、未定義ならその場で取得して hide
                        var $actualLoader = (typeof $loader !== 'undefined' && $loader.length > 0)
                                            ? $loader
                                            : $('#loader', parentWin.document);

                        $actualLoader.fadeOut(300); // 存在すれば消える、しなければ何も起きない（安全）

                        return;
                    }

                    if ($content.length > 0) {

                        // ローダーを表示して更新開始
                        $loader.show();

                        // 1. 親ページのカードを更新
                        $content.stop(true, true).fadeOut(200, function() {
                            $.ajax({
                                type: 'post',
                                url: 'wp-content/themes/kasax_child/pages/fetch_excerpt.php',
                                //url:'fetch_excerpt.php',
                                data: { id: editId },
                                success: function(data) {
                                    $content.html(data).fadeIn(100);

                                    $content.css({
                                        'border-left': '1px solid hsl(120, 100%, 50%)',
                                        'transition': 'border 0.3s ease'
                                    });

                                    // 1分後にボーダーを消すタイマー
                                    setTimeout(function() {
                                        $content.css('border', 'none');
                                    }, 60000); // 60秒

                                    // 2. モーダルを閉じる
                                    setTimeout(function(){
                                        if (window.frameElement) {
                                            var modal = window.frameElement.closest('.kx-inline-editor');

                                            if (modal) {

                                                // ローダーをフェードアウト
                                                $loader.fadeOut(300);

                                                modal.style.display = 'none';

                                                // 通常の記事リスト更新時は iframe 内だけを戻す（既存の挙動）
                                                window.location.href = 'edit_post.php?edit_id=' + editId + '&id=' + postId;

                                            }
                                        }
                                    }, 300);
                                }
                            });
                        });
                    }
                }
            } catch (e) {
                console.error(e);
            }
        })();
    </script>
<?php endif; ?>