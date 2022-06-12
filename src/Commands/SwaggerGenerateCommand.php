<?php

namespace B4zz4r\Swagger\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;

class SwaggerGenerateCommand extends Command
{
    public $signature = 'swagger:generate';

    public $description = 'My command';

    public $filterPath = 'api/v1/data-collection';

    public function __construct(private Router $router)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $routes = collect($this->router->getRoutes())->map(function ($route) {
            return $this->getRouteInformation($route);
        })->filter()->all();

        foreach ($routes as $route) {
            $reflectionClass = new \ReflectionClass($route['controller']);
            $reflectionMethod = $reflectionClass->getMethod($route['action']);
            dd($reflectionMethod->getParameters());
        }

        $this->comment('All done');

        return self::SUCCESS;
    }

    protected function getRouteInformation(Route $route)
    {
        $controllerWithAction = Str::of(ltrim($route->getActionName(), '\\'));

        return $this->filterRoute([
            'method' => implode('|', $route->methods()),
            'uri' => $route->uri(),
            'name' => $route->getName(),
            'controller' => $controllerWithAction->before('@')->value(),
            'action' => $controllerWithAction->after('@')->value(),
        ]);
    }

    private function filterRoute(array $route): ?array
    {
        if (! Str::of($route['uri'])->startsWith($this->filterPath)) {
            return null;
        }

        return $route;
    }
}
