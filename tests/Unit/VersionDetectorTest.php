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

// =========================================================================
// = Detection from Config Files
// =========================================================================

it('detects v3 from tailwind.config.js', function(): void {
    $tmpDir = sys_get_temp_dir() . '/tw-test-' . uniqid();
    mkdir($tmpDir, 0777, true);
    file_put_contents($tmpDir . '/tailwind.config.js', 'module.exports = {}');

    $detector = new VersionDetector();
    $result = $detector->detect('auto', $tmpDir);

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
    $result = $detector->detect('auto', $tmpDir);

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
    $result = $detector->detect('auto', $tmpDir);

    expect($result)->toBe('3');

    // Cleanup
    unlink($tmpDir . '/tailwind.config.cjs');
    rmdir($tmpDir);
});

it('detects v3 from buildchain directory', function(): void {
    $tmpDir = sys_get_temp_dir() . '/tw-test-' . uniqid();
    mkdir($tmpDir . '/buildchain', 0777, true);
    file_put_contents($tmpDir . '/buildchain/tailwind.config.js', 'module.exports = {}');

    $detector = new VersionDetector();
    $result = $detector->detect('auto', $tmpDir);

    expect($result)->toBe('3');

    // Cleanup
    unlink($tmpDir . '/buildchain/tailwind.config.js');
    rmdir($tmpDir . '/buildchain');
    rmdir($tmpDir);
});

// =========================================================================
// = Detection from CSS Files
// =========================================================================

it('detects v4 from @theme directive in CSS', function(): void {
    $tmpDir = sys_get_temp_dir() . '/tw-test-' . uniqid();
    mkdir($tmpDir . '/src/css', 0777, true);
    file_put_contents($tmpDir . '/src/css/app.css', "@theme {\n  --color-primary: #3490dc;\n}");

    $detector = new VersionDetector();
    $result = $detector->detect('auto', $tmpDir);

    expect($result)->toBe('4');

    // Cleanup
    unlink($tmpDir . '/src/css/app.css');
    rmdir($tmpDir . '/src/css');
    rmdir($tmpDir . '/src');
    rmdir($tmpDir);
});

it('does not detect v4 without @theme directive', function(): void {
    $tmpDir = sys_get_temp_dir() . '/tw-test-' . uniqid();
    mkdir($tmpDir . '/src/css', 0777, true);
    file_put_contents($tmpDir . '/src/css/app.css', "@tailwind base;\n@tailwind components;");

    $detector = new VersionDetector();
    $result = $detector->detect('auto', $tmpDir);

    // No config files, no @theme, no package.json → unknown
    expect($result)->toBe('unknown');

    // Cleanup
    unlink($tmpDir . '/src/css/app.css');
    rmdir($tmpDir . '/src/css');
    rmdir($tmpDir . '/src');
    rmdir($tmpDir);
});

// =========================================================================
// = Detection from package.json
// =========================================================================

it('detects v3 from package.json dependencies', function(): void {
    $tmpDir = sys_get_temp_dir() . '/tw-test-' . uniqid();
    mkdir($tmpDir, 0777, true);
    file_put_contents($tmpDir . '/package.json', json_encode([
        'dependencies' => [
            'tailwindcss' => '^3.4.0',
        ],
    ]));

    $detector = new VersionDetector();
    $result = $detector->detect('auto', $tmpDir);

    expect($result)->toBe('3');

    // Cleanup
    unlink($tmpDir . '/package.json');
    rmdir($tmpDir);
});

it('detects v4 from package.json devDependencies', function(): void {
    $tmpDir = sys_get_temp_dir() . '/tw-test-' . uniqid();
    mkdir($tmpDir, 0777, true);
    file_put_contents($tmpDir . '/package.json', json_encode([
        'devDependencies' => [
            'tailwindcss' => '^4.0.0',
        ],
    ]));

    $detector = new VersionDetector();
    $result = $detector->detect('auto', $tmpDir);

    expect($result)->toBe('4');

    // Cleanup
    unlink($tmpDir . '/package.json');
    rmdir($tmpDir);
});

it('detects v4 from tilde version in package.json', function(): void {
    $tmpDir = sys_get_temp_dir() . '/tw-test-' . uniqid();
    mkdir($tmpDir, 0777, true);
    file_put_contents($tmpDir . '/package.json', json_encode([
        'devDependencies' => [
            'tailwindcss' => '~4.1.0',
        ],
    ]));

    $detector = new VersionDetector();
    $result = $detector->detect('auto', $tmpDir);

    expect($result)->toBe('4');

    // Cleanup
    unlink($tmpDir . '/package.json');
    rmdir($tmpDir);
});

it('detects v3 from exact version in package.json', function(): void {
    $tmpDir = sys_get_temp_dir() . '/tw-test-' . uniqid();
    mkdir($tmpDir, 0777, true);
    file_put_contents($tmpDir . '/package.json', json_encode([
        'dependencies' => [
            'tailwindcss' => '3.4.17',
        ],
    ]));

    $detector = new VersionDetector();
    $result = $detector->detect('auto', $tmpDir);

    expect($result)->toBe('3');

    // Cleanup
    unlink($tmpDir . '/package.json');
    rmdir($tmpDir);
});

// =========================================================================
// = Fallback Behavior
// =========================================================================

it('returns unknown when no detection signals are found', function(): void {
    $tmpDir = sys_get_temp_dir() . '/tw-test-' . uniqid();
    mkdir($tmpDir, 0777, true);

    $detector = new VersionDetector();
    $result = $detector->detect('auto', $tmpDir);

    expect($result)->toBe('unknown');

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
    $result1 = $detector->detect('auto', $tmpDir);
    expect($result1)->toBe('3');

    // Remove the config file
    unlink($tmpDir . '/tailwind.config.js');

    // Second call should return cached result
    $result2 = $detector->detect('auto', $tmpDir);
    expect($result2)->toBe('3');

    // After clearing cache, should return unknown
    $detector->clearCache();
    $result3 = $detector->detect('auto', $tmpDir);
    expect($result3)->toBe('unknown');

    // Cleanup
    rmdir($tmpDir);
});

// =========================================================================
// = Priority Order
// =========================================================================

it('prioritizes config file over CSS @theme and package.json', function(): void {
    $tmpDir = sys_get_temp_dir() . '/tw-test-' . uniqid();
    mkdir($tmpDir . '/src/css', 0777, true);

    // Config file → v3
    file_put_contents($tmpDir . '/tailwind.config.js', 'module.exports = {}');
    // CSS @theme → v4
    file_put_contents($tmpDir . '/src/css/app.css', "@theme {\n  --color-primary: #3490dc;\n}");
    // package.json → v4
    file_put_contents($tmpDir . '/package.json', json_encode([
        'devDependencies' => ['tailwindcss' => '^4.0.0'],
    ]));

    $detector = new VersionDetector();
    $result = $detector->detect('auto', $tmpDir);

    // Config file should win → v3
    expect($result)->toBe('3');

    // Cleanup
    unlink($tmpDir . '/tailwind.config.js');
    unlink($tmpDir . '/src/css/app.css');
    unlink($tmpDir . '/package.json');
    rmdir($tmpDir . '/src/css');
    rmdir($tmpDir . '/src');
    rmdir($tmpDir);
});
