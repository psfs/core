<?php

declare(strict_types=1);

namespace Psfs\Bench;

/**
 * The benchmark run: connects to the services, builds the sandbox, measures every cell and prints the report.
 * Whatever happens, the server, the database, the Redis keys and the sandbox are cleaned up at the end.
 */
final class Application
{
    private ?Sandbox $sandbox = null;
    private ?Server $server = null;
    private ?Services $services = null;
    private bool $cleaned = false;

    /**
     * @param string[] $arguments command line arguments without the script name
     */
    public function __construct(private readonly array $arguments)
    {
    }

    public function run(): int
    {
        try {
            $options = Options::fromArguments($this->arguments);
        } catch (\InvalidArgumentException $exception) {
            fwrite(STDERR, $exception->getMessage() . "\n");
            return 2;
        }
        if ($options->has('help')) {
            echo Options::usage();
            return 0;
        }
        $this->registerCleanup($options);
        try {
            $this->execute($options);
        } catch (\Throwable $exception) {
            fwrite(STDERR, "\nBENCH FAILED: " . $exception->getMessage() . "\n");
            return 1;
        }
        return 0;
    }

    private function execute(Options $options): void
    {
        $this->banner($options);
        $this->services = Services::connect($options);
        $this->sandbox = new Sandbox(dirname(__DIR__, 3), $options);
        $this->sandbox->build();
        $this->server = new Server($this->sandbox->path());
        $results = $this->measureAll($options, new Matrix($options));
        (new Reporter($options))->print($results);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function measureAll(Options $options, Matrix $matrix): array
    {
        $runner = new CellRunner($options, $this->sandbox, $this->server, $this->services);
        $dataset = $this->services->pdo() instanceof \PDO ? new Dataset($this->services->pdo(), $this->sandbox->database()) : null;
        $total = $matrix->total();
        $results = [];
        foreach ($matrix->cells() as $index => $cell) {
            fprintf(STDERR, "[%d/%d] %s modules=%d rows=%d opcache=%s debug=%s redis=%s ... ", $index + 1, $total, $cell['scenario'], $cell['modules'], $cell['rows'], $cell['opcache'], $cell['debug'] ? 'true' : 'false', $cell['redis']);
            $this->prepare($cell, $dataset);
            $results[] = $this->measureCell($runner, $cell);
        }
        return $results;
    }

    /**
     * @param array<string,mixed> $cell
     */
    private function prepare(array $cell, ?Dataset $dataset): void
    {
        $this->sandbox->useModules($cell['modules']);
        if (Scenarios::isApi($cell['scenario'])) {
            $dataset?->useRows($cell['rows']);
        }
    }

    /**
     * @param array<string,mixed> $cell
     * @return array<string,mixed>
     */
    private function measureCell(CellRunner $runner, array $cell): array
    {
        try {
            $cell += $runner->measure($cell);
            fprintf(STDERR, "p50 %.2f ms\n", $cell['p50']);
        } catch (\Throwable $exception) {
            $cell['error'] = $exception->getMessage();
            fprintf(STDERR, "FAILED: %s\n", $exception->getMessage());
        }
        return $cell;
    }

    private function banner(Options $options): void
    {
        fprintf(STDERR, "PSFS benchmark | PHP %s | %s %s\n", PHP_VERSION, php_uname('s'), php_uname('r'));
        fprintf(
            STDERR,
            "scenarios=%s modules=%s rows=%s opcache=%s debug=%s redis=%s iterations=%s\n",
            implode(',', $options->list('scenarios')), implode(',', $options->list('modules')), implode(',', $options->list('rows')),
            implode(',', $options->list('opcache')), implode(',', $options->list('debug')), implode(',', $options->list('redis')),
            $options->text('iterations')
        );
    }

    private function registerCleanup(Options $options): void
    {
        register_shutdown_function(fn() => $this->cleanup($options));
        if (!function_exists('pcntl_async_signals')) {
            return;
        }
        pcntl_async_signals(true);
        foreach ([SIGINT, SIGTERM] as $signal) {
            pcntl_signal($signal, function () use ($options): void {
                $this->cleanup($options);
                exit(130);
            });
        }
    }

    private function cleanup(Options $options): void
    {
        if ($this->cleaned) {
            return;
        }
        $this->cleaned = true;
        $this->server?->stop();
        if ($options->has('keep')) {
            fprintf(STDERR, "kept sandbox %s and database %s\n", $this->sandbox?->path(), $this->sandbox?->database());
            return;
        }
        $this->removeServiceData();
        $this->sandbox?->remove();
    }

    private function removeServiceData(): void
    {
        try {
            $database = (string)$this->sandbox?->database();
            if ('' !== $database) {
                $this->services?->dropDatabase($database);
            }
            $this->services?->removeCreatedKeys();
        } catch (\Throwable $exception) {
            fwrite(STDERR, 'cleanup warning: ' . $exception->getMessage() . "\n");
        }
    }
}
