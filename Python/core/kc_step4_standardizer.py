"""
Path: core/kc_step4_standardizer.py
Description: 全記事の ai_score を母集団とし、IQ基準(100/15)で偏差値を算出・更新する。
"""

import mysql.connector
import pandas as pd
import json
import os

# プロジェクトルートの特定
CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
BASE_DIR = os.path.dirname(CURRENT_DIR)
CONFIG_FILE = os.path.join(BASE_DIR, 'config', 'config.json')

def load_json(path):
    with open(path, 'r', encoding='utf-8') as f:
        return json.load(f)

def standardize_scores():
    # 1. コンフィグロード
    config = load_json(CONFIG_FILE)
    db_set = config['db_settings']
    tables = config['table_names']

    sc = config.get('scoring_config', {})
    dev_set = sc.get('deviation_settings', {'mean': 100.0, 'std_dev': 15.0})
    target_mean = dev_set['mean']
    target_sd = dev_set['std_dev']

    conn = mysql.connector.connect(**db_set)

    # 2. 母集団の取得（テーブル名を動的化）
    query = f"SELECT post_id, ai_score FROM {tables['ai_metadata']}"
    df = pd.read_sql(query, conn)
    pop = df[df['ai_score'] > 0]['ai_score']

    if pop.empty:
        print("データがありません。")
        conn.close()
        return

    mu = pop.mean()
    sigma = pop.std()

    print(f"統計データ: 平均={mu:.4f}, 標準偏差={sigma:.4f}")

    # 3. IQ偏差値計算
    def calc_deviation(x):
        if x <= 0 or sigma == 0: return 0.0
        return target_mean + target_sd * ((x - mu) / sigma)

    df['ai_score_deviation'] = df['ai_score'].apply(calc_deviation)

    # 4. DB一括更新
    cursor = conn.cursor()
    update_query = f"UPDATE {tables['ai_metadata']} SET ai_score_deviation = %s WHERE post_id = %s"
    update_data = [(float(row['ai_score_deviation']), int(row['post_id'])) for _, row in df.iterrows()]

    cursor.executemany(update_query, update_data)
    conn.commit()

    cursor.close()
    conn.close()
    print(f"標準化完了: {len(update_data)}件をIQスケールに変換しました。")

if __name__ == "__main__":
    standardize_scores()