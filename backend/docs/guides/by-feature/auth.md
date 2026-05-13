# Authentication Feature Guide

The Authentication feature manages user sessions, identity verification, and token-based access.

## Endpoints

- `POST /api/v1/auth/login`: Authenticate and receive a token.
- `POST /api/v1/auth/logout`: Invalidate current session token.
- `GET /api/v1/auth/me`: Retrieve profile information for the currently authenticated user.
- `PATCH /api/v1/auth/password`: Change current user password.

## Business Rules

- All API endpoints (except login) require a valid token via the `Authorization: Bearer <token>` header.
- Tokens are issued upon successful login.
- Password change requires providing the current password and a new, confirmed password.

## Related Documentation
- [User Roles Guide](./users-roles.md)
- [API Reference (Canonical)](../../reference/api-contract.md#auth)
