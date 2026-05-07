export default function StockSummary() {
  return (
    <div className="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
      
      <h3 className="font-semibold text-gray-900 mb-4">
        Ringkasan Stok Bahan
      </h3>

      {/* BOX */}
      <div className="grid grid-cols-2 gap-3 text-sm mb-4">
        <div className="bg-green-100 p-3 rounded-lg">
          <p className="text-green-600 font-semibold">42</p>
          <p className="text-gray-500">Aman</p>
        </div>

        <div className="bg-yellow-100 p-3 rounded-lg">
          <p className="text-yellow-600 font-semibold">7</p>
          <p className="text-gray-500">Menipis</p>
        </div>

        <div className="bg-red-100 p-3 rounded-lg">
          <p className="text-red-600 font-semibold">3</p>
          <p className="text-gray-500">Kritis</p>
        </div>

        <div className="bg-gray-200 p-3 rounded-lg">
          <p className="text-gray-700 font-semibold">1</p>
          <p className="text-gray-500">Habis</p>
        </div>
      </div>

      {/* PROGRESS */}
      <div className="space-y-2 text-sm">
        
        {[
          { name: "Ayam", val: 80 },
          { name: "Bawang Putih", val: 20 },
          { name: "Beras", val: 40 },
          { name: "Wortel", val: 60 },
        ].map((item) => (
          <div key={item.name}>
            <div className="flex justify-between text-gray-600">
              <span>{item.name}</span>
              <span>{item.val}%</span>
            </div>

            <div className="w-full bg-gray-200 h-2 rounded">
              <div
                className="h-2 bg-orange-400 rounded"
                style={{ width: `${item.val}%` }}
              />
            </div>
          </div>
        ))}

      </div>
    </div>
  );
}