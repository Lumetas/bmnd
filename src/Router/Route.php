<?php
namespace BMND\Router;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class Route
{
    public function __construct(
        public string $path,
        public string $method = 'GET',
        public ?string $name = null,
        public mixed $middlewares = null
    ) {}
}
