<?php

namespace BMND\Http;

use Psr\Http\Message\UriInterface;
use InvalidArgumentException;

class Uri implements UriInterface
{
    private const SCHEME_PORTS = [
        'http' => 80,
        'https' => 443,
        'ftp' => 21,
    ];
    
    private const CHAR_UNRESERVED = 'a-zA-Z0-9_\-\.~';
    private const CHAR_SUB_DELIMS = '!\$&\'\(\)\*\+,;=';
    
    /** @var string */
    private $scheme = '';
    
    /** @var string */
    private $userInfo = '';
    
    /** @var string */
    private $host = '';
    
    /** @var int|null */
    private $port;
    
    /** @var string */
    private $path = '';
    
    /** @var string */
    private $query = '';
    
    /** @var string */
    private $fragment = '';
    
    /**
     * @param string $uri URI строка
     */
    public function __construct(string $uri = '')
    {
        if ($uri !== '') {
            $parts = parse_url($uri);
            
            if ($parts === false) {
                throw new InvalidArgumentException("Unable to parse URI: $uri");
            }
            
            $this->applyParts($parts);
        }
    }
    
    /**
     * Применяет части URI из parse_url
     */
    private function applyParts(array $parts): void
    {
        $this->scheme = isset($parts['scheme']) 
            ? $this->filterScheme($parts['scheme']) 
            : '';
        
        $this->userInfo = isset($parts['user']) 
            ? $this->filterUserInfoPart($parts['user']) 
            : '';
        
        if (isset($parts['pass'])) {
            $this->userInfo .= ':' . $this->filterUserInfoPart($parts['pass']);
        }
        
        $this->host = isset($parts['host']) 
            ? $this->filterHost($parts['host']) 
            : '';
        
        $this->port = isset($parts['port']) 
            ? $this->filterPort($parts['port']) 
            : null;
        
        $this->path = isset($parts['path']) 
            ? $this->filterPath($parts['path']) 
            : '';
        
        $this->query = isset($parts['query']) 
            ? $this->filterQueryAndFragment($parts['query']) 
            : '';
        
        $this->fragment = isset($parts['fragment']) 
            ? $this->filterQueryAndFragment($parts['fragment']) 
            : '';
    }
    
    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        return $this->buildUri();
    }
    
    /**
     * Строит URI строку из компонентов
     */
    private function buildUri(): string
    {
        $uri = '';
        
        // Scheme
        if ($this->scheme !== '') {
            $uri .= $this->scheme . ':';
        }
        
        // Authority
        $authority = $this->getAuthority();
        if ($authority !== '') {
            $uri .= '//' . $authority;
        }
        
        // Path
        $uri .= $this->path;
        
        // Query
        if ($this->query !== '') {
            $uri .= '?' . $this->query;
        }
        
        // Fragment
        if ($this->fragment !== '') {
            $uri .= '#' . $this->fragment;
        }
        
        return $uri;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getScheme(): string
    {
        return $this->scheme;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getAuthority(): string
    {
        if ($this->host === '') {
            return '';
        }
        
        $authority = $this->host;
        
        if ($this->userInfo !== '') {
            $authority = $this->userInfo . '@' . $authority;
        }
        
        if ($this->port !== null && !$this->isStandardPort()) {
            $authority .= ':' . $this->port;
        }
        
        return $authority;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getUserInfo(): string
    {
        return $this->userInfo;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getHost(): string
    {
        return $this->host;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getPort(): ?int
    {
        return $this->port;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getPath(): string
    {
        return $this->path;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getQuery(): string
    {
        return $this->query;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getFragment(): string
    {
        return $this->fragment;
    }
    
    /**
     * {@inheritdoc}
     */
    public function withScheme($scheme): UriInterface
    {
        if (!is_string($scheme)) {
            throw new InvalidArgumentException('Scheme must be a string');
        }
        
        $scheme = $this->filterScheme($scheme);
        
        if ($this->scheme === $scheme) {
            return $this;
        }
        
        $new = clone $this;
        $new->scheme = $scheme;
        $new->port = $new->filterPort($new->port);
        
        return $new;
    }
    
    /**
     * {@inheritdoc}
     */
    public function withUserInfo($user, $password = null): UriInterface
    {
        if (!is_string($user)) {
            throw new InvalidArgumentException('User must be a string');
        }
        
        $userInfo = $this->filterUserInfoPart($user);
        
        if ($password !== null) {
            if (!is_string($password)) {
                throw new InvalidArgumentException('Password must be a string');
            }
            $userInfo .= ':' . $this->filterUserInfoPart($password);
        }
        
        if ($this->userInfo === $userInfo) {
            return $this;
        }
        
        $new = clone $this;
        $new->userInfo = $userInfo;
        
        return $new;
    }
    
    /**
     * {@inheritdoc}
     */
    public function withHost($host): UriInterface
    {
        if (!is_string($host)) {
            throw new InvalidArgumentException('Host must be a string');
        }
        
        $host = $this->filterHost($host);
        
        if ($this->host === $host) {
            return $this;
        }
        
        $new = clone $this;
        $new->host = $host;
        
        return $new;
    }
    
    /**
     * {@inheritdoc}
     */
    public function withPort($port): UriInterface
    {
        $port = $this->filterPort($port);
        
        if ($this->port === $port) {
            return $this;
        }
        
        $new = clone $this;
        $new->port = $port;
        
        return $new;
    }
    
    /**
     * {@inheritdoc}
     */
    public function withPath($path): UriInterface
    {
        if (!is_string($path)) {
            throw new InvalidArgumentException('Path must be a string');
        }
        
        $path = $this->filterPath($path);
        
        if ($this->path === $path) {
            return $this;
        }
        
        $new = clone $this;
        $new->path = $path;
        
        return $new;
    }
    
    /**
     * {@inheritdoc}
     */
    public function withQuery($query): UriInterface
    {
        if (!is_string($query)) {
            throw new InvalidArgumentException('Query must be a string');
        }
        
        $query = $this->filterQueryAndFragment($query);
        
        if ($this->query === $query) {
            return $this;
        }
        
        $new = clone $this;
        $new->query = $query;
        
        return $new;
    }
    
    /**
     * {@inheritdoc}
     */
    public function withFragment($fragment): UriInterface
    {
        if (!is_string($fragment)) {
            throw new InvalidArgumentException('Fragment must be a string');
        }
        
        $fragment = $this->filterQueryAndFragment($fragment);
        
        if ($this->fragment === $fragment) {
            return $this;
        }
        
        $new = clone $this;
        $new->fragment = $fragment;
        
        return $new;
    }
    
    /**
     * Фильтрует схему
     */
    private function filterScheme(string $scheme): string
    {
        $scheme = strtolower($scheme);
        $scheme = preg_replace('#:(//)?$#', '', $scheme);
        
        if (!preg_match('/^[a-z][a-z0-9+\-.]*$/', $scheme)) {
            throw new InvalidArgumentException(
                'Scheme must be compliant with RFC 3986'
            );
        }
        
        return $scheme;
    }
    
    /**
     * Фильтрует часть user info
     */
    private function filterUserInfoPart(string $part): string
    {
        return $this->encodePart($part, self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMS . ':');
    }
    
    /**
     * Фильтрует хост
     */
    private function filterHost(string $host): string
    {
        // IDN to ASCII
        if (function_exists('idn_to_ascii') && defined('INTL_IDNA_VARIANT_UTS46')) {
            $host = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        }
        
        return strtolower($host);
    }
    
    /**
     * Фильтрует порт
     */
    private function filterPort($port): ?int
    {
        if ($port === null) {
            return null;
        }
        
        $port = (int) $port;
        
        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException(
                'Port must be between 1 and 65535'
            );
        }
        
        return $this->isStandardPort() ? null : $port;
    }
    
    /**
     * Фильтрует путь
     */
    private function filterPath(string $path): string
    {
        // Декодируем закодированные слэши
        $path = preg_replace_callback(
            '/(?:[^' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMS . '%:@\/]++|%(?![A-Fa-f0-9]{2}))/',
            [$this, 'rawUrlEncodeMatch'],
            $path
        );
        
        // Если есть authority, путь должен начинаться со слэша или быть пустым
        if ($this->getAuthority() !== '' && $path !== '' && $path[0] !== '/') {
            $path = '/' . $path;
        }
        
        return $path;
    }
    
    /**
     * Фильтрует query и fragment
     */
    private function filterQueryAndFragment(string $str): string
    {
        return preg_replace_callback(
            '/(?:[^' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMS . '%:@\/?]++|%(?![A-Fa-f0-9]{2}))/',
            [$this, 'rawUrlEncodeMatch'],
            $str
        );
    }
    
    /**
     * Кодирует часть URI
     */
    private function encodePart(string $str, string $charMask): string
    {
        return preg_replace_callback(
            '/(?:[^' . $charMask . ']++|%(?![A-Fa-f0-9]{2}))/',
            [$this, 'rawUrlEncodeMatch'],
            $str
        );
    }
    
    /**
     * Callback для preg_replace_callback
     */
    private function rawUrlEncodeMatch(array $match): string
    {
        return rawurlencode($match[0]);
    }
    
    /**
     * Проверяет стандартный ли порт для текущей схемы
     */
    private function isStandardPort(): bool
    {
        if ($this->port === null) {
            return true;
        }
        
        if ($this->scheme === '' || !isset(self::SCHEME_PORTS[$this->scheme])) {
            return false;
        }
        
        return $this->port === self::SCHEME_PORTS[$this->scheme];
    }
    
    /**
     * Создает Uri из глобальных переменных (удобный метод)
     */
    public static function fromGlobals(): self
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
            ? 'https' : 'http';
        
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? $_SERVER['SERVER_ADDR'] ?? 'localhost';
        
        // Убираем порт из host если есть
        $host = explode(':', $host)[0];
        
        $port = $_SERVER['SERVER_PORT'] ?? null;
        if ($port && (($scheme === 'http' && $port == 80) || ($scheme === 'https' && $port == 443))) {
            $port = null;
        }
        
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        $query = $_SERVER['QUERY_STRING'] ?? '';
        
        // Убираем query string из path если есть
        if ($query !== '' && strpos($path, '?') !== false) {
            $path = explode('?', $path, 2)[0];
        }
        
        return new self(
            $scheme . '://' . $host . 
            ($port ? ':' . $port : '') . 
            $path . 
            ($query ? '?' . $query : '')
        );
    }
    
    /**
     * Парсит query string в массив (удобный метод)
     */
    public function getQueryParams(): array
    {
        $params = [];
        if ($this->query !== '') {
            parse_str($this->query, $params);
        }
        return $params;
    }
    
    /**
     * Добавляет параметры к query (удобный метод)
     */
    public function withQueryParams(array $params): self
    {
        $currentParams = $this->getQueryParams();
        $newParams = array_merge($currentParams, $params);
        
        return $this->withQuery(http_build_query($newParams, '', '&', PHP_QUERY_RFC3986));
    }
}
