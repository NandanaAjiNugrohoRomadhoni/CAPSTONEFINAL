# Menu Planning Feature Guide

Manages the core kitchen schedule, dish recipes, and patient counts.

## Endpoints

### Menus & Dishes
- `GET /api/v1/menus`: List defined menu packages (e.g., Paket 1-11). Read-only; identity is fixed.
- `GET /api/v1/menu-dishes`: List assigned meal time slots for packages.
- `POST /api/v1/menu-dishes`: Assign a dish to a slot (Admin/Dapur). Rejects if slot is occupied.
- `PUT /api/v1/menu-dishes/{id}`: Update/replace dish or metadata for a slot (Admin/Dapur).
- `DELETE /api/v1/menu-dishes/{id}`: Remove a slot assignment (Admin/Dapur).
- `GET /api/v1/dishes`: List all dishes.
- `GET /api/v1/dishes/{id}`: Get dish detail.
- `POST /api/v1/dishes`: Create a new dish (Admin/Dapur).
- `PUT /api/v1/dishes/{id}`: Update a dish (Admin/Dapur).
- `DELETE /api/v1/dishes/{id}`: Delete a dish (Admin/Dapur).

### Scheduling & Patient Data
- `GET /api/v1/menu-schedules`: List manual schedule overrides for days of the month.
- `GET /api/v1/menu-schedules/{id}`: Get schedule override detail.
- `POST /api/v1/menu-schedules`: Create a schedule override for a day (Admin/Dapur).
- `PUT /api/v1/menu-schedules/{id}`: Update a schedule override (Admin/Dapur).
- `GET /api/v1/menu-calendar`: View the current month's menu calendar projection.
- `GET /api/v1/daily-patients`: List record of patient counts per date.
- `GET /api/v1/daily-patients/{service_date}`: Get patient count detail by service date (`Y-m-d`).
- `POST /api/v1/daily-patients`: Create the patient count for a date (Admin/Dapur). No edit/delete allowed for audit integrity.

## Business Rules

- **Slots**: Each menu (Package) is assigned to a meal time (`PAGI`, `SIANG`, `SORE`).
- **Compositions**: Dish recipes must use valid `items` and `item-units`.
- **Estimation**: SPK generation uses the `daily-patients` count as the primary multiplier for calculations.

## Related Documentation
- [Dapur Quickstart](../by-user/dapur-quickstart.md)
- [SPK Feature Guide](./spk.md)
