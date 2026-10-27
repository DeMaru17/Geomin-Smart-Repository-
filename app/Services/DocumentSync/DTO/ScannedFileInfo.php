<?php

namespace App\Services\DocumentSync\DTO;

readonly class ScannedFileInfo
{
    public function __construct(
        public string $filename,
        public string $relativePath,
        public string $absolutePath,
        public string $extension,
        public int $sizeBytes,
        public string $hash,
    ) {}

    public function toArray(): array
    {
        return [
            'filename' => $this->filename,
            'relative_path' => $this->relativePath,
            'absolute_path' => $this->absolutePath,
            'extension' => $this->extension,
            'size_bytes' => $this->sizeBytes,
            'hash' => $this->hash,
        ];
    }
}
