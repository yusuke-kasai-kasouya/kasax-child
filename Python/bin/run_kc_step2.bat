@echo off
@chcp 65001 > nul
setlocal

:: プロジェクトルートディレクトリへ移動（バッチファイルの位置基準）
cd /d %~dp0..

echo ==================================================
echo [KC Project] Step 2: Training AI Classifier
echo ==================================================

:: Pythonスクリプトの実行
python core/kc_step2_train_classifier.py

if %errorlevel% neq 0 (
    echo.
    echo [ERROR] AIモデルの学習に失敗しました。
    echo Step 1 の CSV ファイルが存在するか確認してください。
    pause
    exit /b %errorlevel%
)

echo.
echo [SUCCESS] AIモデルの学習と保存が正常に完了しました。
pause