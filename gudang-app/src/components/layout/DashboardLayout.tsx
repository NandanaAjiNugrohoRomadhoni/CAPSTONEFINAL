"use client";

import type { ReactNode } from "react";
import { useState } from "react";
import { usePathname } from "next/navigation";
import Sidebar from "./Sidebar";
import Navbar from "./Navbar";

export default function DashboardLayout({
  children,
}: Readonly<{
  children: ReactNode;
}>) {
  const [open, setOpen] = useState(false);
  const pathname = usePathname();

  return (
    <div className="flex">
      
      {/* SIDEBAR */}
      <Sidebar open={open} setOpen={setOpen} />

      {/* MAIN CONTENT */}
      <div className="flex-1 lg:ml-64 bg-[#F5F7FB] min-h-screen">
        <Navbar setOpen={setOpen} />
        <div key={pathname} className="animate-page-enter p-6">
          {children}
        </div>
      </div>
    </div>
  );
}
