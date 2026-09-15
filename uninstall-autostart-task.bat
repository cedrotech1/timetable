@echo off
REM Remove the UR Timetable Auto Start scheduled task
setlocal
schtasks /Delete /TN "UR Timetable Auto Start" /F
if errorlevel 1 (
  echo Task not found or need Admin rights.
) else (
  echo Scheduled task removed.
)
pause
