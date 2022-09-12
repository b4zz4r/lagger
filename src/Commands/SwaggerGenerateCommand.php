<?php

namespace B4zz4r\LaravelSwagger\Commands;

use B4zz4r\LaravelSwagger\Concerns\DescriptionInterface;
use B4zz4r\LaravelSwagger\Concerns\RequestInterface;
use B4zz4r\LaravelSwagger\Concerns\ResourceInterface;
use B4zz4r\LaravelSwagger\Concerns\SpecificationInterface;
use B4zz4r\LaravelSwagger\Concerns\SummaryInterface;
use B4zz4r\LaravelSwagger\Concerns\TagInterface;
use B4zz4r\LaravelSwagger\DTOs\SpecificationDTO;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use ReflectionAttribute;
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

            $response = new ReflectionClass($returnTypeOfMethod);

            if (! $response->implementsInterface(ResourceInterface::class)) {
                throw new Exception("Instance '$returnTypeOfMethod' must implement " . ResourceInterface::class);
            }

            /** @var DescriptionInterface|null $description */
            $description = Arr::first($reflectionMethod->getAttributes(DescriptionInterface::class, ReflectionAttribute::IS_INSTANCEOF))?->newInstance();

            /** @var SummaryInterface|null $summary */
            $summary = Arr::first($reflectionMethod->getAttributes(SummaryInterface::class, ReflectionAttribute::IS_INSTANCEOF))?->newInstance();

            /** @var TagInterface|null $tag */
            $tag = Arr::first($reflectionClass->getAttributes(TagInterface::class, ReflectionAttribute::IS_INSTANCEOF))?->newInstance();

            $request = null;

            foreach ($reflectionMethod->getParameters() as $parameter) {
                $requestName = $parameter->getType()->getName();

                if (! class_exists($requestName)) {
                    continue;
                }

                $parameterClass = new ReflectionClass($requestName);

                if (! $parameterClass->implementsInterface(RequestInterface::class)) {
                    continue;
                }

                $request = $parameterClass;

                break;
            }

            $this->specifications[] = new SpecificationDTO([
                'route' => $route['uri'],
                'method' => $route['method'],
                'description' => $description?->getDescription(),
                'summary' => $summary?->getSummary(),
                'name' => $route['name'],
                'tag' => $tag->getTags(),
                'response' => $response->newInstance(),
                'request' => $request?->newInstance(),
            ]);
        }

        $this->generateSwaggerSpecification($this->specifications);
        $this->comment('All done');

        return self::SUCCESS;
    }

    /**
     * @param  array<SpecificationDTO>  $specifications
     * @return void
     */
    private function generateSwaggerSpecification(array $specifications = []): void
    {
        $info = config('laravel-swagger');
        $outputPath = Arr::pull($info, 'outputPath');
        $paths = [];

        foreach ($specifications as $specification) {
            $method = $this->resolveMethod($specification->method);

            $paths[$specification->route] = $paths[$specification->route] ?? [];
            $paths[$specification->route][$method] = $this->getContents($specification, $method);
        }

        $info['paths'] = $paths;

        $json = json_encode($info);
        file_put_contents($outputPath, $json);
    }

    private function getContents(SpecificationDTO $specification, string $method): array
    {
        $requestClass = $specification->request;

        dd($requestClass);

        $routeName = Str::after($specification['name'], '.');
        $name = Str::camel("$method $routeName");

        $schema = $this->resolveRequest($requestClass->rules());

        $contents = [
            'description' => $specification['description'],
            'summary' => $specification['summary'],
            'operationId' => $name,
            'tags' => [
                $specification['tag'],
            ],
            $this->getRequestSchemaKeyByMethod($method) => $this->getParametersFromSchema($schema, $method),
            'responses' => [],
            'requests' => [],
        ];

        $class = new ReflectionClass($specification['response']);
        dd($specification['response'], $class);
        $spec = $class->getMethod('specification');
        $idk =
            [
                'number_of_ppl_in_the_world' => 1234567890,
                'users' => [
                    'id' => 12,
                    'users' => 'Alex',
                ],
            ];

        return $contents;
    }

    private function filterRoute(array $route): ?array
    {
        if (! Str::of($route['uri'])->startsWith($this->prefixPath)) {
            return null;
        }

        return $route;
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
            foreach ($children as $rule) {
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

        $this->comment('All done');

        return $schema;
    }

    private function getRequestSchemaKeyByMethod($method): ?string
    {
        dd($method);
        return match ($method) {
            'GET', 'DELETE' => 'parameters',
            'POST', 'PUT', 'PATCH' => 'requestBody',
            default => throw new Exception("Unsupported method. {$method}"),
        };
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

    private function getTypeByMethod($method): string
    {
        return match ($method) {
            'GET', 'DELETE' => 'query',
            'POST' => 'header',
            default => 'requestBody'
        };
    }

    private function getRouteInformation(Route $route)
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
}
