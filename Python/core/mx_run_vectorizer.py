import mysql.connector
import json
import requests
import os
import sys
import time
from tqdm import tqdm

# プロジェクトルートを特定
CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
BASE_DIR = os.path.dirname(CURRENT_DIR)
if BASE_DIR not in sys.path:
    sys.path.insert(0, BASE_DIR)

try:
    from utils.ut_text_processor import clean_text
    from utils.ut_mapping_resolver import resolve_target_id
except ImportError as e:
    print(f"致命的なエラー: モジュールが見つかりません。")
    raise e

# --- 設定 ---
CONFIG_FILE = os.path.join(BASE_DIR, 'config', 'config.json')

def load_config():
    with open(CONFIG_FILE, 'r', encoding='utf-8') as f:
        return json.load(f)

# 引数に設定を渡せるように変更
def get_embedding(text, ai_conf):
    headers = {"Content-Type": "application/json"}
    payload = {
        "model": ai_conf['model_name'],
        "input": text
    }
    try:
        response = requests.post(
            ai_conf['embedding_url'],
            headers=headers,
            json=payload,
            timeout=ai_conf['timeout']
        )
        response.raise_for_status()
        return response.json()['data'][0]['embedding']
    except Exception as e:
        print(f"\nAPIエラー: {e}")
        return None

def main():
    config = load_config()
    db_conf = config['db_settings']
    ai_conf = config['ai_model_settings']
    tables = config['table_names'] # テーブル名も変数化

    conn = mysql.connector.connect(**db_conf) # 展開して渡すとスマート
    cursor = conn.cursor(dictionary=True)

    # 1. クエリ内のテーブル名をconfigから動的に生成
    query = f"""
        SELECT
            idx.id as ID,
            idx.title,
            idx.wp_updated_at,
            p.post_content,
            pm1.meta_value as overview_from,
            pm2.meta_value as overview_to
        FROM {tables['master_index']} idx
        JOIN {tables['posts']} p ON idx.id = p.ID
        LEFT JOIN {tables['postmeta']} pm1 ON idx.id = pm1.post_id AND pm1.meta_key = 'overview_from'
        LEFT JOIN {tables['postmeta']} pm2 ON idx.id = pm2.post_id AND pm2.meta_key = 'overview_to'
        LEFT JOIN {tables['ai_metadata']} m ON idx.id = m.post_id
        WHERE (m.vector_status IS NULL OR m.vector_status = 0)
    """

    print("解析対象の記事を抽出中...")
    cursor.execute(query)
    rows = cursor.fetchall()

    if not rows:
        print("未処理の記事はありません。")
        return

    print(f"{len(rows)} 件の記事をベクトル化します...")

    for row in tqdm(rows):
        # 2. 引数に row 全体を渡して ID 解決 (ut_mapping_resolver の仕様に合致)
        target_id = resolve_target_id(row)

        # target_id が None の場合は「俯瞰記事自体（親）」なのでスキップ
        if target_id is None:
            continue

        # 3. タイトルをベクトル化
        input_text = clean_text(row['title'])
        vector = get_embedding(input_text, ai_conf) # 設定を渡す

        if vector:
            # 4. UPSERTクエリもテーブル名を動的に
            upsert_query = f"""
                INSERT INTO {tables['ai_metadata']} (post_id, post_modified, vector_data, vector_status, last_vectorized_at)
                VALUES (%s, %s, %s, %s, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE
                    post_modified = VALUES(post_modified),
                    vector_data = VALUES(vector_data),
                    vector_status = 1,
                    last_vectorized_at = CURRENT_TIMESTAMP
            """
            cursor.execute(upsert_query, (
                target_id,
                row['wp_updated_at'],
                json.dumps(vector),
                1
            ))

            # 5. もし「詳細記事(子)」から「親」へ保存した場合、子側の status を整理
            original_id = row['ID']
            if target_id != original_id:
                clear_query = f"""
                    INSERT INTO {tables['ai_metadata']} (post_id, vector_status, vector_data)
                    VALUES (%s, 1, NULL)
                    ON DUPLICATE KEY UPDATE vector_status = 1, vector_data = NULL
                """
                cursor.execute(clear_query, (original_id,))

            conn.commit()
            time.sleep(ai_conf['sleep_time']) # スリープ時間も設定から

    cursor.close()
    conn.close()
    print("\nすべての処理が完了しました。")

if __name__ == "__main__":
    main()