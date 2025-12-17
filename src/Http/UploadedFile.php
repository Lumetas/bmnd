<?php

namespace BMND\Http;

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

class UploadedFile implements UploadedFileInterface
{
    private ?string $file;
    private ?string $clientFilename;
    private ?string $clientMediaType;
    private int $error;
    private ?int $size;
    private bool $moved = false;

    public function __construct(
        string $file,
        ?int $size = null,
        int $error = UPLOAD_ERR_OK,
        ?string $clientFilename = null,
        ?string $clientMediaType = null
    ) {
        $this->file = $file;
        $this->size = $size;
        $this->error = $error;
        $this->clientFilename = $clientFilename;
        $this->clientMediaType = $clientMediaType;
    }

    public function getStream(): StreamInterface
    {
        if ($this->moved) {
            throw new RuntimeException('Cannot retrieve stream after it has been moved');
        }

        if ($this->error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Cannot retrieve stream due to upload error');
        }

        if ($this->file === '') {
            throw new RuntimeException('No file available');
        }

        return new Stream(fopen($this->file, 'r'));
    }

    public function moveTo(string $targetPath): void
    {
        if ($this->moved) {
            throw new RuntimeException('File has already been moved');
        }

        if ($this->error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Cannot move file due to upload error');
        }

        if ($this->file === '') {
            throw new RuntimeException('No file available');
        }

        $targetDirectory = dirname($targetPath);
        if (!is_dir($targetDirectory) || !is_writable($targetDirectory)) {
            throw new RuntimeException('Target directory does not exist or is not writable');
        }

        $sapi = PHP_SAPI;
        if (empty($sapi) || strpos($sapi, 'cli') === 0 || !$this->file) {
            // Не через SAPI или CLI
            if (!rename($this->file, $targetPath)) {
                throw new RuntimeException('Error moving uploaded file');
            }
        } elseif (!move_uploaded_file($this->file, $targetPath)) {
            throw new RuntimeException('Error moving uploaded file');
        }

        $this->moved = true;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function getError(): int
    {
        return $this->error;
    }

    public function getClientFilename(): ?string
    {
        return $this->clientFilename;
    }

    public function getClientMediaType(): ?string
    {
        return $this->clientMediaType;
    }
}
