<?php

namespace App\Providers;

use App\Contracts\HerdRepository;
use App\Exceptions\DomainError;
use App\Repositories\FileHerdRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(HerdRepository::class, function ($app) {
            // No authentication exists yet: do not accidentally serve demo records in production.
            if (! $app->environment(['local', 'testing']) || config('herd.store') !== 'demo-file') {
                throw new DomainError(503, 'storage_not_configured',
                    'Configure production storage and authentication before serving herd records');
            }

            return new FileHerdRepository(config('herd.demo_path'));
        });
    }
}
