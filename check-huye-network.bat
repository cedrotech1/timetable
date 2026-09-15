@echo off
REM ============================================================
REM  Diagnose why huye.ur.ac.rw times out /timetable fails
REM  Run ON THE SERVER (WIN-FGV1PMK1NBA) as Administrator
REM ============================================================

setlocal EnableExtensions
set "DOMAIN=huye.ur.ac.rw"
set "LOG=%~dp0logs\network-check.log"
if not exist "%~dp0logs" mkdir "%~dp0logs"

echo.
echo ============================================
echo  Network / port check for %DOMAIN%
echo ============================================
echo.
echo Writing log to %LOG%
echo ========== %DATE% %TIME% ========== > "%LOG%"

echo.
echo [1] Computer name / IPs
hostname
hostname >> "%LOG%"
ipconfig | findstr /I "IPv4"
ipconfig | findstr /I "IPv4" >> "%LOG%"

echo.
echo [2] Resolve domain DNS
nslookup %DOMAIN%
nslookup %DOMAIN% >> "%LOG%" 2>&1

echo.
echo [3] Listening ports 80 / 443 / 9000
echo --- Port 80 ---
netstat -ano | findstr ":80 " | findstr "LISTENING"
netstat -ano | findstr ":80 " | findstr "LISTENING" >> "%LOG%"
echo --- Port 443 ---
netstat -ano | findstr ":443 " | findstr "LISTENING"
netstat -ano | findstr ":443 " | findstr "LISTENING" >> "%LOG%"
echo --- Port 9000 ^(Node API^) ---
netstat -ano | findstr ":9000" | findstr "LISTENING"
netstat -ano | findstr ":9000" | findstr "LISTENING" >> "%LOG%"

echo.
echo [4] Which process owns those ports
for /f "tokens=5" %%P in ('netstat -ano ^| findstr ":80 " ^| findstr "LISTENING"') do (
  echo PID %%P on :80
  tasklist /FI "PID eq %%P"
  tasklist /FI "PID eq %%P" >> "%LOG%"
)
for /f "tokens=5" %%P in ('netstat -ano ^| findstr ":443 " ^| findstr "LISTENING"') do (
  echo PID %%P on :443
  tasklist /FI "PID eq %%P"
)

echo.
echo [5] Local HTTP test ^(from this server^)
echo Testing http://127.0.0.1/ ...
curl -s -o NUL -w "localhost root  HTTP %%{http_code} time %%{time_total}s\n" --connect-timeout 5 http://127.0.0.1/ 2>nul
if errorlevel 1 echo curl failed or not installed — try browser http://localhost/

echo Testing http://127.0.0.1/timetable/ ...
curl -s -o NUL -w "localhost /timetable HTTP %%{http_code} time %%{time_total}s\n" --connect-timeout 5 http://127.0.0.1/timetable/ 2>nul

echo Testing http://%DOMAIN%/ ...
curl -s -o NUL -w "domain root     HTTP %%{http_code} time %%{time_total}s\n" --connect-timeout 8 http://%DOMAIN%/ 2>nul
if errorlevel 1 echo FAIL: cannot reach %DOMAIN% from this server ^(DNS/firewall/bind^)

echo Testing https://%DOMAIN%/ ...
curl -k -s -o NUL -w "domain https   HTTP %%{http_code} time %%{time_total}s\n" --connect-timeout 8 https://%DOMAIN%/ 2>nul
if errorlevel 1 echo FAIL: HTTPS %DOMAIN% not reachable

echo Testing http://%DOMAIN%/timetable/ ...
curl -s -o NUL -w "domain /timetable HTTP %%{http_code}\n" --connect-timeout 8 http://%DOMAIN%/timetable/ 2>nul

echo.
echo [6] Windows Firewall rules ^(80 / 443^)
netsh advfirewall firewall show rule name=all | findstr /I "80 443 Apache HTTP HTTPS World"
echo.
echo Creating inbound allow rules if missing ^(safe; does not stop other apps^)...
netsh advfirewall firewall add rule name="UR Apache HTTP 80" dir=in action=allow protocol=TCP localport=80 >nul 2>&1
netsh advfirewall firewall add rule name="UR Apache HTTPS 443" dir=in action=allow protocol=TCP localport=443 >nul 2>&1
echo Rules ensured: "UR Apache HTTP 80" and "UR Apache HTTPS 443"

echo.
echo [7] Apache listens on which address?
netstat -ano | findstr ":80 " | findstr "LISTENING"
echo If you only see 127.0.0.1:80  — Apache is LOCAL ONLY ^(outside world times out^).
echo You need 0.0.0.0:80 or the server LAN IP:80

echo.
echo ============================================
echo  How to read results
echo ============================================
echo.
echo ERR_CONNECTION_TIMED_OUT from campus PCs usually means:
echo   A^) Windows Firewall / campus firewall blocks 80/443 inbound
echo   B^) Apache listens only on 127.0.0.1 ^(not 0.0.0.0^)
echo   C^) DNS for %DOMAIN% points to wrong IP
echo   D^) Server has no public/LAN route from your PC
echo.
echo localhost works  = Apache + timetable files are OK on this machine
echo domain times out = network path to this server is blocked
echo.
echo Fix checklist:
echo   1. On server browser open http://localhost/timetable/  ^(must work^)
echo   2. netstat must show 0.0.0.0:80 or YOUR-LAN-IP:80 LISTENING
echo   3. Firewall allow TCP 80 and 443 inbound
echo   4. nslookup %DOMAIN% must show THIS server IP
echo   5. From another PC:  ping %DOMAIN%   and   telnet %DOMAIN% 80
echo   6. Node API: keep start-backend / after-pull running on :9000
echo.
echo Log saved: %LOG%
echo.
pause
