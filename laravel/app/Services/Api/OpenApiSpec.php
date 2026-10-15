<?php

declare(strict_types=1);

namespace App\Services\Api;

/**
 * The OpenAPI 3.0.3 document served at /api/{version}/openapi.json and
 * rendered by the Swagger UI at /api/{version}/docs.
 */
class OpenApiSpec
{
    /**
     * Build the structured OpenAPI 3.0.3 specification contract.
     *
     * @return array<string, mixed>
     */
    public function build(string $version): array
    {
        $baseUrl = url("/api/{$version}");

        $v1Paths = [
            // Tokens
            '/tokens' => [
                'get' => [
                    'tags' => ['API Tokens'],
                    'summary' => 'List active personal access tokens',
                    'description' => 'Retrieves all API tokens issued to the authenticated user.',
                    'security' => [['SanctumBearer' => []]],
                    'responses' => [
                        '200' => [
                            'description' => 'List of active tokens',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/SuccessEnvelope'],
                                ],
                            ],
                        ],
                    ],
                ],
                'post' => [
                    'tags' => ['API Tokens'],
                    'summary' => 'Create a new personal access token with scoped abilities',
                    'security' => [['SanctumBearer' => []]],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'required' => ['token_name'],
                                    'properties' => [
                                        'token_name' => ['type' => 'string', 'example' => 'CI-CD-Deployment-Key'],
                                        'abilities' => [
                                            'type' => 'array',
                                            'items' => ['type' => 'string'],
                                            'example' => ['gate-pass:read', 'visitors:verify'],
                                        ],
                                        'expires_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'responses' => [
                        '201' => ['description' => 'Token generated successfully with plainTextToken.'],
                        '422' => ['$ref' => '#/components/responses/ValidationError'],
                    ],
                ],
                'delete' => [
                    'tags' => ['API Tokens'],
                    'summary' => 'Revoke all personal access tokens',
                    'security' => [['SanctumBearer' => []]],
                    'responses' => [
                        '200' => ['description' => 'All tokens revoked.'],
                    ],
                ],
            ],
            '/tokens/{tokenId}' => [
                'delete' => [
                    'tags' => ['API Tokens'],
                    'summary' => 'Revoke a specific token by ID',
                    'security' => [['SanctumBearer' => []]],
                    'parameters' => [
                        [
                            'name' => 'tokenId',
                            'in' => 'path',
                            'required' => true,
                            'schema' => ['type' => 'integer'],
                        ],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Token revoked.'],
                        '404' => ['description' => 'Token not found.'],
                    ],
                ],
            ],

            // Webhooks
            '/webhooks/subscriptions' => [
                'get' => [
                    'tags' => ['Webhooks'],
                    'summary' => 'List webhook subscriptions',
                    'security' => [['SanctumBearer' => []]],
                    'responses' => ['200' => ['description' => 'List of subscriptions']],
                ],
                'post' => [
                    'tags' => ['Webhooks'],
                    'summary' => 'Create a new webhook subscription',
                    'security' => [['SanctumBearer' => []]],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'required' => ['name', 'url', 'events'],
                                    'properties' => [
                                        'name' => ['type' => 'string', 'example' => 'Security Audit Hook'],
                                        'url' => ['type' => 'string', 'format' => 'uri', 'example' => 'https://api.myestate.io/webhooks'],
                                        'events' => [
                                            'type' => 'array',
                                            'items' => ['type' => 'string'],
                                            'example' => ['visitor.checked_in', 'gate_pass.created'],
                                        ],
                                        'secret' => ['type' => 'string', 'nullable' => true],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'responses' => [
                        '201' => ['description' => 'Webhook subscription created with HMAC signing secret.'],
                    ],
                ],
            ],
            '/webhooks/subscriptions/{subscriptionId}/test' => [
                'post' => [
                    'tags' => ['Webhooks'],
                    'summary' => 'Send test ping payload to a webhook subscription',
                    'security' => [['SanctumBearer' => []]],
                    'parameters' => [
                        [
                            'name' => 'subscriptionId',
                            'in' => 'path',
                            'required' => true,
                            'schema' => ['type' => 'integer'],
                        ],
                    ],
                    'responses' => ['200' => ['description' => 'Ping payload dispatched.']],
                ],
            ],
            '/webhooks/incoming/{service}' => [
                'post' => [
                    'tags' => ['Webhooks'],
                    'summary' => 'Receive and verify incoming third-party webhook',
                    'parameters' => [
                        [
                            'name' => 'service',
                            'in' => 'path',
                            'required' => true,
                            'schema' => ['type' => 'string', 'example' => 'stripe'],
                        ],
                        [
                            'name' => 'X-Webhook-Signature',
                            'in' => 'header',
                            'required' => false,
                            'schema' => ['type' => 'string'],
                        ],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Webhook acknowledged.'],
                        '401' => ['description' => 'Invalid HMAC-SHA256 signature.'],
                    ],
                ],
            ],

            // System & Monitoring
            '/health' => [
                'get' => [
                    'tags' => ['System & Monitoring'],
                    'summary' => 'System health check',
                    'description' => 'Evaluates database connectivity, cache response, storage readiness, and memory footprint.',
                    'responses' => [
                        '200' => ['description' => 'System operational.'],
                        '503' => ['description' => 'Service degraded.'],
                    ],
                ],
            ],
            '/metrics' => [
                'get' => [
                    'tags' => ['System & Monitoring'],
                    'summary' => 'API telemetry metrics',
                    'description' => 'Returns aggregate requests, average response latency, HTTP status code distribution, and webhook success rates over the last 24 hours.',
                    'responses' => ['200' => ['description' => 'Metrics summary.']],
                ],
            ],
            '/version' => [
                'get' => [
                    'tags' => ['System & Monitoring'],
                    'summary' => 'Current API version information',
                    'responses' => ['200' => ['description' => 'Version metadata.']],
                ],
            ],
        ];

        $v2Paths = [
            '/version' => [
                'get' => [
                    'tags' => ['System & Monitoring'],
                    'summary' => 'API v2 specification and experimental roadmap',
                    'responses' => ['200' => ['description' => 'v2 status.']],
                ],
            ],
            '/health' => [
                'get' => [
                    'tags' => ['System & Monitoring'],
                    'summary' => 'v2 High-availability health probe',
                    'responses' => ['200' => ['description' => 'Health status.']],
                ],
            ],
        ];

        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'Community Hub Enterprise API',
                'description' => 'Standardized, multi-versioned REST API architecture for Community Hub with personal access token auth, HMAC-SHA256 signed webhooks, rate limiting, and telemetry monitoring.',
                'version' => $version === 'v2' ? '2.0.0-beta' : '1.0.0',
                'contact' => [
                    'name' => 'API Support Team',
                    'email' => 'api-support@communityhub.io',
                ],
            ],
            'servers' => [
                [
                    'url' => $baseUrl,
                    'description' => strtoupper($version).' Production / Staging Server',
                ],
            ],
            'tags' => [
                ['name' => 'API Tokens', 'description' => 'Personal access token creation, scoping, and revocation via Sanctum'],
                ['name' => 'Webhooks', 'description' => 'Outgoing HMAC-SHA256 signed webhooks and incoming verification'],
                ['name' => 'System & Monitoring', 'description' => 'Health checks, telemetry latency, and version negotiation'],
            ],
            'paths' => $version === 'v2' ? $v2Paths : $v1Paths,
            'components' => [
                'securitySchemes' => [
                    'SanctumBearer' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'Sanctum Token',
                        'description' => 'Enter your Personal Access Token in the format: Bearer <token>',
                    ],
                ],
                'schemas' => [
                    'SuccessEnvelope' => [
                        'type' => 'object',
                        'properties' => [
                            'success' => ['type' => 'boolean', 'example' => true],
                            'version' => ['type' => 'string', 'example' => $version],
                            'status' => ['type' => 'integer', 'example' => 200],
                            'message' => ['type' => 'string', 'example' => 'Operation completed successfully.'],
                            'data' => ['type' => 'object'],
                            'meta' => [
                                'type' => 'object',
                                'properties' => [
                                    'request_id' => ['type' => 'string', 'example' => 'req_651a02fb4c91'],
                                    'timestamp' => ['type' => 'string', 'format' => 'date-time'],
                                ],
                            ],
                        ],
                    ],
                    'ErrorEnvelope' => [
                        'type' => 'object',
                        'properties' => [
                            'type' => ['type' => 'string', 'example' => 'about:blank'],
                            'title' => ['type' => 'string', 'example' => 'Validation Error'],
                            'status' => ['type' => 'integer', 'example' => 422],
                            'detail' => ['type' => 'string', 'example' => 'The given data was invalid.'],
                            'code' => ['type' => 'string', 'example' => 'VALIDATION_ERROR'],
                            'invalid_params' => ['type' => 'array', 'items' => ['type' => 'object']],
                            'request_id' => ['type' => 'string'],
                        ],
                    ],
                ],
                'responses' => [
                    'ValidationError' => [
                        'description' => 'Validation Failed (RFC 7807 problem details)',
                        'content' => [
                            'application/problem+json' => [
                                'schema' => ['$ref' => '#/components/schemas/ErrorEnvelope'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
