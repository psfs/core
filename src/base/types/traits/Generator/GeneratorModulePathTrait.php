<?php

namespace PSFS\base\types\traits\Generator;

use PSFS\base\exception\GeneratorException;

/**
 * Normalizes module names and validates their canonical boundary under CORE_DIR.
 *
 * The methods remain available through GeneratorHelper to preserve its public API.
 */
trait GeneratorModulePathTrait
{
    /**
     * Normalize legacy module separators and reject path components that can
     * escape or be reinterpreted outside the intended module root.
     *
     * @throws GeneratorException
     */
    public static function normalizeModuleName(string $module): string
    {
        $module = str_replace('\\', '/', $module);
        if (str_starts_with($module, '/')) {
            $module = substr($module, 1);
        }

        if (
            $module === ''
            || str_starts_with($module, '/')
            || str_contains($module, ':')
            || preg_match('/[\x00-\x1F\x7F]/', $module) === 1
        ) {
            throw new GeneratorException(t('Invalid module path'));
        }

        foreach (explode('/', $module) as $segment) {
            if ($segment === '' || rtrim($segment, '. ') !== $segment) {
                throw new GeneratorException(t('Invalid module path'));
            }
        }

        return $module;
    }

    /**
     * Ensure the existing destination or its nearest existing ancestor stays
     * within the canonical CORE_DIR, including when an existing path is a symlink.
     *
     * @throws GeneratorException
     */
    public static function assertModulePathWithinCore(string $module): void
    {
        $module = self::normalizeModuleName($module);
        [$canonicalRoot, $rootExists] = self::canonicalCoreRoot();
        $modulePath = $canonicalRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $module);
        $existingPath = self::nearestExistingPath($modulePath, $canonicalRoot);

        if (!$rootExists && $existingPath === $canonicalRoot) {
            return;
        }

        self::assertResolvedPathWithinCore(self::resolveExistingPath($existingPath), $canonicalRoot);
    }

    /**
     * Resolve CORE_DIR or its canonical future location when it does not exist yet.
     *
     * @return array{0: string, 1: bool} Canonical root and whether it currently exists.
     * @throws GeneratorException
     */
    private static function canonicalCoreRoot(): array
    {
        $coreDirectory = rtrim(CORE_DIR, '/\\');
        if ($coreDirectory === '') {
            $coreDirectory = DIRECTORY_SEPARATOR;
        }

        $canonicalRoot = realpath($coreDirectory);
        if ($canonicalRoot !== false) {
            return [$canonicalRoot, true];
        }

        if (is_link($coreDirectory)) {
            throw new GeneratorException(t('Invalid module path'));
        }

        $canonicalParent = realpath(dirname($coreDirectory));
        $rootName = basename($coreDirectory);
        if ($canonicalParent === false || $rootName === '' || $rootName === '.' || $rootName === '..') {
            throw new GeneratorException(t('Invalid module path'));
        }

        return [rtrim($canonicalParent, '/\\') . DIRECTORY_SEPARATOR . $rootName, false];
    }

    /**
     * Find the closest existing file, directory, or symlink for a module destination.
     */
    private static function nearestExistingPath(string $modulePath, string $canonicalRoot): string
    {
        $existingPath = $modulePath;
        while (
            $existingPath !== $canonicalRoot
            && !file_exists($existingPath)
            && !is_link($existingPath)
        ) {
            $parentPath = dirname($existingPath);
            if ($parentPath === $existingPath) {
                break;
            }
            $existingPath = $parentPath;
        }

        return $existingPath;
    }

    /**
     * Resolve a path that was already confirmed to exist.
     *
     * @throws GeneratorException
     */
    private static function resolveExistingPath(string $existingPath): string
    {
        $canonicalExistingPath = realpath($existingPath);
        if ($canonicalExistingPath === false) {
            throw new GeneratorException(t('Invalid module path'));
        }

        return $canonicalExistingPath;
    }

    /**
     * Reject canonical paths that fall outside the canonical core directory.
     *
     * @throws GeneratorException
     */
    private static function assertResolvedPathWithinCore(string $canonicalExistingPath, string $canonicalRoot): void
    {
        $normalizedRoot = rtrim($canonicalRoot, '/\\');
        if ($normalizedRoot === '') {
            $normalizedRoot = DIRECTORY_SEPARATOR;
        }
        $rootPrefix = $normalizedRoot === DIRECTORY_SEPARATOR
            ? DIRECTORY_SEPARATOR
            : $normalizedRoot . DIRECTORY_SEPARATOR;
        $resolvedPath = DIRECTORY_SEPARATOR === '\\'
            ? strtolower($canonicalExistingPath)
            : $canonicalExistingPath;
        $resolvedRoot = DIRECTORY_SEPARATOR === '\\'
            ? strtolower($normalizedRoot)
            : $normalizedRoot;
        $resolvedPrefix = DIRECTORY_SEPARATOR === '\\'
            ? strtolower($rootPrefix)
            : $rootPrefix;

        if ($resolvedPath !== $resolvedRoot && !str_starts_with($resolvedPath, $resolvedPrefix)) {
            throw new GeneratorException(t('Invalid module path'));
        }
    }
}
