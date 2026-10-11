<?php

namespace App\Console\Commands;

use App\Models\Route;
use App\Services\SurfaceAnalyzer;
use Illuminate\Console\Command;

class AnalyzeRouteSurfaces extends Command
{
    protected $signature = 'routes:analyze-surface {route? : Analyze a single route id} {--missing : Only routes without surface data} {--force : Recompute routes that already have data}';

    protected $description = 'Compute paved/gravel surface percentages for routes from OpenStreetMap data';

    public function handle(SurfaceAnalyzer $analyzer): int
    {
        $query = Route::query()->whereNotNull('geometry');

        if ($id = $this->argument('route')) {
            $query->whereKey($id);
        } elseif ($this->option('missing') || ! $this->option('force')) {
            $query->whereNull('surface_analyzed_at');
        }

        $routes = $query->get();

        if ($routes->isEmpty()) {
            $this->info('No routes to analyze.');

            return self::SUCCESS;
        }

        $analyzed = 0;
        $failed = 0;

        $this->withProgressBar($routes, function (Route $route) use ($analyzer, &$analyzed, &$failed) {
            if ($analyzer->updateRoute($route)) {
                $analyzed++;
            } else {
                $failed++;
            }
        });

        $this->newLine(2);
        $this->info("Analyzed {$analyzed} route(s), {$failed} failed.");

        return self::SUCCESS;
    }
}
