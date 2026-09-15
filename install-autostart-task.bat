@echo off
REM ============================================================
REM  Install / update Windows Scheduled Task
REM  Runs auto-start-timetable.bat at user logon (after reboot)
REM  Run this bat ONCE as Administrator (right-click → Run as admin)
REM ============================================================

setlocal EnableExtensions
set "ROOT=%~dp0"
set "SCRIPT=%ROOT%auto-start-timetable.bat"
set "TASK_NAME=UR Timetable Auto Start"

if not exist "%SCRIPT%" (
  echo ERROR: Missing %SCRIPT%
  pause
  exit /b 1
)

echo.
echo Creating scheduled task: "%TASK_NAME%"
echo Script: %SCRIPT%
echo.

REM Delete old task if present, then create at logon with 30s delay
schtasks /Delete /TN "%TASK_NAME%" /F >nul 2>&1

schtasks /Create /TN "%TASK_NAME%" /TR "\"%SCRIPT%\"" /SC ONLOGON /RL HIGHEST /DELAY 0000:30 /F
if errorlevel 1 (
  echo.
  echo Failed. Right-click this bat → Run as administrator.
  pause
  exit /b 1
)

echo.
echo OK — task installed.
echo.
echo After every reboot / login it will start:
echo   - XAMPP Apache  ^(http://localhost/timetable/^)
echo   - Node backend  ^(port 9000^)
echo.
echo Logs: %ROOT%logs\auto-start.log
echo.
echo Test now without reboot:
echo   schtasks /Run /TN "%TASK_NAME%"
echo.
echo Remove later:
echo   schtasks /Delete /TN "%TASK_NAME%" /F
echo.
pause
