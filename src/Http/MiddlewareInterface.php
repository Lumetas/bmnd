<?php
namespace BMND\Http;

interface MiddlewareInterface
{
    public function process(RequestInterface $request, RequestHandlerInterface $handler): ResponseInterface;
}

