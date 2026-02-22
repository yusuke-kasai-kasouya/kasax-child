<?php
/**
 * templates/admin/laravel_test.php
 * Laravel死活監視インジケーター
 */
$api_base = defined('LARAVEL_API_URL') ? LARAVEL_API_URL : 'http://localhost:8000';
?>

<div id="laravel-status-box" style="display:inline-block; padding: 4px 12px; border-radius: 4px; background: #f0f0f1; border: 1px solid #c3c4c7;">
    <span id="laravel-indicator" style="color: #666;">●</span>
    <span id="laravel-status-text" style="font-weight:bold; font-size:12px;">Laravel 接続確認中...</span>
</div>

<script>
(function() {
    const statusBox = document.getElementById('laravel-status-box');
    const indicator = document.getElementById('laravel-indicator');
    const statusText = document.getElementById('laravel-status-text');

    // ステップ1で作成した ping エンドポイントを叩く
    fetch('<?php echo esc_url($api_base); ?>/api/kx/ping')
        .then(response => {
            if (response.ok) {
                indicator.style.color = '#00a32a'; // 緑色
                statusText.innerText = 'Laravel Online';
                statusBox.style.borderColor = '#00a32a';
            } else {
                throw new Error();
            }
        })
        .catch(error => {
            indicator.style.color = '#d63638'; // 赤色
            statusText.innerText = 'Laravel Offline';
            statusBox.style.borderColor = '#d63638';
        });
})();
</script>