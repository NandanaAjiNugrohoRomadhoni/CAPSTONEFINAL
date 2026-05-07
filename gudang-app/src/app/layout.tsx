import type { Metadata } from "next";
import "./globals.css";
import "@fontsource/inter";

export const metadata: Metadata = {
  title: "Gudang App",
  description: "Sistem informasi gudang dan gizi RSD Balung",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="id" className="h-full antialiased">
      <body className="min-h-full flex flex-col">{children}</body>
    </html>
  );
}
