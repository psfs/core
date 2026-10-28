<?php

declare(strict_types=1);

namespace Psfs\Bench;

/**
 * The Redis and MySQL servers the benchmark measures: their server-side counters and the cleanup of what it created
 */
final class Services
{
    /** PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, written as a number because the constant moved between PHP versions */
    private const BUFFERED_QUERY = 1000;

    /** @var array<string,int> keys that existed before the run */
    private array $keysBefore = [];

    private function __construct(private readonly ?\Redis $redis, private readonly ?\PDO $pdo)
    {
    }

    public static function connect(Options $options): self
    {
        $redis = self::connectRedis();
        $needsRedis = [] !== array_diff($options->list('redis'), ['off']);
        if (null === $redis && $needsRedis) {
            throw new \RuntimeException('Redis is not reachable but --redis=sessions/on was requested');
        }
        $services = new self($redis, $options->needsApi() ? self::connectMysql() : null);
        $services->keysBefore = null === $redis ? [] : array_flip((array)$redis->keys('*'));
        return $services;
    }

    public function pdo(): ?\PDO
    {
        return $this->pdo;
    }

    /**
     * Server-side totals: the difference between two snapshots is what the requests in between caused
     *
     * @return array{redis_conn:int,redis_cmd:int,db_conn:int,db_q:int}
     */
    public function counters(): array
    {
        $counters = ['redis_conn' => 0, 'redis_cmd' => 0, 'db_conn' => 0, 'db_q' => 0];
        if (null !== $this->redis) {
            $info = $this->redis->info('stats');
            $counters['redis_conn'] = (int)($info['total_connections_received'] ?? 0);
            $counters['redis_cmd'] = (int)($info['total_commands_processed'] ?? 0);
        }
        if (null !== $this->pdo) {
            foreach ($this->mysqlStatus() as [$name, $value]) {
                $counters['Questions' === $name ? 'db_q' : 'db_conn'] = (int)$value;
            }
        }
        return $counters;
    }

    public function dropDatabase(string $database): void
    {
        $this->pdo?->exec('DROP DATABASE IF EXISTS `' . $database . '`');
    }

    /**
     * Removes the Redis keys (sessions, caches) that were created since the connection was opened
     */
    public function removeCreatedKeys(): void
    {
        if (null === $this->redis) {
            return;
        }
        $created = array_diff((array)$this->redis->keys('*'), array_keys($this->keysBefore));
        foreach (array_chunk($created, 500) as $chunk) {
            $this->redis->del($chunk);
        }
    }

    /**
     * @return array<int,array{0:string,1:string}>
     */
    private function mysqlStatus(): array
    {
        $statement = $this->pdo->query("SHOW GLOBAL STATUS WHERE Variable_name IN ('Questions','Connections')");
        return $statement->fetchAll(\PDO::FETCH_NUM);
    }

    private static function connectRedis(): ?\Redis
    {
        if (!class_exists(\Redis::class)) {
            return null;
        }
        $redis = new \Redis();
        return @$redis->connect(getenv('PSFS_REDIS_HOST') ?: 'redis', 6379, 1.0) ? $redis : null;
    }

    private static function connectMysql(): \PDO
    {
        return new \PDO(
            sprintf('mysql:host=%s;port=3306;charset=utf8mb4', getenv('API_DB_HOST') ?: 'db'),
            getenv('API_DB_USER') ?: 'root',
            getenv('API_DB_PASSWORD') ?: 'psfs',
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_EMULATE_PREPARES => true, self::BUFFERED_QUERY => true]
        );
    }
}
