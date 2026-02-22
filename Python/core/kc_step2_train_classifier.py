"""
Path: core/kc_step2_train_classifier.py
Step: 2 (Regression version)
Description:
    kc_step1で生成された 0.0〜5.0 のラベル（知識の質）を学習します。
    分類（Classifier）ではなく回帰（Regressor）モデルを使用し、
    文脈から「知識の完成度」を数値で予測できるようにします。
"""

import pandas as pd
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.linear_model import Ridge
import joblib
import os
import json

# 基本ディレクトリの設定
BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

def load_config():
    config_path = os.path.join(BASE_DIR, 'config', 'config.json')
    with open(config_path, 'r', encoding='utf-8') as f:
        return json.load(f)

def train_ai_model():
    # 1. コンフィグとパスのロード
    config = load_config()
    paths = config.get('file_paths', {})

    # モデル保存ディレクトリ
    MODELS_DIR = os.path.join(BASE_DIR, "models")

    # 入出力パスをconfigの定義に準拠（記述がなければデフォルトを使用）
    input_file = os.path.join(MODELS_DIR, "kc_training_samples.csv")
    output_model = os.path.join(MODELS_DIR, "kc_knowledge_classifier.pkl")
    output_vect = os.path.join(MODELS_DIR, "kc_text_vectorizer.pkl")

    # 1. データの読み込み
    if not os.path.exists(input_file):
        print(f"エラー: {input_file} が見つかりません。まず Step 1 を実行してください。")
        return

    # CSVの読み込み
    df = pd.read_csv(input_file)
    df = df.dropna(subset=['clean_content', 'label'])

    print(f"学習を開始します... (データ件数: {len(df)}件)")
    print(f"ラベル範囲: {df['label'].min()} ～ {df['label'].max()}")

    # 2. テキストを数値（ベクトル）に変換 (TF-IDF)
    vectorizer = TfidfVectorizer(max_features=3000, ngram_range=(1, 2))
    X = vectorizer.fit_transform(df['clean_content'])
    y = df['label']

    # 3. モデルの学習 (Ridge回帰)
    model = Ridge(alpha=1.0)
    model.fit(X, y)

    # 4. モデルの保存
    os.makedirs(MODELS_DIR, exist_ok=True)
    joblib.dump(model, output_model)
    joblib.dump(vectorizer, output_vect)

    print("-" * 30)
    print("AI回帰モデルの学習と保存が完了しました！")
    print(f"生成ファイル: {output_model}")

    # 簡易評価
    score = model.score(X, y)
    print(f"学習データに対する適合度 (R^2): {score:.4f}")
    print("-" * 30)

    # 単語の寄与度表示
    feature_names = vectorizer.get_feature_names_out()
    coefficients = model.coef_
    word_importance = pd.DataFrame({'word': feature_names, 'importance': coefficients})

    print("\n--- 高い知識スコア（5.0に近い）に寄与する表現 TOP 10 ---")
    print(word_importance.sort_values(by='importance', ascending=False).head(10))

    print("\n--- 低いスコア（作業ポスト的）と判断される表現 TOP 10 ---")
    print(word_importance.sort_values(by='importance', ascending=True).head(10))

if __name__ == "__main__":
    train_ai_model()