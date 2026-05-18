<?php

namespace App\OpenApi;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="MenuCoreCollectionMeta",
 *     type="object",
 *     required={"page","perPage","total","totalPages","paginated"},
 *     @OA\Property(property="page", type="integer", example=1),
 *     @OA\Property(property="perPage", type="integer", example=10),
 *     @OA\Property(property="total", type="integer", example=3),
 *     @OA\Property(property="totalPages", type="integer", example=1),
 *     @OA\Property(property="paginated", type="boolean", example=true)
 * )
 * @OA\Schema(
 *     schema="DishSummary",
 *     type="object",
 *     required={"id","name"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Bubur Ayam")
 * )
 * @OA\Schema(
 *     schema="Dish",
 *     type="object",
 *     required={"id","name","created_at","updated_at"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Bubur Ayam"),
 *     @OA\Property(property="created_at", type="string", example="2026-05-07 10:00:00"),
 *     @OA\Property(property="updated_at", type="string", example="2026-05-07 10:00:00")
 * )
 * @OA\Schema(
 *     schema="DishResource",
 *     type="object",
 *     required={"data"},
 *     @OA\Property(property="data", ref="#/components/schemas/Dish")
 * )
 * @OA\Schema(
 *     schema="DishMutationResponse",
 *     type="object",
 *     required={"message","data"},
 *     @OA\Property(property="message", type="string", example="Dish created successfully."),
 *     @OA\Property(property="data", ref="#/components/schemas/Dish")
 * )
 * @OA\Schema(
 *     schema="DishCollectionResponse",
 *     type="object",
 *     required={"data","meta","links"},
 *     @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Dish")),
 *     @OA\Property(property="meta", ref="#/components/schemas/MenuCoreCollectionMeta"),
 *     @OA\Property(property="links", ref="#/components/schemas/PaginationLinks")
 * )
 * @OA\Schema(
 *     schema="DishCompositionItemSummary",
 *     type="object",
 *     required={"id","name","unit_base","is_active"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", nullable=true, example="Beras"),
 *     @OA\Property(property="unit_base", type="string", nullable=true, example="gram"),
 *     @OA\Property(property="is_active", type="boolean", nullable=true, example=true)
 * )
 * @OA\Schema(
 *     schema="DishComposition",
 *     type="object",
 *     required={"id","dish_id","item_id","qty_per_patient","created_at","updated_at","dish","item"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="dish_id", type="integer", example=1),
 *     @OA\Property(property="item_id", type="integer", example=1),
 *     @OA\Property(property="qty_per_patient", type="string", example="125.50", description="Formatted decimal string returned by number_format(..., 2, '.', '')."),
 *     @OA\Property(property="created_at", type="string", example="2026-05-07 10:00:00"),
 *     @OA\Property(property="updated_at", type="string", example="2026-05-07 10:00:00"),
 *     @OA\Property(property="dish", ref="#/components/schemas/DishSummary"),
 *     @OA\Property(property="item", ref="#/components/schemas/DishCompositionItemSummary")
 * )
 * @OA\Schema(
 *     schema="DishCompositionResource",
 *     type="object",
 *     required={"data"},
 *     @OA\Property(property="data", ref="#/components/schemas/DishComposition")
 * )
 * @OA\Schema(
 *     schema="DishCompositionMutationResponse",
 *     type="object",
 *     required={"message","data"},
 *     @OA\Property(property="message", type="string", example="Dish composition updated successfully."),
 *     @OA\Property(property="data", ref="#/components/schemas/DishComposition")
 * )
 * @OA\Schema(
 *     schema="DishCompositionCollectionResponse",
 *     type="object",
 *     required={"data","meta","links"},
 *     @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/DishComposition")),
 *     @OA\Property(property="meta", ref="#/components/schemas/MenuCoreCollectionMeta"),
 *     @OA\Property(property="links", ref="#/components/schemas/PaginationLinks")
 * )
 * @OA\Schema(
 *     schema="MenuSummary",
 *     type="object",
 *     required={"id","name"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Paket 1")
 * )
 * @OA\Schema(
 *     schema="MealTimeSummary",
 *     type="object",
 *     required={"id","name"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Pagi")
 * )
 * @OA\Schema(
 *     schema="MenuCollectionResponse",
 *     type="object",
 *     required={"data","meta","links"},
 *     @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/MenuSummary")),
 *     @OA\Property(property="meta", ref="#/components/schemas/MenuCoreCollectionMeta"),
 *     @OA\Property(property="links", ref="#/components/schemas/PaginationLinks")
 * )
 * @OA\Schema(
 *     schema="MenuSlot",
 *     type="object",
 *     required={"id","menu_id","meal_time_id","dish_id","created_at","updated_at","menu","meal_time","dish"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="menu_id", type="integer", example=1),
 *     @OA\Property(property="meal_time_id", type="integer", example=1),
 *     @OA\Property(property="dish_id", type="integer", example=2),
 *     @OA\Property(property="created_at", type="string", example="2026-05-07 10:00:00"),
 *     @OA\Property(property="updated_at", type="string", example="2026-05-07 10:05:00"),
 *     @OA\Property(property="menu", ref="#/components/schemas/MenuSummary"),
 *     @OA\Property(property="meal_time", ref="#/components/schemas/MealTimeSummary"),
 *     @OA\Property(property="dish", ref="#/components/schemas/DishSummary")
 * )
 * @OA\Schema(
 *     schema="MenuDishCollectionResponse",
 *     type="object",
 *     required={"data","meta","links"},
 *     @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/MenuSlot")),
 *     @OA\Property(property="meta", ref="#/components/schemas/MenuCoreCollectionMeta"),
 *     @OA\Property(property="links", ref="#/components/schemas/PaginationLinks")
 * )
 * @OA\Schema(
 *     schema="MenuDishMutationResponse",
 *     type="object",
 *     required={"message","data"},
 *     @OA\Property(property="message", type="string", example="Menu slot assigned successfully."),
 *     @OA\Property(property="data", ref="#/components/schemas/MenuSlot")
 * )
 */
final class MenuCoreSchemas
{
}
