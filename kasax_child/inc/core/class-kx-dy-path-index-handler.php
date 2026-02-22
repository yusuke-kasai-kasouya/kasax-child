<?php
/**
 * [Path]: inc\core\class-kx-dy-path-index-handler.php
 * DyDomainHandler::: 各ドメインロジックの基底抽象クラス
 */

namespace Kx\Core;

use Su;

abstract class DyPathIndexHandler {

    /**
     * ストレージから生データを取得し、ロジックを適用して返す
     */
    protected static function get_from_storage(string $domain  ) {
        return DyStorage::retrieve($domain);
    }

    /**
     * パス構造の解析ロジック（移設のメイン実体）
     * 冗長なSQL発行を厳禁とする鉄則を遵守
     */
    public static function process_path_analysis(int $post_id, $mode = ''): ?array {
        if (!$post_id) return null;

        // 1. 金庫から現在の状態を確認（重複処理の防止）
        $storage = DyStorage::retrieve('path_index') ?:[];
        if (isset($storage[$post_id])) {
            return $storage[$post_id];       }



        // 1. 唯一の get_post 実行
        $post = get_post($post_id);

        // 2. 基本情報の抽出
        $title    = $post ? $post->post_title  : '';
        $wp_type  = $post ? $post->post_type   : 'unknown';
        $status   = $post ? $post->post_status : 'none';
        $modified = $post ? $post->post_modified : null; // 更新時間を取得

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

        $parent_parts = array_slice($parts, 0, -1);

        $part_names = self::resolve_segment_names($parts);
        $count = count($parts);

        $name_count = $count - 1;

        $last_part_name = $part_names[$name_count] ?? '';



        $entry = [
            'full'          => $title,
            'parts'         => $parts,
            'parts_names'   => $part_names,
            'parent_path'   => implode('≫', $parent_parts), //文字列としての親パス
            'last_part'     => $last_part,    // ラストパーツ全体
            'last_part_name'=> $last_part_name,    // ラストパーツ全体
            'time_slug'     => $time_element, // ＠より前の要素（10-11等）
            'at_name'       => $clean_name,   // ＠より後の純粋な名称

            'depth'         => $count,
            'wp_type'       => $wp_type,
            'status'        => $status,
            'modified'      => $modified, // Dyに追加
            'valid'         => $is_valid,
            'type'          => 'default', // 仮置き
            'genre'         => 'none', // 仮置き
            'markers'         => [],        // 追加
        ];



        // 4. 注意：identify_post_type 内での get_title 呼び出しに備えて先行登録
        $storage[$post_id] = $entry;
        DyStorage::store('path_index', $storage);

        // 5. システムタイプ・フラグ判定
        $attr = self::identify_post_attributes($post_id , $mode );
        $entry['type']   = $attr['type'];  // 例: 'Μ', 'σ'
        $entry['genre']  = $attr['genre'];  // 例: 'strat_sales', 'arc_psy_game_theory'
        $entry['markers']  = $attr['markers']; // 例: 'prod_character_core', 'prod_character_relation'


        // 4. 金庫（Storage）へ保存
        $storage[$post_id] = $entry;
        DyStorage::store('path_index', $storage);

        return $entry;
    }

    /**
     * PostIDから接頭辞等のルールに基づき、システム上の属性（type, genre, flags）を特定する
     * * @param int $post_id
     * @return array {
     * @var string $type  大カテゴリ（Μ, σ, κ等）
     * @var string $genre 小カテゴリ（identifier_schema.jsonのキー名）
     * @var array  $flags BOOLEAN_FLAGSに基づく有効なフラグ群
     * }
     */
    private static function identify_post_attributes($post_id , $mode = '') {
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
     * パスセグメントの配列から、それぞれの「名称」を特定して配列で返す
     * ContextRoot と GroupKeys の両方でカンマ区切り（複数マッチ）に対応
     */
    private static function resolve_segment_names(array $parts) {
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