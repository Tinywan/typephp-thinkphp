<?php

declare(strict_types=1);

namespace League\Flysystem;

use ArrayAccess;
use JsonSerializable;

interface StorageAttributes extends JsonSerializable, ArrayAccess
{
    public function path(): string;

    public function type(): string;

    public function visibility(): ?string;

    public function lastModified(): ?int;

    public static function fromArray(array $attributes): StorageAttributes;

    public function isFile(): bool;

    public function isDir(): bool;

    public function withPath(string $path): StorageAttributes;

    public function extraMetadata(): array;
}
