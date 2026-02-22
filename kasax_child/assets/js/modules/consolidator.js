jQuery(function($) {
    $(document).on('click', '.kx-consolidator-btn', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const data = {
            action: 'kx_consolidate_action',
            source_id: $btn.data('source-id'),
            post_id: $btn.data('post-id'),
            args: $btn.data('args'), // JSONオブジェクトとして取得される
            _ajax_nonce: $btn.data('nonce')
        };

        $btn.prop('disabled', true).text('実行中...');

        $.post(ajaxurl, data, function(response) {
            if (response.success) {
                alert('実行完了: ' + (response.data.message || 'ファイルを確認してください'));
            } else {
                alert('エラー: ' + response.data);
            }
        }).always(function() {
            $btn.prop('disabled', false).text('統合指示を実行');
        });
    });
});