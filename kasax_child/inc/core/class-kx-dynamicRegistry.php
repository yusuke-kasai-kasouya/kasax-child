<?php
/**
 * [Path]: inc\core\class-kx-dynamicRegistry.php
 * アプリケーション実行時メモリキャッシュの窓口（Facade）クラス
 */

namespace Kx\Core;

/**
 * DynamicRegistry クラス
 *
 * 【データ構造定義：content[$id] 三層構造】
 * 1.[raw]:DB生データ / 2.[ana]:論理解析(path,node,attr) / 3.[vis]:表示装飾(atlas,paint,traits)
 */
class DynamicRegistry {

    /**
     * 設定ドメインのデータを取得
     *
     * @param string $key ドメインキー
     * @return mixed 取得したデータ
     */
    public static function get($key) {
        return DyStorage::retrieve($key);
    }

    /**
     * 設定ドメインのデータを上書き保存
     *
     * @param string $key ドメインキー
     * @param mixed $value 保存する値
     * @return void
     */
    public static function set($key, $value) {
        DyStorage::store($key, $value);
    }

    /**
     * システム整合性ステータスの取得
     *
     * @param string|null $key 特定のキー（省略時は全データ）
     * @return mixed システムステータス
     */
    public static function get_system($key = null) {
        return DyDomainHandler::get_system($key);
    }

    /**
     * システム整合性ステータスの更新
     *
     * @return mixed
     */
    public static function set_system() {
        return DyDomainHandler::set_system();
    }

    /**
     * パス構造（≫）を解析しインデックスを構築・更新
     *
     * @param int $post_id 投稿ID
     * @param string $mode 動作モード
     * @return array|null 解析結果の配列
     */
    public static function set_path_index($post_id , $mode = '') {
        return DyPathIndexHandler::process_path_analysis((int)$post_id, $mode);
    }

    /**
     * 指定IDのパスインデックス情報を取得
     *
     * @param int $post_id 投稿ID
     * @param string $key 特定の要素キー（省略時は全データ）
     * @return mixed パス解析情報
     */
    public static function get_path_index($post_id, $key = '') {
        $entry = self::set_path_index($post_id);
        if (!$entry) return null;
        return ($key === '') ? $entry : ($entry[$key] ?? null);
    }

    /**
     * 補充を行わずメモリ上の raw データのみをピンポイント取得
     *
     * @param int $post_id 投稿ID
     * @param string $sub_key サブキー
     * @return mixed
     */
    public static function get_content_raw_cache($post_id, $sub_key) {
        $content = self::get('content');
        return $content[$post_id]['raw'][$sub_key] ?? null;
    }

    /**
     * コンテンツキャッシュを取得（未存在時は自動補充）
     *
     * @param int $post_id 投稿ID
     * @param string|null $sub_key 特定のサブキー
     * @return mixed
     */
    public static function get_content_cache($post_id, $sub_key = null) {
        return DyContentHandler::get_content_cache($post_id, $sub_key);
    }

    /**
     * コンテンツキャッシュへ特定キーのデータを注入（部分更新）
     *
     * @param int $post_id 投稿ID
     * @param string $sub_key サブキー
     * @param mixed $data 注入データ
     * @return mixed
     */
    public static function set_content_cache($post_id, $sub_key, $data) {
        return DyContentHandler::set_content_cache($post_id, $sub_key, $data);
    }

    /**
     * 未ロード時のみコンテンツをDBから一括構築（Lazy Load）
     *
     * @param int $post_id 投稿ID
     * @param string $type コンテンツタイプ
     * @return mixed
     */
    public static function set_content_page($post_id, $type) {
        return DyContentHandler::set_content_page($post_id, $type);
    }

    /**
     * キャッシュの有無に関わらずDBからデータを強制再読込
     *
     * @param int $post_id 投稿ID
     * @return mixed
     */
    public static function set_content_refresh($post_id) {
        return DyContentHandler::set_content_refresh($post_id);
    }

    /**
     * 最速キャッシュ・独自DB・WP標準の順でタイトルを取得
     *
     * @param int $post_id 投稿ID
     * @return string タイトル
     */
    public static function get_title($post_id) {
        return DyDomainHandler::get_title($post_id);
    }

    /**
     * 特定IDのカラーマネージャー情報を取得
     *
     * @param int $post_id 投稿ID
     * @param string $type マネージャータイプ
     * @return mixed
     */
    public static function get_color_mgr($post_id, $type = 'std') {
        return DyDomainHandler::get_color_mgr($post_id, $type );
    }

    /**
     * 生成された装飾セットをシステムキャッシュに登録
     *
     * @param mixed $mgr マネージャーオブジェクト
     * @return mixed
     */
    public static function register_to_color_mgr_cache($mgr) {
        return DyDomainHandler::register_to_color_mgr_cache($mgr);
    }

    /**
     * 特定IDの実行時フラグ状態を取得
     *
     * @param int $post_id 投稿ID
     * @param string|null $key 特定のキー
     * @return mixed
     */
    public static function get_flags($post_id, $key = null) {
        return DyDomainHandler::get_flags($post_id, $key );
    }

    /**
     * 特定IDに実行時の動的フラグをセット
     *
     * @param int $post_id 投稿ID
     * @param string $key フラグキー
     * @param mixed $value 値（デフォルト1）
     * @return mixed
     */
    public static function set_flags($post_id, $key, $value = 1) {
        return DyDomainHandler::set_flags($post_id, $key, $value);
    }

    /**
     * 特定IDのフラグを要素ごと完全に削除
     *
     * @param int $post_id 投稿ID
     * @param string $key 削除するキー
     * @return mixed
     */
    public static function unset_flags($post_id, $key) {
        return DyDomainHandler::unset_flags($post_id, $key);
    }

    /**
     * 文章構造（アウトライン）情報を取得
     *
     * @param int $post_id 投稿ID
     * @return mixed
     */
    public static function get_outline($post_id) {
        return DyDomainHandler::get_outline($post_id);
    }

    /**
     * 文章構造（アウトライン）情報を保存
     *
     * @param int $post_id 投稿ID
     * @param mixed $data 保存データ
     * @return mixed
     */
    public static function set_outline($post_id, $data) {
        return DyDomainHandler::set_outline($post_id, $data);
    }

    /**
     * 投稿に付随する任意の情報を取得
     *
     * @param int $post_id 投稿ID
     * @param string $key キー
     * @param mixed $default デフォルト値
     * @return mixed
     */
    public static function get_info($post_id, $key = '', $default = null) {
        return DyDomainHandler::get_info($post_id, $key , $default);
    }

    /**
     * 投稿に付随する情報をセット（マージ可）
     *
     * @param int $post_id 投稿ID
     * @param array $data セットするデータ
     * @param bool $merge マージするかどうか
     * @return mixed
     */
    public static function set_info($post_id, array $data, $merge = true) {
        return DyDomainHandler::set_info($post_id, $data, $merge);
    }

    /**
     * IDからキャラクター設定値を取得
     *
     * @param int $post_id 投稿ID
     * @return mixed
     */
    public static function get_character($post_id) {
        return DyDomainHandler::get_character($post_id);
    }

    /**
     * IDから作品設定（PublishedWorks）を取得
     *
     * @param int $post_id 投稿ID
     * @return mixed
     */
    public static function get_work($post_id) {
        return DyDomainHandler::get_work($post_id);
    }

    /**
     * 特定IDのMatrix設定を取得
     *
     * @param int $post_id 投稿ID
     * @param string|null $key キー
     * @return mixed
     */
    public static function get_matrix($post_id, $key = null) {
        return DyDomainHandler::get_matrix($post_id, $key);
    }

    /**
     * 特定IDのMatrix設定を蓄積・保存
     *
     * @param int $post_id 投稿ID
     * @param string $key キー
     * @param mixed $data 保存データ
     * @return mixed
     */
    public static function set_matrix($post_id, $key, $data) {
        return DyDomainHandler::set_matrix($post_id, $key, $data);
    }

    /**
     * 投稿の直下の子孫ID配列を取得
     *
     * @param int $post_id 投稿ID
     * @return int[] 子孫IDの配列
     */
    public static function get_descendants($post_id) {
        return DyDomainHandler::get_descendants($post_id);
    }

    /**
     * シリーズと番号からキャラ属性を取得
     *
     * @param string $series シリーズ名
     * @param string $num キャラ識別子（例：＼名前）
     * @return array キャラ属性配列
     */
    public static function get_char_attr($series, $num) {
        return DyDomainHandler::get_char_attr($series, $num);
    }

    /**
     * ID配列を走査し有効な投稿のみを抽出
     *
     * @param array $ids IDの配列
     * @return int[] 有効なIDの配列
     */
    public static function validate_ids(array $ids) {
        return DyDomainHandler::validate_ids($ids);
    }

    /**
     * 指定IDがシステム管理下にあるか判定
     *
     * @param int $post_id 投稿ID
     * @return bool
     */
    public static function is_ID($post_id) {
        return DyDomainHandler::is_ID($post_id);
    }

    /**
     * 同期対象の全投稿タイプをマージして取得
     *
     * @return string[]
     */
    public static function get_shared_sync_types() {
        return DyDomainHandler::get_shared_sync_types();
    }

    /**
     * 実行トレースカウントを増減
     *
     * @param string $key トレースキー
     * @param int $delta 増減値
     * @return void
     */
    public static function trace_count($key, $delta = 1) {
        return DyDomainHandler::trace_count($key, $delta);
    }
}

// 互換性維持のためのクラスエイリアス設定
if (!class_exists('Dy')) class_alias(\Kx\Core\DynamicRegistry::class, 'Dy');
if (!class_exists('KxDy')) class_alias(\Kx\Core\DynamicRegistry::class, 'KxDy');