<?php
/**
 * Template Name: Laravelナレッジ検索ページ
 * [Path]: lib\html\Laravel_search_page.php *
*/
    use Kx\Core\SystemConfig as Su;


    // LaravelのサーバーURLを定義
    //$laravel_api_base = "http://localhost:8000";
    $laravel_api_base = defined('LARAVEL_API_URL') ? LARAVEL_API_URL : "http://localhost:8000";


    //カテゴリー名を設定した配列を呼び出し、foreach。2023-03-05
    $prefixes = Su::get('title_prefix_map')['prefixes'];
    $_cat_num = 0;
    foreach($prefixes as $key =>  $value ):

        $_cat_name_arr[] = [ 'cat_num' => $_cat_num , 'preg' => $key , 'name' => $value[ 'name' ] ];
        $_cat_num ++;

    endforeach;
    unset( $key , $value );


    $_categories = get_categories( array( 'taxonomy' => 'category' ) );

    if ( $_categories )
    {
        foreach( $_categories as $category )
        {
            $_category_base[$category->name] =
            [
                'id'             => $category->term_id,
                'category_count' => $category->category_count,
            ];
        }
    }


  if ( !empty($prefixes) ) :
    $str = '';
        foreach ( $prefixes as $cat_name => $cat_data )
        {
            $str .= '<option value="'.$_category_base[$cat_name]['id'].'">';
            $str .= $cat_name.'：'.$cat_data['name'].'（'.$_category_base[$cat_name]['category_count'].'）';
            $str .= '</option>';
        }
  endif;




?>

<div id="kx-search-container">
    <h2>検索 (Laravel-API)</h2>

    <div class="kx-search-box">
        <input type="text" id="kx-keyword" placeholder="キーワードを入力（-で除外）" style="width: 500px;;">
        <button id="kx-search-btn">検索</button>

        <br><br>

         <select id="kx-category">
            <option value="">全カテゴリ</option>
            <?php echo $str; ?>
        </select>
    </div>

    <hr>
    <div id="kx-result-area" style="margin-top: 20px;">
        <p>ここに結果が表示されます。</p>
    </div>
</div>


<script>
// --- 既存のクリックイベント（省略） ---
document.getElementById('kx-search-btn').addEventListener('click', function() {
    // ...中身はそのまま...
});

// 追加：Enterキー（リターン）での実行対応
document.getElementById('kx-keyword').addEventListener('keypress', function (e) {
    if (e.key === 'Enter') {
        // デフォルトの挙動（ページリロードなど）を防止
        e.preventDefault();
        // 検索ボタンのクリック処理を発火させる
        document.getElementById('kx-search-btn').click();
    }
});
</script>


<script>
document.getElementById('kx-search-btn').addEventListener('click', function() {
    const keyword = document.getElementById('kx-keyword').value;
    const categoryId = document.getElementById('kx-category').value;
    const resultArea = document.getElementById('kx-result-area');

    if (!keyword) {
        alert('キーワードを入力してください');
        return;
    }

    resultArea.innerHTML = '<p>検索中...</p>';

    // Laravel APIのURL（ここだけはブラウザに教えるために絶対パスが必要です）
    const apiBase = "<?php echo $laravel_api_base; ?>";
    const apiUrl = `${apiBase}/api/kx/search?keyword=${encodeURIComponent(keyword)}&category_id=${categoryId}`;


    fetch(apiUrl)
        .then(response => response.json())
        .then(res => {
            if (res.status === 'success' && res.count > 0) {
                let html = `<h3>「${res.keyword}」の検索結果（${res.count}件）</h3>`;
                res.data.forEach(item => {
                    html += `
                        <div style="border-bottom: 1px solid #ccc; padding: 10px 0;">
                            <h4 style="margin:0;"><a href="${item.link}" target="_blank">${item.title}</a></h4>
                            <p style="font-size: 0.9em; color: #666;">...${item.excerpt}...</p>
                        </div>
                    `;
                });
                resultArea.innerHTML = html;
            } else {
                resultArea.innerHTML = '<p>該当する記事が見つかりませんでした。</p>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            resultArea.innerHTML = '<p>エラーが発生しました。Laravelサーバーが起動しているか確認してください。</p>';
        });
});
</script>