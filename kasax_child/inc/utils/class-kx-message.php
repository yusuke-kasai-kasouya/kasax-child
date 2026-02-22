<?php
/**
 * inc\utils\class-kx-message.php
 */


/*
    使い方。

    \Kx\Utils\KxMessage::error("IDが取得できません3。");
	\Kx\Utils\KxMessage::warn("キャッシュを更新しました。");
	\Kx\Utils\KxMessage::caution("キャッシュを更新しました。");
	\Kx\Utils\KxMessage::notice("キャッシュを更新しました。");
	\Kx\Utils\KxMessage::info("キャッシュを更新しました。");

    use \Kx\Utils\KxMessage as Msg;
    Msg::info("キャッシュを更新しました。");

	// 現在の投稿IDを取得して変数に入れる
    $test_id = null;

    if (is_singular()) {
        // ContextManager::sync($test_id); // 既存処理
    }

    // 1. エラーの実験 (IDが取得できない場合)
    if (empty($test_id)) {
        \Kx\Utils\KxMessage::error("IDが取得できません。");

    }

	// 1. エラーの実験 (IDが取得できない場合)
    if (empty($test_id)) {
        \Kx\Utils\KxMessage::error("IDが取得できません2。");

    }

	// 1. エラーの実験 (IDが取得できない場合)
    if (empty($test_id)) {
        \Kx\Utils\KxMessage::error("IDが取得できません3。");
		\Kx\Utils\KxMessage::info("キャッシュを更新しました。");

    // 複数出すテスト
    \Kx\Utils\KxMessage::info("データベースの同期が完了しました。");

    }


	// 1. エラーの実験 (IDが取得できない場合)
    if (empty($test_id)) {
        \Kx\Utils\KxMessage::error([
    'Error-NO' => 'E-001',
    'TargetID' => $test_id,
    'Status'   => 'Missing Metadata',
    'Details'  => ['missing' => 'title_parser', 'code' => 404]
    ]);

    }
    return;
    // 2. 警告の実験
    if ($test_id > 100) { // 実験用に数値を下げています
        \Kx\Utils\KxMessage::warn("ID:{$test_id} はテスト対象のIDです。");
    }

    // 3. Infoの実験
    \Kx\Utils\KxMessage::info("KxMessageシステムの接続テストに成功しました。");
*/

namespace Kx\Utils;

use Kx\Core\DynamicRegistry as Dy;

/**
 * KxMessage: システム全体のメッセージ管理
 * Dyの 'msg_stack' -> 'messages' 配下で一括管理する。
 */
class KxMessage {

    const TYPE_ERROR   = 'error';
    const TYPE_WARN    = 'warn';
    const TYPE_CAUTION = 'caution';
    const TYPE_NOTICE  = 'notice';
    const TYPE_INFO    = 'info';

    public static function error($msg)   { self::add(self::TYPE_ERROR, $msg); }
    public static function warn($msg)    { self::add(self::TYPE_WARN, $msg); }
    public static function caution($msg) { self::add(self::TYPE_CAUTION, $msg); }
    public static function notice($msg)  { self::add(self::TYPE_NOTICE, $msg); }
    public static function info($msg)    { self::add(self::TYPE_INFO, $msg); }



    /**
     * メッセージを登録 (フラット配列構造)
     */
    private static function add($type, $msg) {
        // 1. 'msg_stack' からメッセージのリストを直接取得
        // 取得した時点で [ ['type'=>...], [...] ] という形式の配列
        $stack = Dy::get('msg_stack');
        if (!is_array($stack)) {
            $stack = [];
        }

        // 2. 新しいメッセージを末尾に追加
        $stack[] = [
            'type' => $type,
            'text' => $msg,
            'time' => time()
        ];

        // 3. 配列をそのまま 'msg_stack' に上書き保存
        // 階層がないため、非常にシンプルで高速
        Dy::set('msg_stack', $stack);
    }

    /**
     * 全メッセージを取得
     */
    public static function get_all() {
        // 直接配列を返す（存在しなければ空配列）
        return Dy::get('msg_stack') ?: [];
    }


    /**
     * レンダリング：テンプレートを呼び出す
     */
    public static function render() {
        $messages = self::get_all(); // 既存の取得メソッドを利用

        if (empty($messages)) {
            return '';
        }

        // テンプレートに 'messages' という名前で変数を渡す
        return KxTemplate::get('components/common/message_hub', [
            'messages' => $messages
        ], false);
    }
}
