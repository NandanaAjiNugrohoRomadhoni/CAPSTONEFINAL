export function isItemDeleteConstraintError(message: string) {
  const normalized = message.toLowerCase();
  return (
    normalized.includes("cannot delete item because it is used in one or more dishes") ||
    normalized.includes("used in one or more dishes") ||
    normalized.includes("dipakai pada menu") ||
    normalized.includes("sedang dipakai") ||
    normalized.includes("constraint") ||
    normalized.includes("foreign key") ||
    normalized.includes("referential")
  );
}
