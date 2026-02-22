<?php
/**
 * templates/external/Laravel_search_cat.php
 */
$laravel_api_base = defined('LARAVEL_API_URL') ? LARAVEL_API_URL : "http://localhost:8000";
?>

<div id="laravel-search-wrapper">

    <?php echo $online; ?>

    <div id="laravel-search-settings" style="max-width: <?php echo esc_attr($width); ?>px; margin-bottom: 20px;">
        <form class="kx-search-form" onsubmit="executeLaravelSearch(event, this)">
            <div style="display: flex; gap: 5px; margin-bottom: 5px;">
                <input type="search" name="s" placeholder="Laravelで検索..."
                       size="<?php echo esc_attr($size); ?>" class="__search" style="flex-grow: 1;">
                <button type="submit" class="__search_button">⇨</button>
            </div>

            <div class="<?php echo esc_attr($css_class); ?>" style="font-weight:bold; border-bottom:1px solid #444; margin-bottom:2px;width: 100%;">
                Selected Categories
            </div>
            <table style="width: 100%; font-size: 0.9em;">
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td width="20">
                            <input type="checkbox" name="cat" value="<?php echo $cat->term_id; ?>" checked>
                        </td>
                        <td><?php echo esc_html($cat->name); ?></td>
                        <td style="text-align: right; color: #444;">
                            id:<?php echo $cat->term_id; ?> (<?php echo $cat->category_count; ?>p)
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </form>
    </div>

    <div id="laravel-inline-results" style="width: 100%; min-height: 50px; border-top: 1px solid #5a5a5a; padding-top: 5px;">
        </div>
</div>

<script>
function executeLaravelSearch(e, form) {
    e.preventDefault();
    const keyword = form.s.value;
    const resultsDiv = document.getElementById('laravel-inline-results');

    const checkboxes = form.querySelectorAll('input[name="cat"]:checked');
    const checkedCats = Array.from(checkboxes).map(cb => cb.value);

    if (!keyword) return;
    resultsDiv.innerHTML = '<p>Searching...</p>';

    const apiBase = "<?php echo $laravel_api_base; ?>";
    const catParam = checkedCats.length > 0 ? checkedCats[0] : '';
    const apiUrl = `${apiBase}/api/kx/search?keyword=${encodeURIComponent(keyword)}&category_id=${catParam}`;

    fetch(apiUrl)
        .then(res => {
            if (!res.ok) throw new Error('Network response was not ok');
            return res.json();
        })
        .then(res => {
            if (res.status === 'success' && res.count > 0) {
                // タイトルと件数を表示
                let html = `<h3 style="margin-top:0;">「${res.keyword}」の検索結果（${res.count}件）</h3>`;

                res.data.forEach(item => {
                    // Laravel_search_page.php のデザインを継承
                    html += `
                        <div style="border-bottom: 1px solid #ccc; padding: 12px 0;">
                            <h4 style="margin:0;">
                                <a href="${item.link}" target="_blank" style="color: #0073aa; text-decoration: none;">
                                    ${item.title}
                                </a>
                            </h4>
                            <p style="font-size: 0.9em; color: #444; margin: 5px 0 0 0;">
                                ...${item.excerpt || ''}...
                            </p>
                        </div>
                    `;
                });
                resultsDiv.innerHTML = html;
            } else {
                resultsDiv.innerHTML = '<p>該当する記事が見つかりませんでした。</p>';
            }
        })
        .catch(err => {
            resultsDiv.innerHTML = `<p style="color:red;">通信エラー: ${err.message}</p>`;
        });
}
</script>