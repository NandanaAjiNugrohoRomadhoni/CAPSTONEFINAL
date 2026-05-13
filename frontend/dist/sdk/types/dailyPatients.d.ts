import type { ApiDataResponse, ApiListResponse, ApiMessageDataResponse } from "./common";
/** Daily patient row returned by `GET/POST /api/v1/daily-patients` (api-contract.md §5.7.1). */
export interface DailyPatient {
    id: number;
    service_date: string;
    total_patients: number;
    notes: string | null;
    created_at: string | null;
    updated_at: string | null;
}
/** Request payload for `POST /api/v1/daily-patients`. `service_date` must remain unique. */
export interface CreateDailyPatientRequest {
    service_date: string;
    total_patients: number;
    notes?: string;
}
/** List response for `GET /api/v1/daily-patients`. */
export type DailyPatientsListResponse = ApiListResponse<DailyPatient>;
/** Detail response for `GET /api/v1/daily-patients/{service_date}`. */
export type DailyPatientResponse = ApiDataResponse<DailyPatient>;
/** Create response for `POST /api/v1/daily-patients`. */
export type DailyPatientCreateResponse = ApiMessageDataResponse<DailyPatient>;
