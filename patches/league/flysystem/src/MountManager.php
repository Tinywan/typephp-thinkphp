<?php

declare(strict_types=1);

namespace League\Flysystem;

use DateTimeInterface;
use Throwable;

use function compact;
use function method_exists;
use function sprintf;

class MountManager implements FilesystemOperator
{
    /**
     * @var array<string, FilesystemOperator>
     */
    private $filesystems = [];

    /**
     * @var Config
     */
    private $config;

    public function __construct(array $filesystems = [], array $config = [])
    {
        $this->mountFilesystems($filesystems);
        $this->config = new Config($config);
    }

    public function dangerouslyMountFilesystems(string $key, FilesystemOperator $filesystem): void
    {
        $this->mountFilesystem($key, $filesystem);
    }

    public function extend(array $filesystems, array $config = []): MountManager
    {
        $clone = clone $this;
        $clone->config = $this->config->extend($config);
        $clone->mountFilesystems($filesystems);

        return $clone;
    }

    public function fileExists(string $location): bool
    {
        [$filesystem, $path] = $this->determineFilesystemAndPath($location);

        try {
            return $filesystem->fileExists($path);
        } catch (Throwable $exception) {
            $e = UnableToCheckFileExistence::forLocation($location, $exception);
            throw $e;
        }
    }

    public function has(string $location): bool
    {
        [$filesystem, $path] = $this->determineFilesystemAndPath($location);

        try {
            return $filesystem->fileExists($path) || $filesystem->directoryExists($path);
        } catch (Throwable $exception) {
            $e = UnableToCheckExistence::forLocation($location, $exception);
            throw $e;
        }
    }

    public function directoryExists(string $location): bool
    {
        [$filesystem, $path] = $this->determineFilesystemAndPath($location);

        try {
            return $filesystem->directoryExists($path);
        } catch (Throwable $exception) {
            $e = UnableToCheckDirectoryExistence::forLocation($location, $exception);
            throw $e;
        }
    }

    public function read(string $location): string
    {
        [$filesystem, $path] = $this->determineFilesystemAndPath($location);

        try {
            return $filesystem->read($path);
        } catch (UnableToReadFile $exception) {
            $e = UnableToReadFile::fromLocation($location, $exception->reason(), $exception);
            throw $e;
        }
    }

    public function readStream(string $location)
    {
        [$filesystem, $path] = $this->determineFilesystemAndPath($location);

        try {
            return $filesystem->readStream($path);
        } catch (UnableToReadFile $exception) {
            $e = UnableToReadFile::fromLocation($location, $exception->reason(), $exception);
            throw $e;
        }
    }

    public function listContents(string $location, bool $deep = false): DirectoryListing
    {
        [$filesystem, $path, $mountIdentifier] = $this->determineFilesystemAndPath($location);

        return
            $filesystem
                ->listContents($path, $deep)
                ->map(
                    function (StorageAttributes $attributes) use ($mountIdentifier) {
                        return $attributes->withPath(sprintf('%s://%s', $mountIdentifier, $attributes->path()));
                    }
                );
    }

    public function lastModified(string $location): int
    {
        [$filesystem, $path] = $this->determineFilesystemAndPath($location);

        try {
            return $filesystem->lastModified($path);
        } catch (UnableToRetrieveMetadata $exception) {
            $e = UnableToRetrieveMetadata::lastModified($location, $exception->reason(), $exception);
            throw $e;
        }
    }

    public function fileSize(string $location): int
    {
        [$filesystem, $path] = $this->determineFilesystemAndPath($location);

        try {
            return $filesystem->fileSize($path);
        } catch (UnableToRetrieveMetadata $exception) {
            $e = UnableToRetrieveMetadata::fileSize($location, $exception->reason(), $exception);
            throw $e;
        }
    }

    public function mimeType(string $location): string
    {
        [$filesystem, $path] = $this->determineFilesystemAndPath($location);

        try {
            return $filesystem->mimeType($path);
        } catch (UnableToRetrieveMetadata $exception) {
            $e = UnableToRetrieveMetadata::mimeType($location, $exception->reason(), $exception);
            throw $e;
        }
    }

    public function visibility(string $path): string
    {
        [$filesystem, $location] = $this->determineFilesystemAndPath($path);

        try {
            return $filesystem->visibility($location);
        } catch (UnableToRetrieveMetadata $exception) {
            $e = UnableToRetrieveMetadata::visibility($path, $exception->reason(), $exception);
            throw $e;
        }
    }

    public function write(string $location, string $contents, array $config = []): void
    {
        [$filesystem, $path] = $this->determineFilesystemAndPath($location);

        try {
            $filesystem->write($path, $contents, $this->config->extend($config)->toArray());
        } catch (UnableToWriteFile $exception) {
            $e = UnableToWriteFile::atLocation($location, $exception->reason(), $exception);
            throw $e;
        }
    }

    public function writeStream(string $location, $contents, array $config = []): void
    {
        [$filesystem, $path] = $this->determineFilesystemAndPath($location);
        $filesystem->writeStream($path, $contents, $this->config->extend($config)->toArray());
    }

    public function setVisibility(string $path, string $visibility): void
    {
        [$filesystem, $path] = $this->determineFilesystemAndPath($path);
        $filesystem->setVisibility($path, $visibility);
    }

    public function delete(string $location): void
    {
        [$filesystem, $path] = $this->determineFilesystemAndPath($location);

        try {
            $filesystem->delete($path);
        } catch (UnableToDeleteFile $exception) {
            $e = UnableToDeleteFile::atLocation($location, $exception->reason(), $exception);
            throw $e;
        }
    }

    public function deleteDirectory(string $location): void
    {
        [$filesystem, $path] = $this->determineFilesystemAndPath($location);

        try {
            $filesystem->deleteDirectory($path);
        } catch (UnableToDeleteDirectory $exception) {
            $e = UnableToDeleteDirectory::atLocation($location, $exception->reason(), $exception);
            throw $e;
        }
    }

    public function createDirectory(string $location, array $config = []): void
    {
        [$filesystem, $path] = $this->determineFilesystemAndPath($location);

        try {
            $filesystem->createDirectory($path, $this->config->extend($config)->toArray());
        } catch (UnableToCreateDirectory $exception) {
            $e = UnableToCreateDirectory::dueToFailure($location, $exception);
            throw $e;
        }
    }

    public function move(string $source, string $destination, array $config = []): void
    {
        [$sourceFilesystem, $sourcePath] = $this->determineFilesystemAndPath($source);
        [$destinationFilesystem, $destinationPath] = $this->determineFilesystemAndPath($destination);

        if ($sourceFilesystem === $destinationFilesystem) {
            $this->moveInTheSameFilesystem(
                $sourceFilesystem,
                $sourcePath,
                $destinationPath,
                $source,
                $destination,
                $config,
            );
        } else {
            $this->moveAcrossFilesystems($source, $destination, $config);
        }
    }

    public function copy(string $source, string $destination, array $config = []): void
    {
        [$sourceFilesystem, $sourcePath] = $this->determineFilesystemAndPath($source);
        [$destinationFilesystem, $destinationPath] = $this->determineFilesystemAndPath($destination);

        if ($sourceFilesystem === $destinationFilesystem) {
            $this->copyInSameFilesystem(
                $sourceFilesystem,
                $sourcePath,
                $destinationPath,
                $source,
                $destination,
                $config,
            );
        } else {
            $this->copyAcrossFilesystem(
                $sourceFilesystem,
                $sourcePath,
                $destinationFilesystem,
                $destinationPath,
                $source,
                $destination,
                $config,
            );
        }
    }

    public function publicUrl(string $path, array $config = []): string
    {
        [$filesystem, $path] = $this->determineFilesystemAndPath($path);

        if ( ! method_exists($filesystem, 'publicUrl')) {
            $e = new UnableToGeneratePublicUrl(sprintf('%s does not support generating public urls.', $filesystem::class), $path);
            throw $e;
        }

        return $filesystem->publicUrl($path, $config);
    }

    public function temporaryUrl(string $path, DateTimeInterface $expiresAt, array $config = []): string
    {
        [$filesystem, $path] = $this->determineFilesystemAndPath($path);

        if ( ! method_exists($filesystem, 'temporaryUrl')) {
            $e = new UnableToGenerateTemporaryUrl(sprintf('%s does not support generating public urls.', $filesystem::class), $path);
            throw $e;
        }

        return $filesystem->temporaryUrl($path, $expiresAt, $this->config->extend($config)->toArray());
    }

    public function checksum(string $path, array $config = []): string
    {
        [$filesystem, $path] = $this->determineFilesystemAndPath($path);

        if ( ! method_exists($filesystem, 'checksum')) {
            $e = new UnableToProvideChecksum(sprintf('%s does not support providing checksums.', $filesystem::class), $path);
            throw $e;
        }

        return $filesystem->checksum($path, $this->config->extend($config)->toArray());
    }

    private function mountFilesystems(array $filesystems): void
    {
        foreach ($filesystems as $key => $filesystem) {
            $this->guardAgainstInvalidMount($key, $filesystem);
            $this->mountFilesystem($key, $filesystem);
        }
    }

    private function guardAgainstInvalidMount(mixed $key, mixed $filesystem): void
    {
        if ( ! is_string($key)) {
            $eKey = UnableToMountFilesystem::becauseTheKeyIsNotValid($key);
            throw $eKey;
        }

        if ( ! $filesystem instanceof FilesystemOperator) {
            $eFs = UnableToMountFilesystem::becauseTheFilesystemWasNotValid($filesystem);
            throw $eFs;
        }
    }

    private function mountFilesystem(string $key, FilesystemOperator $filesystem): void
    {
        $this->filesystems[$key] = $filesystem;
    }

    private function determineFilesystemAndPath(string $path): array
    {
        if (strpos($path, '://') < 1) {
            $eSep = UnableToResolveFilesystemMount::becauseTheSeparatorIsMissing($path);
            throw $eSep;
        }

        [$mountIdentifier, $mountPath] = explode('://', $path, 2);

        if ( ! array_key_exists($mountIdentifier, $this->filesystems)) {
            $eMount = UnableToResolveFilesystemMount::becauseTheMountWasNotRegistered($mountIdentifier);
            throw $eMount;
        }

        return [$this->filesystems[$mountIdentifier], $mountPath, $mountIdentifier];
    }

    private function copyInSameFilesystem(
        FilesystemOperator $sourceFilesystem,
        string $sourcePath,
        string $destinationPath,
        string $source,
        string $destination,
        array $config,
    ): void {
        try {
            $sourceFilesystem->copy($sourcePath, $destinationPath, $this->config->extend($config)->toArray());
        } catch (UnableToCopyFile $exception) {
            $e = UnableToCopyFile::fromLocationTo($source, $destination, $exception);
            throw $e;
        }
    }

    private function copyAcrossFilesystem(
        FilesystemOperator $sourceFilesystem,
        string $sourcePath,
        FilesystemOperator $destinationFilesystem,
        string $destinationPath,
        string $source,
        string $destination,
        array $config,
    ): void {
        $copyConfig = $this->config->extend($config);
        $retainVisibility = (bool) $copyConfig->get(Config::OPTION_RETAIN_VISIBILITY, true);
        $visibility = $copyConfig->get(Config::OPTION_VISIBILITY);

        try {
            if ($visibility == null && $retainVisibility) {
                $visibility = $sourceFilesystem->visibility($sourcePath);
                $copyConfig = $copyConfig->extend(compact('visibility'));
            }

            $stream = $sourceFilesystem->readStream($sourcePath);
            $destinationFilesystem->writeStream($destinationPath, $stream, $copyConfig->toArray());
        } catch (Throwable $exception) {
            $e = UnableToCopyFile::fromLocationTo($source, $destination, $exception);
            throw $e;
        }
    }

    private function moveInTheSameFilesystem(
        FilesystemOperator $sourceFilesystem,
        string $sourcePath,
        string $destinationPath,
        string $source,
        string $destination,
        array $config,
    ): void {
        try {
            $sourceFilesystem->move($sourcePath, $destinationPath, $this->config->extend($config)->toArray());
        } catch (UnableToMoveFile $exception) {
            $e = UnableToMoveFile::fromLocationTo($source, $destination, $exception);
            throw $e;
        }
    }

    private function moveAcrossFilesystems(string $source, string $destination, array $config = []): void
    {
        try {
            $this->copy($source, $destination, $config);
            $this->delete($source);
        } catch (Throwable $exception) {
            $e = UnableToMoveFile::fromLocationTo($source, $destination, $exception);
            throw $e;
        }
    }
}
