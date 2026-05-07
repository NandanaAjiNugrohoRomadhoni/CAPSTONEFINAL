import { ReactNode } from "react";

export default function StatCard({
  title,
  value,
  subtitle,
  color,
  icon,
}: {
  title: string;
  value: string;
  subtitle: string;
  color: string;
  icon: ReactNode;
}) {
  return (
    <div
      className={`bg-white p-6 rounded-2xl shadow-sm border border-gray-100 border-t-4 ${color}`}
    >
      <div className="flex justify-between items-start">
        
        <div>
          <p className="text-xs uppercase tracking-wide text-gray-400">
            {title}
          </p>

          <h2 className="text-2xl font-semibold text-gray-900 mt-1">
            {value}
          </h2>

          <p className="text-xs text-gray-400 mt-1">
            {subtitle}
          </p>
        </div>

        <div className="bg-gray-100 p-2 rounded-lg">
          {icon}
        </div>

      </div>
    </div>
  );
}