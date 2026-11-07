<?php

namespace App\Support;

use App\Actions\AddSpool;
use App\Actions\DevicePairing;
use App\Actions\SearchCatalog;
use App\Enums\SpoolEventType;
use App\Enums\SpoolStatus;
use App\Http\Middleware\ThrottleAuthFailures;

/**
 * The public REST API reference as an OpenAPI 3.1 document. The docs page and
 * /docs/api/openapi.json are both built from spec(), so they always agree.
 * Limits, statuses and filters come from the code that enforces them.
 */
class ApiDocumentation
{
    public const TAGS = [
        'Connecting' => 'Get a token for a device.',
        'Account' => 'Who the token belongs to.',
        'Catalog' => 'Every filament the tracker knows about, with your own counts.',
        'Spools' => 'Your own spools. Spools are counted, not weighed.',
    ];

    /** @return array<string, mixed> */
    public function spec(): array
    {
        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => config('app.name').' API',
                'version' => '1',
                'description' => 'Track Bambu Lab filament spools: browse the catalog and count the spools you have boxed or on the spool holder.',
            ],
            'servers' => [['url' => url('/api/v1')]],
            'security' => [['bearer' => []]],
            'tags' => collect(self::TAGS)->map(fn (string $description, string $name) => compact('name', 'description'))->values()->all(),
            'paths' => $this->paths(),
            'components' => [
                'securitySchemes' => [
                    'bearer' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'description' => 'A token from the Devices page, or from POST /pair. Read-only tokens have `spools:read`; read-write tokens also have `spools:write`.',
                    ],
                ],
                'schemas' => $this->schemas(),
                'responses' => $this->errorResponses(),
            ],
        ];
    }

    /**
     * Operations in page order, with references resolved, for the docs page.
     *
     * @return array<string, list<array{id: string, method: string, path: string, summary: string, description: ?string, ability: ?string, public: bool, parameters: list<array<string, mixed>>, body: list<array{name: string, type: string, required: bool, description: string}>, responses: list<array{status: string, description: string, examples: list<array{label: string, value: mixed}>}>}>>
     */
    public function operationsByTag(): array
    {
        $spec = $this->spec();
        $grouped = array_fill_keys(array_keys(self::TAGS), []);

        foreach ($spec['paths'] as $path => $methods) {
            foreach ($methods as $method => $operation) {
                $bodySchema = $operation['requestBody']['content']['application/json']['schema'] ?? ['properties' => []];

                $grouped[$operation['tags'][0]][] = [
                    'id' => $operation['operationId'],
                    'method' => strtoupper($method),
                    'path' => $path,
                    'summary' => $operation['summary'],
                    'description' => $operation['description'] ?? null,
                    'ability' => $operation['x-ability'] ?? null,
                    'public' => ($operation['security'] ?? null) === [],
                    'parameters' => $operation['parameters'] ?? [],
                    'body' => collect($bodySchema['properties'])->map(fn (array $field, string $name) => [
                        'name' => $name,
                        'type' => $this->typeLabel($field),
                        'required' => in_array($name, $bodySchema['required'] ?? [], true),
                        'description' => $field['description'] ?? '',
                    ])->values()->all(),
                    'responses' => collect($operation['responses'])->map(function (array $response, int|string $status) use ($spec) {
                        $response = $this->resolve($spec, $response);

                        $content = $response['content']['application/json'] ?? [];
                        $label = "Example {$status} response";

                        return [
                            'status' => (string) $status,
                            'description' => $response['description'],
                            'examples' => array_key_exists('example', $content)
                                ? [['label' => $label, 'value' => $content['example']]]
                                : collect($content['examples'] ?? [])->map(fn (array $example) => ['label' => "{$label}: {$example['summary']}", 'value' => $example['value']])->values()->all(),
                        ];
                    })->values()->all(),
                ];
            }
        }

        return $grouped;
    }

    /** "string", "integer", "boolean or null", "one of boxed, on_spool". */
    public function typeLabel(array $schema): string
    {
        if (isset($schema['enum'])) {
            return 'one of '.implode(', ', $schema['enum']);
        }

        $type = implode(' or ', (array) ($schema['type'] ?? 'string'));

        return isset($schema['format']) ? "{$type} ({$schema['format']})" : $type;
    }

    /** @return array<string, mixed> */
    private function paths(): array
    {
        $uuid = ['name' => 'uuid', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'format' => 'uuid'], 'description' => 'The spool\'s `uuid`. Someone else\'s spool answers 404.'];
        $page = ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1], 'description' => 'Page number, from 1.'];
        $perPage = ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => SearchCatalog::MAX_PER_PAGE, 'default' => 50], 'description' => 'Items per page, at most '.SearchCatalog::MAX_PER_PAGE.'.'];
        $noteBody = $this->jsonBody(['note' => ['type' => ['string', 'null'], 'maxLength' => 255, 'description' => 'Optional note saved in the spool\'s history.']], optional: true);
        $spoolFields = [
            'is_refill' => ['type' => 'boolean', 'description' => 'A refill (no plastic spool) instead of a spool.'],
            'purchased_at' => ['type' => ['string', 'null'], 'format' => 'date', 'description' => 'Purchase date, `YYYY-MM-DD`.'],
        ];
        $spool = $this->ok('The spool.', 'Spool', $this->exampleSpool());

        return [
            '/pair' => [
                'post' => [
                    'tags' => ['Connecting'],
                    'operationId' => 'pairDevice',
                    'summary' => 'Trade a QR pairing code for a token',
                    'description' => 'On the Devices page, *Show QR code* shows a QR holding JSON: `{"type": "filament-spools-pairing", "api": "<base URL>", "code": "<code>", "expires_at": "<ISO 8601>"}`. '
                        .'Send its `code` here, without a token, to get a token for this device. A code works once, for '.(DevicePairing::TTL_SECONDS / 60).' minutes. '
                        .'The token is valid for '.DevicePairing::TOKEN_LIFETIME_DAYS.' days. A bad, used or expired code answers 401 and counts toward the failed-authentication block.',
                    'security' => [],
                    'requestBody' => $this->jsonBody([
                        'code' => ['type' => 'string', 'maxLength' => 100, 'description' => 'The `code` from the QR.'],
                        'device_name' => ['type' => ['string', 'null'], 'maxLength' => 60, 'description' => 'Name shown on the Devices page. Defaults to the name typed there.'],
                    ], required: ['code']),
                    'responses' => [
                        201 => $this->ok('The new token. Store it; it is not shown again.', 'PairedDevice', [
                            'data' => [
                                'token' => '7|kq3J1nVb0pXr8eLw2YtZ5aGc9hDf4sMu6iOyTlNe',
                                'token_type' => 'Bearer',
                                'abilities' => ['spools:read', 'spools:write'],
                                'expires_at' => '2027-11-07T12:00:00+00:00',
                                'api' => url('/api/v1'),
                                'user' => ['name' => 'Alex', 'email' => 'alex@example.com'],
                            ],
                        ]),
                        401 => [
                            'description' => 'The code is invalid, already used or expired, or the account already has the maximum number of tokens.',
                            'content' => ['application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/Error'],
                                'example' => ['message' => 'This pairing code is invalid, already used or expired. Show a new QR code.'],
                            ]],
                        ],
                        422 => ['$ref' => '#/components/responses/ValidationFailed'],
                        429 => ['$ref' => '#/components/responses/TooManyRequests'],
                    ],
                ],
            ],
            '/me' => [
                'get' => [
                    'tags' => ['Account'],
                    'operationId' => 'getMe',
                    'summary' => 'The token\'s user and abilities',
                    'description' => 'Handy to check that a token works and whether it can write.',
                    'responses' => [
                        200 => $this->ok('The user.', 'Me', ['data' => ['id' => 1, 'name' => 'Alex', 'email' => 'alex@example.com', 'abilities' => ['spools:read', 'spools:write']]]),
                        401 => ['$ref' => '#/components/responses/Unauthenticated'],
                        429 => ['$ref' => '#/components/responses/TooManyRequests'],
                    ],
                ],
            ],
            '/catalog' => [
                'get' => [
                    'tags' => ['Catalog'],
                    'operationId' => 'listCatalog',
                    'summary' => 'Search the catalog',
                    'description' => 'Active products, ordered by article number. Each one carries your own `boxed_count`, `on_spool_count` and `owned_count`.',
                    'x-ability' => 'spools:read',
                    'parameters' => [
                        ['name' => 'q', 'in' => 'query', 'schema' => ['type' => 'string', 'maxLength' => 100], 'description' => 'Part of the name or article number, any case.'],
                        ['name' => 'material', 'in' => 'query', 'schema' => ['type' => 'string', 'maxLength' => 50], 'description' => 'Exact material, such as `PLA` or `PETG`.'],
                        ['name' => 'brand', 'in' => 'query', 'schema' => ['type' => 'string', 'maxLength' => 50], 'description' => 'Exact brand, such as `Bambu Lab`.'],
                        ['name' => 'line', 'in' => 'query', 'schema' => ['type' => 'string', 'maxLength' => 50], 'description' => 'Exact product line, such as `PLA Basic`.'],
                        ['name' => 'color', 'in' => 'query', 'schema' => ['type' => 'string', 'maxLength' => 20], 'description' => 'Color family, such as `white` or `blue`.'],
                        ['name' => 'owned', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => SearchCatalog::OWNED_FILTERS], 'description' => '`1` owned, `0` not owned, `boxed`, `on_spool`, or `running_out` (on the holder with no boxed spool left).'],
                        ['name' => 'updated_since', 'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date-time'], 'description' => 'Only products changed after this moment.'],
                        $perPage,
                        $page,
                    ],
                    'responses' => [
                        200 => $this->ok('A page of products.', 'ProductPage', $this->examplePage([$this->exampleProduct()], '/catalog')),
                        401 => ['$ref' => '#/components/responses/Unauthenticated'],
                        403 => ['$ref' => '#/components/responses/MissingAbility'],
                        422 => ['$ref' => '#/components/responses/ValidationFailed'],
                        429 => ['$ref' => '#/components/responses/TooManyRequests'],
                    ],
                ],
            ],
            '/catalog/{id}' => [
                'get' => [
                    'tags' => ['Catalog'],
                    'operationId' => 'getProduct',
                    'summary' => 'One product',
                    'x-ability' => 'spools:read',
                    'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer'], 'description' => 'The product `id`.']],
                    'responses' => [
                        200 => $this->ok('The product.', 'Product', ['data' => $this->exampleProduct()]),
                        401 => ['$ref' => '#/components/responses/Unauthenticated'],
                        403 => ['$ref' => '#/components/responses/MissingAbility'],
                        404 => ['$ref' => '#/components/responses/NotFound'],
                        429 => ['$ref' => '#/components/responses/TooManyRequests'],
                    ],
                ],
            ],
            '/spools' => [
                'get' => [
                    'tags' => ['Spools'],
                    'operationId' => 'listSpools',
                    'summary' => 'List your spools',
                    'description' => 'Newest change first. With `updated_since` (sync mode) the list also holds removed spools, with `deleted_at` set, so an app can drop them.',
                    'x-ability' => 'spools:read',
                    'parameters' => [
                        ['name' => 'status', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => $this->values(SpoolStatus::cases())], 'description' => 'Only spools with this status.'],
                        ['name' => 'updated_since', 'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date-time'], 'description' => 'Only spools changed after this moment, removed ones included.'],
                        $perPage,
                        $page,
                    ],
                    'responses' => [
                        200 => $this->ok('A page of spools.', 'SpoolPage', $this->examplePage([$this->exampleSpool()['data']], '/spools')),
                        401 => ['$ref' => '#/components/responses/Unauthenticated'],
                        403 => ['$ref' => '#/components/responses/MissingAbility'],
                        422 => ['$ref' => '#/components/responses/ValidationFailed'],
                        429 => ['$ref' => '#/components/responses/TooManyRequests'],
                    ],
                ],
                'post' => [
                    'tags' => ['Spools'],
                    'operationId' => 'addSpool',
                    'summary' => 'Add one or more spools',
                    'description' => 'Boxed unless `status` says otherwise. Each spool is saved with an `added` event. '
                        .'Send `amount` to add several identical spools in one call (all or none); `data` is then a list of the new spools, also when `amount` is 1.',
                    'x-ability' => 'spools:write',
                    'requestBody' => $this->jsonBody([
                        'filament_product_id' => ['type' => 'integer', 'description' => 'The catalog product `id`.'],
                        'amount' => ['type' => 'integer', 'minimum' => 1, 'maximum' => AddSpool::MAX_AMOUNT, 'description' => 'How many identical spools to add, at most '.AddSpool::MAX_AMOUNT.'. Leave it out to add one and get a single spool back.'],
                        'status' => ['type' => 'string', 'enum' => $this->values(SpoolStatus::owned()), 'default' => SpoolStatus::Boxed->value, 'description' => 'Boxed, or already on the spool holder.'],
                        ...$spoolFields,
                    ], required: ['filament_product_id']),
                    'responses' => [
                        201 => [
                            'description' => 'The new spool, or with `amount` the list of new spools.',
                            'content' => ['application/json' => [
                                'schema' => ['oneOf' => [['$ref' => '#/components/schemas/Spool'], ['$ref' => '#/components/schemas/SpoolList']]],
                                'examples' => [
                                    'one' => ['summary' => 'Without amount', 'value' => $this->exampleSpool()],
                                    'several' => ['summary' => 'With "amount": 2', 'value' => ['data' => [$this->exampleSpool()['data'], [...$this->exampleSpool()['data'], 'uuid' => '4f0b2a7d-8e1c-4d6a-b3f5-7c9e1a2d3b4f']]]],
                                ],
                            ]],
                        ],
                        401 => ['$ref' => '#/components/responses/Unauthenticated'],
                        403 => ['$ref' => '#/components/responses/MissingAbility'],
                        422 => ['$ref' => '#/components/responses/ValidationFailed'],
                        429 => ['$ref' => '#/components/responses/TooManyRequests'],
                    ],
                ],
            ],
            '/spools/{uuid}' => [
                'get' => [
                    'tags' => ['Spools'],
                    'operationId' => 'getSpool',
                    'summary' => 'One spool',
                    'x-ability' => 'spools:read',
                    'parameters' => [$uuid],
                    'responses' => $this->spoolResponses($spool, write: false),
                ],
                'patch' => [
                    'tags' => ['Spools'],
                    'operationId' => 'updateSpool',
                    'summary' => 'Change a spool\'s details',
                    'description' => 'Send only the fields to change. The status changes through `/open`, `/used-up` and `DELETE`, so each one leaves an event.',
                    'x-ability' => 'spools:write',
                    'parameters' => [$uuid],
                    'requestBody' => $this->jsonBody($spoolFields),
                    'responses' => $this->spoolResponses($spool),
                ],
                'delete' => [
                    'tags' => ['Spools'],
                    'operationId' => 'removeSpool',
                    'summary' => 'Remove a spool without counting it as used',
                    'description' => 'For a mistake, or a spool sold or given away. The spool is kept with `deleted_at` set and shows up in sync mode.',
                    'x-ability' => 'spools:write',
                    'parameters' => [$uuid],
                    'requestBody' => $noteBody,
                    'responses' => [204 => ['description' => 'Removed. No body.']] + array_diff_key($this->spoolResponses($spool), [200 => true]),
                ],
            ],
            '/spools/{uuid}/open' => [
                'post' => [
                    'tags' => ['Spools'],
                    'operationId' => 'openSpool',
                    'summary' => 'Put a boxed spool on the spool holder',
                    'description' => 'Status `boxed` → `on_spool`, saved as an `opened` event. Any other status answers 422.',
                    'x-ability' => 'spools:write',
                    'parameters' => [$uuid],
                    'requestBody' => $noteBody,
                    'responses' => $this->spoolResponses($spool),
                ],
            ],
            '/spools/{uuid}/used-up' => [
                'post' => [
                    'tags' => ['Spools'],
                    'operationId' => 'markSpoolUsedUp',
                    'summary' => 'Mark a spool used up',
                    'description' => 'From `boxed` or `on_spool` to `used_up`, saved as a `used_up` event. Used-up spools count in the usage graphs.',
                    'x-ability' => 'spools:write',
                    'parameters' => [$uuid],
                    'requestBody' => $noteBody,
                    'responses' => $this->spoolResponses($spool),
                ],
            ],
            '/spools/{uuid}/events' => [
                'get' => [
                    'tags' => ['Spools'],
                    'operationId' => 'listSpoolEvents',
                    'summary' => 'A spool\'s history',
                    'description' => '100 events per page.',
                    'x-ability' => 'spools:read',
                    'parameters' => [$uuid, $page],
                    'responses' => $this->spoolResponses(
                        $this->ok('A page of events.', 'SpoolEventPage', $this->examplePage([
                            ['type' => SpoolEventType::Added->value, 'note' => null, 'created_at' => '2026-10-02T18:12:40+00:00'],
                            ['type' => SpoolEventType::Opened->value, 'note' => 'X1C AMS slot 2', 'created_at' => '2026-11-01T09:30:00+00:00'],
                        ], '/spools/{uuid}/events', 100)),
                        write: false,
                    ),
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function schemas(): array
    {
        $nullable = fn (string $type, string $description, array $extra = []) => ['type' => [$type, 'null'], 'description' => $description] + $extra;
        $pageOf = fn (string $item) => [
            'type' => 'object',
            'properties' => [
                'data' => ['type' => 'array', 'items' => ['$ref' => "#/components/schemas/{$item}"]],
                'links' => ['$ref' => '#/components/schemas/PageLinks'],
                'meta' => ['$ref' => '#/components/schemas/PageMeta'],
            ],
        ];
        $wrap = fn (string $item) => ['type' => 'object', 'properties' => ['data' => ['$ref' => "#/components/schemas/{$item}"]]];

        return [
            'ProductData' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'brand' => $nullable('string', 'Brand name.'),
                    'material' => $nullable('string', 'Material, such as PLA.'),
                    'line' => $nullable('string', 'Product line, such as PLA Basic.'),
                    'article_name' => ['type' => 'string', 'description' => 'Color name.'],
                    'article_number' => ['type' => 'string', 'description' => 'Bambu Lab article number.'],
                    'color_hex' => $nullable('string', 'Main color, `#RRGGBB`.'),
                    'color_hex_2' => $nullable('string', 'Second color of dual-color filament.'),
                    'color_family' => $nullable('string', 'Broad color group used by the `color` filter.'),
                    'diameter' => $nullable('number', 'Millimetres.'),
                    'nominal_weight' => $nullable('integer', 'Grams of filament on a full spool.'),
                    'has_refill' => ['type' => 'boolean', 'description' => 'Sold as a refill too.'],
                    'product_url' => $nullable('string', 'Product page, when known.'),
                    'buy_url' => $nullable('string', 'Product page, or a search in the configured Bambu Lab store.'),
                    'boxed_count' => ['type' => 'integer', 'description' => 'Your boxed spools. Not inside a spool\'s `product`.'],
                    'on_spool_count' => ['type' => 'integer', 'description' => 'Your spools on the holder. Not inside a spool\'s `product`.'],
                    'owned_count' => ['type' => 'integer', 'description' => 'Both together. Not inside a spool\'s `product`.'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'SpoolData' => [
                'type' => 'object',
                'properties' => [
                    'uuid' => ['type' => 'string', 'format' => 'uuid'],
                    'product' => ['$ref' => '#/components/schemas/ProductData'],
                    'status' => ['type' => 'string', 'enum' => $this->values(SpoolStatus::cases())],
                    'is_refill' => ['type' => 'boolean'],
                    'purchased_at' => $nullable('string', 'Purchase date.', ['format' => 'date']),
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                    'deleted_at' => $nullable('string', 'Set when removed.', ['format' => 'date-time']),
                ],
            ],
            'SpoolEventData' => [
                'type' => 'object',
                'properties' => [
                    'type' => ['type' => 'string', 'enum' => $this->values(SpoolEventType::cases())],
                    'note' => $nullable('string', 'Note given with the change. Older `moved` events hold the location the spool moved to.'),
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'Product' => $wrap('ProductData'),
            'Spool' => $wrap('SpoolData'),
            'SpoolList' => ['type' => 'object', 'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/SpoolData']]]],
            'ProductPage' => $pageOf('ProductData'),
            'SpoolPage' => $pageOf('SpoolData'),
            'SpoolEventPage' => $pageOf('SpoolEventData'),
            'Me' => ['type' => 'object', 'properties' => ['data' => ['type' => 'object', 'properties' => [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
                'email' => ['type' => 'string'],
                'abilities' => ['type' => 'array', 'items' => ['type' => 'string']],
            ]]]],
            'PairedDevice' => ['type' => 'object', 'properties' => ['data' => ['type' => 'object', 'properties' => [
                'token' => ['type' => 'string', 'description' => 'Send as `Authorization: Bearer <token>`.'],
                'token_type' => ['type' => 'string', 'const' => 'Bearer'],
                'abilities' => ['type' => 'array', 'items' => ['type' => 'string']],
                'expires_at' => ['type' => 'string', 'format' => 'date-time'],
                'api' => ['type' => 'string', 'description' => 'Base URL of this API.'],
                'user' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string'], 'email' => ['type' => 'string']]],
            ]]]],
            'PageLinks' => ['type' => 'object', 'properties' => [
                'first' => ['type' => ['string', 'null']],
                'last' => ['type' => ['string', 'null']],
                'prev' => ['type' => ['string', 'null']],
                'next' => ['type' => ['string', 'null'], 'description' => 'Null on the last page.'],
            ]],
            'PageMeta' => ['type' => 'object', 'properties' => [
                'current_page' => ['type' => 'integer'],
                'last_page' => ['type' => 'integer'],
                'per_page' => ['type' => 'integer'],
                'total' => ['type' => 'integer'],
                'from' => ['type' => ['integer', 'null']],
                'to' => ['type' => ['integer', 'null']],
                'path' => ['type' => 'string'],
            ]],
            'Error' => ['type' => 'object', 'properties' => ['message' => ['type' => 'string']]],
            'ValidationError' => ['type' => 'object', 'properties' => [
                'message' => ['type' => 'string'],
                'errors' => ['type' => 'object', 'additionalProperties' => ['type' => 'array', 'items' => ['type' => 'string']]],
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function errorResponses(): array
    {
        $error = fn (string $description, array $example, string $schema = 'Error') => [
            'description' => $description,
            'content' => ['application/json' => ['schema' => ['$ref' => "#/components/schemas/{$schema}"], 'example' => $example]],
        ];

        return [
            'Unauthenticated' => $error('No token, or the token is wrong, revoked or expired. Counts toward the failed-authentication block.', ['message' => 'Unauthenticated.']),
            'MissingAbility' => $error('The token lacks the ability this call needs, such as a read-only token writing.', ['message' => 'Invalid ability provided.']),
            'NotFound' => $error('Not found, or not yours.', ['message' => 'Not Found.']),
            'ValidationFailed' => $error('The input is not valid. `errors` lists the messages per field.', ['message' => 'The filament product id field is required.', 'errors' => ['filament_product_id' => ['The filament product id field is required.']]], 'ValidationError'),
            'TooManyRequests' => $error('Rate limit reached. Wait the number of seconds in the `Retry-After` header.', ['message' => 'Too Many Attempts.']),
        ];
    }

    /**
     * @param  array<string, mixed>  $success
     * @return array<int|string, mixed>
     */
    private function spoolResponses(array $success, bool $write = true): array
    {
        return array_filter([
            200 => $success,
            401 => ['$ref' => '#/components/responses/Unauthenticated'],
            403 => ['$ref' => '#/components/responses/MissingAbility'],
            404 => ['$ref' => '#/components/responses/NotFound'],
            422 => $write ? ['$ref' => '#/components/responses/ValidationFailed'] : null,
            429 => ['$ref' => '#/components/responses/TooManyRequests'],
        ]);
    }

    /** @return array<string, mixed> */
    private function ok(string $description, string $schema, array $example): array
    {
        return [
            'description' => $description,
            'content' => ['application/json' => ['schema' => ['$ref' => "#/components/schemas/{$schema}"], 'example' => $example]],
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $properties
     * @param  list<string>  $required
     * @return array<string, mixed>
     */
    private function jsonBody(array $properties, array $required = [], bool $optional = false): array
    {
        return [
            'required' => ! $optional,
            'content' => ['application/json' => ['schema' => array_filter([
                'type' => 'object',
                'required' => $required,
                'properties' => $properties,
            ])]],
        ];
    }

    /** @return array<string, mixed> */
    private function resolve(array $spec, array $item): array
    {
        if (! isset($item['$ref'])) {
            return $item;
        }

        return data_get($spec, str_replace('/', '.', substr($item['$ref'], 2)));
    }

    /**
     * @param  list<\BackedEnum>  $cases
     * @return list<string>
     */
    private function values(array $cases): array
    {
        return array_map(fn (\BackedEnum $case) => $case->value, $cases);
    }

    /** @return array<string, mixed> */
    private function exampleProduct(): array
    {
        return [
            'id' => 1,
            'brand' => 'Bambu Lab',
            'material' => 'PLA',
            'line' => 'PLA Basic',
            'article_name' => 'Jade White',
            'article_number' => '10100',
            'color_hex' => '#FFFFFF',
            'color_hex_2' => null,
            'color_family' => 'white',
            'diameter' => 1.75,
            'nominal_weight' => 1000,
            'has_refill' => true,
            'product_url' => null,
            'buy_url' => rtrim((string) config('services.bambulab.store_url'), '/').'/search?q=10100',
            'boxed_count' => 2,
            'on_spool_count' => 1,
            'owned_count' => 3,
            'updated_at' => '2026-11-06T10:00:00+00:00',
        ];
    }

    /** @return array{data: array<string, mixed>} */
    private function exampleSpool(): array
    {
        return ['data' => [
            'uuid' => '9d3c6f1e-4b7a-4c2e-9f1d-2a8b5e7c0d43',
            'product' => array_diff_key($this->exampleProduct(), array_flip(['boxed_count', 'on_spool_count', 'owned_count'])),
            'status' => SpoolStatus::OnSpool->value,
            'is_refill' => false,
            'purchased_at' => '2026-10-02',
            'created_at' => '2026-10-02T18:12:40+00:00',
            'updated_at' => '2026-11-01T09:30:00+00:00',
            'deleted_at' => null,
        ]];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function examplePage(array $items, string $path, int $perPage = 50): array
    {
        $url = url('/api/v1'.$path);

        return [
            'data' => $items,
            'links' => ['first' => "{$url}?page=1", 'last' => "{$url}?page=1", 'prev' => null, 'next' => null],
            'meta' => ['current_page' => 1, 'from' => 1, 'last_page' => 1, 'path' => $url, 'per_page' => $perPage, 'to' => count($items), 'total' => count($items)],
        ];
    }

    /**
     * Limits shown on the docs page.
     *
     * @return array<string, string>
     */
    public function limits(): array
    {
        $limits = config('access.rate_limits');

        return [
            'Reads' => "{$limits['api_read_per_minute']} per minute per user",
            'Writes' => "{$limits['api_write_per_minute']} per minute and {$limits['api_write_per_day']} per day per user",
            'Any call' => "{$limits['api_ip_per_minute']} per minute per IP address",
            'Failed logins' => ThrottleAuthFailures::MAX_FAILURES.' wrong tokens or pairing codes block the IP for '.(ThrottleAuthFailures::DECAY_SECONDS / 60).' minutes',
            'Pairing' => '10 attempts per minute per IP address',
            'Tokens' => config('access.max_tokens_per_user').' active tokens per user',
        ];
    }
}
