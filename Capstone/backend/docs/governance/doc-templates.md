# Documentation Templates and Style Contract

This document defines the mandatory templates and style requirements for backend documentation. All new documentation must follow these structures to ensure consistency across the project.

## 1. Style Contract

- **Tone:** Professional, technical, and concise.
- **Perspective:** Third-person ("The system does X") or objective ("Use endpoint Y").
- **Path-first:** Always include the file path or endpoint path at the beginning of the section.
- **Language:** English for technical headers and structure; Indonesian or English for descriptive content depending on existing doc context.
- **No AI Slop:** Avoid "delve", "it's important to note", "leverage", etc. Use plain words like "use", "check", "start".
- **Formatting:** Use standard Markdown. No em-dashes (—) or en-dashes (–); use commas, periods, or line breaks instead.

## 1.1 Governance Requirements

All documentation must include:

- **Ownership**: Identify the owner and reviewer roles (see `backend/docs/governance/source-of-truth-map.md` Ownership table).
- **Canonical Status**: Explicitly state if this doc is canonical or archived. Canonical docs live in `backend/docs/`; archived docs live in `backend/docs/archive/`.
- **Implementation Grounding**: Every doc must reference concrete implementation sources (routes, controllers, services, models, tests). No speculative or planned-only content in canonical docs.
- **Review Cadence**: Specify when this doc is reviewed (per-release, per-feature, quarterly, or on-demand).
- **Last Updated**: Include a date or version reference to indicate freshness.

---

## 2. By-User Quickstart Template

Use this template for guides targeted at specific user roles (Admin, Dapur, Gudang).

**File Path:** `backend/docs/guides/by-user/[role]-quickstart.md`

### Template Structure:

```markdown
# [Role Name] Quickstart Guide

## Your Role
Summary of the responsibilities and primary objectives for this role within the system.

## Can/Can’t
- **Can:** List of allowed actions and accessible modules.
- **Can’t:** List of restricted actions or modules requiring higher privileges.

## Key Workflows
Step-by-step description of the most frequent tasks performed by this role.
1. [Workflow A]
2. [Workflow B]

## Gotchas
Common pitfalls, validation rules, or non-obvious behaviors this user should know.
- [Issue 1]
- [Issue 2]
```

---

## 3. By-Workflow Guide Template

Use this template for complex multi-step processes or state-heavy domains.

**File Path:** `backend/docs/guides/by-workflow/[workflow-name].md`

### Template Structure:

```markdown
# [Workflow Name] Workflow

## Overview
High-level summary of the workflow's purpose.

## State Machine
Definition of states and valid transitions.
- **[STATE_A]** -> **[STATE_B]**: Triggered by [Action].
- **[STATE_B]** -> **[STATE_C]**: Triggered by [Action].

## Step-by-step endpoints
Sequence of API calls required to complete the workflow.
1. `POST /api/v1/...` - [Description]
2. `GET /api/v1/...` - [Description]

## Failure paths
How to handle errors, rejections, or edge cases.
- [Failure 1]: [Resolution]
- [Failure 2]: [Resolution]
```

---

## 4. Reference Page Template

Use this template for API contracts, schema definitions, or lookup tables.

**File Path:** `backend/docs/reference/[category].md`

### Template Structure:

```markdown
# [Category] Reference

## Summary
Brief description of the reference data or API group.

## Endpoint/Schema Table
| Field/Method | Path/Type | Description |
|---|---|---|
| | | |

## Request/Response Examples
```json
// Example payload
```
```

---

## 5. Deprecation Bridge Page Template

Use this template when a doc or feature is being phased out but remains for reference during migration.

**File Path:** `backend/docs/archive/[filename].md`

### Template Structure:

```markdown
# DEPRECATED: [Original Title]

> **Replacement:** [Link to new doc or feature]
> **Removal target:** [Sprint/Date/Version]

## Reason for Deprecation
Brief explanation of why this is being replaced.

## Migration Path
How to transition from the old logic/doc to the new one.
```

---

## 6. Verification Checklist

When creating a doc from these templates, ensure:
1. [ ] Mandatory headings are present.
2. [ ] Placeholders (like `Replacement:`) are filled or explicitly marked as TBD.
3. [ ] Paths are correct and follow the `backend/docs/` hierarchy.
4. [ ] Style contract (no AI slop, no em-dashes) is respected.
5. [ ] Ownership and reviewer roles are identified (per `source-of-truth-map.md`).
6. [ ] Canonical status is explicitly stated (canonical in `backend/docs/` or archived in `backend/docs/archive/`).
7. [ ] All claims are grounded in implementation sources (routes, controllers, services, models, tests).
8. [ ] Review cadence is specified (per-release, per-feature, quarterly, or on-demand).
9. [ ] Last updated date or version reference is included.
