<?php

namespace App\OpenApi;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="AuditLogEntry",
 *     type="object",
 *     required={"id","date","time","actor","actorInfo","activityType","activityLabel","module","detail","description","target","changes","ipAddress","rawActionType","created_at"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="date", type="string", example="2026-05-08"),
 *     @OA\Property(property="time", type="string", example="14:30"),
 *     @OA\Property(property="actor", type="string", example="Admin User"),
 *     @OA\Property(
 *         property="actorInfo",
 *         type="object",
 *         @OA\Property(property="id", type="integer", nullable=true, example=7),
 *         @OA\Property(property="name", type="string", example="Admin User"),
 *         @OA\Property(property="username", type="string", nullable=true, example="admin"),
 *         @OA\Property(property="role", type="string", nullable=true, example="admin"),
 *     ),
 *     @OA\Property(property="activityType", type="string", example="update"),
 *     @OA\Property(property="activityLabel", type="string", example="Update"),
 *     @OA\Property(property="module", type="string", example="Stok"),
 *     @OA\Property(property="detail", type="string", example="Menerapkan penyesuaian stok"),
 *     @OA\Property(property="description", type="string", example="Menerapkan penyesuaian stok"),
 *     @OA\Property(
 *         property="target",
 *         type="object",
 *         @OA\Property(property="table", type="string", example="stock_opnames"),
 *         @OA\Property(property="recordId", type="integer", example=12)
 *     ),
 *     @OA\Property(
 *         property="changes",
 *         type="object",
 *         @OA\Property(property="before", type="object", nullable=true),
 *         @OA\Property(property="after", type="object", nullable=true),
 *         @OA\Property(property="diff", type="array", @OA\Items(type="object")),
 *         @OA\Property(
 *             property="itemDiff",
 *             type="array",
 *             nullable=true,
 *             description="Item-level diff for stock transaction revision records.",
 *             @OA\Items(
 *                 type="object",
 *                 @OA\Property(property="item_id", type="integer", example=5),
 *                 @OA\Property(property="label", type="string", example="Tepung Terigu"),
 *                 @OA\Property(property="qty_before", type="string", nullable=true, example="10.00"),
 *                 @OA\Property(property="qty_after", type="string", nullable=true, example="15.00"),
 *                 @OA\Property(property="unit_before", type="string", nullable=true, example="kg"),
 *                 @OA\Property(property="unit_after", type="string", nullable=true, example="kg"),
 *                 @OA\Property(property="status", type="string", enum={"added","removed","changed"})
 *             )
 *         )
 *     ),
 *     @OA\Property(property="ipAddress", type="string", nullable=true, example="127.0.0.1"),
 *     @OA\Property(property="rawActionType", type="string", example="stock_opname_approve"),
 *     @OA\Property(property="created_at", type="string", nullable=true, example="2026-05-08 14:30:00")
 * )
 * @OA\Schema(
 *     schema="AuditLogCollectionResponse",
 *     type="object",
 *     required={"data","meta","links"},
 *     @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/AuditLogEntry")),
 *     @OA\Property(property="meta", ref="#/components/schemas/LookupCollectionMeta"),
 *     @OA\Property(property="links", ref="#/components/schemas/PaginationLinks")
 * )
 * @OA\Schema(
 *     schema="AuditLogSummaryResponse",
 *     type="object",
 *     required={"data"},
 *     @OA\Property(
 *         property="data",
 *         type="object",
 *         @OA\Property(property="total", type="integer", example=150),
 *         @OA\Property(property="byRole", type="object", example={"admin":45,"dapur":60,"gudang":45}),
 *         @OA\Property(property="byActionType", type="object", example={"create":30,"update":50,"delete":10,"approval":20,"rejection":5}),
 *         @OA\Property(property="byModule", type="object", example={"Transaksi":40,"Menu":30,"Stok":35,"SPK":25,"Pengguna":10})
 *     )
 * )
 */
final class AuditLogSchemas
{
}
