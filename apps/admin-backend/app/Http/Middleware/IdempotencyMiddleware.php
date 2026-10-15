<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyRecord;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idempotency for mutating API calls.
 *
 * The client sends an `Idempotency-Key` header (or `idempotency_key` field).
 * The first request executes normally and its response is stored; replays of
 * the same key return the stored response (HTTP 200) without re-executing.
 * Reusing a key with a DIFFERENT payload is rejected (422).
 */
class IdempotencyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $rawKey = $request->header('Idempotency-Key') ?? $request->input('idempotency_key');

        if (empty($rawKey) || !is_string($rawKey)) {
            return response()->json([
                'message' => 'Idempotency-Key wajib disertakan.',
                'code' => 'idempotency_key_required',
            ], 422);
        }

        // Namespace the key per user so two users can never collide.
        $key = 'u' . ($request->user()?->id ?? 'guest') . ':' . substr($rawKey, 0, 80);
        $hash = $this->requestHash($request);

        $existing = IdempotencyRecord::where('key', $key)->first();

        if ($existing && !$existing->isExpired()) {
            if (!hash_equals($existing->request_hash, $hash)) {
                return response()->json([
                    'message' => 'Idempotency-Key sudah dipakai dengan data berbeda.',
                    'code' => 'idempotency_key_mismatch',
                ], 422);
            }
            // Replay: return the stored record with 200 (never re-execute).
            return response(
                $existing->response_body,
                200,
                ['Content-Type' => 'application/json', 'X-Idempotent-Replayed' => 'true']
            );
        }

        /** @var Response $response */
        $response = $next($request);

        if ($response->getStatusCode() < 500) {
            IdempotencyRecord::updateOrCreate(
                ['key' => $key],
                [
                    'user_id' => $request->user()?->id,
                    'method' => $request->method(),
                    'route' => $request->path(),
                    'request_hash' => $hash,
                    'response_status' => $response->getStatusCode(),
                    'response_body' => $response->getContent() ?: '{}',
                    'expires_at' => now()->addDay(),
                ]
            );
        }

        return $response;
    }

    protected function requestHash(Request $request): string
    {
        // NOTE: build the hash from input() + the raw FileBag ONLY.
        // $request->all()/except()/file() populate Request::$convertedFiles,
        // which would hide file keys that controllers add later (e.g. PWA
        // field-name aliases like photo -> selfie).
        $input = $request->input();
        unset($input['selfie'], $input['selfie_original'], $input['photo']);
        unset($input['receipt'], $input['bukti'], $input['idempotency_key']);

        $parts = [$request->method(), $request->path(), $input];
        // NOTE: use the raw FileBag here (files->get), NOT $request->file():
        // file() populates Request::$convertedFiles cache, which would hide
        // keys added later by controllers (PWA field-name aliases).
        foreach (['selfie', 'selfie_original', 'photo', 'receipt', 'bukti'] as $field) {
            $file = $request->files->get($field);
            if ($file instanceof UploadedFile) {
                $parts[] = $field . ':' . md5_file($file->getRealPath());
            }
        }
        return hash('sha256', serialize($parts));
    }
}
