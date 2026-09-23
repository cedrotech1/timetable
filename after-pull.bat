@echo off
REM ============================================================
REM  AFTER PULL / AFTER CODE FIXES
REM  1) git pull
REM  2) npm install (backend + frontend)
REM  3) rebuild React → copy to XAMPP htdocs\timetable
REM  4) restart Node backend + ensure Apache is up
REM
REM  Use this whenever you pull changes or finish a fix.
REM ============================================================

setlocal EnableExtensions
set "ROOT=%~dp0"
set "HTDOCS_TIMETABLE=C:\xampp\htdocs\timetable"
set "XAMPP_DIR=C:\xampp"
set "APP_URL=http://localhost/timetable/"

if exist "D:\xampp\htdocs" (
  set "XAMPP_DIR=D:\xampp"
  set "HTDOCS_TIMETABLE=D:\xampp\htdocs\timetable"
)
if exist "E:\xampp\htdocs" (
  set "XAMPP_DIR=E:\xampp"
  set "HTDOCS_TIMETABLE=E:\xampp\htdocs\timetable"
)

echo.
echo ============================================
echo  UR Timetable — pull, rebuild, restart
echo ============================================
echo.

REM --- 1) Pull ---
echo [1/5] git pull...
cd /d "%ROOT%"
git pull
if errorlevel 1 (
  echo WARNING: git pull failed — continuing with local files...
)

REM --- 2) Backend deps (+ optional migrate) ---
echo.
echo [2/5] Backend npm install...
cd /d "%ROOT%timetable-backend"
if errorlevel 1 (
  echo ERROR: timetable-backend missing
  pause
  exit /b 1
)
call npm install
if errorlevel 1 (
  echo ERROR: backend npm install failed
  pause
  exit /b 1
)

echo       Running migrations ^(safe if already up to date^)...
call npm run migrate
if errorlevel 1 (
  echo WARNING: migrate failed — check PostgreSQL / .env
)

REM --- 3) Frontend build → htdocs ---
echo.
echo [3/5] Frontend build for /timetable/ ...
cd /d "%ROOT%timetable-frontend"
call npm install --fetch-retries=5 --fetch-retry-mintimeout=20000 --fetch-retry-maxtimeout=120000
if errorlevel 1 (
  echo WARNING: npm install failed once — retrying...
  timeout /t 3 /nobreak >nul
  call npm install --fetch-retries=5 --fetch-retry-mintimeout=20000 --fetch-retry-maxtimeout=120000
)
if errorlevel 1 (
  echo ERROR: frontend npm install failed ^(network^). Retry later or check internet/proxy.
  pause
  exit /b 1
)
call npm run build:htdocs
if errorlevel 1 (
  echo ERROR: frontend build failed
  pause
  exit /b 1
)

echo       Copying dist → %HTDOCS_TIMETABLE%
if not exist "%HTDOCS_TIMETABLE%" mkdir "%HTDOCS_TIMETABLE%"
robocopy "%ROOT%timetable-frontend\dist" "%HTDOCS_TIMETABLE%" /E /NFL /NDL /NJH /NJS /nc /ns /np
if errorlevel 8 (
  echo ERROR: copy to htdocs failed
  pause
  exit /b 1
)

REM --- 4) Stop old Node on :9000 so new code loads ---
echo.
echo [4/5] Restarting Node backend on port 9000...
for /f "tokens=5" %%P in ('netstat -ano ^| findstr ":9000" ^| findstr "LISTENING"') do (
  echo       Killing old process PID %%P
  taskkill /F /PID %%P >nul 2>&1
)
timeout /t 2 /nobreak >nul

start "UR Timetable Backend :9000" cmd /k "cd /d "%ROOT%timetable-backend" && npm run start:dev"

REM --- 5) Apache 1— DO NOT call xampp_start.exe (that restarts whole stack / other PHP apps)
echo.
echo [5/5] Checking Apache ^(leave other XAMPP services alone^)...
netstat -ano | findstr ":80 " | findstr "LISTENING" >nul 2>&1
if errorlevel 1 (
  echo       Apache is NOT on port 80.
  if exist "%XAMPP_DIR%\apache_start.bat" (
    echo       Starting Apache ONLY ^(not MySQL / full XAMPP^)...
    start "" cmd /c "cd /d "%XAMPP_DIR%" && call apache_start.bat"
  ) else (
    echo       WARNING: start Apache manually in XAMPP Control Panel ^(Apache only^).
    echo       Do NOT use "Start all" if other staff PHP sites are already fine.
  )
) else (
  echo       Apache already running — left untouched ^(other PHP apps safe^)
)

timeout /t 4 /nobreak >nul
start "" "%APP_URL%"

echo.
echo Done.
echo   App:     %APP_URL%
echo   Backend: keep the "UR Timetable Backend :9000" window open
echo   Hard refresh browser: Ctrl+F5
echo.
pause
