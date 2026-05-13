import type { ApiErrorResponse, ApiValidationErrorResponse } from "./types";

export class ApiError<TBody = unknown> extends Error {
  public readonly status: number;
  public readonly body: TBody | null;

  public constructor(message: string, status: number, body: TBody | null) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.body = body;
  }
}

export class ValidationApiError extends ApiError<ApiValidationErrorResponse> {
  public constructor(body: ApiValidationErrorResponse, status = 400) {
    super(body.message, status, body);
    this.name = "ValidationApiError";
  }

  public get errors(): Record<string, string> {
    return this.body?.errors ?? {};
  }
}

export class AuthenticationApiError extends ApiError<ApiErrorResponse> {
  public constructor(body: ApiErrorResponse | null, status = 401) {
    super(body?.message ?? "Authentication failed.", status, body);
    this.name = "AuthenticationApiError";
  }
}

export class AuthorizationApiError extends ApiError<ApiErrorResponse> {
  public constructor(body: ApiErrorResponse | null, status = 403) {
    super(body?.message ?? "Authorization failed.", status, body);
    this.name = "AuthorizationApiError";
  }
}

export class NotFoundApiError extends ApiError<ApiErrorResponse> {
  public constructor(body: ApiErrorResponse | null, status = 404) {
    super(body?.message ?? "Resource not found.", status, body);
    this.name = "NotFoundApiError";
  }
}

export function toApiError(status: number, body: unknown): ApiError {
  const normalized = isApiErrorResponse(body) ? body : null;

  if (status === 400 && isValidationErrorResponse(body)) {
    return new ValidationApiError(body, status);
  }

  if (status === 401) {
    return new AuthenticationApiError(normalized, status);
  }

  if (status === 403) {
    return new AuthorizationApiError(normalized, status);
  }

  if (status === 404) {
    return new NotFoundApiError(normalized, status);
  }

  return new ApiError(normalized?.message ?? `Request failed with status ${status}.`, status, normalized);
}

function isApiErrorResponse(value: unknown): value is ApiErrorResponse {
  return typeof value === "object" && value !== null && "message" in value && typeof value.message === "string";
}

function isValidationErrorResponse(value: unknown): value is ApiValidationErrorResponse {
  return (
    typeof value === "object" &&
    value !== null &&
    "message" in value &&
    typeof value.message === "string" &&
    "errors" in value &&
    typeof value.errors === "object" &&
    value.errors !== null &&
    !Array.isArray(value.errors)
  );
}
