<?php

/**
 * inc\utils\class-kx-UI.php
 *
 */
namespace Kx\Utils;

use Kx\Core\DynamicRegistry as Dy;

class KxUI {

    /**
 * 指定したポストの共有先（Ghost）へのリンクHTML配列を生成する
 */
public static function get_shared_link_slots($post_id) {
    $slots = [];
    $map = ['lesson' => 'Β', 'sens' => 'γ', 'study' => 'σ', 'data' => 'δ'];

    $shared_ids = Dy::get_content_cache($post_id, 'shared_ids') ?? [];
    $current_title = Dy::get_title($post_id);

    // 1. 統合（既存があれば上書き）
    $combined = array_merge($map, $shared_ids);

    // 2. 自分自身を除外
    $self_key = array_search($post_id, $combined);
    if ($self_key !== false) {
        unset($combined[$self_key]);
    }

    // 3. ループ処理
    foreach ($combined as $key => $val) {

        if (is_numeric($val) ) {
         Dy::is_ID($val);
            // --- 既存リンク ---
            $id = $val;
            $colormgr = Dy::get_color_mgr($id);
            $vars = $colormgr['style_base'] ?? '';

            $bg = "background: hsla(var(--kx-hue), var(--kx-sat), var(--kx-lum), 0.5); border: 1px solid rgba(0, 0, 0, 0.1);";
            $shape = "border-radius: 10px; padding: 2px 10px; line-height: 1.2; display: inline-block; vertical-align: middle; text-decoration: none; color: inherit; margin: 2px; font-size: 0.85rem;";

            $permalink = get_permalink($id);
            $slots[] = "<a href='{$permalink}' style='{$vars}{$bg}{$shape}' class='kx-shared-link'>{$key}</a>";

        } else {
            // --- 新規作成（専用コンポーネントを使用） ---
            $prefix = $val;
            $new_title = $prefix . mb_substr($current_title, 1);

            // 複雑なEditorを通さず、新規作成に特化したクラスを呼び出す
            $slots[] = \Kx\Component\QuickInserter::render($post_id, $new_title,'',"＋{$key}",'shared');
        }
    }

    return $slots;
}
}