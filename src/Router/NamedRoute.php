<?php

namespace BMND\Router;

class NamedRoute
{
    public function __construct(
        public readonly string $name,
        public readonly string $path,
        public readonly string $class,
        public readonly string $method,
        private readonly array $handler
    ) {}
    
    public function run(...$args): mixed
    {
        $class = $this->class;
        $method = $this->method;
        
        $controller = new $class();
        return $controller->$method(...$args);
    }
    
    public function getUrl(array $params = []): string
    {
        $path = $this->path;
        foreach ($params as $key => $value) {
            $path = str_replace('{' . $key . '}', $value, $path);
        }
        return $path;
    }
}
