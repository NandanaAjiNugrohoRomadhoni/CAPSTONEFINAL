<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\DailyPatientService;
use CodeIgniter\HTTP\ResponseInterface;
use OpenApi\Annotations as OA;

/**
 * Daily Patients
 *
 * Daily patient input resource addressed by service date.
 */
class DailyPatients extends BaseController
{
    protected DailyPatientService $dailyPatientService;

    public function __construct()
    {
        $this->dailyPatientService = new DailyPatientService();
    }

    /**
     * @OA\Get(
     *     path="/api/v1/daily-patients",
     *     operationId="listDailyPatients",
     *     tags={"Daily Patients"},
     *     summary="List daily patient rows",
     *     description="Returns daily patient rows in the standard data/meta/links envelope. Accessible to admin, dapur, and gudang users. Runtime currently returns a non-paginated collection with meta.paginated=false and static self/first/last links for the current route.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Daily patient collection.", @OA\JsonContent(ref="#/components/schemas/DailyPatientCollectionResponse")),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Authenticated user lacks one of the admin, dapur, or gudang roles required by the route group.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/api/v1/daily-patients/{service_date}",
     *     operationId="showDailyPatientByServiceDate",
     *     tags={"Daily Patients"},
     *     summary="Show one daily patient row by service date",
     *     description="Returns one daily patient resource resolved by service_date in Y-m-d format. Accessible to admin, dapur, and gudang users.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="service_date", in="path", required=true, description="Service date in Y-m-d format.", @OA\Schema(type="string", example="2026-05-01")),
     *     @OA\Response(response=200, description="Daily patient resource.", @OA\JsonContent(ref="#/components/schemas/DailyPatientResource")),
     *     @OA\Response(response=400, description="Validation failed because service_date is missing the Y-m-d shape or is not a real calendar date.", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Authenticated user lacks one of the admin, dapur, or gudang roles required by the route group.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=404, description="Daily patient not found.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
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

    /**
     * @OA\Post(
     *     path="/api/v1/daily-patients",
     *     operationId="createDailyPatient",
     *     tags={"Daily Patients"},
     *     summary="Create daily patient row",
     *     description="Creates a daily patient row for one service_date. Accessible to admin and dapur users. service_date must be unique and valid in Y-m-d format; duplicates return HTTP 400 validation errors.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/DailyPatientCreateRequest")),
     *     @OA\Response(response=201, description="Daily patient created successfully.", @OA\JsonContent(ref="#/components/schemas/DailyPatientMutationResponse")),
     *     @OA\Response(response=400, description="Validation failed for missing fields, invalid service_date format, invalid calendar dates, duplicate service_date values, or invalid notes data.", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Authenticated user lacks one of the admin or dapur roles required by the route group.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=422, description="Persistence failed while creating the daily patient row.", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse"))
     * )
     */
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
