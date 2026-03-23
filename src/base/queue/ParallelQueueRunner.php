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
        if (!function_exists('proc_open')) {
            throw new RuntimeException('proc_open is required to run queue workers in parallel');
        }
        $processes = [];
        for ($index = 1; $index <= $workers; $index++) {
            $command = $this->buildWorkerCommand($queueName, $maxJobs, $idleSleepUs, $stopWhenEmpty);
            $descriptor = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $process = proc_open($command, $descriptor, $pipes, BASE_DIR);
            if (!is_resource($process)) {
                throw new RuntimeException(sprintf('Unable to spawn worker %d', $index));
            }
            fclose($pipes[0]);
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
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
                $status = proc_get_status($processData['process']);
                if ($status['running']) {
                    $running = true;
                    continue;
                }
                $exitCode = max($exitCode, proc_close($processData['process']));
                fclose($processData['stdout']);
                fclose($processData['stderr']);
                unset($processes[$key]);
            }
            if ($running) {
                usleep(100000);
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
        $chunk = stream_get_contents($stream);
        if (false === $chunk || '' === $chunk) {
            return;
        }
        foreach (preg_split('/\R/', trim($chunk)) as $line) {
            if ('' !== $line) {
                $output->writeln($prefix . ' ' . $line);
            }
        }
    }
}
