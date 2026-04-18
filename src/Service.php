<?php
declare(strict_types=1);

namespace qs9000\thinkuuidv7;

use think\Service as BaseService;

/**
 * UUIDv7 Service Provider
 */
class Service extends BaseService
{
    public function register(): void
    {
        $this->app->bind('uuidv7', UuidV7Manager::class);
    }

    public function boot(): void
    {
        // Register console command
        $this->commands([
            'uuidv7' => command\UuidV7Command::class,
        ]);
    }
}
