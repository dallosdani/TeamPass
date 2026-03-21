<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use TeampassWebSocket\RateLimiter;

require_once __DIR__ . '/../Stubs/MockWsConnection.php';

/**
 * Unit tests for TeampassWebSocket\RateLimiter.
 *
 * RateLimiter uses a sliding window algorithm based on microsecond timestamps.
 * Because getCurrentTimeMs() is private, window-expiry tests use ReflectionClass
 * to inject artificially old timestamps into $messageHistory, removing any
 * dependency on real time or sleep() calls.
 */
class RateLimiterTest extends TestCase
{
    private RateLimiter $limiter;

    protected function setUp(): void
    {
        // Default config: 10 messages per 1000 ms
        $this->limiter = new RateLimiter(10, 1000);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Inject timestamps directly into the private $messageHistory array.
     * Allows testing window-expiry logic without relying on sleep().
     *
     * @param array<int, array<int>> $history  [resourceId => [timestamp_ms, ...]]
     */
    private function injectHistory(RateLimiter $limiter, array $history): void
    {
        $ref  = new ReflectionClass($limiter);
        $prop = $ref->getProperty('messageHistory');
        $prop->setAccessible(true);
        $prop->setValue($limiter, $history);
    }

    /**
     * Read the private $messageHistory array for assertions.
     *
     * @return array<int, array<int>>
     */
    private function readHistory(RateLimiter $limiter): array
    {
        $ref  = new ReflectionClass($limiter);
        $prop = $ref->getProperty('messageHistory');
        $prop->setAccessible(true);
        return $prop->getValue($limiter);
    }

    // =========================================================================
    // getConfig
    // =========================================================================

    public function testGetConfigReturnsConfiguredValues(): void
    {
        $limiter = new RateLimiter(5, 500);
        $config  = $limiter->getConfig();

        $this->assertSame(5, $config['max_messages']);
        $this->assertSame(500, $config['window_ms']);
    }

    public function testGetConfigDefaultValues(): void
    {
        $config = $this->limiter->getConfig();

        $this->assertSame(10, $config['max_messages']);
        $this->assertSame(1000, $config['window_ms']);
    }

    // =========================================================================
    // check() — basic allow / deny
    // =========================================================================

    public function testFirstMessageIsAllowed(): void
    {
        $conn = new MockWsConnection(1);

        $this->assertTrue($this->limiter->check($conn));
    }

    public function testMessagesUpToLimitAreAllowed(): void
    {
        $conn    = new MockWsConnection(1);
        $allowed = 0;

        for ($i = 0; $i < 10; $i++) {
            if ($this->limiter->check($conn)) {
                $allowed++;
            }
        }

        $this->assertSame(10, $allowed);
    }

    public function testMessageOverLimitIsDenied(): void
    {
        $conn = new MockWsConnection(1);

        // Consume all 10 slots
        for ($i = 0; $i < 10; $i++) {
            $this->limiter->check($conn);
        }

        // 11th message must be denied
        $this->assertFalse($this->limiter->check($conn));
    }

    public function testDifferentConnectionsHaveIndependentLimits(): void
    {
        $conn1 = new MockWsConnection(1);
        $conn2 = new MockWsConnection(2);

        // Exhaust conn1's limit
        for ($i = 0; $i < 10; $i++) {
            $this->limiter->check($conn1);
        }

        // conn2 should still have its full quota
        $this->assertTrue($this->limiter->check($conn2));
    }

    public function testCheckRecordsMessageInHistory(): void
    {
        $conn = new MockWsConnection(42);
        $this->limiter->check($conn);

        $history = $this->readHistory($this->limiter);

        $this->assertArrayHasKey(42, $history);
        $this->assertCount(1, $history[42]);
    }

    // =========================================================================
    // check() — sliding window expiry (timestamps injected via reflection)
    // =========================================================================

    public function testExpiredMessagesAreNotCountedAgainstLimit(): void
    {
        $conn = new MockWsConnection(1);

        // Inject 10 timestamps that are 2 seconds in the past (outside 1000ms window)
        $twoSecondsAgo = (int) (microtime(true) * 1000) - 2000;
        $this->injectHistory($this->limiter, [
            1 => array_fill(0, 10, $twoSecondsAgo),
        ]);

        // All 10 old entries should be purged; this message should be allowed
        $this->assertTrue($this->limiter->check($conn));
    }

    public function testOnlyMessagesInsideWindowCountTowardLimit(): void
    {
        $conn  = new MockWsConnection(1);
        $now   = (int) (microtime(true) * 1000);

        // 8 old messages (outside window) + 5 recent messages (inside window)
        $old    = array_fill(0, 8, $now - 2000);  // 2 s ago — outside 1 s window
        $recent = array_fill(0, 5, $now - 500);   // 500 ms ago — inside window

        $this->injectHistory($this->limiter, [
            1 => array_merge($old, $recent),
        ]);

        // 5 recent messages in window, limit is 10 → 5 more are allowed
        $allowed = 0;
        for ($i = 0; $i < 5; $i++) {
            if ($this->limiter->check($conn)) {
                $allowed++;
            }
        }

        $this->assertSame(5, $allowed);
    }

    public function testWindowBoundaryMessageIsNotExpired(): void
    {
        // A message at exactly windowStart (now - windowMs) must NOT be kept
        // because the filter is `$ts > $windowStart` (strictly greater).
        $conn    = new MockWsConnection(1);
        $now     = (int) (microtime(true) * 1000);
        $limiter = new RateLimiter(1, 1000); // limit of 1

        // Inject a timestamp exactly at the boundary (not inside window)
        $this->injectHistory($limiter, [
            1 => [$now - 1000], // exactly at windowStart → will be purged
        ]);

        // The boundary message is discarded → slot is free
        $this->assertTrue($limiter->check($conn));
    }

    // =========================================================================
    // getRemaining()
    // =========================================================================

    public function testGetRemainingReturnsMaxForNewConnection(): void
    {
        $conn = new MockWsConnection(99);

        $this->assertSame(10, $this->limiter->getRemaining($conn));
    }

    public function testGetRemainingDecrementsAfterEachCheck(): void
    {
        $conn = new MockWsConnection(1);

        $this->limiter->check($conn);
        $this->limiter->check($conn);

        $this->assertSame(8, $this->limiter->getRemaining($conn));
    }

    public function testGetRemainingReturnsZeroWhenLimitExhausted(): void
    {
        $conn = new MockWsConnection(1);

        for ($i = 0; $i < 10; $i++) {
            $this->limiter->check($conn);
        }

        $this->assertSame(0, $this->limiter->getRemaining($conn));
    }

    public function testGetRemainingNeverGoesBelowZero(): void
    {
        $conn = new MockWsConnection(1);

        // Exhaust the limit and attempt one more (denied)
        for ($i = 0; $i < 11; $i++) {
            $this->limiter->check($conn);
        }

        $this->assertSame(0, $this->limiter->getRemaining($conn));
    }

    public function testGetRemainingIgnoresExpiredMessages(): void
    {
        $conn  = new MockWsConnection(1);
        $now   = (int) (microtime(true) * 1000);

        // 9 expired messages
        $this->injectHistory($this->limiter, [
            1 => array_fill(0, 9, $now - 5000),
        ]);

        // All 9 are outside the window → full quota available
        $this->assertSame(10, $this->limiter->getRemaining($conn));
    }

    // =========================================================================
    // cleanup()
    // =========================================================================

    public function testCleanupRemovesConnectionHistory(): void
    {
        $conn = new MockWsConnection(1);
        $this->limiter->check($conn);

        $this->limiter->cleanup($conn);

        $history = $this->readHistory($this->limiter);
        $this->assertArrayNotHasKey(1, $history);
    }

    public function testCleanupOnNonExistentConnectionDoesNotThrow(): void
    {
        $conn = new MockWsConnection(999);

        // Must not throw even though conn was never registered
        $this->limiter->cleanup($conn);

        $this->addToAssertionCount(1);
    }

    public function testCleanupDoesNotAffectOtherConnections(): void
    {
        $conn1 = new MockWsConnection(1);
        $conn2 = new MockWsConnection(2);

        $this->limiter->check($conn1);
        $this->limiter->check($conn2);

        $this->limiter->cleanup($conn1);

        $history = $this->readHistory($this->limiter);
        $this->assertArrayNotHasKey(1, $history);
        $this->assertArrayHasKey(2, $history);
    }

    // =========================================================================
    // cleanupStale()
    // =========================================================================

    public function testCleanupStaleRemovesInactiveConnections(): void
    {
        $conn1 = new MockWsConnection(1);
        $conn2 = new MockWsConnection(2);
        $conn3 = new MockWsConnection(3);

        $this->limiter->check($conn1);
        $this->limiter->check($conn2);
        $this->limiter->check($conn3);

        // Only conn2 is still active
        $this->limiter->cleanupStale([2]);

        $history = $this->readHistory($this->limiter);
        $this->assertArrayNotHasKey(1, $history);
        $this->assertArrayHasKey(2, $history);
        $this->assertArrayNotHasKey(3, $history);
    }

    public function testCleanupStaleWithEmptyActiveListRemovesAll(): void
    {
        $conn1 = new MockWsConnection(1);
        $conn2 = new MockWsConnection(2);

        $this->limiter->check($conn1);
        $this->limiter->check($conn2);

        $this->limiter->cleanupStale([]);

        $history = $this->readHistory($this->limiter);
        $this->assertEmpty($history);
    }

    public function testCleanupStaleWithAllActivePreservesAll(): void
    {
        $conn1 = new MockWsConnection(1);
        $conn2 = new MockWsConnection(2);

        $this->limiter->check($conn1);
        $this->limiter->check($conn2);

        $this->limiter->cleanupStale([1, 2]);

        $history = $this->readHistory($this->limiter);
        $this->assertArrayHasKey(1, $history);
        $this->assertArrayHasKey(2, $history);
    }

    public function testCleanupStaleOnEmptyHistoryDoesNotThrow(): void
    {
        $this->limiter->cleanupStale([1, 2, 3]);

        $this->addToAssertionCount(1);
    }

    // =========================================================================
    // After cleanup, check() works correctly again
    // =========================================================================

    public function testConnectionIsFullyResetAfterCleanup(): void
    {
        $conn = new MockWsConnection(1);

        // Exhaust the limit
        for ($i = 0; $i < 10; $i++) {
            $this->limiter->check($conn);
        }
        $this->assertFalse($this->limiter->check($conn));

        // Clean up removes the history → quota is fully restored
        $this->limiter->cleanup($conn);

        $this->assertTrue($this->limiter->check($conn));
    }
}
