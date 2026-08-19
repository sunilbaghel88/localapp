<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class AppBranding extends Model
{
    protected $fillable = [
        'app_name',
        'tagline',
        'logo_path',
    ];

    public static function current(): self
    {
        $branding = static::query()->first();

        if ($branding) {
            return $branding;
        }

        return static::query()->create([
            'app_name' => 'LocalApp',
            'tagline' => 'LOYALTY PROGRAM',
            'logo_path' => null,
        ]);
    }

    public function logoUrl(): ?string
    {
        if (blank($this->logo_path)) {
            return null;
        }

        $path = ltrim((string) $this->logo_path, '/');

        // Prefer the app media route used by the mobile client.
        return secure_url('media/'.$path);
    }

    public function toApiArray(): array
    {
        return [
            'app_name' => $this->app_name,
            'tagline' => $this->tagline,
            'logo_path' => $this->logo_path,
            'logo_url' => $this->logoUrl(),
        ];
    }

    public function deleteLogoFile(): void
    {
        if (blank($this->logo_path)) {
            return;
        }

        $path = ltrim((string) $this->logo_path, '/');
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
