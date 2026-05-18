<?php

namespace App\OpenApi;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="UserRole",
 *     type="object",
 *     required={"id","name"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="gudang")
 * )
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     required={"id","role_id","name","username","is_active","created_at","updated_at","role"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="role_id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Example User"),
 *     @OA\Property(property="username", type="string", example="example-user"),
 *     @OA\Property(property="email", type="string", format="email", nullable=true, example="example.user@example.test"),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="created_at", type="string", example="2026-05-07 10:00:00"),
 *     @OA\Property(property="updated_at", type="string", example="2026-05-07 10:00:00"),
 *     @OA\Property(property="role", ref="#/components/schemas/UserRole")
 * )
 * @OA\Schema(
 *     schema="UserResource",
 *     type="object",
 *     required={"data"},
 *     @OA\Property(property="data", ref="#/components/schemas/User")
 * )
 * @OA\Schema(
 *     schema="LoginResponse",
 *     type="object",
 *     required={"message","access_token","token_type","user"},
 *     @OA\Property(property="message", type="string", example="Login successful."),
 *     @OA\Property(property="access_token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."),
 *     @OA\Property(property="token_type", type="string", example="Bearer"),
 *     @OA\Property(property="user", ref="#/components/schemas/User")
 * )
 * @OA\Schema(
 *     schema="ItemCategory",
 *     allOf={@OA\Schema(ref="#/components/schemas/LookupResource")}
 * )
 * @OA\Schema(
 *     schema="ItemUnit",
 *     allOf={@OA\Schema(ref="#/components/schemas/LookupResource")}
 * )
 * @OA\Schema(
 *     schema="TransactionType",
 *     allOf={@OA\Schema(ref="#/components/schemas/LookupResource")}
 * )
 * @OA\Schema(
 *     schema="ApprovalStatus",
 *     allOf={@OA\Schema(ref="#/components/schemas/LookupResource")}
 * )
 * @OA\Schema(
 *     schema="MealTime",
 *     allOf={@OA\Schema(ref="#/components/schemas/LookupResource")}
 * )
 * @OA\Schema(
 *     schema="RoleLookup",
 *     allOf={@OA\Schema(ref="#/components/schemas/LookupResource")}
 * )
 * @OA\Schema(
 *     schema="ItemCategoryResource",
 *     type="object",
 *     required={"data"},
 *     @OA\Property(property="data", ref="#/components/schemas/ItemCategory")
 * )
 * @OA\Schema(
 *     schema="ItemUnitResource",
 *     type="object",
 *     required={"data"},
 *     @OA\Property(property="data", ref="#/components/schemas/ItemUnit")
 * )
 * @OA\Schema(
 *     schema="TransactionTypeResource",
 *     type="object",
 *     required={"data"},
 *     @OA\Property(property="data", ref="#/components/schemas/TransactionType")
 * )
 * @OA\Schema(
 *     schema="ApprovalStatusResource",
 *     type="object",
 *     required={"data"},
 *     @OA\Property(property="data", ref="#/components/schemas/ApprovalStatus")
 * )
 * @OA\Schema(
 *     schema="MealTimeResource",
 *     type="object",
 *     required={"data"},
 *     @OA\Property(property="data", ref="#/components/schemas/MealTime")
 * )
 * @OA\Schema(
 *     schema="ItemCategoryMutationResponse",
 *     type="object",
 *     required={"message","data"},
 *     @OA\Property(property="message", type="string", example="Item category created successfully."),
 *     @OA\Property(property="data", ref="#/components/schemas/ItemCategory")
 * )
 * @OA\Schema(
 *     schema="ItemUnitMutationResponse",
 *     type="object",
 *     required={"message","data"},
 *     @OA\Property(property="message", type="string", example="Item unit created successfully."),
 *     @OA\Property(property="data", ref="#/components/schemas/ItemUnit")
 * )
 * @OA\Schema(
 *     schema="UserMutationResponse",
 *     type="object",
 *     required={"message","data"},
 *     @OA\Property(property="message", type="string", example="User updated successfully."),
 *     @OA\Property(property="data", ref="#/components/schemas/User")
 * )
 * @OA\Schema(
 *     schema="ItemCategorySummary",
 *     type="object",
 *     required={"id","name"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", nullable=true, example="BASAH")
 * )
 * @OA\Schema(
 *     schema="ItemUnitSummary",
 *     type="object",
 *     required={"id","name"},
 *     @OA\Property(property="id", type="integer", nullable=true, example=1),
 *     @OA\Property(property="name", type="string", nullable=true, example="kg")
 * )
 * @OA\Schema(
 *     schema="Item",
 *     type="object",
 *     required={"id","item_category_id","name","unit_base","unit_convert","conversion_base","min_stock","qty","is_active","created_at","updated_at","category","item_unit_base","item_unit_convert"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="item_category_id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Beras"),
 *     @OA\Property(property="unit_base", type="string", example="gram"),
 *     @OA\Property(property="unit_convert", type="string", example="kg"),
 *     @OA\Property(property="item_unit_base_id", type="integer", nullable=true, example=1),
 *     @OA\Property(property="item_unit_convert_id", type="integer", nullable=true, example=2),
 *     @OA\Property(property="conversion_base", type="integer", example=1000),
 *     @OA\Property(property="min_stock", type="integer", example=10),
 *     @OA\Property(property="qty", type="string", example="12.00", description="Formatted quantity string returned by number_format(..., 2, '.', '')."),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="created_at", type="string", example="2026-05-07 10:00:00"),
 *     @OA\Property(property="updated_at", type="string", example="2026-05-07 10:00:00"),
 *     @OA\Property(property="category", ref="#/components/schemas/ItemCategorySummary"),
 *     @OA\Property(property="item_unit_base", ref="#/components/schemas/ItemUnitSummary"),
 *     @OA\Property(property="item_unit_convert", ref="#/components/schemas/ItemUnitSummary")
 * )
 * @OA\Schema(
 *     schema="ItemResource",
 *     type="object",
 *     required={"data"},
 *     @OA\Property(property="data", ref="#/components/schemas/Item")
 * )
 * @OA\Schema(
 *     schema="ItemMutationResponse",
 *     type="object",
 *     required={"message","data"},
 *     @OA\Property(property="message", type="string", example="Item created successfully."),
 *     @OA\Property(property="data", ref="#/components/schemas/Item")
 * )
 * @OA\Schema(
 *     schema="ItemCollectionResponse",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/CollectionEnvelope"),
 *         @OA\Schema(@OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Item")))
 *     }
 * )
 * @OA\Schema(
 *     schema="RoleCollectionResponse",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/LookupCollectionEnvelope"),
 *         @OA\Schema(@OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/RoleLookup")))
 *     }
 * )
 * @OA\Schema(
 *     schema="ItemCategoryCollectionResponse",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/LookupCollectionEnvelope"),
 *         @OA\Schema(@OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/ItemCategory")))
 *     }
 * )
 * @OA\Schema(
 *     schema="ItemUnitCollectionResponse",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/LookupCollectionEnvelope"),
 *         @OA\Schema(@OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/ItemUnit")))
 *     }
 * )
 * @OA\Schema(
 *     schema="TransactionTypeCollectionResponse",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/LookupCollectionEnvelope"),
 *         @OA\Schema(@OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/TransactionType")))
 *     }
 * )
 * @OA\Schema(
 *     schema="ApprovalStatusCollectionResponse",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/LookupCollectionEnvelope"),
 *         @OA\Schema(@OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/ApprovalStatus")))
 *     }
 * )
 * @OA\Schema(
 *     schema="MealTimeCollectionResponse",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/LookupCollectionEnvelope"),
 *         @OA\Schema(@OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/MealTime")))
 *     }
 * )
 * @OA\Schema(
 *     schema="UserCollectionResponse",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/CollectionEnvelope"),
 *         @OA\Schema(@OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/User")))
 *     }
 * )
 */
final class ResourceSchemas
{
}
