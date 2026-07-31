@echo off
call "C:\Program Files\Microsoft Visual Studio\2022\Community\VC\Auxiliary\Build\vcvarsall.bat" x64
cd /d C:\git\source\tpc_v1095_windows_x86_64
tpc.exe C:\git\php\typephp-think\project.yml -m bin -O2
pause
