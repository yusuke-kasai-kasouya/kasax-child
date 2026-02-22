<?php
namespace Kx\Visual;

//use Kx\Core\SystemConfig as Su;
use Kx\Core\DynamicRegistry as Dy;
use Kx\Core\KxDirector as kx;

use Kx\Utils\KxTemplate;
//use Kx\Database\dbkx0_PostSearchMapper as dbkx0;

class SideBar {
    /**
     * 初期化：WordPressのフックに登録
     */
    public static function init() {
        $instance = new self();
        // footerフックに登録
        add_action('wp_footer', [$instance, 'render']);
    }



    /**
     * サイドバーのレンダリング
     * 左右のパネルデータを構築し、テンプレートへ渡す
     */
    public function render() {
        $post_id = get_the_ID();

        //echo Su::TEST;
        //var_dump(Su::BOOLEAN_MARKERS);

        // 1. 各種データの取得 (DynamicRegistry 経由)
        $system_context = Dy::get('system') ?: [];
        $path_index  = Dy::get_path_index($post_id) ?: [];
        //$log_stack      = $system_context['messages'] ?? [];

        $content = self::build_system_ops_content($post_id,$path_index);

        

        # スコアから不透明度を算出する関数
        $threshold = 50;
        $get_opacity = function($score) use ($threshold) {
            $min_score = 10;   // 下限値
            $min_opacity = 0.2; // 下限時の不透明度
            $max_opacity = 1.0; // 上限時の不透明度

            // 1. 閾値以上なら 1.0 固定
            if ($score >= $threshold) return $max_opacity;

            // 2. 下限以下なら 0.2 固定
            if ($score <= $min_score) return $min_opacity;

            // 3. 閾値が下限より大きく設定されている場合のみ計算（ゼロ除算防止）
            if ($threshold > $min_score) {
                // 線形補間ロジック
                // (現在のスコア - 下限) / (閾値 - 下限) で進捗率(0〜1)を出し、それを不透明度の幅(0.8)に掛ける
                $opacity = $min_opacity + ($score - $min_score) * (($max_opacity - $min_opacity) / ($threshold - $min_score));
                return round($opacity, 2);
            }

            return $max_opacity;
        };

        // 既存の重い処理（$resultsの取得）をバッサリ削り、以下のボタンに差し替えます
        $left_content = '<h4>AI Link</h4>';
        $left_content .= sprintf(
            '<div id="ai-link-container" data-post-id="%d">
                <button class="kx-ai-load-button" onclick="kxFetchAiLinks(this)">AI解析を実行</button>
            </div>',
            $post_id
        );

        $ai_score = round( Dy::get_content_cache($post_id, 'ai_score_deviation'))?? 'N/A';
        $ai_score_text = '：'. $ai_score;

        // 2. パネル共通のデータ構造定義 (LLMが理解しやすい命名)
        $sidebar_config = [
            'current_id' => $post_id,
            'panels'     => [
                'right' => [
                    'dom_id'         => 'kx-sidebar-right',
                    'title'          => ($path_index['genre'] ?? '').$ai_score_text,
                    'accent_color'   => '#00ffcc',
                    'header_content' => $content['header_content'], // 15%領域：クイック操作等
                    'body_content'   => $content['body_content'], // 70%領域：ログスタック
                    'footer_content' => $content['footer_content'], // 15%領域：システム設定等
                    'is_minimized'   => $content['right_is_minimized'],
                ],
                'left' => [
                    'dom_id'         => 'kx-sidebar-left',
                    'title'          => 'KNOWLEDGE TREE',
                    'accent_color'   => '#ffcc00',
                    'header_content' => '',
                    'body_content'   => [
                        // 関数呼び出し結果を明示的に配列化
                        kx::category_search_box(['t' => 24]),
                        self::db_Hierarchy_render_ui(Dy::get_title($post_id)),
                        $left_content
                    ],
                    'footer_content' => '',
                    'is_minimized'   => true, // 左はデフォルト閉じ
                ]
            ]
        ];

        // 3. 共通アセット（CSS/JS）の出力
        $this->render_assets($post_id);

        // 4. テンプレートの実行
        KxTemplate::get('layout/side-action-bar', $sidebar_config);
    }


    /**
     * 右サイドバー（SYSTEM OPS）の3分割コンテンツを一括構築
     * * @param int $post_id 現在の投稿ID
     * @return array [header_content, body_content, footer_content]
     */
    private function build_system_ops_content(int $post_id,$path_index): array {
        // 1. 各セクションの初期化
        $content = [
            'header_content' => [], // 15%: クイック操作
            'body_content'   => [], // 70%: メイン情報・ログ
            'footer_content' => [], // 15%: システムリンク
        ];

        // 基本データ取得
        $post_type  = $path_index['type']  ?? '';
        $post_genre = $path_index['genre']  ?? '';
        $full_path  = $path_index['full']  ?? '';
        $depth      = $path_index['depth'] ?? 0;
        $parts      = $path_index['parts'] ?? [];

        $ai_score = round( Dy::get_content_cache($post_id, 'ai_score'))?? 'N/A';
        $ai_score_stat = round( Dy::get_content_cache($post_id, 'ai_score_stat'))?? 'N/A';
        $ai_score_context = round( Dy::get_content_cache($post_id, 'ai_score_context'))?? 'N/A';
        $ai_score_deviation = round( Dy::get_content_cache($post_id, 'ai_score_deviation'))?? 'N/A';

        $ai_text_array = Dy::get_content_cache($post_id, 'top_keywords')?? [];
        $ai_text = implode(', ', array_map(
            fn($k, $v) => "$k:$v",
            array_keys($ai_text_array),
            $ai_text_array
        ));

        $str_null = '　─';

        $consolidated_to = Dy::get_content_cache($post_id, 'consolidated_to') ?? $str_null;
        $consolidated_from = Dy::get_content_cache($post_id, 'consolidated_from') ?? $str_null;
        $overview_to = Dy::get_content_cache($post_id, 'overview_to') ?? $str_null;
        $overview_from = Dy::get_content_cache($post_id, 'overview_from') ?? $str_null;
        $ghost_to = Dy::get_content_cache($post_id, 'ghost_to') ?? $str_null;
        $flags = Dy::get_content_cache($post_id, 'flags') ?? $str_null;

        // 右寄せ・下寄せの共通コンテナスタイル
        $align_bottom_right = 'display: flex; flex-direction: column; align-items: flex-end; justify-content: flex-end;';

        // --- 0. 表示モード「raretu」時のメタ情報出力 ---
        if (Dy::get_content_cache($post_id, 'short_code') === 'raretu') {

            if(  $post_genre !== 'prod_work_production_log'){
                // Header: 新規追加ボタンを右下に配置
                $content['header_content'][]= sprintf(
                    '<div style="%s height: 100%%;">%s</div>',
                    $align_bottom_right,
                    \Kx\Component\QuickInserter::render($post_id, "{$full_path}≫新規追加", '', "＋Matrix New", 'Matrix side')
                );
            }

            $top_keywords    = Dy::get_info($post_id, 'top_keywords')    ?: 'none';
            $bottom_keywords = Dy::get_info($post_id, 'bottom_keywords') ?: 'none';

            $content['body_content'][] = "<h4>Type</h4>";
            $content['body_content'][] = "Type: {$post_type}";
            $content['body_content'][] = "Genre: " . ($path_index['genre'] ?? '') . "<hr>";
            $content['body_content'][] = "Sort:Top：<br>・{$top_keywords}";
            $content['body_content'][] = "Sort:Bottom：<br>・{$bottom_keywords}";
        }

        // --- 1. Shared (同期) ポストの処理 ---
        $shared_sync_types = Dy::get_shared_sync_types();
        if (in_array($post_type, $shared_sync_types) && $depth !== 1) {

            // Footer: 共有スロットを右下に配置
            $shared_slots = \Kx\Utils\KxUI::get_shared_link_slots($post_id);
            if (!empty($shared_slots)) {
                $content['footer_content'][] = sprintf(
                    '<div style="%s min-height: 100px;"><div>%s</div></div>',
                    $align_bottom_right,
                    implode('</div><div>', $shared_slots)
                );
            }
        }
        // --- 2. Character Core (キャラクタ) の処理 ---
        else if ($post_type === 'prod_character_core' || $post_type === 'prod_character_relation') {

            self::stack_context_details( $post_id, $content['body_content']);

            if( isset($parts)){
                $target_title = $parts[0] . '≫'.$parts[1] . '≫来歴';
                $content['header_content'][] = Kx::render_smart_link($post_id,$target_title,['text'=> '来歴C']);
            }

            if($post_type === 'prod_character_relation'){
                $rel_array = Dy::get_char_attr($parts[0],$parts[2]) ?? [];

                $content['body_content'][] = "<h5>REL：{$parts[2]}</h5>";
                foreach($rel_array as $key =>$value ){
                    $text = is_array($value) ? implode(', ', $value) : $value;
                    $content['body_content'][] = "{$key}：{$text}";
                }
            }

            if( $post_genre === 'prod_work_productions'){
                if( isset($parts)){
                    $target_title = $parts[0] . '≫'.$parts[1] .'≫'.$parts[2] . '≫来歴';
                    $content['header_content'][] = Kx::render_smart_link($post_id,$target_title,['text'=> '来歴W']);
                }

            }
        }

        $content['right_is_minimized'] = false;

        if( $post_genre === 'prod_work_production_log'){
            $content['right_is_minimized'] = true;

            $hero_num = ltrim($parts[1], "c");

            $members = Dy::get_work($post_id)['members'];
            foreach ( $members as $num){
                if($num == $hero_num){
                    $char_title = $parts[0] . '≫'.$parts[1] ;
                    $log_title = $char_title.'≫来歴';
                }
                else{
                    $char_title = $parts[0] . '≫c'.$num ;
                    $log_title = $char_title.'≫＼'.$parts[1].'≫来歴';

                }

                $main = Kx::render_smart_link($post_id,$char_title,['text'=> $num]);
                $log  = Kx::render_smart_link($post_id,$log_title, ['text'=>'Log']);

                $content['header_content'][] =  '<div style="display: flex;justify-content: flex-end;padding:2px 0; height:1.6em;;">'.$main . $log.'</div>';

            }


            self::stack_context_details( $post_id, $content['body_content']);
        }

        $content['body_content'][] = "<h4>DATA：{$post_id}</h4>";
        $content['body_content'][] = "Consolidated_to: {$consolidated_to}";
        $content['body_content'][] = "Consolidated_from: {$consolidated_from}";
        $content['body_content'][] = "Overview_to: {$overview_to}";
        $content['body_content'][] = "Overview_from: {$overview_from}";
        $content['body_content'][] = "Ghost_to: {$ghost_to}";

        $content['body_content'][] = "Flags: {$flags}";

        $content['body_content'][] = "AI:DEV：{$ai_score_deviation}";
        $content['body_content'][] = "AI:Score：{$ai_score}";
        $content['body_content'][] = "AI:Stat：{$ai_score_stat}";
        $content['body_content'][] = "AI:Context：{$ai_score_context}";
        $content['body_content'][] = "AI:TEXT：{$ai_text}";

        return $content;
    }


    /**
     * CHARACTER および WORK 情報を取得し、body_content スタックに追加する
     * * @param int   $post_id 現在の投稿ID
     * @param array &$stack  参照渡しの body_content 配列
     */
    private function stack_context_details(int $post_id, array &$stack): void {
        // 処理対象の設定 [ラベル => 実行する関数の接尾辞]
        $targets = [
            'CHARACTER' => 'character',
            'WORK'      => 'work'
        ];

        foreach ($targets as $label => $suffix) {
            // 動的に関数名を組み立てて実行 (Dy::get_character / Dy::get_work)
            $method = "get_{$suffix}";
            $data = Dy::$method($post_id);

            if (empty($data)) continue;

            $stack[] = "<h4>{$label}</h4>";

            foreach ($data as $key => $value) {
                // 配列の場合はカンマ区切り、それ以外はそのまま文字列化
                $text = is_array($value) ? implode(', ', $value) : $value;

                // キーが数値でない場合のみ「キー：値」の形式にする
                $stack[] = is_numeric($key) ? $text : "{$key}：{$text}";
            }
        }
    }


    /**
     * CSSとJSを一度だけ出力
     */
    private function render_assets($current_id) {
        $colormgr = Dy::get_color_mgr($current_id);
        $body_default = $colormgr['style_array']['body_default'] ?? '';
        $content_default = $colormgr['style_array']['content_default']?? '';
        $style_base = $colormgr['style_base'] ?? '';

        //--kx-hue:0;--kx-sat:0%;--kx-lum:5%;
        static $done = false;
        if ($done) return;
        ?>
        <style>
            :root {
                --kx-bar-w: 280px;
                --kx-hand-w: 10px;
                --kx-bg:hsla( var(--kx-hue, 0),   var(--kx-sat, 0%),  var(--kx-lum, 1%),  1  );
                --kx-trans: 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
                <?= $body_default ?>
            }
            .kx-side-container { position: fixed; top: 0; height: 100vh; z-index: 2; }
            .kx-side-container.right { right: 0; z-index: 5;}
            .kx-side-container.left { left: 0; z-index: 5;}

            /* ハンドル共通 */
            .kx-side-handle {<?= $content_default ?> background:hsla( var(--kx-hue, 0),var(--kx-sat, 0%),  var(--kx-lum, 1%),  1  ); }
            .kx-side-handle {
                position: absolute; top: 0; width: var(--kx-hand-w); height: 100%;
                 cursor: pointer; z-index: 2;
                display: flex; align-items: center; justify-content: center;
                border-left: 1px solid hsla(0,0%,10%,1); border-right: 1px solid hsla(0,0%,10%,1);
            }

            .kx-handle-line { background:#000}
            .kx-side-handle:hover {border: 1px solid hsl(120,100%,50%);}

            /* 本体共通 */
            .kx-side-inner {
                position: absolute; top: 0;
                width: var(--kx-bar-w);
                height: 100%;
                background: var(--kx-bg);
                padding: 0px;
                box-sizing: border-box;
                overflow-y: auto;
                box-shadow: 0 0 20px rgba(0,0,0,0.1);
                transition: transform var(--kx-trans);
            }

            /* 右側の配置と動き */
            .right .kx-side-handle { right: 0; }
            .right .kx-side-inner { right: var(--kx-hand-w); transform: translateX(0); }
            /*
            .kx-side-inner .h1,.kx-side-inner h2,.kx-side-inner h3,.kx-side-inner h4,.kx-side-inner h5,.kx-side-inner h6{
                margin-top: 0 ;
                margin-bottom: 1em;
                padding: 0;
                line-height: 1.4;
                display: block;
                clear: both;
            }
            */
            .right.is-minimized .kx-side-inner { transform: translateX(calc(var(--kx-bar-w) + var(--kx-hand-w))); }

            /* 左側の配置と動き */
            .left .kx-side-handle { left: 0; }
            .left .kx-side-inner { left: var(--kx-hand-w); transform: translateX(0); }
            .left.is-minimized .kx-side-inner { transform: translateX(calc((var(--kx-bar-w) + var(--kx-hand-w)) * -1)); }

            /* 内部パーツ（最小限） */
            .kx-bar-header {<?= $style_base ?>  border-bottom: 1px solid hsla( var(--kx-hue, 0),  100%, 40%,  1  ); margin-bottom: 5px; }
            .kx-bar-header {
                padding: 0px 10px 0px;
                flex: 0 0 auto;
            }

            .kx-msg-stack {<?= $content_default ?> background:hsla( var(--kx-hue, 0),var(--kx-sat, 0%),  var(--kx-lum, 1%),  1  ); }
            .kx-msg-stack {padding: 0 0 0 0.5em; overflow-y: auto; font-size: 14px;overflow:hidden; }
            .type-error { color: #ff5f5f; }
            .kx-msg-dot {<?= $style_base ?> position: absolute; top: 15px; width: 8px; height: 8px; border-radius: 50%; background:hsla( var(--kx-hue, 0),var(--kx-sat, 0%),  var(--kx-lum, 1%),  .25  );}

            /* --- Scrollbar Customization --- */

            /* 全体のスクロールバー幅（細め） */
            .kx-side-inner::-webkit-scrollbar {
                width: 2px;
            }

            /* スクロールバーの背景（溝）：限りなく黒に近いグレー */
            .kx-side-inner::-webkit-scrollbar-track {
                background: #0a0a0a;
            }

            /* 動く部分（ツマミ）：少し明るい黒グレー */
            .kx-side-inner::-webkit-scrollbar-thumb {
                background: #2a2a2a;
                border-radius: 2px;
            }

            /* ホバー時：アクセントカラーを薄く感じる程度のグレーに */
            .kx-side-inner::-webkit-scrollbar-thumb:hover {
                background: #444;
            }

            /* Firefox向け設定 */
            .kx-side-inner {
                scrollbar-width: thin;
                scrollbar-color: #2a2a2a #0a0a0a;
            }

            /* ボディをFlexコンテナ化して高さを100%に */
            .kx-side-inner {
                display: flex;
                flex-direction: column;
                padding: 0; /* 内部パーツで余白を制御するため0に */
            }

            /* 3分割のコンテナ */
            .kx-bar-body {
                flex: 1 1 auto;
                display: flex;
                flex-direction: column;
                overflow: hidden; /* 全体ははみ出さない */
            }

            /* 上段：15% */
            .kx-body-top {
                flex: 0 0 15%;
                padding: 10px 0px;
                /*border-bottom: 1px solid #222;*/
            }

            /* 中段：70%（ここだけスクロール） */
            .kx-body-main {
                flex: 0 0 70%;
                padding: 10px 0px;
                overflow-y: auto; /* ここに先ほどのモノクロスクロールバーが適用される */
                overflow-x:hidden;
            }


            /* 下段：15% */
            .kx-body-bottom {
                flex: 0 0 15%;
                padding: 5px 0px;
                /*border-top: 1px solid #222;*/
            }
            .kx-msg-line{
                padding: 3px 0px;
            }
        </style>
        <script>
            function toggleKxSide(el) {
                el.closest('.kx-side-container').classList.toggle('is-minimized');
            }

            function kxFetchAiLinks(button) {
                const container = document.getElementById('ai-link-container');
                const postId = container.dataset.postId;

                button.innerText = '解析中...';
                button.disabled = true;

                fetch(kx_ajax_obj.ajax_url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'kx_load_ai_links', // AjaxHandlerで定義するアクション
                        post_id: postId
                    })
                })
                .then(response => response.text())
                .then(html => {
                    container.innerHTML = html;
                })
                .catch(err => {
                    console.error('AI Link Load Error:', err);
                    button.innerText = '再試行';
                    button.disabled = false;
                });
            }
        </script>



        <?php
        $done = true;
    }


    /**
     * 表示用関数：raretu.php から呼び出されるUI生成
     * raretu用表示機能。
     */
    private static function db_Hierarchy_render_ui($base_title) {
        if (empty($base_title)) return '<p>No Title Base.</p>';

        $args = [
            'base_title'     => $base_title,
            'unique_id'      => 'kx_h_out_' . md5($base_title),
            'admin_ajax_url' => admin_url('admin-ajax.php'),
        ];

        // 第3引数に false を追加！ これで勝手に echo されなくなります。
        return \Kx\Utils\KxTemplate::get('components/navigation/hierarchy-manager-ui', $args, false);
    }

}