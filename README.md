<div align="center">

# ⚡ TypePHP × ThinkPHP 8.0 原生 AOT 编译工程

<p align="center">
  <strong>基于 TypePHP (TPC) AOT 编译器与 ThinkPHP 8.x 打造的 Windows 原生高性能自包含二进制 Web 服务</strong>
</p>

[![PHP Version](https://img.shields.io/badge/PHP-8.5.10_Embed-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![Framework](https://img.shields.io/badge/ThinkPHP-8.x-29B6F6?style=for-the-badge&logoColor=white)](https://www.thinkphp.cn/)
[![Compiler](https://img.shields.io/badge/Compiler-TypePHP_TPC_v1096-10B981?style=for-the-badge&logoColor=white)](https://typephp.com/)
[![Platform](https://img.shields.io/badge/Platform-Windows_x86__64-0078D6?style=for-the-badge&logo=windows&logoColor=white)](https://microsoft.com/)

</div>

## 一、 环境要求与准备

> [!TIP]
> 📖 **环境配置详解与详细图文教程**：可参考微信公众号 **开源技术小栈** 官方发布文章：[《TypePHP Windows 环境搭建与深度编译实战》](https://mp.weixin.qq.com/s/QTaIYHn9mF-HqyeCJP4JLA)。

### 1. 软件环境矩阵

| 组件 | 要求版本 | 作用说明 |
| :--- | :--- | :--- |
| **操作系统** | Windows 10 / 11 `x86_64` | 宿主编译与运行平台 |
| **C++ 编译器** | Visual Studio 2022 / Build Tools | 提供 MSVC `cl.exe` 与 `link.exe`（需勾选 "使用 C++ 的桌面开发"） |
| **TypePHP SDK** | `tpc_v1096_windows_x86_64` | 包含 `tpc.exe`、`phpx` 与 PHP 8.5 Embed 静态库 |
| **PHP / Composer** | PHP 8.0+ / Composer 2.x | 负责本地包管理与补丁管理（运行时完全不需要） |

### 2. 基础安装与环境配置步骤

1. **安装 Visual Studio 2022**：
   * 社区版免费下载地址：[Visual Studio Community 2022](https://visualstudio.microsoft.com/zh-hans/vs/community/)
   * 安装时务必勾选 **“使用 C++ 的桌面开发”**。

2. **下载 TypePHP 工具包**：
   * 从 GitHub Releases（[https://github.com/swoole/typephp/releases](https://github.com/swoole/typephp/releases)）下载 TypePHP Windows 包。
   * 解压到本地目录，例如：`D:\workspace\tpc_v1096_windows_x86_64`。

3. **配置系统环境变量**：
   * `PHP_HOME` = `D:\workspace\tpc_v1096_windows_x86_64`
   * `PHPX_HOME` = `D:\workspace\tpc_v1096_windows_x86_64\phpx`
   * `Path` 追加：`D:\workspace\tpc_v1096_windows_x86_64`

4. **编译验证官方示例**：
   * 打开 **"x64 Native Tools Command Prompt for VS 2022"**（必须使用该终端，普通 CMD 找不到 MSVC 工具链；本项目中的 `build_env.bat` 已内置自动加载逻辑）。
   * 编译运行官方 Hello 示例：
     ```powershell
     cd D:\workspace\tpc_v1096_windows_x86_64
     tpc.exe examples\hello.php
     hello.exe
     ```

### 3. 核心性能配置（极为关键 ⚡）

> [!IMPORTANT]
> **必须配置 Windows Defender 排除项**：
> Windows 实时杀毒（`MsMpEng.exe`）会在编译时拦截扫描几百个临时 `.cc` 和 `.obj` 文件，导致编译时间翻倍甚至卡死。

1. 打开 **Windows 设置** ➔ **隐私和安全性** ➔ **Windows 安全中心** ➔ **病毒和威胁防护**。
2. 点击 **“‘病毒和威胁防护’设置”** 下方的 **管理设置**。
3. 找到 **“排除项”**，点击 **添加或删除排除项**，添加以下文件夹：
   * 📁 `D:\dnmp\www\TypePHP`（项目所在目录）
   * 📁 `D:\workspace\tpc_v1096_windows_x86_64`（TPC SDK 目录）

## 二、 编译指南

### 1. 编译配置文件 [`project.yml`](file:///D:/dnmp/www/TypePHP/typephp-thinkphp-v2/project.yml)

```yaml
name: typephp-think

sources:
  - main.php
  - vendor/psr
  - vendor/league/mime-type-detection/src
  - vendor/league/flysystem/src
  - vendor/league/flysystem-local
  - vendor/symfony/polyfill-mbstring
  - vendor/symfony/var-dumper
  - vendor/topthink/framework/src
  - vendor/topthink/think-orm/src
  - vendor/topthink/think-helper/src
  - vendor/topthink/think-container/src
  - vendor/topthink/think-validate/src
  - vendor/topthink/think-trace/src
  - vendor/topthink/think-dumper/src
  - vendor/topthink/think-filesystem/src
  - app

ignore:
  - app/provider.php
  - app/middleware.php
  - app/service.php
  - app/event.php
  - config
  - route
  - extend
  - view
  # 语法不兼容或运行时动态加载的文件列表...

output: build/myapp
mode: bin
optimize: 2        # 0: 开发快速编译 | 2: 生产极致优化 (/O2)
job: 16            # 根据 CPU 核心数拉大并发编译进程
debug: false
cxx-flags:
  - /bigobj
  - /MP            # 开启 MSVC 多处理器编译
```

### 2. 编译脚本 [`build_env.bat`](file:///D:/dnmp/www/TypePHP/typephp-thinkphp-v2/build_env.bat)

```bat
@echo off

rem 1. Set environment variables (use system PHP_HOME if defined)
if "%PHP_HOME%"=="" set PHP_HOME=D:\workspace\tpc_v1096_windows_x86_64
set PHPX_HOME=%PHP_HOME%\phpx
set PATH=%PHP_HOME%;%PATH%

rem 2. Initialize MSVC compiler environment
where cl.exe >nul 2>&1
if errorlevel 1 (
    if exist "D:\Program Files\Microsoft Visual Studio\18\Community\VC\Auxiliary\Build\vcvars64.bat" (
        call "D:\Program Files\Microsoft Visual Studio\18\Community\VC\Auxiliary\Build\vcvars64.bat" >nul
    ) else if exist "C:\Program Files\Microsoft Visual Studio\2022\Community\VC\Auxiliary\Build\vcvars64.bat" (
        call "C:\Program Files\Microsoft Visual Studio\2022\Community\VC\Auxiliary\Build\vcvars64.bat" >nul
    ) else if exist "C:\Program Files (x86)\Microsoft Visual Studio\2022\BuildTools\VC\Auxiliary\Build\vcvars64.bat" (
        call "C:\Program Files (x86)\Microsoft Visual Studio\2022\BuildTools\VC\Auxiliary\Build\vcvars64.bat" >nul
    ) else if exist "D:\Program Files (x86)\Microsoft Visual Studio\2022\BuildTools\VC\Auxiliary\Build\vcvars64.bat" (
        call "D:\Program Files (x86)\Microsoft Visual Studio\2022\BuildTools\VC\Auxiliary\Build\vcvars64.bat" >nul
    ) else if defined VS_VCVARS64 (
        call "%VS_VCVARS64%" >nul
    )
)

rem 3. Ensure build directory and sync php.ini
if not exist "%~dp0build" mkdir "%~dp0build"
if exist "%~dp0php.ini" copy /y "%~dp0php.ini" "%~dp0build\php.ini" >nul

rem 4. Run TPC compiler
cd /d %PHP_HOME%
tpc.exe "%~dp0project.yml" > "%~dp0build_log.txt" 2>&1
```

### 3. 执行编译

在项目根目录下打开终端：

```powershell
.\build_env.bat
```

| 编译类型 | 耗时 | 说明 |
| :--- | :--- | :--- |
| **首次全量编译** | 约 1 ~ 3 分钟 | 转译并编译 390 个源文件，生成全局 `.obj` 缓存 |
| **日常增量编译** | 约 2 ~ 5 秒 | 修改代码后只重编变动文件，秒级链接生成 `myapp.exe` |

## 三、 运行与验证

### 1. 启动脚本 [`run_env.bat`](file:///D:/dnmp/www/TypePHP/typephp-thinkphp-v2/run_env.bat)

```bat
@echo off
setlocal
if "%PHP_HOME%"=="" set "PHP_HOME=D:\workspace\tpc_v1096_windows_x86_64"
set "PATH=%PHP_HOME%;%PATH%"

cd /d %~dp0
.\build\myapp.exe %*
```

### 2. 环境自检运行

```powershell
.\run_env.bat info
```

> **预期输出**：
> ```text
> PHP_VERSION: 8.5.10
> PHP_SAPI: embed
> php_ini_loaded_file: D:\...\php.ini
> extension_loaded(pdo): yes
> extension_loaded(pdo_mysql): yes
> HTTP STATUS: 200
> ```

### 3. 启动 ThinkPHP Web 服务

```powershell
# 1. 默认端口启动 (127.0.0.1:8000)
.\run_env.bat run

# 2. 指定端口启动 (如 8788)
.\run_env.bat run -p 8788

# 3. 局域网外部访问 (监听 0.0.0.0)
.\run_env.bat run -H 0.0.0.0 -p 8788
```

### 4. 路由与界面访问测试

| 路由地址 | 请求方式 | 页面效果 / 功能说明 |
| :--- | :--- | :--- |
| `http://localhost:8788/` | `GET` | **展示全新「开源技术小栈 × TypePHP」纯白宽屏首页** |
| `http://localhost:8788/hello/world` | `GET` | **测试控制器路由与动态传参返回** |

## 四、 常见问题与排查解决方案

### 🔴 问题 1：`Failed opening required '.../vendor/autoload.php'`

* **📌 故障现象**：移动项目目录或换机器运行 `myapp.exe` 时，报找不到旧路径下的 `autoload.php`。
* **🔍 根本原因**：TPC 在 AOT 编译阶段将 PHP 的 `__DIR__` 静态固化为编译期的绝对路径常量。
* ** 解决方案**：在 [`main.php`](file:///D:/dnmp/www/TypePHP/typephp-thinkphp-v2/main.php#L6-L10) 中优先使用 `getcwd()` 动态寻找：
  ```php
  function main(): void
  {
      $autoload = getcwd() . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
      if (!file_exists($autoload)) {
          $autoload = __DIR__ . '/vendor/autoload.php';
      }
      require $autoload;
      ...
  ```

### 🔴 问题 2：`LINK : fatal error LNK1104: 无法打开文件 “.../build\myapp.exe.rsp”`

* **📌 故障现象**：全新克隆代码后直接编译，链接器报错退出。
* **🔍 根本原因**：Git 默认不提交空目录，缺少目标 `build/` 文件夹导致链接器无法写入临时参数文件。
* ** 解决方案**：
  1. 在 [`build_env.bat`](file:///D:/dnmp/www/TypePHP/typephp-thinkphp-v2/build_env.bat) 增加：`if not exist "%~dp0build" mkdir "%~dp0build"`。
  2. 在 [`.gitignore`](file:///D:/dnmp/www/TypePHP/typephp-thinkphp-v2/.gitignore) 中通过白名单保留 `build/.gitkeep` 与 `build/php.ini`。

### 🔴 问题 3：`php.ini` 扩展目录硬编码导致运行时扩展加载失败

* **📌 故障现象**：执行 `myapp.exe` 时无法连接 MySQL，`extension_loaded('pdo_mysql')` 返回 `no`。
* **🔍 根本原因**：`php.ini` 中的 `extension_dir` 写死了固定路径。
* ** 解决方案**：
  1. [`php.ini`](file:///D:/dnmp/www/TypePHP/typephp-thinkphp-v2/php.ini#L3) 改用 PHP 原生环境变量语法：`extension_dir="${PHP_HOME}/ext"`。
  2. [`run_env.bat`](file:///D:/dnmp/www/TypePHP/typephp-thinkphp-v2/run_env.bat#L3) 中统一注入环境变量：`if "%PHP_HOME%"=="" set "PHP_HOME=D:\workspace\tpc_v1096_windows_x86_64"`。
  3. [`build_env.bat`](file:///D:/dnmp/www/TypePHP/typephp-thinkphp-v2/build_env.bat) 自动同步最新的 `php.ini` 到 `build/` 供独立打包分发。

### 🔴 问题 4：`TypeError: runBuiltinServer(): Argument #2 ($port) must be of type int, string given`

* **📌 故障现象**：运行 `php think run -p 8788` 时抛出强类型错误。
* **🔍 根本原因**：命令行参数获取到的端口为 `string`，而 `RunServer.php` 的内部方法要求 `int $port`。
* ** 解决方案**：在 [`RunServer.php`](file:///D:/dnmp/www/TypePHP/typephp-thinkphp-v2/vendor/topthink/framework/src/think/console/command/RunServer.php#L42-L44) 显式强制类型转换：
  ```php
  $host = (string) $input->getOption('host');
  $port = (int) $input->getOption('port');
  ```

### 🔴 问题 5：`The process cannot access the file because it is being used by another process`

* **📌 故障现象**：重新编译时报错，提示文件被占用。
* **🔍 根本原因**：
  1. 上一次运行的 `myapp.exe` 仍在后台驻留或监听端口；
  2. 或者上一次启动的 `build_env.bat` 仍在后台运行，占用了 `build_log.txt`。
* ** 解决方案**：在 PowerShell 中执行强制终止并等待后台任务结束：
  ```powershell
  # 结束运行中的服务进程
  Get-Process myapp, tpc, cl, link -ErrorAction SilentlyContinue | Stop-Process -Force
  
  # 重新执行编译
  .\build_env.bat
  ```

### 💡 编译性能优化综合技巧

```yaml
# 1. 安全中心排除：加入 Windows Defender 白名单 (提速 2~3 倍)
# 2. 调大并行线程：project.yml 中设置 job: 16 或根据 CPU 线程数配置
# 3. 降低优化级别：日常开发测试将 optimize: 2 改为 optimize: 0 (编译耗时减少 70%)
# 4. 开启多处理器：cxx-flags 中增加 - /MP
```

<div align="center">

**欢迎关注微信公众号「开源技术小栈」** · 探索 PHP 原生编译的极致性能与现代云原生实践

📖 **相关专栏教程**：[《TypePHP Windows 环境搭建与深度编译实战》](https://mp.weixin.qq.com/s/QTaIYHn9mF-HqyeCJP4JLA)

</div>
