<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Authenticated adapter for the tDocs document service.
 *
 * Protocol (from tDocs OpenAPI, see SPEC.md):
 *  - Auth: Authorization: Bearer <TDOCS_ADMIN_PASSWORD> (or ?api_key=)
 *  - Resumable upload: POST /api/upload/init {name,size,mime_type,folder_id}
 *      -> {session_id, chunk_size}; POST /api/upload/chunk (multipart
 *      session_id, chunk_index, chunk); POST /api/upload/complete {session_id}
 *      -> 201 file record
 *  - GET /api/files/{id}/meta, GET /api/files/{id}/download, GET /api/files/{id}/stream
 *  - POST /api/folders {name, parent_id?}
 *  - GET /api/health, GET /api/status
 *
 * The admin password NEVER leaves the server: all calls happen server-side,
 * and downloads are proxied by Laravel through short-lived signed URLs.
 *
 * Every failure surfaces as TdocsException (connection errors included), so
 * callers can mark uploads pending and retry asynchronously.
 */
class TdocsService
{
    protected string $baseUrl;
    protected string $password;
    protected int $timeout;
    protected int $tries;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.tdocs.base_url', env('TDOCS_BASE_URL', 'http://tdocs:8080')), '/');
        $this->password = (string) config('services.tdocs.password', env('TDOCS_ADMIN_PASSWORD', ''));
        $this->timeout = (int) config('services.tdocs.timeout', 30);
        $this->tries = 3;
    }

    /** Clone with a different timeout (e.g. short timeout for inline attempts). */
    public function withTimeout(int $seconds): static
    {
        $clone = clone $this;
        $clone->timeout = $seconds;
        return $clone;
    }

    /** Clone without retries (fail fast; the queue worker retries later). */
    public function withoutRetries(): static
    {
        $clone = clone $this;
        $clone->tries = 1;
        return $clone;
    }

    protected function client(): PendingRequest
    {
        $c = Http::baseUrl($this->baseUrl)
            ->withHeaders(['Authorization' => 'Bearer ' . $this->password])
            ->timeout($this->timeout)
            ->connectTimeout(min(5, $this->timeout));
        return $this->tries > 1 ? $c->retry($this->tries, 200, throw: false) : $c;
    }

    /**
     * @throws TdocsException
     */
    protected function post(string $uri, array $data = []): Response
    {
        try {
            return $this->client()->post($uri, $data);
        } catch (ConnectionException $e) {
            throw new TdocsException("tDocs POST $uri: tidak dapat terhubung ke penyimpanan.");
        }
    }

    /**
     * @throws TdocsException
     */
    protected function get(string $uri): Response
    {
        try {
            return $this->client()->get($uri);
        } catch (ConnectionException $e) {
            throw new TdocsException("tDocs GET $uri: tidak dapat terhubung ke penyimpanan.");
        }
    }

    /**
     * @throws TdocsException
     */
    protected function postMultipart(string $uri, array $data, string $fileField, string $fileContents, string $fileName): Response
    {
        try {
            return $this->client()->attach($fileField, $fileContents, $fileName)->post($uri, $data);
        } catch (ConnectionException $e) {
            throw new TdocsException("tDocs POST $uri: tidak dapat terhubung ke penyimpanan.");
        }
    }

    /**
     * @throws TdocsException
     */
    protected function ensureOk(Response $response, string $action): array
    {
        if ($response->failed()) {
            throw new TdocsException(
                "tDocs $action failed: HTTP {$response->status()} — " . substr($response->body(), 0, 300)
            );
        }
        return $response->json() ?? [];
    }

    // ------------------------------------------------------------------
    // Health
    // ------------------------------------------------------------------

    public function healthCheck(): array
    {
        return $this->ensureOk($this->get('/api/health'), 'health check');
    }

    // ------------------------------------------------------------------
    // Folders
    // ------------------------------------------------------------------

    /**
     * Ensure a nested folder path exists (e.g. "attendance/EMP-001/2026/10").
     * Returns the deepest folder id.
     *
     * @throws TdocsException
     */
    public function ensureFolder(string $path): string
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        $parentId = null;
        foreach ($segments as $name) {
            $parentId = $this->findOrCreateFolder($name, $parentId);
        }
        return (string) $parentId;
    }

    protected function findOrCreateFolder(string $name, ?string $parentId): string
    {
        $payload = ['name' => $name];
        if ($parentId !== null) {
            $payload['parent_id'] = $parentId;
        }
        $data = $this->ensureOk($this->post('/api/folders', $payload), "create folder [$name]");

        return (string) ($data['id'] ?? $data['folder_id'] ?? $data['folder']['id'] ?? '');
    }

    // ------------------------------------------------------------------
    // Upload (resumable)
    // ------------------------------------------------------------------

    /**
     * Upload a local file to tDocs using the resumable protocol.
     *
     * @return array{file_id: string, meta: array} tDocs file id + file record
     * @throws TdocsException
     */
    public function uploadFile(string $localPath, string $folderPath, ?string $name = null): array
    {
        if (!is_file($localPath)) {
            throw new TdocsException("Local file not found: $localPath");
        }

        $size = filesize($localPath);
        $name = $name ?? basename($localPath);
        $mime = mime_content_type($localPath) ?: 'application/octet-stream';

        $folderId = $this->ensureFolder($folderPath);

        // 1. init
        $init = $this->ensureOk(
            $this->post('/api/upload/init', [
                'name' => $name,
                'size' => $size,
                'mime_type' => $mime,
                'folder_id' => $folderId,
            ]),
            'upload init'
        );
        $sessionId = $init['session_id'] ?? null;
        $chunkSize = (int) ($init['chunk_size'] ?? (5 * 1024 * 1024));
        if (!$sessionId) {
            throw new TdocsException('tDocs upload init returned no session_id');
        }

        // 2. chunks
        $handle = fopen($localPath, 'rb');
        if ($handle === false) {
            throw new TdocsException("Cannot read local file: $localPath");
        }
        try {
            $index = 0;
            while (!feof($handle)) {
                $chunk = fread($handle, $chunkSize);
                if ($chunk === false || $chunk === '') {
                    break;
                }
                $this->ensureOk(
                    $this->postMultipart('/api/upload/chunk', [
                        'session_id' => $sessionId,
                        'chunk_index' => $index,
                    ], 'chunk', $chunk, 'chunk.bin'),
                    "upload chunk [$index]"
                );
                $index++;
            }
        } finally {
            fclose($handle);
        }

        // 3. complete
        $done = $this->ensureOk(
            $this->post('/api/upload/complete', ['session_id' => $sessionId]),
            'upload complete'
        );

        $fileId = $done['id'] ?? $done['file_id'] ?? $done['file']['id'] ?? null;
        if (!$fileId) {
            throw new TdocsException('tDocs upload complete returned no file id');
        }

        return ['file_id' => (string) $fileId, 'meta' => $done];
    }

    // ------------------------------------------------------------------
    // Retrieval
    // ------------------------------------------------------------------

    /** @throws TdocsException */
    public function getMeta(string $fileId): array
    {
        return $this->ensureOk($this->get("/api/files/{$fileId}/meta"), "file meta [$fileId]");
    }

    /**
     * Download a file's bytes from tDocs. The caller (controller) proxies them
     * to the client through a signed URL — the tDocs password never leaves.
     *
     * @throws TdocsException
     */
    public function downloadBytes(string $fileId): string
    {
        $res = $this->get("/api/files/{$fileId}/download");
        if ($res->failed()) {
            throw new TdocsException("tDocs download [$fileId] failed: HTTP {$res->status()}");
        }
        return $res->body();
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }
}
