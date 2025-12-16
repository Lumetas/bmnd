<?php
namespace BMND\Router;

use BMND\DI;
use BMND\Http\RequestInterface;
use BMND\Http\ResponseInterface;
use BMND\Http\RequestHandlerInterface;

class RouteHandler implements RequestHandlerInterface
{
    private array $handler;
    private array $params;

    public function __construct(array $handler, array $params = [])
    {
        $this->handler = $handler;
        $this->params = $params;
    }

    public function handle(RequestInterface $request): ResponseInterface
    {
        [$class, $method] = $this->handler;
        $controller = DI::make($class);
        
        $result = DI::call([$controller, $method], array_merge(['request' => $request], $this->params));
        
        if ($result instanceof ResponseInterface) {
            return $result;
        }
        
        // Дефолтное преобразование
        $response = new \BMND\Http\Response();
        return $response->withBody((string)$result);
    }
}
