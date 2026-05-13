# Users and Roles Feature Guide

Manages the system's human actors and their access levels.

## Endpoints

### User Management
- `GET /api/v1/users`: List users (paginated).
- `POST /api/v1/users`: Create a new user.
- `GET /api/v1/users/{id}`: Show user details.
- `PUT /api/v1/users/{id}`: Update user profile.
- `PATCH /api/v1/users/{id}/activate`: Activate an inactive account.
- `PATCH /api/v1/users/{id}/deactivate`: Deactivate an active account.
- `DELETE /api/v1/users/{id}`: Soft delete a user.
- `PATCH /api/v1/users/{id}/restore`: Restore a soft-deleted user.

### Roles
- `GET /api/v1/roles`: List available roles (`admin`, `dapur`, `gudang`).

## Business Rules

- **Access Control**: Role-based access is enforced via filters. 
  - `admin`: Full access to all endpoints.
  - `dapur`: Access to SPK, dishes, and daily operational data.
  - `gudang`: Access to items, stock transactions, and opnames.
- **Uniqueness**: `username` remains globally unique even after soft delete.
- **Restoration**: Deleted users can be restored by an admin.

## Related Documentation
- [Authentication Guide](./auth.md)
- [Admin Quickstart](../by-user/admin-quickstart.md)
