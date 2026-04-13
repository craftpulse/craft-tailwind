<?php

/**
 * @link      https://craftpulse.com
 * @copyright Copyright (c) CraftPulse
 */

use craftpulse\tailwind\services\VersionDetector;

// =========================================================================
// = Explicit Configuration
// =========================================================================

it('returns v3 when explicitly configured', function(): void {
    $detector = new VersionDetector();

    expect($detector->detect('3'))->toBe('3');
});

it('returns v4 when explicitly configured', function(): void {
    $detector = new VersionDetector();

    expect($detector->detect('4'))->toBe('4');
});

it('skips detection entirely when version is explicit', function(): void {
    $tmpDir = sys_get_temp_dir() . '/tw-test-' . uniqid();
    mkdir($tmpDir, 0777, true);

    // Even with no files present, explicit config returns immediately
    $detector = new VersionDetector();

    expect($detector->detect('3', null, null, $tmpDir))->toBe('3');
    expect($detector->detect('4', null, null, $tmpDir))->toBe('4');

    // Cleanup
    rmdir($tmpDir);
});

// =========================================================================
// = V4 Detection from CSS Files
// =========================================================================

it('detects v4 from @import "tailwindcss" in CSS file', function(): void {
    $tmpDir = sys_get_temp_dir() . '/tw-test-' . uniqid();
    mkdir($tmpDir, 0777, true);
    file_put_contents($tmpDir . '/app.css', '@import "tailwindcss";');

    $detector = new VersionDetector();
    $result = $detector->detect('auto', null, $tmpDir, $tmpDir);

    expect($result)->toBe('4');

    // Cleanup
    unlink($tmpDir . '/app.css');
    rmdir($tmpDir);
});

it('detects v4 from @import \'tailwindcss\' with single quotes', function(): void {
    $tmpDir = sys_get_temp_dir() . '/tw-test-' . uniqid();
    mkdir($tmpDir, 0777, true);
    file_put_contents($tmpDir . '/app.css', "@import 'tailwindcss';");

    $detector = new VersionDetector();
    $result = $detector->detect('auto', null, $tmpDir, $tmpDir);

    expect($result)->toBe('4');

    // Cleanup
    unlink($tmpDir . '/app.css');
    rmdir($tmpDir);
});

it('detects v4 from @theme directive in CSS file', function(): void {
    $tmpDir = sys_get_temp_dir() . '/tw-test-' . uniqid();
    mkdir($tmpDir, 0777, true);
    file_put_contents($tmpDir . '/app.css', "@theme {\n  --color-primary: #3490dc;\n}");

    $detector = new VersionDetector();
    $result = $detector->detect('auto', null, $tmpDir, $tmpDir);

    expect($result)->toBe('4');

    // Cleanup
    unlink($tmpDir . '/app.css');
    rmdir($tmpDir);
});

it('detects v4 from .pcss files', function(): void {
    $tmpDir = sys_get_temp_dir() . '/tw-test-' . uniqid();
    mkdir($tmpDir, 0777, true);
    file_put_contents($tmpDir . '/app.pcss', '@import "tailwindcss";');

    $detector = new VersionDetector();
    $result = $detector->detect('auto', null, $tmpDir, $tmpDir);

    expect($result)->toBe('4');

    // Cleanup
    unlink($tmpDir . '/app.pcss');
    rmdir($tmpDir);
});

it('does not detect v4 from CSS without tailwind signals', function(): void {
    $tmpDir = sys_get_temp_dir() . '/tw-test-' . uniqid();
    mkdir($tmpDir, 0777, true);
    file_put_contents($tmpDir . '/app.css', "body { color: red; }");

    $detector = new VersionDetector();
    $result = $detector->detect('auto', null, $tmpDir, $tmpDir);

    // No config files either, so falls back to v4
    expect($result)->toBe('4');

    // Cleanup
    unlink($tmpDir . '/app.css');
    rmdir($tmpDir);
});

// =========================================================================
// = V3 Detection from Config Files
// =========================================================================

it('detects v3 from tailwind.config.js', function(): void {
    $tmpDir = sys_get_temp_dir() . '/tw-test-' . uniqid();
    mkdir($tmpDir, 0777, true);
    file_put_contents($tmpDir . '/tailwind.config.js', 'module.exports = {}');

    $detector = new VersionDetector();
    $result = $detector->detect('auto', $tmpDir, null, $tmpDir);

    expect($result)->toBe('3');

    // Cleanup
    unlink($tmpDir . '/tailwind.config.js');
    rmdir($tmpDir);
});

it('detects v3 from tailwind.config.ts', function(): void {
    $tmpDir = sys_get_temp_dir() . '/tw-test-' . uniqid();
    mkdir($tmpDir, 0777, true);
    file_put_contents($tmpDir . '/tailwind.config.ts', 'export default {}');

    $detector = new VersionDetector();
    $result = $detector->detect('auto', $tmpDir, null, $tmpDir);

    expect($result)->toBe('3');

    // Cleanup
    unlink($tmpDir . '/tailwind.config.ts');
    rmdir($tmpDir);
});

it('detects v3 from tailwind.config.cjs', function(): void {
    $tmpDir = sys_get_temp_dir() . '/tw-test-' . uniqid();
    mkdir($tmpDir, 0777, true);
    file_put_contents($tmpDir . '/tailwind.config.cjs', 'module.exports = {}');

    $detector = new VersionDetector();
    $result = $detector->detect('auto', $tmpDir, null, $tmpDir);

    expect($result)->toBe('3');

    // Cleanup
    unlink($tmpDir . '/tailwind.config.cjs');
    rmdir($tmpDir);
});

it('detects v3 from tailwind.config.mjs', function(): void {
    $tmpDir = sys_get_temp_dir() . '/tw-test-' . uniqid();
    mkdir($tmpDir, 0777, true);
    file_put_contents($tmpDir . '/tailwind.config.mjs', 'export default {}');

    $detector = new VersionDetector();
    $result = $detector->detect('auto', $tmpDir, null, $tmpDir);

    expect($result)->toBe('3');

    // Cleanup
    unlink($tmpDir . '/tailwind.config.mjs');
    rmdir($tmpDir);
});

it('detects v3 from a custom buildchain path', function(): void {
    $tmpDir = sys_get_temp_dir() . '/tw-test-' . uniqid();
    $buildchain = $tmpDir . '/buildchain';
    mkdir($buildchain, 0777, true);
    file_put_contents($buildchain . '/tailwind.config.js', 'module.exports = {}');

    $detector = new VersionDetector();
    $result = $detector->detect('auto', $buildchain, null, $tmpDir);

    expect($result)->toBe('3');

    // Cleanup
    unlink($buildchain . '/tailwind.config.js');
    rmdir($buildchain);
    rmdir($tmpDir);
});

// =========================================================================
// = Priority: CSS Path Wins Over Buildchain Path
// =========================================================================

it('prioritizes v4 CSS signals over v3 config files when both present', function(): void {
    $tmpDir = sys_get_temp_dir() . '/tw-test-' . uniqid();
    $cssDir = $tmpDir . '/src/css';
    $buildDir = $tmpDir . '/buildchain';
    mkdir($cssDir, 0777, true);
    mkdir($buildDir, 0777, true);

    // Config file -> v3
    file_put_contents($buildDir . '/tailwind.config.js', 'module.exports = {}');
    // CSS @import -> v4
    file_put_contents($cssDir . '/app.css', '@import "tailwindcss";');

    $detector = new VersionDetector();
    $result = $detector->detect('auto', $buildDir, $cssDir, $tmpDir);

    // v4 CSS signal should win
    expect($result)->toBe('4');

    // Cleanup
    unlink($buildDir . '/tailwind.config.js');
    unlink($cssDir . '/app.css');
    rmdir($cssDir);
    rmdir($tmpDir . '/src');
    rmdir($buildDir);
    rmdir($tmpDir);
});

it('falls through to v3 config when CSS has no tailwind signals', function(): void {
    $tmpDir = sys_get_temp_dir() . '/tw-test-' . uniqid();
    $cssDir = $tmpDir . '/src/css';
    $buildDir = $tmpDir . '/buildchain';
    mkdir($cssDir, 0777, true);
    mkdir($buildDir, 0777, true);

    // Config file -> v3
    file_put_contents($buildDir . '/tailwind.config.js', 'module.exports = {}');
    // CSS without tailwind signals
    file_put_contents($cssDir . '/app.css', 'body { color: red; }');

    $detector = new VersionDetector();
    $result = $detector->detect('auto', $buildDir, $cssDir, $tmpDir);

    // Should fall through to v3 config detection
    expect($result)->toBe('3');

    // Cleanup
    unlink($buildDir . '/tailwind.config.js');
    unlink($cssDir . '/app.css');
    rmdir($cssDir);
    rmdir($tmpDir . '/src');
    rmdir($buildDir);
    rmdir($tmpDir);
});

// =========================================================================
// = Unconfigured Paths Default to Root
// =========================================================================

it('uses project root for CSS detection when cssPath is null', function(): void {
    $tmpDir = sys_get_temp_dir() . '/tw-test-' . uniqid();
    mkdir($tmpDir, 0777, true);
    file_put_contents($tmpDir . '/app.css', '@import "tailwindcss";');

    $detector = new VersionDetector();
    $result = $detector->detect('auto', null, null, $tmpDir);

    expect($result)->toBe('4');

    // Cleanup
    unlink($tmpDir . '/app.css');
    rmdir($tmpDir);
});

it('uses project root for config detection when buildchainPath is null', function(): void {
    $tmpDir = sys_get_temp_dir() . '/tw-test-' . uniqid();
    mkdir($tmpDir, 0777, true);
    file_put_contents($tmpDir . '/tailwind.config.js', 'module.exports = {}');

    $detector = new VersionDetector();
    $result = $detector->detect('auto', null, null, $tmpDir);

    expect($result)->toBe('3');

    // Cleanup
    unlink($tmpDir . '/tailwind.config.js');
    rmdir($tmpDir);
});

// =========================================================================
// = Fallback Behavior
// =========================================================================

it('falls back to v4 when no detection signals are found', function(): void {
    $tmpDir = sys_get_temp_dir() . '/tw-test-' . uniqid();
    mkdir($tmpDir, 0777, true);

    $detector = new VersionDetector();
    $result = $detector->detect('auto', null, null, $tmpDir);

    expect($result)->toBe('4');

    // Cleanup
    rmdir($tmpDir);
});

// =========================================================================
// = Caching
// =========================================================================

it('caches detection result for the request lifecycle', function(): void {
    $tmpDir = sys_get_temp_dir() . '/tw-test-' . uniqid();
    mkdir($tmpDir, 0777, true);
    file_put_contents($tmpDir . '/tailwind.config.js', 'module.exports = {}');

    $detector = new VersionDetector();

    // First call should detect v3
    $result1 = $detector->detect('auto', $tmpDir, null, $tmpDir);
    expect($result1)->toBe('3');

    // Remove the config file
    unlink($tmpDir . '/tailwind.config.js');

    // Second call should return cached result
    $result2 = $detector->detect('auto', $tmpDir, null, $tmpDir);
    expect($result2)->toBe('3');

    // After clearing cache, should fall back to v4
    $detector->clearCache();
    $result3 = $detector->detect('auto', $tmpDir, null, $tmpDir);
    expect($result3)->toBe('4');

    // Cleanup
    rmdir($tmpDir);
});
