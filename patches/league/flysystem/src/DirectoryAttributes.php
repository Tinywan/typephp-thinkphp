<?php

declare(strict_types=1);

namespace League\Flysystem;

class DirectoryAttributes implements StorageAttributes
{
    public const TYPE_DIRECTORY = 'dir';
    public const ATTRIBUTE_PATH = 'path';
    public const ATTRIBUTE_TYPE = 'type';
    public const ATTRIBUTE_FILE_SIZE = 'file_size';
    public const ATTRIBUTE_VISIBILITY = 'visibility';
    public const ATTRIBUTE_LAST_MODIFIED = 'last_modified';
    public const ATTRIBUTE_MIME_TYPE = 'mime_type';
    public const ATTRIBUTE_EXTRA_METADATA = 'extra_metadata';

    private string $type = self::TYPE_DIRECTORY;
    private string $path;
    private ?string $visibility;
    private ?int $lastModified;
    private array $extraMetadata;

    public function __construct(
        string $path,
        ?string $visibility = null,
        ?int $lastModified = null,
        array $extraMetadata = []
    ) {
        $this->path = trim($path, '/');
        $this->visibility = $visibility;
        $this->lastModified = $lastModified;
        $this->extraMetadata = $extraMetadata;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function visibility(): ?string
    {
        return $this->visibility;
    }

    public function lastModified(): ?int
    {
        return $this->lastModified;
    }

    public function extraMetadata(): array
    {
        return $this->extraMetadata;
    }

    public function isDir(): bool
    {
        return true;
    }

    public function isFile(): bool
    {
        return false;
    }

    public static function fromArray(array $attributes): self
    {
        return new DirectoryAttributes(
            $attributes[self::ATTRIBUTE_PATH],
            $attributes[self::ATTRIBUTE_VISIBILITY] ?? null,
            $attributes[self::ATTRIBUTE_LAST_MODIFIED] ?? null,
            $attributes[self::ATTRIBUTE_EXTRA_METADATA] ?? []
        );
    }

    public function jsonSerialize(): array
    {
        return [
            self::ATTRIBUTE_TYPE => self::TYPE_DIRECTORY,
            self::ATTRIBUTE_PATH => $this->path,
            self::ATTRIBUTE_VISIBILITY => $this->visibility,
            self::ATTRIBUTE_LAST_MODIFIED => $this->lastModified,
            self::ATTRIBUTE_EXTRA_METADATA => $this->extraMetadata,
        ];
    }

    public function withPath(string $path): self
    {
        $clone = clone $this;
        $clone->path = $path;

        return $clone;
    }
}
