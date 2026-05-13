/** Supported operational app role names from the `roles` table. */
export type RoleName = "admin" | "dapur" | "gudang";

/** Role row returned by `GET /api/v1/roles`. */
export interface Role {
  id: number;
  name: RoleName | string;
}
