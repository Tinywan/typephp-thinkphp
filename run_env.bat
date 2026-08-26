@echo off
rem Run helper: set SDK DLL path then launch myapp.exe (php.ini depends on CWD)
cd /d %~dp0
set PATH=D:\git\php\tpc_v0.6.5_windows_x86_64;%PATH%
.\build\myapp.exe %*
