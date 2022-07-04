<?php

namespace B4zz4r\LaravelSwagger\Commands;

use App\Http\Requests\SwaggerRequest;
use B4zz4r\LaravelSwagger\Swagger;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Psy\CodeCleaner\FunctionReturnInWriteContextPass;
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
        })->flatten(1)->filter()->all();

        // dd($routes);

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
            $route = $specification['route'];
            $method = $this->resolveMethod($specification['method']);

            $paths[$route] = $paths[$route] ?? [];
            $paths[$route][$method] = $this->getContents($specification);
        }

        $info['paths'] = $paths;

        // dd($info);
        $json = json_encode($info);
        file_put_contents($outputPath, $json);
        dd('done');
    }

    private function getContents($specification): array
    {
        $requestClass = $specification['requests'][0];
        $request = new $requestClass();
        // dd($request->rules());
        $schema = []; // todo
        $this->resolveRequest($request->rules(), $schema);
        dd($schema);

        return [
            'description' => 'BINGUS DRIPPIN',
            'summary' => 'BINGUS WAS HERE',
            'operationId' => 'getInformation',
            'tags' => [
                'pet',
            ],
            $this->getRequestSchemaKeyByMethod($specification['method']) => $schema,
            // 'responses' => $specification['response'],
            // 'requests' => $specification['requests'],
        ];
    }

    private function resolveRequest($rules)
    {   
        $propArray = [];
        foreach($rules as $key => $value)
        {
            if (strpos($value, "array") !== false) 
            {
                $rules[$key] = [];
                // var_dump($rules[$key]);
            }

            if(Str::contains($key, ".") && !Str::contains($key, "*") && strpos($value, "array") == false)
            {
                $propArray[$key] = $value;
            }

        }
        // $this->resolveRequest($propArray);
        dd($rules);
    }

    private function getRequestSchemaKeyByMethod($method): string
    {
        return match ($method) {
            'GET' => 'parameters',
            'DELETE' => 'parameters',
            'POST' => 'requestBody',
            'PATCH' => 'requestBody',
            'PUT'  => 'requestBody',
            default => 'requestBody'
        };
    }

    private function resolveMethod($methods)
    {
        return match ($methods) {
            'GET' => 'get',
            'POST' => 'post',
            'DELETE' => 'delete',
            'PUT' => 'put',
            default => 'unknow',
        };
    }

    protected function getRouteInformation(Route $route)
    {
        $controllerWithAction = Str::of(ltrim($route->getActionName(), '\\'));

        $methods = Arr::where($route->methods(), fn ($value) => $value !== 'HEAD');

        return Arr::map($methods, fn ($method) => $this->filterRoute([
            'method' => $method,
            'uri' => "/{$route->uri()}",
            'name' => $route->getName(),
            'controller' => $controllerWithAction->before('@')->value(),
            'action' => $controllerWithAction->after('@')->value(),
        ]));
    }

    private function filterRoute(array $route): ?array
    {
        if (! Str::of($route['uri'])->startsWith($this->prefixPath)) {
            return null;
        }

        return $route;
    }
}