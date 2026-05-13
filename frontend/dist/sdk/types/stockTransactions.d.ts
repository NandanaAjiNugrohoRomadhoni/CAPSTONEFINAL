import type { XOR } from "./common";
/** Implemented transaction type names accepted by stock transaction create endpoints. */
export type TransactionTypeName = "IN" | "OUT" | "RETURN_IN";
/** `base` stores qty as-is; `convert` stores qty × `items.conversion_base`. */
export type StockTransactionInputUnit = "base" | "convert";
/** Stock transaction header returned by `/api/v1/stock-transactions*` endpoints. */
export interface StockTransaction {
    id: number;
    type_id: number;
    transaction_date: string;
    /** Backend-managed. `true` only for revision children. */
    is_revision: boolean;
    /** Backend-managed. Null for normal transactions. */
    parent_transaction_id: number | null;
    /** Backend-managed approval status identifier. */
    approval_status_id: number;
    approved_by: number | null;
    /** Backend derives this from the Bearer token for create requests. */
    user_id: number;
    spk_id: number | null;
    reason: string | null;
    created_at: string;
    updated_at: string;
}
/** Detail row returned by `GET /api/v1/stock-transactions/{id}/details`. */
export interface StockTransactionDetail {
    id: number;
    transaction_id: number;
    item_id: number;
    item_name: string | null;
    item_category_id: number | null;
    item_category_name: string | null;
    /** Base unit label for the normalized `qty`, sourced from `items.unit_base`. */
    satuan: string | null;
    /** Normalized base-unit quantity used for stock mutation. */
    qty: string;
    /** Original submitted quantity persisted by the backend. */
    input_qty: string;
    input_unit: StockTransactionInputUnit;
}
/** Request detail row for create and submit-revision endpoints. */
export interface StockTransactionDetailInput {
    item_id: number;
    qty: number;
    /** Optional. Defaults to `base` when omitted. */
    input_unit?: StockTransactionInputUnit;
}
/** Type-level XOR for transaction type lookup: send `type_id` OR `type_name`, not both. */
type TransactionTypeIdentifier = XOR<{
    type_id: number;
}, {
    type_name: TransactionTypeName | string;
}>;
/** Query params for `GET /api/v1/stock-transactions`. */
export interface ListStockTransactionsQuery {
    page?: number;
    perPage?: number;
    q?: string;
    search?: string;
    sortBy?: "id" | "transaction_date" | "type_id" | "approval_status_id" | "created_at" | "updated_at";
    sortDir?: "ASC" | "DESC";
    type_id?: number;
    status_id?: number;
    transaction_date_from?: string;
    transaction_date_to?: string;
    created_at_from?: string;
    created_at_to?: string;
    updated_at_from?: string;
    updated_at_to?: string;
}
/** Request payload for `POST /api/v1/stock-transactions`. `user_id` is derived from the Bearer token, not sent by the client. */
export type CreateStockTransactionRequest = TransactionTypeIdentifier & {
    transaction_date: string;
    spk_id?: number | null;
    details: StockTransactionDetailInput[];
};
/** Request payload for `POST /api/v1/stock-transactions/{id}/submit-revision`. */
export interface SubmitRevisionRequest {
    transaction_date: string;
    spk_id?: number | null;
    details: StockTransactionDetailInput[];
}
/** Request payload for admin-only `POST /api/v1/stock-transactions/direct-corrections`. */
export interface DirectStockCorrectionRequest {
    transaction_date: string;
    item_id: number;
    expected_current_qty: number;
    target_qty: number;
    reason: string;
}
/** Message data returned by create/direct-correction endpoints. */
export interface StockTransactionCreateResult {
    id: number;
    approval_status_id: number;
    is_revision: boolean;
}
/** Message data returned by submit-revision. */
export interface StockTransactionRevisionResult extends StockTransactionCreateResult {
    parent_transaction_id: number;
}
/** Message data returned by approve/reject endpoints. */
export interface StockTransactionModerationResult {
    id: number;
    approval_status_id: number;
    approved_by: number;
}
export {};
