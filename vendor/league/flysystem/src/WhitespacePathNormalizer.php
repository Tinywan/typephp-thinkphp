<?php

declare(strict_types=1);

namespace League\Flysystem;

use function array_pop;
use function explode;
use function implode;
use function preg_match;
use function str_replace;

class WhitespacePathNormalizer implements PathNormalizer
{
    private bool $allowRelativePaths;

    public function __construct(bool $allowRelativePathTraversal = true)
    {
        $this->allowRelativePaths = $allowRelativePathTraversal;
    }

    public function normalizePath(string $path): string
    {
        $unixPath = str_replace('\\', '/', $path);

        if (preg_match('#\p{C}+#u', $unixPath)) {
            $err = CorruptedPathDetected::forPath($path);
            throw $err;
        }

        $parts = [];

        foreach (explode('/', $unixPath) as $part) {
            switch ($part) {
                case '':
                case '.':
                    break;

                case '..':
                    if ($this->allowRelativePaths === false || empty($parts)) {
                        $e2 = PathTraversalDetected::forPath($path);
                        throw $e2;
                    }

                    array_pop($parts);
                    break;

                default:
                    $parts[] = $part;
                    break;
            }
        }

        return implode('/', $parts);
    }
}
