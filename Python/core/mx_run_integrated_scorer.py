"""
Path: core/mx_run_integrated_scorer.py
Project: Knowledge Classification (KC)
Step: Mixed Analysis (MX)
Description:
    記事の「外形的な構え（Stat）」と「キーワード」を解析し、DBに登録します。
    - AIモデル(Context)はここでは使用せず、物理的な構造や統計的重みを評価します。
    - 解析結果は wp_kx_ai_metadata テーブルに保存されます。
"""

import mysql.connector
import json
import spacy
import os
import sys
from tqdm import tqdm
from collections import Counter

# プロジェクトルートの特定
CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
BASE_DIR = os.path.dirname(CURRENT_DIR)

if BASE_DIR not in sys.path:
    sys.path.insert(0, BASE_DIR)

try:
    from utils.ut_text_processor import clean_text, is_valid_keyword
    from utils.ut_mapping_resolver import resolve_target_id
except ImportError as e:
    print(f"致命的なエラー: モジュールが見つかりません。")
    raise e

# --- 設定ファイルのロード ---
CONFIG_FILE = os.path.join(BASE_DIR, 'config', 'config.json')

def load_config():
    with open(CONFIG_FILE, 'r', encoding='utf-8') as f:
        return json.load(f)

def load_stop_words(stop_words_path):
    if os.path.exists(stop_words_path):
        with open(stop_words_path, 'r', encoding='utf-8') as f:
            data = json.load(f)
            return set(data.get('ignore_list', []))
    return set()

def get_top_keywords(title, content, nlp, stop_words):
    raw_full_text = f"{title or ''} 。 {content or ''}"
    full_text = clean_text(raw_full_text)
    doc = nlp(full_text[:10000])

    nouns = [t.lemma_ for t in doc if t.pos_ in ["NOUN", "PROPN"] and is_valid_keyword(t.lemma_, stop_words)]
    counts = Counter(nouns).most_common(10)
    return {k: v for k, v in counts}

def calculate_stat_score(keywords, term_weights):
    if not keywords or not term_weights:
        return 0.0
    total_score = sum(term_weights.get(word, 0.0) * count for word, count in keywords.items())
    return float(total_score)

def main():
    # 1. コンフィグロード
    config = load_config()
    db_set = config['db_settings']
    a_set = config.get('analysis_settings', {})
    tables = config['table_names']
    paths = config['file_paths']

    # パスを絶対パスに変換
    stop_words_path = os.path.join(BASE_DIR, paths['stop_words'])
    weights_path = os.path.join(BASE_DIR, paths.get('weights', 'models/global_term_weights.json'))

    print("解析リソースをロード中 (MX: 外形評価フェーズ)...")
    nlp = spacy.load("ja_ginza")
    stop_words = load_stop_words(stop_words_path)

    with open(weights_path, 'r', encoding='utf-8') as f:
        term_weights = json.load(f)

    conn = mysql.connector.connect(**db_set)
    cursor = conn.cursor(dictionary=True)

    # 2. 抽出クエリの構築
    query = f"""
        SELECT k0.id as ID, k0.title as post_title, p.post_content, k0.wp_updated_at,
               k1.overview_to, k1.overview_from
        FROM {tables['master_index']} k0
        INNER JOIN {tables['posts']} p ON k0.id = p.ID
        LEFT JOIN {tables['master_hierarchy']} k1 ON k0.id = k1.id
        LEFT JOIN {tables['ai_metadata']} m ON k0.id = m.post_id
        WHERE p.post_status = 'publish'
    """
    if not a_set.get('force_update', False):
        query += " AND (m.post_modified IS NULL OR k0.wp_updated_at > m.post_modified)"

    cursor.execute(query)
    rows = cursor.fetchall()
    print(f"解析対象: {len(rows)} 件")

    for row in tqdm(rows, desc="MX Processing"):
        target_id = resolve_target_id(row)
        if target_id is None: continue

        # 1. キーワード抽出
        keywords = get_top_keywords(row['post_title'], row['post_content'], nlp, stop_words)

        # 2. 統計的スコア(Stat)の算出
        stat_score = calculate_stat_score(keywords, term_weights)

        # 3. DB更新 (UPSERT)
        upsert_query = f"""
            INSERT INTO {tables['ai_metadata']} (post_id, post_modified, top_keywords, ai_score_stat)
            VALUES (%s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE
                post_modified = VALUES(post_modified),
                top_keywords = VALUES(top_keywords),
                ai_score_stat = VALUES(ai_score_stat),
                last_analyzed_at = CURRENT_TIMESTAMP
        """
        cursor.execute(upsert_query, (
            target_id,
            row['wp_updated_at'],
            json.dumps(keywords, ensure_ascii=False),
            stat_score
        ))

        # 4. 子記事データのクリア処理
        post_id = row['ID']
        if target_id != post_id:
            clear_query = f"""
                UPDATE {tables['ai_metadata']}
                SET top_keywords = NULL,
                    ai_score_stat = 0, ai_score_context = 0, ai_score = 0,
                    last_analyzed_at = CURRENT_TIMESTAMP
                WHERE post_id = %s
            """
            cursor.execute(clear_query, (post_id,))

    conn.commit()
    cursor.close()
    conn.close()
    print("MX解析完了: 外形評価(ai_score_stat)とキーワードの更新が完了しました。")

if __name__ == "__main__":
    main()