import TransactionRevisionPage from "@/components/stock/TransactionRevisionPage";

export const metadata = {
  title: "Revisi Riwayat Transaksi Barang | Super Admin",
};

export default function Page() {
  return (
    <TransactionRevisionPage
      title="Revisi Riwayat Transaksi Barang"
      subtitle="Tinjau dan proses pengajuan revisi transaksi barang dari tim gudang."
      role="admin"
    />
  );
}
  