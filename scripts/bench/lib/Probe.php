<?php

declare(strict_types=1);

namespace Psfs\Bench;

/**
 * Sends the same request again and again and checks every response
 */
final class Probe
{
    private \CurlHandle $handle;

    public function __construct(string $url, private readonly string $marker)
    {
        $this->handle = curl_init($url);
        curl_setopt_array($this->handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => ['Accept: text/html,application/json'],
        ]);
    }

    /**
     * @return float milliseconds
     */
    public function request(): float
    {
        $start = hrtime(true);
        $body = curl_exec($this->handle);
        $milliseconds = (hrtime(true) - $start) / 1e6;
        $this->assertResponse((int)curl_getinfo($this->handle, CURLINFO_RESPONSE_CODE), $body);
        return $milliseconds;
    }

    /**
     * @return float[] milliseconds of every request, in ascending order
     */
    public function sample(int $requests): array
    {
        $times = [];
        for ($i = 0; $i < $requests; $i++) {
            $times[] = $this->request();
        }
        sort($times);
        return $times;
    }

    private function assertResponse(int $status, string|bool $body): void
    {
        $found = is_string($body) && str_contains($body, $this->marker);
        if (200 !== $status || !$found) {
            throw new \RuntimeException(sprintf('unexpected response (HTTP %d, marker "%s" %s)', $status, $this->marker, $found ? 'found' : 'missing'));
        }
    }
}
