@echo off
@chcp 65001 > nul
setlocal enabledelayedexpansion

:: --------------------------------------------------
:: [KC Project] Python系 全機能一括実行パイプライン
:: --------------------------------------------------
:: このバッチは Python/bin/ に配置されることを想定しています
cd /d %~dp0..

echo ==================================================
echo  KxSystem: AI/Statistical Analysis Pipeline
echo ==================================================
echo 実行開始時刻: %date% %time%
echo プロジェクトルート: %cd%
echo.

:: --- STEP 1: GA (Global Analysis) ---
echo [STEP 1/7] GA: 統計的重要語重みの定義を開始...
echo ※GiNZAモデルのロードには時間がかかる場合があります。
python core/ga_def_term_weights.py
if %errorlevel% neq 0 goto :error

:: --- STEP 2: KC Step 1 (Samples) ---
echo.
echo [STEP 2/7] KC: 学習サンプルの構築を開始...
python core/kc_step1_build_training_samples.py
if %errorlevel% neq 0 goto :error

:: --- STEP 3: KC Step 2 (Training) ---
echo.
echo [STEP 3/7] KC: AI分類モデルの学習を開始...
python core/kc_step2_train_classifier.py
if %errorlevel% neq 0 goto :error

:: --- STEP 4: KC Step 3 (Scoring) ---
echo.
echo [STEP 4/7] KC: AIコンテキストスコアの算出を開始...
python core/kc_step3_ai_context_scorer.py
if %errorlevel% neq 0 goto :error

:: --- STEP 5: KC Step 4 (Standardization) ---
echo.
echo [STEP 5/7] KC: スコアの標準化 (IQ100/15基準) を実行...
python core/kc_step4_standardizer.py
if %errorlevel% neq 0 goto :error

:: --- STEP 6: MX (Integrated) ---
echo.
echo [STEP 6/7] MX: 統計重みとAIスコアの統合解析を開始...
python core/mx_run_integrated_scorer.py
if %errorlevel% neq 0 goto :error

:: --- STEP 7: VEC (Vectorization) ---
echo.
echo [STEP 7/7] VEC: 意味ベクトル(Embedding)の生成を開始...
:: #7のコードが core/mx_run_vectorizer.py として保存されているか確認
if exist core\mx_run_vectorizer.py (
    python core/mx_run_vectorizer.py
) else (
    echo [SKIP] core/mx_run_vectorizer.py が存在しないためスキップします。
)
if %errorlevel% neq 0 goto :error

:: --- 完了 ---
echo.
echo ==================================================
echo [SUCCESS] すべての工程が正常に完了しました。
echo 完了時刻: %date% %time%
echo ==================================================
pause
exit /b 0

:error
echo.
echo !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
echo [FATAL ERROR] ステップ実行中にエラーが発生しました。
echo 処理を中断します。ログを確認してください。
echo !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
pause
exit /b %errorlevel%