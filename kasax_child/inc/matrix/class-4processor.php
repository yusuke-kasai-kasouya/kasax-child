<?php
/**
 *[Path]: inc/core/matrix/class-orchestrator.php
 */



namespace Kx\Matrix;

use Kx\Core\SystemConfig as Su;
use Kx\Core\DynamicRegistry as Dy;
use Kx\Core\KxDirector as kx;

use Kx\Core\TitleParser;
//use Kx\Core\KxQuery;
use Kx\Database\dbkx0_PostSearchMapper as dbkx0;
use Kx\Core\ContextManager;
//use \Kx\Utils\KxMessage as Msg;



class Processor {
    private $collection;
    private $context;
    private $atts;
    private $post_id;
    private $origin_path;
    private $virtuals;

    /**
     * Processor コンストラクタ
     * * @param array  $collection DataCollectorによって収集された、各IDごとの詳細データ群
     * @param string $context    表示コンテキスト (timetable_matrix, vertical_timeline 等)
     * @param array  $atts       ショートコード属性一式（ソート設定や表示オプションを含む）
     */
    public function __construct(array $collection, string $context, array $atts) {
        $this->collection = $collection;
        $this->context    = $context;
        $this->atts       = $atts;
        $this->post_id = $atts['post_id'];
        $this->origin_path = Dy::get_path_index($this->atts['post_id'])??null;
    }

    /**
     * 指定されたコンテキストに基づいて、表示用のデータ構造（Matrix）を構築する
     * * 各コンテキスト専用のビルドメソッドを呼び出し、
     * レンダラーが処理しやすい形式の配列（items, config等を含む構造体）を生成する。
     * * @return array ビルド済みのデータ構造体
     */
    public function build() {
        switch ($this->context) {
            case 'timetable_matrix':
                return $this->build_timetable_matrix();
            case 'vertical_timeline':
                return $this->build_linear_list();
                //return $this->build_vertical_timeline();
            case 'default_list':
            default:
                return $this->build_linear_list();
        }
    }

    /**
     * 通常のリスト構造の構築（実データ構造への最適化）
     */
    private function build_linear_list() {
        $items = [];

        foreach ($this->collection as $id => $data) {
            $path = Dy::set_path_index($id)??[];

            // 1. 基本情報の整理 (raw層)
            $db0 = $data['raw']['db_kx0'] ?? [];
            //$db1 = $data['raw']['db_kx1'] ?? [];
            $dbh = $data['raw']['db_kx_hierarchy'] ?? [];
            //$dbs = $data['raw']['db_kx_shared'] ?? [];

            // 2. 階層・論理情報の整理 (ana層)
            $node = $data['ana']['node'] ?? null;

            // 3. 表示装飾の整理 (vis層)
            $vis = $data['vis'] ?? [];

            // タイトル調整
            $title = $path['at_name'];
            if (!empty($this->atts['label_type'])) {
                if( $this->atts['label_type'] ==='time' && !empty($path['time_slug']) ){
                    $title = $path['time_slug'] .'：'.$path['at_name'];
                }
            }

            // Rendererがそのまま使える「描画用パケット」を作成
            $items[] = [
                'id'         => $id,
                'time_slug'  => $path['time_slug'],
                'title'      => $title,
                'full_title' => $db0['title'] ?? '',
                'type'       => $db0['type'] ?? 'default',
                'level'      => $dbh['level'] ?? 0,
                'is_folder'  => $node['is_folder'] ?? false,
                'atlas'      => $vis['atlas'] ?? null, // 色彩設定
                'updated_at' => $db0['wp_updated_at'] ?? '',

                // 特定のフラグがあれば付与（例：概要ポストなど）
                'is_overview'=> !empty($data['ana']['control']['overview_to']),

                // リンク先パス（必要に応じて）
                'parent_path'=> $dbh['parent_path'] ?? '',
            ];
        }

        // 2. 仮想階層フラグの注入。virtual_descendantsは直接配列に追加。
        if (!empty($this->virtuals)) {
            $items[] = [
                    'id'          => 0, // 物理IDはない
                    'type'        => 'virtual_flag',
                    'title'       => '',
                    'virtual_path'=> '', // リンク生成に使用
                    'temp_sort_val' => '', // 仮想階層を末尾に置く等のソート制御用
                ];

        }


        // 1. ソート実行の判定
        $use_custom_sort = false;
        $target_sort_key = '';

        if (!empty($this->atts['sort'])) {
            $sort_val = $this->atts['sort'];
            $table    = $this->atts['table'] ?? '';
            // shared系テーブルか判定
            $is_shared = ($table === 'shared' || strpos($table, 'kx_shared_title') !== false);

            if ($is_shared) {
                if ($sort_val === 'date') {
                    $use_custom_sort = true;
                    $target_sort_key = 'date';
                } elseif ($sort_val === 'json' && !empty($this->atts['where_json'])) {
                    // sort=json の場合は where_json があれば有効
                    $use_custom_sort = true;

                    // where_json="key:val" からソート対象の "key" を抽出
                    $json_parts = explode(':', $this->atts['where_json']);
                    $target_sort_key = trim($json_parts[0]);
                }
            }
        }

        // 2. 実行分岐
        if ($use_custom_sort && !empty($target_sort_key)) {
            // 明示的な指定に基づく単純ソート
            $items = $this->sort_by_specified_key($items, $target_sort_key);
        } else {
            // 条件に合致しない、または指定がない場合は既存の多段文脈ソート
            $items = $this->sort_items($items);
        }

        // 必要に応じてここでソート（例：level順やタイトルの数値順など）
        return [
            'post_id' => $this->atts['post_id'],
            'context'  => $this->context,
            'items' => $items,
            'count' => count($items),
            'virtual_descendants' => Dy::get_content_cache($this->post_id, 'virtual_descendants') ?: []
        ];
    }


    /**
     * 多段比較ソートロジック（一般アイテムのタイトル順を追加）
     */
    private function sort_items(array $items) {
        $default_sort_order = Su::get('identifier_schema')['detection']['default']['node_sort_order'];
        $genre_sort_setting = TitleParser::get_type_meta($this->origin_path['genre'], 'node_sort_order') ?? $default_sort_order ?? [];

        /**
         * キーワード決定の優先順位：
         * 1. SCの 'top' / 'bottom' (新しい明示的な指定)
         * 2. SCの 'j' / 'je' (従来の指定)
         * 3. ジャンル別設定 (DB/meta)
         */
        $top_raw = !empty($this->atts['sort_top'])
                    ? $this->atts['sort_top']
                    : (!empty($this->atts['j']) ? $this->atts['j'] : ($genre_sort_setting[0] ?? ''));

        $bottom_raw = !empty($this->atts['sort_bottom'])
                    ? $this->atts['sort_bottom']
                    : (!empty($this->atts['je']) ? $this->atts['je'] : ($genre_sort_setting[1] ?? ''));

        // 配列化
        $top_keywords    = array_filter(array_map('trim', explode(',', $top_raw)));
        $bottom_keywords = array_filter(array_map('trim', explode(',', $bottom_raw)));


        Dy::set_info($this->atts['post_id'],[
            'top_keywords'    => $top_raw,
            'bottom_keywords' => $bottom_raw,
        ]);

        // 前方一致判定用のクロージャ
        $startsWithAny = function($title, $keywords) {
            foreach ($keywords as $index => $kw) {
                if ($kw !== '' && strpos($title, $kw) === 0) {
                    return $index; // trueではなく添字を返す
                }
            }
            return false;
        };

        usort($items, function($a, $b) use ($top_keywords, $bottom_keywords, $startsWithAny) {

            // --- 第1優先: time_slug (時間軸) ---
            $hasTimeA = !empty($a['time_slug']);
            $hasTimeB = !empty($b['time_slug']);

            if ($hasTimeA !== $hasTimeB) {
                return $hasTimeB <=> $hasTimeA;
            }
            if ($hasTimeA && $hasTimeB) {
                $cmp = strnatcmp($a['time_slug'], $b['time_slug']);
                if ($cmp !== 0) return $cmp;
            }

            // --- 第2優先: node_sort_order による特定タイトルの位置制御 ---

            // 先行キーワードチェック（インデックス順を優先）
            $isTopA = $startsWithAny($a['title'], $top_keywords);
            $isTopB = $startsWithAny($b['title'], $top_keywords);
            if ($isTopA !== $isTopB) {
                if ($isTopA === false) return 1;
                if ($isTopB === false) return -1;
                return $isTopA <=> $isTopB; // 両方一致ならキーワード配列の記述順
            }

            // 末尾キーワードチェック（インデックス順を優先）
            $isBottomA = $startsWithAny($a['title'], $bottom_keywords);
            $isBottomB = $startsWithAny($b['title'], $bottom_keywords);
            if ($isBottomA !== $isBottomB) {
                if ($isBottomA === false) return -1;
                if ($isBottomB === false) return 1;
                return $isBottomA <=> $isBottomB; // 両方一致ならキーワード配列の記述順
            }

            // --- 第3優先: 一般アイテム間のタイトル順 ---
            $title_cmp = strnatcmp($a['title'], $b['title']);
            if ($title_cmp !== 0) {
                return $title_cmp;
            }

            // --- 第4優先: updated_at ---
            if ($a['updated_at'] !== $b['updated_at']) {
                return strtotime($b['updated_at']) <=> strtotime($a['updated_at']);
            }

            return 0;
        });

        return $items;
    }


    /**
     * 指定されたキーによる単純ソート
     * sharedテーブル系かつ、sort=date または sort=json (where_jsonあり) の場合に実行
     */
    private function sort_by_specified_key(array $items, string $sort_key) {
        // 1. 昇順・降順の判定
        $order = 'DESC';

        // 判定パターンA: sort="date_asc" のような接尾辞指定がある場合
        if (strpos($this->atts['sort'], '_asc') !== false) {
            $order = 'ASC';
        }
        // 判定パターンB: order="ASC" という独立した属性がある場合
        elseif (!empty($this->atts['order']) && strtoupper($this->atts['order']) === 'ASC') {
            $order = 'ASC';
        }

        // 実際の比較に使用するキー
        $key = $sort_key;

        // 2. 比較用の値を各アイテムに注入
        foreach ($items as &$item) {
            // キャッシュからデータを取得 (shared_date または shared_json)
            // $this->atts['sort'] は 'date' や 'json' が入っている想定
            $raw_data = Dy::get_content_cache($item['id'], 'shared_' . $this->atts['sort']);

            if ($this->atts['sort'] === 'json') {
                // jsonは文字列なので配列に変換
                $decoded = json_decode($raw_data, true);
                // 指定されたキー（where_jsonの第一キー）の値をセット
                $item['temp_sort_val'] = $decoded[$key] ?? '';
            } elseif ($this->atts['sort'] === 'date') {
                // dateはbigint(20)なので数値として扱う
                $item['temp_sort_val'] = !empty($raw_data) ? (int)$raw_data : 0;
            } else {
                $item['temp_sort_val'] = $raw_data;
            }
        }
        unset($item); // 参照渡しを解除

        // 3. ソート実行
        usort($items, function($a, $b) use ($order) {
            $valA = $a['temp_sort_val'];
            $valB = $b['temp_sort_val'];

            if ($valA === $valB) return 0;

            // 数値（date等）か文字列（json内の値等）かを判定して比較
            if (is_numeric($valA) && is_numeric($valB)) {
                $cmp = $valA <=> $valB;
            } else {
                $cmp = strnatcmp((string)$valA, (string)$valB);
            }

            return ($order === 'ASC') ? $cmp : -$cmp;
        });

        return $items;
    }



    /**
     * タイムテーブル（ラテ欄）形式のデータ構造を構築する
     */
    private function build_timetable_matrix() {

        $pre_data = $this->prepare_matrix_grid();
        $character_map = $pre_data['characters'];
        $time_slots    = $pre_data['time_slots'];

        $matrix_grid = [];

        // キャラクターの並び順（インデックス）を取得
        $char_keys = array_keys($character_map);
        $total_count = count($char_keys);
        $half_point = floor($total_count / 2);

        $editor_modes = [];
        foreach ($char_keys as $index => $char_no) {
            $editor_modes[$char_no] = ($index < $half_point)
                ? 'matrix_editor_left'
                : 'matrix_editor_right';
        }

        // 主人公の年齢基準（相対計算用）
        $hero_no = $pre_data['hero_no'] ?? 0;
        $base_age_diff = $character_map[$hero_no]['age_diff'] ?? 0;


        $i = '';
        foreach ($time_slots as $slot) {
            $matrix_grid[$slot] = [];

            foreach ($character_map as $char_no => $char_info) {
                // 1. IDリストの取得
                $target_ids = $this->find_ids_by_time_and_member($slot, $char_info['descendants']);

                // 2. 学年（Grade）の計算ロジック
                $grade = '';
                if (strpos((string)$char_no, '8') !== 0 && strpos((string)$char_no, '9') !== 0) {
                    $age_offset = $base_age_diff - ($char_info['age_diff'] ?? 0);

                    $parts = explode('-', $slot);
                    $relative_age = (int)$parts[0] + $age_offset;

                    // サフィックス（月・日等）を維持してパース
                    $calc_slug = $relative_age . (isset($parts[1]) ? '-' . $parts[1] : '');
                    $parsed = \Kx\Utils\Time::parse_slug($calc_slug, $char_info['root_id']);
                    $grade = $parsed['grade'] ?? '';
                    $grade_html = "<spna class='kx-age' >{$grade}</span>";
                }
                else if( strpos((string)$char_no, '98') === 0){
                    $i++;
                    $page = ($i * 2) -1;
                    $grade_html = "P：{$page}";
                }

                // 3. グリッドデータへの格納
                $matrix_grid[$slot][$char_no] = [
                    'ids'   => !empty($target_ids) ? $target_ids : [],
                    'grade' => $grade_html ?? null,
                ];
                unset($grade_html);
            }
        }

        return [
            'post_id'      => $this->post_id,
            'time_slots'   => $time_slots,
            'characters'   => $character_map,
            'grid'         => $matrix_grid,
            'editor_modes' => $editor_modes,
            'hero_no'      => $hero_no,
            'hero_id'      => $pre_data['hero_id']
        ];
    }


    /**
     * タイムテーブル（ラテ欄）用の行列データを準備する
     * キャラクターごとのID群と、全キャラクター共通の時間軸（time_slots）を抽出・正規化文字列ベースでソート・フィルタリングする
     */
    private function prepare_matrix_grid() {
        $work    = Dy::get_work($this->post_id);
        $parts   = $this->origin_path['parts'];

        // 主人公番号の特定
        $hero_no = $work['hiro_no'] ?? (isset($parts[1]) ? ltrim($parts[1], 'c') : 0);
        $members = $work['members'] ?? [];

        // #1 表示期間の限定変数と正規化
        $limit_from = $work['timeline_from'] ?? null;
        $limit_to   = $work['timeline_to'] ?? null;

        $norm_from = $limit_from ? $this->normalize_time_slug($limit_from) : null;
        // toに関しては、その年や月の末尾まで含むよう z で補完して比較
        $norm_to   = $limit_to ? $this->normalize_time_slug($limit_to . '-zz') : null;

        $new_title_time = $limit_from ?: '00-00';

        $character_map = [];
        $temp_slots    = []; // [normalized_string => raw_slug]

        // 1. メインキャラクター（ヒロイン）のデータ収集
        $hero_title = $parts[0] . '≫' . $parts[1] . '≫来歴';
        $hero_ids   = dbkx0::get_ids_by_title($hero_title);
        $hero_id    = $hero_ids[0] ?? '';

        if ($hero_id) {
            ContextManager::sync($hero_id);
            // 1回だけ取得して変数に格納
            $raw_char_data = Dy::get_character($hero_id) ?? [];
            //echo $raw_char_data['age_diff'];
            $colormgr = Dy::get_color_mgr($hero_id) ?? [];

            $character_map[$hero_no] = [
                'root_id'     => $hero_id,
                'name'        => $raw_char_data['name'] ?? 'No Name',
                'age_diff'    => $raw_char_data['age_diff'] ?? 0,
                'colormgr'    => $colormgr ?? [],
                'descendants' => Dy::get_content_cache($hero_id, 'descendants') ?: [],
                'new_title'   => $hero_title.'≫'.$new_title_time.'＠New'
            ];
        }

        if(is_string($members)) $members = explode(',',$members);

        // 2. サブメンバー（関連キャラクター）のデータ収集
        foreach ($members as $num) {
            $member_title = $parts[0] . '≫c' . $num . '≫＼' . $parts[1] . '≫来歴';
            $m_ids = dbkx0::get_ids_by_title($member_title);
            $m_id  = $m_ids[0] ?? '';

            if ($m_id) {
                ContextManager::sync($m_id);
                // こちらも1回に集約
                $m_raw_data = Dy::get_character($m_id) ?? [];
                $m_colormgr = Dy::get_color_mgr($m_id) ?? [];

                $character_map[$num] = [
                    'root_id'     => $m_id,
                    'name'        => $m_raw_data['name'] ?? 'No Name',
                    'age_diff'    => $m_raw_data['age_diff'] ?? 0,
                    'colormgr'    => $m_colormgr ?? [],
                    'descendants' => Dy::get_content_cache($m_id, 'descendants') ?: [],
                    'new_title'   => $member_title.'≫'.$new_title_time.'＠New'
                ];
            }
        }

        //echo kx_dump($character_map);

        // 3. 全キャラクターから時間軸を抽出
        $internal_schema = Su::get('system_internal_schema') ?: [];
        $exclusion_map   = $internal_schema['timeline_exclusion_map'] ?? ['default' => [800]];

        // シリーズ名（パスの第1階層）を取得し、除外リストを決定
        $series_key     = $this->origin_path['parts'][0] ?? 'default';
        $excluded_chars = $exclusion_map[$series_key] ?? ($exclusion_map['default'] ?? []);

        foreach ($character_map as $char_num => $char_data) {
            // 例外設定されたキャラクター（800番等）はスキップ
            if (in_array((int)$char_num, $excluded_chars, true)) {
                continue;
            }

            foreach ($char_data['descendants'] as $id) {
                $path_index = Dy::set_path_index($id);
                $slug       = $path_index['time_slug'] ?? '';

                if ($slug === '') {
                    continue;
                }

                $norm_current = $this->normalize_time_slug($slug);

                // 期間フィルタリング（文字列比較：0-9 < a-z が成立する）
                if ($norm_from && $norm_current < $norm_from) continue;
                if ($norm_to   && $norm_current > $norm_to)   continue;

                $temp_slots[$norm_current] = $slug;
            }
        }

        // 4. 正規化キーで昇順ソートし、スラグ配列を確定させる
        ksort($temp_slots);
        $all_time_slugs = array_values($temp_slots);

        $character_map = $this->sort_characters_by_custom_rule($character_map, $hero_no);

        return [
            'characters'  => $character_map,
            'time_slots'  => $all_time_slugs,
            'hero_no'     => $hero_no,
            'hero_id'     => $hero_id,
            //'edu_table' => $edu_table
        ];
    }

    /**
     * タイムスラグをソート・比較用の文字列に正規化
     * 左辺(年) + 右辺(10桁埋め)
     * 例: "21-11a" -> "2111a00000"
     */
    private function normalize_time_slug($slug) {
        if (empty($slug)) return '0';

        $parts = explode('-', (string)$slug);
        $left  = $parts[0]; // 年
        $right = $parts[1] ?? ''; // 詳細

        // 右辺を10桁になるまで '0' で埋める（a-zはそのままで位置を固定）
        $right_padded = str_pad($right, 10, '0', STR_PAD_RIGHT);

        // 文字列として結合して返す
        return $left . $right_padded;
    }

    /**
     * キャラクターマップを特定の規則（9系優先 > 主人公 > その他）でソートする
     * * @param array $character_map 整列前のマップ
     * @param mixed $hero_no 主人公番号
     * @return array 整列後のマップ
     */
    private function sort_characters_by_custom_rule(array $character_map, $hero_no) {
        $sorted_map = [];
        $char_keys  = array_keys($character_map);

        // ① 9から始まる番号を最優先
        foreach ($char_keys as $k) {
            if (strpos((string)$k, '9') === 0) {
                $sorted_map[$k] = $character_map[$k];
            }
        }

        // ② 次に主人公を配置
        if (isset($character_map[$hero_no]) && !isset($sorted_map[$hero_no])) {
            $sorted_map[$hero_no] = $character_map[$hero_no];
        }

        // ③ 残りのメンバー（その他）を順次追加
        foreach ($character_map as $k => $v) {
            if (!isset($sorted_map[$k])) {
                $sorted_map[$k] = $v;
            }
        }

        return $sorted_map;
    }


    /**
     * 特定の時間スラグを持つすべてのIDを子要素の中から検索する
     */
    private function find_ids_by_time_and_member($slot, $descendant_ids) {
        $found = [];
        if (empty($descendant_ids)) return $found;

        foreach ($descendant_ids as $id) {
            $path_index = Dy::set_path_index($id);
            if (($path_index['time_slug'] ?? '') === (string)$slot) {
                $found[] = $id; // 一致するIDをすべて追加
            }
        }
        return $found;
    }


    /** Orchestratorからデータを受け取るためのメソッド */
    public function set_virtuals(array $virtuals) {
        $this->virtuals = $virtuals;
    }
}