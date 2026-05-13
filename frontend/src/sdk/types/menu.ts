import type { ApiDataResponse, ApiListResponse, ApiMessageDataResponse } from "./common";

/** Fixed package menu row returned by `/api/v1/menus`. */
export interface Menu {
  id: number;
  name: string;
}

/** Dish row returned by `/api/v1/dishes*`. */
export interface Dish {
  id: number;
  name: string;
  created_at?: string | null;
  updated_at?: string | null;
}

/** Nested dish summary used in menu and composition responses. */
export interface DishSummary {
  id: number;
  name: string | null;
}

/** Nested item summary used in dish composition responses. */
export interface DishCompositionItemSummary {
  id: number;
  name: string | null;
  unit_base: string | null;
  is_active: boolean | null;
}

/** Dish composition row returned by `/api/v1/dish-compositions*`. */
export interface DishComposition {
  id: number;
  dish_id: number;
  item_id: number;
  qty_per_patient: string;
  created_at: string | null;
  updated_at: string | null;
  dish: DishSummary;
  item: DishCompositionItemSummary;
}

/** Menu slot assignment row returned by `/api/v1/menu-dishes*`. */
export interface MenuSlot {
  id: number;
  menu_id: number;
  meal_time_id: number;
  dish_id: number;
  created_at: string | null;
  updated_at: string | null;
  menu: Menu;
  meal_time: {
    id: number;
    name: string | null;
  };
  dish: DishSummary;
}

/** Manual day-of-month override row returned by `/api/v1/menu-schedules*`. */
export interface MenuSchedule {
  id: number;
  day_of_month: number;
  menu_id: number;
  created_at: string | null;
  updated_at: string | null;
  menu: Menu;
}

/** Effective calendar projection entry returned by `/api/v1/menu-calendar`. */
export interface MenuCalendarEntry {
  date: string;
  day_of_month: number;
  menu_id: number;
  menu_name: string;
}

export interface MenuCalendarMonthMeta {
  month: string;
  total: number;
}

export interface MenuCalendarRangeMeta {
  start_date: string;
  end_date: string;
  total: number;
}

/** Response for `GET /api/v1/menu-calendar?date=YYYY-MM-DD`. */
export interface MenuCalendarDateResponse extends ApiDataResponse<MenuCalendarEntry> {}

/** Response for `GET /api/v1/menu-calendar?month=YYYY-MM`. */
export interface MenuCalendarMonthResponse extends ApiDataResponse<MenuCalendarEntry[]> {
  meta: MenuCalendarMonthMeta;
}

/** Response for `GET /api/v1/menu-calendar?start_date=...&end_date=...`. */
export interface MenuCalendarRangeResponse extends ApiDataResponse<MenuCalendarEntry[]> {
  meta: MenuCalendarRangeMeta;
}

export type MenuCalendarResponse = MenuCalendarDateResponse | MenuCalendarMonthResponse | MenuCalendarRangeResponse;

/** Query params for `GET /api/v1/dishes`. */
export interface ListDishesQuery {
  page?: number;
  perPage?: number;
  q?: string;
  search?: string;
  sortBy?: "id" | "name" | "created_at" | "updated_at";
  sortDir?: "ASC" | "DESC";
  created_at_from?: string;
  created_at_to?: string;
  updated_at_from?: string;
  updated_at_to?: string;
}

/** Query params for `GET /api/v1/dish-compositions`. */
export interface ListDishCompositionsQuery {
  page?: number;
  perPage?: number;
  dish_id?: number;
  item_id?: number;
  q?: string;
  search?: string;
  sortBy?: "id" | "dish_id" | "item_id" | "qty_per_patient" | "created_at" | "updated_at";
  sortDir?: "ASC" | "DESC";
  created_at_from?: string;
  created_at_to?: string;
  updated_at_from?: string;
  updated_at_to?: string;
}

/** Query params for `GET /api/v1/menu-calendar`. Send exactly one of `date`, `month`, or `start_date` + `end_date`. */
export interface MenuCalendarQuery {
  month?: string;
  date?: string;
  start_date?: string;
  end_date?: string;
}

/** Request payload for `POST /api/v1/dishes`. */
export interface CreateDishRequest {
  name: string;
}

/** Request payload for `PUT /api/v1/dishes/{id}`. */
export interface UpdateDishRequest {
  name?: string;
}

/** Request payload for `POST /api/v1/dish-compositions`. The `dish_id` + `item_id` pair must stay unique. */
export interface CreateDishCompositionRequest {
  dish_id: number;
  item_id: number;
  qty_per_patient: string;
}

/** Request payload for `PUT /api/v1/dish-compositions/{id}`. */
export interface UpdateDishCompositionRequest {
  dish_id?: number;
  item_id?: number;
  qty_per_patient?: string;
}

/** Request payload for `POST /api/v1/menu-dishes`. Occupied slots are rejected. */
export interface CreateMenuSlotRequest {
  menu_id: number;
  meal_time_id: number;
  dish_id: number;
}

/** Request payload for `PUT /api/v1/menu-dishes/{id}`. */
export interface UpdateMenuSlotRequest {
  menu_id?: number;
  meal_time_id?: number;
  dish_id?: number;
}

/** Request payload for `POST /api/v1/menu-schedules`. `day_of_month` must stay unique. */
export interface CreateMenuScheduleRequest {
  day_of_month: number;
  menu_id: number;
}

/** Request payload for `PUT /api/v1/menu-schedules/{id}`. */
export interface UpdateMenuScheduleRequest {
  day_of_month?: number;
  menu_id?: number;
}

export type DishesListResponse = ApiListResponse<Dish>;
export type DishCompositionsListResponse = ApiListResponse<DishComposition>;
export type MenusListResponse = ApiListResponse<Menu>;
export type MenuSlotsListResponse = ApiListResponse<MenuSlot>;
export type MenuSchedulesListResponse = ApiListResponse<MenuSchedule>;

export type DishCreateResponse = ApiMessageDataResponse<Dish>;
export type DishCompositionCreateResponse = ApiMessageDataResponse<DishComposition>;
export type MenuSlotCreateResponse = ApiMessageDataResponse<MenuSlot>;
export type MenuScheduleCreateResponse = ApiMessageDataResponse<MenuSchedule>;
