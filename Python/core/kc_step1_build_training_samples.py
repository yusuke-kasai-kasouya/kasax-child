"""
Path: core/kc_step1_build_training_samples.py
Project: Knowledge Classification (KC)
Step: 1
Description:
    WordPressのデータベースから記事データを抽出し、タイトルに含まれる
    特定のプレフィックスに基づいて「知識の質(0.0 or 1.0-5.0)」を数値化します。
    これが Step 2 でAIが学習する際の「教師データ」になります。
"""

import mysql.connector
import pandas as pd
import json
import os
import sys

# 自作モジュールのインポート準備
BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.append(BASE_DIR)
from utils.ut_text_processor import clean_text
from utils.ut_score_calculator import calculate_integrated_score

def create_training_data():
    # 1. config, weightsの読み込み
    try:
        config_path = os.path.join(BASE_DIR, 'config', 'config.json')
        with open(config_path, 'r', encoding='utf-8') as f:
            config = json.load(f)

        # configからパスを取得
        paths = config.get('file_paths', {})
        tables = config.get('table_names', {})

        weights_path = os.path.join(BASE_DIR, paths.get('weights', 'models/global_term_weights.json'))
        with open(weights_path, 'r', encoding='utf-8') as f:
            term_weights = json.load(f)

    except FileNotFoundError as e:
        print(f"エラー: 設定ファイルが見つかりません。 {e}")
        return

    db_set = config['db_settings']
    conn = mysql.connector.connect(**db_set)

    # 2. クエリのテーブル名を動的化
    query = f"""
        SELECT k0.id, k0.title as post_title, p.post_content,
               k1.tag, k1.consolidated_from, k1.overview_from, k1.overview_to, k1.flags, k1.json
        FROM {tables['master_index']} k0
        INNER JOIN {tables['posts']} p ON k0.id = p.ID
        LEFT JOIN {tables['master_hierarchy']} k1 ON k0.id = k1.id
        WHERE p.post_status = 'publish'
    """
    df = pd.read_sql(query, conn)
    conn.close()

    # --- 親子構造の解決ロジック ---
    df['id'] = df['id'].astype(int)
    df['overview_to'] = pd.to_numeric(df['overview_to'], errors='coerce').fillna(0).astype(int)
    df['overview_from'] = pd.to_numeric(df['overview_from'], errors='coerce').fillna(0).astype(int)

    print(f"初期データ数: {len(df)}件")

    child_mask = df['overview_to'] > 0
    children_df = df[child_mask].copy()

    aggregated_children_content = children_df.groupby('overview_to')['post_content'].apply(
        lambda x: "\n\n".join(filter(None, x))
    ).to_dict()

    df_filtered = df[~child_mask].copy()

    def update_content_and_score(row):
        current_id = row['id']
        title = str(row['post_title'])
        content = row['post_content'] if row['post_content'] else ""

        if row['overview_from'] > 0 or current_id in aggregated_children_content:
            combined_content = aggregated_children_content.get(current_id, "")
            if combined_content:
                content = combined_content

        full_text_for_ai = f"{title} 。 {content}"
        clean_c = clean_text(full_text_for_ai)

        prefixes = config.get('analysis_settings', {}).get('knowledge_prefixes', [])

        if not any(title.startswith(p) for p in prefixes):
            return pd.Series([None, None])

        _, score_stat, _ = calculate_integrated_score(
            full_text_for_ai, row, None, None, term_weights, config, clean_text
        )

        label = float(score_stat)
        return pd.Series([clean_c, label])

    df_filtered[['clean_content', 'label']] = df_filtered.apply(update_content_and_score, axis=1)
    df_filtered = df_filtered.dropna(subset=['clean_content', 'label'])

    output_data = df_filtered[df_filtered['clean_content'].str.len() > 10][['clean_content', 'label']]

    # 保存パスもconfig/pathsから取得（なければデフォルト）
    os.makedirs(os.path.join(BASE_DIR, 'models'), exist_ok=True)
    output_path = os.path.join(BASE_DIR, 'models', 'kc_training_samples.csv')
    output_data.to_csv(output_path, index=False, encoding='utf-8')

    print("-" * 30)
    print(f"学習データ作成完了: {output_path}")
    print(f"有効サンプル数: {len(output_data)}件")
    print("-" * 30)

if __name__ == "__main__":
    create_training_data()