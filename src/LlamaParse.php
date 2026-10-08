<?php

declare(strict_types=1);

namespace Hwkdo\LlamaParseLaravel;

use Hwkdo\LlamaParseLaravel\Exceptions\LlamaParseException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class LlamaParse
{
    public function configured(): bool
    {
        return $this->apiKey() !== '';
    }

    public function parse(string $contents, string $filename): string
    {
        $jobId = $this->start($contents, $filename);
        $deadline = microtime(true) + max(1, (int) config('llama-parse-laravel.timeout_seconds', 300));

        while (true) {
            $payload = $this->result($jobId);
            $job = is_array($payload['job'] ?? null) ? $payload['job'] : [];
            $status = (string) ($job['status'] ?? '');

            if ($status === 'COMPLETED') {
                return $this->markdown($payload);
            }

            if ($status === 'FAILED' || $status === 'CANCELLED') {
                $message = $job['error_message'] ?? null;
                throw new LlamaParseException(is_string($message) && $message !== ''
                    ? $message
                    : 'LlamaParse hat die Datei nicht gelesen.');
            }

            if (microtime(true) >= $deadline) {
                throw new LlamaParseException('LlamaParse hat das Zeitlimit überschritten.');
            }

            $waitMs = max(0, (int) config('llama-parse-laravel.poll_interval_ms', 2000));
            if ($waitMs > 0) {
                usleep($waitMs * 1000);
            }
        }
    }

    private function start(string $contents, string $filename): string
    {
        $configuration = json_encode([
            'tier' => (string) config('llama-parse-laravel.tier', 'agentic'),
            'version' => (string) config('llama-parse-laravel.version', 'latest'),
        ], JSON_THROW_ON_ERROR);

        $response = $this->request()
            ->attach('file', $contents, $filename)
            ->post($this->baseUrl().'/api/v2/parse/upload', [
                'configuration' => $configuration,
            ]);

        $this->throwIfFailed($response);

        $jobId = $response->json('id');
        if (! is_string($jobId) || $jobId === '') {
            throw new LlamaParseException('LlamaParse hat keine Job-ID geliefert.');
        }

        return $jobId;
    }

    /**
     * @return array<string, mixed>
     */
    private function result(string $jobId): array
    {
        $response = $this->request()->get($this->baseUrl().'/api/v2/parse/'.rawurlencode($jobId), [
            'expand' => 'markdown_full',
        ]);

        $this->throwIfFailed($response);

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new LlamaParseException('LlamaParse hat keine Antwort geliefert.');
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function markdown(array $payload): string
    {
        $markdown = $payload['markdown_full'] ?? null;
        if (is_array($markdown)) {
            $markdown = $markdown['markdown'] ?? null;
        }

        if (! is_string($markdown) || trim($markdown) === '') {
            throw new LlamaParseException('LlamaParse hat keinen Text geliefert.');
        }

        return $markdown;
    }

    private function request(): PendingRequest
    {
        $apiKey = $this->apiKey();
        if ($apiKey === '') {
            throw new LlamaParseException('LLAMA_CLOUD_API_KEY fehlt.');
        }

        return Http::withToken($apiKey)
            ->acceptJson()
            ->timeout(120);
    }

    private function throwIfFailed(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $detail = $response->json('detail');
        if (is_string($detail) && $detail !== '') {
            throw new LlamaParseException($detail);
        }

        if (is_array($detail)) {
            $messages = [];
            foreach ($detail as $item) {
                if (is_array($item) && is_string($item['msg'] ?? null)) {
                    $messages[] = $item['msg'];
                }
            }

            if ($messages !== []) {
                throw new LlamaParseException(implode(' ', $messages));
            }
        }

        throw new LlamaParseException('LlamaParse-Anfrage fehlgeschlagen ('.$response->status().').');
    }

    private function apiKey(): string
    {
        return trim((string) config('llama-parse-laravel.api_key'));
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('llama-parse-laravel.base_url'), '/');
    }
}
