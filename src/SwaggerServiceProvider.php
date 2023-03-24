<?php

namespace B4zz4r\LaravelSwagger;

use B4zz4r\LaravelSwagger\Commands\SwaggerGenerateCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class SwaggerServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */

        $package
            ->name('laravel-swagger')
            ->hasConfigFile('laravel-swagger')
            ->hasCommand(SwaggerGenerateCommand::class);
    }
}
