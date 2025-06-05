@echo off
:: Set your desired time interval in minutes here
set INTERVAL=1

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

echo Setting up Hostel Application Checker task...
echo Time interval set to: %INTERVAL% minutes

:: Delete existing task if it exists
schtasks /delete /tn "Hostel Application Checker" /f >nul 2>&1

:: Create the task to run every X minutes with current user
schtasks /create /tn "Hostel Application Checker" /tr "C:\xampp\php\php.exe C:\xampp\htdocs\hostelms\cron\check_pending_applications.php" /sc minute /mo %INTERVAL% /ru "%USERNAME%" /rl HIGHEST

if %errorLevel% == 0 (
    echo Task has been created successfully!
    echo The script will now run automatically every %INTERVAL% minutes.
    echo To stop the task, run: schtasks /delete /tn "Hostel Application Checker"
) else (
    echo Failed to create the task. Error code: %errorLevel%
    echo Please make sure you have administrator rights.
)

pause 