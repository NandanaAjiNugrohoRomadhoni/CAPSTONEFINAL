import { ReactNode } from "react";

export default function Card({
  title,
  value,
  subtitle,
  color,
  icon,
  borderColor,
}: {
  title: string;
  value: string;
  subtitle?: string;
  color: string;
  icon?: ReactNode;
  borderColor?: string;
}) {
  return (
    <div
      className={`bg-white rounded-2xl p-5 border border-gray-100 shadow-sm border-t-4 ${borderColor}`}
    >
      <div className="flex justify-between items-center mb-3">
        <p className="text-xs text-gray-400 font-medium uppercase">
          {title}
        </p>
        <div className="text-lg">{icon}</div>
      </div>

      <h2 className={`text-3xl font-bold ${color}`}>
        {value}
      </h2>

      {subtitle && (
        <p className="text-xs text-gray-400 mt-1">
          {subtitle}
        </p>
      )}
    </div>
  );
}