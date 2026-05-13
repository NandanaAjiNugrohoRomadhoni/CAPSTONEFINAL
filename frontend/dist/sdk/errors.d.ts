import type { ApiErrorResponse, ApiValidationErrorResponse } from "./types";
export declare class ApiError<TBody = unknown> extends Error {
    readonly status: number;
    readonly body: TBody | null;
    constructor(message: string, status: number, body: TBody | null);
}
export declare class ValidationApiError extends ApiError<ApiValidationErrorResponse> {
    constructor(body: ApiValidationErrorResponse, status?: number);
    get errors(): Record<string, string>;
}
export declare class AuthenticationApiError extends ApiError<ApiErrorResponse> {
    constructor(body: ApiErrorResponse | null, status?: number);
}
export declare class AuthorizationApiError extends ApiError<ApiErrorResponse> {
    constructor(body: ApiErrorResponse | null, status?: number);
}
export declare class NotFoundApiError extends ApiError<ApiErrorResponse> {
    constructor(body: ApiErrorResponse | null, status?: number);
}
export declare function toApiError(status: number, body: unknown): ApiError;
