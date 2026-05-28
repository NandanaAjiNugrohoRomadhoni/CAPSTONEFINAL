"use client";

export function getSpkConflictId(error: unknown): number | null {
  const visited = new Set<unknown>();
  const body = error && typeof error === "object" && "body" in error ? error.body : null;
  const candidates = [body, error];

  for (const candidate of candidates) {
    const conflictId = findSpkConflictId(candidate, visited);
    if (conflictId) return conflictId;
  }

  return null;
}

function findSpkConflictId(value: unknown, visited: Set<unknown>): number | null {
  if (!value || typeof value !== "object") return null;
  if (visited.has(value)) return null;
  visited.add(value);

  const record = value as Record<string, unknown>;
  const message = typeof record.message === "string" ? record.message.toLowerCase() : "";
  const isSpkConflict = message.includes("spk generation conflict") || message.includes("generation conflict");

  if (isSpkConflict) {
    const directConflictId = readConflictId(record.conflict);
    if (directConflictId) return directConflictId;

    const directSpkId = readPositiveNumber(record.spk_id);
    if (directSpkId) return directSpkId;
  }

  const nestedConflictId = readConflictId(record.conflict);
  if (nestedConflictId) return nestedConflictId;

  for (const nestedValue of Object.values(record)) {
    const nestedId = findSpkConflictId(nestedValue, visited);
    if (nestedId) return nestedId;
  }

  return null;
}

function readConflictId(value: unknown): number | null {
  if (!value || typeof value !== "object") return null;
  const record = value as Record<string, unknown>;
  return readPositiveNumber(record.spk_id ?? record.id);
}

function readPositiveNumber(value: unknown): number | null {
  const numeric = Number(value);
  return Number.isFinite(numeric) && numeric > 0 ? numeric : null;
}
