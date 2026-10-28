<?php

declare(strict_types=1);

namespace Psfs\Bench;

/**
 * Command line options: every list option takes comma separated values and the benchmark runs their product
 */
final class Options
{
    private const KNOWN = ['scenarios', 'modules', 'rows', 'opcache', 'debug', 'redis', 'iterations', 'warmup', 'rtt', 'repeat', 'json', 'keep', 'quick', 'help'];
    private const CHOICES = [
        'opcache' => ['off', 'on', 'jit'],
        'redis' => ['off', 'sessions', 'on'],
        'debug' => ['true', 'false'],
    ];

    /**
     * @param array<string,string|bool> $values
     */
    private function __construct(private readonly array $values)
    {
    }

    /**
     * @param string[] $arguments command line arguments without the script name
     */
    public static function fromArguments(array $arguments): self
    {
        $values = self::defaults(in_array('--quick', $arguments, true));
        foreach ($arguments as $argument) {
            if (!str_starts_with($argument, '--')) {
                throw new \InvalidArgumentException('Unexpected argument: ' . $argument);
            }
            [$key, $value] = array_pad(explode('=', substr($argument, 2), 2), 2, true);
            $values[$key] = $value;
        }
        $options = new self($values);
        $options->validate();
        return $options;
    }

    /**
     * @return array<string,string>
     */
    private static function defaults(bool $quick): array
    {
        return [
            'scenarios' => $quick ? 'page,api-get' : 'page,api-get,api-list',
            'modules' => $quick ? '2' : '2,20',
            'rows' => '1000',
            'opcache' => 'off,on',
            'debug' => $quick ? 'false' : 'false,true',
            'redis' => $quick ? 'off' : 'off,on',
            'iterations' => $quick ? '40' : '200',
            'warmup' => $quick ? '10' : '30',
            'rtt' => '1,5',
            'repeat' => '1',
        ];
    }

    /**
     * @return string[]
     */
    public function list(string $key): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string)$this->values[$key])), 'strlen'));
    }

    public function int(string $key): int
    {
        return (int)$this->values[$key];
    }

    public function text(string $key): string
    {
        return (string)($this->values[$key] ?? '');
    }

    public function has(string $flag): bool
    {
        return isset($this->values[$flag]);
    }

    public function needsApi(): bool
    {
        return [] !== array_filter($this->list('scenarios'), [Scenarios::class, 'isApi']);
    }

    public function needsPage(): bool
    {
        return in_array('page', $this->list('scenarios'), true);
    }

    /**
     * @return string[] the modules the scenarios need, without the generated dummies
     */
    public function baseModules(): array
    {
        $modules = array_map(static fn(string $name): string => Scenarios::get($name)['module'], $this->list('scenarios'));
        return array_values(array_unique($modules));
    }

    /**
     * @return array<string,string|bool>
     */
    public function toArray(): array
    {
        return $this->values;
    }

    private function validate(): void
    {
        foreach (array_diff(array_keys($this->values), self::KNOWN) as $unknown) {
            throw new \InvalidArgumentException('Unknown option --' . $unknown . ' (see --help)');
        }
        foreach ($this->list('scenarios') as $scenario) {
            if (!Scenarios::exists($scenario)) {
                throw new \InvalidArgumentException('Unknown scenario ' . $scenario);
            }
        }
        foreach (self::CHOICES as $key => $allowed) {
            $this->validateChoices($key, $allowed);
        }
        $this->validateModules();
    }

    /**
     * @param string[] $allowed
     */
    private function validateChoices(string $key, array $allowed): void
    {
        foreach ($this->list($key) as $value) {
            if (!in_array($value, $allowed, true)) {
                throw new \InvalidArgumentException(sprintf('--%s accepts %s', $key, implode(',', $allowed)));
            }
        }
    }

    private function validateModules(): void
    {
        $minimum = count($this->baseModules());
        foreach ($this->list('modules') as $count) {
            if ((int)$count < $minimum) {
                throw new \InvalidArgumentException(sprintf('--modules needs at least %d for these scenarios', $minimum));
            }
        }
    }

    public static function usage(): string
    {
        return <<<'TXT'
PSFS request benchmark (run through scripts/bench/run.sh)

Every option takes a comma separated list; the benchmark runs the cartesian product.

  --scenarios=page,api-get,api-list   page: Twig page of a public module | api-get: /client/api/test/1 | api-list: paginated list
  --modules=2,20       total modules loaded on every request (the scenario modules plus generated dummies)
  --rows=1000          rows in the API table (affects api-list: COUNT, joins, pagination)
  --opcache=off,on     off | on (validate_timestamps=0, JIT off) | jit (tracing, as in docker/php.ini)
  --debug=false,true   PSFS debug flag
  --redis=off,on       off: file sessions, psfs.redis=false | sessions: Redis sessions only | on: Redis sessions and psfs.redis=true
  --iterations=200     measured requests per cell        --warmup=30   requests discarded before measuring
  --rtt=1,5            network round trip (ms) used to estimate remote DB/Redis cost
  --repeat=3           measure every cell N times with a fresh server and report the median (and the p50 range)
  --json=PATH          also write the raw results as JSON
  --keep               keep the sandbox and its database for inspection
  --quick              small matrix for a smoke test

TXT;
    }
}
