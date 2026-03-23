<?php

namespace PSFS\base\queue;

use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;

class ParallelQueueRunner
{
    public function run(
        string $queueName,
        int $workers,
        int $maxJobs,
        int $idleSleepUs,
        bool $stopWhenEmpty,
        ?OutputInterface $output = null
    ): int {
        if ($workers < 1) {
            throw new RuntimeException('Workers must be >= 1');
        }
        if (!$this->canSpawnProcesses()) {
            throw new RuntimeException('proc_open is required to run queue workers in parallel');
        }
        $processes = [];
        for ($index = 1; $index <= $workers; $index++) {
            $command = $this->buildWorkerCommand($queueName, $maxJobs, $idleSleepUs, $stopWhenEmpty);
            $descriptor = $this->createProcessDescriptor();
            $pipes = [];
            $process = $this->openProcess($command, $descriptor, $pipes, BASE_DIR);
            if (!is_resource($process)) {
                throw new RuntimeException(sprintf('Unable to spawn worker %d', $index));
            }
            $this->closeStream($pipes[0] ?? null);
            $this->setStreamBlocking($pipes[1] ?? null, false);
            $this->setStreamBlocking($pipes[2] ?? null, false);
            $processes[] = [
                'index' => $index,
                'process' => $process,
                'stdout' => $pipes[1],
                'stderr' => $pipes[2],
            ];
            if (null !== $output) {
                $output->writeln(sprintf('[queue] spawned worker %d for %s', $index, $queueName));
            }
        }

        $exitCode = 0;
        do {
            $running = false;
            foreach ($processes as $key => $processData) {
                $this->flushStream($processData['stdout'], sprintf('[worker:%d]', $processData['index']), $output);
                $this->flushStream($processData['stderr'], sprintf('[worker:%d:err]', $processData['index']), $output);
                $status = $this->getProcessStatus($processData['process']);
                if ($status['running']) {
                    $running = true;
                    continue;
                }
                $exitCode = max($exitCode, $this->closeProcess($processData['process']));
                $this->closeStream($processData['stdout']);
                $this->closeStream($processData['stderr']);
                unset($processes[$key]);
            }
            if ($running) {
                $this->sleep(100000);
            }
        } while ($running || [] !== $processes);

        return $exitCode;
    }

    public function buildWorkerCommand(string $queueName, int $maxJobs, int $idleSleepUs, bool $stopWhenEmpty): string
    {
        $parts = [
            escapeshellarg(PHP_BINARY),
            escapeshellarg(SOURCE_DIR . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'psfs'),
            'psfs:queue:work',
            '--queue=' . escapeshellarg($queueName),
            '--max-jobs=' . escapeshellarg((string)$maxJobs),
            '--idle-sleep=' . escapeshellarg((string)$idleSleepUs),
            '--stop-when-empty=' . escapeshellarg($stopWhenEmpty ? '1' : '0'),
        ];
        return implode(' ', $parts);
    }

    private function flushStream($stream, string $prefix, ?OutputInterface $output): void
    {
        if (null === $output || !is_resource($stream)) {
            return;
        }
        $chunk = $this->readStream($stream);
        if (false === $chunk || '' === $chunk) {
            return;
        }
        foreach (preg_split('/\R/', trim($chunk)) as $line) {
            if ('' !== $line) {
                $output->writeln($prefix . ' ' . $line);
            }
        }
    }

    protected function canSpawnProcesses(): bool
    {
        return function_exists('proc_open');
    }

    protected function createProcessDescriptor(): array
    {
        return [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
    }

    protected function openProcess(string $command, array $descriptor, array &$pipes, string $cwd)
    {
        return proc_open($command, $descriptor, $pipes, $cwd);
    }

    protected function setStreamBlocking($stream, bool $blocking): void
    {
        if (is_resource($stream)) {
            stream_set_blocking($stream, $blocking);
        }
    }

    protected function readStream($stream)
    {
        return stream_get_contents($stream);
    }

    protected function getProcessStatus($process): array
    {
        return proc_get_status($process);
    }

    protected function closeProcess($process): int
    {
        return proc_close($process);
    }

    protected function sleep(int $microseconds): void
    {
        usleep($microseconds);
    }

    protected function closeStream($stream): void
    {
        if (is_resource($stream)) {
            fclose($stream);
        }
    }
}
