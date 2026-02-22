"""
Path: core/kc_maintenance_cleaner.py
Project: Knowledge Classification (KC)
Description:
    データベースの整合性チェック。
    wp_kx_0 に存在しない ID が wp_kx_ai_metadata に残っている場合、それらを削除します。
"""

import mysql.connector
import json
import os
import sys

# 自身のファイル場所からプロジェクトルート(Python/)を確実に特定
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

    print("データベース整合性チェックを開始します...")

    try:
        conn = mysql.connector.connect(**db_set)
        cursor = conn.cursor()

        # 1. 削除対象の特定 (master_index に存在しない metadata 内のレコード)
        find_query = f"""
            SELECT m.post_id
            FROM {tables['ai_metadata']} m
            LEFT JOIN {tables['master_index']} k0 ON m.post_id = k0.id
            WHERE k0.id IS NULL
        """
        cursor.execute(find_query)
        orphans = cursor.fetchall()

        if not orphans:
            print("クレンジングの必要はありません。整合性は保たれています。")
            return

        orphan_ids = [row[0] for row in orphans]
        print(f"削除対象（孤立データ）: {len(orphan_ids)} 件見つかりました。")

        # 2. 削除の実行
        delete_query = f"DELETE FROM {tables['ai_metadata']} WHERE post_id IN ({','.join(['%s'] * len(orphan_ids))})"
        cursor.execute(delete_query, tuple(orphan_ids))

        conn.commit()
        print(f"クレンジング完了: {cursor.rowcount} 件のデータを削除しました。")

    except Exception as e:
        print(f"エラーが発生しました: {e}")
    finally:
        if 'conn' in locals() and conn.is_connected():
            cursor.close()
            conn.close()

if __name__ == "__main__":
    main()