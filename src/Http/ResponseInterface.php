<?php
namespace BMND\Http;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\StreamInterface;

interface ResponseInterface extends PsrResponseInterface
{
    public function getStatusCode(): int;
    public function withStatus(int $code, string $reasonPhrase = ''): self;
    public function getReasonPhrase(): string;
    public function getProtocolVersion(): string;
    public function withProtocolVersion(string $version): self;
    public function getHeaders(): array;
    public function hasHeader(string $name): bool;
    public function getHeader(string $name): array;
    public function getHeaderLine(string $name): string;
    public function withHeader(string $name, $value): self;
    public function withAddedHeader(string $name, $value): self;
    public function withoutHeader(string $name): self;
    public function getBody(): StreamInterface;
    public function withBody(StreamInterface $body): self;
    public function send(): void;
}
