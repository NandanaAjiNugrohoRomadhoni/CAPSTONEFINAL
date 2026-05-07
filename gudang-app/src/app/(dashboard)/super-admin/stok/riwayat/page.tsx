import StockAdjustmentPage from "@/components/stock/StockAdjustmentPage";

export default function Page() {
  return (
    <StockAdjustmentPage
      autoApplyOnCreate
      allowVerificationAction
      additionalHistoryStorageKeys={["gudang-stock-opname-history"]}
      historyStorageKey="super-admin-stock-opname-history"
      subtitle="Input penyesuaian stok fisik ke backend stock opname dan tampilkan history draft yang pernah dibuat."
      title="Penyesuaian Stok"
    />
  );
}
