<?php

/**
 * @link      https://craftpulse.com
 * @copyright Copyright (c) CraftPulse
 */

namespace craftpulse\tailwind\models;

use Closure;
use Stringable;

/**
 * Immutable named-slot class builder for Tailwind CSS.
 *
 * Holds named "slots" of CSS classes (e.g., layout, color, font)
 * and merges them into a single resolved string via the merge callable.
 * All mutating methods return new instances — the original is never modified.
 *
 * @author CraftPulse
 * @since 1.0.0
 */
class ClassList implements Stringable
{
    // =========================================================================
    // = Private Properties
    // =========================================================================

    /**
     * The named slots of CSS class strings.
     *
     * @var array<string, string>
     */
    private array $_slots;

    /**
     * The merge callable used to resolve conflicting classes.
     *
     * @var Closure
     */
    private Closure $_merger;

    // =========================================================================
    // = Public Methods
    // =========================================================================

    /**
     * Constructor.
     *
     * @param array<string, string> $slots Named slots of CSS class strings.
     * @param Closure $merger Callable that accepts variadic strings and returns a merged string.
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    public function __construct(array $slots, Closure $merger)
    {
        $this->_slots = $slots;
        $this->_merger = $merger;
    }

    /**
     * Converts the class list to a merged string of all slot values.
     *
     * @return string The merged CSS class string.
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    public function __toString(): string
    {
        $allClasses = implode(' ', array_values($this->_slots));

        return ($this->_merger)($allClasses);
    }

    /**
     * Gets the class string for a single named slot.
     *
     * @param string $slot The slot name to retrieve.
     *
     * @return ?string The class string for the slot, or null if not found.
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    public function get(string $slot): ?string
    {
        return $this->_slots[$slot] ?? null;
    }

    /**
     * Returns a new instance with the given slots replaced.
     *
     * Existing slots not present in the override array are preserved.
     * This method does not modify the current instance.
     *
     * @param array<string, string> $slots Slots to replace.
     *
     * @return self A new ClassList instance with overridden slots.
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    public function override(array $slots): self
    {
        return new self(
            array_merge($this->_slots, $slots),
            $this->_merger,
        );
    }

    /**
     * Returns a new instance with additional slots appended.
     *
     * If a slot name already exists, its value is extended (concatenated).
     * New slot names are added. This method does not modify the current instance.
     *
     * @param array<string, string> $slots Slots to extend or add.
     *
     * @return self A new ClassList instance with extended slots.
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    public function extend(array $slots): self
    {
        $merged = $this->_slots;

        foreach ($slots as $name => $value) {
            $merged[$name] = isset($merged[$name])
                ? $merged[$name] . ' ' . $value
                : $value;
        }

        return new self($merged, $this->_merger);
    }

    /**
     * Returns a new instance without the specified slots.
     *
     * This method does not modify the current instance.
     *
     * @param string ...$slots Slot names to remove.
     *
     * @return self A new ClassList instance without the specified slots.
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    public function without(string ...$slots): self
    {
        $filtered = array_diff_key($this->_slots, array_flip($slots));

        return new self($filtered, $this->_merger);
    }

    /**
     * Returns the merged string with additional classes appended.
     *
     * Combines all slot values with the additional string and runs
     * the result through the merge callable.
     *
     * @param string $additional Additional CSS classes to merge in.
     *
     * @return string The fully merged CSS class string.
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    public function merge(string $additional): string
    {
        $allClasses = implode(' ', array_values($this->_slots));

        return ($this->_merger)($allClasses, $additional);
    }

    /**
     * Returns the named slots as an associative array.
     *
     * @return array<string, string> The slot name-value pairs.
     *
     * @author CraftPulse
     * @since 1.0.0
     */
    public function toArray(): array
    {
        return $this->_slots;
    }
}
