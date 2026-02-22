@echo off
setlocal
cd /d %~dp0..

echo =================================================
echo  Knowledge Classification: Full Process Start
echo =================================================

echo [Step 1/3] Building training samples...
python core/kc_step1_build_training_samples.py
if %errorlevel% neq 0 pause

echo.
echo [Step 2/3] Training AI model...
python core/kc_step2_train_classifier.py
if %errorlevel% neq 0 pause

echo.
echo [Step 3/3] Calculating AI context scores...
python core/kc_step3_ai_context_scorer.py
if %errorlevel% neq 0 pause

echo.
echo =================================================
echo  All processes completed successfully!
echo =================================================
pause