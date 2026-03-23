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
        $lines = @file($queueFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return false === $lines ? 0 : count($lines);
    }

    private function queueFile(string $queue): string
    {
        $slug = trim((string)preg_replace('/[^a-z0-9\-_]+/i', '-', strtolower($queue)), '-');
        if ('' === $slug) {
            $slug = 'default';
        }
        return $this->basePath . DIRECTORY_SEPARATOR . $slug . '-' . sha1($queue) . '.queue';
    }
}
