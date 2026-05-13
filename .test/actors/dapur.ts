import { createClient, actors } from "../utils/client";

export async function testDapurPermissions() {
  console.log("--- Testing Dapur Permissions ---");
  const sdk = createClient();
  
  const login = await sdk.auth.login(actors.dapur);
  sdk.setAccessToken(login.access_token);
  console.log("✓ Dapur logged in");

  // Dapur should fail users/list (403)
  try {
    await sdk.users.list({ perPage: 1 });
    console.error("✗ Dapur should NOT have access to users/list");
  } catch (e: any) {
    if (e.status === 403) {
        console.log("✓ Dapur access to users/list: Forbidden (Correct)");
    } else {
        console.error("✗ Expected 403 for users/list, got:", e.status);
    }
  }

  // Dapur should fail items/list (403)
  try {
    await sdk.items.list({ perPage: 1 });
    console.error("✗ Dapur should NOT have access to items/list");
  } catch (e: any) {
     if (e.status === 403) {
        console.log("✓ Dapur access to items/list: Forbidden (Correct)");
    } else {
        console.error("✗ Expected 403 for items/list, got:", e.status);
    }
  }

  // Dapur should have access to daily patients (POST)
  try {
    const today = new Date().toISOString().split('T')[0];
    await sdk.dailyPatients.create({
      service_date: today,
      total_patients: 100,
      meal_time: 'SIANG'
    });
    console.log("✓ Dapur access to dailyPatients/create: Success");
  } catch (e: any) {
    if (e.errors && e.errors.service_date) {
        console.log("✓ Dapur access to dailyPatients/create: Success (Already exists but permission verified)");
    } else {
        console.error("✗ Dapur should have access to dailyPatients/create", e.message, e.errors);
    }
  }
}
