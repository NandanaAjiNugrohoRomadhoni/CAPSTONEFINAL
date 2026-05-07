import StockAdjustmentPage from "@/components/stock/StockAdjustmentPage";

export default function Page() {
  return (
    <StockAdjustmentPage
      additionalHistoryStorageKeys={["super-admin-stock-opname-history"]}
      historyStorageKey="gudang-stock-opname-history"
      legacyLatestKey="gudang-latest-stock-opname-id"
      subtitle="Input penyesuaian stok fisik ke backend stock opname dan simpan riwayat draft yang pernah dibuat."
      title="Penyesuaian Stok"
      useDraftSubmissionChecklist
    />
  );
}
