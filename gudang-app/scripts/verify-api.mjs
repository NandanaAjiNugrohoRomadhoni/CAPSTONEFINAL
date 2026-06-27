#!/usr/bin/env node
// Verify backend read API endpoints against http://localhost:8080
// Usage: node scripts/verify-api.mjs [username] [password]

const BASE = process.env.API_URL || "http://localhost:8080/api/v1";
const USERNAME = process.argv[2] || "admin";
const PASSWORD = process.argv[3] || "password123";

let token = null;
let user = null;

// ---------- helpers ----------

async function api(path, opts = {}) {
  const headers = { "Content-Type": "application/json" };
  if (token) headers["Authorization"] = `Bearer ${token}`;
  const url = `${BASE}${path}`;
  const res = await fetch(url, { ...opts, headers, ...(opts.body ? { body: JSON.stringify(opts.body) } : {}) });
  const text = await res.text();
  let data;
  try { data = JSON.parse(text); } catch { data = null; }
  return { status: res.status, ok: res.ok, data, text: text.slice(0, 500) };
}

function statusIcon(okFlag) { return okFlag ? "\u2705" : "\u274C"; }

function plural(n, w) { return `${n} ${w}${n !== 1 ? "s" : ""}`; }

// ---------- login ----------

async function login() {
  const res = await api("/auth/login", {
    method: "POST",
    body: { username: USERNAME, password: PASSWORD },
  });
  if (res.ok && res.data?.access_token) {
    token = res.data.access_token;
    user = res.data.user;
    return res.data;
  }
  return null;
}

// ---------- test runner ----------

const results = [];

async function test(name, fn) {
  try {
    const r = await fn();
    results.push({ name, ...r });
  } catch (e) {
    results.push({ name, status: 0, ok: false, error: e.message ?? String(e) });
  }
}

// ---------- read API endpoints ----------

const endpoints = [
  // [name, method, path]
  ["GET /auth/me",               "GET",  "/auth/me"],
  ["GET /dashboard",             "GET",  "/dashboard"],
  ["GET /items",                 "GET",  "/items"],
  ["GET /item-categories",       "GET",  "/item-categories"],
  ["GET /transaction-types",     "GET",  "/transaction-types"],
  ["GET /approval-statuses",     "GET",  "/approval-statuses"],
  ["GET /meal-times",            "GET",  "/meal-times"],
  ["GET /item-units",            "GET",  "/item-units"],
  ["GET /stock-transactions",    "GET",  "/stock-transactions"],
  ["GET /stock-opnames",         "GET",  "/stock-opnames"],
  ["GET /stock-snapshots/current","GET", "/stock-snapshots/current"],
  ["GET /notifications",         "GET",  "/notifications"],
  ["GET /audit-logs",            "GET",  "/audit-logs"],
  ["GET /audit-logs/summary",    "GET",  "/audit-logs/summary"],
  ["GET /users",                 "GET",  "/users"],
  ["GET /roles",                 "GET",  "/roles"],
  ["GET /dishes",                "GET",  "/dishes"],
  ["GET /menus",                 "GET",  "/menus"],
  ["GET /menu-dishes",           "GET",  "/menu-dishes"],
  ["GET /menu-schedules",        "GET",  "/menu-schedules"],
  ["GET /menu-calendar",         "GET",  "/menu-calendar?month=2026-06"],
  ["GET /daily-patients",        "GET",  "/daily-patients"],
  ["GET /reports/stocks",        "GET",  "/reports/stocks?month=2026-06"],
  ["GET /reports/transactions",  "GET",  "/reports/transactions?month=2026-06"],
  ["GET /reports/spk-history",   "GET",  "/reports/spk-history?month=2026-06"],
  ["GET /reports/evaluation",    "GET",  "/reports/evaluation?month=2026-06"],
  ["GET /spk/basah/history",     "GET",  "/spk/basah/history"],
  ["GET /spk/kering-pengemas/history","GET","/spk/kering-pengemas/history"],
];

async function main() {
  console.log(`\n🔐 Login as "${USERNAME}"...`);
  let loginResult = await login();
  if (!loginResult) {
    console.log(`  ❌ Login failed. Trying "dapur" / "gudang"...`);
    for (const u of ["dapur", "gudang"]) {
      const r = await api("/auth/login", { method: "POST", body: { username: u, password: PASSWORD } });
      if (r.ok && r.data?.access_token) {
        token = r.data.access_token;
        user = r.data.user;
        loginResult = r.data;
        console.log(`  ✅ Logged in as "${u}" (${r.data.user?.name ?? "?"})`);
        break;
      }
    }
    if (!token) {
      console.log(`  ❌ All login attempts failed. Aborting.`);
      process.exit(1);
    }
  } else {
    const roleName = loginResult.user?.role?.name ?? loginResult.user?.role ?? "?";
    console.log(`  ✅ ${loginResult.user?.name ?? USERNAME} (${roleName})`);
  }

  console.log(`\n📡 Verifying ${endpoints.length} read endpoints...\n`);

  for (const [name, method, path] of endpoints) {
    await test(name, async () => {
      const r = await api(path, { method });
      const dataLen = r.data?.data
        ? (Array.isArray(r.data.data) ? r.data.data.length : "obj")
        : r.data?.rows
          ? (Array.isArray(r.data.rows) ? r.data.rows.length : "obj")
          : r.data
            ? "has-data"
            : 0;
      return {
        status: r.status,
        ok: r.ok,
        detail: r.ok
          ? (typeof dataLen === "number" ? `${plural(dataLen, "record")}` : dataLen)
          : (r.data?.message ?? r.text?.slice(0, 80) ?? "???"),
      };
    });
  }

  // ---------- results ----------

  const passed = results.filter(r => r.ok).length;
  const failed = results.filter(r => !r.ok).length;

  console.log(`\n─── Results ───`);
  console.log(`  ${results.length} endpoints, ${passed} passed, ${failed} failed\n`);

  for (const r of results) {
    const icon = statusIcon(r.ok);
    const detail = r.error ?? r.detail ?? "";
    console.log(`  ${icon} ${r.name} \u2192 ${r.status} ${detail ? `(${detail})` : ""}`);
  }

  console.log(`\n─── Summary ───`);
  if (failed === 0) {
    console.log(`  ✅ All ${results.length} read API endpoints verified OK`);
  } else {
    console.log(`  ⚠️  ${failed}/${results.length} endpoints failed (may be auth-scoped or data-dependent)`);
  }
  const roleName = user?.role?.name ?? user?.role ?? "?";
  console.log(`  User: ${user?.name ?? "?"} (${roleName})`);
  console.log(`  Token: ${token ? `${token.slice(0, 20)}...` : "none"}`);
}

main().catch(e => { console.error("Fatal:", e); process.exit(1); });
