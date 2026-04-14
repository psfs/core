<?php

namespace PSFS\base\queue;

use LogicException;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

class JobRegistry
{
    /**
     * @var array<string, string>
     */
    private array $jobs = [];

    /**
     * @param array<int, string>|null $jobClasses
     * @param array<int, string>|null $paths
     */
    public function __construct(?array $jobClasses = null, ?array $paths = null)
    {
        $classes = $jobClasses ?? $this->discoverJobClasses($paths ?? $this->defaultDiscoveryPaths());
        foreach ($classes as $className) {
            $this->register($className);
        }
    }

    public function has(string $code): bool
    {
        return array_key_exists($code, $this->jobs);
    }

    /**
     * @return string
     */
    public function get(string $code): string
    {
        if (!$this->has($code)) {
            throw new LogicException(sprintf('Queue job "%s" is not registered', $code));
        }
        return $this->jobs[$code];
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->jobs;
    }

    /**
     * @param string $className
     */
    public function register(string $className): void
    {
        if (!class_exists($className)) {
            throw new LogicException(sprintf('Queue job class "%s" does not exist', $className));
        }
        if (!is_subclass_of($className, QueueJobInterface::class)) {
            throw new LogicException(sprintf('Queue job class "%s" must implement %s', $className, QueueJobInterface::class));
        }
        $reflection = new ReflectionClass($className);
        if ($reflection->isAbstract()) {
            return;
        }
        $code = $className::code();
        if (isset($this->jobs[$code]) && $this->jobs[$code] !== $className) {
            throw new LogicException(sprintf('Queue job code collision for "%s": %s and %s', $code, $this->jobs[$code], $className));
        }
        $this->jobs[$code] = $className;
    }

    /**
     * @param array<int, string> $paths
     * @return array<int, string>
     */
    private function discoverJobClasses(array $paths): array
    {
        $classes = [];
        foreach ($paths as $path) {
            if (!is_dir($path)) {
                continue;
            }
            $finder = new Finder();
            $finder->files()->in($path)->name('*.php');
            foreach ($finder as $file) {
                $className = $this->extractClassName($file->getRealPath());
                if (null === $className) {
                    continue;
                }
                require_once $file->getRealPath();
                if (class_exists($className)) {
                    $classes[] = $className;
                }
            }
        }
        return array_values(array_unique($classes));
    }

    /**
     * @return array<int, string>
     */
    private function defaultDiscoveryPaths(): array
    {
        $paths = [SOURCE_DIR . DIRECTORY_SEPARATOR . 'Queue'];
        if (defined('CORE_DIR') && is_dir(CORE_DIR)) {
            $finder = new Finder();
            $finder->directories()->in(CORE_DIR)->depth('< 4')->name('Queue');
            foreach ($finder as $directory) {
                $paths[] = $directory->getRealPath();
            }
        }
        return array_values(array_unique(array_filter($paths)));
    }

    private function extractClassName(string $path): ?string
    {
        $contents = @file_get_contents($path);
        if (false === $contents) {
            return null;
        }
        $tokens = token_get_all($contents);
        $namespace = '';
        $className = null;
        $count = count($tokens);
        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];
            if (!is_array($token)) {
                continue;
            }
            if (T_NAMESPACE === $token[0]) {
                $namespace = $this->consumeQualifiedName($tokens, $index + 1);
            }
            if (T_CLASS === $token[0]) {
                $className = $this->consumeClassName($tokens, $index + 1);
                break;
            }
        }
        if (null === $className) {
            return null;
        }
        return '' !== $namespace ? $namespace . '\\' . $className : $className;
    }

    private function consumeQualifiedName(array $tokens, int $offset): string
    {
        $parts = [];
        $count = count($tokens);
        for ($index = $offset; $index < $count; $index++) {
            $token = $tokens[$index];
            if (is_array($token) && in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], true)) {
                $parts[] = $token[1];
                continue;
            }
            if (is_array($token) && T_WHITESPACE === $token[0]) {
                continue;
            }
            break;
        }
        return str_replace('\\\\', '\\', implode('', $parts));
    }

    private function consumeClassName(array $tokens, int $offset): ?string
    {
        $count = count($tokens);
        for ($index = $offset; $index < $count; $index++) {
            $token = $tokens[$index];
            if (is_array($token) && T_STRING === $token[0]) {
                return $token[1];
            }
        }
        return null;
    }
}
