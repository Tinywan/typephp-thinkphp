@echo off
setlocal
if "%PHP_HOME%"=="" set "PHP_HOME=D:\workspace\tpc_v0.6.5_windows_x86_64"
set "PHPRC=%PHP_HOME%"
set "PATH=%PHP_HOME%;%PATH%"

cd /d %~dp0
.\build\typephp-server.exe %*
