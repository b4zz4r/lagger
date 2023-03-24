<?php

namespace B4zz4r\LaravelSwagger\Commands;

use B4zz4r\LaravelSwagger\Concerns\DescriptionInterface;
use B4zz4r\LaravelSwagger\Concerns\PropertyDataInterface;
use B4zz4r\LaravelSwagger\Concerns\RequestInterface;
use B4zz4r\LaravelSwagger\Concerns\ResourceInterface;
use B4zz4r\LaravelSwagger\Concerns\SpecificationInterface;
use B4zz4r\LaravelSwagger\Concerns\SummaryInterface;
use B4zz4r\LaravelSwagger\Concerns\TagInterface;
use B4zz4r\LaravelSwagger\Data\ArrayPropertyData;
use B4zz4r\LaravelSwagger\Data\BooleanPropertyData;
use B4zz4r\LaravelSwagger\Data\EnumPropertyData;
use B4zz4r\LaravelSwagger\Data\IntegerPropertyData;
use B4zz4r\LaravelSwagger\Data\SpecificationData;
use B4zz4r\LaravelSwagger\Data\StringPropertyData;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\RequiredIf;
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

            if ($request === null) {
                throw new Exception($route['controller'] . '@' . $route['action'] .  ' dont have any parameter which implement ' . RequestInterface::class);
            }

            $this->specifications[] = new SpecificationData(
                name: $route['name'],
                route: $route['uri'],
                method: $route['method'],
                request: $request->newInstance(),
                response: $response->newInstance(null),
                description: $description,
                summary: $summary,
                tag: $tag,
            );
        }

        $this->generateSwaggerSpecification($this->specifications);
        $this->comment('All done');

        return self::SUCCESS;
    }

    /**
     * @param  array<SpecificationData>  $specifications
     * @return void
     */
    private function generateSwaggerSpecification(array $specifications = []): void
    {
        /** @var array $info */
        $info = config('laravel-swagger', []);
        $outputPath = Arr::pull($info, 'outputPath') ?? \public_path();
        $paths = [];

        foreach ($specifications as $specification) {
            $method = Str::lower($specification->method);

            $paths[$specification->route] = $paths[$specification->route] ?? [];
            $paths[$specification->route][$method] = $this->getOpenApiSpecification($specification, $method);
        }

        $info['paths'] = $paths;

        $json = json_encode($info);
        file_put_contents($outputPath, $json);
    }

    /**
     * Getting (generate) OpenAPI 3 Specification by Specification
     */
    private function getOpenApiSpecification(SpecificationData $data, string $method): array
    {
        $requestClass = $data->request;
        $operationId = Str::camel("$method ") . Str::after($data->name, '.');

        return [
            'description' => $data->description?->getDescription(),
            'summary' => $data->summary?->getSummary(),
            'operationId' => $operationId,
            'tags' => $data->tag?->getTags(),
            $this->getRequestSchemaKeyByMethod($method) => $this->getSchemaByRequest($requestClass, $method),
            'responses' => [
                '200' => $this->getSchemaByResource($data->response),
            ],
        ];
    }

    private function filterRoute(array $route): ?array
    {
        if (! Str::of($route['uri'])->startsWith($this->prefixPath)) {
            return null;
        }

        return $route;
    }

    private function resolveRequestParameters(array $rules): array
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

                // filter children with '*'
                $childrenWithStar = collect($children)
                    ->filter(fn($item, $key) => Str::contains($key, '*'))
                    ->mapWithKeys(fn($item, $key) => [Str::after($key, ".") => $item])
                    ->toArray();

                $children = count($childrenWithStar) ? $childrenWithStar : $children;
                $schema[$parentKey] = $this->generateSchemaByRules($value, $children, $pureChildKey);

                unset($children);
            }
        }

        return $schema;
    }

    private function generateSchemaByRules(mixed $rules, array $children = [], $childKey = null): array
    {
        $schema = [
            'type' => null,
            'properties' => [],
            'required' => [],
        ];

        $rules = Str::contains($rules, '|') ? explode('|', $rules) : $rules;

        if (! empty($children)) {
            if (isset($children['*'])) {
                foreach ($children as $rule) {
                    $schema['properties'] = $this->generateSchemaByRules($rule);
                }
            } else {
                foreach ($children as $key => $rule) {
                    $schema['properties'][$key] = $this->generateSchemaByRules($rule);
                }
            }

            $schema['required'] = array_keys(Arr::where($schema['properties'], fn ($item) => $item['required'] ?? false));
        }

        foreach ($rules as $rule) {
            if ($rule instanceof RequiredIf) {

            }


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

            if (Str::contains($rule, 'digits_between')) {
                $digits = \explode(',', Str::after($rule, ':'));
                $schema['minimum'] = (int) $digits[0];
                $schema['maximum'] = (int) $digits[1];

                continue;
            }

            if (Str::contains($rule, 'max')) {
                $schema['maxLength'] = (int) Str::after($rule, ':');

                continue;
            }

            if (Str::contains($rule, 'min')) {
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

            if (Str::startsWith($rule, ['required', 'required_'])) {
                $schema['required'] = true;
                $schema['description'] = $rule;
            }
        }

        return $schema;
    }

    private function getRequestSchemaKeyByMethod($method): ?string
    {
        return match (Str::lower($method)) {
            'get', 'delete' => 'parameters',
            'post', 'put', 'patch' => 'requestBody',
            default => throw new Exception("Unsupported method. {$method}"),
        };
    }

    private function getSchemaByRequest(RequestInterface $request, string $method): array
    {
        $requestSchema = $this->resolveRequestParameters($request->rules());
        $attributes = (new ReflectionClass($request))->getMethod('rules')->getAttributes();
        dd($attributes);

        if ($method == 'GET' || $method == 'DELETE') {
            $arrayOfParameters = [];
            $parameters = [
                'in' => $this->getTypeByMethod($method),
                'name' => null,
                'required' => true,
                'schema' => null,
            ];

            foreach ($requestSchema as $schemaKey => $schemaValue) {
                $parameters = collect($parameters)
                    ->transform(function ($item, $key) use ($schemaKey, $schemaValue) {
                        return match ($key) {
                            'name' => $schemaKey,
                            'schema' => $schemaValue,
                            'in', 'required' => $item,
                            default => throw new Exception("Missing key - $key"),
                        };
                    })
                    ->toArray();

                $arrayOfParameters[] = $parameters;
            }

            return $arrayOfParameters;
        }

        return [
            'content' => [
                'application/json' => [
                    'schema' => [
                        'properties' => $requestSchema,
                    ],
                ],
            ],
        ];
    }

    private function getSchemaByResource(ResourceInterface $resource): array
    {
        $specification = $resource->specification();
        $schema = $this->getSpecificationSchema($specification);

        return [
            'description' => $specification->getDescription(),
            'content' => [
                'application/json' => [
                    'schema' => $schema,
                ]
            ]
        ];
    }

    private function getSpecificationSchema(SpecificationInterface $specification): array
    {
        $specificationProperties = $specification->getProperties();
        $properties = [];
        // $required = [];

        /** @var \ReflectionProperty $property */
        foreach ($specificationProperties as $property) {
            $propertyTypeRepresentation = $property->getType()?->getName();

            /** @var PropertyDataInterface $dataType */
            $dataType = match (true) {
                $propertyTypeRepresentation === 'int' => new IntegerPropertyData($property),
                $propertyTypeRepresentation === 'bool' => new BooleanPropertyData($property),
                $propertyTypeRepresentation === 'array' => new ArrayPropertyData($property),
                enum_exists($propertyTypeRepresentation) => new EnumPropertyData($property),
                default => new StringPropertyData($property),
            };

            $properties[$property->getName()] = $dataType->toArray();

            // if ($property->getType()?->allowsNull() === false) {
            //     $required[] = $property->getName();
            // }
        }

        // Has with specifications?
        foreach ($specification->getOtherSpecifications() as $propertyName => $otherSpecification) {
            $properties[$propertyName] = $this->getSpecificationSchema($otherSpecification);
        }

        $result = [
            'properties' => $properties,
            // 'required' => $required,
        ];

        if ($specification->isArray()) {
            $result = [
                'type' => 'array',
                'nullable' => $specification->isNullable(),
                'items' => $result,
            ];
        }

        return $result;
    }

    private function getTypeByMethod($method): string
    {
        return match ($method) {
            'GET', 'DELETE' => 'query',
            'POST' => 'header',
            default => 'requestBody'
        };
    }

    private function getRouteInformation(Route $route): ?array
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
