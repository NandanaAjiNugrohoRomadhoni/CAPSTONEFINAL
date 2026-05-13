<?php

namespace App\OpenApi;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="StockTransaction",
 *     type="object",
 *     required={"id","type_id","transaction_date","is_revision","approval_status_id","user_id","created_at","updated_at"},
 *     @OA\Property(property="id", type="integer", example=10),
 *     @OA\Property(property="type_id", type="integer", example=1),
 *     @OA\Property(property="transaction_date", type="string", example="2026-04-18"),
 *     @OA\Property(property="is_revision", type="boolean", example=false),
 *     @OA\Property(property="parent_transaction_id", type="integer", nullable=true, example=null),
 *     @OA\Property(property="approval_status_id", type="integer", example=1),
 *     @OA\Property(property="approved_by", type="integer", nullable=true, example=null),
 *     @OA\Property(property="user_id", type="integer", example=2),
 *     @OA\Property(property="spk_id", type="integer", nullable=true, example=31),
 *     @OA\Property(property="reason", type="string", nullable=true, example="Manual stock correction after recount."),
 *     @OA\Property(property="rejection_reason", type="string", nullable=true, example="Revision quantities did not match the audit evidence."),
 *     @OA\Property(property="created_at", type="string", example="2026-04-18 08:00:00"),
 *     @OA\Property(property="updated_at", type="string", example="2026-04-18 08:00:00"),
 *     @OA\Property(property="user_name", type="string", nullable=true, example="Gudang User"),
 *     @OA\Property(property="approved_by_name", type="string", nullable=true, example="Admin User")
 * )
 * @OA\Schema(
 *     schema="StockTransactionResource",
 *     type="object",
 *     required={"data"},
 *     @OA\Property(property="data", ref="#/components/schemas/StockTransaction")
 * )
 * @OA\Schema(
 *     schema="StockTransactionCollectionResponse",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/CollectionEnvelope"),
 *         @OA\Schema(@OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/StockTransaction")))
 *     }
 * )
 * @OA\Schema(
 *     schema="StockTransactionCreateResult",
 *     type="object",
 *     required={"id","approval_status_id","is_revision"},
 *     @OA\Property(property="id", type="integer", example=15),
 *     @OA\Property(property="approval_status_id", type="integer", example=1),
 *     @OA\Property(property="is_revision", type="boolean", example=false)
 * )
 * @OA\Schema(
 *     schema="StockTransactionCreateResponse",
 *     type="object",
 *     required={"message","data"},
 *     @OA\Property(property="message", type="string", example="Stock transaction created successfully."),
 *     @OA\Property(property="data", ref="#/components/schemas/StockTransactionCreateResult")
 * )
 * @OA\Schema(
 *     schema="StockTransactionCreateRequest",
 *     type="object",
 *     description="Client must send exactly one of type_id or type_name. Details require unique item_id rows. input_unit defaults to base and may be base or convert.",
 *     required={"transaction_date","details"},
 *     @OA\Property(property="type_id", type="integer", minimum=1, example=1),
 *     @OA\Property(property="type_name", type="string", example="RETURN_IN"),
 *     @OA\Property(property="transaction_date", type="string", example="2026-04-02"),
 *     @OA\Property(property="spk_id", type="integer", nullable=true, minimum=1, example=31),
 *     @OA\Property(property="details", type="array", minItems=1, @OA\Items(ref="#/components/schemas/StockTransactionWriteDetail"))
 * )
 * @OA\Schema(
 *     schema="StockTransactionWriteDetail",
 *     type="object",
 *     required={"item_id","qty"},
 *     @OA\Property(property="item_id", type="integer", minimum=1, example=1),
 *     @OA\Property(property="qty", type="number", format="float", minimum=0.01, example=5),
 *     @OA\Property(property="input_unit", type="string", enum={"base","convert"}, example="convert")
 * )
 * @OA\Schema(
 *     schema="DirectStockCorrectionRequest",
 *     type="object",
 *     required={"transaction_date","item_id","expected_current_qty","target_qty","reason"},
 *     @OA\Property(property="transaction_date", type="string", example="2026-04-15"),
 *     @OA\Property(property="item_id", type="integer", minimum=1, example=1),
 *     @OA\Property(property="expected_current_qty", type="number", format="float", minimum=0, example=5000),
 *     @OA\Property(property="target_qty", type="number", format="float", minimum=0, example=4800),
 *     @OA\Property(property="reason", type="string", maxLength=255, example="Manual stock correction after recount.")
 * )
 * @OA\Schema(
 *     schema="DirectStockCorrectionResponse",
 *     type="object",
 *     required={"message","data"},
 *     @OA\Property(property="message", type="string", example="Direct stock correction created successfully."),
 *     @OA\Property(property="data", ref="#/components/schemas/StockTransactionCreateResult")
 * )
 * @OA\Schema(
 *     schema="StockTransactionRevisionSubmitResult",
 *     type="object",
 *     required={"id","approval_status_id","is_revision","parent_transaction_id"},
 *     @OA\Property(property="id", type="integer", example=21),
 *     @OA\Property(property="approval_status_id", type="integer", example=2),
 *     @OA\Property(property="is_revision", type="boolean", example=true),
 *     @OA\Property(property="parent_transaction_id", type="integer", example=15)
 * )
 * @OA\Schema(
 *     schema="StockTransactionRevisionSubmitResponse",
 *     type="object",
 *     required={"message","data"},
 *     @OA\Property(property="message", type="string", example="Revision submitted successfully."),
 *     @OA\Property(property="data", ref="#/components/schemas/StockTransactionRevisionSubmitResult")
 * )
 * @OA\Schema(
 *     schema="StockTransactionRevisionRequest",
 *     type="object",
 *     description="Writable fields are limited to transaction_date, spk_id, and details.",
 *     required={"transaction_date","details"},
 *     @OA\Property(property="transaction_date", type="string", example="2026-05-08"),
 *     @OA\Property(property="spk_id", type="integer", nullable=true, minimum=1, example=31),
 *     @OA\Property(property="details", type="array", minItems=1, @OA\Items(ref="#/components/schemas/StockTransactionWriteDetail"))
 * )
 * @OA\Schema(
 *     schema="StockTransactionRevisionDecisionResult",
 *     type="object",
 *     required={"id","approval_status_id","approved_by"},
 *     @OA\Property(property="id", type="integer", example=21),
 *     @OA\Property(property="approval_status_id", type="integer", example=1),
 *     @OA\Property(property="approved_by", type="integer", example=1)
 * )
 * @OA\Schema(
 *     schema="StockTransactionRevisionDecisionResponse",
 *     type="object",
 *     required={"message","data"},
 *     @OA\Property(property="message", type="string", example="Revision approved successfully."),
 *     @OA\Property(property="data", ref="#/components/schemas/StockTransactionRevisionDecisionResult")
 * )
 * @OA\Schema(
 *     schema="StockTransactionRevisionRejectRequest",
 *     type="object",
 *     @OA\Property(property="reason", type="string", nullable=true, example="Revision quantities did not match the audit evidence.")
 * )
 * @OA\Schema(
 *     schema="StockTransactionRevisionRejectResponse",
 *     type="object",
 *     required={"message","data"},
 *     @OA\Property(property="message", type="string", example="Revision rejected successfully."),
 *     @OA\Property(property="data", ref="#/components/schemas/StockTransactionRevisionDecisionResult")
 * )
 * @OA\Schema(
 *     schema="StockTransactionDetail",
 *     type="object",
 *     required={"id","transaction_id","item_id","item_name","item_category_id","item_category_name","satuan","qty","input_qty","input_unit"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="transaction_id", type="integer", example=10),
 *     @OA\Property(property="item_id", type="integer", example=1),
 *     @OA\Property(property="item_name", type="string", nullable=true, example="Beras"),
 *     @OA\Property(property="item_category_id", type="integer", nullable=true, example=2),
 *     @OA\Property(property="item_category_name", type="string", nullable=true, example="KERING"),
 *     @OA\Property(property="satuan", type="string", nullable=true, example="gram"),
 *     @OA\Property(property="qty", type="string", example="3000.00", description="Normalized base-unit quantity stored in stock_transaction_details.qty."),
 *     @OA\Property(property="input_qty", type="string", example="3.00", description="Original request quantity before base-unit normalization."),
 *     @OA\Property(property="input_unit", type="string", enum={"base","convert"}, example="convert")
 * )
 * @OA\Schema(
 *     schema="StockTransactionDetailsResponse",
 *     type="object",
 *     required={"data"},
 *     @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/StockTransactionDetail"))
 * )
 */
final class StockTransactionSchemas
{
}
