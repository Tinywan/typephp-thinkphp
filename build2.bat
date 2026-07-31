@echo off
call "C:\Program Files\Microsoft Visual Studio\2022\Community\VC\Auxiliary\Build\vcvarsall.bat" x64
echo === VC Environment Ready ===
where cl.exe
echo === Running tpc ===
set PHP_HOME=C:\git\source\tpc_v1095_windows_x86_64
set PATH=%PHP_HOME%;%PATH%
cd /d C:\git\php\typephp-think
"%PHP_HOME%\tpc.exe" project.yml > tpc_output.txt 2>&1
echo === Exit code: %ERRORLEVEL% ===
type tpc_output.txt
pause
