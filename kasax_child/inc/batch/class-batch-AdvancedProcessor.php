<?php
/**
 * 統合バッチ処理エンジン：Kx_Advanced_Batch_Processor
 * 修正ポイント：
 * 1. refresh_state の強制呼び出しによるステート整合性の確保
 * 2. 物理削除後のクリーンな INSERT プロセスの安定化
 * 3. 実行ログ (text5) の蓄積ロジック追加
 */

namespace Kx\Batch;

class AdvancedProcessor {

    private $type = 'update_posts';
    private $table = 'wp_kx_temporary';
    public $state = [];

    public function __construct() {
        // インスタンス化の時点では読み込むだけで、実行直前にも再度リフレッシュする
        $this->refresh_state();
    }

    /**
     * ステートの初期化
     */
    public function init_state($params) {
        global $wpdb;

        // 1. 同一タイプの古いレコードを確実に削除
        $wpdb->delete($this->table, ['type' => $this->type]);

        // 2. 新しい状態でプロパティをセット
        $this->state = [
            'type'   => $this->type,
            'text1'  => $params['title_from']   ?? '',
            'text2'  => $params['title_to']     ?? '',
            'text3'  => $params['content_from'] ?? '',
            'text4'  => $params['content_to']   ?? '',
            'text5'  => '--- Batch Initialized ---<br>', // ログリセット
            'text6'  => $params['replace_mode'] ?? 'first',
            'text7'  => '0', // 速度リセット
            'ids_array' => $params['ids_array'] ?? [],
        ];

        // 3. DBへ新規保存
        $this->save_state();
    }

    /**
     * DBから最新の状態を読み込む
     */
    public function refresh_state() {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE type = %s", $this->type
        ), ARRAY_A);

        if ($row) {
            $this->state = $row;
            // JSONデコードして ids_array に展開
            $this->state['ids_array'] = json_decode($row['json'] ?? '[]', true);
        } else {
            // レコードがない場合はデフォルト値をセット
            $this->state = [
                'type' => $this->type,
                'ids_array' => [],
                'text5' => '',
                'text7' => '0'
            ];
        }
    }

    /**
     * DBへの保存 (UPDATE または INSERT)
     */
    public function save_state() {
        global $wpdb;

        // ids_array は json カラムへ保存
        $json_data = json_encode(array_values($this->state['ids_array'] ?? []), JSON_UNESCAPED_UNICODE);

        $data = [
            'text1' => (string)($this->state['text1'] ?? ''),
            'text2' => (string)($this->state['text2'] ?? ''),
            'text3' => (string)($this->state['text3'] ?? ''),
            'text4' => (string)($this->state['text4'] ?? ''),
            'text5' => (string)($this->state['text5'] ?? ''),
            'text6' => (string)($this->state['text6'] ?? 'first'),
            'text7' => (string)($this->state['text7'] ?? '0'),
            'json'  => $json_data,
            'time'  => time(),
        ];

        $exists = $wpdb->get_var($wpdb->prepare("SELECT type FROM {$this->table} WHERE type = %s", $this->type));

        if ($exists) {
            $result = $wpdb->update($this->table, $data, ['type' => $this->type]);
        } else {
            $data['type'] = $this->type;
            $result = $wpdb->insert($this->table, $data);
        }

        // デバッグ用：DBエラーがある場合に表示して止める
        if ($result === false) {
            echo "DB Error: " . $wpdb->last_error;
            die();
        }
    }

    /**
     * バッチ実行の1ステップ
     */
    public function execute_step($limit = 5) {
        // 実行直前にDBから最新状態をロード（必須）
        $this->refresh_state();

        $start_time = microtime(true);
        $ids = $this->state['ids_array'];

        if (empty($ids)) return 0;

        // 指定件数分を切り出す
        $targets = array_splice($ids, 0, $limit);
        $processed_log = "";

        foreach ($targets as $post_id) {
            $status = $this->update_post_content($post_id);
            $processed_log .= "ID: {$post_id} - {$status}<br>";
        }

        $elapsed = microtime(true) - $start_time;

        // 状態更新
        $this->state['ids_array'] = $ids; // 残りID
        $this->state['text7'] = (string)($elapsed / count($targets)); // 1件あたりの平均速度

        // ログを直近の10行程度に制限して保持（text5の肥大化防止）
        $new_log = $processed_log . ($this->state['text5'] ?? '');
        $this->state['text5'] = substr($new_log, 0, 2000);

        $this->save_state();

        return count($ids);
    }

    private function update_post_content($post_id) {
        $post = get_post($post_id);
        if (!$post) return "Skip (Not Found)";

        $update_data = ['ID' => $post_id];
        $updated = false;

        // タイトル置換
        if (!empty($this->state['text1'])) {
            $new_title = $this->apply_replacement($post->post_title, $this->state['text1'], $this->state['text2']);
            if ($post->post_title !== $new_title) {
                $update_data['post_title'] = $new_title;
                $updated = true;
            }
        }

        // 本文置換
        if (!empty($this->state['text3'])) {
            $new_content = $this->apply_replacement($post->post_content, $this->state['text3'], $this->state['text4']);
            if ($post->post_content !== $new_content) {
                $update_data['post_content'] = $new_content;
                $updated = true;
            }
        }

        if ($updated) {
            wp_update_post($update_data);
            return "Updated";
        }

        return "No Change";
    }

    private function apply_replacement($subject, $from, $to) {
        if (empty($from)) return $subject;

        $mode = $this->state['text6'] ?? 'first';
        $limit = ($mode === 'first') ? 1 : -1;

        // 正規表現判定
        if (preg_match('/^\/.*\/[a-z]*$/', $from)) {
            // クラス内での自動エスケープ（フォームからの入力を想定）
            $from = str_replace(['\\\\d', '\\\\w', '\\\\s'], ['\d', '\w', '\s'], $from);
            $result = @preg_replace($from, $to, $subject, $limit);
            return ($result === null) ? $subject : $result;
        } else {
            // 通常の文字列置換
            if ($mode === 'first') {
                $pos = strpos($subject, $from);
                if ($pos !== false) {
                    return substr_replace($subject, $to, $pos, strlen($from));
                }
                return $subject;
            } else {
                return str_replace($from, $to, $subject);
            }
        }
    }
}