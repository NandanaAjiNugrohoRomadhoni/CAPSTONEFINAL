<?php

namespace App\OpenApi;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="MessageResponse",
 *     type="object",
 *     required={"message"},
 *     @OA\Property(property="message", type="string", example="Logout successful.")
 * )
 * @OA\Schema(
 *     schema="ValidationError",
 *     type="object",
 *     additionalProperties=@OA\AdditionalProperties(type="string"),
 *     example={"password":"The password field is required."}
 * )
 * @OA\Schema(
 *     schema="ValidationErrorResponse",
 *     type="object",
 *     required={"message","errors"},
 *     @OA\Property(property="message", type="string", example="Validation failed."),
 *     @OA\Property(property="errors", ref="#/components/schemas/ValidationError")
 * )
 * @OA\Schema(
 *     schema="MessageWithOptionalErrorsResponse",
 *     type="object",
 *     required={"message"},
 *     @OA\Property(property="message", type="string", example="User not found."),
 *     @OA\Property(
 *         property="errors",
 *         oneOf={
 *             @OA\Schema(ref="#/components/schemas/ValidationError"),
 *             @OA\Schema(type="array", @OA\Items(type="string"), example={})
 *         }
 *     )
 * )
 * @OA\Schema(
 *     schema="CollectionMeta",
 *     type="object",
 *     required={"page","perPage","total","totalPages"},
 *     @OA\Property(property="page", type="integer", example=1),
 *     @OA\Property(property="perPage", type="integer", example=10),
 *     @OA\Property(property="total", type="integer", example=42),
 *     @OA\Property(property="totalPages", type="integer", example=5)
 * )
 * @OA\Schema(
 *     schema="LookupCollectionMeta",
 *     type="object",
 *     required={"page","perPage","total","totalPages","paginated"},
 *     @OA\Property(property="page", type="integer", example=1),
 *     @OA\Property(property="perPage", type="integer", example=10),
 *     @OA\Property(property="total", type="integer", example=3),
 *     @OA\Property(property="totalPages", type="integer", example=1),
 *     @OA\Property(property="paginated", type="boolean", example=true)
 * )
 * @OA\Schema(
 *     schema="PaginationLinks",
 *     type="object",
 *     required={"self","first","last","next","previous"},
 *     @OA\Property(property="self", type="string", format="uri-reference", example="/api/v1/items?page=1&perPage=10"),
 *     @OA\Property(property="first", type="string", format="uri-reference", example="/api/v1/items?page=1&perPage=10"),
 *     @OA\Property(property="last", type="string", format="uri-reference", example="/api/v1/items?page=5&perPage=10"),
 *     @OA\Property(property="next", type="string", format="uri-reference", nullable=true, example="/api/v1/items?page=2&perPage=10"),
 *     @OA\Property(property="previous", type="string", format="uri", nullable=true, example=null)
 * )
 * @OA\Schema(
 *     schema="CollectionEnvelope",
 *     type="object",
 *     required={"data","meta","links"},
 *     @OA\Property(property="data", type="array", @OA\Items(type="object")),
 *     @OA\Property(property="meta", ref="#/components/schemas/CollectionMeta"),
 *     @OA\Property(property="links", ref="#/components/schemas/PaginationLinks")
 * )
 * @OA\Schema(
 *     schema="LookupCollectionEnvelope",
 *     type="object",
 *     required={"data","meta","links"},
 *     @OA\Property(property="data", type="array", @OA\Items(type="object")),
 *     @OA\Property(property="meta", ref="#/components/schemas/LookupCollectionMeta"),
 *     @OA\Property(property="links", ref="#/components/schemas/PaginationLinks")
 * )
 * @OA\Schema(
 *     schema="LookupResource",
 *     type="object",
 *     required={"id","name","created_at","updated_at"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="BASAH"),
 *     @OA\Property(property="created_at", type="string", example="2026-05-07 10:00:00"),
 *     @OA\Property(property="updated_at", type="string", example="2026-05-07 10:00:00")
 * )
 */
final class CommonSchemas
{
}
