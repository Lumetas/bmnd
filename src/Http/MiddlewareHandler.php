<?php
namespace BMND\Http;

use BMND\DI;
use Psr\Http\Message\ServerRequestInterface;

class MiddlewareHandler implements RequestHandlerInterface
{
    private array $middlewares;
    private RequestHandlerInterface $finalHandler;
    private int $index = 0;

    public function __construct(array $middlewares, RequestHandlerInterface $finalHandler)
    {
        $this->middlewares = $middlewares;
        $this->finalHandler = $finalHandler;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->index >= count($this->middlewares)) {
            return $this->finalHandler->handle($request);
        }

        $middleware = $this->middlewares[$this->index];
        $this->index++;

        if ($middleware instanceof MiddlewareInterface) {
            return $middleware->process($request, $this);
        }

        // Если middleware - строка, создаем через DI
        if (is_string($middleware) && class_exists($middleware)) {
            $middlewareInstance = DI::make($middleware);
            if ($middlewareInstance instanceof MiddlewareInterface) {
                return $middlewareInstance->process($request, $this);
            }
        }

        throw new \RuntimeException("Invalid middleware: " . (is_string($middleware) ? $middleware : gettype($middleware)));
    }
}
