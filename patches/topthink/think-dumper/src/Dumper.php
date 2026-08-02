<?php

namespace think\dumper;

use Exception;
use Symfony\Component\VarDumper\Caster\ReflectionCaster;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Dumper\ContextProvider\ContextProviderInterface;
use Symfony\Component\VarDumper\Dumper\ContextualizedDumper;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use think\App;
use think\Env;
use think\Request;

class Dumper
{
    public function __construct(protected App $app, protected Env $env)
    {
    }

    public function dump($var, ?string $label = null)
    {
        $format = $this->app->runningInConsole() ? 'cli' : 'html';

        $handler = $this->createHandler($format);

        return $handler($var, $label);
    }

    private function createHandler($format)
    {
        $cloner = new VarCloner();
        $cloner->addCasters(ReflectionCaster::UNSET_CLOSURE_FILE_INFO);

        if ('html' === $format) {
            $htmlDumper = new HtmlDumper();

            return function ($var, ?string $label = null) use ($cloner, $htmlDumper, $format) {
                $var = $cloner->cloneVar($var);

                if (null !== $label) {
                    $var = $var->withContext(['label' => $label]);
                }

                if ($this->env->has('DUMPER_TOKEN')) {
                    $srvDumper = new ServerDumper($htmlDumper, $this->getDefaultContextProviders($format));
                    $srvDumper->dump($var);
                } else {
                    $ctxDumper = new ContextualizedDumper($htmlDumper, [new SourceContextProvider()]);
                    $ctxDumper->dump($var);
                }
            };
        } elseif ('cli' === $format) {
            $cliDumper = new CliDumper();

            return function ($var, ?string $label = null) use ($cloner, $cliDumper, $format) {
                $var = $cloner->cloneVar($var);

                if (null !== $label) {
                    $var = $var->withContext(['label' => $label]);
                }

                if ($this->env->has('DUMPER_TOKEN')) {
                    $srvDumper = new ServerDumper($cliDumper, $this->getDefaultContextProviders($format));
                    $srvDumper->dump($var);
                } else {
                    $ctxDumper = new ContextualizedDumper($cliDumper, [new SourceContextProvider()]);
                    $ctxDumper->dump($var);
                }
            };
        }

        throw new Exception('Invalid dump format.');
    }

    private function getDefaultContextProviders($format): array
    {
        $contextProviders = [];

        switch ($format) {
            case 'html' :
                $request = $this->app->make('request');
                $contextProviders['request'] = new RequestContextProvider($request);
                break;
            case 'cli':
                $contextProviders['cli'] = new CliContextProvider();
                break;
        }

        $contextProviders['source'] = new SourceContextProvider();

        return $contextProviders;
    }
}
