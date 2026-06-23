<?php

namespace App\Services;

use App\Enums\AuditActionType;
use App\Models\MenuModel;
use App\Models\MenuScheduleModel;
use DateTimeImmutable;
use CodeIgniter\Database\BaseConnection;
use Config\Database;

class MenuScheduleManagementService
{
    protected MenuScheduleModel $menuScheduleModel;
    protected MenuModel $menuModel;
    protected MenuCalendarContract $calendarContract;
    protected AuditService $auditService;
    protected BaseConnection $db;

    public function __construct()
    {
        $this->menuScheduleModel = new MenuScheduleModel();
        $this->menuModel = new MenuModel();
        $this->calendarContract = new MenuCalendarContract();
        $this->auditService = new AuditService();
        $this->db = Database::connect();
    }

    public function getAllSchedules(): array
    {
        $rows = $this->menuScheduleModel->getAllWithMenu();

        return [
            'success' => true,
            'data' => array_map(fn(array $row): array => $this->formatSchedule($row), $rows),
            'meta' => [
                'page' => 1,
                'perPage' => max(1, count($rows)),
                'total' => count($rows),
                'totalPages' => count($rows) > 0 ? 1 : 0,
                'paginated' => false,
            ],
        ];
    }

    public function getScheduleById(int $id): ?array
    {
        $row = $this->menuScheduleModel->findWithMenuById($id);

        return $row === null ? null : $this->formatSchedule($row);
    }

    public function createSchedule(array $data, ?int $actorId = null, ?string $ipAddress = null): array
    {
        $validated = $this->validateWritePayload($data);
        if (!$validated['success']) {
            return $validated;
        }

        $dayOfMonth = (int) $data['day_of_month'];
        $menuId = (int) $data['menu_id'];
        $this->db->transStart();

        $created = $this->menuScheduleModel->insert([
            'day_of_month' => $dayOfMonth,
            'menu_id' => $menuId,
            'patient_count' => $patientCount,
        ], true);

        if ($created === false) {
            $this->db->transRollback();
            return [
                'success' => false,
                'message' => 'Failed to create menu schedule.',
                'errors' => $this->menuScheduleModel->errors(),
            ];
        }

        if (!$this->auditService->log($actorId, AuditActionType::Create, 'menu_schedules', (int) $created, 'Menu schedule created.', null, $data, $ipAddress)) {
            $this->db->transRollback();
            return [
                'success' => false,
                'message' => 'Failed to create menu schedule.',
            ];
        }

        $this->db->transComplete();

        if (!$this->db->transStatus()) {
            return [
                'success' => false,
                'message' => 'Failed to create menu schedule.',
            ];
        }

        return [
            'success' => true,
            'schedule' => $this->getScheduleById((int) $created),
        ];
    }

    public function updateSchedule(int $id, array $data, ?int $actorId = null, ?string $ipAddress = null): array
    {
        $existing = $this->menuScheduleModel->find($id);
        if ($existing === null) {
            return [
                'success' => false,
                'message' => 'Menu schedule not found.',
            ];
        }

        $validation = service('validation');
        if (
            !$validation->setRules([
                'day_of_month' => 'permit_empty|is_natural_no_zero|less_than_equal_to[31]',
                'menu_id' => 'permit_empty|is_natural_no_zero',
                'patient_count' => 'permit_empty|is_natural',
            ])->run($data)
        ) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validation->getErrors(),
            ];
        }

        $resolvedDayOfMonth = array_key_exists('day_of_month', $data)
            ? (int) $data['day_of_month']
            : (int) $existing['day_of_month'];
        $resolvedMenuId = array_key_exists('menu_id', $data)
            ? (int) $data['menu_id']
            : (int) $existing['menu_id'];

        $errors = [];

        if ($this->menuModel->find($resolvedMenuId) === null || $resolvedMenuId < 1 || $resolvedMenuId > 11) {
            $errors['menu_id'] = 'The selected menu is invalid.';
        }

        if ($errors !== []) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $errors,
            ];
        }

        $updateData = [];
        if (array_key_exists('day_of_month', $data)) {
            $updateData['day_of_month'] = $resolvedDayOfMonth;
        }
        if (array_key_exists('menu_id', $data)) {
            $updateData['menu_id'] = $resolvedMenuId;
        }
        if (array_key_exists('patient_count', $data)) {
            $updateData['patient_count'] = $data['patient_count'] !== '' ? (int) $data['patient_count'] : null;
        }

        if ($updateData === []) {
            return [
                'success' => true,
                'schedule' => $this->getScheduleById($id),
            ];
        }

        $this->db->transStart();

        if (!$this->menuScheduleModel->update($id, $updateData)) {
            $this->db->transRollback();
            return [
                'success' => false,
                'message' => 'Failed to update menu schedule.',
                'errors' => $this->menuScheduleModel->errors(),
            ];
        }

        if (!$this->auditService->log($actorId, AuditActionType::Update, 'menu_schedules', $id, 'Menu schedule updated.', $existing, $updateData, $ipAddress)) {
            $this->db->transRollback();
            return [
                'success' => false,
                'message' => 'Failed to update menu schedule.',
            ];
        }

        $this->db->transComplete();

        if (!$this->db->transStatus()) {
            return [
                'success' => false,
                'message' => 'Failed to update menu schedule.',
            ];
        }

        return [
            'success' => true,
            'schedule' => $this->getScheduleById($id),
        ];
    }

    public function resolveCalendar(array $queryParams): array
    {
        $month = trim((string) ($queryParams['month'] ?? ''));
        $date = trim((string) ($queryParams['date'] ?? ''));
        $startDate = trim((string) ($queryParams['start_date'] ?? ''));
        $endDate = trim((string) ($queryParams['end_date'] ?? ''));

        $modeCount = 0;
        $modeCount += $month !== '' ? 1 : 0;
        $modeCount += $date !== '' ? 1 : 0;
        $modeCount += ($startDate !== '' || $endDate !== '') ? 1 : 0;

        if ($modeCount === 0) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => ['month' => 'One resolver mode is required: month, date, or start_date+end_date.'],
            ];
        }

        if ($modeCount > 1) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => ['query' => 'Use exactly one resolver mode: month, date, or start_date+end_date.'],
            ];
        }

        if ($date !== '') {
            return $this->resolveDate($date);
        }

        if ($startDate !== '' || $endDate !== '') {
            return $this->resolveRange($startDate, $endDate);
        }

        return $this->resolveMonth($month);
    }

    private function resolveDate(string $date): array
    {
        $resolvedDate = $this->parseStrictDate($date);
        if ($resolvedDate === null) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => ['date' => 'The date field must be a valid date in Y-m-d format.'],
            ];
        }

        $assignments = $this->resolveEffectiveAssignments($resolvedDate);

        return [
            'success' => true,
            'data' => [
                'date' => $resolvedDate->format('Y-m-d'),
                'day_of_month' => (int) $resolvedDate->format('j'),
                'assignments' => $assignments,
            ],
        ];
    }

    private function resolveMonth(string $month): array
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => ['month' => 'The month field must use Y-m format.'],
            ];
        }

        [$year, $monthNum] = array_map('intval', explode('-', $month));
        if (!checkdate($monthNum, 1, $year)) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => ['month' => 'The month field must be a valid calendar month.'],
            ];
        }

        $daysInMonth = (int) date('t', mktime(0, 0, 0, $monthNum, 1, $year));
        $rows = [];

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $monthNum, $day));
            $assignments = $this->resolveEffectiveAssignments($date);

            $rows[] = [
                'date' => $date->format('Y-m-d'),
                'day_of_month' => $day,
                'assignments' => $assignments,
            ];
        }

        return [
            'success' => true,
            'data' => $rows,
            'meta' => [
                'month' => sprintf('%04d-%02d', $year, $monthNum),
                'total' => count($rows),
            ],
        ];
    }

    private function resolveRange(string $startDate, string $endDate): array
    {
        if ($startDate === '' || $endDate === '') {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => ['start_date' => 'Both start_date and end_date are required for range mode.'],
            ];
        }

        $start = $this->parseStrictDate($startDate);
        $end = $this->parseStrictDate($endDate);

        if ($start === null || $end === null) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => ['start_date' => 'The start_date and end_date fields must be valid dates in Y-m-d format.'],
            ];
        }

        if ($start > $end) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => ['start_date' => 'The start_date must be earlier than or equal to end_date.'],
            ];
        }

        $rows = [];
        $current = $start;

        while ($current <= $end) {
            $assignments = $this->resolveEffectiveAssignments($current);

            $rows[] = [
                'date' => $current->format('Y-m-d'),
                'day_of_month' => (int) $current->format('j'),
                'assignments' => $assignments,
            ];

            $current = $current->modify('+1 day');
        }

        return [
            'success' => true,
            'data' => $rows,
            'meta' => [
                'start_date' => $start->format('Y-m-d'),
                'end_date' => $end->format('Y-m-d'),
                'total' => count($rows),
            ],
        ];
    }

    private function parseStrictDate(string $value): ?DateTimeImmutable
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));
        if (!checkdate($month, $day, $year)) {
            return null;
        }

        return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
    }

    private function resolveEffectiveAssignments(DateTimeImmutable $date): array
    {
        if ($date->format('m-d') === '02-29') {
            return [['menu_id' => 9, 'patient_count' => null]];
        }

        if ((int) $date->format('j') === 31) {
            return [['menu_id' => 11, 'patient_count' => null]];
        }

        $schedules = $this->menuScheduleModel->findAssignmentsByDay((int) $date->format('j'));

        if (!empty($schedules)) {
            return array_map(fn($s) => [
                'menu_id' => (int) $s['menu_id'],
                'patient_count' => isset($s['patient_count']) ? (int) $s['patient_count'] : null,
            ], $schedules);
        }

        return [['menu_id' => $this->calendarContract->resolvePackageId($date), 'patient_count' => null]];
    }

    private function validateWritePayload(array $data): array
    {
        $validation = service('validation');
        if (
            !$validation->setRules([
                'day_of_month' => 'required|is_natural_no_zero|less_than_equal_to[31]',
                'menu_id' => 'required|is_natural_no_zero',
                'patient_count' => 'permit_empty|is_natural',
            ])->run($data)
        ) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validation->getErrors(),
            ];
        }

        $errors = [];
        $menuId = (int) $data['menu_id'];
        if ($this->menuModel->find($menuId) === null || $menuId < 1 || $menuId > 11) {
            $errors['menu_id'] = 'The selected menu is invalid.';
        }

        if ($errors !== []) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $errors,
            ];
        }

        return ['success' => true];
    }

    private function formatSchedule(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'day_of_month' => (int) $row['day_of_month'],
            'menu_id' => (int) $row['menu_id'],
            'patient_count' => isset($row['patient_count']) ? (int) $row['patient_count'] : null,
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'menu' => [
                'id' => (int) $row['menu_id'],
                'name' => $row['menu_name'] ?? null,
            ],
        ];
    }
}
