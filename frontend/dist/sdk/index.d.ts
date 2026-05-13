export * from "./client";
export * from "./errors";
export * from "./resources/auth";
export * from "./resources/approvalStatuses";
export * from "./resources/dailyPatients";
export * from "./resources/dishes";
export * from "./resources/dishCompositions";
export * from "./resources/itemCategories";
export * from "./resources/items";
export * from "./resources/itemUnits";
export * from "./resources/mealTimes";
export * from "./resources/menus";
export * from "./resources/menuSchedules";
export * from "./resources/notifications";
export * from "./resources/roles";
export * from "./resources/spk";
export * from "./resources/stockTransactions";
export * from "./resources/transactionTypes";
export * from "./resources/users";
export * from "./resources/dashboard";
export * from "./resources/reports";
export * from "./resources/stockOpnames";
export * from "./types";
import { ApiClient, type ApiClientOptions } from "./client";
import { ApprovalStatusesResource } from "./resources/approvalStatuses";
import { AuthResource } from "./resources/auth";
import { DailyPatientsResource } from "./resources/dailyPatients";
import { DishesResource } from "./resources/dishes";
import { DishCompositionsResource } from "./resources/dishCompositions";
import { ItemCategoriesResource } from "./resources/itemCategories";
import { ItemsResource } from "./resources/items";
import { ItemUnitsResource } from "./resources/itemUnits";
import { MealTimesResource } from "./resources/mealTimes";
import { MenusResource } from "./resources/menus";
import { MenuSchedulesResource } from "./resources/menuSchedules";
import { NotificationsResource } from "./resources/notifications";
import { RolesResource } from "./resources/roles";
import { SpkResource } from "./resources/spk";
import { StockTransactionsResource } from "./resources/stockTransactions";
import { TransactionTypesResource } from "./resources/transactionTypes";
import { UsersResource } from "./resources/users";
import { DashboardResource } from "./resources/dashboard";
import { ReportsResource } from "./resources/reports";
import { StockOpnamesResource } from "./resources/stockOpnames";
/**
 * High-level SDK entry point for the current Capstone API surface.
 */
export declare class CapstoneSdk {
    readonly client: ApiClient;
    readonly approvalStatuses: ApprovalStatusesResource;
    readonly auth: AuthResource;
    readonly dailyPatients: DailyPatientsResource;
    readonly dishes: DishesResource;
    readonly dishCompositions: DishCompositionsResource;
    readonly itemCategories: ItemCategoriesResource;
    readonly roles: RolesResource;
    readonly items: ItemsResource;
    readonly itemUnits: ItemUnitsResource;
    readonly mealTimes: MealTimesResource;
    readonly menus: MenusResource;
    readonly menuSchedules: MenuSchedulesResource;
    readonly notifications: NotificationsResource;
    readonly spk: SpkResource;
    readonly stockTransactions: StockTransactionsResource;
    readonly transactionTypes: TransactionTypesResource;
    readonly users: UsersResource;
    readonly dashboard: DashboardResource;
    readonly reports: ReportsResource;
    readonly stockOpnames: StockOpnamesResource;
    constructor(options: ApiClientOptions);
    /**
     * Updates the in-memory bearer token used by the shared client.
     */
    setAccessToken(token: string | null): void;
    /**
     * Clears the in-memory bearer token used by the shared client.
     */
    clearAccessToken(): void;
}
/**
 * Creates a configured SDK instance for the Capstone API.
 */
export declare function createCapstoneSdk(options: ApiClientOptions): CapstoneSdk;
