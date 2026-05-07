export default function BahanTable() {
  return (
    <div className="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
      
      {/* HEADER */}
      <div className="flex justify-between mb-2">
        <h3 className="font-semibold text-gray-900">
          Bahan Keluar Hari Ini
        </h3>
        <button className="text-sm text-blue-500">
          Lihat Detail →
        </button>
      </div>

      <p className="text-xs text-gray-400 mb-4">
        12 Maret 2026 · Paket 2 · 40 pasien
      </p>

      {/* TABLE */}
      <table className="w-full text-sm">
        <thead>
          <tr className="text-gray-400 border-b">
            <th className="text-left py-2">Bahan</th>
            <th>Keluar</th>
            <th>Sisa</th>
            <th>Status</th>
          </tr>
        </thead>

        <tbody className="text-gray-700">
          
          <tr className="border-b">
            <td className="py-2">
              <p className="font-medium">Beras</p>
              <p className="text-xs text-gray-400">KERING</p>
            </td>
            <td>18.6 kg</td>
            <td>45.5 kg</td>
            <td>
              <span className="text-xs px-2 py-1 rounded-full bg-green-100 text-green-600 font-medium">
                Aman
              </span>
            </td>
          </tr>

          <tr className="border-b">
            <td className="py-2">
              <p className="font-medium">Ayam</p>
              <p className="text-xs text-gray-400">BASAH</p>
            </td>
            <td>6.2 kg</td>
            <td>4 kg</td>
            <td>
              <span className="text-xs px-2 py-1 rounded-full bg-red-100 text-red-600 font-medium">
                Kritis
              </span>
            </td>
          </tr>

        </tbody>
      </table>

      <p className="text-xs text-gray-400 mt-3">
        Menampilkan 2 item keluar hari ini
      </p>
    </div>
  );
}