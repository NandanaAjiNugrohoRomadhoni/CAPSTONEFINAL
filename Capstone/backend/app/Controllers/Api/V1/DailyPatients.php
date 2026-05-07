<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\DailyPatientService;
use CodeIgniter\HTTP\ResponseInterface;

class DailyPatients extends BaseController
{
    protected DailyPatientService $dailyPatientService;

    public function __construct()
    {
        $this->dailyPatientService = new DailyPatientService();
    }

    public function index(): ResponseInterface
    {
        $result = $this->dailyPatientService->getAllDailyPatients();

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'data'  => $result['data'],
                'meta'  => $result['meta'],
                'links' => $this->buildStaticLinks(),
            ]);
    }

    public function show(string $serviceDate): ResponseInterface
    {
        $validation = service('validation');

        if (! $validation->setRules([
            'service_date' => 'required|regex_match[/^\d{4}-\d{2}-\d{2}$/]',
        ])->run(['service_date' => $serviceDate])) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'message' => 'Validation failed.',
                    'errors'  => $validation->getErrors(),
                ]);
        }

        if (! $this->isValidDate($serviceDate)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'message' => 'Validation failed.',
                    'errors'  => [
                        'service_date' => 'The service_date field must be a valid date in Y-m-d format.',
                    ],
                ]);
        }

        $row = $this->dailyPatientService->getDailyPatientByServiceDate($serviceDate);

        if ($row === null) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'message' => 'Daily patient not found.',
                ]);
        }

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'data' => $row,
            ]);
    }

    public function create(): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? [];
        $result = $this->dailyPatientService->createDailyPatient($data);

        if (! $result['success']) {
            return $this->response
                ->setStatusCode($result['message'] === 'Failed to create daily patient.' ? 422 : 400)
                ->setJSON([
                    'message' => $result['message'],
                    'errors'  => $result['errors'] ?? [],
                ]);
        }

        return $this->response
            ->setStatusCode(201)
            ->setJSON([
                'message' => 'Daily patient created successfully.',
                'data'    => $result['daily_patient'],
            ]);
    }

    private function buildStaticLinks(): array
    {
        $self = current_url();

        return [
            'self'     => $self,
            'first'    => $self,
            'last'     => $self,
            'next'     => null,
            'previous' => null,
        ];
    }

    private function isValidDate(string $value): bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        return checkdate($month, $day, $year);
    }
}
