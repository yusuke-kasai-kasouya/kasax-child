<?php
/**
 *[Path]: inc/core/class-kx-context_manager.php
 */

namespace Kx\Core;


use Kx\Core\DynamicRegistry as Dy;
use \Kx\Database\dbkx0_PostSearchMapper as dbkx0;
use \Kx\Database\dbkx1_DataManager as dbkx1;
use Kx\Database\Hierarchy;
use \Kx\Database\dbKxAiMetadataMapper;
use \Kx\Database\dbkx_SharedTitleManager as dbkx_share;


class ContextManager {

    /**
     * システム全体の整合性を保ちながら同期を実行
     */
    public static function sync($post_id , $mode = 'read'){

        // 1. 数値化
        if (is_array($post_id)) {
            $post_id = $post_id['id'] ?? $post_id[0] ?? null;
        }
        if (!$post_id) return;

        // 自動保存やリビジョンは対象外
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        //systemチェックセット。
        Dy::set_system();

        // 2. Dyに解析とキャッシュを一任 (ここで $path_index が確定)
        $p_idx = Dy::set_path_index($post_id);


        // 3. バリデーション：対象外なら早期リターン
        // ここで page の処理や publish 以外の処理も一元管理できる
        if (!$p_idx['valid']) {
            if ($p_idx['wp_type'] === 'page') {
                Dy::set_content_page( $post_id,  'page');
            }
            if ($p_idx['status'] !== 'none' && $p_idx['status'] !== 'publish') {
                self::purge_all($post_id);
            }
            return;
        }

        // 4. 再帰ガード
        Dy::trace_count('context_mgr_count' . $post_id, +1);
        $call_count = Dy::get('trace')['context_mgr_count' . $post_id] ?? 0;


        if ($mode === 'read' && $call_count > 1) {
            return;
        }


        // 5. 各テーブルへのオーケストレーション
        // 必要な処理を実行
        dbkx0::sync($post_id);
        Hierarchy::sync($post_id);
        dbkx1::sync($post_id);
        dbKxAiMetadataMapper::sync($post_id);
        dbkx_share::sync($post_id);

        // 6. Dyメモリキャッシュの最終確定
        // 更新後のデータを再ロードしてDyを最新状態にする
        Dy::set_content_refresh($post_id);
    }



    /**
     * 投稿が無効（ゴミ箱等）になった際の全テーブルクリーンアップ
     */
    private static function purge_all($post_id) {
        // 階層レコードの削除、Dyキャッシュの抹消
        Hierarchy::purge_post_record($post_id, 'trash');
    }
}