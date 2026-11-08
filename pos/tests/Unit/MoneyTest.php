<?php

namespace Tests\Unit;

use App\Support\Money;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    public function test_percent_rounds_half_up_to_the_rupiah(): void
    {
        $this->assertSame(1, Money::percentOf(10, 500)); // 0.5 -> 1
        $this->assertSame(0, Money::percentOf(9, 500));  // 0.45 -> 0
        $this->assertSame(2, Money::percentOf(15, 1000)); // 1.5 -> 2
        $this->assertSame(1, Money::percentOf(14, 1000)); // 1.4 -> 1
    }

    public function test_percent_edges(): void
    {
        $this->assertSame(0, Money::percentOf(0, 500));
        $this->assertSame(0, Money::percentOf(12500, 0));
        $this->assertSame(12500, Money::percentOf(12500, 10000));
        $this->assertSame(625, Money::percentOf(12500, 500));
    }

    public function test_percent_is_exact_integer_math_near_the_integer_limit(): void
    {
        $max = intdiv(PHP_INT_MAX, 10000);
        $this->assertSame(922337203685477, $max);

        $this->assertSame($max, Money::percentOf($max, 10000));
        // 922337203685477 / 2 = 461168601842738.5 -> up. A float would lose this (> 2^53).
        $this->assertSame(461168601842739, Money::percentOf($max, 5000));
        // 922337203685477 * 0.0001 = 92233720368.5477 -> 92233720369
        $this->assertSame(92233720369, Money::percentOf($max, 1));
    }

    #[DataProvider('invalidPercents')]
    public function test_percent_rejects_negative_amounts_and_out_of_range_basis_points(int $amount, int $bp): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Money::percentOf($amount, $bp);
    }

    public static function invalidPercents(): array
    {
        return [[-16, 1000], [-1, 0], [100, -1], [100, 10001]];
    }

    #[DataProvider('formats')]
    public function test_rupiah_and_angka(int $n, string $rupiah, string $angka): void
    {
        $this->assertSame($rupiah, rupiah($n));
        $this->assertSame($angka, angka($n));
    }

    public static function formats(): array
    {
        return [
            [0, 'Rp0', '0'],
            [500, 'Rp500', '500'],
            [12500, 'Rp12.500', '12.500'],
            [1250000, 'Rp1.250.000', '1.250.000'],
        ];
    }

    public function test_dates_format_in_indonesian(): void
    {
        $this->assertSame('Senin, 2 Maret 2026', Carbon::parse('2026-03-02')->translatedFormat('l, j F Y'));
    }
}
