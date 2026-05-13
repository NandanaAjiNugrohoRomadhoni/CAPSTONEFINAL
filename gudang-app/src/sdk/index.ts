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
export class CapstoneSdk {
  public readonly client: ApiClient;
  public readonly approvalStatuses: ApprovalStatusesResource;
  public readonly auth: AuthResource;
  public readonly dailyPatients: DailyPatientsResource;
  public readonly dishes: DishesResource;
  public readonly dishCompositions: DishCompositionsResource;
  public readonly itemCategories: ItemCategoriesResource;
  public readonly roles: RolesResource;
  public readonly items: ItemsResource;
  public readonly itemUnits: ItemUnitsResource;
  public readonly mealTimes: MealTimesResource;
  public readonly menus: MenusResource;
  public readonly menuSchedules: MenuSchedulesResource;
  public readonly notifications: NotificationsResource;
  public readonly spk: SpkResource;
  public readonly stockTransactions: StockTransactionsResource;
  public readonly transactionTypes: TransactionTypesResource;
  public readonly users: UsersResource;
  public readonly dashboard: DashboardResource;
  public readonly reports: ReportsResource;
  public readonly stockOpnames: StockOpnamesResource;

  public constructor(options: ApiClientOptions) {
    this.client = new ApiClient(options);
    this.approvalStatuses = new ApprovalStatusesResource(this.client);
    this.auth = new AuthResource(this.client);
    this.dailyPatients = new DailyPatientsResource(this.client);
    this.dishes = new DishesResource(this.client);
    this.dishCompositions = new DishCompositionsResource(this.client);
    this.itemCategories = new ItemCategoriesResource(this.client);
    this.roles = new RolesResource(this.client);
    this.items = new ItemsResource(this.client);
    this.itemUnits = new ItemUnitsResource(this.client);
    this.mealTimes = new MealTimesResource(this.client);
    this.menus = new MenusResource(this.client);
    this.menuSchedules = new MenuSchedulesResource(this.client);
    this.notifications = new NotificationsResource(this.client);
    this.spk = new SpkResource(this.client);
    this.stockTransactions = new StockTransactionsResource(this.client);
    this.transactionTypes = new TransactionTypesResource(this.client);
    this.users = new UsersResource(this.client);
    this.dashboard = new DashboardResource(this.client);
    this.reports = new ReportsResource(this.client);
    this.stockOpnames = new StockOpnamesResource(this.client);
  }

  /**
   * Updates the in-memory bearer token used by the shared client.
   */
  public setAccessToken(token: string | null): void {
    this.client.setAccessToken(token);
  }

  /**
   * Clears the in-memory bearer token used by the shared client.
   */
  public clearAccessToken(): void {
    this.client.clearAccessToken();
  }
}

/**
 * Creates a configured SDK instance for the Capstone API.
 */
export function createCapstoneSdk(options: ApiClientOptions): CapstoneSdk {
  return new CapstoneSdk(options);
}
