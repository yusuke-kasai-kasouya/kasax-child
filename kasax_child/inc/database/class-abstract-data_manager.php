<?php
/**
 * [Path]: inc/database/class-abstract-data_manager.php

 */

namespace Kx\Database;

use Kx\Core\DynamicRegistry as Dy;

abstract class Abstract_DataManager {

    /**
     * 子クラスで必ずテーブル名を定義させる
     */
    abstract protected static function t();

    /**
     * データのロードと保持の共通ロジック
     */
    public static function load_raw_data_common($id, $cache_key, $id_column = 'id') {
        // 1. キャッシュ確認（null ではなく false で未存在を管理するのも手）
        $cached_raw = Dy::get_content_raw_cache($id, $cache_key);
        if ($cached_raw !== null) { // nullチェックを厳密に
            return $cached_raw;
        }


        global $wpdb;
        $table = static::t();

        // デバッグ用：ここでテーブル名が本当に kx_1 か確認してください
        // error_log("Fetching from table: " . $table);

        $res = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE $id_column = %s", $id),
            ARRAY_A
        );


        // 2. 取得結果が空でも「空である」ことをキャッシュに記録する（汚染防止）
        // これにより、他から変なデータがねじ込まれる隙を減らす
        Dy::set_content_cache($id, $cache_key, $res ? $res : false);

        return $res;
    }

    /**
     * 共通の保存判定ロジック（変更があるか？）
     */
    protected static function has_changed($new_data, $old_data, $ignore_keys = ['time']) {
        if (!$old_data) return true; // 新規なら変更あり

        $old_data = (array)$old_data;
        foreach ($ignore_keys as $key) {
            unset($new_data[$key], $old_data[$key]);
        }
        return $new_data !== $old_data;
    }
}