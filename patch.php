<?php

/**
 * Find the project vendor/ directory by walking up from the package directory.
 *
 * Works in both scenarios:
 *   1. Root package (dev):  __DIR__/vendor/          → immediate
 *   2. Dependency (vendor):  __DIR__/../../vendor/    → walk up
 */
function getVendorDir(string $packageDir): string
{
    $dir = $packageDir;

    for ($i = 0; $i < 10; $i++) {
        if (is_dir($dir . '/vendor')) {
            return realpath($dir . '/vendor') . '/';
        }
        $parent = dirname($dir);
        if ($parent === $dir) {
            break; // hit filesystem root
        }
        $dir = $parent;
    }

    // Fallback: assume vendor/ lives alongside the package
    return $packageDir . '/vendor/';
}

function copyPatchesSafely(): bool
{
    $packageDir = __DIR__;
    $source = $packageDir . '/patches/';

    if (!is_dir($source)) {
        return false;
    }

    $destination = getVendorDir($packageDir);

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $source,
            RecursiveDirectoryIterator::SKIP_DOTS,
        ),
        RecursiveIteratorIterator::SELF_FIRST,
    );

    foreach ($iterator as $item) {
        $relativePath = substr($item->getPathname(), strlen($source));
        $target = $destination . $relativePath;

        if ($item->isDir()) {
            if (!is_dir($target)) {
                mkdir($target, 0755, true);
            }
        } else {
            copy($item->getPathname(), $target);
        }
    }

    // Write marker so bootstrap.php can skip on subsequent requests
    file_put_contents($packageDir . '/.patches_applied', date('c'));

    return true;
}

copyPatchesSafely();
