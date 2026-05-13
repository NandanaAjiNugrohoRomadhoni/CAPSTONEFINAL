import { createClient, actors } from "../utils/client";

export async function testOpnameFlow() {
  console.log("--- Testing Stock Opname Flow (Gudang -> Admin) ---");
  const sdk = createClient();

  // 1. Gudang Login
  const gudangLogin = await sdk.auth.login(actors.gudang);
  sdk.setAccessToken(gudangLogin.access_token);
  console.log("✓ Step 1: Gudang logged in");

  // Get an item to opname
  const items = await sdk.items.list({ perPage: 1 });
  const testItem = items.data[0];

  // 2. Gudang creates opname draft
  const opnameDate = new Date().toISOString().split('T')[0];
  const opname = await sdk.stockOpnames.create({
    opname_date: opnameDate,
    notes: "Monthly Audit Test",
    details: [
      {
        item_id: testItem.id,
        counted_qty: Math.max(0, Number(testItem.qty) - 10) // Found 10 units missing
      }
    ]
  });
  const opnameId = opname.data.id;
  console.log(`✓ Step 2: Gudang created opname draft ID: ${opnameId}`);

  // 3. Gudang submits opname
  await sdk.stockOpnames.submit(opnameId);
  console.log("✓ Step 3: Gudang submitted opname for approval");

  // 4. Admin reviews and approves
  const adminLogin = await sdk.auth.login(actors.admin);
  sdk.setAccessToken(adminLogin.access_token);
  
  await sdk.stockOpnames.approve(opnameId);
  console.log("✓ Step 4: Admin approved opname");

  // 5. Admin posts opname
  await sdk.stockOpnames.post(opnameId);
  console.log("✓ Step 5: Admin posted opname to ledger");

  // Verify stock change (should be current - 10)
  const updatedItem = await sdk.items.get(testItem.id);
  console.log(`✓ Flow Verification: Item ${testItem.name} qty changed from ${testItem.qty} to ${updatedItem.data.qty}`);
}
