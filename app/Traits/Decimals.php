<?php

namespace App\Traits;

/**
 * BCMath decimal helpers (string-safe).
 * Designed for financial calculations.
 */
trait Decimals
{
    protected int $scale = 8;

    protected function decimal($value): string
    {
        return is_string($value) ? $value : (string) $value;
    }

    protected function addDecimal($a, $b): string
    {
        return bcadd($this->decimal($a), $this->decimal($b), $this->scale);
    }

    protected function subtractDecimal($a, $b): string
    {
        return bcsub($this->decimal($a), $this->decimal($b), $this->scale);
    }

    protected function multiplyDecimal($a, $b): string
    {
        return bcmul($this->decimal($a), $this->decimal($b), $this->scale);
    }

    /**
     * Compare two decimals
     * -1 = less, 0 = equal, 1 = greater
     */
    protected function compareDecimal($a, $b): int
    {
        return bccomp($this->decimal($a), $this->decimal($b), $this->scale);
    }

    protected function isLessThan($a, $b): bool
    {
        return $this->compareDecimal($a, $b) < 0;
    }

    protected function isGreaterThan($a, $b): bool
    {
        return $this->compareDecimal($a, $b) > 0;
    }

    protected function isZero($value): bool
    {
        return $this->compareDecimal($value, '0') === 0;
    }
}
