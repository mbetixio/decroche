<?php

namespace App;

use Mcp\Schema\Extension\Apps\UiResourceCsp;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel implements CompilerPassInterface
{
    use MicroKernelTrait;

    /**
     * The MCP Apps' content security policy names the server's own address  as
     * %env(DEFAULT_URI)%: the bundle copies (and escapes) attribute values as they are, so the variable is resolved here.
     */
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $definition) {
            $this->resolve($container, $definition);
        }
    }

    /**
     * Looks for the policies anywhere, the bundle nests them in the arguments of the apps' method calls.
     */
    private function resolve(ContainerBuilder $container, mixed $value): mixed
    {
        if (\is_array($value)) {
            return array_map(fn (mixed $v): mixed => $this->resolve($container, $v), $value);
        }
        if (!$value instanceof Definition) {
            return $value;
        }
        $csp = UiResourceCsp::class === $value->getClass();
        $value->setArguments(array_map(fn (mixed $argument): mixed => $csp && \is_array($argument)
            ? array_map(static fn (mixed $d): mixed => \is_string($d) && preg_match('/^%%?env\((.+)\)%%?$/', $d, $m) ? $container->resolveEnvPlaceholders('%env('.$m[1].')%') : $d, $argument)
            : $this->resolve($container, $argument), $value->getArguments()));
        $value->setMethodCalls(array_map(fn (array $call): array => [$call[0], $this->resolve($container, $call[1]), ...\array_slice($call, 2)], $value->getMethodCalls()));

        return $value;
    }

    /**
     * The application's time zone, whatever PHP's own setting on the machine (APP_TIMEZONE to change it).
     */
    public function boot(): void
    {
        date_default_timezone_set(\is_string($_SERVER['APP_TIMEZONE'] ?? null) && '' !== $_SERVER['APP_TIMEZONE'] ? $_SERVER['APP_TIMEZONE'] : 'Europe/Paris');
        parent::boot();
    }

    /**
     * @return list<string> An array of allowed values for APP_ENV
     */
    private function getAllowedEnvs(): array
    {
        return ['prod', 'dev', 'test'];
    }
}
