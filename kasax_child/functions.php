<?php
/**
 * 子テーマfunction。
 * xampp\htdocs\0\wp-content\themes\kasax_child\functions.php
 * 2026-01-02
 *
 * @return void
 */



// 1. 親テーマのスタイル読み込み
add_action( 'wp_enqueue_scripts' , function() {
    wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
});


// 2. 自動読み込み設定
$inc_directories = [
    'core',
    'database',
    'matrix',
    'utils',
    'visual',
    'component',
    'batch',
    'launcher',
    'legacy-code',
    'parser',
    'admin',
];

foreach ( $inc_directories as $dir ) {
    $path = get_stylesheet_directory() . '/inc/' . $dir;
    if ( is_dir( $path ) ) {
        // globの戻り値をチェックし、確実にPHPファイルのみを読み込む
        $files = glob( "$path/*.php" );
        if ( $files ) {
            foreach ( $files as $filename ) {
                require_once $filename;
            }
        }
    }
}

// 3. 既存の core-loader を読み込む。
$core_loader = 'inc/core-loader.php';
if ( locate_template( $core_loader ) ) {
    require_once locate_template( $core_loader, true );
}



/**
 * js 読み込み。
 */
add_action('wp_enqueue_scripts', function() {
    // スキャン対象のディレクトリ（テーマの /assets/js/modules/）
    $js_mod_dir = get_stylesheet_directory() . '/assets/js/modules';
    $js_mod_uri = get_stylesheet_directory_uri() . '/assets/js/modules';

    if (is_dir($js_mod_dir)) {
        // フォルダ内のファイルをスキャン
        $files = scandir($js_mod_dir);
        foreach ($files as $file) {
            // .js で終わるファイルのみを対象にする
            if (pathinfo($file, PATHINFO_EXTENSION) === 'js') {
                $handle = 'kx-js-' . pathinfo($file, PATHINFO_FILENAME);

                // 自動的に一括エンキュー
                wp_enqueue_script(
                    $handle,
                    $js_mod_uri . '/' . $file,
                    ['jquery'], // 依存関係にjQueryを指定
                    filemtime($js_mod_dir . '/' . $file), // キャッシュ対策：更新日時をバージョンに
                    true // </body>直前で読み込み
                );
            }
        }
    }
});


/**
 * REST APIの投稿データにカスタムコンテンツフィールドを制御する
 * 標準の 'content' フィールドを配列型に上書きすると、ブロックエディター(JS)側の
 * n.replace() 等の文字列操作関数がクラッシュし、編集画面が白壊するため。
 * * 【対策】
 * 1. 編集コンテキスト(context=edit)では標準の文字列型を維持する。
 * 2. 常に「rendered」を持つオブジェクト構造を返し、APIスキーマの整合性を保つ。
 */
add_action('rest_api_init', function() {
    register_rest_field('post', 'content', [
        'get_callback' => function($post_array) {
            $post = get_post($post_array['id']);

            // 常に「オブジェクト（配列）」を返し、中の値だけを切り替える
            return [
                'raw'      => $post->post_content,
                'rendered' => (is_admin() || (isset($_GET['context']) && $_GET['context'] === 'edit'))
                    ? $post->post_content // 管理画面では生データ
                    : apply_filters('the_content', $post->post_content), // 表側ではフィルター適用
            ];
        }
    ]);
});