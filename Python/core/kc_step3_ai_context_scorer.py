"""
Path: core/kc_step3_ai_context_scorer.py
Project: Knowledge Classification (KC)
Step: 3
Description:
    1. 学習済みAIモデル(学習データとの類似性)から ai_score_context を算出。
    2. ut_score_calculator(外形的な統計・構造評価)から ai_score_stat を算出。
    3. 両者を統合し、最終的な ai_score を算出・保存します。
"""

import mysql.connector
import json
import os
import sys
import joblib
from tqdm import tqdm

# プロジェクトルートの特定
CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
BASE_DIR = os.path.dirname(CURRENT_DIR)

if BASE_DIR not in sys.path:
    sys.path.insert(0, BASE_DIR)

try:
    from utils.ut_mapping_resolver import resolve_target_id
    from utils.ut_text_processor import clean_text
    from utils.ut_score_calculator import calculate_integrated_score
except ImportError as e:
    print(f"致命的なエラー: モジュールが見つかりません。")
    raise e

def load_json(path):
    with open(path, 'r', encoding='utf-8') as f:
        return json.load(f)

def main():
    # 1. コンフィグロード
    config = load_json(os.path.join(BASE_DIR, 'config', 'config.json'))
    db_set = config['db_settings']
    tables = config['table_names']
    paths = config['file_paths']

    # 各種ファイルのパス設定
    weights_path = os.path.join(BASE_DIR, paths.get('weights', 'models/global_term_weights.json'))
    model_path = os.path.join(BASE_DIR, 'models', 'kc_knowledge_classifier.pkl')
    vect_path = os.path.join(BASE_DIR, 'models', 'kc_text_vectorizer.pkl')

    term_weights = load_json(weights_path)

    print("AIモデルをロード中...")
    if not os.path.exists(model_path) or not os.path.exists(vect_path):
        print("エラー: モデルファイルが見つかりません。")
        return

    model = joblib.load(model_path)
    vectorizer = joblib.load(vect_path)

    conn = mysql.connector.connect(**db_set)
    cursor = conn.cursor(dictionary=True)

    # 2. 抽出クエリの動的化
    query = f"""
        SELECT m.post_id as ID, p.post_title, p.post_content,
               k1.overview_to, k1.overview_from, k1.tag, k1.consolidated_from
        FROM {tables['ai_metadata']} m
        INNER JOIN {tables['posts']} p ON m.post_id = p.ID
        LEFT JOIN {tables['master_hierarchy']} k1 ON m.post_id = k1.id
        WHERE p.post_status = 'publish'
    """
    cursor.execute(query)
    rows = cursor.fetchall()
    print(f"AI解析開始: {len(rows)} 件")

    for row in tqdm(rows, desc="Hybrid Scoring"):
        target_id = resolve_target_id(row)
        if target_id is None: continue

        post_id = row['ID']
        title = row['post_title'] if row['post_title'] else ""
        content = row['post_content'] if row['post_content'] else ""

        full_text_for_ai = f"{title} 。 {content}"

        # --- A. 文脈評価 (AI Context) ---
        c_text = clean_text(full_text_for_ai)
        if not c_text.strip(): continue

        X = vectorizer.transform([c_text])
        ai_val = model.predict(X)[0]
        score_context = float(ai_val)

        # --- B. 外形統計評価 (Structural Stat) ---
        _, score_stat, _ = calculate_integrated_score(
            full_text_for_ai, row, None, None, term_weights, config, clean_text
        )

        # --- C. 最終統合 (Final AI Score) ---
        sc = config.get('scoring_config', {})
        weights = sc.get('integration_weights', {})
        w_stat = weights.get('stat_weight', 1.0)
        w_context = weights.get('context_weight', 1.0)

        final_score = float((score_stat * w_stat) + (score_context * w_context))

        # DB更新
        update_query = f"""
            UPDATE {tables['ai_metadata']}
            SET ai_score_stat = %s,
                ai_score_context = %s,
                ai_score = %s,
                last_analyzed_at = CURRENT_TIMESTAMP
            WHERE post_id = %s
        """
        cursor.execute(update_query, (score_stat, score_context, final_score, target_id))

        # 子記事（移譲元）のデータをクリア
        if target_id != post_id:
            clear_query = f"""
                UPDATE {tables['ai_metadata']}
                SET top_keywords = NULL, ai_score_stat = 0, ai_score_context = 0, ai_score = 0
                WHERE post_id = %s
            """
            cursor.execute(clear_query, (post_id,))

    conn.commit()
    cursor.close()
    conn.close()
    print("-" * 30)
    print("STEP 3 完了: 文脈評価と外形統計評価が分離・統合されました。")

if __name__ == "__main__":
    main()