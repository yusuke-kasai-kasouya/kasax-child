<?php
/**
 * [Path]: inc\core-loader.php
 */


use Kx\Core\KxDirector as kx;
//use Kx\Utils\Toolbox;
use Kx\Database\DB;

use Kx\Core\SaveManager;    // 保存・同期管理
use Kx\Core\AjaxHandler;    // Ajaxリクエスト処理
use Kx\Core\ContentFilter;  // 表示フィルタ・加工





// --- 0. ---
// Utils レイヤー：補助・調整ツール
use Kx\Utils\WpTweak;       // WP挙動調整・雑務



//DB作成
add_action('after_switch_theme', [DB::class,'create_custom_tables']);

//サイドバーの起動。
\Kx\Visual\SideBar::init();

//ショートコード登録。
add_action('init', [kx::class,'register_shortcodes']);


// --- 1. WP設定・最適化・雑務 (WpTweak) ---
// リビジョンの削除
add_action('init', [WpTweak::class, 'delete_old_revisions']);
// wp関連の非表示
add_action('admin_init', [WpTweak::class, 'update_nag_hide']);
// クッキーの有効期限を変更
add_filter('auth_cookie_expiration', [WpTweak::class, 'my_auth_cookie_expiration']);


// --- 2. 保存・更新・整合性管理 (SaveManager) ---
// 条件付き保存（仮想ポストリクエスト）
add_action('init', [SaveManager::class, 'handleVirtualPostRequest']);

// 保存フック群 (優先度8)
$save_hooks = ['save_post', 'wp_trash_post', 'publish_to_trash', 'untrash_post', 'edit_post'];
foreach ($save_hooks as $hook) {
    add_action($hook, [SaveManager::class, 'save_hook_8'], 8);
}

// 保存フック (優先度9)
add_action('save_post', [SaveManager::class, 'save_hook_9'], 9);

// 保存前のデータ加工
add_filter('wp_insert_post_data', [SaveManager::class, 'insert_post_data_content'], 10, 2);

// 削除フック
add_action('publish_to_trash', [SaveManager::class, 'trash_post_include']);


// --- 3. 表示・フロントエンド加工 (ContentFilter) ---
// ContextManager発火
add_action('template_redirect', [ContentFilter::class, 'ContextManager_template_redirect']);

// コンテンツ介入 (the_content)
add_filter('the_content', [ContentFilter::class, 'the_content_8'], 8);
add_filter('the_content', [ContentFilter::class, 'the_content_9'], 9);

// ブラウザーのタブのタイトルを変更
add_filter('pre_get_document_title', [ContentFilter::class, 'browser_title']);

// 検索順位調整 (タイトル末尾一致優先)
add_filter('posts_clauses', [ContentFilter::class, 'Prioritize_title_endswith_search'], 10, 2);

// footer実行（メッセージレンダリング等）
add_action('wp_footer', [ContentFilter::class, 'footer_hook'], 9999);


// --- 3B. 仮想ノード用テンプレート ---
/* 仮想ノード用テンプレートの割り当て */
add_filter('template_include',[ContentFilter::class, 'virtual_node'] );

/* WpTweak.php 等の init フック内に追加 */
add_action('init', function() {
    // index.php の前に明示的なルールを追加
    // hierarchy/直後の値を kx_virtual_path として受け取る
    add_rewrite_rule('hierarchy/([^/]+)/?$', 'index.php?kx_virtual_path=$1', 'top');
});

// カスタムクエリ変数として登録（WordPressに無視されないようにする）
add_filter('query_vars', function($vars) {
    $vars[] = 'kx_virtual_path';
    return $vars;
});



// --- 4. AJAXハンドラ (AjaxHandler) ---
// 階層操作ハンドラ
add_action('wp_ajax_kx_hierarchy_action', [AjaxHandler::class, 'hierarchy_ajax_handler']);

// 統合系アクション
add_action('wp_ajax_kx_consolidate_action', [AjaxHandler::class, 'ajax_consolidate_action']);

// エディタ用削除
add_action('wp_ajax_trash_post_ajax', [AjaxHandler::class, 'ajax_trash_post']);

// 新規投稿作成
add_action('wp_ajax_insert_post_ajax', [AjaxHandler::class, 'ajax_my_insert_post_handler']);


// --- 4. AJAXハンドラ (AjaxHandler) ---

// 【追加】JS側で kx_ajax_obj を使えるようにする
add_action('wp_enqueue_scripts', function() {
    // 既に読み込まれているメインのJS（例えば SideBar や共通のJS）のハンドル名に紐付ける
    // ハンドル名が不明な場合は、適当な名前で空のスクリプトを登録してlocalizeする
    wp_register_script('kx-ajax-bridge', '', [], null, true);
    wp_enqueue_script('kx-ajax-bridge');

    wp_localize_script('kx-ajax-bridge', 'kx_ajax_obj', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('kx_ai_link_nonce'),
    ]);
});

// 既存のハンドラ登録
add_action('wp_ajax_kx_hierarchy_action', [AjaxHandler::class, 'hierarchy_ajax_handler']);
// 【追加】AI Link用のハンドラを登録（これを忘れると400エラーになります）
add_action('wp_ajax_kx_load_ai_links', [AjaxHandler::class, 'handle_load_ai_links']);



