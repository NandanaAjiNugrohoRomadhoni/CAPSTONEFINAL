import TransactionRevisionPage from "@/components/stock/TransactionRevisionPage";

export const metadata = {
  title: "Pengajuan Revisi Transaksi Barang | Gudang",
};

export default function Page() {
  return (
    <TransactionRevisionPage
      title="Pengajuan Revisi Transaksi Barang"
      subtitle="Pantau status pengajuan revisi transaksi barang masuk dan keluar Anda."
      role="gudang"
    />
  );
}
