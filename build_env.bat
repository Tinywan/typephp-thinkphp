@echo off
call "D:\Program Files (x86)\Microsoft Visual Studio\2022\BuildTools\VC\Auxiliary\Build\vcvars64.bat" >nul
set PHP_HOME=D:\git\php\tpc_v1113_windows_x86_64
set PHPX_HOME=D:\git\php\tpc_v1113_windows_x86_64\phpx
set PATH=D:\git\php\tpc_v1113_windows_x86_64;%PATH%
cd /d D:\git\php\tpc_v1113_windows_x86_64
tpc.exe ..\typephp-think\project.yml > ..\typephp-think\build_log.txt 2>&1
