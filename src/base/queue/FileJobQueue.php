<?php

namespace PSFS\base\queue;

use PSFS\base\config\Config;
use PSFS\base\types\helpers\FileHelper;

class FileJobQueue implements JobQueueInterface
{
    private string $basePath;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = rtrim((string)($basePath ?: Config::getParam('job.queue.file.path', CACHE_DIR . DIRECTORY_SEPARATOR . 'queue')), DIRECTORY_SEPARATOR);
    }

    public function enqueue(string $queue, array $payload): bool
    {
        $queueFile = $this->queueFile($queue);
        $lockPath = $queueFile . '.lock';
        $line = json_encode($payload);
        if (false === $line) {
            return false;
        }
        $line .= PHP_EOL;

        return true === FileHelper::withExclusiveLock($lockPath, function () use ($queueFile, $line) {
            $dir = dirname($queueFile);
            if (!is_dir($dir) && @mkdir($dir, 0775, true) === false) {
                return false;
            }
            $handle = @fopen($queueFile, 'ab');
            if (false === $handle) {
                return false;
            }
            try {
                return false !== @fwrite($handle, $line);
            } finally {
                @fclose($handle);
            }
        });
    }

    public function dequeue(string $queue): ?array
    {
        $queueFile = $this->queueFile($queue);
        if (!file_exists($queueFile)) {
            return null;
        }
        $lockPath = $queueFile . '.lock';

        return FileHelper::withExclusiveLock($lockPath, function () use ($queueFile) {
            $input = @fopen($queueFile, 'rb');
            if (false === $input) {
                return null;
            }
            $tmpPath = $this->createTempQueuePath($queueFile);
            $output = @fopen($tmpPath, 'wb');
            if (false === $output) {
                @fclose($input);
                return $this->dequeueUsingFullRead($queueFile);
            }

            $first = null;
            $hasRemaining = false;
            try {
                while (($line = fgets($input)) !== false) {
                    $line = trim($line);
                    if ('' === $line) {
                        continue;
                    }
                    if (null === $first) {
                        $first = $line;
                        continue;
                    }
                    if (false === @fwrite($output, $line . PHP_EOL)) {
                        return null;
                    }
                    $hasRemaining = true;
                }
            } finally {
                @fclose($input);
                @fclose($output);
            }

            if (null === $first) {
                @unlink($tmpPath);
                return null;
            }

            if ($hasRemaining) {
                if (!@rename($tmpPath, $queueFile)) {
                    $remaining = @file_get_contents($tmpPath);
                    @unlink($tmpPath);
                    if (false === $remaining || !FileHelper::writeFileAtomic($queueFile, $remaining)) {
                        return null;
                    }
                }
            } else {
                FileHelper::deleteFile($queueFile);
                @unlink($tmpPath);
            }

            $decoded = json_decode($first, true);
            return is_array($decoded) ? $decoded : null;
        });
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function size(string $queue): int
    {
        $queueFile = $this->queueFile($queue);
        if (!file_exists($queueFile)) {
            return 0;
        }
        $handle = @fopen($queueFile, 'rb');
        if (false === $handle) {
            return 0;
        }
        $count = 0;
        try {
            while (($line = fgets($handle)) !== false) {
                if ('' !== trim($line)) {
                    $count++;
                }
            }
        } finally {
            @fclose($handle);
        }
        return $count;
    }

    private function queueFile(string $queue): string
    {
        $slug = trim((string)preg_replace('/[^a-z0-9\-_]+/i', '-', strtolower($queue)), '-');
        if ('' === $slug) {
            $slug = 'default';
        }
        return $this->basePath . DIRECTORY_SEPARATOR . $slug . '-' . sha1($queue) . '.queue';
    }

    private function dequeueUsingFullRead(string $queueFile): ?array
    {
        $lines = @file($queueFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (false === $lines || [] === $lines) {
            return null;
        }
        $raw = array_shift($lines);
        $rewritten = [] === $lines ? '' : implode(PHP_EOL, $lines) . PHP_EOL;
        if (!FileHelper::writeFileAtomic($queueFile, $rewritten)) {
            return null;
        }
        if ('' === $rewritten) {
            FileHelper::deleteFile($queueFile);
        }
        $decoded = json_decode((string)$raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function createTempQueuePath(string $queueFile): string
    {
        $random = uniqid('tmp_', true);
        return $queueFile . '.' . $random;
    }
}
