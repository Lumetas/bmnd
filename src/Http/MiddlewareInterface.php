<?php
namespace BMND\Http;

use Psr\Http\Message\ServerRequestInterface;

interface MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface;
}

