<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        Validator::extend('csv_file', function ($attribute, $value, $parameters, $validator) {
            $extension = $value->getClientOriginalExtension();

            return in_array($extension, ['csv', 'xlsx', 'xls']);
        });
    }
}