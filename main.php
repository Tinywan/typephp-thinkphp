<?php
use think\App;

// 命令行入口文件
// 加载基础文件
require __DIR__ . '/vendor/autoload.php';

function main() :void
{    
    global $argv;
    // 应用初始化
    (new App())->console->run();
}
