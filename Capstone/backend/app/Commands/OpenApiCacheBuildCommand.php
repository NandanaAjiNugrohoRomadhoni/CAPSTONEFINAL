<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Services;
use Throwable;

class OpenApiCacheBuildCommand extends BaseCommand
{
    protected $group       = 'Docs';
    protected $name        = 'docs:cache-openapi';
    protected $description = 'Force regeneration of the cached OpenAPI JSON document.';
    protected $usage       = 'docs:cache-openapi';

    public function run(array $params)
    {
        try {
            $result = Services::openApiSpec(false)->getSpecJson(true);
        } catch (Throwable $exception) {
            CLI::error($exception->getMessage());

            return EXIT_ERROR;
        }

        CLI::write('OpenAPI spec cache generated successfully.', 'green');
        CLI::write('Cache path: ' . $result['cache_path'], 'green');
        CLI::write('Source: ' . $result['source'], 'green');
        CLI::write('Generated at: ' . date(DATE_ATOM, $result['generated_at']), 'green');

        return EXIT_SUCCESS;
    }
}
