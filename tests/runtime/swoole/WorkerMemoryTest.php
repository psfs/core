<?php

namespace PSFS\tests\runtime\swoole;

use PHPUnit\Framework\TestCase;
use PSFS\Dispatcher;
use PSFS\runtime\swoole\SwooleRuntimeStateManager;

/**
 * A Swoole worker serves thousands of requests in the same process, and builds the Dispatcher again for each one:
 * nothing of a request may stay referenced by the process once it is over.
 */
class WorkerMemoryTest extends TestCase
{
    protected function tearDown(): void
    {
        // the Dispatcher binds the error handler of the process once
        restore_error_handler();
    }

    public function testTheMemoryOfAWorkerStaysFlatAcrossRequests(): void
    {
        $state = new SwooleRuntimeStateManager();
        $this->serveRequests($state, 30);
        gc_collect_cycles();
        $before = memory_get_usage();

        $this->serveRequests($state, 150);
        gc_collect_cycles();
        $grown = memory_get_usage() - $before;

        $this->assertLessThan(1048576, $grown, sprintf('%d bytes retained by 150 requests', $grown));
    }

    private function serveRequests(SwooleRuntimeStateManager $state, int $requests): void
    {
        for ($request = 0; $request < $requests; $request++) {
            $state->resetBeforeRequest();
            Dispatcher::getInstance();
            $state->cleanupAfterRequest('worker-memory-test');
        }
    }
}
