@echo off
REM ============================================================
REM  AUTO START — for Windows Task Scheduler (at logon / startup)
REM  Starts: XAMPP Apache (React in htdocs) + Node API :9000
REM  No pause / no prompts — safe for scheduled runs.
REM
REM  Register once with:  install-autostart-task.bat
REM  Or Task Scheduler → Action → this file
REM ============================================================

setlocal EnableExtensions
set "ROOT=%~dp0"
set "LOGDIR=%ROOT%logs"
set "LOG=%LOGDIR%\auto-start.log"
set "HTDOCS_TIMETABLE=C:\xampp\htdocs\timetable"
set "XAMPP_DIR=C:\xampp"

if not exist "%LOGDIR%" mkdir "%LOGDIR%"

echo.>> "%LOG%"
echo ========== %DATE% %TIME% ==========>> "%LOG%"
echo Auto-start begin>> "%LOG%"

REM Wait after reboot so PostgreSQL / network can come up
timeout /t 25 /nobreak >nul

if not exist "%XAMPP_DIR%\xampp_start.exe" if exist "D:\xampp\xampp_start.exe" (
  set "XAMPP_DIR=D:\xampp"
  set "HTDOCS_TIMETABLE=D:\xampp\htdocs\timetable"
)
if not exist "%XAMPP_DIR%\xampp_start.exe" if exist "E:\xampp\xampp_start.exe" (
  set "XAMPP_DIR=E:\xampp"
  set "HTDOCS_TIMETABLE=E:\xampp\htdocs\timetable"
)

REM --- Apache / frontend ---
netstat -ano | findstr ":80 " | findstr "LISTENING" >nul 2>&1
if errorlevel 1 (
  echo Starting XAMPP Apache...>> "%LOG%"
  if exist "%XAMPP_DIR%\xampp_start.exe" (
    start "" "%XAMPP_DIR%\xampp_start.exe"
  ) else if exist "%XAMPP_DIR%\apache_start.bat" (
    start "" /min cmd /c "cd /d "%XAMPP_DIR%" && call apache_start.bat"
  ) else if exist "%XAMPP_DIR%\apache\bin\httpd.exe" (
    start "" /min "%XAMPP_DIR%\apache\bin\httpd.exe"
  ) else (
    echo XAMPP not found — starting Vite frontend>> "%LOG%"
    set "USE_VITE=1"
  )
) else (
  echo Port 80 already listening — skip Apache>> "%LOG%"
)

if not exist "%HTDOCS_TIMETABLE%\index.html" (
  if not defined USE_VITE (
    echo htdocs timetable missing — starting Vite>> "%LOG%"
    set "USE_VITE=1"
  )
)

REM --- Node backend :9000 ---
netstat -ano | findstr ":9000" | findstr "LISTENING" >nul 2>&1
if errorlevel 1 (
  echo Starting Node backend :9000...>> "%LOG%"
  if not exist "%ROOT%timetable-backend\package.json" (
    echo ERROR: backend folder missing>> "%LOG%"
    exit /b 1
  )
  start "UR Timetable Backend :9000" /min cmd /k "cd /d "%ROOT%timetable-backend" && if not exist node_modules npm install && npm run start:dev"
) else (
  echo Port 9000 already listening — skip backend>> "%LOG%"
)

REM --- Vite frontend only if needed ---
if defined USE_VITE (
  netstat -ano | findstr ":5173" | findstr "LISTENING" >nul 2>&1
  if errorlevel 1 (
    echo Starting Vite :5173...>> "%LOG%"
    start "UR Timetable Frontend :5173" /min cmd /k "cd /d "%ROOT%timetable-frontend" && if not exist node_modules npm install && npm run dev"
  ) else (
    echo Port 5173 already listening — skip Vite>> "%LOG%"
  )
)

echo Auto-start done>> "%LOG%"
exit /b 0
