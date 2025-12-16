<?php
namespace BMND\Http;

interface RequestHandlerInterface
{
    public function handle(RequestInterface $request): ResponseInterface;
}
