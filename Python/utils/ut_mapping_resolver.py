"""
Path: utils/ut_mapping_resolver.py
Project: Knowledge Classification (KC)
Description:
    KX構造（俯瞰と詳細）におけるデータ保存先の解決ロジック。
    詳細記事の解析成果を親（俯瞰記事）へ集約・書き換えを行います。
"""

def resolve_target_id(row):
    """
    保存先IDを決定し、処理を続行するか判定する。

    Args:
        row (dict): SQLレコード (ID, overview_from, overview_to を含む)

    Returns:
        int or None:
            - int: 有効な保存先ID (自分自身、または親のID)
            - None: 処理をスキップすべき（親記事自体の）場合
    """
    post_id = row.get('ID')
    ov_from = row.get('overview_from')
    ov_to = row.get('overview_to')

    # 1. 親（overview_from持ち）なら処理しない
    if ov_from:
        return None

    # 2. 子（overview_to持ち）なら親のIDを返す、それ以外は自身のID
    return ov_to if ov_to else post_id