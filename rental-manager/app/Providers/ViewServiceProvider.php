<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ViewServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped('business.settings', fn () => Setting::first());
    }

    public function boot(): void
    {
        View::composer('*', function ($view) {
            $view->with('business', app('business.settings'));
        });
    }
}
