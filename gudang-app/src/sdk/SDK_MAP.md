# AI Agent Navigation Index (SDK_MAP)

This document maps backend API endpoints to their corresponding SDK methods and response types. Use this to quickly find the right tool for a given request.

| Backend Endpoint | SDK Method | Response Type |
| :--- | :--- | :--- |
 | **Audit Logs** | | |
 | `GET /api/v1/audit-logs` | `sdk.auditLogs.list(query?)` | `AuditLogListResponse` |
 | `GET /api/v1/audit-logs/summary` | `sdk.auditLogs.summary()` | `AuditLogSummaryResponse` |
 | `GET /api/v1/audit-logs/types` | `sdk.auditLogs.types()` | `AuditLogTypesResponse` |

| **Approval Statuses** | | |
| `GET /api/v1/approval-statuses` | `sdk.approvalStatuses.list(query?)` | `ApiListResponse<ApprovalStatus>` |
| **Auth** | | |
| `POST /api/v1/auth/login` | `sdk.auth.login(payload)` | `LoginResponse` |
| `GET /api/v1/auth/me` | `sdk.auth.me()` | `ApiDataResponse<User>` |
| `POST /api/v1/auth/logout` | `sdk.auth.logout()` | `ApiMessageResponse` |
| `PATCH /api/v1/auth/password` | `sdk.auth.changePassword(payload)` | `ApiMessageResponse` |
| **Daily Patients** | | |
| `GET /api/v1/daily-patients` | `sdk.dailyPatients.list()` | `DailyPatientsListResponse` |
| `GET /api/v1/daily-patients/{service_date}` | `sdk.dailyPatients.get(serviceDate)` | `DailyPatientResponse` |
| `POST /api/v1/daily-patients` | `sdk.dailyPatients.create(payload)` | `DailyPatientCreateResponse` |
| `PUT /api/v1/daily-patients/{id}` | `sdk.dailyPatients.update(id, payload)` | `DailyPatientUpdateResponse` |
| **Dashboard** | | |
| `GET /api/v1/dashboard` | `sdk.dashboard.getAggregate()` | `DashboardResponse` |
| **Dish Compositions** | | |
| `GET /api/v1/dish-compositions` | `sdk.dishCompositions.list(query?)` | `DishCompositionsListResponse` |
| `GET /api/v1/dish-compositions/{id}` | `sdk.dishCompositions.get(id)` | `ApiDataResponse<DishComposition>` |
| `POST /api/v1/dish-compositions` | `sdk.dishCompositions.create(payload)` | `ApiMessageDataResponse<DishComposition>` |
| `PUT /api/v1/dish-compositions/{id}` | `sdk.dishCompositions.update(id, payload)` | `ApiMessageDataResponse<DishComposition>` |
| `DELETE /api/v1/dish-compositions/{id}` | `sdk.dishCompositions.delete(id)` | `ApiMessageResponse` |
| **Dishes** | | |
| `GET /api/v1/dishes` | `sdk.dishes.list(query?)` | `DishesListResponse` |
| `GET /api/v1/dishes/{id}` | `sdk.dishes.get(id)` | `ApiDataResponse<Dish>` |
| `POST /api/v1/dishes` | `sdk.dishes.create(payload)` | `ApiMessageDataResponse<Dish>` |
| `PUT /api/v1/dishes/{id}` | `sdk.dishes.update(id, payload)` | `ApiMessageDataResponse<Dish>` |
| `PATCH /api/v1/dishes/{id}/deactivate` | `sdk.dishes.deactivate(id)` | `ApiMessageDataResponse<Dish>` |
| `PATCH /api/v1/dishes/{id}/reactivate` | `sdk.dishes.reactivate(id)` | `ApiMessageDataResponse<Dish>` |
| `DELETE /api/v1/dishes/{id}` | `sdk.dishes.delete(id)` | `ApiMessageResponse` |
| **Item Categories** | | |
| `GET /api/v1/item-categories` | `sdk.itemCategories.list(query?)` | `ApiListResponse<ItemCategory>` |
| `GET /api/v1/item-categories/{id}` | `sdk.itemCategories.get(id)` | `ApiDataResponse<ItemCategory>` |
| `POST /api/v1/item-categories` | `sdk.itemCategories.create(payload)` | `ApiMessageDataResponse<ItemCategory>` |
| `PUT /api/v1/item-categories/{id}` | `sdk.itemCategories.update(id, payload)` | `ApiMessageDataResponse<ItemCategory>` |
| `DELETE /api/v1/item-categories/{id}` | `sdk.itemCategories.delete(id)` | `ApiMessageResponse` |
| `PATCH /api/v1/item-categories/{id}/restore` | `sdk.itemCategories.restore(id)` | `ApiMessageDataResponse<ItemCategory>` |
| **Items** | | |
| `GET /api/v1/items` | `sdk.items.list(query?)` | `ApiListResponse<Item>` |
| `GET /api/v1/items/{id}` | `sdk.items.get(id)` | `ApiDataResponse<Item>` |
| `POST /api/v1/items` | `sdk.items.create(payload)` | `ApiMessageDataResponse<Item>` |
| `PUT /api/v1/items/{id}` | `sdk.items.update(id, payload)` | `ApiMessageDataResponse<Item>` |
| `DELETE /api/v1/items/{id}` | `sdk.items.delete(id)` | `ApiMessageResponse` |
| `PATCH /api/v1/items/{id}/restore` | `sdk.items.restore(id)` | `ApiMessageDataResponse<Item>` |
| **Item Units** | | |
| `GET /api/v1/item-units` | `sdk.itemUnits.list(query?)` | `ApiListResponse<ItemUnit>` |
| `GET /api/v1/item-units/{id}` | `sdk.itemUnits.get(id)` | `ApiDataResponse<ItemUnit>` |
| `POST /api/v1/item-units` | `sdk.itemUnits.create(payload)` | `ApiMessageDataResponse<ItemUnit>` |
| `PUT /api/v1/item-units/{id}` | `sdk.itemUnits.update(id, payload)` | `ApiMessageDataResponse<ItemUnit>` |
| `DELETE /api/v1/item-units/{id}` | `sdk.itemUnits.delete(id)` | `ApiMessageResponse` |
| `PATCH /api/v1/item-units/{id}/restore` | `sdk.itemUnits.restore(id)` | `ApiMessageDataResponse<ItemUnit>` |
| **Meal Times** | | |
| `GET /api/v1/meal-times` | `sdk.mealTimes.list(query?)` | `ApiListResponse<MealTime>` |
| **Menus** | | |
| `GET /api/v1/menus` | `sdk.menus.list()` | `MenusListResponse` |
| `GET /api/v1/menu-dishes` | `sdk.menus.slots()` | `MenuSlotsListResponse` |
| `POST /api/v1/menu-dishes` | `sdk.menus.assignSlot(payload)` | `ApiMessageDataResponse<MenuSlot>` |
| `PUT /api/v1/menu-dishes/{id}` | `sdk.menus.updateSlot(id, payload)` | `ApiMessageDataResponse<MenuSlot>` |
| `DELETE /api/v1/menu-dishes/{id}` | `sdk.menus.deleteSlot(id)` | `ApiMessageResponse` |
| **Menu Schedules** | | |
| `GET /api/v1/menu-schedules` | `sdk.menuSchedules.list()` | `MenuSchedulesListResponse` |
| `GET /api/v1/menu-schedules/{id}` | `sdk.menuSchedules.get(id)` | `ApiDataResponse<MenuSchedule>` |
| `POST /api/v1/menu-schedules` | `sdk.menuSchedules.create(payload)` | `MenuScheduleCreateResponse` |
| `PUT /api/v1/menu-schedules/{id}` | `sdk.menuSchedules.update(id, payload)` | `MenuScheduleCreateResponse` |
| `GET /api/v1/menu-calendar` | `sdk.menuSchedules.calendarProjection(query?)` | `MenuCalendarResponse` |
| **Notifications** | | |
| `GET /api/v1/notifications` | `sdk.notifications.list(query?)` | `ApiListResponse<Notification>` |
| `POST /api/v1/notifications/{id}/read` | `sdk.notifications.markAsRead(id)` | `ApiMessageResponse` |
| `POST /api/v1/notifications/read-all` | `sdk.notifications.markAllAsRead()` | `ApiMessageResponse` |
| `DELETE /api/v1/notifications/{id}` | `sdk.notifications.delete(id)` | `ApiMessageResponse` |
| `DELETE /api/v1/notifications` | `sdk.notifications.deleteAll()` | `ApiMessageResponse` |
| **Reports** | | |
| `GET /api/v1/reports/stocks` | `sdk.reports.getStocks(params)` | `ReportResponse` |
| `GET /api/v1/reports/transactions` | `sdk.reports.getTransactions(params)` | `ReportResponse` |
| `GET /api/v1/reports/spk-history` | `sdk.reports.getSpkHistory(params)` | `ReportResponse` |
| `GET /api/v1/reports/evaluation` | `sdk.reports.getEvaluation(params)` | `ReportResponse` |
| `GET /api/v1/reports/monthly-stock-export` | `sdk.reports.getMonthlyStockExport(params)` | `ReportResponse` |
| **Roles** | | |
| `GET /api/v1/roles` | `sdk.roles.list(query?)` | `ApiListResponse<Role>` |
| **SPK** | | |
| `GET /api/v1/spk/basah/menu-calendar` | `sdk.spk.basahMenuCalendar(query?)` | `SpkMenuCalendarResponse` |
| `POST /api/v1/spk/basah/operational-stock-preview` | `sdk.spk.operationalStockPreview(payload)` | `OperationalStockPreviewResponse` |
| `POST /api/v1/spk/basah/generate` | `sdk.spk.generateBasah(payload)` | `SpkBasahGenerateResponse` |
| `GET /api/v1/spk/basah/history` | `sdk.spk.listBasah()` | `SpkBasahHistoryListResponse` |
| `GET /api/v1/spk/basah/history/{id}` | `sdk.spk.getBasah(id)` | `SpkBasahDetailResponse` |
| `POST /api/v1/spk/basah/history/{id}/override` | `sdk.spk.overrideBasah(id, payload)` | `SpkOverrideResponse` |
| `POST /api/v1/spk/basah/history/{id}/post-stock` | `sdk.spk.postBasahStock(id)` | `SpkPostStockResponse` |
| `GET /api/v1/spk/kering-pengemas/menu-calendar` | `sdk.spk.keringPengemasMenuCalendar(query?)` | `SpkMenuCalendarResponse` |
| `POST /api/v1/spk/kering-pengemas/generate` | `sdk.spk.generateKeringPengemas(payload)` | `SpkKeringPengemasGenerateResponse` |
| `GET /api/v1/spk/kering-pengemas/history` | `sdk.spk.listKeringPengemas()` | `SpkKeringPengemasHistoryListResponse` |
| `GET /api/v1/spk/kering-pengemas/history/{id}` | `sdk.spk.getKeringPengemas(id)` | `SpkKeringPengemasDetailResponse` |
| `POST /api/v1/spk/kering-pengemas/history/{id}/override` | `sdk.spk.overrideKeringPengemas(id, payload)` | `SpkOverrideResponse` |
| `POST /api/v1/spk/kering-pengemas/history/{id}/post-stock` | `sdk.spk.postKeringPengemasStock(id)` | `SpkPostStockResponse` |
| `GET /api/v1/spk/stock-in-prefill/{id}` | `sdk.spk.stockInPrefill(id)` | `SpkStockInPrefillResponse` |
 | **Stock Opnames** | | |
 | `POST /api/v1/stock-opnames` | `sdk.stockOpnames.create(request)` | `StockOpnameActionResponse` |
 | `GET /api/v1/stock-opnames` | `sdk.stockOpnames.list(query?)` | `ApiListResponse<StockOpnameHeader>` |
 | `GET /api/v1/stock-opnames/{id}` | `sdk.stockOpnames.get(id)` | `StockOpnameResponse` |
 | `PUT /api/v1/stock-opnames/{id}` | `sdk.stockOpnames.update(id, request)` | `StockOpnameActionResponse` |
 | `POST /api/v1/stock-opnames/{id}/submit` | `sdk.stockOpnames.submit(id)` | `StockOpnameActionResponse` |
 | `POST /api/v1/stock-opnames/{id}/approve` | `sdk.stockOpnames.approve(id)` | `StockOpnameActionResponse` |
 | `POST /api/v1/stock-opnames/{id}/reject` | `sdk.stockOpnames.reject(id, request)` | `StockOpnameActionResponse` |
 | `POST /api/v1/stock-opnames/{id}/post` | `sdk.stockOpnames.post(id)` | `StockOpnameActionResponse` |
 | **Stock Snapshots** | | |
 | `GET /api/v1/stock-snapshots` | `sdk.stockSnapshots.list(query?)` | `ApiListResponse<StockSnapshotRow>` |
 | `POST /api/v1/stock-snapshots/take` | `sdk.stockSnapshots.take(request?)` | `CreateSnapshotResponse` |
 | `GET /api/v1/stock-snapshots/current` | `sdk.stockSnapshots.current()` | `CurrentSnapshotStatus` |
| `GET /api/v1/stock-transactions` | `sdk.stockTransactions.list(query?)` | `ApiListResponse<StockTransaction>` |
| `GET /api/v1/stock-transactions/{id}` | `sdk.stockTransactions.get(id)` | `ApiDataResponse<StockTransaction>` |
| `GET /api/v1/stock-transactions/{id}/details` | `sdk.stockTransactions.details(id)` | `ApiDataResponse<StockTransactionDetail[]>` |
| `POST /api/v1/stock-transactions` | `sdk.stockTransactions.create(payload)` | `ApiMessageDataResponse<StockTransactionCreateResult>` |
| `POST /api/v1/stock-transactions/direct-corrections` | `sdk.stockTransactions.directCorrection(payload)` | `ApiMessageDataResponse<StockTransactionCreateResult>` |
| `POST /api/v1/stock-transactions/{id}/submit-revision` | `sdk.stockTransactions.submitRevision(id, payload)` | `ApiMessageDataResponse<StockTransactionRevisionResult>` |
| `POST /api/v1/stock-transactions/{id}/approve` | `sdk.stockTransactions.approve(id)` | `ApiMessageDataResponse<StockTransactionModerationResult>` |
| `POST /api/v1/stock-transactions/{id}/reject` | `sdk.stockTransactions.reject(id, payload?)` | `ApiMessageDataResponse<StockTransactionModerationResult>` |
 | `PUT /api/v1/stock-transactions/{id}` | `sdk.stockTransactions.updateDraft(id, payload)` | `ApiMessageDataResponse<StockTransactionCreateResult>` |
 | `POST /api/v1/stock-transactions/{id}/submit` | `sdk.stockTransactions.submitDraft(id)` | `ApiMessageDataResponse<StockTransactionCreateResult>` |
 | `POST /api/v1/stock-transactions/{id}/cancel` | `sdk.stockTransactions.cancelDraft(id)` | `ApiMessageDataResponse<StockTransactionCreateResult>` |
| **Transaction Types** | | |
| `GET /api/v1/transaction-types` | `sdk.transactionTypes.list(query?)` | `ApiListResponse<TransactionType>` |
| **Users** | | |
| `GET /api/v1/users` | `sdk.users.list(query?)` | `ApiListResponse<User>` |
| `GET /api/v1/users/{id}` | `sdk.users.get(id)` | `ApiDataResponse<User>` |
| `POST /api/v1/users` | `sdk.users.create(payload)` | `ApiMessageDataResponse<User>` |
| `PUT /api/v1/users/{id}` | `sdk.users.update(id, payload)` | `ApiMessageDataResponse<User>` |
| `PATCH /api/v1/users/{id}/activate` | `sdk.users.activate(id)` | `ApiMessageDataResponse<User>` |
| `PATCH /api/v1/users/{id}/deactivate` | `sdk.users.deactivate(id)` | `ApiMessageDataResponse<User>` |
| `PATCH /api/v1/users/{id}/password` | `sdk.users.changePassword(id, payload)` | `ApiMessageResponse` |
| `DELETE /api/v1/users/{id}` | `sdk.users.delete(id)` | `ApiMessageResponse` |
| `PATCH /api/v1/users/{id}/restore` | `sdk.users.restore(id)` | `ApiMessageDataResponse<User>` |
