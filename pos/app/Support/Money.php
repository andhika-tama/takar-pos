<?php

namespace App\Support;

class Money
{
    /** Basis points (10000 = 100%) of a rupiah amount, half-up, integer math only. */
    public static function percentOf(int $amount, int $bp): int
    {
        // Money is non-negative here (the DB enforces CHECK >= 0); a negative would round the wrong way.
        if ($amount < 0 || $bp < 0 || $bp > 10000) {
            throw new \InvalidArgumentException('percentOf needs amount >= 0 and basis points within 0..10000.');
        }

        // shortcut: amount * bp must fit in int (amount <= PHP_INT_MAX / 10000), true for all callers.
        $product = $amount * $bp;

        // Split quotient/remainder so adding the half never overflows.
        return intdiv($product, 10000) + ($product % 10000 >= 5000 ? 1 : 0);
    }
}
