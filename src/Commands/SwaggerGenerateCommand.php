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

    public $prefixPath = '/test';

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

            $requests = [];
            foreach ($reflectionMethod->getParameters() as $parameter) {
                $type = $parameter->getType()->getName();
                if (! class_exists($type)) {
                    continue;
                }

                $parameterClass = new ReflectionClass($type);
                if (! $parameterClass->hasMethod('rules')) {
                    continue;
                }

                $requests[] = $type;
            }

            $this->specifications[] = [
                'route' => $route['uri'],
                'method' => $route['method'],
                'response' => $returnTypeOfMethod,
                'requests' => $requests,
            ];
        }

        $this->generateSwaggerSpecification($this->specifications);
        $this->comment('All done');
        return self::SUCCESS;
    }

    protected function generateSwaggerSpecification(array $specifications = []): void
    {
        // @TODO save JSON file
        $info = config('laravel-swagger');
        $outputPath = Arr::pull($info, 'outputPath');
        
        $paths = [];
        /**
         * @var array $specification
         */
        foreach ($specifications as $specification) {
            $paths =  Arr::add($paths, $specification['route'], [$this->resolveMethod($specification['method']) => [
                'description' => 'BINGUS DRIPPIN',
                'summary' => 'BINGUS WAS HERE',
                'operationId' => 'getInformation',
                'tags' => [
                    'pet',
                ],
                'parameters' => 
                [
                    [
                        'name' => 'BINGUS',
                        'in' => 'query',
                        'description' => 'STATUS OF BINGUS',
                        'required' => false,
                        'style' => 'form',
                        'explode' => true,
                        'schema' => [
                           'type' => 'boolean'
                        ]
                    ]
                ],
                'responses' => $specification['response'],


            ]]);
        }

        $info['paths'] = $paths;
        // dd($info);

        $json = json_encode($info);
        file_put_contents($outputPath, $json);
        dd('done');
    }

    private function resolveMethod(string $method): string
    {
        return match ($method) {
            'GET|HEAD' => 'get',
            default => 'unknow',
        };
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