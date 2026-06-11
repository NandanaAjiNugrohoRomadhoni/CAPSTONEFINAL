<?php

namespace App\OpenApi;

use OpenApi\Annotations as OA;

/**
 * @OA\OpenApi(
 *     @OA\Info(
 *         title="Capstone API",
 *         version="1.0.0",
 *         description="Shared OpenAPI metadata and reusable components for the implemented /api/v1 auth, lookup, menu-core, daily-patient, inventory, dashboard, SPK, and user-management slices."
 *     ),
 *     @OA\Server(
 *         url="/",
 *         description="Current application origin"
 *     ),
 *     @OA\Tag(
 *         name="Auth",
 *         description="Authentication endpoints for login, session inspection, and password changes."
 *     ),
 *     @OA\Tag(
 *         name="Items",
 *         description="Item master endpoints with collection envelopes and item resource payloads."
 *     ),
 *     @OA\Tag(
 *         name="Item Categories",
 *         description="Inventory lookup endpoints for item category administration and dropdown reads."
 *     ),
 *     @OA\Tag(
 *         name="Transaction Types",
 *         description="Inventory lookup endpoints for transaction type reads used by stock transaction clients."
 *     ),
 *     @OA\Tag(
 *         name="Approval Statuses",
 *         description="Inventory lookup endpoints for approval status reads used by workflow-aware clients."
 *     ),
 *     @OA\Tag(
 *         name="Meal Times",
 *         description="Meal planning lookup endpoints for meal time dropdown reads."
 *     ),
 *     @OA\Tag(
 *         name="Item Units",
 *         description="Inventory lookup endpoints for item unit administration and dropdown reads."
 *     ),
 *     @OA\Tag(
 *         name="Roles",
 *         description="Read-only role lookup endpoints used by admin user-management flows."
 *     ),
 *     @OA\Tag(
 *         name="Users",
 *         description="Admin-only user management endpoints for listing, mutation, activation, deletion, and restore flows."
 *     ),
 *     @OA\Tag(
 *         name="Dishes",
 *         description="Menu-core dish master endpoints for listing, detail reads, and admin/dapur mutations."
 *     ),
 *     @OA\Tag(
 *         name="Dish Compositions",
 *         description="Menu-core composition endpoints that connect dishes to item usage per patient."
 *     ),
 *     @OA\Tag(
 *         name="Menus",
 *         description="Read-only menu package headers plus mutable menu-slot assignment endpoints."
 *     ),
 *     @OA\Tag(
 *         name="Dashboard",
 *         description="Role-conditioned operational aggregate endpoint for admin, dapur, and gudang users."
 *     ),
 *     @OA\Tag(
 *         name="Reports",
 *         description="Export-ready reporting endpoints for stock, transaction, SPK, evaluation, and monthly stock movement datasets."
 *     ),
 *     @OA\Tag(
 *         name="Daily Patients",
 *         description="Daily patient input endpoints for listing, creating, and resolving service-date-specific operational totals."
 *     ),
 *     @OA\Tag(
 *         name="Stock Transactions",
 *         description="Inventory workflow endpoints for transaction listing, creation, revision submission, direct corrections, and admin review actions."
 *     ),
 *     @OA\Tag(
 *         name="SPK Basah",
 *         description="Fresh-stock planning workflow endpoints for menu projection, operational preview, generation, history review, override, and post-stock finalization."
 *     ),
 *     @OA\Components(
 *         @OA\SecurityScheme(
 *             securityScheme="bearerAuth",
 *             type="http",
 *             scheme="bearer",
 *             bearerFormat="JWT",
 *             description="Bearer access token returned by the login endpoint."
 *         ),
 *         @OA\Response(
 *             response="MessageOnlyResponse",
 *             description="Simple message-only response envelope.",
 *             @OA\JsonContent(ref="#/components/schemas/MessageResponse")
 *         ),
 *         @OA\Response(
 *             response="ValidationErrorResponse",
 *             description="Validation failed response envelope.",
 *             @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")
 *         ),
 *         @OA\Response(
 *             response="UnauthorizedMessageResponse",
 *             description="Authentication is required or the supplied credentials are invalid.",
 *             @OA\JsonContent(ref="#/components/schemas/MessageResponse")
 *         )
 *     )
 * )
 */
final class OpenApiSpec
{
}
