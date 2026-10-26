<?php

namespace App\Providers;

use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\DatabaseManager;
use Libsql\Laravel\Database\LibsqlConnection;
use Libsql\Laravel\Database\LibsqlConnector;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register only libsql; the upstream provider replaces the factory for
        // every driver, including ordinary SQLite and PostgreSQL connections.
        $this->app->resolving('db', function (DatabaseManager $db): void {
            $db->extend('libsql', function (array $config, string $name) {
                $config['url'] = $config['turso_url'] ?? null;
                if (($config['database'] ?? null) !== ':memory:' && (empty($config['url']) || empty($config['password']))) {
                    throw new \InvalidArgumentException('TURSO_DATABASE_URL and TURSO_AUTH_TOKEN are required for remote libsql.');
                }
                $config['name'] = $name;
                $database = $this->app->make(LibsqlConnector::class)->connect($config);

                return new LibsqlConnection($database, $config['database'] ?? ':memory:', $config['prefix'] ?? '', $config);
            });
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(UrlGenerator $url): void
    {
        if ($this->app->environment('production')) {
            $url->forceScheme('https');
        }
    }
}
