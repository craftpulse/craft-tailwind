<?php

/**
 * @link      https://craftpulse.com
 * @copyright Copyright (c) CraftPulse
 */

use craftpulse\tailwind\models\Settings;

// =========================================================================
// = autoInjectAttributes — allowed-key whitelist
// =========================================================================

it('accepts nonce, media, and title as auto-inject attribute keys', function(): void {
    $settings = new Settings([
        'autoInjectAttributes' => [
            'nonce' => 'abc123',
            'media' => 'screen',
            'title' => 'tailwind variables',
        ],
    ]);

    $settings->validate();

    expect($settings->getErrors('autoInjectAttributes'))->toBe([]);
});

it('rejects auto-inject attribute keys outside the whitelist', function(): void {
    $settings = new Settings([
        'autoInjectAttributes' => ['onload' => 'alert(1)'],
    ]);

    $settings->validate();
    $errors = $settings->getErrors('autoInjectAttributes');

    expect($errors)->not->toBeEmpty();
    expect($errors[0])->toContain('"onload"');
    expect($errors[0])->toContain('not allowed');
});

it('exposes the allowed auto-inject attribute set as a constant', function(): void {
    expect(Settings::ALLOWED_AUTO_INJECT_ATTRIBUTES)->toBe(['nonce', 'media', 'title']);
});

// =========================================================================
// = cssVariables — save-time safety pattern
// =========================================================================

it('accepts CSS variable values containing common safe characters', function(): void {
    $settings = new Settings([
        'cssVariables' => [
            '--color' => '#3490dc',
            '--font' => '"Helvetica Neue", sans-serif',
            '--rgb' => 'rgb(52, 144, 220)',
            '--ratio' => '16/9',
        ],
    ]);

    $settings->validate();

    expect($settings->getErrors('cssVariables'))->toBe([]);
});

it('rejects CSS variable values that could break out of the :root declaration', function(): void {
    $settings = new Settings([
        'cssVariables' => [
            '--injected' => 'red; } body { display: none; /*',
        ],
    ]);

    $settings->validate();
    $errors = $settings->getErrors('cssVariables');

    expect($errors)->not->toBeEmpty();
    expect($errors[0])->toContain('unsafe characters');
});

it('rejects CSS variable values containing semicolons', function(): void {
    $settings = new Settings([
        'cssVariables' => ['--evil' => '1; @import "x"'],
    ]);

    $settings->validate();

    expect($settings->getErrors('cssVariables'))->not->toBeEmpty();
});

it('rejects empty CSS variable values', function(): void {
    $settings = new Settings([
        'cssVariables' => ['--color' => ''],
    ]);

    $settings->validate();
    $errors = $settings->getErrors('cssVariables');

    expect($errors)->not->toBeEmpty();
    expect($errors[0])->toContain('non-empty string value');
});

it('reports an error per unsafe key while still rejecting all of them', function(): void {
    $settings = new Settings([
        'cssVariables' => [
            '--bad-one' => 'red; }',
            '--bad-two' => '} body { display: none',
            '--good' => '#fff',
        ],
    ]);

    $settings->validate();

    expect($settings->getErrors('cssVariables'))->toHaveCount(2);
    // Validation reports errors but does not mutate the underlying data;
    // the good entry remains so the admin can fix the bad ones and re-save.
    expect($settings->cssVariables['--good'])->toBe('#fff');
});

it('rejects CSS variable values containing newlines', function(): void {
    $settings = new Settings([
        'cssVariables' => ['--multiline' => "red\nblue"],
    ]);

    $settings->validate();

    expect($settings->getErrors('cssVariables'))->not->toBeEmpty();
});

// =========================================================================
// = Other rules still apply
// =========================================================================

it('rejects out-of-range cacheSize', function(): void {
    $settings = new Settings(['cacheSize' => 99999]);

    $settings->validate();

    expect($settings->getErrors('cacheSize'))->not->toBeEmpty();
});

it('rejects unknown tailwindVersion values', function(): void {
    $settings = new Settings(['tailwindVersion' => '5']);

    $settings->validate();

    expect($settings->getErrors('tailwindVersion'))->not->toBeEmpty();
});

// =========================================================================
// = prefix — version-aware validation
// =========================================================================

it('defaults prefix to null', function(): void {
    $settings = new Settings();

    expect($settings->prefix)->toBeNull();
});

it('collapses an empty-string prefix to null on validate', function(): void {
    // The CP form posts `prefix=` (empty string) when the field is blank.
    $settings = new Settings(['prefix' => '']);

    $settings->validate();

    expect($settings->prefix)->toBeNull();
    expect($settings->getErrors('prefix'))->toBe([]);
});

it('accepts a bare prefix on any version', function(): void {
    foreach (['auto', '3', '4'] as $version) {
        $settings = new Settings(['tailwindVersion' => $version, 'prefix' => 'tw']);

        $settings->validate();

        expect($settings->getErrors('prefix'))->toBe([]);
    }
});

it('accepts a trailing-hyphen prefix on v3 (doc-canonical form)', function(): void {
    $settings = new Settings(['tailwindVersion' => '3', 'prefix' => 'tw-']);

    $settings->validate();

    expect($settings->getErrors('prefix'))->toBe([]);
});

it('accepts a trailing-hyphen prefix on auto (permissive, version unknown)', function(): void {
    // `auto` detection happens at runtime and may resolve to either v3 or
    // v4. We stay permissive here so we don't reach into the filesystem
    // from a model validator. The service-level normalization handles it.
    $settings = new Settings(['tailwindVersion' => 'auto', 'prefix' => 'tw-']);

    $settings->validate();

    expect($settings->getErrors('prefix'))->toBe([]);
});

it('rejects a trailing-hyphen prefix on explicit v4', function(): void {
    $settings = new Settings(['tailwindVersion' => '4', 'prefix' => 'tw-']);

    $settings->validate();
    $errors = $settings->getErrors('prefix');

    expect($errors)->not->toBeEmpty();
    expect($errors[0])->toContain('v4');
    expect($errors[0])->toContain('bare');
});

it('rejects malformed prefix shapes regardless of version', function(): void {
    // Leading digit, special characters, etc. — bad on any version.
    foreach (['1tw', 'tw:', 'tw px', '!tw'] as $bad) {
        $settings = new Settings(['tailwindVersion' => '3', 'prefix' => $bad]);

        $settings->validate();

        expect($settings->getErrors('prefix'))->not->toBeEmpty();
    }
});

// =========================================================================
// = CP form roundtrip — editable-table POST shape
// =========================================================================

it('normalizes the editable-table POST shape for cssVariables', function(): void {
    // Mirrors what `forms.editableTableField` posts: `field[rowId][colId]`.
    $settings = new Settings([
        'cssVariables' => [
            'row1' => ['name' => '--color-brand', 'value' => '#3490dc'],
            'row2' => ['name' => '--font-display', 'value' => '"Inter", sans-serif'],
        ],
    ]);

    $settings->validate();

    expect($settings->getErrors('cssVariables'))->toBe([]);
    expect($settings->cssVariables)->toBe([
        '--color-brand' => '#3490dc',
        '--font-display' => '"Inter", sans-serif',
    ]);
});

it('normalizes the editable-table POST shape for autoInjectAttributes', function(): void {
    $settings = new Settings([
        'autoInjectAttributes' => [
            'row1' => ['name' => 'nonce', 'value' => 'abc123'],
            'row2' => ['name' => 'media', 'value' => 'screen'],
        ],
    ]);

    $settings->validate();

    expect($settings->getErrors('autoInjectAttributes'))->toBe([]);
    expect($settings->autoInjectAttributes)->toBe([
        'nonce' => 'abc123',
        'media' => 'screen',
    ]);
});

it('drops editable-table rows with an empty name on normalization', function(): void {
    // Editable tables submit a blank trailing row when the admin adds one
    // and never fills it. Those should be dropped silently, not validated.
    $settings = new Settings([
        'cssVariables' => [
            'row1' => ['name' => '--color', 'value' => '#fff'],
            'row2' => ['name' => '', 'value' => ''],
        ],
    ]);

    $settings->validate();

    expect($settings->getErrors('cssVariables'))->toBe([]);
    expect($settings->cssVariables)->toBe(['--color' => '#fff']);
});

it('leaves a flat-shape cssVariables map untouched on normalization', function(): void {
    // Constructor injection from code or `config/tailwind.php` — already flat.
    $settings = new Settings([
        'cssVariables' => ['--color' => '#fff', '--brand' => '#222'],
    ]);

    $settings->validate();

    expect($settings->cssVariables)->toBe(['--color' => '#fff', '--brand' => '#222']);
});

it('runs the editable-table validators against the normalized shape', function(): void {
    // An unsafe value buried in the row format must still be rejected.
    $settings = new Settings([
        'cssVariables' => [
            'row1' => ['name' => '--injected', 'value' => 'red; } body { display: none; /*'],
        ],
    ]);

    $settings->validate();

    expect($settings->getErrors('cssVariables'))->not->toBeEmpty();
    expect($settings->cssVariables)->toBe(['--injected' => 'red; } body { display: none; /*']);
});
