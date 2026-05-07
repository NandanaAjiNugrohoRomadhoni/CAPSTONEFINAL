import { createClient, actors } from "../utils/client";

const RESOURCES = [
  { name: 'auth.me', method: 'GET', path: '/auth/me', call: (sdk: any) => sdk.auth.me() },
  { name: 'roles.list', method: 'GET', path: '/roles', call: (sdk: any) => sdk.roles.list() },
  { name: 'users.list', method: 'GET', path: '/users', call: (sdk: any) => sdk.users.list() },
  { name: 'items.list', method: 'GET', path: '/items', call: (sdk: any) => sdk.items.list() },
  { name: 'itemCategories.list', method: 'GET', path: '/item-categories', call: (sdk: any) => sdk.itemCategories.list() },
  { name: 'itemUnits.list', method: 'GET', path: '/item-units', call: (sdk: any) => sdk.itemUnits.list() },
  { name: 'stockTransactions.list', method: 'GET', path: '/stock-transactions', call: (sdk: any) => sdk.stockTransactions.list() },
  { name: 'dailyPatients.list', method: 'GET', path: '/daily-patients', call: (sdk: any) => sdk.dailyPatients.list() },
  { name: 'spk.listBasah', method: 'GET', path: '/spk/basah/history', call: (sdk: any) => sdk.spk.listBasah() },
  { name: 'spk.listKeringPengemas', method: 'GET', path: '/spk/kering-pengemas/history', call: (sdk: any) => sdk.spk.listKeringPengemas() },
  { name: 'menus.list', method: 'GET', path: '/menus', call: (sdk: any) => sdk.menus.list() },
  { name: 'menus.slots', method: 'GET', path: '/menu-dishes', call: (sdk: any) => sdk.menus.slots() },
  { name: 'dishes.list', method: 'GET', path: '/dishes', call: (sdk: any) => sdk.dishes.list() },
  { name: 'dishCompositions.list', method: 'GET', path: '/dish-compositions', call: (sdk: any) => sdk.dishCompositions.list() },
  { name: 'menuSchedules.list', method: 'GET', path: '/menu-schedules', call: (sdk: any) => sdk.menuSchedules.list() },
  { name: 'dashboard.getAggregate', method: 'GET', path: '/dashboard', call: (sdk: any) => sdk.dashboard.getAggregate() },
];

const WRITE_RESOURCES = [
    { name: 'dailyPatients.create', method: 'POST', path: '/daily-patients', call: (sdk: any) => sdk.dailyPatients.create({ service_date: '2099-01-01', total_patients: 1, meal_time: 'PAGI' }) },
    { name: 'dishes.create', method: 'POST', path: '/dishes', call: (sdk: any) => sdk.dishes.create({ name: 'Test Dish', category: 'Testing' }) },
    { name: 'menus.assignSlot', method: 'POST', path: '/menu-dishes', call: (sdk: any) => sdk.menus.assignSlot({ menu_id: 1, meal_time_id: 1, dish_id: 1 }) },
];

async function testActor(actorName: string, credentials: any) {
  console.log(`\n=== Analyzing Actor: ${actorName.toUpperCase()} ===`);
  const sdk = createClient();
  try {
    const login = await sdk.auth.login(credentials);
    sdk.setAccessToken(login.access_token);
    console.log(`[Auth] Login successful as ${actorName}`);
  } catch (e: any) {
    console.error(`[Auth] Login failed for ${actorName}: ${e.message}`);
    return;
  }

  console.log(`\n--- Read Permission Matrix ---`);
  for (const res of RESOURCES) {
    try {
      await res.call(sdk);
      console.log(`[OK]  ${res.method} ${res.path} (${res.name})`);
    } catch (e: any) {
      if (e.status === 403) {
        console.log(`[403] ${res.method} ${res.path} (${res.name}) - Forbidden`);
      } else if (e.status === 404) {
        console.log(`[404] ${res.method} ${res.path} (${res.name}) - Not Found`);
      } else {
        console.log(`[ERR] ${res.method} ${res.path} (${res.name}) - Status: ${e.status}, Msg: ${e.message}`);
      }
    }
  }

  console.log(`\n--- Write Permission Matrix ---`);
  for (const res of WRITE_RESOURCES) {
    try {
      await res.call(sdk);
      console.log(`[OK]  ${res.method} ${res.path} (${res.name})`);
    } catch (e: any) {
       if (e.status === 403) {
        console.log(`[403] ${res.method} ${res.path} (${res.name}) - Forbidden`);
      } else if (e.status === 400) {
         // 400 might be validation error but it means it REACHED the controller (Permission OK)
         console.log(`[400] ${res.method} ${res.path} (${res.name}) - Reachable (Validation Error)`);
      } else {
        console.log(`[ERR] ${res.method} ${res.path} (${res.name}) - Status: ${e.status}, Msg: ${e.message}`);
      }
    }
  }
}

async function runAnalysis() {
  await testActor('admin', actors.admin);
  await testActor('dapur', actors.dapur);
  await testActor('gudang', actors.gudang);
}

runAnalysis();
