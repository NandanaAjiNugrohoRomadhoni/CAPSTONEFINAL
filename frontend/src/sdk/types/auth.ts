import type { User } from "./users";

/** Request payload for `POST /api/v1/auth/login` (api-contract.md §5.1). */
export interface LoginRequest {
  username: string;
  password: string;
}

/** Response payload for `POST /api/v1/auth/login` (api-contract.md §5.1). */
export interface LoginResponse {
  message: string;
  access_token: string;
  token_type: "Bearer";
  user: User;
}

/** Request payload for self-service `PATCH /api/v1/auth/password` (api-contract.md §5.1.1). */
export interface SelfServiceChangePasswordRequest {
  current_password: string;
  password: string;
}
