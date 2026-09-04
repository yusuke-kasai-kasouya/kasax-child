<?php
/**
 * [Path]: inc\core\class-kx-dy-path-index-handler.php
 * DyDomainHandler::: 各ドメインロジックの基底抽象クラス
 */
namespace Kx\Core;

use Su;
use Dy;

abstract class DyPathIndexHandler {

    /**
     * ストレージから特定のドメインの生データを取得する
     *
     * @param string $domain 取得対象のドメイン名（例: 'path_index'）
     * @return mixed         ストレージから取得されたデータ
     */
    protected static function get_from_storage(string $domain) {
        return DyStorage::retrieve($domain);
    }

    /**
     * パス構造の解析ロジック（メイン実体）
     *
     * タイトルに含まれる「≫」や「＠」を分解し、階層構造、名称、初回投稿日、更新日時等を
     * インデックス化してストレージに格納する。
     *
     * @version 0.2026.0904
     * @updated 2026-09-04
     * @context エディタINFO等での表示要求に伴い、初回投稿日時（post_date）をpath_indexキャッシュ構造に追加
     * @constraint 既存のキー構造（modified等）および戻り値の型定義を維持し、追加DBクエリを発生させないこと
     * @param int    $post_id WP標準のPostID
     * @param string $mode    動作モード（'maintenance' 等）
     * @return array|null     解析済みのエントリ配列。失敗時は null
     */
    public static function process_path_analysis(int $post_id, string $mode = ''): ?array {
        if (!$post_id) return null;

        // 1. 金庫から現在の状態を確認（重複処理の防止）
        $storage = DyStorage::retrieve('path_index') ?: [];
        if (isset($storage[$post_id])) {
            return $storage[$post_id];
        }

        // 1. 唯一の get_post 実行
        $post = get_post($post_id);

        // 2. 基本情報の抽出
        $title     = $post ? $post->post_title    : '';
        $wp_type   = $post ? $post->post_type     : 'unknown';
        $status    = $post ? $post->post_status   : 'none';
        $post_date = $post ? $post->post_date     : null; // 初回投稿日時を取得
        $modified  = $post ? $post->post_modified : null; // 更新時間を取得

        $is_valid = ($wp_type === 'post' && $status === 'publish');

        // 3. パス解析（先行セット用）
        $parts = ($title !== '') ? explode('≫', $title) : [];
        $last_part = !empty($parts) ? end($parts) : ''; // 最後の要素（ラストパーツ）

        // 「＠」による分解 (例: "10-11＠タイトル名" -> ["10-11", "タイトル名"])
        $at_split = ($last_part !== '') ? explode('＠', $last_part, 2) : [];

        $time_element = null;
        $clean_name   = null;

        if (count($at_split) === 2) {
            // @が存在する場合
            $time_element = trim($at_split[0]); // "10-11"
            // @の後ろが空でも、名前部分だけを抽出（@自体は含めない）
            $clean_name = trim($at_split[1]);
            // もし名前が空なら、管理用に time_element を名前として扱うか、
            // スキーマ判定用にラストパーツ全体を保持する
            if ($clean_name === '') {
                $clean_name = $last_part;
            }
        } else {
            // @が存在しない場合
            $time_element = null;
            $clean_name   = $last_part;
        }

        // --- CODE の抽出（最初の半角コロン ':' より前を抽出） ---
        $code = "{$post_id}_NO_CODE";
        if ($clean_name !== null && ($colon_pos = strpos($clean_name, ':')) !== false) {
            $extracted_code = trim(substr($clean_name, 0, $colon_pos));
            if ($extracted_code !== '') {
                $code = $extracted_code;
            }
        }

        $parent_parts = array_slice($parts, 0, -1);

        // --- セグメント名の基本解決 ---
        $part_names = self::resolve_segment_names($parts);
        $count      = count($parts);

        // デフォルトのラストパーツ名（解決済み配列の最後）
        $last_part_name = ($count > 0) ? ($part_names[$count - 1] ?? '') : '';

        // --- 特殊階層（リレーション）の動的解決 ---
        // 1. まず階層数が「3」であることを確認（リレーションの絶対条件）
        // 2. その上で第3要素（インデックス2）が「＼」で始まるか確認
        if ($count === 3 && isset($parts[0], $parts[2]) && mb_strpos($parts[2], '＼') === 0) {
            $attr = Dy::get_char_attr($parts[0], $parts[2]);
            if (!empty($attr['name'])) {
                $last_part_name = $attr['name'];
            }
        }

        $entry = [
            'full'          => $title,
            'parts'         => $parts,
            'parts_names'   => $part_names,
            'parent_path'   => implode('≫', $parent_parts), // 文字列としての親パス
            'last_part'     => $last_part,                  // ラストパーツ全体
            'last_part_name'=> $last_part_name,             // 解決済みラストパーツ名
            'time_slug'     => $time_element,               // ＠より前の要素（10-11等）
            'at_name'       => $clean_name,                 // ＠より後の純粋な名称
            'code'          => $code,                       // 抽出されたCODE

            'depth'         => $count,
            'wp_type'       => $wp_type,
            'status'        => $status,
            'post_date'     => $post_date,
            'modified'      => $modified,
            'valid'         => $is_valid,
            'type'          => 'default', // 仮置き
            'genre'         => 'none',    // 仮置き
            'markers'       => [],
        ];

        // 4. 注意：identify_post_attributes 内での get_title 呼び出しに備えて先行登録
        $storage[$post_id] = $entry;
        DyStorage::store('path_index', $storage);

        // 5. システムタイプ・フラグ判定
        $attr = self::identify_post_attributes($post_id, $mode);
        $entry['type']    = $attr['type'];    // 例: 'Μ', 'σ'
        $entry['genre']   = $attr['genre'];   // 例: 'strat_sales', 'arc_psy_game_theory'
        $entry['markers'] = $attr['markers']; // 例: 'prod_character_core', 'prod_character_relation'

        // 6. 金庫（Storage）へ確定保存
        $storage[$post_id] = $entry;
        DyStorage::store('path_index', $storage);

        return $entry;
    }

    /**
     * 接頭辞やルールに基づき、システム上の属性（type, genre, markers）を特定する
     *
     * @param int    $post_id ポストID
     * @param string $mode    動作モード
     * @return array{type: string, genre: string, markers: array<string, int>} 属性情報
     */
    private static function identify_post_attributes(int $post_id, string $mode = ''): array {
        $post_id = (int)$post_id;

        // デフォルト値の設定
        $result = [
            'type'   => 'default',
            'genre'  => 'none',
            'markers'  => []
        ];

        if (!$post_id) return $result;

        // 1. 重複可能なフラグ判定（Ghost, Archiveなど）
        foreach (Su::BOOLEAN_MARKERS as $key => $marker) {
            if (TitleParser::is_type($key, $post_id)) {
                $result['markers'][$marker] = 1;
            }
        }

        // 2. メインドメイン（大カテゴリ）の判定
        foreach (Su::PRIORITY_TYPES as $type) {
            if (TitleParser::is_type($type, $post_id)) {
                $result['type'] = $type;
                break;
            }
        }

        if( $mode != 'maintenance') {
            // 3. 詳細ジャンル（小カテゴリ：identifier_schema のキー）の判定
            // TitleParser::detect_type は 'strat_sales' などの識別キーを返す
            $detected_genre = TitleParser::detect_type($post_id);
            if ($detected_genre) {
                $result['genre'] = $detected_genre;
            }
        }

        return $result;
    }

    /**
     * パスセグメント配列から、各階層の定義名称を特定して配列で返す
     *
     * SystemConfig の prefix_map および contextual_definitions を参照し、
     * 文脈依存の名称解決を行う。
     *
     * @param string[] $parts パスセグメントの配列
     * @return array          解決された名称の配列（未定義箇所は null）
     */
    private static function resolve_segment_names(array $parts): array {
        $prefix_data = Su::get('title_prefix_map');
        $prefix_map  = $prefix_data['prefixes'] ?? [];
        $context_map = $prefix_data['contextual_definitions'] ?? [];

        $resolved_names = [];
        $last_key = null;
        $ancestor_keys = [];

        foreach ($parts as $index => $segment) {
            $key = (mb_strpos($segment, '≫') !== false) ? mb_strstr($segment, '≫', true) : $segment;
            $name = null;

            // --- 1. prefix_map (汎用) ---
            if ($last_key && isset($prefix_map[$last_key]['children'][$key])) {
                $name = $prefix_map[$last_key]['children'][$key]['name'];
            } elseif (isset($prefix_map[$key])) {
                $name = $prefix_map[$key]['name'];
            }

            // --- 2. contextual_definitions (文脈依存：多重カンマ区切り対応) ---
            if (!$name && $index >= 2) {
                $context_root = $ancestor_keys[$index - 2] ?? null;

                if ($context_root) {
                    // $context_map のキー（例: "∫,∬01"）をループして判定
                    foreach ($context_map as $root_keys => $groups) {
                        $valid_roots = explode(',', $root_keys);

                        // 1. ルート（第1階層）が一致するか
                        if (in_array($context_root, $valid_roots)) {
                            // 2. グループ（第2階層：T,M等）をループして判定
                            foreach ($groups as $group_keys => $id_map) {
                                $valid_groups = explode(',', $group_keys);

                                if (in_array($last_key, $valid_groups)) {
                                    // 3. IDが一致するか
                                    if (isset($id_map[$key])) {
                                        $val = $id_map[$key];
                                        $name = is_array($val) ? ($val[0] ?? null) : $val;
                                        break 2; // ルートのループまで抜ける
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $resolved_names[] = $name;
            $ancestor_keys[]  = $key;
            $last_key         = $key;
        }

        return $resolved_names;
    }
}