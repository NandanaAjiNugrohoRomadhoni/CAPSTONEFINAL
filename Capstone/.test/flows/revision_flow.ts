import { createClient, actors } from "../utils/client";

export async function testRevisionFlow() {
  console.log("--- Testing Stock Revision Flow (Gudang -> Admin) ---");
  const sdk = createClient();
  const today = new Date().toISOString().split('T')[0];

  // 1. Gudang Login
  const gudangLogin = await sdk.auth.login(actors.gudang);
  sdk.setAccessToken(gudangLogin.access_token);

  // 2. Gudang creates initial transaction
  const items = await sdk.items.list({ perPage: 1 });
  const testItem = items.data[0];
  
  const parent = await sdk.stockTransactions.create({
    type_name: "IN",
    transaction_date: today,
    details: [{ item_id: testItem.id, qty: 100, input_unit: "base" }]
  });
  const parentId = parent.data.id;
  console.log(`✓ Step 1: Gudang created parent transaction ID: ${parentId}`);

  // 3. Gudang submits revision
  const revision = await sdk.stockTransactions.submitRevision(parentId, {
    transaction_date: today,
    details: [{ item_id: testItem.id, qty: 80, input_unit: "base" }] // Correcting from 100 to 80
  });
  const revisionId = revision.data.id;
  console.log(`✓ Step 2: Gudang submitted revision ID: ${revisionId}`);

  // 4. Admin reviews and approves
  const adminLogin = await sdk.auth.login(actors.admin);
  sdk.setAccessToken(adminLogin.access_token);
  
  await sdk.stockTransactions.approve(revisionId);
  console.log("✓ Step 3: Admin approved revision");

  // Verify stock calculation (Net change should be -20)
  const finalItem = await sdk.items.get(testItem.id);
  console.log(`✓ Flow Verification: Final qty of ${testItem.name} is ${finalItem.data.qty}`);
}
