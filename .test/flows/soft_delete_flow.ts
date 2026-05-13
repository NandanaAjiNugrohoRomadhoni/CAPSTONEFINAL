import { createClient, actors } from "../utils/client";

export async function testSoftDeleteFlow() {
  console.log("--- Testing Soft Delete & Restore Flow (Admin) ---");
  const sdk = createClient();

  // 1. Admin Login
  const adminLogin = await sdk.auth.login(actors.admin);
  sdk.setAccessToken(adminLogin.access_token);

  // 2. Create and Delete Item Category
  const categoryName = `TempCat_${Date.now()}`;
  const category = await sdk.itemCategories.create({ name: categoryName });
  const catId = category.data.id;
  console.log(`✓ Step 1: Created category ${categoryName} (ID: ${catId})`);

  await sdk.itemCategories.delete(catId);
  console.log(`✓ Step 2: Soft-deleted category ID: ${catId}`);

  // Try to create same name (Should fail 400 with restore_id)
  try {
    await sdk.itemCategories.create({ name: categoryName });
    console.error("✗ Creating duplicate name after soft delete should have failed");
  } catch (e: any) {
    if (e.status === 400 && e.errors.restore_id) {
        console.log(`✓ Step 3: Duplicate create correctly failed with restore_id: ${e.errors.restore_id}`);
    } else {
        console.error("✗ Expected 400 with restore_id, got:", e.status, e.errors);
    }
  }

  // Restore
  await sdk.itemCategories.restore(catId);
  console.log(`✓ Step 4: Restored category ID: ${catId}`);

  // Cleanup
  await sdk.itemCategories.delete(catId);
  console.log(`✓ Step 5: Final cleanup delete`);
}
