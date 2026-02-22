@echo off
setlocal
cd /d %~dp0..

echo [Maintenance] Starting Database Cleaning...
python core/kc_maintenance_cleaner.py

echo.
echo Maintenance Finished.
pauseecho 5秒後にこのウィンドウを閉じます...
timeout /t 5