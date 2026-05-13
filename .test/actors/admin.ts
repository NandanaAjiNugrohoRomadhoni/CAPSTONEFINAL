import { createClient, actors } from "../utils/client";

export async function testAdminPermissions() {
  console.log("--- Testing Admin Permissions ---");
  const sdk = createClient();
  
  // Login
  const login = await sdk.auth.login(actors.admin);
  sdk.setAccessToken(login.access_token);
  console.log("✓ Admin logged in");

  // Admin should see users
  try {
    const users = await sdk.users.list({ perPage: 1 });
    console.log(`✓ Admin access to users/list: ${users.data.length} found`);
  } catch (e) {
    console.error("✗ Admin should have access to users/list");
  }

  // Admin should see items
  try {
    const items = await sdk.items.list({ perPage: 1 });
    console.log(`✓ Admin access to items/list: ${items.data.length} found`);
  } catch (e) {
    console.error("✗ Admin should have access to items/list");
  }
}
