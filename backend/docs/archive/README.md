# Archive Directory

This directory contains historical and superseded documentation that has been moved out of the active documentation tree but is retained for reference and traceability.

## Archive Criteria

Documents are moved to this archive when they meet one or more of these conditions:

1. **Superseded by Canonical**: The document has been replaced by a newer canonical version in the active documentation tree (e.g., `system-design-plan.md` superseded by `backend/docs/architecture/runtime-status.md`).
2. **Historical Reference Only**: The document describes past design decisions, audit trails, or project phases that are no longer active but should be retained for traceability.
3. **Deprecated with Replacement**: The document is explicitly deprecated in favor of a newer document, with a clear replacement link and removal schedule.

## Retention Policy

### Retention Period

- **Indefinite**: Documents in this archive are retained indefinitely unless explicitly scheduled for removal.
- **Scheduled Removal**: Some documents may have an explicit removal target release (e.g., "Removal target: v1.2.0"). These documents will be removed in the specified release.

### Removal Conditions

A document is eligible for hard removal from the archive when:

1. **Removal Target Release Reached**: The document has an explicit "Removal target" marker and the specified release has been published.
2. **No Active References**: No active documentation or code comments reference the archived document.
3. **Explicit Approval**: The removal is approved by the System Architect or Documentation Owner.

### Removal Process

1. **Announce**: Include removal notice in the release changelog 2 releases before removal.
2. **Verify**: Confirm no active references exist in code, docs, or external links.
3. **Remove**: Delete the file from the archive directory in the target release.
4. **Document**: Record the removal in the governance changelog.

## Current Archive Contents

- **`Software Requirements Specification (SRS).md`** - Original software requirements specification. Superseded by canonical documentation in `backend/docs/`. Planned retention: Indefinite.
- **`System Request.md`** - Original system request and business requirements. Superseded by canonical documentation in `backend/docs/`. Planned retention: Indefinite.

## Archive Access

To access archived documents:

1. Navigate to `backend/docs/archive/` in the repository.
2. Open the desired document directly.
3. Check the document header for deprecation status and replacement links.

## Adding to Archive

When archiving a document:

1. Move the file to `backend/docs/archive/`.
2. Add an entry to this README under the appropriate section.
3. Include the document's supersession status and removal target (if applicable).
4. Update the governance changelog (`backend/docs/governance/changelog.md`).
5. Update any active documentation that referenced the original location with a deprecation note and link to the archived version.

## Related Documentation

- **Governance Changelog**: `backend/docs/governance/changelog.md` - Record of documentation changes and migrations
- **Migration Map**: `backend/docs/governance/migration-map.md` - Master mapping of old → new paths and deprecation schedule
- **Documentation Index**: `backend/docs/README.md` - Entry point for active documentation
