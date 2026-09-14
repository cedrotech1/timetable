@echo off
REM Start Node API on port 9000 (required for http://localhost/timetable/)
REM Keep this window open while using the app.

setlocal EnableExtensions
cd /d "%~dp0timetable-backend"
if errorlevel 1 (
  echo ERROR: timetable-backend folder not found next to this bat.
  pause
  exit /b 1
)

if not exist "node_modules\" (
  echo Installing dependencies first...
  call npm install
  if errorlevel 1 (
    echo ERROR: npm install failed.
    pause
    exit /b 1
  )
)

echo.
echo === Starting backend API on http://127.0.0.1:9000 ===
echo Keep this window OPEN. Close it to stop the API.
echo Frontend ^(XAMPP^): http://localhost/timetable/
echo.
call npm run start:dev
