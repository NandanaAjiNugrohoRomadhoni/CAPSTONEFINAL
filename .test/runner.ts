import { testAdminPermissions } from "./actors/admin";
import { testDapurPermissions } from "./actors/dapur";
import { testGudangPermissions } from "./actors/gudang";
import { testEndToEndFlow } from "./flows/endtoend";
import { testOpnameFlow } from "./flows/opname_flow";
import { testRevisionFlow } from "./flows/revision_flow";
import { testSoftDeleteFlow } from "./flows/soft_delete_flow";

async function runAllTests() {
  console.log("Starting Full E2E Test Suite...\n");
  
  try {
    await testAdminPermissions();
    console.log("");
    await testDapurPermissions();
    console.log("");
    await testGudangPermissions();
    console.log("");
    
    console.log("--- BUSINESS FLOWS ---");
    await testEndToEndFlow();
    console.log("");
    await testOpnameFlow();
    console.log("");
    await testRevisionFlow();
    console.log("");
    await testSoftDeleteFlow();
    
    console.log("\nAll tests completed successfully.");
  } catch (error: any) {
    console.error("\nUnexpected test runner error:", error.message || error);
    if (error.status) console.error("HTTP Status:", error.status);
    if (error.errors) console.error("Validation Errors:", error.errors);
    process.exit(1);
  }
}

runAllTests();
