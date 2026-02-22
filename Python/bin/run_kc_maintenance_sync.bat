@echo off
setlocal
cd /d %~dp0..

echo [Maintenance SYNC] Starting Database Cleaning...
python core/kc_maintenance_sync_metadata.py

echo.
echo Maintenance Finished.
pause
pauseecho 5秒後にこのウィンドウを閉じます...
timeout /t 5