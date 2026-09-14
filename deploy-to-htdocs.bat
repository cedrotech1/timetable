@echo off
REM Build React for http://localhost/timetable/ then copy into XAMPP htdocs.
REM Usage (on the XAMPP PC):
REM   1. Put this whole repo anywhere (or under htdocs)
REM   2. Edit HTDOCS_TIMETABLE below if needed
REM   3. Run this bat
REM   4. Keep Node backend running: cd timetable-backend && npm run start:dev

setlocal
set "HTDOCS_TIMETABLE=C:\xampp\htdocs\timetable"
set "ROOT=%~dp0"

echo.
echo === Building frontend for /timetable/ ===
cd /d "%ROOT%timetable-frontend"
call npm install
if errorlevel 1 exit /b 1
call npm run build:htdocs
if errorlevel 1 exit /b 1

echo.
echo === Copying dist to %HTDOCS_TIMETABLE% ===
if not exist "%HTDOCS_TIMETABLE%" mkdir "%HTDOCS_TIMETABLE%"
robocopy "%ROOT%timetable-frontend\dist" "%HTDOCS_TIMETABLE%" /E /NFL /NDL /NJH /NJS /nc /ns /np
if errorlevel 8 exit /b 1

echo.
echo Done.
echo   Open:  http://localhost/timetable/
echo   DB:    run setup-database.bat once ^(PostgreSQL must be running^)
echo   API:   run start-backend.bat and keep it open ^(port 9000^)
echo   Or:    setup-and-start-backend.bat does DB + API together
echo   PHP:   other folders in htdocs are unchanged
echo.
pause
