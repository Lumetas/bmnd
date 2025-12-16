<?php
namespace BMND\Http;

class Response implements ResponseInterface
{
    private int $statusCode = 200;
    private string $reasonPhrase = '';
    private string $protocolVersion = '1.1';
    private array $headers = [];
    private mixed $body = '';

    private static array $phrases = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
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
        422 => 'Unprocessable Entity',
        425 => 'Too Early',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        511 => 'Network Authentication Required',
    ];

    public function __construct(mixed $body = '', int $statusCode = 200, array $headers = [])
    {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
		$this->body = $body;
        $this->reasonPhrase = self::$phrases[$statusCode] ?? '';
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function withStatus(int $code, string $reasonPhrase = ''): self
    {
        $new = clone $this;
        $new->statusCode = $code;
        $new->reasonPhrase = $reasonPhrase ?: (self::$phrases[$code] ?? '');
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
        return isset($this->headers[strtolower($name)]);
    }

    public function getHeader(string $name): array
    {
        $name = strtolower($name);
        return $this->headers[$name] ?? [];
    }

    public function getHeaderLine(string $name): string
    {
        $header = $this->getHeader($name);
        return implode(', ', $header);
    }

    public function withHeader(string $name, $value): self
    {
        $new = clone $this;
        $name = strtolower($name);
        $new->headers[$name] = is_array($value) ? $value : [$value];
        return $new;
    }

    public function withAddedHeader(string $name, $value): self
    {
        $new = clone $this;
        $name = strtolower($name);
        $current = $new->getHeader($name);
        
        if (is_array($value)) {
            $new->headers[$name] = array_merge($current, $value);
        } else {
            $new->headers[$name][] = $value;
        }
        
        return $new;
    }

    public function withoutHeader(string $name): self
    {
        $new = clone $this;
        $name = strtolower($name);
        unset($new->headers[$name]);
        return $new;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function withBody(string $body): self
    {
        $new = clone $this;
        $new->body = $body;
        return $new;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            header(sprintf(
                'HTTP/%s %s %s',
                $this->protocolVersion,
                $this->statusCode,
                $this->reasonPhrase
            ));

            foreach ($this->headers as $name => $values) {
                foreach ($values as $value) {
                    header(sprintf('%s: %s', ucfirst($name), $value), false);
                }
            }
        }

		if (is_string($this->body)) {
			echo $this->body;
		} else if (is_array($this->body)) {
			header('Content-Type: application/json');
			echo json_encode($this->body);
		} else if (is_object($this->body)) {
			header('Content-Type: application/json');
			echo json_encode($this->body);
		} else { 
			echo $this->body;
		}
    }
}
