<?php
/**
 *[Path]: inc/core/class-kx-outline_manager.php
 */

 /*
    // Dy::set('outline', [ $post_id => $data ]) の中身
    $data = [
        'stack' => [  // 分析・抽出された純粋なリスト
            ['level' => 2, 'title' => '見出し1', 'anchor' => 'outline_1'],
            ['level' => 3, 'title' => '子見出し', 'anchor' => 'outline_1_1'],
        ],
        'meta' => [   // 付属情報（旧ロジックの装飾フラグやカウント数など）
            'sprintf_on' => true,
            'has_emoji'  => false,
            'total_h2'   => 12
        ]
    ];
 */

namespace Kx\Core;

use Kx\Core\DynamicRegistry as Dy;
//use \Kx\Utils\KxMessage as Msg;
use Kx\Utils\KxTemplate;

class OutlineManager {

/**
     * ★ アウトライン記号の装飾設定
     * キー（記号）を見つけたら、値（HTML）に置き換えます。
     */
    private static $symbol_decorations = [
        '★' => '<i style="color:red; font-style:normal;">★</i>',
        '▲' => '<i style="color:red; font-style:normal;">▲</i>',
        '■' => '<i style="color:Blue; font-style:normal;">■</i>',
    ];

    /**
     * 解析・アンカー注入・Dy格納
     * @param string $content 対象テキスト
     * @param int $post_id 投稿ID（格納先となる親ID）
     * @param string $prefix 'a'(本体), 'b'(ループ1), 'c'(ループ2)...
     */
    public static function analyze_and_inject($content, $post_id, $prefix = 'a') {

        // 1. 専用メソッドを使用してフラットなデータを取得
        $data = Dy::get_outline($post_id);

        //echo kx_dump(Dy::get('outline')[ 323511]);

        // デバッグ用：構造が崩れていないか確認（問題なければ削除）
        // echo kx_dump(Dy::get('outline'));

        // 2. 指定されたprefixが既に処理済みなら、解析をスキップ

        if (!empty($data['processed'][$prefix])) {
            return $content;
        }

        if($prefix ==='sc'){
            $prefix = 'a';
        }

        $h_levels = '2-6';
        $sub_levels = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0];

        // 保存されていた prefix_count を開始位置として使用する
        $prefix_count = isset($data['prefix_count']) ? (int)$data['prefix_count'] : 1;

        if (preg_match_all('/<h([' . $h_levels . '])>(.*?)<\/h\1>/i', $content, $matches, PREG_SET_ORDER)) {

            // 実際に出現した最小のhレベルを特定
            $found_levels = array_map(function($m) { return (int)$m[1]; }, $matches);
            $min_level = min($found_levels);

            foreach ($matches as $m) {
                $level = (int)$m[1];
                $title_clean = strip_tags($m[2]);

                // 階層番号の計算
                $sub_levels[$level]++;
                for ($i = $level + 1; $i <= 6; $i++) { $sub_levels[$i] = 0; }

                $path = [];
                for ($i = $min_level; $i <= $level; $i++) {
                    $path[] = $sub_levels[$i];
                }
                $dot_number = implode('.', $path);

                // キーとアンカーの生成 (例: a1, a2... / b1, b2...)
                $key = $prefix . $prefix_count;
                $anchor = "outline_{$post_id}_{$key}";

                // stackに追記（既存のa系統などを壊さない）
                $data['stack'][$key] = [
                    'level'      => $level,
                    'dot_number' => $dot_number,
                    'title'      => self::apply_decorations($title_clean),
                    'anchor'     => $anchor,
                    'origin'     => $prefix
                ];

                // 本文へのID注入（置換対象を「IDを持っていないタグ」に限定する）
                $content = preg_replace(
                    '/<h([' . $h_levels . '])(?![^>]*id=)>/i', // すでにid属性があるものはスキップ
                    "<h$1 id=\"{$anchor}\" class='kx-outline-anchor-target'>",
                    $content,
                    1
                );

                $prefix_count++;
            }
        }

        // 3. 処理済みマークと、進んだ prefix_count を保存データに反映
        $data['processed'][$prefix] = true;
        $data['prefix_count'] = $prefix_count; // 次回の呼び出しのためにカウントを更新
        $data['id'] = $post_id;

        //echo var_dump($data);

        // 4. Dy系へ保存（専用メソッドを使用してフラットに保存）
        // ここで [ $post_id => $data ] と包むと入れ子になるため、そのまま渡す
        Dy::set_outline($post_id, $data);

        //Dy::get_outline($post_id);
        //echo kx_dump(Dy::get_outline($post_id));

        return $content;
    }


    /**
     * raretu等のループから個別に見出しをアウトラインに追加する。
     * * @param int    $post_id  データを格納する対象の投稿ID
     * @param string $title    追加する見出しのタイトル
     * @param string $entry_id エントリーID
     * @param array  $args     ['time_slug' => '00-00'] などの追加引数
     * @param int    $level    基本となる階層レベル（デフォルトは2）
     * @return string ジャンプ先として機能するID付きのHTML（spanタグ）
     */
    public static function add_from_loop($post_id, $title, $entry_id = '', $args = [], $level = 2) {
        $data = Dy::get_outline($post_id);
        $prefix_count = isset($data['prefix_count']) ? (int)$data['prefix_count'] : 1;

        $time_slug = $args['time_slug'] ?? '';

        // 1. 階層レベルを動的に判定
        $final_level = self::determine_level_by_time_slug($data['stack'] ?? [], $time_slug, $title, $level);

        // 2. ドット番号の計算
        $dot_number = (string)$prefix_count;
        if ($final_level > 2) {
            $parent_dot = '';
            $sub_count = 0;
            // 直近の親（自分より1つ上のレベル）を探す
            foreach (array_reverse($data['stack']) as $item) {
                if ($item['level'] === ($final_level - 1)) {
                    $parent_dot = $item['dot_number'];
                    break;
                }
            }
            // 同一親を持つ、同レベルの兄弟要素をカウント
            foreach ($data['stack'] as $item) {
                if ($item['level'] === $final_level) {
                    if ($parent_dot !== '' && strpos($item['dot_number'], $parent_dot . '.') === 0) {
                        $sub_count++;
                    }
                }
            }
            $dot_number = $parent_dot ? $parent_dot . '.' . ($sub_count + 1) : (string)$prefix_count;
        }

        $prefix = 'm';
        $key = $prefix . $prefix_count;
        $anchor = "outline_{$post_id}_{$key}";

        $data['stack'][$key] = [
            'entry_id'   => $entry_id,
            'level'      => $final_level,
            'dot_number' => $dot_number,
            'title'      => self::apply_decorations($title),
            'title_raw'  => $title, // 比較判定用に装飾前を保持
            'anchor'     => $anchor,
            'origin'     => $prefix,
            'time_slug'  => $time_slug // 判定用に保存
        ];

        $data['prefix_count'] = $prefix_count + 1;
        Dy::set_outline($post_id, $data);

        return sprintf('<span id="%s" class="kx-outline-anchor-target"></span>', esc_attr($anchor));
    }

    /**
     * time_slug およびタイトル文字列から適切な階層レベル（h2-h4）を決定する
     */
    private static function determine_level_by_time_slug($stack, $current_slug, $current_title, $base_level) {
        if (empty($stack)) return $base_level;

        // 1. 直近のアイテムと、直近の「H2」アイテムを特定
        $last_item = end($stack);
        $last_h2 = null;
        foreach (array_reverse($stack) as $item) {
            if ($item['level'] === 2) {
                $last_h2 = $item;
                break;
            }
        }

        $last_h2_title = $last_h2 ? ($last_h2['title_raw'] ?? strip_tags($last_h2['title'])) : '';
        $last_title    = $last_item['title_raw'] ?? strip_tags($last_item['title']);// 現在は使用していないが、直近のタイトル比較が必要になった時のために保持
        $last_slug     = $last_item['time_slug'] ?? '';

        // --- A. グループ判定（前方一致） ---
        // 直近のH2タイトルを自分が含んでいる場合、H3（子要素）とする
        // これにより「方針(h2) -> 方針1(h3) -> 方針2(h3)」が成立する
        if ($last_h2_title !== '') {
            if (mb_strpos($current_title, $last_h2_title) === 0 && $current_title !== $last_h2_title) {
                return 3;
            }
        }

        // --- B. time_slug による判定 ---
        if ($current_slug && $last_slug) {
            $current_parts = explode('-', $current_slug);
            $last_parts    = explode('-', $last_slug);

            $curr_left  = $current_parts[0] ?? '';
            $curr_right = $current_parts[1] ?? '';
            $last_left  = $last_parts[0] ?? '';
            $last_right = $last_parts[1] ?? '';

            // 左側（10-00の「10」など）が一致する場合
            if ($curr_left !== '' && $curr_left === $last_left) {
                if ($curr_right !== '' && $last_right !== '') {
                    // 直前がh3の場合のみ、右側の連続性をチェックしてh4にするか判定
                    if ($last_item['level'] === 3) {
                        $curr_r_int = (int)preg_replace('/[^0-9]/', '', $curr_right);
                        $last_r_int = (int)preg_replace('/[^0-9]/', '', $last_right);

                        // 右側数値が同一、または「+1（連続）」の場合のみh4へ（詳細な追記とみなす）
                        if ($curr_r_int === $last_r_int || $curr_r_int === $last_r_int + 1) {
                            return 4;
                        }
                    }
                    return 3; // それ以外はh3並列
                }
                return 3;
            }
        }

        // 条件に合致しなければ元のレベル（通常は2）を返す
        return $base_level;
    }


    /**
     * キャッシュ保存用に現在の全データを取得
     */
    public static function get_data_for_cache($post_id) {
        if (!$post_id) return [];

        // Dy::get_outline を通すことで、マトリョーシカ状態ではない綺麗な配列を保証
        $data = Dy::get_outline($post_id);

        if (empty($data['stack'])) {
            return [];
        }

        return $data;
    }


    /**
     * キャッシュから復元（既存のDyデータを消さずにマージ）
     */
    public static function restore_to_dy($post_id, $cached_data) {
        if (!$post_id || empty($cached_data)) return;

        $current = Dy::get_outline($post_id);

        // 修正：array_replace_recursive は数値キーを破壊するため使用禁止
        // キーごとに上書きする
        foreach ($cached_data as $key => $val) {
            $current[$key] = $val;
        }

        Dy::set_outline($post_id, $current);
    }

    /**
     * アウトラインのレンダリング
     * @param int|null $post_id 指定がない場合は現在のグローバルな投稿IDを使用
     * @param bool $echo 即座に出力するか、文字列として返すか
     * @return string|void
     */
    public static function render($post_id = null,$type = 'card' , $echo = true) {
        if (!$post_id) {
            $post_id = get_the_ID();
        }

        if (!$post_id) return '';

        // 1. Dy側の専用メソッドで取得（確実に stack, processed が存在する状態にする）
        $data = Dy::get_outline($post_id);

        $path_index = Dy::get_path_index($post_id);
        if ($type==='side' && !empty($path_index['markers']['matrix_grid'])) {
            $type= 'side_matrix_grid';

        }

        // 2. stackが空なら何も表示しない
        if (empty($data['stack'])) {
            return '';
        }

        // 30個制限ロジック ---
        if ($type === 'card' && count($data['stack']) > 30) {
            // h6 から h3 まで順に削る
            for ($level = 6; $level >= 3; $level--) {
                foreach ($data['stack'] as $key => $item) {
                    if ($item['level'] === $level) {
                        unset($data['stack'][$key]);
                    }
                    // 30個以下になったら終了
                    if (count($data['stack']) <= 30) break 2;
                }
            }
        }

        //echo kx_dump(Dy::get('outline')[$post_id]['stack']);

        // 3. テンプレートに渡す変数の準備
        $args = [
            'type'      => $type,
            'post_id'   => $post_id,
            'items'     => $data['stack'],     // a系統, b系統が統合された全リスト
            'processed' => $data['processed']  // 処理済みフラグ
        ];

        // KxTemplateを使用して表示層（View）を呼び出す
        return KxTemplate::get('components/navigation/outline', $args, $echo);
    }

    /**
     * タイトルの記号を装飾する内部メソッド
     */
    private static function apply_decorations($title) {
        if (empty(self::$symbol_decorations)) return $title;
        return str_replace(
            array_keys(self::$symbol_decorations),
            array_values(self::$symbol_decorations),
            $title
        );
    }
}