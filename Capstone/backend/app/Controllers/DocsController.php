<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class DocsController extends BaseController
{
    public function index()
    {
        return view('docs/index');
    }

    public function spec(): ResponseInterface
    {
        $spec = Services::openApiSpec()->getSpecJson();

        return $this->response
            ->setStatusCode(200)
            ->setContentType('application/json')
            ->setBody($spec['json']);
    }
}
