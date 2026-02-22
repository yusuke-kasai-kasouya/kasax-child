<?php
/**
 * app/Repositories/KxPostRepository.php
 */
namespace App\Repositories;

use Illuminate\Support\Facades\DB;

/**
 * Class KxPostRepository
 *
 * WordPressの投稿データ（wp_postsテーブル）へのアクセスを専門に担当するリポジトリ。
 * データの取得ロジック（SQL/クエリビルダ）をこのクラスに集約することで、
 * DB構造の変更があった際の影響範囲を最小限に抑える。
 * 2025-12-25
 *
 * @package App\Repositories
 */
class KxPostRepository {
    /**
     * タイトルに特定の文字列を含む公開済み記事のIDを取得
     * 2025-12-25
     */
    public function findIdsByTitle(string $title) {
        return DB::table('wp_posts')
            ->where('post_title', 'LIKE', "%{$title}%")
            ->where('post_status', 'publish')
            ->pluck('ID'); // IDのみをコレクションで取得
    }

    /**
     * 指定されたタイトル階層（前方一致）に属する投稿ID群を取得する。
     * 現在地のタイトル自体は含めない。
     * 2025-12-25
     *
     * @param string $hierarchyPrefix 「現タイトル≫」の文字列
     * @return \Illuminate\Support\Collection
     */
    public function findIdsByHierarchy(string $hierarchyPrefix) {
        return DB::table('wp_posts')
            ->where('post_title', 'LIKE', "{$hierarchyPrefix}%") // 前方一致
            ->where('post_status', 'publish')
            ->pluck('ID');
    }


}
