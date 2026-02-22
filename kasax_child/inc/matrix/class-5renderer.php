<?php
/**
 *[Path]: inc/core/matrix/class-orchestrator.php
 */
namespace Kx\Matrix;

//use Kx\Core\SystemConfig as Su;
use Kx\Core\DynamicRegistry as Dy;
use Kx\Utils\KxTemplate;

/**
 * Class Renderer
 */
class Renderer {

    /**
     * @param array  $matrix  Processorが生成したデータ構造体
     * @param string $context 表示モード (default_list, timetable_matrix等)
     * @return string 生成されたHTML
     */
    public static function render(array $matrix, string $context) {
        if (empty($matrix['items']) && $context !== 'timetable_matrix') {
            return "";
        }

        // 1. コンテキストに基づいてテンプレートファイルを選択
        $template_name = self::get_template_name($context);

        // 2. KxTemplate を使用してHTMLをバッファリング出力
        // 第二引数にデータを渡すことで、テンプレート内で $matrix として参照可能
        return KxTemplate::get(
            $template_name,
            ['matrix' => $matrix],
            false // 直接出力せず文字列として返す
        );
    }

    /**
     * コンテキスト名からテンプレートパスを解決する
     */
    private static function get_template_name(string $context) {
        switch ($context) {
            case 'timetable_matrix':
                return 'matrix/timetable-board';
            case 'vertical_timeline':
                return 'matrix/linear-list';
                //return 'matrix/vertical-list';
            case 'outline_list': // 再帰階層用
                return 'matrix/outline-list';
            case 'default_list':
            default:
                return 'matrix/linear-list';
        }
    }

    /**
     * アウトライン形式（軽量リスト）のHTMLを生成する
     * * * 主にMatrixの再帰呼び出し時（2階層目以降）に使用される。
     * * 抽出されたアイテムをOutlineManagerに登録し、目次構造を生成した上で、
     * 概要カード(PostCard)等と組み合わせてテンプレートへ出力する。
     *
     * @param array $matrix  Processorによって構造化されたデータ（itemsを含む）
     * @param int   $post_id 出力の起点となる現在の投稿ID
     * @return string 生成されたアウトラインHTML
     */
    public static function render_outline(array $matrix, int $post_id) {
        // 1. 各アイテムの同期とアウトラインへの登録
        foreach ($matrix['items'] as $item) {
            \Kx\Core\ContextManager::sync($item['id']);
            $path_index = Dy::get_path_index($item['id'])?? [];

            $last_name = $path_index['last_part_name'] ?? 'Virtual？';
            $last_name = $last_name? '：'.$last_name : '';

            $sort_label = '';
            if (!empty($item['temp_sort_val'])) {
                // 数値または文字列として扱い、末尾4桁を削除
                $val = (string)$item['temp_sort_val'];
                $short_val = substr($val, 0, -4);


                // 値が存在する場合のみ「：」を付与
                $sort_label = !empty($short_val) ? $short_val . '：' : '';
            }

            $time_slug = $path_index['time_slug'] ?? '';

            \Kx\Core\OutlineManager::add_from_loop($post_id , esc_html($sort_label .$item['title'].$last_name ) , $item['id'],['time_slug' => $time_slug ] );
        }

        // 2. アウトラインHTMLの生成
        $outline_content = \Kx\Core\OutlineManager::render($post_id,'matrix' ,false);

        // --- 追加：概要カードの取得 ---
        $overview = "";
        $overview_from_id = Dy::get_content_cache($post_id, 'overview_from');
        if ($overview_from_id) {
            $overview = \Kx\Component\PostCard::render($overview_from_id, 'overview');
        }


        // 3. テンプレートに出力（'overview' を追加）
        return KxTemplate::get(
            self::get_template_name('outline_list'),
            [
                'matrix'          => $matrix,
                'outline_content' => $outline_content,
                'post_id'         => $post_id,
                'overview'        => $overview
            ],
            false
        );
    }
}