<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use TeampassClasses\PasswordGeneratorService\PasswordGeneratorService;

/**
 * Unit tests for PasswordGeneratorService.
 *
 * getPresetForComplexity() is fully testable (pure static, no I/O).
 *
 * generateForFolder() calls getFolderComplexity() which hits the DB when
 * folderId > 0. All generation tests therefore use folderId = 0 so the
 * private method returns 0 immediately without any DB query.
 *
 * Covered:
 *   getPresetForComplexity()
 *     - Each canonical complexity level (0, 20, 38, 48, 60).
 *     - Intermediate values fall back to the nearest lower preset.
 *     - Values above 60 use the 60 preset.
 *     - Negative values use the 0 preset.
 *
 *   generateForFolder() (folderId = 0)
 *     - Return structure: keys 'key', 'error', 'effective_options'.
 *     - 'error' is empty on success.
 *     - Generated password length matches the requested size.
 *     - Length is raised to the preset minimum when requestedSize is too small.
 *     - maxLength caps the generated length.
 *     - User options are reflected in effective_options.
 *     - Union rule: preset requirements cannot be removed by user options.
 *     - Fallback: at least one char class is always enabled.
 *     - effective_options keys match the expected schema.
 */
class PasswordGeneratorServiceTest extends TestCase
{
    private PasswordGeneratorService $service;

    protected function setUp(): void
    {
        $this->service = new PasswordGeneratorService();
    }

    // =========================================================================
    // getPresetForComplexity — canonical levels
    // =========================================================================

    public function testPresetLevel0HasCorrectValues(): void
    {
        $p = PasswordGeneratorService::getPresetForComplexity(0);

        $this->assertSame(4, $p['min_length']);
        $this->assertFalse($p['lowercase']);
        $this->assertFalse($p['uppercase']);
        $this->assertFalse($p['numbers']);
        $this->assertFalse($p['symbols']);
    }

    public function testPresetLevel20HasCorrectValues(): void
    {
        $p = PasswordGeneratorService::getPresetForComplexity(20);

        $this->assertSame(8, $p['min_length']);
        $this->assertTrue($p['lowercase']);
        $this->assertFalse($p['uppercase']);
        $this->assertTrue($p['numbers']);
        $this->assertFalse($p['symbols']);
    }

    public function testPresetLevel38HasCorrectValues(): void
    {
        $p = PasswordGeneratorService::getPresetForComplexity(38);

        $this->assertSame(12, $p['min_length']);
        $this->assertTrue($p['lowercase']);
        $this->assertTrue($p['uppercase']);
        $this->assertTrue($p['numbers']);
        $this->assertFalse($p['symbols']);
    }

    public function testPresetLevel48HasCorrectValues(): void
    {
        $p = PasswordGeneratorService::getPresetForComplexity(48);

        $this->assertSame(16, $p['min_length']);
        $this->assertTrue($p['lowercase']);
        $this->assertTrue($p['uppercase']);
        $this->assertTrue($p['numbers']);
        $this->assertFalse($p['symbols']);
    }

    public function testPresetLevel60HasCorrectValues(): void
    {
        $p = PasswordGeneratorService::getPresetForComplexity(60);

        $this->assertSame(16, $p['min_length']);
        $this->assertTrue($p['lowercase']);
        $this->assertTrue($p['uppercase']);
        $this->assertTrue($p['numbers']);
        $this->assertTrue($p['symbols']);
    }

    // =========================================================================
    // getPresetForComplexity — boundary and intermediate values
    // =========================================================================

    public function testIntermediateValueUsesNearestLowerPreset(): void
    {
        // 30 is between 20 and 38, so the 20 preset applies
        $p30 = PasswordGeneratorService::getPresetForComplexity(30);
        $p20 = PasswordGeneratorService::getPresetForComplexity(20);

        $this->assertSame($p20, $p30);
    }

    public function testValueBetween38And48UsesPreset38(): void
    {
        $p40 = PasswordGeneratorService::getPresetForComplexity(40);
        $p38 = PasswordGeneratorService::getPresetForComplexity(38);

        $this->assertSame($p38, $p40);
    }

    public function testValueAbove60UsesPreset60(): void
    {
        $p100 = PasswordGeneratorService::getPresetForComplexity(100);
        $p60  = PasswordGeneratorService::getPresetForComplexity(60);

        $this->assertSame($p60, $p100);
    }

    public function testNegativeValueUsesPreset0(): void
    {
        $pNeg = PasswordGeneratorService::getPresetForComplexity(-1);
        $p0   = PasswordGeneratorService::getPresetForComplexity(0);

        $this->assertSame($p0, $pNeg);
    }

    public function testValueJustBelow20UsesPreset0(): void
    {
        $p19 = PasswordGeneratorService::getPresetForComplexity(19);
        $p0  = PasswordGeneratorService::getPresetForComplexity(0);

        $this->assertSame($p0, $p19);
    }

    // =========================================================================
    // generateForFolder — return structure (folderId = 0)
    // =========================================================================

    public function testGenerateReturnsArrayWithRequiredKeys(): void
    {
        $result = $this->service->generateForFolder(0, 16, true, true, true, false, 128);

        $this->assertArrayHasKey('key', $result);
        $this->assertArrayHasKey('error', $result);
        $this->assertArrayHasKey('effective_options', $result);
    }

    public function testGenerateReturnsEmptyErrorOnSuccess(): void
    {
        $result = $this->service->generateForFolder(0, 16, true, true, true, false, 128);

        $this->assertSame('', $result['error']);
    }

    public function testGeneratedPasswordIsNonEmptyString(): void
    {
        $result = $this->service->generateForFolder(0, 16, true, false, false, false, 128);

        $this->assertIsString($result['key']);
        $this->assertNotEmpty($result['key']);
    }

    public function testEffectiveOptionsContainsExpectedKeys(): void
    {
        $result = $this->service->generateForFolder(0, 16, true, true, true, false, 128);
        $opts   = $result['effective_options'];

        $this->assertArrayHasKey('size', $opts);
        $this->assertArrayHasKey('lowercase', $opts);
        $this->assertArrayHasKey('capitalize', $opts);
        $this->assertArrayHasKey('numerals', $opts);
        $this->assertArrayHasKey('symbols', $opts);
        $this->assertArrayHasKey('secure_pwd', $opts);
    }

    // =========================================================================
    // generateForFolder — length enforcement (folderId = 0)
    // =========================================================================

    public function testPasswordLengthMatchesRequestedSize(): void
    {
        $result = $this->service->generateForFolder(0, 20, true, true, true, false, 128);

        $this->assertSame(20, strlen($result['key']));
        $this->assertSame(20, $result['effective_options']['size']);
    }

    public function testPasswordLengthVariousValues(): void
    {
        foreach ([8, 12, 16, 24, 32] as $size) {
            $result = $this->service->generateForFolder(0, $size, true, false, false, false, 128);
            $this->assertSame($size, strlen($result['key']), "Expected length {$size}.");
        }
    }

    public function testRequestedSizeBelowPresetMinIsRaisedToPresetMin(): void
    {
        // folderId=0 → complexity=0 → preset min_length=4; request 2 → raised to 4
        $result = $this->service->generateForFolder(0, 2, true, false, false, false, 128);

        $this->assertGreaterThanOrEqual(4, strlen($result['key']));
        $this->assertGreaterThanOrEqual(4, $result['effective_options']['size']);
    }

    public function testZeroRequestedSizeDefaultsToTen(): void
    {
        // requestedSize=0 → code uses 10 as default before comparing with preset min
        $result = $this->service->generateForFolder(0, 0, true, false, false, false, 128);

        $this->assertGreaterThanOrEqual(4, strlen($result['key']));
    }

    public function testMaxLengthCapsPasswordLength(): void
    {
        $result = $this->service->generateForFolder(0, 50, true, true, true, false, 20);

        $this->assertSame(20, strlen($result['key']));
        $this->assertSame(20, $result['effective_options']['size']);
    }

    public function testZeroMaxLengthAppliesNoUpperCap(): void
    {
        // maxLength=0 means no cap
        $result = $this->service->generateForFolder(0, 30, true, true, true, false, 0);

        $this->assertSame(30, strlen($result['key']));
    }

    // =========================================================================
    // generateForFolder — effective options / union rule (folderId = 0)
    // =========================================================================

    public function testUserLowercaseOptionIsReflectedInEffectiveOptions(): void
    {
        $result = $this->service->generateForFolder(0, 12, true, false, false, false, 128);

        $this->assertTrue($result['effective_options']['lowercase']);
    }

    public function testUserUppercaseOptionIsReflectedInEffectiveOptions(): void
    {
        $result = $this->service->generateForFolder(0, 12, false, true, false, false, 128);

        $this->assertTrue($result['effective_options']['capitalize']);
    }

    public function testUserSymbolsOptionIsReflectedInEffectiveOptions(): void
    {
        $result = $this->service->generateForFolder(0, 12, false, false, false, true, 128);

        $this->assertTrue($result['effective_options']['symbols']);
    }

    public function testAllUserOptionsFalseWithNoPresetForcesLowercaseUppercaseNumbers(): void
    {
        // folderId=0 → preset all false; all user options false → fallback activates lc+uc+num
        $result = $this->service->generateForFolder(0, 12, false, false, false, false, 128);
        $opts   = $result['effective_options'];

        $this->assertTrue($opts['lowercase']);
        $this->assertTrue($opts['capitalize']);
        $this->assertTrue($opts['numerals']);
    }

    public function testUserSymbolsFalseDoesNotEnableSymbolsWhenPresetDoesNotRequireIt(): void
    {
        // folderId=0 → preset symbols=false; user symbols=false → symbols stays false
        $result = $this->service->generateForFolder(0, 16, true, true, true, false, 128);

        $this->assertFalse($result['effective_options']['symbols']);
    }

    public function testUserSymbolsTrueEnablesSymbolsEvenWhenPresetDoesNotRequireIt(): void
    {
        $result = $this->service->generateForFolder(0, 16, true, true, true, true, 128);

        $this->assertTrue($result['effective_options']['symbols']);
    }

    public function testSecurePwdFlagIsAlwaysFalseInEffectiveOptions(): void
    {
        $result = $this->service->generateForFolder(0, 16, true, true, true, true, 128);

        $this->assertFalse($result['effective_options']['secure_pwd']);
    }
}
