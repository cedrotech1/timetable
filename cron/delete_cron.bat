@echo off
:: Check for admin rights
net session >nul 2>&1
if %errorLevel% == 0 (
    echo Running with administrator privileges...
) else (
    echo Please run this script as Administrator!
    echo Right-click on this file and select "Run as administrator"
    pause
    exit /b 1
)

echo Deleting Hostel Application Checker task...

:: Delete the task
schtasks /delete /tn "Hostel Application Checker" /f

if %errorLevel% == 0 (
    echo Task has been deleted successfully!
) else (
    echo Failed to delete the task. Error code: %errorLevel%
    echo Please make sure you have administrator rights.
)

pause 