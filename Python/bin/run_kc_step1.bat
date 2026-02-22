@echo off
@chcp 65001 > lul
setlocal

:: プロジェクトルートディレクトリへ移動（バッチファイルの位置基準）
cd /d %~dp0..

echo ==================================================
echo [KC Project] Step 1: Building Training Samples
echo ==================================================

:: Pythonスクリプトの実行
python core/kc_step1_build_training_samples.py

if %errorlevel% neq 0 (
    echo.
    echo [ERROR] スクリプトの実行に失敗しました。
    pause
    exit /b %errorlevel%
)

echo.
echo [SUCCESS] 処理が正常に完了しました。
pause