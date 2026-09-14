@echo off
REM One-shot: setup DB (create + migrate + seed) then start API on port 9000
REM Use this on a fresh machine after cloning / copying the project.

setlocal EnableExtensions
set "ROOT=%~dp0"

echo.
echo ============================================
echo  UR Timetable — database setup + API start
echo ============================================
echo.

call "%ROOT%setup-database.bat" nopause
if errorlevel 1 exit /b 1

echo.
echo Starting API... ^(this window stays open^)
echo.
call "%ROOT%start-backend.bat"
