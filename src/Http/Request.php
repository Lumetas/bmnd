<?php

namespace BMND\Http;

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\ServerRequestInterface;
use InvalidArgumentException;

class Request implements ServerRequestInterface
{
    private string $method;
    private UriInterface $uri;
    private array $queryParams;
    private $parsedBody;
    private array $serverParams;
    private array $cookieParams;
    private array $uploadedFiles;
    private array $attributes;
    private array $headers;
    private StreamInterface $body;
    private string $requestTarget = '';
    private string $protocolVersion = '1.1';

    public function __construct(
        string $method = '',
        string|UriInterface $uri = '',
        array $queryParams = [],
        $parsedBody = null,
        array $serverParams = [],
        array $cookieParams = [],
        array $uploadedFiles = [],
        array $attributes = [],
        array $headers = [],
        ?StreamInterface $body = null,
        string $protocolVersion = '1.1'
    ) {
        $this->method = $method ?: $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        if ($uri instanceof UriInterface) {
            $this->uri = $uri;
        } else {
            $uriString = $uri ?: $_SERVER['REQUEST_URI'] ?? '/';
            $this->uri = new Uri($uriString);
        }
        
        $this->queryParams = $queryParams ?: $_GET;
        $this->parsedBody = $parsedBody ?? $_POST;
        $this->serverParams = $serverParams ?: $_SERVER;
        $this->cookieParams = $cookieParams ?: $_COOKIE;
        $this->headers = $headers ?: $this->extractHeadersFromServer($this->serverParams);
        $this->uploadedFiles = $this->normalizeUploadedFiles($uploadedFiles ?: $_FILES);
        $this->body = $body ?: $this->createBodyFromInput();
        $this->attributes = $attributes;
        $this->protocolVersion = $protocolVersion;
        $this->requestTarget = $this->getRequestTarget();
    }

    public static function createFromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = Uri::fromGlobals();
        
        $body = new Stream(fopen('php://input', 'r'));
        
        // Определяем Content-Type
        $contentType = $_SERVER['CONTENT_TYPE'] ?? $headers['Content-Type'][0] ?? '';
        
        $parsedBody = null;
        if ($method !== 'GET' && $method !== 'HEAD') {
            if (strpos($contentType, 'application/json') !== false) {
                $input = file_get_contents('php://input');
                $parsedBody = json_decode($input, true) ?: null;
            } elseif (strpos($contentType, 'application/x-www-form-urlencoded') !== false ||
                      strpos($contentType, 'multipart/form-data') !== false) {
                $parsedBody = $_POST;
            }
        }

        return new self(
            method: $method,
            uri: $uri,
            queryParams: $_GET,
            parsedBody: $parsedBody,
            serverParams: $_SERVER,
            cookieParams: $_COOKIE,
            uploadedFiles: $_FILES,
            body: $body,
            protocolVersion: str_replace('HTTP/', '', $_SERVER['SERVER_PROTOCOL'] ?? '1.1')
        );
    }

    /* ========== MessageInterface Methods ========== */

    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    public function withProtocolVersion(string $version): self
    {
        $validVersions = ['1.0', '1.1', '2.0', '2'];
        if (!in_array($version, $validVersions, true)) {
            throw new InvalidArgumentException('Invalid HTTP version');
        }

        if ($this->protocolVersion === $version) {
            return $this;
        }

        $new = clone $this;
        $new->protocolVersion = $version;
        return $new;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headers[$this->normalizeHeaderName($name)]);
    }

    public function getHeader(string $name): array
    {
        $name = $this->normalizeHeaderName($name);
        return $this->headers[$name] ?? [];
    }

    public function getHeaderLine(string $name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    public function withHeader(string $name, $value): self
    {
        $name = $this->normalizeHeaderName($name);
        $value = is_array($value) ? $value : [$value];

        $new = clone $this;
        $new->headers[$name] = $value;
        return $new;
    }

    public function withAddedHeader(string $name, $value): self
    {
        $name = $this->normalizeHeaderName($name);
        $value = is_array($value) ? $value : [$value];

        if ($this->hasHeader($name)) {
            $current = $this->getHeader($name);
            $value = array_merge($current, $value);
        }

        return $this->withHeader($name, $value);
    }

    public function withoutHeader(string $name): self
    {
        $name = $this->normalizeHeaderName($name);

        if (!$this->hasHeader($name)) {
            return $this;
        }

        $new = clone $this;
        unset($new->headers[$name]);
        return $new;
    }

    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    public function withBody(StreamInterface $body): self
    {
        if ($this->body === $body) {
            return $this;
        }

        $new = clone $this;
        $new->body = $body;
        return $new;
    }


    public function getRequestTarget(): string
    {
        if ($this->requestTarget !== null) {
            return $this->requestTarget;
        }

        $target = $this->uri->getPath();
        if ($target === '') {
            $target = '/';
        }

        if ($this->uri->getQuery() !== '') {
            $target .= '?' . $this->uri->getQuery();
        }

        return $target;
    }

    public function withRequestTarget(string $requestTarget): self
    {
        if (preg_match('/\s/', $requestTarget)) {
            throw new InvalidArgumentException('Request target cannot contain whitespace');
        }

        $new = clone $this;
        $new->requestTarget = $requestTarget;
        return $new;
    }

    public function getMethod(): string
    {
        return strtoupper($this->method);
    }

    public function withMethod(string $method): self
    {
        $method = strtoupper($method);
        
        if ($this->method === $method) {
            return $this;
        }

        $new = clone $this;
        $new->method = $method;
        return $new;
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    public function withUri(UriInterface $uri, bool $preserveHost = false): self
    {
        if ($this->uri === $uri) {
            return $this;
        }

        $new = clone $this;
        $new->uri = $uri;

        // Если не сохранять хост и у новой URI есть хост - обновляем заголовок Host
        if (!$preserveHost && $uri->getHost() !== '') {
            $new = $new->withHeader('Host', $uri->getHost());
        } elseif ($preserveHost && $this->hasHeader('Host')) {
            // Если сохраняем хост и у текущего запроса есть заголовок Host - не меняем
            return $new;
        } elseif ($uri->getHost() !== '') {
            // Если у новой URI есть хост, но нет заголовка Host - добавляем
            $new = $new->withHeader('Host', $uri->getHost());
        }

        return $new;
    }

    /* ========== ServerRequestInterface Methods ========== */

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

    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    public function withUploadedFiles(array $uploadedFiles): self
    {
        $new = clone $this;
        $new->uploadedFiles = $this->normalizeUploadedFiles($uploadedFiles);
        return $new;
    }

    public function getParsedBody()
    {
        return $this->parsedBody;
    }

    public function withParsedBody($data): self
    {
        if (!is_array($data) && !is_object($data) && $data !== null) {
            throw new InvalidArgumentException('Parsed body must be array, object, or null');
        }

        $new = clone $this;
        $new->parsedBody = $data;
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
        if (!isset($this->attributes[$name])) {
            return $this;
        }

        $new = clone $this;
        unset($new->attributes[$name]);
        return $new;
    }

    private function extractHeadersFromServer(array $server): array
    {
        $headers = [];

        foreach ($server as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $name = str_replace('_', '-', substr($key, 5));
                $headers[$name] = [$value];
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                $name = str_replace('_', '-', $key);
                $headers[$name] = [$value];
            } elseif ($key === 'PHP_AUTH_USER' && isset($server['PHP_AUTH_PW'])) {
                $headers['Authorization'] = ['Basic ' . base64_encode($value . ':' . $server['PHP_AUTH_PW'])];
            }
        }

        return $headers;
    }

    private function normalizeHeaderName(string $name): string
    {
        return str_replace(' ', '-', ucwords(str_replace('-', ' ', strtolower($name))));
    }

    private function createBodyFromInput(): StreamInterface
    {
        $resource = fopen('php://temp', 'r+');
        $input = file_get_contents('php://input');
        
        if ($input !== false && $input !== '') {
            fwrite($resource, $input);
            rewind($resource);
        }
        
        return new Stream($resource);
    }

    private function normalizeUploadedFiles(array $files): array
    {
        $normalized = [];
        
        foreach ($files as $key => $file) {
            if (is_array($file['tmp_name'] ?? null)) {
                // Multiple files
                foreach ($file['tmp_name'] as $index => $tmpName) {
                    $normalized[$key][$index] = new UploadedFile(
                        $tmpName,
                        $file['size'][$index] ?? 0,
                        $file['error'][$index] ?? UPLOAD_ERR_OK,
                        $file['name'][$index] ?? '',
                        $file['type'][$index] ?? ''
                    );
                }
            } else {
                // Single file
                $normalized[$key] = new UploadedFile(
                    $file['tmp_name'] ?? '',
                    $file['size'] ?? 0,
                    $file['error'] ?? UPLOAD_ERR_OK,
                    $file['name'] ?? '',
                    $file['type'] ?? ''
                );
            }
        }
        
        return $normalized;
    }
}
