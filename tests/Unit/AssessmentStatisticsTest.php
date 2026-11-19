<?php

namespace Tests\Unit;

use App\Services\AssessmentStatistics;
use PHPUnit\Framework\TestCase;

class AssessmentStatisticsTest extends TestCase
{
    public function test_statistics_handle_empty_unique_repeated_and_multimodal_scores(): void
    {
        $empty = AssessmentStatistics::metrics([]);
        $this->assertNull($empty['mean']);
        $this->assertNull($empty['mps']);
        $this->assertSame([], $empty['modes']);
        $metrics = AssessmentStatistics::metrics(array_map(fn (int $score): array => ['score' => $score, 'total' => 10], [2, 4, 2, 4, 8]));
        $this->assertSame(4.0, $metrics['mean']);
        $this->assertSame(4.0, $metrics['median']);
        $this->assertSame([2, 4], $metrics['modes']);
        $this->assertSame(40.0, $metrics['mps']);
        $this->assertSame(10, $metrics['total']);
        $unique = AssessmentStatistics::metrics([['score' => 1, 'total' => 4], ['score' => 4, 'total' => 4]]);
        $this->assertSame(2.5, $unique['median']);
        $this->assertSame([], $unique['modes']);
    }

    public function test_mps_normalizes_each_result_and_invalid_totals_are_excluded(): void
    {
        $metrics = AssessmentStatistics::metrics([['score' => 1, 'total' => 1], ['score' => 0, 'total' => 9], ['score' => 0, 'total' => 0], ['score' => 11, 'total' => 10]]);
        $this->assertSame(2, $metrics['count']);
        $this->assertSame(50.0, $metrics['mps']);
        $this->assertNull($metrics['total']);
    }
}
