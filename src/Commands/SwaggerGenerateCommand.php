<?php

namespace B4zz4r\LaravelSwagger\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use ReflectionClass;

class SwaggerGenerateCommand extends Command
{
    public $signature = 'swagger:generate';

    public $description = 'My command';

    public $prefixPath = '/special';

    public $specifications = [];

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
            $reflectionClass = new ReflectionClass($route['controller']);
            $reflectionMethod = $reflectionClass->getMethod($route['action']);
            $returnTypeOfMethod = $reflectionMethod->getReturnType()?->getName();

            if (! class_exists($returnTypeOfMethod)) {
                throw new Exception("Return type of '{$route['controller']}:{$route['action']}' must be class.");
            }

            $returnClass = new ReflectionClass($returnTypeOfMethod);

            if (! $returnClass->hasMethod('specification')) {
                throw new Exception("Instance '$returnTypeOfMethod' must have 'specification' methods.");
            }

            dd($returnClass->getMethod('specification')->invoke(null));

            Arr::set($this->specifications, $route['uri'], [
                'requests' => [$returnClass],
            ]);

            dd($this->specifications);

            foreach ($reflectionMethod->getParameters() as $parameter) {
                $type = $parameter->getType()->getName();

                if (! class_exists($type)) {
                    continue;
                }

                $parameterClass = new ReflectionClass($type);

                if (! $parameterClass->hasMethod('rules')) {
                    continue;
                }

                if ($rc->hasMethod("rules") && $rc2->hasMethod("specification")) {
                    $specifications["/test"]["request"] = $rc;
                    $specifications["/test"]["response"] = $rc2;
                }
            }
        }
        $this->comment('All done');

        return self::SUCCESS;
    }

    protected function getRouteInformation(Route $route)
    {
        $controllerWithAction = Str::of(ltrim($route->getActionName(), '\\'));

        return $this->filterRoute([
            'method' => implode('|', $route->methods()),
            'uri' => "/{$route->uri()}",
            'name' => $route->getName(),
            'controller' => $controllerWithAction->before('@')->value(),
            'action' => $controllerWithAction->after('@')->value(),
        ]);
    }

    private function filterRoute(array $route): ?array
    {
        if (! Str::of($route['uri'])->startsWith($this->prefixPath)) {
            return null;
        }

        return $route;
    }
}
