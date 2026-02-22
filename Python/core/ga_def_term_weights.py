"""
Path: core/ga_def_term_weights.py
Project: Knowledge Classification (KC)
Step: Global Analysis (GA)
Description:
    サイト全体の記事からTF-IDFを用いて重要語（名詞）を抽出し、
    統計的な単語の重み（global_term_weights.json）を定義します。
    これが解析フェーズ（MX）での「統計スコア」の基礎となります。
"""

import mysql.connector
import pandas as pd
import spacy
from sklearn.feature_extraction.text import TfidfVectorizer
import json
import os
import sys
from tqdm import tqdm

# プロジェクトルートをパスに追加してutilsを読み込めるようにする
BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.append(BASE_DIR)
from utils.ut_text_processor import clean_text

# --- 設定 ---
CONFIG_FILE = os.path.join(BASE_DIR, 'config', 'config.json')

def load_config():
    with open(CONFIG_FILE, 'r', encoding='utf-8') as f:
        return json.load(f)

def load_stop_words(stop_words_path):
    """専門辞書（無視ワード）を読み込む"""
    if os.path.exists(stop_words_path):
        with open(stop_words_path, 'r', encoding='utf-8') as f:
            data = json.load(f)
            return set(data.get('ignore_list', []))
    return set()

def is_valid_keyword(word, stop_words):
    """有効なキーワードか判定（長さ2以上、かつストップワードでない）"""
    return len(word) >= 2 and word not in stop_words

def get_nouns(text, nlp, stop_words):
    """
    クレンジング後に名詞を抽出
    """
    clean_c = clean_text(text)
    if not clean_c:
        return ""

    max_chunk_len = 10000
    chunks = [clean_c[i:i + max_chunk_len] for i in range(0, len(clean_c), max_chunk_len)]
    all_nouns = []

    for chunk in chunks:
        if not chunk.strip():
            continue
        try:
            doc = nlp(chunk)
            for token in doc:
                word = token.lemma_
                if token.pos_ in ["NOUN", "PROPN"]:
                    if is_valid_keyword(word, stop_words):
                        all_nouns.append(word)
        except Exception:
            pass
    return " ".join(all_nouns)

def main():
    # 1. ロード
    config = load_config()
    db_set = config['db_settings']
    a_set = config.get('analysis_settings', {})
    tables = config['table_names']
    paths = config['file_paths']

    # パス設定の動的取得
    stop_words_path = os.path.join(BASE_DIR, paths['stop_words'])
    output_file = os.path.join(BASE_DIR, paths.get('weights', 'models/global_term_weights.json'))

    prefixes = a_set.get('knowledge_prefixes', [])
    stop_words = load_stop_words(stop_words_path)
    print(f"✓ 設定と辞書 ({len(stop_words)}語) をロードしました。")

    # 2. DB取得
    print("1. データベースから記事を取得中...")
    try:
        conn = mysql.connector.connect(**db_set)
        # テーブル名をconfigから取得
        query = f"SELECT ID, post_title, post_content FROM {tables['posts']} WHERE post_status='publish' AND post_type='post'"
        df = pd.read_sql(query, conn)
        conn.close()
    except Exception as e:
        print(f"DBエラー: {e}")
        return

    # --- フィルタリング処理 ---
    if prefixes:
        initial_count = len(df)
        df = df[df['post_title'].str.startswith(tuple(prefixes))]
        print(f"   -> プレフィックス一致で絞り込み: {initial_count}件 -> {len(df)}件")

    if df.empty:
        print("エラー: 解析対象の記事（知識資産）が見つかりませんでした。")
        return

    # 3. 解析（GiNZA）
    print("2. 日本語解析モデル(GiNZA)をロード中...")
    nlp = spacy.load("ja_ginza")

    print("3. 全文解析を実行中（名詞抽出）...")
    tqdm.pandas(desc="解析進捗")
    df['nouns'] = df['post_content'].progress_apply(lambda x: get_nouns(x, nlp, stop_words))

    # 4. 重み付け計算
    print("4. 重要度の重み付け(TF-IDF)を計算中...")
    df = df[df['nouns'] != ""]
    if df.empty:
        print("エラー: 解析可能なテキストがありませんでした。")
        return

    vectorizer = TfidfVectorizer(max_features=100)
    tfidf_matrix = vectorizer.fit_transform(df['nouns'])

    feature_names = vectorizer.get_feature_names_out()
    weights = tfidf_matrix.sum(axis=0).A1
    result = {word: round(float(score), 4) for word, score in zip(feature_names, weights)}

    sorted_result = dict(sorted(result.items(), key=lambda x: x[1], reverse=True))

    # 5. 保存
    os.makedirs(os.path.dirname(output_file), exist_ok=True)
    with open(output_file, 'w', encoding='utf-8') as f:
        json.dump(sorted_result, f, ensure_ascii=False, indent=4)

    print(f"\n✓ 完了: 重要語重み定義を {output_file} に書き出しました。")

if __name__ == "__main__":
    main()