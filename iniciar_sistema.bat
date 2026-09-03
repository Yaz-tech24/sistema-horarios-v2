@echo off
setlocal
title Sistema de Horarios - FAGRENM/UCM

set XAMPP=C:\xampp
rem 127.0.0.1 em vez de localhost: nesta maquina ha um contentor Docker
rem (projetosucm/Caddy) que tambem ocupa a porta 80 e "rouba" o localhost
rem via IPv6. 127.0.0.1 forca IPv4, que e onde a XAMPP fica.
set URL=http://127.0.0.1/sistema-horarios-fagrenm/

echo ============================================
echo   A iniciar o Sistema de Horarios FAGRENM/UCM
echo ============================================
echo.

echo [1/3] Apache...
tasklist /FI "IMAGENAME eq httpd.exe" 2>NUL | find /I "httpd.exe" >NUL
if "%ERRORLEVEL%"=="0" (
    echo       Ja esta em execucao, a saltar.
) else (
    start "XAMPP - Apache" "%XAMPP%\apache_start.bat"
)

echo [2/3] MySQL...
tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I "mysqld.exe" >NUL
if "%ERRORLEVEL%"=="0" (
    echo       Ja esta em execucao, a saltar.
) else (
    start "XAMPP - MySQL" "%XAMPP%\mysql_start.bat"
)

echo [3/3] A aguardar os servicos ficarem prontos...
timeout /t 6 /nobreak >nul

echo.
echo A abrir o sistema no browser: %URL%
start "" "%URL%"

echo.
echo Sistema iniciado. Pode fechar esta janela ou deixa-la aberta
echo para ver o estado do Apache/MySQL.
pause
