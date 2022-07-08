<?php

namespace B4zz4r\LaravelSwagger\Commands;

use App\Http\Requests\SwaggerRequest;
use B4zz4r\LaravelSwagger\Swagger;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\Console\DumpCommand;
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

        $schema = $this->resolveRequest($request->rules()); // todo
        dd($this->resolveRequest($request->rules()));

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

    private function resolveRequest(array $rules)
    {
        $schema = [];
        $skip = [];

        foreach ($rules as $parentKey => $value) {
            if (in_array($parentKey, $skip)) {
                continue;
            }

            if (! Str::contains($value, 'array')) {
                $schema[$parentKey] = $this->generateSchemaByRules($value);

                Arr::forget($rules, $parentKey);
                continue;
            }

            if (Str::contains($value, 'array')) {
                if (Str::contains($parentKey, ".")) {
                    $tempChildern= Str::after($parentKey, ".");
                    $trueParentKey = Str::remove(".$tempChildern", $parentKey);
                }
                
                $children = collect($rules)
                    ->filter(fn ($item, $key) => Str::startsWith($key, "$parentKey."));

                array_push($skip, ... array_keys($children->toArray()));


                $children = $children
                    ->mapWithKeys(fn ($item, $key) => [Str::after($key, "$parentKey.") => $item])
                    ->toArray();

                $parentKey = $trueParentKey ?? $parentKey;
                $schema[$parentKey] = $this->generateSchemaByRules($value, $children);

                unset($children);
                continue;
            }
        }

        // dd($schema);
        return $schema;
    }

    private function generateSchemaByRules(array|string $rules, array $children = []): array
    {
        $schema = [
            'type' => null,
        ];
        // dump($rules);
        // dd($children);

        $rules = Str::contains($rules, '|') ? explode('|', $rules) : $rules;

        if (! empty($children)) {
            foreach ($children as $key => $rule) {
                $schema['properties'][$key] = $this->generateSchemaByRules($rule);
            }
        }

        foreach ($rules as $rule) {
            // dump($rule);
            if (Str::contains($rule, ['date', 'date_format', 'date_equals'])) {
                $schema['type'] = 'string';
                $schema['format'] = 'date';

                continue;
            }

            if ($rule === 'nullable') {
                $schema['nullable'] = true;

                continue;
            }

            if ($rule === 'numeric') {
                $schema['type'] = 'integer';

                continue;
            }

            if ($rule === 'string') {
                $schema['type'] = 'string';

                continue;
            }

            if(Str::contains($rule, "digits_between")) {
                $schema['maximum'] = Str::after($rule, ',');
                
                continue;
            }

            if(Str::contains($rule, "max")) {
                $schema['maxLength'] = Str::after($rule, ':');

                continue;
            }

            if(Str::contains($rule, "min")) {
                $schema['minLength'] = Str::after($rule, ':');

                continue;
            }

            if (count($children) && isset($children['*'])) {
                $schema['type'] = 'array';

                continue;
            }

            if ($schema['type'] === null) {
                $schema['type'] = 'object';
            }
        }

        return $schema;
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
