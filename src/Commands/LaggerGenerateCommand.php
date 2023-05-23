<?php

namespace B4zz4r\Lagger\Commands;

use B4zz4r\Lagger\Attribute\LaggerParameterDescription;
use B4zz4r\Lagger\Concerns\DescriptionInterface;
use B4zz4r\Lagger\Concerns\PropertyDataInterface;
use B4zz4r\Lagger\Concerns\RequestInterface;
use B4zz4r\Lagger\Concerns\ResourceInterface;
use B4zz4r\Lagger\Concerns\SpecificationInterface;
use B4zz4r\Lagger\Concerns\SummaryInterface;
use B4zz4r\Lagger\Concerns\TagInterface;
use B4zz4r\Lagger\Data\ArrayPropertyData;
use B4zz4r\Lagger\Data\BooleanPropertyData;
use B4zz4r\Lagger\Data\EnumPropertyData;
use B4zz4r\Lagger\Data\IntegerPropertyData;
use B4zz4r\Lagger\Data\SpecificationData;
use B4zz4r\Lagger\Data\StringPropertyData;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\RequiredIf;
use ReflectionAttribute;
use ReflectionClass;

class LaggerGenerateCommand extends Command
{
    public $signature = 'lagger:generate';

    public $description = 'Generate swagger JSON';

    public string $prefixPath = '/api';

    public array $specifications = [];

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
        $info = config('lagger', []);
        $outputPath = Arr::pull($info, 'outputPath') ?? public_path('swagger.json');
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

    private function resolveRequestParameters(RequestInterface $request): array
    {
        $schema = [];
        $skip = [];
        $rules = $request->rules();

        /** @var array<string, string> $descriptions */
        $descriptions = (new ReflectionClass($request))
            ->getMethod('rules')
            ->getAttributes(LaggerParameterDescription::class)[0]
            ?->getArguments()[0] ?? [];

        foreach ($rules as $parentKey => $value) {
            if (in_array($parentKey, $skip)) {
                continue;
            }

            if (! Str::contains($value, 'array')) {
                $schema[$parentKey] = $this->generateSchemaByRules(
                    rules: $value,
                    parentKey: $parentKey,
                    descriptions: $descriptions
                );
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

                foreach ($children as $childKey => $rule) {
                    if (Str::contains($childKey, '.*')) {
                        $children[Str::before($childKey, '.*')] .= '|item:' . Str::replace('|', '-', $rule);
                        unset($children[$childKey]);
                    }
                }

                $schema[$parentKey] = $this->generateSchemaByRules(
                    rules: $value,
                    children: $children,
                    parentKey: $parentKey,
                    descriptions: $descriptions
                );

                unset($children);
            }
        }

        return $schema;
    }

    private function generateSchemaByRules(
        mixed $rules,
        array $children = [],
        string $parentKey = null,
        array $descriptions = []
    ): array {
        $schema = [
            'type' => 'object',
            'description' => $descriptions[$parentKey] ?? null,
        ];

        $rules = Arr::wrap(
            Str::contains($rules, '|') ? explode('|', $rules) : $rules
        );

        if (! empty($children)) {
            foreach ($children as $key => $rule) {
                $schema['properties'][$key] = $this->generateSchemaByRules(
                    rules: $rule,
                    parentKey: "$parentKey.$key",
                    descriptions: $descriptions
                );
            }
        }

        foreach ($rules as $rule) {
            $schema['description'] ??= $rule;

            if ($rule instanceof RequiredIf) {
                $schema['type'] = 'object';
                $schema['isRequired'] = true;

                continue;
            }

            if (Str::startsWith($rule, 'item:')) {
                $itemRule = Str::replace('-', '|', Str::after($rule, 'item:'));
                $schema = [
                    'type' => 'array',
                    'items' => $this->generateSchemaByRules(
                        rules: $itemRule,
                        parentKey: $parentKey,
                        descriptions: $descriptions
                    ),
                ];

                continue;
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

            if ($rule === 'file') {
                $schema['type'] = 'string';
                $schema['format'] = 'binary';

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

            if (Str::startsWith($rule, ['required', 'required_'])) {
                $schema['isRequired'] = true;
            }
        }

        // Required attribute
        return $this->resolveRequiredOpenApiAttribute($schema);
    }

    private function resolveRequiredOpenApiAttribute(array $schema): array
    {
        if (! empty($schema['properties'] ?? [])) {
            $schema['required'] = array_keys(Arr::where($schema['properties'] ?? [], fn($item) => $item['isRequired'] ?? false));

            foreach ($schema['properties'] ?? [] as $key => $item) {
                unset($schema['properties'][$key]['isRequired']);
            }

            if (empty($schema['required'])) {
                unset($schema['required']);
            }
        }

        foreach ($schema as $key => $item) {
            if ($item['isRequired'] ?? false) {
                $schema['required'] ??= [];
                $schema['required'][] = $key;
                unset($schema[$key]['isRequired']);
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
        $requestSchema = $this->resolveRequestParameters($request);
        $requestSchema = $this->resolveRequiredOpenApiAttribute($requestSchema);

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

        $responseSchema = [];

        if (! empty($requestSchema['required'] ?? [])) {
            $responseSchema['required'] = $requestSchema['required'];
            unset($requestSchema['required']);
        }

        $responseSchema['properties'] = $requestSchema;

        return [
            'content' => [
                'application/json' => [
                    'schema' => $responseSchema,
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
