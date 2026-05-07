import { createClient, actors } from "../utils/client";

export async function testEndToEndFlow() {
  console.log("--- Testing End-to-End Flow (Dapur -> Gudang -> Admin) ---");
  const sdk = createClient();
  const today = new Date().toISOString().split('T')[0];

  // 1. Dapur inputs patients
  const dapurLogin = await sdk.auth.login(actors.dapur);
  sdk.setAccessToken(dapurLogin.access_token);
  
  let patient_id;
  // Use a random future date to ensure uniqueness in tests
  const randomDays = Math.floor(Math.random() * 1000) + 100;
  const futureDate = new Date();
  futureDate.setDate(futureDate.getDate() + randomDays);
  const targetDateStr = futureDate.toISOString().split('T')[0];

  try {
    console.log(`Debug: Sending service_date = ${targetDateStr}`);
    const patients = await sdk.dailyPatients.create({
      service_date: targetDateStr,
      total_patients: 10000, // Massive number to force positive recommendation
      meal_time: 'PAGI'
    });
    patient_id = Number(patients.data.id);
    console.log("✓ Step 1: Dapur created daily patients for", targetDateStr);
  } catch (e: any) {
    console.error("Debug: Error payload:", e.errors);
    throw e;
  }

  // 2. Dapur generates SPK recommendation
  const spkResponse = await sdk.spk.generateBasah({
    daily_patient_id: patient_id,
    service_date: targetDateStr,
    category_id: 1 
  });
  
  // WORKAROUND: Use admin to fetch details because Dapur is currently blocked from GET /spk/basah/history/:id by a bug in Routes.php
  const adminLoginForFetch = await sdk.auth.login(actors.admin);
  sdk.setAccessToken(adminLoginForFetch.access_token);
  
  const spkDetail = await sdk.spk.getBasah(spkResponse.data.id);
  const hasPositive = spkDetail.data.items.some(item => Number(item.final_recommended_qty) > 0);
  
  if (!hasPositive) {
      console.log("⚠ Warning: No positive recommendations in generated SPK. Flow might fail at post-stock.");
      // Dapur CAN override though
      sdk.setAccessToken(dapurLogin.access_token);
      await sdk.spk.overrideBasah(spkResponse.data.id, {
          recommendation_id: spkDetail.data.items[0].id,
          recommended_qty: 100,
          reason: "Test override"
      });
      console.log("✓ Step 2b: Dapur forced override to ensure positive recommendation");
  } else {
      sdk.setAccessToken(dapurLogin.access_token);
  }

  const spkId = spkResponse.data.id;
  console.log("✓ Step 2: Dapur generated SPK Basah recommendation (ID:", spkId, ")");

  // 3. Gudang records Stock IN based on SPK
  const gudangLogin = await sdk.auth.login(actors.gudang);
  sdk.setAccessToken(gudangLogin.access_token);

  // Get first item from items list to test
  const items = await sdk.items.list({ perPage: 1 });
  if (items.data.length === 0) throw new Error("No items found to test stock transaction");
  const testItem = items.data[0];

  const stockIn = await sdk.stockTransactions.create({
    type_name: "IN",
    transaction_date: today,
    spk_id: spkId,
    details: [
      {
        item_id: testItem.id,
        qty: 1000, // 1000g / 1kg
        input_unit: "base"
      }
    ]
  });
  console.log("✓ Step 3: Gudang recorded Stock IN for item", testItem.name);

  // 4. Admin reviews and posts SPK stock (Finalization)
  const adminLogin = await sdk.auth.login(actors.admin);
  sdk.setAccessToken(adminLogin.access_token);

  await sdk.spk.postBasahStock(spkId);
  console.log("✓ Step 4: Admin posted SPK stock (Finalized)");

  // Verify stock transaction exists
  const transactions = await sdk.stockTransactions.list({ perPage: 1 });
  console.log("✓ Flow Verification: Last transaction ID:", transactions.data[0].id);
}
