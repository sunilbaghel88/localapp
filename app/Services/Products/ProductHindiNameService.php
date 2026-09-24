<?php

namespace App\Services\Products;

use App\AiAgents\ProductHindiNameAgent;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProductHindiNameService
{
    public function translate(string $name, ?User $user = null): ?string
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $hindi = $this->translateMany([$name], $user)[$name] ?? null;

        return $hindi !== null && $hindi !== '' ? $hindi : null;
    }

    /**
     * @param  array<int, string>  $names
     * @return array<string, string>
     */
    public function translateMany(array $names, ?User $user = null): array
    {
        $unique = [];
        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name !== '') {
                $unique[$name] = $name;
            }
        }

        if ($unique === [] || ! class_exists(\LarAgent\Agent::class) || ! class_exists(ProductHindiNameAgent::class)) {
            return [];
        }

        $resolved = [];
        foreach (array_chunk(array_values($unique), 20) as $chunk) {
            try {
                $chunkResult = $this->translateChunk($chunk, $user);
                if ($chunkResult === []) {
                    Log::warning('Hindi product name AI returned nothing usable', [
                        'count' => count($chunk),
                    ]);
                }
                $resolved = array_merge($resolved, $chunkResult);
            } catch (\Throwable $e) {
                Log::warning('Hindi product name AI failed', [
                    'message' => $e->getMessage(),
                    'count' => count($chunk),
                ]);
            }
        }

        return $resolved;
    }

    /**
     * @param  array<int, string>  $names
     * @return array<string, string>
     */
    protected function translateChunk(array $names, ?User $user): array
    {
        $message = count($names) === 1
            ? $names[0]
            : json_encode(array_values($names), JSON_UNESCAPED_UNICODE);

        $agent = $user
            ? ProductHindiNameAgent::forUser($user)
            : ProductHindiNameAgent::for('product_hindi_name');

        $raw = $agent->respond($message);

        return $this->mapResponse($raw, $names);
    }

    /**
     * LarAgent returns parsed JSON as an array. Casting that array to a
     * string becomes the word "Array", which then fails to decode.
     *
     * @param  array<int, string>  $names
     * @return array<string, string>
     */
    protected function mapResponse(mixed $raw, array $names): array
    {
        $decoded = $this->normalizeDecoded($raw);
        if (! is_array($decoded)) {
            Log::warning('Hindi product name AI response was not JSON', [
                'type' => get_debug_type($raw),
                'preview' => Str::limit(is_scalar($raw) ? (string) $raw : '', 300, ''),
            ]);

            return [];
        }

        if (isset($decoded['name_hi']) && count($names) === 1) {
            $hindi = $this->clean((string) $decoded['name_hi']);

            return $hindi !== null ? [$names[0] => $hindi] : [];
        }

        $rows = $decoded;
        if (! array_is_list($decoded)) {
            $nested = $decoded['names'] ?? $decoded['items'] ?? $decoded['products'] ?? $decoded['data'] ?? null;
            if (is_array($nested) && array_is_list($nested)) {
                $rows = $nested;
            } else {
                $fromKeys = $this->mapByEnglishKey($decoded, $names);
                if ($fromKeys !== []) {
                    return $fromKeys;
                }
                $rows = array_values($decoded);
            }
        }

        if (! is_array($rows) || ! array_is_list($rows)) {
            return [];
        }

        $resolved = [];
        foreach ($names as $index => $name) {
            $hindi = $this->hindiFromRow($rows[$index] ?? null);
            if ($hindi !== null) {
                $resolved[$name] = $hindi;
            }
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $english = trim((string) ($row['name'] ?? $row['product_name'] ?? ''));
            $hindi = $this->hindiFromRow($row);
            if ($english === '' || $hindi === null) {
                continue;
            }
            foreach ($names as $name) {
                if (! isset($resolved[$name]) && $this->sameName($english, $name)) {
                    $resolved[$name] = $hindi;
                }
            }
        }

        return $resolved;
    }

    /**
     * @param  array<int, string>  $names
     * @return array<string, string>
     */
    protected function mapByEnglishKey(array $decoded, array $names): array
    {
        $resolved = [];
        foreach ($names as $name) {
            foreach ($decoded as $key => $value) {
                if (! is_string($key) || ! $this->sameName($key, $name)) {
                    continue;
                }
                $hindi = is_string($value) ? $this->clean($value) : $this->hindiFromRow($value);
                if ($hindi !== null) {
                    $resolved[$name] = $hindi;
                }
            }
        }

        return $resolved;
    }

    protected function hindiFromRow(mixed $row): ?string
    {
        if (is_string($row)) {
            return $this->clean($row);
        }
        if (! is_array($row)) {
            return null;
        }

        foreach (['name_hi', 'hindi', 'hindi_name'] as $key) {
            if (isset($row[$key]) && is_string($row[$key])) {
                return $this->clean($row[$key]);
            }
        }

        return null;
    }

    protected function sameName(string $left, string $right): bool
    {
        $normalize = static function (string $value): string {
            $value = mb_strtolower(trim($value));

            return preg_replace('/\s+/', ' ', $value) ?? $value;
        };

        return $normalize($left) === $normalize($right);
    }

    protected function normalizeDecoded(mixed $raw): mixed
    {
        if (is_object($raw) && method_exists($raw, 'toArray')) {
            $raw = $raw->toArray();
        }

        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw) || (is_object($raw) && method_exists($raw, '__toString'))) {
            return $this->decodeJson((string) $raw);
        }

        return null;
    }

    protected function decodeJson(string $raw): mixed
    {
        $raw = trim($raw);
        $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw) ?? $raw;
        $raw = preg_replace('/\s*```$/', '', $raw) ?? $raw;

        $decoded = json_decode(trim($raw), true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/(\{.*\}|\[.*\])/s', $raw, $match) === 1) {
            $decoded = json_decode($match[1], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    protected function clean(string $value): ?string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
        if ($value === '') {
            return null;
        }

        return Str::limit($value, 255, '');
    }
}
