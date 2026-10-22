<?php

declare(strict_types=1);

namespace App\Support;

final class ReleaseIdentity
{
    public function __construct(private readonly ?string $path = null) {}

    public function data(): array
    {
        $file = $this->path ?? base_path('RELEASE.json');
        if (! is_file($file) && ! app()->environment('production')) {
            return ['application' => 'midway-weather', 'release' => 'development', 'contracts' => CanonicalResponse::CONTRACTS];
        }
        if (! is_file($file)) {
            throw new \RuntimeException('Missing release metadata.');
        }
        $release = json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
        if (($release['application'] ?? null) !== 'midway-weather' || ($release['contract'] ?? null) !== 'midway.release-manifest/v1'
            || ($release['contracts'] ?? null) !== CanonicalResponse::CONTRACTS || empty($release['release_id']) || empty($release['source_commit']) || empty($release['built_at'])) {
            throw new \RuntimeException('Invalid release metadata.');
        }

        return ['application' => 'midway-weather', 'release' => $release['release_id'], 'source_commit' => $release['source_commit'], 'built_at' => $release['built_at'], 'contracts' => CanonicalResponse::CONTRACTS];
    }
}
