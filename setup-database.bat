@echo off
REM Create Postgres DB + migrate + import bundled seed SQL (full campuses/schools/users/...)
REM Requires: PostgreSQL running, Node.js installed
REM Seed file: timetable-backend\data\ur-timetable-seed.sql
REM Does NOT use external timetable-v3.sql / timetable-php paths.

setlocal EnableExtensions
cd /d "%~dp0timetable-backend"
if errorlevel 1 (
  echo ERROR: timetable-backend folder not found next to this bat.
  pause
  exit /b 1
)

set "SEED_SQL=%cd%\data\ur-timetable-seed.sql"
if not exist "%SEED_SQL%" (
  echo ERROR: Missing seed file:
  echo   %SEED_SQL%
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
echo === 4/4  Import seed data from data\ur-timetable-seed.sql ===
call node scripts\import-timetable-sql.js --sql "%SEED_SQL%"
if errorlevel 1 (
  echo ERROR: seed import failed.
  pause
  exit /b 1
)

echo.
echo Database ready ^(full seed imported^).
echo   Login password for imported users: see SEED_USER_PASSWORD in .env ^(default 23122312^)
echo   Next:  run start-backend.bat
echo.
if /i not "%~1"=="nopause" pause
exit /b 0
