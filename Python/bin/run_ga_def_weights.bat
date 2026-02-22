@echo off
@chcp 65001 > nul
setlocal

:: プロジェクトルートディレクトリへ移動（バッチファイルの位置基準）
cd /d %~dp0..

echo ==================================================
echo [KC Project] Step GA: Global Analysis (Statistical)
echo ==================================================
echo ※日本語解析モデル(GiNZA)のロードに数十分かかる場合があります。
echo.

:: Pythonスクリプトの実行
python core/ga_def_term_weights.py

if %errorlevel% neq 0 (
    echo.
    echo [ERROR] 統計解析に失敗しました。
    echo config/config.json および stop_words.json の配置を確認してください。
    pause
    exit /b %errorlevel%
)

echo.
echo [SUCCESS] 全域の重要語重み定義が完了しました。
echo 保存先: models/global_term_weights.json
pause