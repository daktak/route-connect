<?php

namespace Tests\Feature;

use App\Http\Controllers\RideController;
use App\Models\GroupRide;
use App\Models\GroupRideRouteOption;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View as ViewFacade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RideShowViewDataTest extends TestCase
{
    public static function routeOptionProvider(): array
    {
        return [
            'single route' => [1, false],
            'multiple routes' => [2, true],
        ];
    }

    #[DataProvider('routeOptionProvider')]
    public function test_show_passes_multiple_routes_flag_to_the_view(int $optionCount, bool $expected): void
    {
        $ride = new class extends GroupRide
        {
            public function load($relations)
            {
                return $this;
            }
        };
        $ride->ride_date = Carbon::now()->subDay();
        $ride->setRelation(
            'routeOptions',
            collect(range(1, $optionCount))->map(fn (int $sortOrder) => new GroupRideRouteOption(['sort_order' => $sortOrder]))
        );

        $view = \Mockery::mock(View::class);
        ViewFacade::shouldReceive('make')
            ->once()
            ->with('rides.show', \Mockery::on(fn (array $data) => $data['ride'] === $ride && $data['multipleRoutes'] === $expected), [])
            ->andReturn($view);

        $this->assertSame($view, (new RideController)->show($ride));
    }
}
