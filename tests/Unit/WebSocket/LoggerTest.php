<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use TeampassWebSocket\Logger;

/**
 * Unit tests for TeampassWebSocket\Logger.
 *
 * Each test uses a dedicated temp file so tests are fully isolated.
 * All temp files are deleted in tearDown().
 */
class LoggerTest extends TestCase
{
    /** @var string[] Temp files to delete after each test */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (is_dir($path)) {
                rmdir($path);
            } elseif (file_exists($path)) {
                unlink($path);
            }
        }
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeTempLogFile(): string
    {
        $path = sys_get_temp_dir() . '/teampass_logger_test_' . uniqid() . '.log';
        $this->tempFiles[] = $path;
        return $path;
    }

    private function readLog(string $file): string
    {
        return file_exists($file) ? (string) file_get_contents($file) : '';
    }

    // =========================================================================
    // Constructor / getLogFile / getLevel
    // =========================================================================

    public function testGetLogFileReturnsConfiguredPath(): void
    {
        $path   = $this->makeTempLogFile();
        $logger = new Logger($path);

        $this->assertSame($path, $logger->getLogFile());
    }

    public function testDefaultMinLevelIsInfo(): void
    {
        $logger = new Logger($this->makeTempLogFile());

        $this->assertSame('info', $logger->getLevel());
    }

    public function testCustomMinLevelIsApplied(): void
    {
        $logger = new Logger($this->makeTempLogFile(), 'debug');

        $this->assertSame('debug', $logger->getLevel());
    }

    public function testConstructorCreatesLogDirectoryIfAbsent(): void
    {
        $dir    = sys_get_temp_dir() . '/teampass_test_dir_' . uniqid();
        $path   = $dir . '/sub/app.log';
        $this->tempFiles[] = $path;
        $this->tempFiles[] = $dir . '/sub';
        $this->tempFiles[] = $dir;

        new Logger($path, 'info');

        $this->assertDirectoryExists($dir . '/sub');
    }

    // =========================================================================
    // setLevel
    // =========================================================================

    public function testSetLevelUpdatesMinLevel(): void
    {
        $logger = new Logger($this->makeTempLogFile(), 'info');
        $logger->setLevel('error');

        $this->assertSame('error', $logger->getLevel());
    }

    public function testSetLevelIgnoresUnknownLevel(): void
    {
        $logger = new Logger($this->makeTempLogFile(), 'info');
        $logger->setLevel('verbose'); // unknown

        $this->assertSame('info', $logger->getLevel());
    }

    // =========================================================================
    // Log entry format
    // =========================================================================

    public function testInfoEntryContainsLevelTag(): void
    {
        $path   = $this->makeTempLogFile();
        $logger = new Logger($path, 'info');
        $logger->info('hello world');

        $this->assertStringContainsString('[INFO]', $this->readLog($path));
    }

    public function testInfoEntryContainsMessage(): void
    {
        $path   = $this->makeTempLogFile();
        $logger = new Logger($path, 'info');
        $logger->info('my test message');

        $this->assertStringContainsString('my test message', $this->readLog($path));
    }

    public function testEntryContainsTimestamp(): void
    {
        $path   = $this->makeTempLogFile();
        $logger = new Logger($path, 'info');
        $logger->info('ts check');

        // Timestamp format: [YYYY-MM-DD HH:MM:SS]
        $this->assertMatchesRegularExpression('/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]/', $this->readLog($path));
    }

    public function testEntryEndsWithNewline(): void
    {
        $path   = $this->makeTempLogFile();
        $logger = new Logger($path, 'info');
        $logger->info('newline check');

        $this->assertStringEndsWith(PHP_EOL, $this->readLog($path));
    }

    public function testContextIsAppendedAsJson(): void
    {
        $path   = $this->makeTempLogFile();
        $logger = new Logger($path, 'info');
        $logger->info('with context', ['user_id' => 42, 'action' => 'login']);

        $content = $this->readLog($path);
        $this->assertStringContainsString('"user_id":42', $content);
        $this->assertStringContainsString('"action":"login"', $content);
    }

    public function testEmptyContextProducesNoJsonSuffix(): void
    {
        $path   = $this->makeTempLogFile();
        $logger = new Logger($path, 'info');
        $logger->info('no context');

        $content = $this->readLog($path);
        $this->assertStringNotContainsString('{', $content);
    }

    // =========================================================================
    // Level filtering
    // =========================================================================

    public function testDebugIsWrittenWhenMinLevelIsDebug(): void
    {
        $path   = $this->makeTempLogFile();
        $logger = new Logger($path, 'debug');
        $logger->debug('debug msg');

        $this->assertStringContainsString('[DEBUG]', $this->readLog($path));
    }

    public function testDebugIsNotWrittenWhenMinLevelIsInfo(): void
    {
        $path   = $this->makeTempLogFile();
        $logger = new Logger($path, 'info');
        $logger->debug('debug msg');

        $this->assertSame('', $this->readLog($path));
    }

    public function testInfoIsNotWrittenWhenMinLevelIsWarning(): void
    {
        $path   = $this->makeTempLogFile();
        $logger = new Logger($path, 'warning');
        $logger->info('info msg');

        $this->assertSame('', $this->readLog($path));
    }

    public function testWarningIsWrittenWhenMinLevelIsWarning(): void
    {
        $path   = $this->makeTempLogFile();
        $logger = new Logger($path, 'warning');
        $logger->warning('warn msg');

        $this->assertStringContainsString('[WARNING]', $this->readLog($path));
    }

    public function testErrorIsWrittenWhenMinLevelIsError(): void
    {
        $path   = $this->makeTempLogFile();
        $logger = new Logger($path, 'error');
        $logger->error('error msg');

        $this->assertStringContainsString('[ERROR]', $this->readLog($path));
    }

    public function testDebugAndInfoNotWrittenWhenMinLevelIsError(): void
    {
        $path   = $this->makeTempLogFile();
        $logger = new Logger($path, 'error');
        $logger->debug('d');
        $logger->info('i');
        $logger->warning('w');

        $this->assertSame('', $this->readLog($path));
    }

    public function testAllLevelsWrittenWhenMinLevelIsDebug(): void
    {
        $path   = $this->makeTempLogFile();
        $logger = new Logger($path, 'debug');
        $logger->debug('d');
        $logger->info('i');
        $logger->warning('w');
        $logger->error('e');

        $content = $this->readLog($path);
        $this->assertStringContainsString('[DEBUG]', $content);
        $this->assertStringContainsString('[INFO]', $content);
        $this->assertStringContainsString('[WARNING]', $content);
        $this->assertStringContainsString('[ERROR]', $content);
    }

    // =========================================================================
    // setLevel at runtime changes filtering immediately
    // =========================================================================

    public function testSetLevelAtRuntimeAffectsSubsequentWrites(): void
    {
        $path   = $this->makeTempLogFile();
        $logger = new Logger($path, 'error');

        $logger->info('should not appear');
        $logger->setLevel('info');
        $logger->info('should appear');

        $content = $this->readLog($path);
        $this->assertStringNotContainsString('should not appear', $content);
        $this->assertStringContainsString('should appear', $content);
    }

    // =========================================================================
    // Multiple entries accumulate
    // =========================================================================

    public function testMultipleEntriesAreAppended(): void
    {
        $path   = $this->makeTempLogFile();
        $logger = new Logger($path, 'info');
        $logger->info('first');
        $logger->info('second');
        $logger->info('third');

        $lines = array_filter(explode(PHP_EOL, $this->readLog($path)));
        $this->assertCount(3, $lines);
    }
}
