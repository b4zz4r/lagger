<?php

namespace B4zz4r\Lagger\Commands;

use B4zz4r\Lagger\Attribute\LaggerParameterDescription;
use B4zz4r\Lagger\Concerns\DescriptionInterface;
use B4zz4r\Lagger\Concerns\ParametersInterface;
use B4zz4r\Lagger\Concerns\PropertyDataInterface;
use B4zz4r\Lagger\Concerns\RequestInterface;
use B4zz4r\Lagger\Concerns\ResourceInterface;
use B4zz4r\Lagger\Concerns\ResponseInterface;
use B4zz4r\Lagger\Concerns\SpecificationInterface;
use B4zz4r\Lagger\Concerns\SummaryInterface;
use B4zz4r\Lagger\Concerns\TagInterface;
use B4zz4r\Lagger\Data\ArrayPropertyData;
use B4zz4r\Lagger\Data\BooleanPropertyData;
use B4zz4r\Lagger\Data\DatePropertyData;
use B4zz4r\Lagger\Data\DateTimePropertyData;
use B4zz4r\Lagger\Data\EnumPropertyData;
use B4zz4r\Lagger\Data\IntegerPropertyData;
use B4zz4r\Lagger\Data\SpecificationData;
use B4zz4r\Lagger\Data\StringPropertyData;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\RequiredIf;
use ReflectionAttribute;
use ReflectionClass;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

            $responseInstance = new ReflectionClass($returnTypeOfMethod);

            if (! $responseInstance->implementsInterface(ResourceInterface::class) &&
                ! $responseInstance->newInstanceWithoutConstructor() instanceof Response &&
                ! $responseInstance->newInstanceWithoutConstructor() instanceof JsonResponse &&
                ! $responseInstance->newInstanceWithoutConstructor() instanceof BinaryFileResponse &&
                ! $responseInstance->newInstanceWithoutConstructor() instanceof StreamedResponse
            ) {
                throw new Exception(
                    vsprintf("Instance '%s' must implement %s or must be instance of %s or %s or %s", [
                        $route['controller'].'::'.$route['action'],
                        $returnTypeOfMethod,
                        ResourceInterface::class,
                        Response::class,
                        JsonResponse::class,
                    ])
                );
            }

            /** @var DescriptionInterface|null $description */
            $description = Arr::first($reflectionMethod->getAttributes(DescriptionInterface::class, ReflectionAttribute::IS_INSTANCEOF))?->newInstance();

            /** @var ParametersInterface|null $parameters */
            $parameters = Arr::first($reflectionMethod->getAttributes(ParametersInterface::class, ReflectionAttribute::IS_INSTANCEOF))?->newInstance();

            /** @var SummaryInterface|null $summary */
            $summary = Arr::first($reflectionMethod->getAttributes(SummaryInterface::class, ReflectionAttribute::IS_INSTANCEOF))?->newInstance();

            /** @var TagInterface|null $tag */
            $tag = Arr::first($reflectionClass->getAttributes(TagInterface::class, ReflectionAttribute::IS_INSTANCEOF))?->newInstance();

            /** @var ResponseInterface|null $response */
            $response = Arr::first($reflectionMethod->getAttributes(ResponseInterface::class, ReflectionAttribute::IS_INSTANCEOF))?->newInstance();

            $requestInstance = null;

            foreach ($reflectionMethod->getParameters() as $parameter) {
                $requestName = $parameter->getType()?->getName();

                if (is_null($requestName) || ! class_exists($requestName)) {
                    continue;
                }

                $parameterClass = new ReflectionClass($requestName);

                if (! $parameterClass->implementsInterface(RequestInterface::class)) {
                    continue;
                }

                $requestInstance = $parameterClass;

                break;
            }

            if ($requestInstance === null) {
                throw new Exception($route['controller'] . '@' . $route['action'] .  ' dont have any parameter which implement ' . RequestInterface::class);
            }

            $this->specifications[] = new SpecificationData(
                name: $route['name'],
                route: $route['uri'],
                method: $route['method'],
                request: $requestInstance->newInstance(),
                response: $responseInstance->newInstanceWithoutConstructor(),
                description: $description,
                summary: $summary,
                tag: $tag,
                parameters: $parameters,
                responses: $response?->getResponses() ?? [],
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

        // Forget custom responses
        Arr::forget($info, 'responses');

        $json = json_encode($info);
        file_put_contents($outputPath, $json);

    }

    /**
     * Getting (generate) OpenAPI 3 Specification by Specification
     */
    private function getOpenApiSpecification(SpecificationData $data, string $method): array
    {
        $requestClass = $data->request;
        $operationId = \vsprintf('%s.%s', [
            $data->name,
            Str::lower($data->method),
        ]);
        $responses = [];

        if ($data->response instanceof ResourceInterface) {
            $responses['200'] = $this->getSchemaByResource($data->response);
        }

        if ($data->response instanceof BinaryFileResponse || $data->response instanceof StreamedResponse) {
            $responses['200'] = [
                'description' => 'File response.',
                'content' => [
                    'application/pdf' => [
                        'schema' => [
                            'type' => 'string',
                            'format' => 'binary',
                        ],
                    ],
                ],
            ];
        }

        foreach ($data->responses as $responseData) {
            $responses[$responseData->statusCode] = [
                'description' => $responseData->summary,
                'content' => [
                    'application/json' => (object) [],
                ],
            ];
        }

        $specification = [
            'description' => $data->description?->getDescription(),
            'summary' => $data->summary?->getSummary(),
            'operationId' => $operationId,
            'tags' => $data->tag?->getTags(),
            $this->getRequestSchemaKeyByMethod($method) => $this->getSchemaByRequest($requestClass, $method),
            'responses' => $responses,
        ];

        if (! ($data->parameters instanceof ParametersInterface)) {
            return $specification;
        }

        $parameters = [];
        foreach ($data->parameters->getParameters() as $name => $values) {
            [$instance, $attributes] = array_pad(Arr::wrap($values), 2, []);

            $parameters[] = (new $instance)->toArray($name, $attributes);
        }

        $specification['parameters'] = array_merge($parameters, $specification['parameters']);

        return $specification;
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
        $descriptions = [];
        $rules = $request->rules();

        $rulesDescriptionInstance = (new ReflectionClass($request))
            ->getMethod('rules')
            ->getAttributes(LaggerRulesDescription::class);

        if (! empty($rulesDescription)) {
            $descriptions = $rulesDescription[0]?->getRules() ?? [];
        }

        foreach ($rules as $parentKey => $value) {
            if (in_array($parentKey, $skip)) {
                continue;
            }

            if (! is_array($value)) {
                $value = explode('|', $value);
            }

            if (! in_array('array', $value)) {
                $schema[$parentKey] = $this->generateSchemaByRules(
                    rules: $value,
                    parentKey: $parentKey,
                    descriptions: $descriptions
                );
                Arr::forget($rules, $parentKey);

                continue;
            }

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

            if ($rule instanceof Rule) {
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

            if ($rule === 'numeric' || $rule === 'integer') {
                $schema['type'] = 'integer';

                continue;
            }

            if ($rule === 'boolean') {
                $schema['type'] = 'boolean';

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

        if (Str::lower($method ) === 'get' || Str::lower($method) === 'delete') {
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

        $responseSchema['properties'] = empty($requestSchema) ? (object) [] : $requestSchema;

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

        /**
         * @var \ReflectionProperty $property
         */
        foreach ($specificationProperties as $property) {
            $propertyTypeRepresentation = $property->getType()?->getName();

            if (count($property->getAttributes())) {
                $propertyTypeRepresentation = $property->getAttributes()[0]->getArguments()['type'] ?? 'string';
            }

            /**
             * @var PropertyDataInterface $dataType
             */
            $dataType = match (true) {
                $propertyTypeRepresentation === 'int' => new IntegerPropertyData($property),
                $propertyTypeRepresentation === 'bool' => new BooleanPropertyData($property),
                $propertyTypeRepresentation === 'array' => new ArrayPropertyData($property),
                $propertyTypeRepresentation === 'date' => new DatePropertyData($property),
                $propertyTypeRepresentation === 'date-time' => new DateTimePropertyData($property),
                enum_exists($propertyTypeRepresentation) => new EnumPropertyData($property),
                default => new StringPropertyData($property),
            };

            $properties[$property->getName()] = $dataType->getSpecification();
        }

        // Has with specifications?
        foreach ($specification->getOtherSpecifications() as $propertyName => $otherSpecification) {
            $properties[$propertyName] = $this->getSpecificationSchema($otherSpecification);
        }

        $result = [
            'properties' => $properties,
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
            'method' => $route->methods()[0],
            'uri' => "/{$route->uri()}",
            'name' => $route->getName(),
            'controller' => $controllerWithAction->before('@')->value(),
            'action' => $controllerWithAction->after('@')->value(),
        ]);
    }
}
