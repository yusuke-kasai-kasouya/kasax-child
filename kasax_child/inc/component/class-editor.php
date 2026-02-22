<?php
/**
 * [Path]: inc\component\class-post_card.php
 */

namespace Kx\Component;

//use Kx\Core\SystemConfig as Su;
use Kx\Core\DynamicRegistry as Dy;
//use Kx\Core\ColorManager;
use Kx\Utils\KxTemplate;


class Editor {

    /**
     * エディター（モーダル）のオーケストレーター
     */
    public static function open($post_id, $editor_mode = 'update', $label = 'Edit', $options = []) {


        // 1. 新規作成(insert)の場合はシンプルに1つ返して終了
        if ($editor_mode === 'insert' || $editor_mode ==='sidebar_insert') {
            $args = self::prepareInsertArgs($post_id, $editor_mode, $label, $options);
            return KxTemplate::get('components/editor/inline-modal-editor', $args,false);
        }
        // 2. 通常の更新用(実体/Real)のHTMLを生成
        $args = self::prepareUpdateArgs($post_id, $editor_mode, $label, $options);
        $html = KxTemplate::get('components/editor/inline-modal-editor', $args,false);

        // 3. Ghost(本体)判定：自身が他者の窓（ghost_to）である場合、自分自身の編集ボタン(👻)を連結
        $ghost_to = Dy::get_content_cache($post_id, 'ghost_to');

        if ($ghost_to) {
            // Ghost(本体)編集用の引数を再構築
            // editor_modeを'update'に固定し、ラベルを「👻」にする
            $ghost_args = self::prepareUpdateArgs($post_id, 'ghost', '👻', $options);

            // 【重要】edit_idを強制的にpost_id(本体)に向け、modeも通常更新にする
            $ghost_args['edit_id']    = $post_id;
            $ghost_args['editor_mode'] = ($editor_mode === 'overview')?'ghost': $editor_mode ;
            $ghost_args['label']       = '👻';
            //$ghost_args['editor_mode'] = 'ghost';

            // 【重要】DOM IDの衝突を避けるための識別子(uidはテンプレート内で$post_id依存のため)
            // 必要に応じて $ghost_args['suffix'] = '-ghost'; のように渡すとより安全です

            $html_ghost_to = KxTemplate::get('components/editor/inline-modal-editor', $ghost_args,false);
        }

        return ($html_ghost_to ?? '').$html;
    }

    /**
     * 新規作成用の引数組み立て（内部処理）
     */
    private static function prepareInsertArgs($post_id,$editor_mode, $label, $options) {
        $path_index = Dy::get_path_index($post_id);

        return [
            'post_id'     => $post_id,
            'edit_id'     => 0,
            'editor_mode'    =>$editor_mode,
            'title'       => $options['new_title'] ?? ($path_index['full'] ?? '') . 'New Post',
            'new_content' => $options['new_content'] ?? '',
            'label'       => '➕️',
            'url'         => get_stylesheet_directory_uri() . "/pages/edit_post.php?mode=insert",
            'paint'       => 'background-color: #333;',
            'traits'      => '',
            'info_label'  => '新規作成',
            'info_html'   => '新規作成モード',
        ];
    }

    /**
     * 更新用の引数組み立て（内部処理）
     */
    private static function prepareUpdateArgs($post_id, $editor_mode, $label, $options) {

        $esc_editor_mode = $editor_mode;
        $path_index = Dy::get_path_index($post_id) ?? [];
        $colormgr   = Dy::get_color_mgr($post_id)?? [];

        // DynamicRegistryからGhost情報を取得
        $ghost_to   = Dy::get_content_cache($post_id, 'ghost_to');
        $ghost_from = Dy::get_content_cache($post_id, 'ghost_from');

        if($editor_mode ==='header'){
            $label = mb_strimwidth(($path_index['at_name'] ?? ''), 0, 60, '...', 'UTF-8')??'Edit';
        }


        $edit_id    = $post_id;
        $info_label = 'INFO：';
        $info_links = [];

        // 1. --- Business Logic: Ghost 判定 (実体編集への切り替え) ---
        // ghost_to がある場合：このカードは「他記事の窓」である
        if ($ghost_to) {
            $edit_id     = $ghost_to;      // 編集対象を実体IDに切り替え
            $editor_mode = 'ghost_to';     // テンプレート側での判定用
            $info_label .= 'ghost_to＋';
            $label      .= '：G';          // 通常Editボタンのラベル装飾
        }

        // 2. --- コンテキストに応じたリンク先ID ($link_id) の決定 ---
        // 👻ボタン経由の時は、実体ではなく「本体(post_id)」の情報を出したい
        $link_id = ($esc_editor_mode === 'ghost') ? $post_id : $edit_id;

        // 3. --- INFOリンク集の構築 ---
        // 常に表示する基本セット
        $info_links[] = \Kx\Utils\Toolbox::script_id_clipboard($link_id);
        $info_links[] = self::get_admin_edit_link($link_id, "Main");

        // 実体(ghost_to)が存在し、かつ現在「本体(ghost)」を編集していない場合のみ、実体への予備リンクを出す
        if ($ghost_to && $esc_editor_mode !== 'ghost') {
            $info_links[] = self::get_admin_edit_link($ghost_to, "RealEntity");
        }

        // ghost_from がある場合：この記事は「他階層に召喚」されている
        if ($ghost_from) {
            $info_label .= 'ghost_from＋';
            $info_links = array_merge($info_links, self::generate_ghost_from_links($ghost_from));
            $label .= '&nbsp;&nbsp;G'.count($ghost_from);
        }



        return [
            'post_id'     => $post_id,
            'edit_id'     => $edit_id,
            'editor_mode' => $editor_mode,
            'title'       => $options['new_title'] ?? ($path_index['full'] ?? ''),
            'new_content' => $options['new_content'] ?? '',
            'label'       => $label,
            'url'         => get_stylesheet_directory_uri() . "/pages/edit_post.php?id={$post_id}",
            'paint'       => ($colormgr['style_base'] ?? '') . 'background-color:hsla(var(--kx-hue),var(--kx-sat),var(--kx-lum),var(--kx-alp));',
            'traits'      => $colormgr['style_array']['vars_only'] ?? '',
            'info_label'  => rtrim($info_label, '＋+'),
            'info_html'   => implode('<br>', $info_links),
            'save_html'   => \Kx\Core\Kx_Consolidator::render_ui($link_id,'single_post')
        ];
    }

    /**
     * 要件3: ghost_from の複数のポストへの「パーマリンク」を生成する
     */
    private static function generate_ghost_from_links($ids) {
        if (!is_array($ids)) return [];

        $links = [];
        foreach ($ids as $id) {
            $url = get_permalink($id);
            $title = Dy::get_title($id);
            if ($url) {
                // 公開ページへのリンクであることがわかるようアイコンとテキストを調整
                $links[] = "<a href='{$url}' target='_blank' rel='noopener' title='View Post'>🔗View(#{$id})$title</a>";
            }
        }
        return $links;
    }

    /**
     * WP管理画面の「編集ページ」URLを生成するヘルパー
     */
    private static function get_admin_edit_link($id, $prefix) {
        $url = get_edit_post_link($id);
        if (!$url) return "";
        // 編集画面へのリンクであることがわかるようアイコンを付与
        return "<a href='{$url}' target='_blank' rel='noopener' title='Edit Post'>{$prefix}(#{$id})</a>";
    }

    public static function render() {
        // ...
    }
}