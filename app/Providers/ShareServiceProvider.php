<?php

namespace App\Providers;

use App\Services\Share\Providers\BoutiqueShareProvider;
use App\Services\Share\Providers\CentreShareProvider;
use App\Services\Share\Providers\EventShareProvider;
use App\Services\Share\Providers\GroupShareProvider;
use App\Services\Share\Providers\MaterialShareProvider;
use App\Services\Share\Providers\ZoneShareProvider;
use App\Services\Share\SharePreviewManager;
use Illuminate\Support\ServiceProvider;

class ShareServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SharePreviewManager::class, fn () => new SharePreviewManager([
            new CentreShareProvider(),
            new ZoneShareProvider(),
            new EventShareProvider(),
            new MaterialShareProvider(),
            new BoutiqueShareProvider(),
            new GroupShareProvider(),
        ]));
    }
}
