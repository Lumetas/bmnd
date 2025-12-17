<?php

namespace BMND\Http;

use Psr\Http\Message\StreamInterface;
use InvalidArgumentException;

class Response implements ResponseInterface
{
    private int $statusCode = 200;
    private string $reasonPhrase = '';
    private string $protocolVersion = '1.1';
    private array $headers = [];
    private StreamInterface $body;

    private const STANDARD_PHRASES = [
        // Informational 1xx
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        103 => 'Early Hints',
        
        // Successful 2xx
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        208 => 'Already Reported',
        226 => 'IM Used',
        
        // Redirection 3xx
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        
        // Client Errors 4xx
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Payload Too Large',
        414 => 'URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        421 => 'Misdirected Request',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Too Early',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',
        
        // Server Errors 5xx
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
    ];

    public function __construct(
        $body = '',
        int $statusCode = 200,
        array $headers = [],
        string $protocolVersion = '1.1'
    ) {
        $this->validateStatusCode($statusCode);
        
        $this->statusCode = $statusCode;
        $this->reasonPhrase = self::STANDARD_PHRASES[$statusCode] ?? '';
        $this->protocolVersion = $this->validateProtocolVersion($protocolVersion);
        
        // Инициализация заголовков (нижний регистр для ключей)
        foreach ($headers as $name => $value) {
            $this->headers[$this->normalizeHeaderName($name)] = 
                is_array($value) ? $value : [$value];
        }
        
        // Инициализация тела
        $this->body = $this->createBody($body);
    }

    /**
     * Создает тело ответа из различных типов данных
     */
    private function createBody($body): StreamInterface
    {
        if ($body instanceof StreamInterface) {
            return $body;
        }
        
        if (is_string($body)) {
			return new Stream($body);
        }
        
        if (is_array($body) || is_object($body)) {
            $stream = new Stream(fopen('php://temp', 'r+'));
            $stream->write(json_encode($body, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $stream->rewind();
            
            // Автоматически добавляем Content-Type для JSON
            if (!$this->hasHeader('Content-Type')) {
                $this->headers['content-type'] = ['application/json; charset=utf-8'];
            }
            
            return $stream;
        }
        
        if (is_resource($body)) {
            return new Stream($body);
        }
        
        // Пустое тело по умолчанию
        return new Stream(fopen('php://temp', 'r+'));
    }

    /**
     * Валидация кода статуса
     */
    private function validateStatusCode(int $code): void
    {
        if ($code < 100 || $code > 599) {
            throw new InvalidArgumentException(
                'Status code must be between 100 and 599'
            );
        }
    }

    /**
     * Валидация версии протокола
     */
    private function validateProtocolVersion(string $version): string
    {
        $valid = ['1.0', '1.1', '2.0', '2'];
        if (!in_array($version, $valid, true)) {
            throw new InvalidArgumentException(
                'Protocol version must be one of: ' . implode(', ', $valid)
            );
        }
        return $version;
    }

    /**
     * Нормализация имени заголовка (нижний регистр)
     */
    private function normalizeHeaderName(string $name): string
    {
        return strtolower($name);
    }

    /* ========== PSR-7 Methods ========== */

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function withStatus(int $code, string $reasonPhrase = ''): self
    {
        $this->validateStatusCode($code);
        
        if ($this->statusCode === $code && $this->reasonPhrase === $reasonPhrase) {
            return $this;
        }
        
        $new = clone $this;
        $new->statusCode = $code;
        $new->reasonPhrase = $reasonPhrase ?: (self::STANDARD_PHRASES[$code] ?? '');
        
        return $new;
    }

    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }

    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    public function withProtocolVersion(string $version): self
    {
        $version = $this->validateProtocolVersion($version);
        
        if ($this->protocolVersion === $version) {
            return $this;
        }
        
        $new = clone $this;
        $new->protocolVersion = $version;
        return $new;
    }

    public function getHeaders(): array
    {
        $headers = [];
        foreach ($this->headers as $name => $values) {
            $headers[$name] = $values;
        }
        return $headers;
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
        
        if ($this->hasHeader($name) && $this->getHeader($name) === $value) {
            return $this;
        }
        
        $new = clone $this;
        $new->headers[$name] = $value;
        return $new;
    }

    public function withAddedHeader(string $name, $value): self
    {
        $name = $this->normalizeHeaderName($name);
        $value = is_array($value) ? $value : [$value];
        $current = $this->getHeader($name);
        
        $newValues = array_merge($current, $value);
        
        return $this->withHeader($name, $newValues);
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

    /* ========== Convenience Methods ========== */

    /**
     * Удобный метод для создания JSON ответа
     */
    public function withJson($data, int $statusCode = 200, int $options = JSON_UNESCAPED_UNICODE): self
    {
        $stream = new Stream(fopen('php://temp', 'r+'));
        $stream->write(json_encode($data, $options));
        $stream->rewind();
        
        return $this
            ->withStatus($statusCode)
            ->withBody($stream)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Удобный метод для создания HTML ответа
     */
    public function withHtml(string $html, int $statusCode = 200): self
    {
        $stream = new Stream(fopen('php://temp', 'r+'));
        $stream->write($html);
        $stream->rewind();
        
        return $this
            ->withStatus($statusCode)
            ->withBody($stream)
            ->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Удобный метод для создания текстового ответа
     */
    public function withText(string $text, int $statusCode = 200): self
    {
        $stream = new Stream(fopen('php://temp', 'r+'));
        $stream->write($text);
        $stream->rewind();
        
        return $this
            ->withStatus($statusCode)
            ->withBody($stream)
            ->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }

    /**
     * Удобный метод для редиректа
     */
    public function withRedirect(string $url, int $statusCode = 302): self
    {
        if ($statusCode < 300 || $statusCode > 308) {
            throw new InvalidArgumentException('Invalid redirect status code');
        }
        
        return $this
            ->withStatus($statusCode)
            ->withHeader('Location', $url);
    }

    /**
     * Устанавливает заголовки CORS
     */
    public function withCors(
        string $origin = '*',
        string $methods = 'GET, POST, PUT, DELETE, OPTIONS',
        string $headers = 'Content-Type, Authorization',
        string $credentials = 'true',
        int $maxAge = 86400
    ): self {
        return $this
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Access-Control-Allow-Methods', $methods)
            ->withHeader('Access-Control-Allow-Headers', $headers)
            ->withHeader('Access-Control-Allow-Credentials', $credentials)
            ->withHeader('Access-Control-Max-Age', (string) $maxAge);
    }

    /**
     * Отправляет ответ клиенту
     */
    public function send(): void
    {
        // Проверяем, не отправлены ли уже заголовки
        if (headers_sent()) {
            return;
        }
        
        // Отправляем статус
        $statusHeader = sprintf(
            'HTTP/%s %d %s',
            $this->protocolVersion,
            $this->statusCode,
            $this->reasonPhrase
        );
        header($statusHeader, true, $this->statusCode);
        
        // Отправляем заголовки
        foreach ($this->headers as $name => $values) {
            // Пропускаем заголовки, которые уже были отправлены
            $name = ucwords(str_replace('-', ' ', $name));
            $name = str_replace(' ', '-', $name);
            
            $replace = ($name === 'Content-Type' || $name === 'Content-Length');
            
            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, $value), $replace);
                $replace = false;
            }
        }
        
        // Отправляем тело
        if ($this->body->isSeekable()) {
            $this->body->rewind();
        }
        
        // Отправляем по частям для больших тел
        while (!$this->body->eof()) {
            echo $this->body->read(8192);
        }
    }

    /**
     * Магический метод для преобразования в строку
     */
    public function __toString(): string
    {
        $output = sprintf(
            "HTTP/%s %d %s\r\n",
            $this->protocolVersion,
            $this->statusCode,
            $this->reasonPhrase
        );
        
        foreach ($this->headers as $name => $values) {
            $normalizedName = ucwords(str_replace('-', ' ', $name));
            $normalizedName = str_replace(' ', '-', $normalizedName);
            
            foreach ($values as $value) {
                $output .= sprintf("%s: %s\r\n", $normalizedName, $value);
            }
        }
        
        $output .= "\r\n";
        $output .= (string) $this->body;
        
        return $output;
    }
}
