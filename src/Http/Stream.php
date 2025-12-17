<?php

namespace BMND\Http;

use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Реализация PSR-7 StreamInterface
 * Работает со строками и ресурсами
 */
class Stream implements StreamInterface
{
    /** @var resource|null */
    private $resource;
    
    /** @var string|null */
    private $stringContent;
    
    /** @var bool */
    private $isStringMode;
    
    /** @var int */
    private $size;
    
    /** @var bool */
    private $seekable;
    
    /** @var bool */
    private $readable;
    
    /** @var bool */
    private $writable;
    
    /** @var int */
    private $position = 0;
    
    /** @var array */
    private $metadata;
    
    /**
     * Конструктор
     * 
     * @param mixed $content Строка, ресурс или null
     * @param string $mode Режим работы с ресурсом (если передается ресурс)
     */
    public function __construct($content = '', string $mode = 'r+')
    {
        if (is_string($content) || $content === null) {
            $this->isStringMode = true;
            $this->stringContent = (string)$content;
            $this->resource = null;
            $this->seekable = true;
            $this->readable = true;
            $this->writable = true;
            $this->size = strlen($this->stringContent);
            return;
        }
        
        // Режим ресурса
        if (is_resource($content)) {
            $this->isStringMode = false;
            $this->resource = $content;
            $this->metadata = stream_get_meta_data($this->resource);
            $this->seekable = $this->metadata['seekable'];
            $this->readable = in_array($mode[0], ['r', '+', 'w', 'a', 'x', 'c']);
            $this->writable = in_array($mode[0], ['w', '+', 'a', 'x', 'c']);
            $this->size = null;
            return;
        }
        
        throw new RuntimeException('Stream content must be string, resource or null');
    }
    
    /**
     * Создает Stream из строки (фабричный метод)
     */
    public static function fromString(string $content): self
    {
        return new self($content);
    }
    
    /**
     * Создает Stream из ресурса (фабричный метод)
     */
    public static function fromResource($resource, string $mode = 'r+'): self
    {
        return new self($resource, $mode);
    }
    
    /**
     * Создает Stream для записи (фабричный метод)
     */
    public static function forWrite(): self
    {
        $resource = fopen('php://temp', 'r+');
        return new self($resource, 'r+');
    }
    
    /**
     * Возвращает все содержимое как строку
     */
    public function __toString(): string
    {
        try {
            if ($this->isStringMode) {
                return $this->stringContent;
            }
            
            if ($this->resource) {
                $this->rewind();
                return $this->getContents();
            }
            
            return '';
        } catch (RuntimeException $e) {
            return '';
        }
    }
    
    /**
     * Закрывает поток
     */
    public function close(): void
    {
        if ($this->resource) {
            fclose($this->resource);
        }
        $this->detach();
    }
    
    /**
     * Отсоединяет ресурс от потока
     */
    public function detach()
    {
        if ($this->isStringMode) {
            $content = $this->stringContent;
            $this->stringContent = '';
            $this->size = 0;
            $this->position = 0;
            return $content;
        }
        
        $resource = $this->resource;
        $this->resource = null;
        $this->metadata = [];
        $this->size = null;
        $this->seekable = false;
        $this->readable = false;
        $this->writable = false;
        $this->position = 0;
        
        return $resource;
    }
    
    /**
     * Возвращает размер содержимого
     */
    public function getSize(): ?int
    {
        if ($this->isStringMode) {
            return strlen($this->stringContent);
        }
        
        if ($this->resource === null) {
            return null;
        }
        
        // Кешируем размер
        if ($this->size === null) {
            $stats = fstat($this->resource);
            if ($stats !== false) {
                $this->size = $stats['size'] ?? null;
            }
        }
        
        return $this->size;
    }
    
    /**
     * Возвращает текущую позицию
     */
    public function tell(): int
    {
        if ($this->isStringMode) {
            return $this->position;
        }
        
        if ($this->resource === null) {
            throw new RuntimeException('No resource available');
        }
        
        $result = ftell($this->resource);
        if ($result === false) {
            throw new RuntimeException('Unable to determine stream position');
        }
        
        return $result;
    }
    
    /**
     * Проверяет достигнут ли конец потока
     */
    public function eof(): bool
    {
        if ($this->isStringMode) {
            return $this->position >= strlen($this->stringContent);
        }
        
        if ($this->resource) {
            return feof($this->resource);
        }
        
        return true;
    }
    
    /**
     * Проверяет можно ли перемещаться по потоку
     */
    public function isSeekable(): bool
    {
        return $this->seekable;
    }
    
    /**
     * Перемещает указатель на указанную позицию
     */
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if (!$this->isSeekable()) {
            throw new RuntimeException('Stream is not seekable');
        }
        
        if ($this->isStringMode) {
            $length = strlen($this->stringContent);
            
            switch ($whence) {
                case SEEK_SET:
                    $this->position = $offset;
                    break;
                case SEEK_CUR:
                    $this->position += $offset;
                    break;
                case SEEK_END:
                    $this->position = $length + $offset;
                    break;
            }
            
            if ($this->position < 0) {
                $this->position = 0;
            } elseif ($this->position > $length) {
                $this->position = $length;
            }
            
            return;
        }
        
        if ($this->resource === null) {
            throw new RuntimeException('No resource available');
        }
        
        if (fseek($this->resource, $offset, $whence) === -1) {
            throw new RuntimeException('Unable to seek stream');
        }
        
        $this->size = null; // Сброс кеша размера
    }
    
    /**
     * Перемещает указатель в начало
     */
    public function rewind(): void
    {
        $this->seek(0);
    }
    
    /**
     * Проверяет можно ли писать в поток
     */
    public function isWritable(): bool
    {
        return $this->writable;
    }
    
    /**
     * Записывает данные в поток
     */
    public function write(string $string): int
    {
        if (!$this->isWritable()) {
            throw new RuntimeException('Stream is not writable');
        }
        
        if ($this->isStringMode) {
            // Заменяем содержимое с текущей позиции
            $newContent = substr_replace(
                $this->stringContent,
                $string,
                $this->position,
                strlen($string)
            );
            
            $written = strlen($string);
            $this->stringContent = $newContent;
            $this->position += $written;
            $this->size = strlen($this->stringContent);
            
            return $written;
        }
        
        if ($this->resource === null) {
            throw new RuntimeException('No resource available');
        }
        
        $result = fwrite($this->resource, $string);
        if ($result === false) {
            throw new RuntimeException('Unable to write to stream');
        }
        
        $this->size = null; // Сброс кеша размера
        return $result;
    }
    
    /**
     * Проверяет можно ли читать из потока
     */
    public function isReadable(): bool
    {
        return $this->readable;
    }
    
    /**
     * Читает данные из потока
     */
    public function read(int $length): string
    {
        if (!$this->isReadable()) {
            throw new RuntimeException('Stream is not readable');
        }
        
        if ($this->isStringMode) {
            $remaining = strlen($this->stringContent) - $this->position;
            $readLength = min($length, $remaining);
            
            if ($readLength <= 0) {
                return '';
            }
            
            $result = substr($this->stringContent, $this->position, $readLength);
            $this->position += $readLength;
            
            return $result;
        }
        
        if ($this->resource === null) {
            throw new RuntimeException('No resource available');
        }
        
        $result = fread($this->resource, $length);
        if ($result === false) {
            throw new RuntimeException('Unable to read from stream');
        }
        
        return $result;
    }
    
    /**
     * Возвращает оставшееся содержимое как строку
     */
    public function getContents(): string
    {
        if (!$this->isReadable()) {
            throw new RuntimeException('Stream is not readable');
        }
        
        if ($this->isStringMode) {
            $result = substr($this->stringContent, $this->position);
            $this->position = strlen($this->stringContent);
            return $result;
        }
        
        if ($this->resource === null) {
            throw new RuntimeException('No resource available');
        }
        
        $result = stream_get_contents($this->resource);
        if ($result === false) {
            throw new RuntimeException('Unable to read stream contents');
        }
        
        return $result;
    }
    
    /**
     * Возвращает метаданные потока
     */
    public function getMetadata(?string $key = null)
    {
        if ($this->isStringMode) {
            $metadata = [
                'timed_out' => false,
                'blocked' => true,
                'eof' => $this->eof(),
                'unread_bytes' => 0,
                'stream_type' => 'string',
                'wrapper_type' => 'string',
                'wrapper_data' => null,
                'mode' => 'r+',
                'seekable' => true,
                'uri' => 'string',
            ];
            
            if ($key === null) {
                return $metadata;
            }
            
            return $metadata[$key] ?? null;
        }
        
        if ($this->resource === null) {
            return $key ? null : [];
        }
        
        $this->metadata = stream_get_meta_data($this->resource);
        
        if ($key === null) {
            return $this->metadata;
        }
        
        return $this->metadata[$key] ?? null;
    }
    
    /**
     * Добавляет содержимое в конец (удобный хелпер)
     */
    public function append(string $content): void
    {
        $currentPos = $this->tell();
        $this->seek(0, SEEK_END);
        $this->write($content);
        $this->seek($currentPos);
    }
    
    /**
     * Возвращает содержимое как строку (удобный хелпер)
     */
    public function getContentsAsString(): string
    {
        return (string)$this;
    }
}
