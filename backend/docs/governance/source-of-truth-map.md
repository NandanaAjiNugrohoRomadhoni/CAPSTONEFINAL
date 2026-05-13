# Source-of-Truth Mapping Matrix

This matrix maps planned documentation domains to implementation sources in the current backend runtime. Use these paths as canonical verification points before authoring or revising docs.

## Canonical Precedence

**Canonical documentation lives in `backend/docs/` only.** Legacy or root-level documentation is archived and non-canonical.

- **Canonical runtime docs**: `backend/docs/reference/api-contract.md`, `backend/docs/reference/schema.md`, `backend/docs/architecture/runtime-status.md`
- **Canonical governance docs**: `backend/docs/governance/source-of-truth-map.md`, `backend/docs/governance/doc-templates.md`, `backend/docs/governance/changelog.md`
- **Canonical guides**: `backend/docs/guides/by-user/*.md`, `backend/docs/guides/by-workflow/*.md`, `backend/docs/guides/by-feature/*.md`
- **Non-canonical (archived)**: Any docs in `backend/docs/archive/` or root-level legacy files are historical reference only and must not be used for implementation decisions.

When docs conflict, prefer the implementation source (routes, controllers, services, models) and then update the canonical doc to match.

## Runtime + SDK Truth Matrix (compact)

Use this compact matrix when aligning backend docs with frontend SDK surfaces. Route and gate truth remains backend runtime code first.

| Domain | Runtime truth sources | SDK truth sources |
|---|---|---|
| `items` | `backend/app/Config/Routes.php`, `backend/app/Controllers/Api/V1/Items.php`, `backend/app/Services/ItemManagementService.php`, `backend/app/Filters/RoleFilter.php` | `frontend/src/sdk/resources/items.ts` |
| `auth/password` | `backend/app/Config/Routes.php` (`PATCH auth/password`), `backend/app/Controllers/Api/V1/Auth.php`, `backend/app/Services/AuthService.php` | `frontend/src/sdk/resources/auth.ts` (`changePassword`) |
| `daily-patients` | `backend/app/Config/Routes.php`, `backend/app/Controllers/Api/V1/DailyPatients.php`, `backend/app/Services/DailyPatientService.php`, `backend/app/Filters/RoleFilter.php` | `frontend/src/sdk/resources/dailyPatients.ts` |
| `spk` | `backend/app/Config/Routes.php`, `backend/app/Controllers/Api/V1/SpkBasah.php`, `backend/app/Controllers/Api/V1/SpkKeringPengemas.php`, `backend/app/Services/SpkBasahGenerationService.php`, `backend/app/Services/SpkKeringPengemasGenerationService.php`, `backend/app/Services/SpkStockPostingService.php` | `frontend/src/sdk/resources/spk.ts` |
| `stock-opnames` | `backend/app/Config/Routes.php`, `backend/app/Controllers/Api/V1/StockOpnames.php`, `backend/app/Services/StockOpnameService.php`, `backend/app/Filters/RoleFilter.php` | `frontend/src/sdk/resources/stockOpnames.ts` |
| `dashboard` | `backend/app/Config/Routes.php` (`GET dashboard`), `backend/app/Controllers/Api/V1/Dashboard.php`, `backend/app/Services/DashboardAggregateService.php` | `frontend/src/sdk/resources/dashboard.ts` |
| `reports` | `backend/app/Config/Routes.php` (`reports/*`), `backend/app/Controllers/Api/V1/Reports.php`, `backend/app/Services/ReportingService.php` | `frontend/src/sdk/resources/reports.ts` |

## Roles

- Primary route and access control authority:
  - `backend/app/Config/Routes.php` (role filters: `role:admin,dapur,gudang`, `role:admin,gudang`, `role:admin,dapur`, `role:admin`)
  - `backend/app/Filters/RoleFilter.php` (enforcement for active user and allowed role names)
- Role identity and bootstrap:
  - `backend/app/Database/Seeds/RoleSeeder.php` (seeded role names: `admin`, `dapur`, `gudang`)
  - `backend/app/Controllers/Api/V1/Auth.php` + `backend/app/Services/AuthService.php` (login/me/logout/change password flow and role payload in user response)

### Validation query hints

- `rg "filter" backend/app/Config/Routes.php | rg "role:"`
- `rg "class RoleFilter|Insufficient permissions|Account is inactive" backend/app/Filters/RoleFilter.php`
- `rg "admin|dapur|gudang" backend/app/Database/Seeds/RoleSeeder.php`

## Stock transactions

- Endpoint surface:
  - `backend/app/Config/Routes.php` (`stock-transactions`, `stock-transactions/(:num)/details`, `stock-transactions/(:num)/submit-revision`, `stock-transactions/direct-corrections`, `stock-transactions/(:num)/approve`, `stock-transactions/(:num)/reject`)
  - `backend/app/Controllers/Api/V1/StockTransactions.php` (index/create/show/details/submitRevision/approve/reject/directCorrection)
- Mutation semantics and constraints:
  - `backend/app/Services/StockTransactionService.php` (supported types, forbidden fields, revision lifecycle, qty updates)
  - `backend/app/Models/TransactionTypeModel.php` (`NAME_IN`, `NAME_OUT`, `NAME_RETURN_IN`)
  - `backend/app/Models/ApprovalStatusModel.php` (`NAME_APPROVED`, `NAME_PENDING`, `NAME_REJECTED`)

### Validation query hints

- `rg "stock-transactions|submit-revision|direct-corrections" backend/app/Config/Routes.php`
- `rg "function (create|directCorrection|submitRevision|approve|reject)" backend/app/Controllers/Api/V1/StockTransactions.php`
- `rg "SUPPORTED_TRANSACTION_TYPES|NAME_IN|NAME_OUT|NAME_RETURN_IN" backend/app/Services/StockTransactionService.php backend/app/Models/TransactionTypeModel.php`
- `rg "NAME_APPROVED|NAME_PENDING|NAME_REJECTED" backend/app/Models/ApprovalStatusModel.php`

## Stock opname

- Endpoint surface:
  - `backend/app/Config/Routes.php` (`stock-opnames`, `stock-opnames/(:num)`, `stock-opnames/(:num)/submit`, `stock-opnames/(:num)/approve`, `stock-opnames/(:num)/reject`, `stock-opnames/(:num)/post`)
  - `backend/app/Controllers/Api/V1/StockOpnames.php` (create/show/submit/approve/reject/post)
- State machine and posting behavior:
  - `backend/app/Services/StockOpnameService.php` (state transition validation and posting transaction generation)
  - `backend/app/Models/StockOpnameModel.php` (`STATE_DRAFT`, `STATE_SUBMITTED`, `STATE_APPROVED`, `STATE_REJECTED`, `STATE_POSTED`)

### Validation query hints

- `rg "stock-opnames" backend/app/Config/Routes.php`
- `rg "function (create|show|submit|approve|reject|post)" backend/app/Controllers/Api/V1/StockOpnames.php`
- `rg "STATE_DRAFT|STATE_SUBMITTED|STATE_APPROVED|STATE_REJECTED|STATE_POSTED" backend/app/Models/StockOpnameModel.php backend/app/Services/StockOpnameService.php`

## Task 4 Spot-Check Matrix — Route↔Doc and SDK↔Doc

This sampled matrix is evidence-grade only: it maps a verified route or SDK method to the implementation source and the doc location that should describe it. It does **not** claim exhaustive endpoint coverage.

| Check | Route / SDK method | Implementation source | Doc location | Status |
|---|---|---|---|---|
| 1 | `PATCH /api/v1/auth/password` / `sdk.auth.changePassword` | `backend/app/Config/Routes.php`, `frontend/src/sdk/resources/auth.ts` | `backend/docs/reference/api-contract.md` §5.1.1, `backend/docs/architecture/runtime-status.md` row `auth/password` | PASS |
| 2 | `GET /api/v1/items` / `sdk.items.list` | `backend/app/Config/Routes.php`, `frontend/src/sdk/resources/items.ts` | `backend/docs/reference/api-contract.md` §5.4 Items, `backend/docs/architecture/runtime-status.md` row `items` | PASS |
| 3 | `GET /api/v1/items/{id}` / `sdk.items.get` | `backend/app/Config/Routes.php`, `frontend/src/sdk/resources/items.ts` | `backend/docs/reference/api-contract.md` §5.4 Items, `backend/docs/architecture/runtime-status.md` row `items` | PASS |
| 4 | `POST /api/v1/items` / `sdk.items.create` | `backend/app/Config/Routes.php`, `frontend/src/sdk/resources/items.ts` | `backend/docs/reference/api-contract.md` §5.4 Items, `backend/docs/architecture/runtime-status.md` row `items` | PASS |
| 5 | `PUT /api/v1/items/{id}` / `sdk.items.update` | `backend/app/Config/Routes.php`, `frontend/src/sdk/resources/items.ts` | `backend/docs/reference/api-contract.md` §5.4 Items, `backend/docs/architecture/runtime-status.md` row `items` | PASS |
| 6 | `DELETE /api/v1/items/{id}` / `sdk.items.delete` | `backend/app/Config/Routes.php`, `frontend/src/sdk/resources/items.ts` | `backend/docs/reference/api-contract.md` §5.4 Items, `backend/docs/architecture/runtime-status.md` row `items` | PASS |
| 7 | `PATCH /api/v1/items/{id}/restore` / `sdk.items.restore` | `backend/app/Config/Routes.php`, `frontend/src/sdk/resources/items.ts` | `backend/docs/reference/api-contract.md` §5.4 Items, `backend/docs/architecture/runtime-status.md` row `items` | PASS |
| 8 | `GET /api/v1/daily-patients` / `sdk.dailyPatients.list` | `backend/app/Config/Routes.php`, `frontend/src/sdk/resources/dailyPatients.ts` | `backend/docs/reference/api-contract.md` §5.7.1 Daily Patients, `backend/docs/architecture/runtime-status.md` row `daily-patients` | PASS |
| 9 | `GET /api/v1/daily-patients/{service_date}` / `sdk.dailyPatients.get` | `backend/app/Config/Routes.php`, `frontend/src/sdk/resources/dailyPatients.ts` | `backend/docs/reference/api-contract.md` §5.7.1 Daily Patients, `backend/docs/architecture/runtime-status.md` row `daily-patients` | PASS |
| 10 | `POST /api/v1/daily-patients` / `sdk.dailyPatients.create` | `backend/app/Config/Routes.php`, `frontend/src/sdk/resources/dailyPatients.ts` | `backend/docs/reference/api-contract.md` §5.7.1 Daily Patients, `backend/docs/architecture/runtime-status.md` row `daily-patients` | PASS |
| 11 | `POST /api/v1/spk/basah/generate` / `sdk.spk.generateBasah` | `backend/app/Config/Routes.php`, `frontend/src/sdk/resources/spk.ts` | `backend/docs/reference/api-contract.md` §5.7.2 SPK Basah Route Family, `backend/docs/architecture/runtime-status.md` row `spk` | PASS |
| 12 | `GET /api/v1/spk/basah/history` / `sdk.spk.listBasah` | `backend/app/Config/Routes.php`, `frontend/src/sdk/resources/spk.ts` | `backend/docs/reference/api-contract.md` §5.7.2 SPK Basah Route Family, `backend/docs/architecture/runtime-status.md` row `spk` | PASS |
| 13 | `GET /api/v1/spk/basah/history/{id}` / `sdk.spk.getBasah` | `backend/app/Config/Routes.php`, `frontend/src/sdk/resources/spk.ts` | `backend/docs/reference/api-contract.md` §5.7.2 SPK Basah Route Family, `backend/docs/architecture/runtime-status.md` row `spk` | PASS |
| 14 | `POST /api/v1/spk/basah/history/{id}/override` / `sdk.spk.overrideBasah` | `backend/app/Config/Routes.php`, `frontend/src/sdk/resources/spk.ts` | `backend/docs/reference/api-contract.md` §5.7.2 SPK Basah Route Family, `backend/docs/architecture/runtime-status.md` row `spk` | PASS |
| 15 | `POST /api/v1/spk/basah/history/{id}/post-stock` / `sdk.spk.postBasahStock` | `backend/app/Config/Routes.php`, `frontend/src/sdk/resources/spk.ts` | `backend/docs/reference/api-contract.md` §5.7.2 SPK Basah Route Family, `backend/docs/architecture/runtime-status.md` row `spk` | PASS |
| 16 | `GET /api/v1/spk/kering-pengemas/history` / `sdk.spk.listKeringPengemas` | `backend/app/Config/Routes.php`, `frontend/src/sdk/resources/spk.ts` | `backend/docs/reference/api-contract.md` §5.7.3 SPK Kering/Pengemas Route Family, `backend/docs/architecture/runtime-status.md` row `spk` | PASS |
| 17 | `GET /api/v1/dashboard` / `sdk.dashboard.getAggregate` | `backend/app/Config/Routes.php`, `frontend/src/sdk/resources/dashboard.ts` | `backend/docs/reference/api-contract.md` §5.8 Dashboard, `backend/docs/architecture/runtime-status.md` row `dashboard` | PASS |
| 18 | `GET /api/v1/reports/stocks` / `sdk.reports.getStocks` | `backend/app/Config/Routes.php`, `frontend/src/sdk/resources/reports.ts` | `backend/docs/reference/api-contract.md` §5.9 Reports, `backend/docs/architecture/runtime-status.md` row `reports` | PASS |
| 19 | `GET /api/v1/reports/transactions` / `sdk.reports.getTransactions` | `backend/app/Config/Routes.php`, `frontend/src/sdk/resources/reports.ts` | `backend/docs/reference/api-contract.md` §5.9 Reports, `backend/docs/architecture/runtime-status.md` row `reports` | PASS |
| 20 | `GET /api/v1/stock-opnames` surface / `sdk.stockOpnames.create|get|submit|approve|reject|post` | `backend/app/Config/Routes.php`, `frontend/src/sdk/resources/stockOpnames.ts` | `backend/docs/reference/api-contract.md` §5.5.8 Revision Workflow Actions & §5.5.10 Stock Opname Compatibility Facade, `backend/docs/architecture/runtime-status.md` row `stock-opnames` | PASS |

## Notes

- Mandatory terms covered by the sampled rows above: `auth/password`, `items CRUD+restore`, `dailyPatients list/get/create`, `SPK extended methods`, `dashboard`, `reports`, `stockOpnames`.
- The matrix is intentionally sampled; it is meant to support traceable spot checks, not replace endpoint-by-endpoint contract verification.

## Ownership and Review Responsibilities

### Documentation Ownership

| Scope | Owner | Reviewer | Responsibility |
|---|---|---|---|
| **API Contract** | Backend Lead | System Architect | Maintain `backend/docs/reference/api-contract.md` in sync with implemented routes, controllers, and request/response shapes. |
| **Database Schema** | DB Admin / Backend Lead | System Architect | Maintain `backend/docs/reference/schema.md` in sync with migrations, models, and FK/uniqueness constraints. |
| **Runtime Status** | System Architect | Backend Lead | Maintain `backend/docs/architecture/runtime-status.md` as the authoritative record of implemented vs planned modules and endpoints. |
| **Error Reference** | Backend Lead | System Architect | Maintain `backend/docs/reference/errors.md` in sync with controller error responses and service validation logic. |
| **By-User Guides** | Technical Writer / QA | Backend Lead | Maintain `backend/docs/guides/by-user/*.md` for role-specific workflows and capabilities. |
| **By-Workflow Guides** | Technical Writer / QA | Backend Lead | Maintain `backend/docs/guides/by-workflow/*.md` for complex multi-step processes and state machines. |
| **By-Feature Guides** | Technical Writer / QA | Backend Lead | Maintain `backend/docs/guides/by-feature/*.md` for feature-specific documentation and integration points. |
| **Source-of-Truth Map** | System Architect | Backend Lead | Maintain this file and the mapping matrix to reflect current implementation sources. |
| **Doc Templates** | System Architect | Backend Lead | Maintain `backend/docs/governance/doc-templates.md` and enforce style contract across all docs. |
| **Governance Changelog** | System Architect | Backend Lead | Record all significant documentation structure, migration, and governance policy changes. |

### Review Cadence

- **Per-Release Review**: All canonical docs must be reviewed and updated before each release to ensure API contract, schema, and runtime status reflect the shipped implementation.
- **Per-Feature Review**: When a new feature is implemented, the corresponding canonical docs (API contract, schema, runtime status, and relevant guides) must be reviewed and updated before the feature is merged.
- **Quarterly Governance Review**: The governance docs (source-of-truth map, doc templates, changelog) are reviewed quarterly to ensure they remain aligned with project practices and ownership structure.
- **Spot-Check Verification**: The Task 4 Spot-Check Matrix (rows 1-20 above) is re-verified quarterly to ensure canonical docs remain traceable to implementation sources.

### Canonical Precedence Rules

1. **Implementation is source of truth**: If code and docs disagree, the implementation (routes, controllers, services, models, tests) is correct. Update the docs to match.
2. **Runtime docs override planned docs**: `backend/docs/reference/` and `backend/docs/architecture/` describe what is actually implemented, not what is planned. Planned features belong in architecture roadmap sections only.
3. **Canonical docs override legacy docs**: Any doc in `backend/docs/` is canonical. Any doc in `backend/docs/archive/` or root-level legacy files is non-canonical and must not be used for implementation decisions.
4. **Governance docs are binding**: Policies in `backend/docs/governance/` (ownership, review cadence, templates, changelog) are binding for all documentation work. Deviations require explicit approval from System Architect.
5. **No duplicate truth**: If a concept is documented in multiple places, the canonical location (per the ownership table above) is the source of truth. Other locations must link to the canonical doc and not duplicate content.

## SPK basah

- Endpoint surface:
  - `backend/app/Config/Routes.php` (`spk/basah/menu-calendar`, `spk/basah/generate`, `spk/basah/operational-stock-preview`, `spk/basah/history`, `spk/basah/history/(:num)`, `spk/basah/history/(:num)/post-stock`, `spk/basah/history/(:num)/override`)
  - `backend/app/Controllers/Api/V1/SpkBasah.php` (menuCalendarProjection/generate/history/show/postStock/overrideItem/operationalStockPreview)
- Generation and stock-post integration:
  - `backend/app/Services/SpkBasahGenerationService.php`
  - `backend/app/Services/SpkStockPostingService.php`
  - `backend/app/Services/SpkOverrideService.php`

### Validation query hints

- `rg "spk/basah" backend/app/Config/Routes.php`
- `rg "function (menuCalendarProjection|generate|history|show|postStock|overrideItem|operationalStockPreview)" backend/app/Controllers/Api/V1/SpkBasah.php`
- `rg "class SpkBasahGenerationService|class SpkStockPostingService|class SpkOverrideService" backend/app/Services/*.php`

## SPK kering-pengemas

- Endpoint surface:
  - `backend/app/Config/Routes.php` (`spk/kering-pengemas/menu-calendar`, `spk/kering-pengemas/generate`, `spk/kering-pengemas/history`, `spk/kering-pengemas/history/(:num)`, `spk/kering-pengemas/history/(:num)/post-stock`, `spk/kering-pengemas/history/(:num)/override`)
  - `backend/app/Controllers/Api/V1/SpkKeringPengemas.php` (menuCalendarProjection/generate/history/show/postStock/overrideItem)
- Generation and stock-post integration:
  - `backend/app/Services/SpkKeringPengemasGenerationService.php`
  - `backend/app/Services/SpkStockPostingService.php`
  - `backend/app/Services/SpkOverrideService.php`

### Validation query hints

- `rg "spk/kering-pengemas" backend/app/Config/Routes.php`
- `rg "function (menuCalendarProjection|generate|history|show|postStock|overrideItem)" backend/app/Controllers/Api/V1/SpkKeringPengemas.php`
- `rg "class SpkKeringPengemasGenerationService|class SpkStockPostingService|class SpkOverrideService" backend/app/Services/*.php`

## Menu planning

- Endpoint surface:
  - `backend/app/Config/Routes.php` (`menus`, `menu-dishes`, `menu-schedules`, `menu-schedules/(:num)`, `menu-calendar`, `daily-patients`, `daily-patients/(:segment)`)
  - `backend/app/Controllers/Api/V1/Menus.php` (index/slots/assignSlot)
  - `backend/app/Controllers/Api/V1/MenuSchedules.php` (index/show/create/update/calendarProjection)
- Supporting service contracts:
  - `backend/app/Services/MenuPackageManagementService.php`
  - `backend/app/Services/MenuScheduleManagementService.php`

### Validation query hints

- `rg "menus|menu-dishes|menu-schedules|menu-calendar|daily-patients" backend/app/Config/Routes.php`
- `rg "function (index|slots|assignSlot)" backend/app/Controllers/Api/V1/Menus.php`
- `rg "function (index|show|create|update|calendarProjection)" backend/app/Controllers/Api/V1/MenuSchedules.php`
- `rg "class MenuPackageManagementService|class MenuScheduleManagementService" backend/app/Services/*.php`

## Errors

- Shared API error shape and status decisions:
  - `backend/app/Controllers/Api/V1/StockTransactions.php` (`Validation failed.`, `Unauthorized.`, `Stock transaction not found.` with 400/401/404)
  - `backend/app/Controllers/Api/V1/StockOpnames.php` (`Unauthorized.` and service-driven `status` propagation)
  - `backend/app/Controllers/Api/V1/SpkBasah.php` + `backend/app/Controllers/Api/V1/SpkKeringPengemas.php` (400 validation failures, 401 unauthenticated, 404 history-not-found)
- Domain/business validation sources:
  - `backend/app/Services/StockTransactionService.php` (unknown fields, invalid type/state, insufficient stock, revision not found)
  - `backend/app/Services/StockOpnameService.php` (invalid state transitions, missing rejection reason, insufficient stock on posting)

### Validation query hints

- `rg "Validation failed\.|Unauthorized\.|not found\." backend/app/Controllers/Api/V1/*.php`
- `rg "Insufficient stock|Invalid state transition|Unknown field\(s\)" backend/app/Services/StockTransactionService.php backend/app/Services/StockOpnameService.php`
