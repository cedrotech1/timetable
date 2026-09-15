@echo off
REM ============================================================
REM  After PC restart — start EVERYTHING for UR Timetable
REM  - XAMPP Apache (serves http://localhost/timetable/)
REM  - Node backend API (port 9000)
REM  - Opens the app in your browser
REM
REM  First-time only: run setup-database.bat once
REM  If frontend not in htdocs yet: run deploy-to-htdocs.bat once
REM ============================================================

setlocal EnableExtensions
set "ROOT=%~dp0"
set "HTDOCS_TIMETABLE=C:\xampp\htdocs\timetable"
set "XAMPP_DIR=C:\xampp"
set "APP_URL=http://localhost/timetable/"

REM Allow other XAMPP installs
if not exist "%XAMPP_DIR%\xampp_start.exe" if exist "D:\xampp\xampp_start.exe" (
  set "XAMPP_DIR=D:\xampp"
  set "HTDOCS_TIMETABLE=D:\xampp\htdocs\timetable"
)
if not exist "%XAMPP_DIR%\xampp_start.exe" if exist "E:\xampp\xampp_start.exe" (
  set "XAMPP_DIR=E:\xampp"
  set "HTDOCS_TIMETABLE=E:\xampp\htdocs\timetable"
)

echo.
echo ============================================
echo  UR Timetable — start all
echo ============================================
echo.

REM --- 1) PostgreSQL hint ---
echo [1/4] Database: make sure PostgreSQL service is running
echo.

REM --- 2) Apache only if down — NEVER xampp_start.exe (protects other PHP apps)
echo [2/4] Checking Apache ^(timetable only; leave MySQL/other apps alone^)...
netstat -ano | findstr ":80 " | findstr "LISTENING" >nul 2>&1
if errorlevel 1 (
  if exist "%XAMPP_DIR%\apache_start.bat" (
    start "" cmd /c "cd /d "%XAMPP_DIR%" && call apache_start.bat"
    echo      Started Apache only via apache_start.bat
  ) else if exist "%XAMPP_DIR%\apache\bin\httpd.exe" (
    start "XAMPP Apache" "%XAMPP_DIR%\apache\bin\httpd.exe"
    echo      Started httpd.exe
  ) else (
    echo      WARNING: Apache not running and not found — start Apache ONLY in XAMPP.
    set "USE_VITE=1"
  )
) else (
  echo      Apache already running — not restarted
)

if not exist "%HTDOCS_TIMETABLE%\index.html" (
  if not defined USE_VITE (
    echo      WARNING: %HTDOCS_TIMETABLE%\index.html missing.
    echo      Run deploy-to-htdocs.bat once, or Vite will be used.
    set "USE_VITE=1"
  )
)

REM --- 3) Node backend ---
echo [3/4] Starting Node backend on port 9000...
if not exist "%ROOT%timetable-backend\package.json" (
  echo ERROR: timetable-backend not found at %ROOT%timetable-backend
  pause
  exit /b 1
)
start "UR Timetable Backend :9000" cmd /k "cd /d "%ROOT%timetable-backend" && if not exist node_modules npm install && npm run start:dev"

REM --- 4) Frontend (Vite only if XAMPP/htdocs not ready) ---
echo [4/4] Frontend...
if defined USE_VITE (
  echo      Starting Vite React app...
  if not exist "%ROOT%timetable-frontend\package.json" (
    echo ERROR: timetable-frontend not found
    pause
    exit /b 1
  )
  start "UR Timetable Frontend :5173" cmd /k "cd /d "%ROOT%timetable-frontend" && if not exist node_modules npm install && npm run dev"
  set "APP_URL=http://127.0.0.1:5173/"
) else (
  echo      Frontend served by Apache at %APP_URL%
)

echo.
echo Waiting a few seconds for services to come up...
timeout /t 5 /nobreak >nul

echo Opening %APP_URL%
start "" "%APP_URL%"

echo.
echo Done. Two windows may stay open:
echo   - "UR Timetable Backend :9000"  ^(keep open^)
echo   - Frontend: Apache or Vite window
echo.
echo Close those windows to stop the services.
echo.
pause
