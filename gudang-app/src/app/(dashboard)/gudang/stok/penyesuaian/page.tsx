import StockAdjustmentPage from "@/components/stock/StockAdjustmentPage";

export default function Page() {
  return (
    <StockAdjustmentPage
      additionalHistoryStorageKeys={["super-admin-stock-opname-history"]}
      historyStorageKey="gudang-stock-opname-history"
      legacyLatestKey="gudang-latest-stock-opname-id"
      subtitle=""
      title="Penyesuaian Stok"
      submittedStateLabel="Menunggu Konfirmasi"
      useDraftSubmissionChecklist
    />
  );
}
