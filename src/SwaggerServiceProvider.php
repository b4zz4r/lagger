<?php

namespace B4zz4r\Lagger;

use B4zz4r\Lagger\Commands\SwaggerGenerateCommand;
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
            ->name('lagger')
            ->hasConfigFile('lagger')
            ->hasCommand(SwaggerGenerateCommand::class);
    }
}
