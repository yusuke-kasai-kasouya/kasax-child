<?php
namespace Kx\Utils;

use Kx\Core\SystemConfig as Su;
use Kx\Core\DynamicRegistry as Dy;

/**
 * 時間管理クラス
 */
class Time {

    /**
     * 時間の取得と整形（力技＋正規指定のハイブリッド版）
     * * @param string $type   'tokyo' (固定+9h), または 'Asia/Tokyo', 'UTC' 等の正式名称
     * @param string|null $format フォーマット。NULL指定ならタイムスタンプ(数値)を返す。
     * @return string|int
     */
    public static function format($type = 'tokyo', $format = "Y/m/d H:i:s") {

        $type = $type ?: 'tokyo';

        // 1. 「tokyo」の場合は、これまでの力技（絶対計算）を適用
        if ($type === 'tokyo') {
            $timestamp = time() + (9 * 60 * 60);
        }
        // 2. それ以外（Asia/TokyoやUTC等）は、正式なタイムゾーンとして処理を試みる
        else {
            try {
                // 有効なタイムゾーン名であれば、その地点の時間を取得
                $tz = new \DateTimeZone($type);
                $dt = new \DateTime('now', $tz);
                $timestamp = $dt->getTimestamp();
            } catch (\Exception $e) {
                // 不正な文字列が渡された場合は、安全策としてサーバー標準時(UTC)を返す
                $timestamp = time();
            }
        }

        // 3. 出力判定：フォーマットが指定されていれば文字列、NULLなら数値を返す
        return $format ? date($format, $timestamp) : $timestamp;
    }


    /**
     * タイムスラッグを解析して構造化データを返す
     * @param string $time_slug
     * @param int|array $char_id キャラクターID
     * @return array
     */
    public static function parse_slug($time_slug, $char_id) {
        $system_internal_schema = Su::get('system_internal_schema'); // Su::get
        $character = Dy::get_character($char_id); // Dy::get_character

        $country   = $character['education']['country'] ?? 'JP';
        $edu_start = (int)($character['education']['start'] ?? 0);
        $edu_end   = (int)($character['education']['end'] ?? 0);
        $edu_table = $system_internal_schema['education_systems'][$country]['grades'] ?? [];

        $data = [
            'age'   => 0,
            'month' => '00',
            'day'   => '00',
            'time'  => '',
            'grade' => '',
            'slug'  => (string)$time_slug
        ];

        // 1. スラッグの解析
        if (!empty($time_slug)) {
            if (strpos((string)$time_slug, '-') !== false) {
                // ハイフンあり: "11-10151804" 形式
                list($age_raw, $suffix) = explode('-', (string)$time_slug);
                $data['age']   = (int)$age_raw;
                $data['month'] = substr($suffix, 0, 2);
                $data['day']   = substr($suffix, 2, 2);
                $data['time']  = substr($suffix, 4);
            } else {
                // ハイフンなし: 単純な "11" 形式
                $data['age']   = (int)$time_slug;
            }
        }

        // 2. 学年(grade)の算出とフィルタリング
        $current_age = $data['age'];

        // 基本値として年齢（またはスラッグそのまま）を入れる
        $data['grade'] = (string)$current_age;

        // 学歴設定がある場合のみ、テーブル変換を行う
        // STARTとENDが共に0なら学年表記は不要（年齢のみにする）
        if ($edu_start !== 0 || $edu_end !== 0) {
            // 現在の年齢が学歴範囲内の場合のみテーブルを参照
            if ($current_age >= $edu_start && $current_age <= $edu_end) {
                if (isset($edu_table[$current_age])) {
                    $data['grade'] = $edu_table[$current_age];
                }
            }
        } else {
            // 0と0なら学年変換はせず、年齢のみ（既に$data['grade']に入っている）
        }

        return $data;
    }

    // \Kx\Utils\Time クラス内
    private static $last_state = ['age' => '', 'month' => '', 'day' => ''];

    public static function check_date_changed($current_timeline) {
        $is_changed = (
            ($current_timeline['age']   !== self::$last_state['age']) ||
            ($current_timeline['month'] !== self::$last_state['month']) ||
            ($current_timeline['day']   !== self::$last_state['day'])
        );

        if ($is_changed) {
            self::$last_state = [
                'age'   => $current_timeline['age'],
                'month' => $current_timeline['month'],
                'day'   => $current_timeline['day']
            ];
        }

        return $is_changed;
    }

    /**
     * 日付判定の状態をリセットする
     * ループの直前に必ず呼び出す
     */
    public static function reset_date_check() {
        self::$last_state = [
            'age'   => '',
            'month' => '',
            'day'   => ''
        ];
    }


    /**
     * 現在時刻と投稿の最終更新時刻の差分（秒）を返す
     * * @param int|\WP_Post|null $post_id 投稿IDまたはオブジェクト
     * @return int 秒数差
     */
    public static function get_modified_diff($post_id = null) {
        // get_post_modified_time('U') は内部で get_post() を呼ぶため ID/Object どちらでも可
        $modified_time = (int)get_post_modified_time('U', true, $post_id);
        if (!$modified_time) return 0;

        return time() - $modified_time;
    }
}