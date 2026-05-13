<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

class TestFailure extends BaseController
{
    public function triggerUnhandledException(): ResponseInterface
    {
        if (ENVIRONMENT !== 'testing') {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['message' => 'This endpoint is only available in testing environment.']);
        }

        throw new RuntimeException('Test unhandled exception for JSON error envelope verification');
    }

    public function triggerNotFound(): ResponseInterface
    {
        if (ENVIRONMENT !== 'testing') {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['message' => 'This endpoint is only available in testing environment.']);
        }

        throw new \CodeIgniter\Exceptions\PageNotFoundException('Test resource not found');
    }
}
