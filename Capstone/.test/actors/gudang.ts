import { createClient, actors } from "../utils/client";

export async function testGudangPermissions() {
  console.log("--- Testing Gudang Permissions ---");
  const sdk = createClient();
  
  const login = await sdk.auth.login(actors.gudang);
  sdk.setAccessToken(login.access_token);
  console.log("✓ Gudang logged in");

  // Gudang should fail users/list (403)
  try {
    await sdk.users.list({ perPage: 1 });
    console.error("✗ Gudang should NOT have access to users/list");
  } catch (e: any) {
    if (e.status === 403) {
        console.log("✓ Gudang access to users/list: Forbidden (Correct)");
    } else {
        console.error("✗ Expected 403 for users/list, got:", e.status);
    }
  }

  // Gudang should have access to items/list
  try {
    const items = await sdk.items.list({ perPage: 1 });
    console.log(`✓ Gudang access to items/list: ${items.data.length} found`);
  } catch (e: any) {
    console.error("✗ Gudang should have access to items/list", e.status);
  }

  // Gudang should have access to stock transactions (POST)
  try {
    const items = await sdk.items.list({ perPage: 1 });
    if (items.data.length > 0) {
      const today = new Date().toISOString().split('T')[0];
      await sdk.stockTransactions.create({
        type_name: "IN",
        transaction_date: today,
        details: [
          {
            item_id: items.data[0].id,
            qty: 10,
            input_unit: "base"
          }
        ]
      });
      console.log("✓ Gudang access to stockTransactions/create: Success");
    }
  } catch (e: any) {
    console.error("✗ Gudang should have access to stockTransactions/create", e.message, e.errors);
  }
}
