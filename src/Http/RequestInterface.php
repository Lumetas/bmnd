<?php
namespace BMND\Http;

interface RequestInterface
{
    public function getMethod(): string;
    public function withMethod(string $method): self;
    public function getUri(): string;
    public function withUri(string $uri, bool $preserveHost = false): self;
    public function getQueryParams(): array;
    public function withQueryParams(array $query): self;
    public function getParsedBody(): array;
    public function withParsedBody(array $data): self;
    public function getServerParams(): array;
    public function getCookieParams(): array;
    public function withCookieParams(array $cookies): self;
    public function getUploadedFiles(): array;
    public function withUploadedFiles(array $uploadedFiles): self;
    public function getAttributes(): array;
    public function getAttribute(string $name, $default = null);
    public function withAttribute(string $name, $value): self;
    public function withoutAttribute(string $name): self;
}
