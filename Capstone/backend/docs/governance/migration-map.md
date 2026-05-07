# Documentation Migration Map and Ownership Index

This document maps legacy top-level documentation to the new canonical structure. Use this map to track migration progress, identify ownership scopes, and locate replacements for deprecated docs.

## 1. Migration Status Definitions

| Status | Action |
|---|---|
| **KEEP** | Doc remains in its current location with minimal updates. |
| **MOVE** | Doc will be moved to a new directory in the `backend/docs/` hierarchy. |
| **REMOVED** | Doc is removed; no longer present in repository. |
| **ARCHIVE** | Doc is moved to `backend/docs/archive/` for historical reference only. |

---

## 2. Master Migration Map

| Original File | Status | Destination / Replacement | Owner Scope | Phase | Removal Status |
|---|---|---|---|---|---|
| `README.md` | **KEEP** | `backend/README.md` | Core / Setup | 1 | N/A |
| `api-design.md` | **REMOVED** | `backend/docs/reference/api-contract.md` | API | 2 | Removed (2026-04-16) |
| `data-dictionary.md` | **REMOVED** | `backend/docs/reference/schema.md` | Data | 2 | Removed (2026-04-16) |
| `fr-traceability-matrix.md` | **REMOVED** | Superseded by canonical docs | PM / QA | 3 | Removed (2026-04-16) |
| `migration-baseline-audit-task-1.md` | **REMOVED** | Superseded by canonical docs | Core | 3 | Removed (2026-04-16) |
| `project-flow-alignment.md` | **REMOVED** | `backend/docs/architecture/runtime-status.md` | Core | 1 | Removed (2026-04-16) |
| `stock-transaction-analysis.md` | **REMOVED** | `backend/docs/guides/by-workflow/stock-correction-workflow.md` | Stock | 2 | Removed (2026-04-16) |
| `system-design-plan.md` | **REMOVED** | `backend/docs/architecture/runtime-status.md` | Architecture | 3 | Removed (2026-04-16) |
| `system-design.md` | **REMOVED** | `backend/docs/architecture/runtime-status.md` | Architecture | 3 | Removed (2026-04-16) |
| `typescript-sdk-maintenance-guide.md` | **REMOVED** | Superseded by `backend/AGENTS.md` | SDK | 3 | Removed (2026-04-16) |
| `use-case-diagram.md` | **REMOVED** | `backend/docs/architecture/runtime-status.md` | Architecture | 3 | Removed (2026-04-16) |

---

## 3. Removal Details

### Removed Legacy Files (2026-04-16)

The following files have been removed from the repository. They are superseded by the canonical documentation:

- `api-design.md`: Superseded by `backend/docs/reference/api-contract.md`.
- `data-dictionary.md`: Superseded by `backend/docs/reference/schema.md`.
- `fr-traceability-matrix.md`: Superseded by canonical docs.
- `migration-baseline-audit-task-1.md`: Superseded by canonical docs.
- `project-flow-alignment.md`: Superseded by `backend/docs/architecture/runtime-status.md`.
- `stock-transaction-analysis.md`: Superseded by `backend/docs/guides/by-workflow/stock-correction-workflow.md`.
- `system-design-plan.md`: Superseded by `backend/docs/architecture/runtime-status.md`.
- `system-design.md`: Superseded by `backend/docs/architecture/runtime-status.md`.
- `typescript-sdk-maintenance-guide.md`: Superseded by `backend/AGENTS.md`.
- `use-case-diagram.md`: Superseded by `backend/docs/architecture/runtime-status.md`.

---

## 4. Ownership Index

| Scope | Canonical Directory | Primary Owner |
|---|---|---|
| **Core** | `backend/docs/` | System Architect |
| **Governance** | `backend/docs/governance/` | System Architect |
| **API** | `backend/docs/reference/` | Backend Lead |
| **Data** | `backend/docs/reference/` | DB Admin / Backend Lead |
| **Guides** | `backend/docs/guides/` | Technical Writer / QA |
| **Architecture** | `backend/docs/architecture/` | System Architect |
| **Archive** | `backend/docs/archive/` | N/A |

---

## 5. Verification Checklist

- [x] All top-level `backend/docs/*.md` files are listed.
- [x] Every entry has a status, destination, and owner scope.
- [x] DEPRECATE entries include a reason and removal marker.
- [x] Table-driven and deterministic structure.
