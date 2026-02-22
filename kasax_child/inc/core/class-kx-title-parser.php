<?php
/**
 * [Path]: inc\core\class-kx-title-parser.php
 */

namespace Kx\Core;

use Kx\Core\SystemConfig as Su;
use Kx\Core\DynamicRegistry as Dy;

class TitleParser {

    /**
     * 指定された型（タイプ）または属性に合致するか判定する
     * * 投稿タイトルを分解したパーツ配列が、スキーマで定義された検出パターンを満たすか確認します。
     * $type_name に配列を渡すことで、複数条件の論理判定（AND/OR）が可能です。
     *
     * @param string|array $type_name 判定対象のタイプ名、またはその配列（detectionキー）
     * @param int|null $post_id 対象の投稿ID。nullの場合は現在の投稿（Loop内等）を対象とする
     * @param string $logic 配列受け取り時の論理演算子。'OR'（いずれかに一致）または 'AND'（すべてに一致）
     * @return bool 判定結果。合致すれば true、そうでなければ false
     * * @description
     * 1. **再帰処理**: $type_name が配列の場合、$logic に基づいて自身を再帰的に呼び出し、結果を統合する。
     * 2. **スキーマ照合**: 単一の $type_name に対し `identifier_schema.json` から判定パターンを取得。
     * 3. **Strictモード**: パターン内に `"strict": true` がある場合、パーツの総数と定義階層数が一致しなければ false を返す。
     * 4. **階層判定**: パターンの数値キー（0, 1, 2...）ごとに、対応するタイトルのパーツを前方一致でチェック。
     * - 条件が配列（@参照含む）の場合：いずれかの要素に前方一致すればその階層はパス（OR）。
     * - すべての階層条件をクリアした場合のみ、最終的に true を返す。
     */
    public static function is_type($type_name, $post_id = null, $logic = 'OR') {
        // --- 配列受け取り時の再帰処理 ---
        if (is_array($type_name)) {
            foreach ($type_name as $tn) {
                $res = self::is_type($tn, $post_id);
                if ($logic === 'AND' && !$res) return false; // 一つでも失敗なら即座に不適合
                if ($logic === 'OR' && $res) return true;    // 一つでも成功なら即座に適合
            }
            return ($logic === 'AND'); // ANDなら完走でtrue、ORなら完走でfalse
        }
        $parts = self::get_parts($post_id);
        if (!$parts) return false;

        $schema = Su::get('identifier_schema');
        $pattern = $schema['detection'][$type_name] ?? null;

        if (!$pattern) return false;

        // --- 階層の一致チェック ---
        if (!empty($pattern['strict'])) {
            // 数値キーの数（階層の深さ）をカウント
            $defined_depth = count(array_filter(array_keys($pattern), 'is_numeric'));
            // 実際のパーツ数と異なれば、即座に false
            if (count($parts) !== $defined_depth) {
                return false;
            }
        }


        foreach ($pattern as $idx => $condition) {
            // 重要：数値キー（階層インデックス）以外は判定ロジックから除外する
            if (!is_numeric($idx)) continue;

            $current = $parts[$idx] ?? '';
            $condition = self::resolve_condition($condition, $schema);

            if (is_array($condition)) {
                $match = false;
                foreach ($condition as $r) {
                    if (mb_stripos($current, $r) === 0) {
                        $match = true;
                        break;
                    }
                }
                if (!$match) return false;
            } else {
                if (mb_stripos($current, $condition) !== 0) return false;
            }
        }
        return true;
    }


    /**
     * タイトルがいずれの定義済み型に該当するかを判定して返す
     * 判定結果の型名と、紐づくメタデータを連想配列で返す。
     *
     * @param int|null $post_id
     * @return array|null ['type' => string, 'attr' => mixed]、見つからない場合は null
     */
    public static function detect_type($post_id = null) {
        $parts = self::get_parts($post_id);
        if (empty($parts)) return null;

        $first_char = mb_substr($parts[0], 0, 1);
        $candidates = self::get_candidate_types($first_char);

        foreach ($candidates as $type_name) {

            //識別子でループを終了
            if ($type_name === "---[ TERMINATE_TYPE_DETECTION ]---") {
                break;
            }

            if (self::is_type($type_name, $post_id)) {
                return $type_name;
            }
        }

        return null;
    }

    /**
     * 型判定に基づき、タイトルパーツの正規化とメタデータの統合を行う
     * * @description
     * 1. 投稿タイトルを分解した各パーツから、定義されたプレフィックスを除去して「純粋な値」を抽出する。
     * 2. スキーマの detection 内にある数値キー（解析ルール）と文字列キー（メタデータ）を分離して処理。
     * 3. 数値キーに対しては、プレフィックスの特定および mb_substr によるカットを実行。
     * 4. 文字列キー（node_sort_order 等）に対しては、設定値をそのまま結果セットにマージする。
     *
     * @param string $type_name 判定・抽出の基準となるスキーマ上の型名（detectionのキー）
     * @param int|null $post_id 対象の投稿ID。nullの場合は現在の投稿
     * @return array|null 解析結果の連想配列。型が不一致の場合は null
     * [
     * 0 => "抽出値1",
     * 1 => "抽出値2",
     * "node_sort_order" => ["A", "B"] // メタデータが混在する
     * ]
     */
    public static function get_attr($type_name, $post_id = null) {
        if (!self::is_type($type_name, $post_id)) return null;

        $parts = self::get_parts($post_id);
        $schema = Su::get('identifier_schema');
        $pattern = $schema['detection'][$type_name];

        $results = [];
        foreach ($pattern as $idx => $condition) {
            if (!is_numeric($idx)) {
                $results[$idx] = $condition;
                continue;
            }

            $condition = self::resolve_condition($condition, $schema);

            // 実際にマッチしたプレフィックス文字列を特定してカット
            $matched_pfx = is_array($condition)
                ? self::find_matched_prefix($parts[$idx], $condition)
                : $condition;
            $results[$idx] = mb_substr($parts[$idx], mb_strlen($matched_pfx));
        }
        return $results;
    }

    /**
     * @参照を解決して実際の条件（文字列 or 配列）を返す
     */
    private static function resolve_condition($condition, $schema) {
        if (!is_array($condition) && strpos($condition, '@') === 0) {
            $ref_key = substr($condition, 1);

            return $schema['common_prefixes'][$ref_key] ?? $condition;
        }
        return $condition;
    }

    /**
     * マッチしたプレフィックスを特定
     */
    private static function find_matched_prefix($text, $prefixes) {
        foreach ($prefixes as $p) {
            if (mb_stripos($text, $p) === 0) return $p;
        }
        return '';
    }


    /**
     * タイトル分解配列を取得
     * 引数が配列ならそのまま返し、数値ならキャッシュから取得する
     */
    private static function get_parts($post_id) {
        $full_title   = Dy::get_title($post_id);
        return Dy::get_path_index($post_id)['parts'] ?? explode('≫', $full_title );

    }


    /**
     * systemセクションの設定値を取得する
     * * @param string $key 'separator', 'encoding' など
     * @return mixed
     */
    public static function get_system($key) {
        $schema = Su::get('identifier_schema');
        return $schema['system'][$key] ?? null;
    }






    /**
     * 先頭文字に基づいて判定すべきスキーマ（型名）を間引く
     *
     * @param string $char 先頭の1文字
     * @return array 該当する可能性のある型名の配列
     */
    private static function get_candidate_types($char) {
        static $index_cache = null;

        // インデックスを初回呼び出し時に1回だけ構築（staticキャッシュ）
        if ($index_cache === null) {
            $index_cache = [];
            $schema = Su::get('identifier_schema');
            $detection = $schema['detection'] ?? [];

            foreach ($detection as $type_name => $pattern) {
                if (!isset($pattern[0])) continue;

                // インデックス[0]の条件（@参照を含む）を解決
                $cond0 = self::resolve_condition($pattern[0], $schema);

                // 配列（["γ","σ","δ"]等）を許容してプレフィックスリスト化
                $prefixes = is_array($cond0) ? $cond0 : [$cond0];

                foreach ($prefixes as $pfx) {
                    // プレフィックスの先頭1文字をキーとして型名を登録
                    $key = mb_substr($pfx, 0, 1);
                    $index_cache[$key][] = $type_name;
                }
            }
        }

        return $index_cache[$char] ?? [];
    }



    /**
     * 指定されたキーのメタデータを取得する
     */
    public static function get_type_meta($type_name, $meta_key = 'node_sort_order') {
        $schema = Su::get('identifier_schema');
        return $schema['detection'][$type_name][$meta_key] ?? null;
    }

}