<?php

/**
 * inc\core\class-kx-LaravelClient.php
 *
 */

namespace Kx\Core;

use Kx\Core\DynamicRegistry as Dy;

class LaravelClient {

    private static function get_laravel_api_url($path = '') {
        // 定数があればそれを使い、なければ localhost をデフォルトにする
        $base_url = defined('LARAVEL_API_URL') ? LARAVEL_API_URL : 'http://localhost:8000';

        // パス（/api/kx/search-advanced など）を結合して返す
        return rtrim($base_url, '/') . '/' . ltrim($path, '/');
    }

    /**
     * Laravel APIがオンラインかどうかを判定する
     * 補助関数 get_laravel_api_url を使用してURLを生成
     * * @return bool オンラインならtrue、オフラインならfalse
     */
    public static function is_laravel_online() {
        // #1 の関数を使ってエンドポイントのURLを安全に取得
        $ping_endpoint = self::get_laravel_api_url('api/kx/ping');

        // タイムアウトを短く設定してリクエスト
        $response = wp_remote_get($ping_endpoint, [
            'timeout' => 2,
        ]);

        // エラーがなく、かつステータスコードが200であればオンラインとみなす
        if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
            return true;
        }

        return false;
    }



    /*
        --------使い方-----------

        // 例：前方一致で「研究」を検索し、末尾「アーカイブ」を除外、カテゴリ10のみに絞る
        $results = kx_search_advanced_ids_cached([
            'title'              => '研究',
            'title_mode'         => 'prefix',
            'title_exclude'      => 'アーカイブ',
            'title_exclude_mode' => 'suffix',
            'category_id'        => 10
        ]);

        foreach ($results as $id) {
            echo get_the_title($id);
        }
    */

    /**
     * Laravel APIを使用した高度な記事ID検索（キャッシュ対応）
     * * @param array $args {
     * 検索条件の連想配列
     * @type string $title               検索タイトル
     * @type string $title_mode          一致モード (prefix:前方一致 | suffix:後方一致 | exact:完全一致 | both:部分一致)
     * @type string $title_exclude       除外タイトル
     * @type string $title_exclude_mode  除外モード
     * @type int    $category_id         カテゴリID
     * @type int    $category_exclude_id 除外カテゴリID
     * @type int    $tag_id              タグID
     * @type int    $tag_exclude_id      除外タグID
     * }
     * @return array 記事IDの配列（失敗時は空配列）
     */
    public static function search_advanced_ids_cached($args = []) {

        //echo kx_dump($args);

        // 1. キャッシュチェック
        $cache_key = 'search_adv_' . md5(serialize($args));
        $cache = Dy::get('laravel_search_results');

        // ★ 修正箇所：$cache が配列でない場合は初期化する
        if ( ! is_array($cache) ) {
            $cache = [];
        }

        if ( isset($cache[$cache_key]) ) {
            return $cache[$cache_key];
        }

        // 2. 死活監視
        if ( ! Dy::get_system('laravel_online') ) {
            return []; // 戻り値の型を array に合わせるため false より [] が安全
        }

        // 3. 通信実行
        $api_url = self::get_laravel_api_url('api/kx/search-advanced');

        $mapped_args = [
            'title'               => $args['search'] ?? null,
            'title_mode'          => $args['title_mode'] ?? 'both',
            'category_id'         => $args['cat'] ?? null,
            'category_exclude_id' => $args['cat_not'] ?? null,
            'tag_id'              => $args['tag'] ?? null,
            'tag_exclude_id'      => $args['tag_not'] ?? null,
        ];

        // 不要な null を除外して URL を構築
        $url = add_query_arg(array_filter($mapped_args), $api_url);
        $url = add_query_arg($args, $api_url);

        $response = wp_remote_get($url, ['timeout' => 5]);

        if ( is_wp_error($response) ) {
            return [];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($body['status']) || $body['status'] !== 'success') {
            return [];
        }

        $ids = $body['ids'] ?? [];

        // 4. 保存
        // ここに到達した時点で $cache は必ず配列になっているためエラーを回避できます
        $cache[$cache_key] = $ids;
        Dy::set('laravel_search_results', $cache);

        return $ids;
    }
}