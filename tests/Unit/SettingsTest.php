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

it('rejects non-string auto-inject attribute values', function(): void {
    $settings = new Settings([
        'autoInjectAttributes' => ['nonce' => ['nested']],
    ]);

    $settings->validate();

    expect($settings->getErrors('autoInjectAttributes'))->not->toBeEmpty();
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
