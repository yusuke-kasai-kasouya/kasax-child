"""
Path: utils/ut_text_processor.py
Project: Knowledge Classification (KC)
Step: Utility (UT)
Description:
    プロジェクト全体で共通利用するテキスト処理関数群です。
    HTMLタグ除去、ショートコード除去、キーワードの妥当性判定など、
    解析精度を均一にするためのクレンジング処理を担います。
    ストップワードの物理除去機能を追加し、AIの文脈解析精度を向上させます。
"""


import re
import os
import json

# プロジェクトルートにある stop_words.json を読み込むためのパス設定
BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
STOP_WORDS_FILE = os.path.join(BASE_DIR, 'config', 'stop_words.json')

def load_stop_words():
    """stop_words.json から無視単語リストを読み込む"""
    if os.path.exists(STOP_WORDS_FILE):
        with open(STOP_WORDS_FILE, 'r', encoding='utf-8') as f:
            data = json.load(f)
            return data.get('ignore_list', [])
    return []

def clean_text(text, remove_stopwords=True):
    """
    AI解析・統計解析用の共通テキストクレンジング
    remove_stopwords=True の場合、stop_words.json に基づき単語を除去します。
    """
    if not text:
        return ""

    # 1. 制御文字 (\r, \n, \t) を半角スペースに置換
    text = text.replace('\r', ' ').replace('\n', ' ').replace('\t', ' ')

    # 【追記】パンくずリスト等の記号「≫」や「>」を半角スペースに置換
    # ついでに全角の「＞」なども含めておくと安全です
    text = text.replace('≫', ',').replace('>', ',').replace('＞', ',')

    # 2. WordPressショートコード [...] を除去
    text = re.sub(r'\[.*?\]', '', text)

    # 3. HTMLタグ <...> を除去
    text = re.sub(r'<[^>]+>', '', text)

    # 4. ストップワード（「とは」など）の除去
    if remove_stopwords:
        stop_words = load_stop_words()
        # 単語として独立している場合や、特定の助詞的な使われ方を想定して除去
        # (簡易的な置換ですが、TF-IDFの前処理として効果的です)
        for word in stop_words:
            if word:
                text = text.replace(word, ' ')

    # 5. 余分な連続空白を1つにまとめる
    text = re.sub(r'\s+', ' ', text).strip()

    return text

def is_valid_keyword(word, stop_words):
    """キーワード抽出用の妥当性判定（変更なし）"""
    if len(word) < 2:
        return False
    if word in stop_words:
        return False
    if re.match(r'^[a-zA-Z0-9_\s]+$', word):
        return False
    if not word.strip():
        return False
    return True