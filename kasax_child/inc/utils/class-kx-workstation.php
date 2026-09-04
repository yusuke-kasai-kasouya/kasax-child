<?php

/**
 * inc\utils\class-kx-workstation.php
 *
 * 作業ボード・拡張版 (WorkStation)
 * 特定のプレフィックスに基づいた動的なコンテンツ管理機能を提供。
 */

namespace Kx\Utils;

use Kx\Core\SystemConfig as Su;
use Kx\Core\DynamicRegistry as Dy;
//use Kx\Core\KxDirector as kx;
use Kx\Utils\KxMessage as MSG;
use Kx\Launcher\KxPostLauncher;
//use Kx\Utils\KxTemplate;

/**
 * 作業ボード (WorkStation)
 */
class WorkStation {

    private static $host_id = '';
    private static $host_cat = []; // [id, name, slug]
    private static $existing_titles = []; // カテゴリ内の全記事タイトル（存在チェック用）

    /**
     * ショートコードエントリーポイント [kx_ws type="k0_top"]
     *
     * @param array|string|null $atts ショートコード属性
     * @return string レンダリング結果
     */
    public static function shortcode($atts) {
        if (is_admin()) return '';

        // 二重呼び出し防止
        if ((Dy::get('trace')['kxx_ws_count'] ?? 0) > 0) {
            return '━━━　WorkStation: Recursive Call Detected　━━━';
        }
        Dy::trace_count('kxx_ws_count', 1);

        $args = shortcode_atts([
            'type' => '',
            'cat'  => '', // 必要に応じて上書き用
        ], $atts);

        $ret = self::render($args);

        Dy::trace_count('kxx_ws_count', -1);
        return $ret;
    }

    /**
     * レンダリングメイン処理
     * @param array $args
     * @return string
     */
    public static function render(array $args): string {
        self::$host_id = get_the_ID();
        self::fetch_context();

        $type = (string)($args['type'] ?? '');
        $blueprint = self::get_blueprint($type, $args);

        if (empty($blueprint)) {
            // ここで (string) キャストするとエディタが安心します
            return (string)MSG::error("WorkStation: No schema for type [{$type}]");
        }

        $html = '';
        foreach ($blueprint as $key => $data) {
            // アンカーIDの生成
            $anchor_id = "section-" . str_replace('section_', '', (string)$key);

            // アウトライン（目次）への登録
            if (!empty($data['use_outline'])) {
                $title_for_outline = (string)($data['title'] ?? '');
                // タイトルが空でない場合のみアウトラインに登録
                if ($title_for_outline !== '') {
                    $html .= (string)\Kx\Core\OutlineManager::add_from_loop(self::$host_id, $title_for_outline, $anchor_id);
                }
            }

            // アンカーIDを持つセクションでラップして出力
            $html .= sprintf('<section id="%s" class="workstation-unit">', esc_attr($anchor_id));
            $html .= self::render_component($data);
            $html .= '</section>';
        }

        // 最後に確実に string を返す（これがないと波線が出ることがあります）
        return (string)$html;
    }

    /**
     * スキーマの分岐・構成定義（Blueprint）の取得
     *
     * @param string $type ボードのタイプ
     * @param array  $args 追加の引数
     * @return array 構築されたスキーマ配列
     */
    private static function get_blueprint(string $type, array $args): array {
        switch ($type) {
            case 'k0_top':
                return self::get_post_k0_top_schema();
            default:
                return [];
        }
    }

    /**
     * k0_top スキーマ定義
     */
    private static function get_post_k0_top_schema() {
        $cat_id   = self::$host_cat['id']   ?? 0;
        $cat_name = self::$host_cat['name'] ?? 'Unknown';

        $prod_works = Su::get('identifier_schema')['common_prefixes']['work_a'] ?? [];
        $blueprint  = [];

         // --- 0. バッチ操作パネル (一番上に配置) ---
        $blueprint['BATCH'] = [
            'content_type' => 'function',
            'title'        => '',
            'use_outline'  => false,
            'callback'     => [self::class, 'render_batch_panel'],
        ];

        // --- 1. WORKセクション ---
        foreach ($prod_works as $work_key) {
            $uc_work = ucfirst($work_key);

            $blueprint["i_{$work_key}"] = [
                'content_type' => 'inserter',
                'title'        => "WORK：{$uc_work}", // これだけ残す
                'use_outline'  => true,
                'args'         => [
                    'title'   => "{$cat_name}≫c00NEW00≫{$work_key}0000000",
                    'content' => '[raretu]',
                    'label'   => "NEW＋{$uc_work}",
                ],
            ];

            if (self::has_matching_post($work_key)) {
                $blueprint["p_{$work_key}"] = [
                    'content_type' => 'launcher',
                    'title'        => '', // ★ 空文字にする（List：xxx を消去）
                    'level'        => 6,
                    'use_outline'  => false,
                    'args'         => [
                        't'             => 96,
                        'mode'          => 'link',
                        'cat'           => $cat_id,
                        'search'        => $work_key,
                        'search_suffix' => ':num:',
                        'depth'         => 3,
                    ],
                ];
            }
        }

        // --- 2. CHARACTERセクション (0～9) ---
        for ($i = 0; $i < 10; $i++) {
            $char_marker = "≫c{$i}";

            $blueprint["ci_{$i}"] = [
                'content_type' => 'inserter',
                'title'        => "CHARACTER：{$i}", // これだけ残す
                'use_outline'  => true,
                'args'         => [
                    'title'   => "{$cat_name}≫c{$i}NEW00",
                    'content' => '[raretu]',
                    'label'   => "NEW＋C{$i}",
                ],
            ];

            if (self::has_matching_post($char_marker)) {
                $blueprint["cl_{$i}"] = [
                    'content_type' => 'launcher',
                    'title'        => '', // ★ 空文字にする（CharX List を消去）
                    'level'        => 6,
                    'use_outline'  => false,
                    'args'         => [
                        't'      => 96,
                        'mode'   => 'link',
                        'search' => "{$cat_name}{$char_marker}",
                        'depth'  => 2,
                    ],
                ];
            }
        }

        // --- 3. 全体リスト (常に表示) ---
        $blueprint['ALL'] = [
            'content_type' => 'launcher',
            'title'        => 'ALL LIST',
            'use_outline'  => true,
            'args'         => [
                'mode' => 'link',
                'ids'  => Dy::get_descendants(self::$host_id), // 子孫を注入
            ],
        ];

        $blueprint["ADD"] = [
                'content_type' => 'inserter',
                'title'        => "ADD", // これだけ残す
                'use_outline'  => true,
                'args'         => [
                    'title'   => "{$cat_name}≫X構成",
                    'content' => '[raretu]',
                    'label'   => "NEW＋",
                ],
            ];

        // --- 4. バッチ操作パネル (新規追加) ---
        $blueprint['BATCH'] = [
            'content_type' => 'function',
            'title'        => '',
            'use_outline'  => false,
            'callback'     => [self::class, 'render_batch_panel'],
        ];

        return $blueprint;
    }

    /**
     * 個別コンポーネントの出力（HTML生成）
     */
    private static function render_component(array $data): string {
        $type  = (string)($data['content_type'] ?? 'ERROR');
        $level = (int)($data['level'] ?? 2);
        $tag   = 'h' . max(1, min(6, $level));
        $title = (string)($data['title'] ?? ''); // デフォルトを空文字に

        $html = '';

        // ★ タイトルがある場合のみ見出しタグを出力する
        if ($title !== '') {
            $html .= sprintf('<%1$s>%2$s</%1$s>', $tag, esc_html($title));
        }

        switch ($type) {
            case 'launcher':
                $html .= KxPostLauncher::run($data['args'] ?? []);
                break;
            case 'inserter':
                $html .= \Kx\Component\QuickInserter::render(
                    self::$host_id,
                    $data['args']['title']   ?? null,
                    $data['args']['content'] ?? null,
                    $data['args']['label']   ?? null,
                    'workstation'
                );
                break;
            case 'function':
                if (is_callable($data['callback'] ?? null)) {
                    $html .= call_user_func($data['callback'], $data['args'] ?? []);
                }
                break;
            default:
                $html .= MSG::error("Type [{$type}] is not supported.");
        }
        return $html;
    }

    /**
     * コンテキスト情報（カテゴリと記事タイトル一覧）を取得
     */
     private static function fetch_context() {
        // カテゴリ取得
        $categories = get_the_category(self::$host_id);
        if ($categories && !is_wp_error($categories)) {
            $cat = $categories[0];
            self::$host_cat = [
                'id'   => $cat->term_id,
                'name' => $cat->name,
                'slug' => $cat->slug,
            ];

            // そのカテゴリに属する全記事を取得
            $posts = get_posts([
                'category'       => $cat->term_id,
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                // 'fields' => 'post_title' は無効なので、オブジェクトとして取得
            ]);

            // オブジェクトの配列から、タイトルだけの配列に変換する
            if (!empty($posts)) {
                self::$existing_titles = wp_list_pluck($posts, 'post_title');
            } else {
                self::$existing_titles = [];
            }
        }
    }

    /**
     * キャッシュされたタイトル配列の中に、キーワードが含まれるか判定
     *
     * @param string $needle 検索するキーワード（プレフィックス等）
     * @return bool 記事が存在すればtrue
     */
    private static function has_matching_post(string $needle): bool {
        if (empty(self::$existing_titles)) {
            return false;
        }

        foreach (self::$existing_titles as $title) {
            // 文字列であることを保証して検索
            if (strpos((string)$title, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * バッチ操作パネル (matrix-batch-title.php) のレンダリング
     */
    private static function render_batch_panel(): string {
        if (empty(self::$host_id)) return '';

        // 直下または子孫のIDリストを取得
        $descendant_ids = Dy::get_content_cache(self::$host_id, 'descendants')
                       ?: Dy::get_descendants(self::$host_id)
                       ?: [];

        // matrix-batch-title.php が期待する配列構造を構築
        $items = array_map(function($id) {
            return ['id' => $id];
        }, $descendant_ids);

        $matrix_data = [
            'post_id' => self::$host_id,
            'items'   => $items
        ];

        // テンプレートの読み込みとレンダリング
        return \Kx\Utils\KxTemplate::get(
            'matrix/matrix-batch-title',
            ['matrix' => $matrix_data],
            false
        );
    }
}