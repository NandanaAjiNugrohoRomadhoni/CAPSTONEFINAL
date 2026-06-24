import StockAdjustmentPage from "@/components/stock/StockAdjustmentPage";

export default function Page() {
  return (
    <StockAdjustmentPage
      autoApplyOnCreate
      allowVerificationAction
      additionalHistoryStorageKeys={["gudang-stock-opname-history"]}
      historyStorageKey="super-admin-stock-opname-history"
      subtitle=""
      title="Penyesuaian Stok"
    />
  );
}
