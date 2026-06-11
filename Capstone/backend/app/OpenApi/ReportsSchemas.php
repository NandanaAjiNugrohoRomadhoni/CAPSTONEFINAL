<?php

namespace App\OpenApi;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="MonthlyStockExportDayEntry",
 *     type="object",
 *     required={"tanggal","masuk","keluar","sisa"},
 *     @OA\Property(property="tanggal", type="integer", example=12),
 *     @OA\Property(property="masuk", type="number", format="float", example=40),
 *     @OA\Property(property="keluar", type="number", format="float", example=5),
 *     @OA\Property(property="sisa", type="number", format="float", nullable=true, example=285)
 * )
 * @OA\Schema(
 *     schema="MonthlyStockExportRow",
 *     type="object",
 *     required={"no","item_id","nama_bahan_makanan","category_id","category_name","satuan","stok_awal","harian"},
 *     @OA\Property(property="no", type="integer", example=1),
 *     @OA\Property(property="item_id", type="integer", example=9),
 *     @OA\Property(property="nama_bahan_makanan", type="string", example="Plastik Vakum"),
 *     @OA\Property(property="category_id", type="integer", example=3),
 *     @OA\Property(property="category_name", type="string", example="PENGEMAS"),
 *     @OA\Property(property="satuan", type="string", example="gram"),
 *     @OA\Property(property="stok_awal", type="number", format="float", nullable=true, example=250),
 *     @OA\Property(
 *         property="harian",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/MonthlyStockExportDayEntry")
 *     )
 * )
 * @OA\Schema(
 *     schema="MonthlyStockExportSummary",
 *     type="object",
 *     required={"total_items","total_days"},
 *     @OA\Property(property="total_items", type="integer", example=3),
 *     @OA\Property(property="total_days", type="integer", example=30)
 * )
 * @OA\Schema(
 *     schema="MonthlyStockExportData",
 *     type="object",
 *     required={"report_type","period","filters","summary","periode","rows"},
 *     @OA\Property(property="report_type", type="string", example="monthly-stock-export"),
 *     @OA\Property(
 *         property="period",
 *         type="object",
 *         required={"start","end"},
 *         @OA\Property(property="start", type="string", example="2026-04-01"),
 *         @OA\Property(property="end", type="string", example="2026-04-30")
 *     ),
 *     @OA\Property(
 *         property="filters",
 *         type="object",
 *         description="Applied query filters. May be empty or contain item/category filters only.",
 *         additionalProperties=true,
 *         example={"category_id": 3}
 *     ),
 *     @OA\Property(property="summary", ref="#/components/schemas/MonthlyStockExportSummary"),
 *     @OA\Property(property="periode", type="string", example="1-30"),
 *     @OA\Property(
 *         property="rows",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/MonthlyStockExportRow")
 *     )
 * )
 * @OA\Schema(
 *     schema="MonthlyStockExportResponse",
 *     type="object",
 *     required={"data"},
 *     @OA\Property(property="data", ref="#/components/schemas/MonthlyStockExportData")
 * )
 */
final class ReportsSchemas
{
}
