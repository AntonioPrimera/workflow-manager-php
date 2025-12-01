<?php

declare(strict_types=1);

namespace AntonioPrimera\WorkflowManager\Api;

use Illuminate\Support\Facades\Http;

class WorkflowManagerClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $token,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listProjects(): array
    {
        $response = $this->request('GET', '/api/wfm/projects');

        return $response['data'] ?? [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createProject(string $name, array $payload = []): array
    {
        $body = array_merge([
            'name' => $name,
        ], $payload);

        $response = $this->request('POST', '/api/wfm/projects', $body);

        return $response['data'] ?? [];
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $payload = null): array
    {
        $method = strtoupper($method);

        $options = [];

        if ($payload !== null) {
            $options['json'] = $payload;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->token,
            'Accept' => 'application/json',
        ])->send($method, rtrim($this->baseUrl, '/').$path, $options);

        if ($response->status() === 401) {
            throw new \RuntimeException('Authentication with Workflow Manager failed');
        }

        $response->throw();

        return (array) $response->json();
    }
}
