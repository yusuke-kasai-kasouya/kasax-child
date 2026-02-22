@echo off
setlocal
:: カレントディレクトリを Python/ ルートに移動
cd /d %~dp0..

echo =================================================
echo  Knowledge Classification: STEP 4 (Standardization)
echo =================================================

:: Pythonコマンドの存在確認
where python >nul 2>nul
if %errorlevel% neq 0 (
    echo [ERROR] python コマンドが見つかりません。
    echo Pythonがインストールされているか、環境変数PATHが設定されているか確認してください。
    pause
    exit /b 1
)

echo [Running] core/kc_step4_standardizer.py ...
:: 実行
python core/kc_step4_standardizer.py

:: 実行結果の判定
if %errorlevel% neq 0 (
    echo.
    echo !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
    echo  [FATAL ERROR] ステップ4の実行中に失敗しました。
    echo  上記のエラーメッセージを確認してください。
    echo !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
    pause
    exit /b 1
)

echo.
echo =================================================
echo  SUCCESS: IQ基準(100/15)への標準化が完了しました。
echo =================================================
pause