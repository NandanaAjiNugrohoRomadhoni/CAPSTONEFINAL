import TransactionRevisionPage from "@/components/stock/TransactionRevisionPage";

export const metadata = {
  title: "Revisi Riwayat Transaksi Barang | Super Admin",
};

export default function Page() {
  return (
    <TransactionRevisionPage
      title="Revisi Riwayat Transaksi Barang"
      subtitle=""
      role="admin"
    />
  );
}
  
