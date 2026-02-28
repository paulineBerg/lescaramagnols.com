<?php
declare(strict_types=1);

namespace Caramagnols\Http;

use Closure;

/**
 * Pipeline minimaliste de middlewares (callable(Request, callable): Response).
 */
class MiddlewareStack
{
    /** @var array<int, callable(Request, callable): Response> */
    private array $stack = [];

    public function add(callable $middleware): void
    {
        $this->stack[] = $middleware;
    }

    /**
     * Exécute la pile puis le handler final.
     */
    public function handle(Request $request, callable $handler): Response
    {
        $pipeline = array_reduce(
            array_reverse($this->stack),
            function ($next, $middleware) {
                return function (Request $req) use ($middleware, $next) {
                    return $middleware($req, $next);
                };
            },
            $handler
        );

        return $pipeline($request);
    }
}
