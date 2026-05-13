<?php

namespace App\Services;

use Config\OpenApiDocs;
use OpenApi\Generator;
use RuntimeException;
use Throwable;

class OpenApiSpecService
{
    private const CONFIG_PATH = APPPATH . 'Config/OpenApiDocs.php';

    public function __construct(private readonly OpenApiDocs $config = new OpenApiDocs())
    {
    }

    /**
     * @return list<string>
     */
    public function sourceFiles(): array
    {
        return $this->config->sourceFiles;
    }

    public function cachePath(): string
    {
        return $this->config->cachePath;
    }

    /**
     * @return array{json:string, source:string, cache_path:string, generated_at:int}
     */
    public function getSpecJson(bool $forceRefresh = false): array
    {
        $cachePath = $this->cachePath();

        if (! $forceRefresh && $this->isCacheFresh()) {
            $json = $this->readCacheJson();

            return [
                'json' => $json,
                'source' => 'cache',
                'cache_path' => $cachePath,
                'generated_at' => (int) filemtime($cachePath),
            ];
        }

        $json = $this->buildSpecJson();
        $this->writeCacheAtomically($json);

        clearstatcache(true, $cachePath);

        return [
            'json' => $json,
            'source' => 'generated',
            'cache_path' => $cachePath,
            'generated_at' => (int) filemtime($cachePath),
        ];
    }

    public function buildSpecJson(): string
    {
        $generator = new Generator();
        $openapi = $generator
            ->setVersion($this->config->openApiVersion)
            ->generate($this->sourceFiles(), null, false);

        $json = $openapi->toJson();

        if (! $this->isValidSpecJson($json)) {
            throw new RuntimeException('Generated OpenAPI JSON is invalid.');
        }

        return $json;
    }

    public function isCacheFresh(): bool
    {
        $cachePath = $this->cachePath();

        if (! is_file($cachePath) || ! is_readable($cachePath)) {
            return false;
        }

        $json = $this->readFile($cachePath);

        if (! $this->isValidSpecJson($json)) {
            return false;
        }

        $cacheMtime = filemtime($cachePath);
        if ($cacheMtime === false) {
            return false;
        }

        foreach ($this->freshnessSourceFiles() as $sourceFile) {
            $mtime = filemtime($sourceFile);

            if ($mtime === false || $mtime > $cacheMtime) {
                return false;
            }
        }

        return true;
    }

    public function isValidSpecJson(string $json): bool
    {
        if ($json === '') {
            return false;
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return false;
        }

        return is_array($decoded)
            && isset($decoded['openapi'], $decoded['info'], $decoded['paths'], $decoded['components'])
            && is_array($decoded['paths'])
            && is_array($decoded['components']);
    }

    public function writeCacheAtomically(string $json): void
    {
        if (! $this->isValidSpecJson($json)) {
            throw new RuntimeException('Refusing to write invalid OpenAPI JSON cache.');
        }

        $cachePath = $this->cachePath();
        $directory = dirname($cachePath);

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Failed to create OpenAPI cache directory.');
        }

        $tempPath = tempnam($directory, 'openapi-spec-');
        if ($tempPath === false) {
            throw new RuntimeException('Failed to allocate temporary OpenAPI cache file.');
        }

        try {
            if (file_put_contents($tempPath, $json, LOCK_EX) === false) {
                throw new RuntimeException('Failed to write temporary OpenAPI cache file.');
            }

            if (! rename($tempPath, $cachePath)) {
                throw new RuntimeException('Failed to replace OpenAPI cache file.');
            }
        } catch (Throwable $exception) {
            if (is_file($tempPath)) {
                unlink($tempPath);
            }

            throw $exception;
        }
    }

    public function readCacheJson(): string
    {
        $cachePath = $this->cachePath();

        if (! is_file($cachePath)) {
            throw new RuntimeException('OpenAPI cache file does not exist.');
        }

        $json = $this->readFile($cachePath);

        if (! $this->isValidSpecJson($json)) {
            throw new RuntimeException('OpenAPI cache file is invalid.');
        }

        return $json;
    }

    private function readFile(string $path): string
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('Failed to read file: ' . $path);
        }

        return $contents;
    }

    /**
     * @return list<string>
     */
    private function freshnessSourceFiles(): array
    {
        $sourceFiles = [self::CONFIG_PATH, ...$this->sourceFiles()];

        $uniqueFiles = [];

        foreach ($sourceFiles as $sourceFile) {
            $normalized = (string) $sourceFile;

            if ($normalized === '' || in_array($normalized, $uniqueFiles, true)) {
                continue;
            }

            $uniqueFiles[] = $normalized;
        }

        return $uniqueFiles;
    }
}
