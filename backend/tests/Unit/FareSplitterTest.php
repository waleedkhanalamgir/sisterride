<?php

namespace Tests\Unit;

use App\Services\FareSplitter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class FareSplitterTest extends TestCase
{
    public function test_deck_example_9_6_3_km_on_rs_600(): void
    {
        $r = (new FareSplitter())->split(600, ['a' => 9, 'b' => 6, 'c' => 3], 10);

        $this->assertSame(['a' => 300, 'b' => 200, 'c' => 100], $r['shares']);
        $this->assertSame(60, $r['commission']);
        $this->assertSame(540, $r['driver_earning']);
    }

    public function test_shares_always_add_up_to_total(): void
    {
        $r = (new FareSplitter())->split(500, ['a' => 7, 'b' => 7, 'c' => 7], 5);

        $this->assertSame(500, array_sum($r['shares']));
    }

    public function test_total_fare_from_rate_card(): void
    {
        $this->assertSame(240, (new FareSplitter())->totalFare(60, 20, 9));
    }

    public function test_rejects_empty_group(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new FareSplitter())->split(600, [], 10);
    }
}
