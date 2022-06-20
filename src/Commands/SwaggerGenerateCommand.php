<?php

namespace B4zz4r\Swagger\Commands;

use App\Http\Requests\idkRequest;
use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;

class SwaggerGenerateCommand extends Command
{
    public $signature = 'swagger:generate';

    public $description = 'My command';

    public $filterPath = 'test';

    public $specifications = [
        '/test' => [
            'request' => null,
            'response' => null,
         ],
    ];

    public function __construct(private Router $router)
    {
        parent::__construct();
    }

    public function handle(idkRequest $request): int
    {
        $routes = collect($this->router->getRoutes())->map(function ($route) {
            return $this->getRouteInformation($route);
        })->filter()->all();

        foreach ($routes as $route) {
            $reflectionClass = new \ReflectionClass($route['controller']);
            $reflectionMethod = $reflectionClass->getMethod($route['action']);
            $resourceClass = $reflectionMethod->getReturnType()->getName();
            $rc2 = new \ReflectionClass($resourceClass);

            foreach ($reflectionMethod->getParameters() as $parameter) {
                $requestClass = $parameter->getType()->getName();
                $rc = new \ReflectionClass($requestClass);

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
