@echo off
REM Create Postgres DB + run migrations + seed initial data
REM Requires: PostgreSQL running, Node.js installed
REM Reads DB settings from timetable-backend\.env

setlocal EnableExtensions
cd /d "%~dp0timetable-backend"
if errorlevel 1 (
  echo ERROR: timetable-backend folder not found next to this bat.
  pause
  exit /b 1
)

echo.
echo === 1/4  npm install ===
call npm install
if errorlevel 1 (
  echo ERROR: npm install failed.
  pause
  exit /b 1
)

echo.
echo === 2/4  Ensure database exists ===
call node scripts\ensure-db.js
if errorlevel 1 (
  echo ERROR: Could not create/connect database. Check PostgreSQL and .env
  pause
  exit /b 1
)

echo.
echo === 3/4  Migrate tables ===
call npm run migrate
if errorlevel 1 (
  echo ERROR: migrate failed.
  pause
  exit /b 1
)

echo.
echo === 4/4  Seed data ===
REM Prefer full PHP dump if present; otherwise bootstrap sample data
set "PHP_SQL=%~dp0timetable-php\timetable-v3.sql"
if exist "%PHP_SQL%" (
  echo Importing from %PHP_SQL%
  call node scripts\import-timetable-sql.js --sql "%PHP_SQL%"
  if errorlevel 1 (
    echo WARNING: PHP import failed — trying bootstrap seed...
    call npm run seed
  )
) else (
  echo No timetable-php\timetable-v3.sql found — using bootstrap seed.
  echo ^(Place the PHP SQL dump there later for full UR data, then re-run seed:php^)
  call npm run seed
  if errorlevel 1 (
    echo ERROR: seed failed.
    pause
    exit /b 1
  )
)

echo.
echo Database ready.
echo   Login: administrator@ur.ac.rw / Admin@123
echo   Next:  run start-backend.bat
echo.
if /i not "%~1"=="nopause" pause
exit /b 0
