<?php
/**
 * [Path]: inc\core\class-kx-dy-storage.php
 * DyStorage: 実行時メモリキャッシュの物理保持クラス
 */

namespace Kx\Core;

class DyStorage {
    /**
     * ドメインごとに区分けされたデータストレージ
     * @var array
     */
    private static array $data = [
        'path_index' => [],
        'content'    => [],

        'system'    => [], // メッセージスタック等
        'matrix'    => [], // raretu再帰制御、計算結果
        'post'      => [], // 投稿メタ、カレント情報
    ];

    /**
     * 指定ドメインのデータを上書き保存する
     */
    public static function store(string $domain, $payload): void {
        self::$data[$domain] = $payload;
    }

    /**
     * 指定ドメインのデータをアプデート（マージ）する
     */
    public static function update(string $domain, array $new_elements): void {
        $current = self::$data[$domain] ?? [];

        // currentが配列でない場合（初期状態など）への安全策
        if (!is_array($current)) {
            $current = [];
        }

        self::$data[$domain] = $new_elements + $current;
    }

    /**
     * 指定ドメインのデータを取得する
     */
    public static function retrieve(string $domain): ?array {
        $value = self::$data[$domain] ?? null;
        return is_array($value) ? $value : null;
    }
}