<?php

namespace app\controller;

use app\BaseController;
use app\model\Musics;

class Index extends BaseController
{
    public function index()
    {
        $phpVersion = PHP_VERSION;
        $phpSapi = PHP_SAPI;

        return <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>开源技术小栈 · TypePHP</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --ink: #111827;
            --sub: #6b7280;
            --faint: #9ca3af;
            --line: #e5e7eb;
            --soft: #f9fafb;
            --accent: #2563eb;
            --green: #16a34a;
        }
        html, body { height: 100%; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "PingFang SC", "Hiragino Sans GB", "Microsoft YaHei", sans-serif;
            -webkit-font-smoothing: antialiased;
            background: #ffffff;
            color: var(--ink);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        ::selection { background: #dbeafe; }
        a:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; border-radius: 4px; }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }

        .header { flex-shrink: 0; border-bottom: 1px solid var(--line); }
        .header-inner {
            max-width: 1120px; margin: 0 auto; height: 64px; padding: 0 32px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .brand { display: flex; align-items: center; gap: 10px; text-decoration: none; color: var(--ink); }
        .brand-logo {
            width: 28px; height: 28px; border-radius: 7px;
            background: var(--ink); color: #ffffff;
            display: flex; align-items: center; justify-content: center;
        }
        .brand-logo svg { width: 14px; height: 14px; }
        .brand-name { font-size: 15px; font-weight: 600; letter-spacing: -0.01em; }
        .brand-name em { font-style: normal; color: var(--faint); font-weight: 500; }
        .header-right { display: flex; align-items: center; gap: 8px; }
        .pill {
            display: inline-flex; align-items: center; gap: 7px;
            height: 28px; padding: 0 12px;
            border: 1px solid var(--line); border-radius: 999px;
            font-size: 12px; color: var(--sub); background: #ffffff;
        }
        .pill .dot { width: 6px; height: 6px; border-radius: 50%; background: var(--green); }

        .main { flex: 1; display: flex; }
        .wrap { width: 100%; max-width: 1120px; margin: auto; padding: 88px 32px; }

        .eyebrow {
            font-size: 13px; font-weight: 600; letter-spacing: 0.14em;
            text-transform: uppercase; color: var(--accent); margin-bottom: 20px;
        }
        h1 {
            font-size: clamp(60px, 9vw, 108px);
            font-weight: 800; letter-spacing: -0.045em; line-height: 1;
            margin-bottom: 22px;
        }
        h1 .accent { color: var(--accent); }
        .tagline { font-size: 22px; font-weight: 600; letter-spacing: -0.01em; margin-bottom: 14px; }
        .lede { font-size: 16px; color: var(--sub); line-height: 1.8; max-width: 640px; margin-bottom: 38px; }
        .actions { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 76px; }
        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            height: 48px; padding: 0 26px; border-radius: 10px;
            font-size: 15px; font-weight: 600; text-decoration: none;
            transition: background 0.15s ease, border-color 0.15s ease;
        }
        .btn-dark { background: var(--ink); color: #ffffff; }
        .btn-dark:hover { background: #000000; }
        .btn-ghost { border: 1px solid var(--line); color: var(--ink); background: #ffffff; }
        .btn-ghost:hover { border-color: #d1d5db; background: var(--soft); }

        .stats {
            display: grid; grid-template-columns: repeat(4, 1fr);
            border: 1px solid var(--line); border-radius: 14px;
            overflow: hidden; margin-bottom: 72px; background: #ffffff;
        }
        .stat { padding: 24px; }
        .stat + .stat { border-left: 1px solid var(--line); }
        .stat-label {
            font-size: 12px; font-weight: 600; letter-spacing: 0.08em;
            text-transform: uppercase; color: var(--faint); margin-bottom: 8px;
        }
        .stat-value { font-size: 19px; font-weight: 700; color: var(--ink); font-variant-numeric: tabular-nums; }

        .section-title { font-size: 15px; font-weight: 700; margin-bottom: 24px; }
        .section-title span { color: var(--faint); font-weight: 400; font-size: 13px; margin-left: 6px; }
        .features { display: grid; grid-template-columns: repeat(3, 1fr); gap: 32px; }
        .feature { border-top: 1px solid var(--line); padding-top: 22px; }
        .feature-icon {
            width: 40px; height: 40px; border-radius: 9px;
            border: 1px solid var(--line); background: var(--soft);
            display: flex; align-items: center; justify-content: center;
            color: var(--ink); margin-bottom: 16px;
        }
        .feature-icon svg { width: 18px; height: 18px; }
        .feature h3 { font-size: 16px; font-weight: 600; margin-bottom: 8px; }
        .feature p { font-size: 14px; color: var(--sub); line-height: 1.8; }

        .footer { flex-shrink: 0; border-top: 1px solid var(--line); }
        .footer-inner {
            max-width: 1120px; margin: 0 auto; padding: 18px 32px;
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 8px;
            font-size: 13px; color: var(--faint);
        }
        .footer strong { color: var(--sub); font-weight: 600; }

        @media (max-width: 860px) {
            .header-inner { padding: 0 20px; }
            .wrap { padding: 48px 20px; margin: 0; }
            h1 { font-size: 52px; letter-spacing: -0.04em; }
            .tagline { font-size: 18px; }
            .actions { margin-bottom: 44px; }
            .stats { grid-template-columns: repeat(2, 1fr); margin-bottom: 44px; }
            .stat:nth-child(3) { border-left: none; }
            .stat:nth-child(n+3) { border-top: 1px solid var(--line); }
            .features { grid-template-columns: 1fr; gap: 20px; }
            .footer-inner { padding: 16px 20px; }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-inner">
            <a class="brand" href="/">
                <span class="brand-logo"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M13 2 3 14h7l-1 8 10-12h-7l1-8z"/></svg></span>
                <span class="brand-name">开源技术小栈 <em>/ TypePHP</em></span>
            </a>
            <div class="header-right">
                <span class="pill"><span class="dot"></span>AOT 引擎运行中</span>
                <span class="pill mono">v{$phpVersion}</span>
            </div>
        </div>
    </header>

    <main class="main">
        <div class="wrap">
            <div class="eyebrow">开源技术小栈 · AOT Native</div>
            <h1>TypePHP<span class="accent">.</span></h1>
            <p class="tagline">高性能、自包含的纯原生 PHP Web 服务</p>
            <p class="lede">基于 ThinkPHP 8.x 与 TypePHP AOT 编译技术打造，全量业务逻辑深度编译为原生机器码，零解释开销，毫秒级响应。</p>

            <div class="actions">
                <a href="/hello/world" class="btn btn-dark">测试 Hello 路由 →</a>
                <a href="/music" class="btn btn-ghost">查看 Music 接口</a>
            </div>

            <div class="stats">
                <div class="stat">
                    <div class="stat-label">编译模式 / Mode</div>
                    <div class="stat-value">AOT Native</div>
                </div>
                <div class="stat">
                    <div class="stat-label">SAPI 架构</div>
                    <div class="stat-value mono">{$phpSapi}</div>
                </div>
                <div class="stat">
                    <div class="stat-label">PHP 内核版本</div>
                    <div class="stat-value mono">v{$phpVersion}</div>
                </div>
                <div class="stat">
                    <div class="stat-label">底层框架</div>
                    <div class="stat-value">ThinkPHP 8.x</div>
                </div>
            </div>

            <div class="section-title">核心特性<span>/ Features</span></div>
            <div class="features">
                <div class="feature">
                    <div class="feature-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/></svg>
                    </div>
                    <h3>机器码级性能</h3>
                    <p>全量 PHP 业务逻辑深度编译为 C++ 原生机器码，消除 OPCODE 解析开销。</p>
                </div>
                <div class="feature">
                    <div class="feature-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/></svg>
                    </div>
                    <h3>工程安全与自包含</h3>
                    <p>源码免明文部署，二进制打包内嵌完整运行环境，无环境依赖负担。</p>
                </div>
                <div class="feature">
                    <div class="feature-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12l4 6-10 13L2 9z"/><path d="M11 3 8 9l4 13 4-13-3-6"/><path d="M2 9h20"/></svg>
                    </div>
                    <h3>全面兼容生态</h3>
                    <p>完美融合 ThinkPHP 容器注入、路由分发、ORM 数据模型与中间件机制。</p>
                </div>
            </div>
        </div>
    </main>

    <footer class="footer">
        <div class="footer-inner">
            <div>Powered by <strong>开源技术小栈</strong></div>
            <div>探索 PHP 原生编译的极致性能与现代云原生实践</div>
        </div>
    </footer>
</body>
</html>
HTML;
    }

    public function hello($name = 'ThinkPHP8')
    {
        return 'hello,' . $name;
    }

    public function music(){
        return Musics::count();
    }
}
