@echo off
setlocal
:: Move to Project Root
cd /d %~dp0..

echo ======================================================
echo  KC System: Daily Integrated Analysis
echo ======================================================

echo.
echo [1/4] Running Mixed Analysis (MX)...
echo ------------------------------------------------------
python core/mx_run_integrated_scorer.py
if %errorlevel% neq 0 (
    echo [ERROR] MX Analysis failed.
    pause
    exit /b %errorlevel%
)

:: --- Execute STEP 3 ---
echo.
echo [2/4] Calculating AI Context Scores (STEP 3)...
echo ------------------------------------------------------
python core/kc_step3_ai_context_scorer.py
if %errorlevel% neq 0 (
    echo.
    echo [ERROR] STEP 3 failed with error level %errorlevel%.
    pause
    exit /b %errorlevel%
)

:: --- Execute STEP 4 ---
echo.
echo [3/4] Standardizing Scores to IQ Scale (STEP 4)...
echo ------------------------------------------------------
python core/kc_step4_standardizer.py
if %errorlevel% neq 0 (
    echo.
    echo [ERROR] STEP 4 failed with error level %errorlevel%.
    pause
    exit /b %errorlevel%
)


:: --- Vectorizer Analyzer ---
echo.
echo [4/4] [KC Project] Step MX: Vectorizer Analyzer...
echo ------------------------------------------------------
python core/mx_run_vectorizer.py
if %errorlevel% neq 0 (
    echo.
    echo [ERROR] Vectorizer Analyzer %errorlevel%.
    pause
    exit /b %errorlevel%
)

echo.
echo ------------------------------------------------------
echo  Process completed successfully.
echo  Raw scores have been converted to IQ Deviation.
echo ------------------------------------------------------
echo.
echo Closing this window in 5 seconds...
timeout /t 5