"use client";

import {
  LineChart,
  Line,
  XAxis,
  Tooltip,
  ResponsiveContainer,
} from "recharts";

const data = [
  { day: "Sen", pasien: 20 },
  { day: "Sel", pasien: 30 },
  { day: "Rab", pasien: 25 },
  { day: "Kam", pasien: 40 },
  { day: "Jum", pasien: 35 },
  { day: "Sab", pasien: 50 },
  { day: "Min", pasien: 45 },
];

export default function PatientChart() {
  return (
    <ResponsiveContainer width="100%" height={150}>
      <LineChart data={data}>
        <XAxis dataKey="day" stroke="#ccc" />
        <Tooltip />
        <Line
          type="monotone"
          dataKey="pasien"
          stroke="#3b82f6"
          strokeWidth={3}
        />
      </LineChart>
    </ResponsiveContainer>
  );
}