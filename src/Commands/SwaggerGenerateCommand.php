<?php

namespace B4zz4r\LaravelSwagger\Commands;

use App\Http\Controllers\SwaggerController;
use App\Http\Resources\SwaggerResource;
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

    public ?string $prefixPath = '/test';

    public array $specifications = [];

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
            $reflectionAttributes = $reflectionClass->getMethods()[1]->getAttributes();

            // array of description and summary
            $array = [
                'description' => null,
                'summary' => null,
            ];

            foreach ($reflectionAttributes as $reflectionAttribute) {
                if (Str::contains($reflectionAttribute->getName(), 'Description')) {
                    $array['description'] = $reflectionAttribute->getArguments()[0];
                }

                if (Str::contains($reflectionAttribute->getName(), 'Summary')) {
                    $array['summary'] = $reflectionAttribute->getArguments()[0];
                }
            }

            $attributes = $reflectionClass->getAttributes('B4zz4r\LaravelSwagger\Attribute\SwaggerTag');
            $attributes = $attributes[0]->getArguments();
            foreach ($attributes as $attributeKey => $attributeValue) {
                $tag = $attributeValue;
            }

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
                'description' => $array['description'],
                'summary' => $array['summary'],
                'name' => $route['name'],
                'tag' => $tag,
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
    }

    private function getContents($specification): array
    {
        $requestClass = $specification['requests'][0];
        $request = new $requestClass();

        $method = $this->resolveMethod($specification['method']);
        $routeName = Str::after($specification['name'], '.');
        $name = Str::camel("$method $routeName");

        $schema = $this->resolveRequest($request->rules());

        $contents = [
            'description' => $specification['description'],
            'summary' => $specification['summary'],
            'operationId' => $name,
            'tags' => [
                $specification['tag'],
            ],
            $this->getRequestSchemaKeyByMethod($specification['method']) => $this->getParametersFromSchema($schema, $specification['method']),
            // 'responses' => SwaggerResource::specification(307, $specification['response'])

            'responses' => [
                200 => [
                    'description' => 'successful operation',
                    'content' => [
                        'application/xml' => [
                            'schema' => [
                                'type' => "object",
                            ],
                        ],
                    ],
                ],
            ],
            // 'requests' => dd($specification['requests']),
        ];

        $class = new ReflectionClass($specification['response']);
        $spec = $class->getMethod('specification');
        $idk =
            [
                'number_of_ppl_in_the_world' => 1234567890,
                'users' => [
                    'id' => 12,
                    'users' => 'Alex',
                ],
            ];
        dd(SwaggerResource::specification($idk));
        // dd($class->getMethod('getDescriptionByRespondCode'));

        return $contents;
    }

    private function getParametersFromSchema($schema, $method = []): array
    {
        if ($method == 'GET' || $method == 'DELETE') {
            $arrayOfParameters = [];
            $parameters = [
                'in' => $this->getTypeByMethod($method),
                'name' => null,
                'required' => true,
                'schema' => null,
            ];

            foreach ($schema as $schemaKey => $schemaValue) {
                $parameters = collect($parameters)
                    ->transform(function ($item, $key) use ($schemaKey, $schemaValue) {
                        return match ($key) {
                            'name' => $schemaKey,
                            'schema' => $schemaValue,
                            'in' => $item,
                            'required' => $item,
                            default => throw new Exception("Missing key - $key"),
                        };
                    })
                    ->toArray();

                $arrayOfParameters[] = $parameters;
            }

            return $arrayOfParameters;
        } else {
            return [
                'content' => [
                    'application/x-www-form-urlencoded' => [
                        'schema' => [
                            'properties' => $schema,
                        ],
                    ],
                ],
            ];
        }
    }

    private function resolveRequest(array $rules): array
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
                    ->filter(fn($item, $key) => Str::startsWith($key, "$parentKey."));

                array_push($skip, ...array_keys($children->toArray()));

                $children = $children
                    ->mapWithKeys(fn($item, $key) => [Str::after($key, "$parentKey.") => $item])
                    ->toArray();

                // get array of children with removed '*'
                $pureChildKey = collect($children)
                    ->filter(fn($item, $key) => Str::contains($key, '*'))
                    ->transform(fn($item, $key) => Str::remove('.*', $key))
                    ->values()
                    ->all();

                // filter childern with '*'
                $childrenWithStar = collect($children)
                    ->filter(fn($item, $key) => Str::contains($key, '*'))
                    ->mapWithKeys(fn($item, $key) => [Str::after($key, ".") => $item])
                    ->toArray();

                $children = $childrenWithStar ? $childrenWithStar : $children;
                $schema[$parentKey] = $this->generateSchemaByRules($value, $children, $pureChildKey);

                unset($children);
            }
        }

        return $schema;
    }

    private function generateSchemaByRules(array|string $rules, array $children = [], $childKey = null): array
    {
        $schema = [
            'type' => null,
        ];

        if (! Str::contains($rules, 'array')) {
            $nullable = Str::before($rules, '|');
            $rules = Str::remove($nullable, $rules);
        }

        $rules = Str::contains($rules, '|') ? explode('|', $rules) : $rules;

        if (! empty($children) && isset($children['*'])) {
            foreach ($children as $key => $rule) {
                $schema['properties'] = $this->generateSchemaByRules($rule);
            }
        }

        if (! empty($children) && ! isset($children['*'])) {
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

            if (Str::contains($rule, "digits_between")) {
                $digits = \explode(',', Str::after($rule, ':'));
                $schema['minimum'] = (int) $digits[0];
                $schema['maximum'] = (int) $digits[1];

                continue;
            }

            if (Str::contains($rule, "max")) {
                $schema['maxLength'] = (int) Str::after($rule, ':');

                continue;
            }

            if (Str::contains($rule, "min")) {
                $schema['minLength'] = (int) Str::after($rule, ':');

                continue;
            }

            if (count($children) && isset($children['*'])) {
                for ($index = 0; $index < count($childKey); $index++) {
                    unset($schema['properties']['type']);
                    $schema['properties'][$childKey[$index]]['type'] = 'array';
                    $schema['properties'][$childKey[$index]]['items']['format'] = 'binary';
                }
            }
        }

        return $schema;
    }

    private function getRequestSchemaKeyByMethod($method): ?string
    {
        return match ($method) {
            'GET', 'DELETE' => 'parameters',
            'POST', 'PUT', 'PATCH' => 'requestBody',
            default => throw new Exception("Unsupported method. {$method}"),
        };
    }

    private function getTypeByMethod($method): string
    {
        return match ($method) {
            'GET', 'DELETE' => 'query',
            'POST' => 'header',
            default => 'requestBody'
        };
    }

    private function resolveMethod($methods): string
    {
        return match ($methods) {
            'GET' => 'get',
            'POST' => 'post',
            'DELETE' => 'delete',
            'PUT' => 'put',
            default => 'unknown',
        };
    }

    protected function getRouteInformation(Route $route)
    {
        $controllerWithAction = Str::of(ltrim($route->getActionName(), '\\'));

        $methods = Arr::where($route->methods(), fn($value) => $value !== 'HEAD');

        return Arr::map($methods, fn($method) => $this->filterRoute([
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
