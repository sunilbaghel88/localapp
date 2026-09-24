<?php

namespace App\Services\Products;

use App\AiAgents\ProductHindiNameAgent;
use App\Models\User;
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
                $resolved = array_merge($resolved, $this->translateChunk($chunk, $user));
            } catch (\Throwable) {
                // Leave this chunk blank. Callers can still save the English name.
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

        /** @var string $raw */
        $raw = $agent->respond($message);
        $decoded = $this->decodeJson((string) $raw);
        if (! is_array($decoded)) {
            return [];
        }

        $resolved = [];
        if (count($names) === 1 && isset($decoded['name_hi'])) {
            $hindi = $this->clean((string) $decoded['name_hi']);
            if ($hindi !== null) {
                $resolved[$names[0]] = $hindi;
            }

            return $resolved;
        }

        $rows = array_is_list($decoded) ? $decoded : ($decoded['names'] ?? $decoded['items'] ?? null);
        if (! is_array($rows) || ! array_is_list($rows)) {
            return [];
        }

        foreach ($names as $index => $name) {
            $row = $rows[$index] ?? null;
            $hindi = is_array($row) ? $this->clean((string) ($row['name_hi'] ?? '')) : null;
            if ($hindi !== null) {
                $resolved[$name] = $hindi;
            }
        }

        return $resolved;
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
