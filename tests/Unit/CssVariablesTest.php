<?php

/**
 * @link      https://craftpulse.com
 * @copyright Copyright (c) CraftPulse
 */

use craftpulse\tailwind\models\CssVariables;

// =========================================================================
// = Empty Variables
// =========================================================================

it('returns empty string from asCss() when no variables exist', function(): void {
    $vars = new CssVariables([]);

    expect($vars->asCss())->toBe('');
});

it('returns empty string from __toString() when no variables exist', function(): void {
    $vars = new CssVariables([]);

    expect((string) $vars)->toBe('');
});

// =========================================================================
// = asCss Output
// =========================================================================

it('renders a single variable as a :root CSS block', function(): void {
    $vars = new CssVariables(['--color-brand' => '#222']);

    $expected = ":root {\n  --color-brand: #222;\n}";

    expect($vars->asCss())->toBe($expected);
});

it('renders multiple variables as a :root CSS block', function(): void {
    $vars = new CssVariables([
        '--color-brand' => '#222',
        '--size-lg' => '1.5rem',
    ]);

    $expected = ":root {\n  --color-brand: #222;\n  --size-lg: 1.5rem;\n}";

    expect($vars->asCss())->toBe($expected);
});

// =========================================================================
// = __toString
// =========================================================================

it('casts to string using asCss', function(): void {
    $vars = new CssVariables(['--color-brand' => '#222']);

    expect((string) $vars)->toBe($vars->asCss());
});

// =========================================================================
// = get
// =========================================================================

it('gets a variable value by name', function(): void {
    $vars = new CssVariables(['--color-brand' => '#222']);

    expect($vars->get('--color-brand'))->toBe('#222');
});

it('gets a variable value by name without -- prefix', function(): void {
    $vars = new CssVariables(['--color-brand' => '#222']);

    expect($vars->get('color-brand'))->toBe('#222');
});

it('returns null for a non-existent variable', function(): void {
    $vars = new CssVariables(['--color-brand' => '#222']);

    expect($vars->get('--missing'))->toBeNull();
});

// =========================================================================
// = has
// =========================================================================

it('returns true for an existing variable', function(): void {
    $vars = new CssVariables(['--color-brand' => '#222']);

    expect($vars->has('--color-brand'))->toBeTrue();
});

it('returns true for an existing variable without -- prefix', function(): void {
    $vars = new CssVariables(['--color-brand' => '#222']);

    expect($vars->has('color-brand'))->toBeTrue();
});

it('returns false for a non-existent variable', function(): void {
    $vars = new CssVariables([]);

    expect($vars->has('--missing'))->toBeFalse();
});

// =========================================================================
// = all
// =========================================================================

it('returns all variables as an associative array', function(): void {
    $vars = new CssVariables([
        '--color-brand' => '#222',
        '--size-lg' => '1.5rem',
    ]);

    expect($vars->all())->toBe([
        '--color-brand' => '#222',
        '--size-lg' => '1.5rem',
    ]);
});

it('returns empty array when no variables exist', function(): void {
    $vars = new CssVariables([]);

    expect($vars->all())->toBe([]);
});

// =========================================================================
// = isEmpty
// =========================================================================

it('returns true when empty', function(): void {
    $vars = new CssVariables([]);

    expect($vars->isEmpty())->toBeTrue();
});

it('returns false when variables exist', function(): void {
    $vars = new CssVariables(['--color-brand' => '#222']);

    expect($vars->isEmpty())->toBeFalse();
});

// =========================================================================
// = Key Prefixing
// =========================================================================

it('auto-prefixes keys without -- prefix', function(): void {
    $vars = new CssVariables(['color-brand' => '#222']);

    expect($vars->has('--color-brand'))->toBeTrue();
    expect($vars->get('--color-brand'))->toBe('#222');
});

it('does not double-prefix keys that already have --', function(): void {
    $vars = new CssVariables(['--color-brand' => '#222']);

    expect($vars->all())->toBe(['--color-brand' => '#222']);
});

// =========================================================================
// = Value Sanitization
// =========================================================================

it('rejects empty string values', function(): void {
    $vars = new CssVariables(['--color-brand' => '']);

    expect($vars->isEmpty())->toBeTrue();
});

it('rejects values with unsafe characters', function(): void {
    $vars = new CssVariables([
        '--safe' => '#222',
        '--unsafe-semicolon' => '#222; background: url(evil)',
        '--unsafe-curly' => 'red} .injected { color: green',
        '--unsafe-newline' => "red\nblue",
    ]);

    expect($vars->all())->toBe(['--safe' => '#222']);
});

it('allows values with common CSS characters', function(): void {
    $vars = new CssVariables([
        '--color' => '#3490dc',
        '--font' => '"Helvetica Neue", sans-serif',
        '--opacity' => '0.5',
        '--calc' => '100%',
        '--rgb' => 'rgb(52, 144, 220)',
        '--ratio' => '16/9',
        '--negative' => '-1px',
    ]);

    expect(count($vars->all()))->toBe(7);
});
