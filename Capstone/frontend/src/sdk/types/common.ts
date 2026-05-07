/** Standard pagination metadata for list responses across implemented collection endpoints. */
export interface PaginationMeta {
  page: number;
  perPage: number;
  total: number;
  totalPages: number;
  /** Present when the backend keeps the list envelope but disables paging via `paginate=false`. */
  paginated?: boolean;
}

/** Standard pagination links for list responses. */
export interface PaginationLinks {
  self: string;
  first: string;
  last: string;
  next: string | null;
  previous: string | null;
}

/** Single-resource response envelope used by show endpoints and some helper endpoints. */
export interface ApiDataResponse<T> {
  data: T;
}

/** List response envelope used by implemented paginated collection endpoints. */
export interface ApiListResponse<T> {
  data: T[];
  meta: PaginationMeta;
  links: PaginationLinks;
}

/** Message-only response envelope used by delete and action endpoints with no data body. */
export interface ApiMessageResponse {
  message: string;
}

/** Message-plus-data response envelope used by create, update, restore, and workflow action endpoints. */
export interface ApiMessageDataResponse<T> extends ApiMessageResponse {
  data: T;
}

/** Raw backend error details returned by non-success responses. */
export type ApiErrorDetails = Record<string, string> | string[];

/** Validation error response envelope (`400`). */
export interface ApiValidationErrorResponse extends ApiMessageResponse {
  errors: Record<string, string>;
}

/** Generic failure response envelope used by non-validation failures. */
export interface ApiFailureResponse extends ApiMessageResponse {
  errors?: ApiErrorDetails;
}

/** Union of backend error response shapes used by the client error layer. */
export type ApiErrorResponse = ApiFailureResponse | ApiValidationErrorResponse;

/** Supported serialized query value types for SDK request builders. */
export type QueryValue = string | number | boolean | null | undefined;

/** Query parameter map for SDK request builders. */
export type QueryParams = Record<string, QueryValue>;

type Without<T, K extends PropertyKey> = {
  [P in Exclude<keyof T, K>]?: never;
};

/**
 * Type-level XOR helper used by request payloads that accept exactly one of two lookup forms.
 *
 * Contract examples:
 * - users: `role_id` OR `role_name`
 * - items: `item_category_id` OR `item_category_name`
 * - stock-transactions: `type_id` OR `type_name`
 */
export type XOR<T, U> = (T & Without<U, keyof T>) | (U & Without<T, keyof U>);
