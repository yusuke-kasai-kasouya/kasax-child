@echo off
@chcp 65001 > nul
setlocal

:: プロジェクトルートディレクトリへ移動
cd /d %~dp0..

echo ==================================================
echo [KC Project] Step MX: Integrated Content Analyzer
echo ==================================================
echo AIモデルと統計重みを使用してスコアを算出します...
echo.

:: Pythonスクリプトの実行
python core/mx_run_integrated_scorer.py

if %errorlevel% neq 0 (
    echo.
    echo [ERROR] 解析処理中にエラーが発生しました。
    echo models/ 内に .pkl ファイルと .json ファイルがあるか確認してください。
    timeout /t 10
    exit /b %errorlevel%
)

echo.
echo [SUCCESS] 全記事の統合解析が完了しました。
echo データベース wp_kx_ai_metadata を確認してください。
echo.
echo 5秒後にこのウィンドウを閉じます...
timeout /t 5