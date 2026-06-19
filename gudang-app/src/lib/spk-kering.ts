export type KeringRecommendationLike = {
  item_id?: number | null;
  item_name?: string | null;
  current_stock_qty?: unknown;
  required_qty?: unknown;
  system_recommended_qty?: unknown;
  final_recommended_qty?: unknown;
  item_unit_base?: string | null;
};

export function calculateKeringRecommendedQty(requiredQty: unknown, currentStockQty: unknown) {
  const numericRequiredQty = Number(requiredQty ?? 0);
  const numericCurrentStockQty = Number(currentStockQty ?? 0);

  const safeRequiredQty = Number.isFinite(numericRequiredQty) ? numericRequiredQty : 0;
  const safeCurrentStockQty = Number.isFinite(numericCurrentStockQty) ? numericCurrentStockQty : 0;

  return Math.max(safeRequiredQty * 1.1 - safeCurrentStockQty, 0);
}

export function aggregateKeringRecommendationRows<T extends KeringRecommendationLike>(rows: T[]) {
  const byItem = new Map<
    string,
    T & {
      current_stock_qty: number;
      required_qty: number;
      system_recommended_qty: number;
      final_recommended_qty: number;
    }
  >();

  for (const row of rows) {
    const requiredQty = Number(row.required_qty ?? 0);
    const currentStockQty = Number(row.current_stock_qty ?? 0);

    if (!Number.isFinite(requiredQty) || !Number.isFinite(currentStockQty)) continue;

    const recommendation = calculateKeringRecommendedQty(requiredQty, currentStockQty);
    const key = String(row.item_id ?? row.item_name ?? row.final_recommended_qty ?? byItem.size);
    const current = byItem.get(key);

    if (current) {
      const combinedRequiredQty = Number(current.required_qty ?? 0) + requiredQty;
      const combinedCurrentStockQty =
        Number.isFinite(Number(current.current_stock_qty ?? 0)) && Number(current.current_stock_qty ?? 0) > 0
          ? Number(current.current_stock_qty ?? 0)
          : currentStockQty;
      const combinedRecommendation = calculateKeringRecommendedQty(combinedRequiredQty, combinedCurrentStockQty);

      byItem.set(key, {
        ...current,
        current_stock_qty: combinedCurrentStockQty,
        required_qty: combinedRequiredQty,
        system_recommended_qty: combinedRecommendation,
        final_recommended_qty: combinedRecommendation,
      });
      continue;
    }

    byItem.set(key, {
      ...row,
      current_stock_qty: currentStockQty,
      required_qty: requiredQty,
      system_recommended_qty: recommendation,
      final_recommended_qty: recommendation,
    });
  }

  return Array.from(byItem.values());
}
