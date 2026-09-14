@echo off
REM Start React frontend only (Vite). Prefer start-all.bat after reboot.

setlocal EnableExtensions
cd /d "%~dp0timetable-frontend"
if errorlevel 1 (
  echo ERROR: timetable-frontend folder not found next to this bat.
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
echo === Starting frontend ^(Vite^) ===
echo Keep this window OPEN.
echo Dev URL: http://127.0.0.1:5173/
echo XAMPP:   use start-all.bat / deploy-to-htdocs.bat for http://localhost/timetable/
echo.
call npm run dev
