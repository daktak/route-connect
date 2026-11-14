<?php

namespace Tests\Unit;

use App\Models\GroupRide;
use PHPUnit\Framework\TestCase;

class GroupRideMeetingPointTest extends TestCase
{
    public function test_it_builds_a_map_url_from_coordinates(): void
    {
        $ride = new GroupRide([
            'meeting_point_lat' => '-37.81750000',
            'meeting_point_lng' => '144.96900000',
        ]);

        $this->assertSame(
            'https://www.google.com/maps/search/?api=1&query=-37.8175%2C144.969',
            $ride->meetingPointMapUrl()
        );
    }

    public function test_it_returns_null_without_coordinates(): void
    {
        $ride = new GroupRide(['meeting_point_name' => 'Fed Square']);

        $this->assertNull($ride->meetingPointMapUrl());
    }

    public function test_it_handles_zero_coordinates(): void
    {
        $ride = new GroupRide([
            'meeting_point_lat' => '0.00000000',
            'meeting_point_lng' => '0.00000000',
        ]);

        $this->assertSame(
            'https://www.google.com/maps/search/?api=1&query=0%2C0',
            $ride->meetingPointMapUrl()
        );
    }
}
