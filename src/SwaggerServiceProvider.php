<?php

namespace B4zz4r\Swagger;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use B4zz4r\Swagger\Commands\SwaggerGenerateCommand;

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
            ->hasConfigFile()
            ->hasCommand(SwaggerGenerateCommand::class);
    }
}
