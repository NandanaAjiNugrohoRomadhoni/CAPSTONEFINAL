<?php

namespace App\Services;

use App\Models\DailyPatientModel;

class DailyPatientService
{
    protected DailyPatientModel $dailyPatientModel;

    public function __construct()
    {
        $this->dailyPatientModel = new DailyPatientModel();
    }

    public function getAllDailyPatients(): array
    {
        $rows = $this->dailyPatientModel
            ->orderBy('service_date', 'ASC')
            ->findAll();

        return [
            'success' => true,
            'data'    => array_map(fn (array $row): array => $this->formatRow($row), $rows),
            'meta'    => [
                'page'       => 1,
                'perPage'    => max(1, count($rows)),
                'total'      => count($rows),
                'totalPages' => count($rows) > 0 ? 1 : 0,
                'paginated'  => false,
            ],
        ];
    }

    public function getDailyPatientById(int $id): ?array
    {
        $row = $this->dailyPatientModel->find($id);

        return $row === null ? null : $this->formatRow($row);
    }

    public function getDailyPatientByServiceDate(string $serviceDate): ?array
    {
        $row = $this->dailyPatientModel->findByServiceDate($serviceDate);

        return $row === null ? null : $this->formatRow($row);
    }

    public function createDailyPatient(array $data): array
    {
        $validation = service('validation');
        if (! $validation->setRules([
            'service_date'   => 'required|regex_match[/^\d{4}-\d{2}-\d{2}$/]',
            'total_patients' => 'required|is_natural',
            'notes'          => 'permit_empty|string',
        ])->run($data)) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validation->getErrors(),
            ];
        }

        if (! $this->isValidDate($data['service_date'])) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => [
                    'service_date' => 'The service_date field must be a valid date in Y-m-d format.',
                ],
            ];
        }

        $existing = $this->dailyPatientModel->findByServiceDate($data['service_date']);
        if ($existing !== null) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => [
                    'service_date' => 'A daily patient input for this service_date already exists. Use PUT /api/v1/daily-patients/{id} to update the existing row.',
                    'existing_id'  => (string) $existing['id'],
                ],
            ];
        }

        $created = $this->dailyPatientModel->insert([
            'service_date'   => $data['service_date'],
            'total_patients' => (int) $data['total_patients'],
            'notes'          => array_key_exists('notes', $data) ? $data['notes'] : null,
        ], true);

        if ($created === false) {
            return [
                'success' => false,
                'message' => 'Failed to create daily patient.',
                'errors'  => $this->dailyPatientModel->errors(),
            ];
        }

        return [
            'success'      => true,
            'daily_patient' => $this->getDailyPatientById((int) $created),
        ];
    }

    public function updateDailyPatient(int $id, array $data): array
    {
        $existingRow = $this->dailyPatientModel->find($id);
        if ($existingRow === null) {
            return [
                'success' => false,
                'message' => 'Daily patient not found.',
                'errors'  => [],
            ];
        }

        $validation = service('validation');
        if (! $validation->setRules([
            'service_date'   => 'permit_empty|regex_match[/^\d{4}-\d{2}-\d{2}$/]',
            'total_patients' => 'permit_empty|is_natural',
            'notes'          => 'permit_empty|string',
        ])->run($data)) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validation->getErrors(),
            ];
        }

        $serviceDate = array_key_exists('service_date', $data)
            ? (string) $data['service_date']
            : (string) $existingRow['service_date'];

        if (! $this->isValidDate($serviceDate)) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => [
                    'service_date' => 'The service_date field must be a valid date in Y-m-d format.',
                ],
            ];
        }

        $duplicate = $this->dailyPatientModel->findByServiceDate($serviceDate, $id);
        if ($duplicate !== null) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => [
                    'service_date' => 'A daily patient input for this service_date already exists.',
                    'existing_id'  => (string) $duplicate['id'],
                ],
            ];
        }

        $payload = [
            'service_date'   => $serviceDate,
            'total_patients' => array_key_exists('total_patients', $data)
                ? (int) $data['total_patients']
                : (int) $existingRow['total_patients'],
            'notes'          => array_key_exists('notes', $data)
                ? $data['notes']
                : $existingRow['notes'],
        ];

        if (! $this->dailyPatientModel->update($id, $payload)) {
            return [
                'success' => false,
                'message' => 'Failed to update daily patient.',
                'errors'  => $this->dailyPatientModel->errors(),
            ];
        }

        return [
            'success'       => true,
            'daily_patient' => $this->getDailyPatientById($id),
        ];
    }

    private function formatRow(array $row): array
    {
        return [
            'id'             => (int) $row['id'],
            'service_date'   => $row['service_date'],
            'total_patients' => (int) $row['total_patients'],
            'notes'          => $row['notes'],
            'created_at'     => $row['created_at'],
            'updated_at'     => $row['updated_at'],
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
