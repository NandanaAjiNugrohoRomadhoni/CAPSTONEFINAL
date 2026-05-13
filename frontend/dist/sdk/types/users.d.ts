import type { XOR } from "./common";
import type { Role } from "./roles";
/** User response model used by implemented `/api/v1/users*` and auth endpoints. */
export interface User {
    id: number;
    role_id: number;
    name: string;
    username: string;
    email: string | null;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    role?: Role;
}
/** Type-level XOR for role lookup: send `role_id` OR `role_name`, not both. */
type UserRoleIdentifier = XOR<{
    role_id: number;
}, {
    role_name: string;
}>;
type OptionalUserRoleIdentifier = UserRoleIdentifier | {
    role_id?: undefined;
    role_name?: undefined;
};
/** Request payload for `POST /api/v1/users`. */
export type CreateUserRequest = UserRoleIdentifier & {
    name: string;
    username: string;
    password: string;
    email?: string;
    is_active?: boolean;
};
/** Request payload for `PUT /api/v1/users/{id}` with partial-update semantics. */
export type UpdateUserRequest = OptionalUserRoleIdentifier & {
    name?: string;
    username?: string;
    email?: string;
    is_active?: boolean;
};
/** Query params for `GET /api/v1/users`. */
export interface ListUsersQuery {
    page?: number;
    perPage?: number;
    q?: string;
    search?: string;
    sortBy?: "id" | "name" | "username" | "email" | "created_at" | "updated_at";
    sortDir?: "ASC" | "DESC";
    role_id?: number;
    is_active?: boolean;
    created_at_from?: string;
    created_at_to?: string;
    updated_at_from?: string;
    updated_at_to?: string;
}
/** Request payload for admin-only `PATCH /api/v1/users/{id}/password`. */
export interface ChangePasswordRequest {
    password: string;
}
export {};
