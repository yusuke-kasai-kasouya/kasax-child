"""
Path: core/kc_maintenance_sync_metadata.py
Project: Knowledge Classification (KC)
Description:
    wp_kx_0 に存在するが wp_kx_ai_metadata に存在しないレコードを抽出し、
    IDと更新日時を同期して新規作成します。
"""

import mysql.connector
import json
import os
import sys

# プロジェクトルートの特定
CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
BASE_DIR = os.path.dirname(CURRENT_DIR)

if BASE_DIR not in sys.path:
    sys.path.insert(0, BASE_DIR)

def load_config():
    config_path = os.path.join(BASE_DIR, 'config', 'config.json')
    with open(config_path, 'r', encoding='utf-8') as f:
        return json.load(f)

def main():
    config = load_config()
    db_set = config['db_settings']
    tables = config['table_names']

    print("データベース不足レコードの同期を開始します...")

    try:
        conn = mysql.connector.connect(**db_set)
        cursor = conn.cursor()

        # 1. 不足しているレコードと、対応する更新日時を取得
        # master_index (k0) にあり、ai_metadata (m) にないものを特定
        sync_check_query = f"""
            SELECT k0.id, k0.wp_updated_at
            FROM {tables['master_index']} k0
            LEFT JOIN {tables['ai_metadata']} m ON k0.id = m.post_id
            WHERE m.post_id IS NULL
        """
        cursor.execute(sync_check_query)
        missing_records = cursor.fetchall()

        if not missing_records:
            print("不足しているレコードはありません。整合性は保たれています。")
            return

        print(f"同期対象（不足データ）: {len(missing_records)} 件見つかりました。")

        # 2. 不足レコードの挿入
        insert_query = f"""
            INSERT INTO {tables['ai_metadata']} (post_id, post_modified)
            VALUES (%s, %s)
        """

        # executemany 用にデータを整形
        insert_data = [(row[0], row[1]) for row in missing_records]

        cursor.executemany(insert_query, insert_data)

        conn.commit()
        print(f"同期完了: {len(insert_data)} 件のレコードを新規作成し、更新日時を同期しました。")

    except Exception as e:
        print(f"エラーが発生しました: {e}")
    finally:
        if 'conn' in locals() and conn.is_connected():
            cursor.close()
            conn.close()

if __name__ == "__main__":
    main()