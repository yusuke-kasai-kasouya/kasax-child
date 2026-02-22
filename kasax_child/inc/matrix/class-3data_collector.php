<?php
/**
 *[Path]: inc/core/matrix/class-orchestrator.php
 */

namespace Kx\Matrix;

//use Kx\Core\SystemConfig as Su;
use Kx\Core\DynamicRegistry as Dy;
//use Kx\Core\TitleParser as Tp;

/**
 * Class DataCollector
 * * Queryクラスで抽出されたID群に対し、表示に必要な詳細データ（コンテンツ・メタ情報・タイムライン情報等）
 * を一括収集・補完し、Matrixパイプライン用のデータセットを構築する。
 */
class DataCollector {
    private $post_id;
    private $ids;

    /** @var array 収集されたデータの格納庫 */
    private $collection = [];

    /**
     * DataCollector コンストラクタ
     * * @param array $ids  抽出済みのIDリスト
     * @param array $atts ショートコード属性（post_id等を含む）
     */
    public function __construct(array $ids,array $atts) {
        $this->ids = $ids;
        $this->post_id = $atts['post_id'];
    }

    /**
     * IDリストに基づいて基本データを収集・リフレッシュする
     * * 各IDのコンテンツ情報を最新状態に更新（再構築）し、
     * 生存しているデータのみをコレクションに格納して返す。
     * * @return array 収集された投稿データの連想配列 [id => fetchedData]
     */
    public function get_collection() {
        if (empty($this->ids)) return [];

        foreach ($this->ids as $id) {
            // ここで一括再構築（raw/ana/vis/production）
            // Queryクラスでは生存確認だけだったので、ここで中身を完全に充填する
            $fetchedData = Dy::set_content_refresh($id);

            if ($fetchedData) {
                $this->collection[$id] = $fetchedData;
            }
        }

        return $this->collection;
    }

    /**
     * 表示コンテキストに応じて必要なデータを一括準備する
     * * 基本データの収集に加え、タイムライン表示などの特殊なコンテキストで
     * 必要となる計算（時間軸の解析等）を事前実行する。
     * * @param string|null $context 表示モード (vertical_timeline, timetable_matrix 等)
     * @return void
     */
    public function prepare_all($context = null) {
        // 1. 全コンテキスト共通：基本データのキャッシュ充填
        $this->get_collection();




        // 2. コンテキスト別の特殊データ収集
        // $context が渡されていない場合は、内部保持しているかもしれない atts から推測（設計に合わせて選択）
        switch ($context) {
            case 'vertical_timeline':
                // タイムラインやラテ欄形式の場合、年齢・月などの時間軸解析を実行
                // これにより Dy::set_matrix($id, 'timeline', [...]) が走る
                $this->prepare_timeline_matrix($this->ids);
                break;

            case 'timetable_matrix':
                $this->prepare_timeline_matrix($this->ids);
                break;

            case 'dynamic_table':
                // SQLクエリモード専用の準備が必要ならここに記述
                break;

            case 'default_list':
            default:
                // 特殊な解析が不要な場合は何もしない
                break;
        }
    }

    /**
     * 各IDのタイムスラッグを解析し、Matrixストレージに時間軸情報を保存する
     * * IDごとの time_slug を解析し、年齢・月・学年・日・時間などの
     * 構造化されたデータを生成して DynamicRegistry(Dy) の Matrix ストレージへ格納する。
     * * @param array $ids 処理対象のIDリスト
     * @return void
     */
    private function prepare_timeline_matrix(array $ids) {
        // システム設定から教育システム（学年対応表）を取得
        /*
        $system_internal_schema = Su::get('system_internal_schema');
        // メイン記事のキャラクター情報から国別設定を特定
        $character = Dy::get_character($this->post_id);
        $country   = $character['education']['country'] ?? 'JP'; // デフォルトはJP
        $edu_table = $system_internal_schema['education_systems'][$country]['grades'] ?? [];
        */

        foreach ($ids as $id) {
            $path_index = Dy::get_path_index($id);
            $timeline = \Kx\Utils\Time::parse_slug($path_index['time_slug'] ?? '', $this->post_id);

            Dy::set_matrix($id, 'timeline', $timeline);

        }
    }
}