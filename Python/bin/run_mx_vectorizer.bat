@echo off
@chcp 65001 > nul
setlocal

:: プロジェクトルートディレクトリへ移動
cd /d %~dp0..

echo ==================================================
echo [KC Project] Step MX: Vectorizer Analyzer
echo ==================================================
echo.

:: Pythonスクリプトの実行
python core/mx_run_vectorizer.py

if %errorlevel% neq 0 (
    echo.
    echo [ERROR] 解析処理中にエラーが発生しました。
    echo models/ 内に .pkl ファイルと .json ファイルがあるか確認してください。
    pause
    exit /b %errorlevel%
)

echo.
echo [SUCCESS] 全記事の統合解析が完了しました。
echo データベース wp_kx_ai_metadata を確認してください。
pause