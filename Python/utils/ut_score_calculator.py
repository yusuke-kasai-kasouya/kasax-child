"""
Path: utils/ut_score_calculator.py
Description: AI文脈・統計・構造メタデータを統合して「知識度」を算出する統合エンジン
"""
import re
import json

def calculate_integrated_score(raw_content, kx1_row, model, vectorizer, term_weights, config, clean_func):
    sc = config.get('scoring_config', {})
    if not raw_content:
        return 0.0, 0.0, 0.0

    cleaned_body = clean_func(raw_content)
    text_len = len(cleaned_body)
    all_tags = re.findall(r'<[^>]+>', raw_content)
    tag_count = len(all_tags)

    # --- A. 外形統計評価 (score_stat) の算出 ---
    # 1. 本文ボリューム (リミッター解除)
    conf_eval = sc.get('content_evaluation', {})
    # 以前: min(..., base_score_cap) で制限していたのを廃止
    base_score = text_len / conf_eval.get('length_divisor', 100.0)

    tag_density = (tag_count * 100) / (text_len + 1)
    penalty = max(0, (tag_density - conf_eval.get('tag_density_threshold', 1.5)) * conf_eval.get('penalty_multiplier', 15.0))
    content_eval_score = max(0.0, base_score - penalty)

    # 2. 構造的ボーナス (リミッター解除)
    structural_bonus = 0.0
    conf_struct = sc.get('structural_bonus', {})
    if kx1_row is not None:
        if kx1_row.get('tag'): structural_bonus += conf_struct.get('tag', 20.0)
        if kx1_row.get('consolidated_from'): structural_bonus += conf_struct.get('consolidated_from', 20.0)
        # 以前: structural_bonus = min(structural_bonus, 30.0) を廃止

    # 3. 統計キーワード評価 (リミッター解除)
    match_count = sum(1 for word in term_weights if word in cleaned_body)
    kw_conf = sc.get('stat_keyword_weight', {})
    # 以前: min(..., max_score) を廃止
    stat_keyword_score = match_count * kw_conf.get('multiplier', 2.0)

    # --- Statの統合 (素点のまま扱う) ---
    # 以前: 80点満点を100点満点に変換（1.25倍）し、100でキャップしていたのを廃止
    final_stat_score = float(content_eval_score + structural_bonus + stat_keyword_score)

    # --- B. AIコンテキスト評価 (score_context) の算出 ---
    if model and vectorizer:
        X = vectorizer.transform([cleaned_body])
        ai_val = model.predict(X)[0]
        # Ridge回帰の予測値をそのまま採用（負の値も理論上許容するなら max(0) も外すが、一旦 0 下限のみ維持）
        ai_context_score = float(ai_val)
    else:
        ai_context_score = 0.0

    # --- C. ポストサイズによるスコア調整 (巨大ポスト減衰 & 極小ポスト足切り) ---
    lim_conf = sc.get('length_limit_settings', {})

    # 1. 下限足切り：小さすぎる記事は「知恵」ではないと断じる
    # 例: 30文字未満などは問答無用で 0.0
    min_limit = lim_conf.get('min_limit', 30)
    if text_len < min_limit:
        final_stat_score = 0.0
        ai_context_score = 0.0

    # 2. 上限減衰：巨大すぎる記事のノイズ化を防ぐ (既存ロジック)
    elif text_len > lim_conf.get('hard_limit', 10000):
        final_stat_score = 0.0
        ai_context_score = 0.0
    elif text_len > lim_conf.get('soft_limit', 5000):
        reduction = (lim_conf['hard_limit'] - text_len) / (lim_conf['hard_limit'] - lim_conf['soft_limit'])
        final_stat_score *= float(max(0, reduction))
        ai_context_score *= float(max(0, reduction))

    # 最終統合スコア (50:50)
    final_total = (final_stat_score + ai_context_score) / 2.0

    # 以前: round(..., 4) を行っていたが、解像度維持のため丸めずに return
    return (
        float(final_total),
        float(final_stat_score),
        float(ai_context_score)
    )