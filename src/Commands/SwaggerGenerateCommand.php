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

        // dd($paths);
        $json = json_encode($info);
        file_put_contents($outputPath, $json);
        dd('done');
    }

    private function getContents($specification): array
    {
        $requestClass = $specification['requests'][0];
        $request = new $requestClass();

        $schema = $this->resolveRequest($request->rules());
        // dd($schema);

        $contents = [
            'description' => 'BINGUS DRIPPIN',
            'summary' => 'BINGUS WAS HERE',
            'operationId' => 'getInformation',
            'tags' => [
                'pet',
            ],
            'parameters' => $this->getParametersFromSchema($schema, $specification['method']),
            // $this->getRequestSchemaKeyByMethod($specification['method']) => $this->getRequestBody($schema),

            'responses' => [
                200 => [
                    'description' => 'successful operation',
                    'content' => [
                        'application/xml' => [
                            'schema' => [
                                'type' => 'object',
                            ]
                        ]
                    ]
                ]
            ],
            // 'requests' => $specification['requests'],
        ];

        if($specification['method'] == 'POST') {
            $contents['requestBody'] = $this->getRequestBody($schema);
        }

        return $contents;
    }

    private function getRequestBody($schema): array
    {
        return [
                'content' => [
                    'application/x-www-form-urlencoded' => 
                        [
                            'schema' => 
                            [
                                'properties' => $schema
                            ]
                        ]
                    ]
        ];
    }

    private function getParametersFromSchema($schema, $method = []): array
    {
        $arrayOfParameters = [];
        $parameters = [
            'in' => $this->getTypeByMethod($method),
            'name' => null,
            'required' => true,
            'schema' => null
        ]; 
        // dump($schema);

        foreach($schema as $schemaKey => $schemaValue) {
            $parameters = collect($parameters)
            ->transform(function ($item, $key) use ($schemaKey, $schemaValue){
                if ($key == 'name') {
                    $item = $schemaKey;
                }

                if ($key == 'schema') {
                    $item = $schemaValue;
                }
                return $item;
            })
            ->toArray();
            array_push($arrayOfParameters, $parameters);
        }

        return $arrayOfParameters;
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
                $children = collect($rules)
                    ->filter(fn ($item, $key) => Str::startsWith($key, "$parentKey."));

                array_push($skip, ... array_keys($children->toArray()));

                $children = $children
                ->mapWithKeys(fn ($item, $key) => [Str::after($key, "$parentKey.") => $item])
                ->toArray();

                // get array of children with removed '*'
                $cleanChildKey = collect($children)
                ->filter(fn ($item, $key) => Str::contains($key, '*'))
                ->transform(fn ($item, $key) => Str::remove('.*', $key))
                ->values()
                ->all();


                // filter childern with '*'
                $childrenWithStar = collect($children)
                ->filter(fn ($item, $key) => Str::contains($key, '*'))
                ->mapWithKeys(fn ($item, $key) => [Str::after($key, ".") => $item])
                ->toArray();

                $children = $childrenWithStar ? $childrenWithStar : $children;
                $schema[$parentKey] = $this->generateSchemaByRules($value, $children, $cleanChildKey);

                unset($children);
                continue;
            }
        }

        // dd($schema);
        return $schema;
    }

    private function generateSchemaByRules(array|string $rules, array $children = [], $propertyKey = null): array
    {
        $schema = [
            'type' => null,
        ];

        if (!Str::contains($rules, 'array')) {
            $nullable = Str::before($rules, '|');
            $rules = Str::remove($nullable, $rules);
        }

        $rules = Str::contains($rules, '|') ? explode('|', $rules) : $rules;

        if (! empty($children) && isset($children['*'])) {
            foreach ($children as $key => $rule) {
                $schema['properties'] = $this->generateSchemaByRules($rule);

            }
        }

        if (! empty($children) && !isset($children['*'])) {
            foreach ($children as $key => $rule) {
                $schema['properties'][$key] = $this->generateSchemaByRules($rule);

            }
        }



        foreach ($rules as $rule) {
            if ($schema['type'] === null) {
                $schema['type'] = 'object';
            }

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
                for($index = 0; $index < count($propertyKey); $index++) {
                    unset($schema['properties']['type']);
                    $schema['properties'][$propertyKey[$index]]['type'] = 'array';
                    $schema['properties'][$propertyKey[$index]]['items']['format'] = 'binary';
                }

                continue;
            }


        }

        return $schema;
    }

    private function getRequestSchemaKeyByMethod($method)
    {
       
        return match ($method) {
            'GET' => 'parameters',
            'DELETE' => 'parameters',
            'POST' => 'requestBody',
            'PATCH' => 'requestBody',
            'PUT'  => 'requestBody',
            default => null
        };
    }

    private function getTypeByMethod($method): string {

        return match ($method) {
            'GET' => 'query',
            'POST' => 'header',
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
