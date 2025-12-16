<?php
namespace BMND\Http;

class Request implements RequestInterface
{
    private string $method;
    private string $uri;
    private array $queryParams;
    private array $parsedBody;
    private array $serverParams;
    private array $cookieParams;
    private array $uploadedFiles;
    private array $attributes;

    public function __construct(
        string $method = '',
        string $uri = '',
        array $queryParams = [],
        array $parsedBody = [],
        array $serverParams = [],
        array $cookieParams = [],
        array $uploadedFiles = [],
        array $attributes = []
    ) {
        $this->method = $method ?: $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->uri = $uri ?: $_SERVER['REQUEST_URI'] ?? '/';
        $this->queryParams = $queryParams ?: $_GET;
        $this->parsedBody = $parsedBody ?: $_POST;
        $this->serverParams = $serverParams ?: $_SERVER;
        $this->cookieParams = $cookieParams ?: $_COOKIE;
        $this->uploadedFiles = $uploadedFiles ?: $_FILES;
        $this->attributes = $attributes;
    }

    public static function createFromGlobals(): self
    {
        return new self();
    }

    public function getMethod(): string
    {
        return strtoupper($this->method);
    }

    public function withMethod(string $method): self
    {
        $new = clone $this;
        $new->method = strtoupper($method);
        return $new;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function withUri(string $uri, bool $preserveHost = false): self
    {
        $new = clone $this;
        $new->uri = $uri;
        return $new;
    }

    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    public function withQueryParams(array $query): self
    {
        $new = clone $this;
        $new->queryParams = $query;
        return $new;
    }

    public function getParsedBody(): array
    {
        return $this->parsedBody;
    }

    public function withParsedBody(array $data): self
    {
        $new = clone $this;
        $new->parsedBody = $data;
        return $new;
    }

    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }

    public function withCookieParams(array $cookies): self
    {
        $new = clone $this;
        $new->cookieParams = $cookies;
        return $new;
    }

    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    public function withUploadedFiles(array $uploadedFiles): self
    {
        $new = clone $this;
        $new->uploadedFiles = $uploadedFiles;
        return $new;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getAttribute(string $name, $default = null)
    {
        return $this->attributes[$name] ?? $default;
    }

    public function withAttribute(string $name, $value): self
    {
        $new = clone $this;
        $new->attributes[$name] = $value;
        return $new;
    }

    public function withoutAttribute(string $name): self
    {
        $new = clone $this;
        unset($new->attributes[$name]);
        return $new;
    }
}
