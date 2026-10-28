<?php
/**
 * PSFS request benchmark.
 *
 * Builds an isolated copy of the project under /tmp, serves it with `php -S` and measures real HTTP
 * requests for every combination of opcache, debug, number of modules, Redis and table size.
 * Nothing in the repository is modified: module generation, config, cache, logs and the MySQL
 * database all live in the sandbox and are removed at the end.
 *
 * Run it through scripts/bench/run.sh (it executes inside the php container). See scripts/bench/README.md.
 */
declare(strict_types=1);

const SCENARIOS = [
    'page' => ['module' => 'BENCHPAGE', 'path' => '/BENCHPAGE', 'marker' => 'BENCH-PAGE-OK'],
    'api-get' => ['module' => 'CLIENT', 'path' => '/client/api/test/1', 'marker' => '"success":true'],
    'api-list' => ['module' => 'CLIENT', 'path' => '/client/api/test?__limit=20&__page=1', 'marker' => '"success":true'],
];

exit((new Bench($argv))->run());

final class Bench
{
    private array $opts;
    private string $root;
    private string $sandbox = '';
    private string $dbName = '';
    private array $redisKeysBefore = [];
    private ?\Redis $redis = null;
    private ?\PDO $pdo = null;
    /** @var resource|null */
    private $server = null;
    private array $results = [];
    private int $currentModules = -1;

    public function __construct(array $argv)
    {
        $this->root = dirname(__DIR__, 2);
        $this->opts = $this->parseArgs(array_slice($argv, 1));
    }

    public function run(): int
    {
        if (isset($this->opts['help'])) {
            $this->usage();
            return 0;
        }
        $this->validate();
        register_shutdown_function(fn() => $this->cleanup());
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            foreach ([SIGINT, SIGTERM] as $signal) {
                pcntl_signal($signal, function () {
                    $this->cleanup();
                    exit(130);
                });
            }
        }
        try {
            $this->banner();
            $this->connectServices();
            $this->buildSandbox();
            $this->runMatrix();
            $this->report();
        } catch (\Throwable $e) {
            fwrite(STDERR, "\nBENCH FAILED: " . $e->getMessage() . "\n");
            return 1;
        }
        return 0;
    }

    // ------------------------------------------------------------------ options

    private function parseArgs(array $args): array
    {
        $quick = in_array('--quick', $args, true);
        $opts = [
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
        foreach ($args as $arg) {
            if (!str_starts_with($arg, '--')) {
                throw new \InvalidArgumentException('Unexpected argument: ' . $arg);
            }
            [$key, $value] = array_pad(explode('=', substr($arg, 2), 2), 2, true);
            $opts[$key] = $value;
        }
        return $opts;
    }

    private function list(string $key): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string)$this->opts[$key])), 'strlen'));
    }

    private function validate(): void
    {
        $known = ['scenarios', 'modules', 'rows', 'opcache', 'debug', 'redis', 'iterations', 'warmup', 'rtt', 'repeat', 'json', 'keep', 'quick', 'help'];
        foreach (array_keys($this->opts) as $key) {
            if (!in_array($key, $known, true)) {
                throw new \InvalidArgumentException('Unknown option --' . $key . ' (see --help)');
            }
        }
        foreach ($this->list('scenarios') as $scenario) {
            if (!isset(SCENARIOS[$scenario])) {
                throw new \InvalidArgumentException('Unknown scenario ' . $scenario);
            }
        }
        foreach ($this->list('opcache') as $mode) {
            if (!in_array($mode, ['off', 'on', 'jit'], true)) {
                throw new \InvalidArgumentException('--opcache accepts off,on,jit');
            }
        }
        foreach ($this->list('redis') as $mode) {
            if (!in_array($mode, ['off', 'sessions', 'on'], true)) {
                throw new \InvalidArgumentException('--redis accepts off,sessions,on');
            }
        }
        foreach ($this->list('debug') as $mode) {
            if (!in_array($mode, ['true', 'false'], true)) {
                throw new \InvalidArgumentException('--debug accepts true,false');
            }
        }
        $base = $this->baseModules();
        foreach ($this->list('modules') as $count) {
            if ((int)$count < count($base)) {
                throw new \InvalidArgumentException(sprintf('--modules needs at least %d for these scenarios', count($base)));
            }
        }
    }

    private function usage(): void
    {
        echo <<<TXT
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

    // ------------------------------------------------------------------ services

    private function banner(): void
    {
        fprintf(STDERR, "PSFS benchmark | PHP %s | %s\n", PHP_VERSION, php_uname('s') . ' ' . php_uname('r'));
        fprintf(STDERR, "scenarios=%s modules=%s rows=%s opcache=%s debug=%s redis=%s iterations=%s\n",
            implode(',', $this->list('scenarios')), implode(',', $this->list('modules')), implode(',', $this->list('rows')),
            implode(',', $this->list('opcache')), implode(',', $this->list('debug')), implode(',', $this->list('redis')),
            $this->opts['iterations']);
    }

    private function connectServices(): void
    {
        $host = getenv('PSFS_REDIS_HOST') ?: 'redis';
        if (class_exists(\Redis::class)) {
            $redis = new \Redis();
            if (@$redis->connect($host, 6379, 1.0)) {
                $this->redis = $redis;
                $this->redisKeysBefore = array_flip((array)$redis->keys('*'));
            }
        }
        if (null === $this->redis && array_diff($this->list('redis'), ['off'])) {
            throw new \RuntimeException('Redis is not reachable at ' . $host . ':6379 but --redis=sessions/on was requested');
        }
        if ($this->needsApi()) {
            $this->pdo = new \PDO(
                sprintf('mysql:host=%s;port=3306;charset=utf8mb4', getenv('API_DB_HOST') ?: 'db'),
                getenv('API_DB_USER') ?: 'root',
                getenv('API_DB_PASSWORD') ?: 'psfs',
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_EMULATE_PREPARES => true, 1000 => true] // 1000: MYSQL_ATTR_USE_BUFFERED_QUERY
            );
        }
    }

    private function needsApi(): bool
    {
        foreach ($this->list('scenarios') as $scenario) {
            if (str_starts_with($scenario, 'api')) {
                return true;
            }
        }
        return false;
    }

    private function needsPage(): bool
    {
        return in_array('page', $this->list('scenarios'), true);
    }

    /**
     * @return string[]
     */
    private function baseModules(): array
    {
        return array_values(array_unique(array_map(fn(string $s) => SCENARIOS[$s]['module'], $this->list('scenarios'))));
    }

    // ------------------------------------------------------------------ sandbox

    private function buildSandbox(): void
    {
        $this->sandbox = '/tmp/psfs-bench-' . bin2hex(random_bytes(4));
        $s = $this->sandbox;
        fprintf(STDERR, "building sandbox %s ...\n", $s);
        foreach (['modules', 'parked', 'config', 'cache', 'logs', 'sessions'] as $dir) {
            mkdir($s . '/' . $dir, 0775, true);
        }
        $this->sh(sprintf('cd %s && tar -cf - --exclude=src/public src composer.json | tar -xf - -C %s', escapeshellarg($this->root), escapeshellarg($s)));
        $this->sh(sprintf('cp -a %s %s', escapeshellarg($this->root . '/vendor'), escapeshellarg($s . '/vendor')));
        if ($this->needsApi()) {
            foreach (['tests_api/support', 'tests/examples/generator', 'tests/fixtures/sql'] as $dir) {
                mkdir($s . '/' . dirname($dir), 0775, true);
                $this->sh(sprintf('cp -a %s %s', escapeshellarg($this->root . '/' . $dir), escapeshellarg($s . '/' . $dir)));
            }
        }
        // Without every required key PSFS stays in installer mode and answers each request with the admin setup
        file_put_contents($s . '/config/config.json', json_encode([
            'debug' => false, 'cache.var' => 'bench', 'platform.name' => 'PSFSBench',
            'db.host' => 'db', 'db.port' => '3306', 'db.name' => 'psfs', 'db.user' => 'psfs', 'db.password' => 'psfs',
            'home.action' => 'admin', 'default.language' => 'en_US',
        ]));
        file_put_contents($s . '/bench-prepend.php', self::PREPEND);
        file_put_contents($s . '/prep.php', self::PREP);

        $maxModules = max(array_map('intval', $this->list('modules')));
        $dummies = $maxModules - count($this->baseModules());
        $out = $this->sh(sprintf(
            'cd %s && php -d xdebug.mode=off prep.php %d %d %d',
            escapeshellarg($s),
            $this->needsPage() ? 1 : 0,
            $this->needsApi() ? 1 : 0,
            $dummies
        ));
        $info = json_decode(trim((string)(explode("\n", trim($out))[count(explode("\n", trim($out))) - 1])), true);
        $this->dbName = (string)($info['db'] ?? '');
        if ($this->needsApi() && '' === $this->dbName) {
            throw new \RuntimeException("Sandbox preparation did not report a database:\n" . $out);
        }
        fprintf(STDERR, "sandbox ready (%d generated dummy modules%s)\n", $dummies, $this->dbName ? ', db ' . $this->dbName : '');
    }

    private function useModules(int $total): void
    {
        if ($this->currentModules === $total) {
            return;
        }
        $wanted = $total - count($this->baseModules());
        $dummies = glob($this->sandbox . '/{modules,parked}/BENCHDUMMY*', GLOB_BRACE | GLOB_ONLYDIR) ?: [];
        sort($dummies, SORT_NATURAL);
        foreach ($dummies as $index => $dir) {
            $name = basename($dir);
            $target = ($index < $wanted ? $this->sandbox . '/modules/' : $this->sandbox . '/parked/') . $name;
            if ($dir !== $target) {
                rename($dir, $target);
            }
        }
        $this->deployRoutes();
        $this->currentModules = $total;
    }

    private function deployRoutes(): void
    {
        $this->sh(sprintf('cd %s && php -d xdebug.mode=off src/bin/psfs psfs:deploy:project', escapeshellarg($this->sandbox)));
    }

    private function useRows(int $rows): void
    {
        if (null === $this->pdo || '' === $this->dbName) {
            return;
        }
        $db = '`' . $this->dbName . '`';
        $current = (int)$this->pdo->query("SELECT COALESCE(MAX(ID), 0) FROM $db.CLIENT_TEST")->fetchAll(\PDO::FETCH_COLUMN)[0];
        if ($current >= $rows) {
            $this->pdo->exec("DELETE FROM $db.CLIENT_TEST_i18n WHERE ID > $rows");
            $this->pdo->exec("DELETE FROM $db.CLIENT_TEST WHERE ID > $rows");
            return;
        }
        $this->pdo->exec('SET SESSION cte_max_recursion_depth = ' . ($rows + 10));
        $this->pdo->exec("INSERT INTO $db.CLIENT_TEST (NUMBER, SUMMARY, TYPE, CHECKER, ID_RELATED, created_at, updated_at)
            WITH RECURSIVE seq(n) AS (SELECT " . ($current + 1) . " UNION ALL SELECT n + 1 FROM seq WHERE n < $rows)
            SELECT n, CONCAT('Bench row ', n), 1, n % 2, 1, NOW(), NOW() FROM seq");
        $template = $this->pdo->query("SELECT * FROM $db.CLIENT_TEST_i18n ORDER BY ID LIMIT 1")->fetchAll(\PDO::FETCH_ASSOC)[0] ?? null;
        if ($template) {
            $columns = array_keys($template);
            $select = array_map(fn($c) => 'ID' === $c ? 't.ID' : (null === $template[$c] ? 'NULL' : $this->pdo->quote((string)$template[$c])), $columns);
            $this->pdo->exec(sprintf(
                "INSERT INTO $db.CLIENT_TEST_i18n (%s) SELECT %s FROM $db.CLIENT_TEST t WHERE t.ID > %d",
                implode(',', array_map(fn($c) => "`$c`", $columns)),
                implode(',', $select),
                $current
            ));
        }
        // ANALYZE returns a result set: it has to be consumed or the connection stays busy
        $this->pdo->query("ANALYZE TABLE $db.CLIENT_TEST, $db.CLIENT_TEST_i18n")->fetchAll();
    }

    private function writeConfig(array $overrides): void
    {
        $file = $this->sandbox . '/config/config.json';
        $config = json_decode((string)file_get_contents($file), true) ?: [];
        file_put_contents($file, json_encode(array_merge($config, $overrides), JSON_PRETTY_PRINT));
    }

    // ------------------------------------------------------------------ matrix

    private function runMatrix(): void
    {
        $cells = 0;
        $total = count($this->list('scenarios')) * count($this->list('modules')) * count($this->list('rows')) * count($this->list('opcache')) * count($this->list('debug')) * count($this->list('redis'));
        foreach ($this->list('scenarios') as $scenario) {
            $rowsList = str_starts_with($scenario, 'api') ? $this->list('rows') : [$this->list('rows')[0]];
            foreach ($this->list('modules') as $modules) {
                $this->useModules((int)$modules);
                foreach ($rowsList as $rows) {
                    if (str_starts_with($scenario, 'api')) {
                        $this->useRows((int)$rows);
                    }
                    foreach ($this->list('debug') as $debug) {
                        foreach ($this->list('redis') as $redis) {
                            foreach ($this->list('opcache') as $opcache) {
                                $cells++;
                                fprintf(STDERR, "[%d/%d] %s modules=%s rows=%s opcache=%s debug=%s redis=%s ... ", $cells, $total, $scenario, $modules, $rows, $opcache, $debug, $redis);
                                $cell = [
                                    'scenario' => $scenario, 'modules' => (int)$modules, 'rows' => (int)$rows,
                                    'opcache' => $opcache, 'debug' => 'true' === $debug, 'redis' => $redis,
                                ];
                                try {
                                    $cell += $this->measureRepeated($cell);
                                    fprintf(STDERR, "p50 %.2f ms\n", $cell['p50']);
                                } catch (\Throwable $e) {
                                    $cell['error'] = $e->getMessage();
                                    fprintf(STDERR, "FAILED: %s\n", $e->getMessage());
                                }
                                $this->results[] = $cell;
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Measures the cell --repeat times (fresh server each time) and keeps the median of every metric
     */
    private function measureRepeated(array $cell): array
    {
        $runs = [];
        for ($i = 0, $n = max(1, (int)$this->opts['repeat']); $i < $n; $i++) {
            $runs[] = $this->measureCell($cell);
        }
        if (1 === count($runs)) {
            return $runs[0];
        }
        $merged = [];
        foreach ($runs[0] as $key => $value) {
            if (is_float($value) || is_int($value)) {
                $values = array_column($runs, $key);
                sort($values);
                $merged[$key] = $values[intdiv(count($values), 2)];
            } else {
                $merged[$key] = $value;
            }
        }
        $p50 = array_column($runs, 'p50');
        $merged['p50_min'] = min($p50);
        $merged['p50_max'] = max($p50);
        foreach ($runs as $run) {
            if (isset($run['warning'])) {
                $merged['warning'] = $run['warning'];
            }
        }
        return $merged;
    }

    private function measureCell(array $cell): array
    {
        $scenario = SCENARIOS[$cell['scenario']];
        $this->writeConfig(['debug' => $cell['debug'], 'psfs.redis' => 'on' === $cell['redis']]);
        // Cold start: empty cache directory, fresh server (so a fresh opcache too)
        $this->sh(sprintf('rm -rf %s/cache/* %s/sessions/*', escapeshellarg($this->sandbox), escapeshellarg($this->sandbox)));
        $statsFile = $this->sandbox . '/stats.tsv';
        file_put_contents($statsFile, '');
        [$port] = $this->startServer($cell, $statsFile);
        try {
            $url = 'http://127.0.0.1:' . $port . $scenario['path'];
            $ch = $this->curl($url);
            $cold = $this->request($ch, $scenario['marker']);
            for ($i = 0; $i < (int)$this->opts['warmup']; $i++) {
                $this->request($ch, $scenario['marker']);
            }
            file_put_contents($statsFile, '');
            $before = $this->counters();
            $times = [];
            for ($i = 0, $n = (int)$this->opts['iterations']; $i < $n; $i++) {
                $times[] = $this->request($ch, $scenario['marker']);
            }
            $after = $this->counters();
            $ch = null;
        } finally {
            $this->stopServer();
        }
        sort($times);
        $n = count($times);
        $stats = $this->readStats($statsFile);
        $perReq = fn(string $k, int $own = 0) => max(0.0, ($after[$k] - $before[$k] - $own) / $n);
        $result = [
            'cold' => $cold,
            'p50' => $this->percentile($times, 50), 'p95' => $this->percentile($times, 95),
            'mean' => array_sum($times) / $n, 'min' => $times[0],
            'php_p50' => $stats['php_p50'], 'peak_mb' => $stats['peak_mb'], 'files' => $stats['files'],
            'opcache_active' => $stats['opcache'],
            'redis_conn' => $perReq('redis_conn'), 'redis_cmd' => $perReq('redis_cmd', 1),
            'db_conn' => $perReq('db_conn'), 'db_q' => $perReq('db_q', 1),
        ];
        if (('off' !== $cell['opcache']) !== $stats['opcache']) {
            $result['warning'] = 'opcache state differs from the requested one';
        }
        return $result;
    }

    private function counters(): array
    {
        $c = ['redis_conn' => 0, 'redis_cmd' => 0, 'db_conn' => 0, 'db_q' => 0];
        if (null !== $this->redis) {
            $info = $this->redis->info('stats');
            $c['redis_conn'] = (int)($info['total_connections_received'] ?? 0);
            $c['redis_cmd'] = (int)($info['total_commands_processed'] ?? 0);
        }
        if (null !== $this->pdo) {
            foreach ($this->pdo->query("SHOW GLOBAL STATUS WHERE Variable_name IN ('Questions','Connections')")->fetchAll(\PDO::FETCH_NUM) as $row) {
                $c['Questions' === $row[0] ? 'db_q' : 'db_conn'] = (int)$row[1];
            }
        }
        return $c;
    }

    // ------------------------------------------------------------------ server and client

    /**
     * @return array{0:int}
     */
    private function startServer(array $cell, string $statsFile): array
    {
        $probe = stream_socket_server('tcp://127.0.0.1:0', $errno, $err);
        $port = (int)explode(':', (string)stream_socket_get_name($probe, false))[1];
        fclose($probe);

        $flags = ['-d', 'xdebug.mode=off', '-d', 'auto_prepend_file=' . $this->sandbox . '/bench-prepend.php'];
        if ('off' === $cell['opcache']) {
            array_push($flags, '-d', 'opcache.enable=0', '-d', 'opcache.enable_cli=0');
        } else {
            array_push($flags, '-d', 'opcache.enable=1', '-d', 'opcache.enable_cli=1', '-d', 'opcache.validate_timestamps=0',
                '-d', 'opcache.memory_consumption=128', '-d', 'opcache.max_accelerated_files=10000');
            if ('jit' === $cell['opcache']) {
                array_push($flags, '-d', 'opcache.jit=tracing', '-d', 'opcache.jit_buffer_size=100M');
            } else {
                array_push($flags, '-d', 'opcache.jit=off');
            }
        }
        if ('off' === $cell['redis']) {
            // 'sessions' and 'on' keep the session handler of docker/php.ini (Redis)
            array_push($flags, '-d', 'session.save_handler=files', '-d', 'session.save_path=' . $this->sandbox . '/sessions');
        }
        $env = array_merge(getenv(), [
            'BENCH_STATS_FILE' => $statsFile,
            'PSFS_REDIS_HOST' => getenv('PSFS_REDIS_HOST') ?: 'redis',
            'PSFS_LOG_OUTPUT' => 'file', 'PSFS_LOG_FORMAT' => 'line', 'PSFS_LOG_LEVEL' => '',
        ]);
        $log = $this->sandbox . '/server.log';
        $this->server = proc_open(
            array_merge([PHP_BINARY], $flags, ['-S', '127.0.0.1:' . $port, '-t', $this->sandbox . '/html']),
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']],
            $pipes,
            $this->sandbox,
            $env
        );
        if (!is_resource($this->server)) {
            throw new \RuntimeException('Unable to start php -S');
        }
        for ($i = 0; $i < 100; $i++) {
            $sock = @fsockopen('127.0.0.1', $port, $errno, $err, 0.1);
            if ($sock) {
                fclose($sock);
                return [$port];
            }
            usleep(50000);
        }
        throw new \RuntimeException('php -S did not start; see ' . $log);
    }

    private function stopServer(): void
    {
        if (is_resource($this->server)) {
            proc_terminate($this->server);
            proc_close($this->server);
        }
        $this->server = null;
    }

    private function curl(string $url)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_HTTPHEADER => ['Accept: text/html,application/json']]);
        return $ch;
    }

    private function request($ch, string $marker): float
    {
        $start = hrtime(true);
        $body = curl_exec($ch);
        $ms = (hrtime(true) - $start) / 1e6;
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if (200 !== $status || !is_string($body) || !str_contains($body, $marker)) {
            throw new \RuntimeException(sprintf('unexpected response (HTTP %d, marker "%s" %s)', $status, $marker, is_string($body) && str_contains($body, $marker) ? 'found' : 'missing'));
        }
        return $ms;
    }

    private function readStats(string $file): array
    {
        $rows = array_filter(explode("\n", (string)file_get_contents($file)), 'strlen');
        if (!$rows) {
            throw new \RuntimeException('the server reported no statistics');
        }
        $peak = $files = $php = [];
        $opcache = false;
        foreach ($rows as $row) {
            [$p, $f, $ms, $oc] = explode("\t", $row);
            $peak[] = (int)$p;
            $files[] = (int)$f;
            $php[] = (float)$ms;
            $opcache = '1' === $oc;
        }
        sort($php);
        sort($peak);
        sort($files);
        return [
            'php_p50' => $this->percentile($php, 50),
            'peak_mb' => $this->percentile($peak, 50) / 1048576,
            'files' => (int)$this->percentile($files, 50),
            'opcache' => $opcache,
        ];
    }

    private function percentile(array $sorted, int $p): float
    {
        $index = (int)ceil(($p / 100) * count($sorted)) - 1;
        return (float)$sorted[max(0, min(count($sorted) - 1, $index))];
    }

    // ------------------------------------------------------------------ output

    private function report(): void
    {
        $rtts = array_map('floatval', $this->list('rtt'));
        echo "\n## PSFS benchmark results\n\n";
        $suffix = (int)$this->opts['repeat'] > 1 ? sprintf(', median of %d runs', (int)$this->opts['repeat']) : '';
        printf("PHP %s, %s requests per cell after %s warm-up%s. Times are wall clock through HTTP (php -S, one process, no keep-alive).\n", PHP_VERSION, $this->opts['iterations'], $this->opts['warmup'], $suffix);
        echo "`php p50` is the time inside PHP (request start to shutdown). `conn`/`cmd`/`q` are per request: Redis connections and commands, MySQL connections and queries.\n";
        printf("`est@N` = p50 + (db q + redis cmd + 2 x (db conn + redis conn)) x N ms: a rough model of the same request with Redis/MySQL N ms away.\n\n");
        $repeated = (int)$this->opts['repeat'] > 1;
        $head = ['scenario', 'mods', 'rows', 'opcache', 'debug', 'redis', 'cold ms', 'p50', ...($repeated ? ['p50 range'] : []), 'p95', 'php p50', 'peak MB', 'files', 'redis conn', 'redis cmd', 'db conn', 'db q'];
        foreach ($rtts as $rtt) {
            $head[] = 'est@' . rtrim(rtrim(number_format($rtt, 1), '0'), '.');
        }
        echo '| ' . implode(' | ', $head) . " |\n|" . str_repeat('---|', count($head)) . "\n";
        foreach ($this->results as $r) {
            if (isset($r['error'])) {
                printf("| %s | %d | %d | %s | %s | %s | FAILED: %s |\n", $r['scenario'], $r['modules'], $r['rows'], $r['opcache'], $r['debug'] ? 'true' : 'false', $r['redis'], $r['error']);
                continue;
            }
            $roundTrips = $r['db_q'] + $r['redis_cmd'] + 2 * ($r['db_conn'] + $r['redis_conn']);
            $row = [
                $r['scenario'], $r['modules'], 'api' === substr($r['scenario'], 0, 3) ? $r['rows'] : '-', $r['opcache'] . (isset($r['warning']) ? '!' : ''),
                $r['debug'] ? 'true' : 'false', $r['redis'], number_format($r['cold'], 1), number_format($r['p50'], 2),
                ...($repeated ? [number_format($r['p50_min'] ?? $r['p50'], 2) . '-' . number_format($r['p50_max'] ?? $r['p50'], 2)] : []),
                number_format($r['p95'], 2),
                number_format($r['php_p50'], 2), number_format($r['peak_mb'], 1), $r['files'],
                number_format($r['redis_conn'], 2), number_format($r['redis_cmd'], 1), number_format($r['db_conn'], 2), number_format($r['db_q'], 1),
            ];
            foreach ($rtts as $rtt) {
                $row[] = number_format($r['p50'] + $roundTrips * $rtt, 1);
            }
            echo '| ' . implode(' | ', $row) . " |\n";
        }
        if (array_filter($this->results, fn($r) => isset($r['warning']))) {
            echo "\n`!` after the opcache value: the server reported a different opcache state than requested; do not trust that row.\n";
        }
        if (isset($this->opts['json'])) {
            file_put_contents((string)$this->opts['json'], json_encode(['php' => PHP_VERSION, 'options' => $this->opts, 'results' => $this->results], JSON_PRETTY_PRINT));
            fprintf(STDERR, "raw results written to %s\n", $this->opts['json']);
        }
    }

    // ------------------------------------------------------------------ cleanup and helpers

    private function cleanup(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $this->stopServer();
        if (isset($this->opts['keep'])) {
            fprintf(STDERR, "kept sandbox %s and database %s\n", $this->sandbox, $this->dbName);
            return;
        }
        try {
            if ($this->pdo && $this->dbName) {
                $this->pdo->exec('DROP DATABASE IF EXISTS `' . $this->dbName . '`');
            }
            if ($this->redis) {
                $created = array_diff((array)$this->redis->keys('*'), array_keys($this->redisKeysBefore));
                foreach (array_chunk($created, 500) as $chunk) {
                    $this->redis->del($chunk);
                }
            }
        } catch (\Throwable $e) {
            fwrite(STDERR, 'cleanup warning: ' . $e->getMessage() . "\n");
        }
        if ('' !== $this->sandbox && str_starts_with($this->sandbox, '/tmp/psfs-bench-')) {
            exec('rm -rf ' . escapeshellarg($this->sandbox));
        }
    }

    private function sh(string $command): string
    {
        exec($command . ' 2>&1', $output, $code);
        $text = implode("\n", $output);
        if (0 !== $code) {
            throw new \RuntimeException("command failed ($code): $command\n$text");
        }
        return $text;
    }

    /** Runs inside the server for every request and records memory, loaded files, PHP time and opcache state */
    private const PREPEND = <<<'PHP'
<?php
register_shutdown_function(static function () {
    $file = getenv('BENCH_STATS_FILE');
    if (!$file) {
        return;
    }
    $opcache = function_exists('opcache_get_status') && ($status = @opcache_get_status(false)) && !empty($status['opcache_enabled']);
    file_put_contents($file, sprintf(
        "%d\t%d\t%.3f\t%d\n",
        memory_get_peak_usage(false),
        count(get_included_files()),
        (microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))) * 1000,
        $opcache ? 1 : 0
    ), FILE_APPEND | LOCK_EX);
});
PHP;

    /** Runs once inside the sandbox: generates the benchmark modules (and the API module and database) */
    private const PREP = <<<'PHP'
<?php
define('PSFS_UNIT_TESTING_EXECUTION', true);
chdir(__DIR__);
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/src/bootstrap.php';

[$script, $page, $api, $dummies] = $argv + [null, 0, 0, 0];
$generator = PSFS\services\GeneratorService::getInstance();

/** Until an admin exists the Dispatcher forces the installer page for every request: create a minimal one */
PSFS\base\Security::save([
    'username' => 'bench',
    'password' => bin2hex(random_bytes(8)),
    'profile' => PSFS\base\types\helpers\AuthHelper::USER_ID_TOKEN,
]);

/** A generated module is private to admins: make it public and give the page a known marker */
function publicize(string $module): void {
    $base = __DIR__ . '/modules/' . $module . '/Controller/base/' . $module . 'BaseController.php';
    $code = file_get_contents($base);
    $code = str_replace(['use PSFS\base\types\AuthAdminController;', 'extends AuthAdminController'], ['use PSFS\base\types\Controller;', 'extends Controller'], $code);
    file_put_contents($base, $code);
    $template = __DIR__ . '/modules/' . $module . '/Templates/index.html.twig';
    file_put_contents($template, str_replace('It works!', 'BENCH-PAGE-OK', file_get_contents($template)));
}

/** Only the API module may own the default datasource, otherwise the last loaded module wins */
function dropDefaultDatasource(string $module): void {
    $config = __DIR__ . '/modules/' . $module . '/Config/config.php';
    file_put_contents($config, preg_replace('/^.*setDefaultDatasource.*\R/m', '', file_get_contents($config)));
}

$db = null;
if ((int)$api) {
    require __DIR__ . '/tests_api/support/ClientModuleHarness.php';
    PSFS\apitests\support\ClientModuleHarness::acquire();
    $db = PSFS\base\config\Config::getParam('db.name');
}
if ((int)$page) {
    $generator->createStructureModule('BENCHPAGE', true, skipMigration: true);
    publicize('BENCHPAGE');
    dropDefaultDatasource('BENCHPAGE');
}
for ($i = 1; $i <= (int)$dummies; $i++) {
    $name = sprintf('BENCHDUMMY%02d', $i);
    $generator->createStructureModule($name, true, skipMigration: true);
    dropDefaultDatasource($name);
}
echo json_encode(['db' => $db]), PHP_EOL;
PHP;
}
