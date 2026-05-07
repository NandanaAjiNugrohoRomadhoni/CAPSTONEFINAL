// src/sdk/errors.ts
var ApiError = class extends Error {
  status;
  body;
  constructor(message, status, body) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.body = body;
  }
};
var ValidationApiError = class extends ApiError {
  constructor(body, status = 400) {
    super(body.message, status, body);
    this.name = "ValidationApiError";
  }
  get errors() {
    return this.body?.errors ?? {};
  }
};
var AuthenticationApiError = class extends ApiError {
  constructor(body, status = 401) {
    super(body?.message ?? "Authentication failed.", status, body);
    this.name = "AuthenticationApiError";
  }
};
var AuthorizationApiError = class extends ApiError {
  constructor(body, status = 403) {
    super(body?.message ?? "Authorization failed.", status, body);
    this.name = "AuthorizationApiError";
  }
};
var NotFoundApiError = class extends ApiError {
  constructor(body, status = 404) {
    super(body?.message ?? "Resource not found.", status, body);
    this.name = "NotFoundApiError";
  }
};
function toApiError(status, body) {
  const normalized = isApiErrorResponse(body) ? body : null;
  if (status === 400 && isValidationErrorResponse(body)) {
    return new ValidationApiError(body, status);
  }
  if (status === 401) {
    return new AuthenticationApiError(normalized, status);
  }
  if (status === 403) {
    return new AuthorizationApiError(normalized, status);
  }
  if (status === 404) {
    return new NotFoundApiError(normalized, status);
  }
  return new ApiError(normalized?.message ?? `Request failed with status ${status}.`, status, normalized);
}
function isApiErrorResponse(value) {
  return typeof value === "object" && value !== null && "message" in value && typeof value.message === "string";
}
function isValidationErrorResponse(value) {
  return typeof value === "object" && value !== null && "message" in value && typeof value.message === "string" && "errors" in value && typeof value.errors === "object" && value.errors !== null && !Array.isArray(value.errors);
}

// src/sdk/client.ts
var ApiClient = class {
  baseUrl;
  apiBasePath;
  defaultHeaders;
  fetchImplementation;
  getAccessToken;
  accessToken;
  constructor(options = {}) {
    this.baseUrl = trimTrailingSlash(options.baseUrl ?? "http://127.0.0.1:8080");
    this.apiBasePath = ensureLeadingSlash(trimTrailingSlash(options.apiBasePath ?? "/api/v1"));
    this.defaultHeaders = options.defaultHeaders ?? {};
    this.fetchImplementation = options.fetchImplementation ?? globalThis.fetch;
    this.getAccessToken = options.getAccessToken;
    this.accessToken = options.accessToken ?? null;
    if (typeof this.fetchImplementation !== "function") {
      throw new Error("A fetch implementation is required to use the API client.");
    }
  }
  setAccessToken(token) {
    this.accessToken = token;
  }
  clearAccessToken() {
    this.accessToken = null;
  }
  async request(options) {
    const headers = new Headers(this.defaultHeaders);
    for (const [key, value] of new Headers(options.headers).entries()) {
      headers.set(key, value);
    }
    headers.set("Accept", "application/json");
    const token = await this.resolveAccessToken();
    if (token) {
      headers.set("Authorization", `Bearer ${token}`);
    }
    let body;
    if (options.body !== void 0) {
      headers.set("Content-Type", "application/json");
      body = JSON.stringify(options.body);
    }
    const requestInit = {
      method: options.method ?? "GET",
      headers
    };
    if (body !== void 0) {
      requestInit.body = body;
    }
    const response = await this.fetchImplementation(this.buildUrl(options.path, options.query), requestInit);
    const payload = await parseResponse(response);
    if (!response.ok) {
      throw toApiError(response.status, payload);
    }
    return payload;
  }
  buildUrl(path, query) {
    const normalizedPath = ensureLeadingSlash(path);
    const url = new URL(`${this.baseUrl}${this.apiBasePath}${normalizedPath}`);
    if (query) {
      for (const [key, value] of Object.entries(query)) {
        if (value === void 0 || value === null) {
          continue;
        }
        url.searchParams.set(key, String(value));
      }
    }
    return url.toString();
  }
  async resolveAccessToken() {
    const dynamicToken = await this.getAccessToken?.();
    if (dynamicToken !== void 0) {
      return dynamicToken ?? null;
    }
    return this.accessToken;
  }
};
async function parseResponse(response) {
  if (response.status === 204) {
    return void 0;
  }
  const contentType = response.headers.get("content-type") ?? "";
  if (contentType.includes("application/json")) {
    return response.json();
  }
  const text = await response.text();
  return text.length > 0 ? text : void 0;
}
function trimTrailingSlash(value) {
  return value.replace(/\/+$/, "");
}
function ensureLeadingSlash(value) {
  return value.startsWith("/") ? value : `/${value}`;
}

// src/sdk/resources/auth.ts
var AuthResource = class {
  constructor(client) {
    this.client = client;
  }
  client;
  /**
   * Logs a user in with username and password.
   *
   * @endpoint POST /api/v1/auth/login
   * @access   public
   * @param payload - Required fields: `username`, `password`.
   * @returns {Promise<LoginResponse>}
   * @throws {ValidationApiError} if required credentials are missing or invalid (400)
   * @throws {AuthenticationApiError} if credentials are rejected (401)
   * @sideeffect Issues a new Bearer access token on success.
   */
  login(payload) {
    return this.client.request({
      method: "POST",
      path: "/auth/login",
      body: payload
    });
  }
  /**
   * Returns the current authenticated user profile from the Bearer token.
   *
   * @endpoint GET /api/v1/auth/me
   * @access   authenticated
   * @returns {Promise<ApiDataResponse<User>>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @sideeffect None
   */
  me() {
    return this.client.request({
      method: "GET",
      path: "/auth/me"
    });
  }
  /**
   * Logs out the current Bearer token.
   *
   * @endpoint POST /api/v1/auth/logout
   * @access   authenticated
   * @returns {Promise<ApiMessageResponse>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @sideeffect Revokes the current access token.
   */
  logout() {
    return this.client.request({
      method: "POST",
      path: "/auth/logout"
    });
  }
  /**
   * Changes the current authenticated user's password.
   *
   * @endpoint PATCH /api/v1/auth/password
   * @access   authenticated
   * @param payload - Required fields: `current_password`, `password`.
   * @returns {Promise<ApiMessageResponse>}
   * @throws {ValidationApiError} if validation fails or the current password is wrong (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @sideeffect Revokes all access tokens for the current user after a successful password change.
   */
  changePassword(payload) {
    return this.client.request({
      method: "PATCH",
      path: "/auth/password",
      body: payload
    });
  }
};

// src/sdk/resources/approvalStatuses.ts
var ApprovalStatusesResource = class {
  constructor(client) {
    this.client = client;
  }
  client;
  /**
   * Lists approval statuses with pagination, filtering, and optional full lookup reads.
   *
   * @endpoint GET /api/v1/approval-statuses
   * @access   admin | gudang
   *
   * @param query - Supports `paginate`, `page`, `perPage`, `q`/`search` (`q` wins), `sortBy`, `sortDir`, `created_at_from/to`, and `updated_at_from/to`. Unknown params return 400. Soft-deleted rows are excluded. `paginate=false` keeps the same envelope and sets `meta.paginated=false`.
   * @returns {Promise<ApiListResponse<ApprovalStatus>>}
   *
   * @throws {ValidationApiError} if query validation fails (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   *
   * @sideeffect None
   */
  list(query) {
    return this.client.request({
      method: "GET",
      path: "/approval-statuses",
      ...query ? { query: buildLookupQuery(query) } : {}
    });
  }
};
function buildLookupQuery(query) {
  const result = {};
  if (query.paginate !== void 0) result.paginate = query.paginate ? "true" : "false";
  if (query.page !== void 0) result.page = query.page;
  if (query.perPage !== void 0) result.perPage = query.perPage;
  if (query.q !== void 0) result.q = query.q;
  if (query.search !== void 0) result.search = query.search;
  if (query.sortBy !== void 0) result.sortBy = query.sortBy;
  if (query.sortDir !== void 0) result.sortDir = query.sortDir;
  if (query.created_at_from !== void 0) result.created_at_from = query.created_at_from;
  if (query.created_at_to !== void 0) result.created_at_to = query.created_at_to;
  if (query.updated_at_from !== void 0) result.updated_at_from = query.updated_at_from;
  if (query.updated_at_to !== void 0) result.updated_at_to = query.updated_at_to;
  return result;
}

// src/sdk/resources/dailyPatients.ts
var DailyPatientsResource = class {
  constructor(client) {
    this.client = client;
  }
  client;
  /**
   * Lists daily patient rows.
   *
   * @endpoint GET /api/v1/daily-patients
   * @access   admin | dapur | gudang
   * @returns {Promise<DailyPatientsListResponse>} Standard `data[]/meta/links` envelope.
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect None
   */
  list() {
    return this.client.request({
      method: "GET",
      path: "/daily-patients"
    });
  }
  /**
   * Returns one daily patient row.
   *
   * @endpoint GET /api/v1/daily-patients/{service_date}
   * @access   admin | dapur | gudang
   * @returns {Promise<DailyPatientResponse>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {ValidationApiError} if `service_date` path format is invalid (400)
   * @throws {NotFoundApiError} if the row does not exist for the given service date (404)
   * @sideeffect None
   */
  get(serviceDate) {
    return this.client.request({
      method: "GET",
      path: `/daily-patients/${serviceDate}`
    });
  }
  /**
   * Creates a daily patient row.
   *
   * @endpoint POST /api/v1/daily-patients
   * @access   admin | dapur
   * @param payload - Writable fields: `service_date`, `total_patients`, and optional `notes`. `service_date` must remain unique.
   * @returns {Promise<DailyPatientCreateResponse>}
   * @throws {ValidationApiError} if validation fails or the service date already exists (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect Creates a new immutable audit row; no update/delete endpoint exists.
   */
  create(payload) {
    return this.client.request({
      method: "POST",
      path: "/daily-patients",
      body: payload
    });
  }
};

// src/sdk/resources/dishes.ts
var DishesResource = class {
  constructor(client) {
    this.client = client;
  }
  client;
  /** @endpoint GET /api/v1/dishes @access admin | gudang | dapur @param query - Supports standard list pagination, search, sorting, and created/updated date ranges. @returns {Promise<DishesListResponse>} @throws {ValidationApiError} if query validation fails (400) @throws {AuthenticationApiError} if no valid Bearer token is provided (401) @throws {AuthorizationApiError} if the caller lacks the required role (403) @sideeffect None */
  list(query) {
    return this.client.request({
      method: "GET",
      path: "/dishes",
      ...query ? { query: buildDishesQuery(query) } : {}
    });
  }
  /** @endpoint GET /api/v1/dishes/{id} @access admin | gudang | dapur @returns {Promise<ApiDataResponse<Dish>>} @throws {AuthenticationApiError} if no valid Bearer token is provided (401) @throws {AuthorizationApiError} if the caller lacks the required role (403) @throws {NotFoundApiError} if the dish does not exist (404) @sideeffect None */
  get(id) {
    return this.client.request({
      method: "GET",
      path: `/dishes/${id}`
    });
  }
  /** @endpoint POST /api/v1/dishes @access admin | dapur @returns {Promise<ApiMessageDataResponse<Dish>>} @throws {ValidationApiError} if validation fails (400) @throws {AuthenticationApiError} if no valid Bearer token is provided (401) @throws {AuthorizationApiError} if the caller lacks the required role (403) @sideeffect Creates a dish row. */
  create(payload) {
    return this.client.request({
      method: "POST",
      path: "/dishes",
      body: payload
    });
  }
  /** @endpoint PUT /api/v1/dishes/{id} @access admin | dapur @returns {Promise<ApiMessageDataResponse<Dish>>} @throws {ValidationApiError} if validation fails (400) @throws {AuthenticationApiError} if no valid Bearer token is provided (401) @throws {AuthorizationApiError} if the caller lacks the required role (403) @throws {NotFoundApiError} if the dish does not exist (404) @sideeffect Updates a dish row. */
  update(id, payload) {
    return this.client.request({
      method: "PUT",
      path: `/dishes/${id}`,
      body: payload
    });
  }
  /** @endpoint DELETE /api/v1/dishes/{id} @access admin | dapur @returns {Promise<ApiMessageResponse>} @throws {AuthenticationApiError} if no valid Bearer token is provided (401) @throws {AuthorizationApiError} if the caller lacks the required role (403) @throws {NotFoundApiError} if the dish does not exist (404) @sideeffect Permanently deletes the dish row. */
  delete(id) {
    return this.client.request({
      method: "DELETE",
      path: `/dishes/${id}`
    });
  }
};
function buildDishesQuery(query) {
  const result = {};
  if (query.page !== void 0) result.page = query.page;
  if (query.perPage !== void 0) result.perPage = query.perPage;
  if (query.q !== void 0) result.q = query.q;
  if (query.search !== void 0) result.search = query.search;
  if (query.sortBy !== void 0) result.sortBy = query.sortBy;
  if (query.sortDir !== void 0) result.sortDir = query.sortDir;
  if (query.created_at_from !== void 0) result.created_at_from = query.created_at_from;
  if (query.created_at_to !== void 0) result.created_at_to = query.created_at_to;
  if (query.updated_at_from !== void 0) result.updated_at_from = query.updated_at_from;
  if (query.updated_at_to !== void 0) result.updated_at_to = query.updated_at_to;
  return result;
}

// src/sdk/resources/dishCompositions.ts
var DishCompositionsResource = class {
  constructor(client) {
    this.client = client;
  }
  client;
  /** @endpoint GET /api/v1/dish-compositions @access admin | gudang | dapur @param query - Supports standard list pagination, `dish_id`, `item_id`, search, sorting, and created/updated date ranges. @returns {Promise<DishCompositionsListResponse>} @throws {ValidationApiError} if query validation fails (400) @throws {AuthenticationApiError} if no valid Bearer token is provided (401) @throws {AuthorizationApiError} if the caller lacks the required role (403) @sideeffect None */
  list(query) {
    return this.client.request({
      method: "GET",
      path: "/dish-compositions",
      ...query ? { query: buildDishCompositionsQuery(query) } : {}
    });
  }
  /** @endpoint GET /api/v1/dish-compositions/{id} @access admin | gudang | dapur @returns {Promise<ApiDataResponse<DishComposition>>} @throws {AuthenticationApiError} if no valid Bearer token is provided (401) @throws {AuthorizationApiError} if the caller lacks the required role (403) @throws {NotFoundApiError} if the row does not exist (404) @sideeffect None */
  get(id) {
    return this.client.request({
      method: "GET",
      path: `/dish-compositions/${id}`
    });
  }
  /** @endpoint POST /api/v1/dish-compositions @access admin | dapur @returns {Promise<ApiMessageDataResponse<DishComposition>>} @throws {ValidationApiError} if validation fails or a dish/item pair already exists (400) @throws {AuthenticationApiError} if no valid Bearer token is provided (401) @throws {AuthorizationApiError} if the caller lacks the required role (403) @sideeffect Creates a composition row. */
  create(payload) {
    return this.client.request({
      method: "POST",
      path: "/dish-compositions",
      body: payload
    });
  }
  /** @endpoint PUT /api/v1/dish-compositions/{id} @access admin | dapur @returns {Promise<ApiMessageDataResponse<DishComposition>>} @throws {ValidationApiError} if validation fails or uniqueness rules fail (400) @throws {AuthenticationApiError} if no valid Bearer token is provided (401) @throws {AuthorizationApiError} if the caller lacks the required role (403) @throws {NotFoundApiError} if the row does not exist (404) @sideeffect Updates a composition row. */
  update(id, payload) {
    return this.client.request({
      method: "PUT",
      path: `/dish-compositions/${id}`,
      body: payload
    });
  }
  /** @endpoint DELETE /api/v1/dish-compositions/{id} @access admin | dapur @returns {Promise<ApiMessageResponse>} @throws {AuthenticationApiError} if no valid Bearer token is provided (401) @throws {AuthorizationApiError} if the caller lacks the required role (403) @throws {NotFoundApiError} if the row does not exist (404) @sideeffect Permanently deletes the composition row. */
  delete(id) {
    return this.client.request({
      method: "DELETE",
      path: `/dish-compositions/${id}`
    });
  }
};
function buildDishCompositionsQuery(query) {
  const result = {};
  if (query.page !== void 0) result.page = query.page;
  if (query.perPage !== void 0) result.perPage = query.perPage;
  if (query.dish_id !== void 0) result.dish_id = query.dish_id;
  if (query.item_id !== void 0) result.item_id = query.item_id;
  if (query.q !== void 0) result.q = query.q;
  if (query.search !== void 0) result.search = query.search;
  if (query.sortBy !== void 0) result.sortBy = query.sortBy;
  if (query.sortDir !== void 0) result.sortDir = query.sortDir;
  if (query.created_at_from !== void 0) result.created_at_from = query.created_at_from;
  if (query.created_at_to !== void 0) result.created_at_to = query.created_at_to;
  if (query.updated_at_from !== void 0) result.updated_at_from = query.updated_at_from;
  if (query.updated_at_to !== void 0) result.updated_at_to = query.updated_at_to;
  return result;
}

// src/sdk/resources/itemCategories.ts
var ItemCategoriesResource = class {
  constructor(client) {
    this.client = client;
  }
  client;
  /**
   * Lists item categories with pagination, filtering, and optional full lookup reads.
   *
   * @endpoint GET /api/v1/item-categories
   * @access   admin | gudang
   * @param query - Supports `paginate`, `page`, `perPage`, `q`/`search` (`q` wins), `sortBy`, `sortDir`, `created_at_from/to`, and `updated_at_from/to`. Unknown params return 400. Soft-deleted rows are excluded. `paginate=false` keeps the same envelope and sets `meta.paginated=false`.
   * @returns {Promise<ApiListResponse<ItemCategory>>}
   * @throws {ValidationApiError} if query validation fails (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect None
   */
  list(query) {
    return this.client.request({
      method: "GET",
      path: "/item-categories",
      ...query ? { query: buildLookupQuery2(query) } : {}
    });
  }
  /**
   * Returns one active item category.
   *
   * @endpoint GET /api/v1/item-categories/{id}
   * @access   admin | gudang
   * @returns {Promise<ApiDataResponse<ItemCategory>>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the category does not exist or is soft-deleted (404)
   * @sideeffect None
   */
  get(id) {
    return this.client.request({
      method: "GET",
      path: `/item-categories/${id}`
    });
  }
  /**
   * Creates an item category.
   *
   * @endpoint POST /api/v1/item-categories
   * @access   admin
   * @param payload - Writable fields: `name`. Name uniqueness applies to active rows only; if a deleted-name collision exists, backend returns restore guidance with `errors.restore_id`.
   * @returns {Promise<ApiMessageDataResponse<ItemCategory>>}
   * @throws {ValidationApiError} if validation fails or the name conflicts (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect None
   */
  create(payload) {
    return this.client.request({
      method: "POST",
      path: "/item-categories",
      body: payload
    });
  }
  /**
   * Updates an active item category.
   *
   * @endpoint PUT /api/v1/item-categories/{id}
   * @access   admin
   * @param payload - Writable fields: `name`. Active-only uniqueness rules still apply.
   * @returns {Promise<ApiMessageDataResponse<ItemCategory>>}
   * @throws {ValidationApiError} if validation fails or the name conflicts (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the category does not exist or is soft-deleted (404)
   * @sideeffect None
   */
  update(id, payload) {
    return this.client.request({
      method: "PUT",
      path: `/item-categories/${id}`,
      body: payload
    });
  }
  /**
   * Soft-deletes an item category.
   *
   * @endpoint DELETE /api/v1/item-categories/{id}
   * @access   admin
   * @returns {Promise<ApiMessageResponse>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the category does not exist or is already soft-deleted (404)
   * @sideeffect Sets `deleted_at`; the row remains restorable.
   */
  delete(id) {
    return this.client.request({
      method: "DELETE",
      path: `/item-categories/${id}`
    });
  }
  /**
   * Restores a soft-deleted item category.
   *
   * @endpoint PATCH /api/v1/item-categories/{id}/restore
   * @access   admin
   * @returns {Promise<ApiMessageDataResponse<ItemCategory>>}
   * @throws {ValidationApiError} if restore fails because an active row already owns the name (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the category does not exist (404)
   * @sideeffect Clears `deleted_at` when restore succeeds.
   */
  restore(id) {
    return this.client.request({
      method: "PATCH",
      path: `/item-categories/${id}/restore`
    });
  }
};
function buildLookupQuery2(query) {
  const result = {};
  if (query.paginate !== void 0) result.paginate = query.paginate ? "true" : "false";
  if (query.page !== void 0) result.page = query.page;
  if (query.perPage !== void 0) result.perPage = query.perPage;
  if (query.q !== void 0) result.q = query.q;
  if (query.search !== void 0) result.search = query.search;
  if (query.sortBy !== void 0) result.sortBy = query.sortBy;
  if (query.sortDir !== void 0) result.sortDir = query.sortDir;
  if (query.created_at_from !== void 0) result.created_at_from = query.created_at_from;
  if (query.created_at_to !== void 0) result.created_at_to = query.created_at_to;
  if (query.updated_at_from !== void 0) result.updated_at_from = query.updated_at_from;
  if (query.updated_at_to !== void 0) result.updated_at_to = query.updated_at_to;
  return result;
}

// src/sdk/resources/items.ts
var ItemsResource = class {
  constructor(client) {
    this.client = client;
  }
  client;
  /**
   * Lists active items with pagination, filtering, and search.
   *
   * @endpoint GET /api/v1/items
   * @access   admin | gudang
   * @param query - Supports `page`, `perPage`, `item_category_id`, `is_active`, `q`/`search` (`q` wins), `sortBy`, `sortDir`, `created_at_from/to`, and `updated_at_from/to`. Unknown params return 400. Soft-deleted items are excluded.
   * @returns {Promise<ApiListResponse<Item>>}
   * @throws {ValidationApiError} if query validation fails (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect None
   */
  list(query) {
    return this.client.request({
      method: "GET",
      path: "/items",
      ...query ? { query: buildItemsQuery(query) } : {}
    });
  }
  /**
   * Returns one active item.
   *
   * @endpoint GET /api/v1/items/{id}
   * @access   admin | gudang
   * @returns {Promise<ApiDataResponse<Item>>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the item does not exist or is soft-deleted (404)
   * @sideeffect None
   */
  get(id) {
    return this.client.request({
      method: "GET",
      path: `/items/${id}`
    });
  }
  /**
   * Creates an item.
   *
   * @endpoint POST /api/v1/items
   * @access   admin | gudang
   * @param payload - Writable fields: `name`, `unit_base`, `unit_convert`, `conversion_base`, `min_stock`, `is_active`, and exactly one of `item_category_id` or `item_category_name`. `unit_base` and `unit_convert` resolve case-insensitively to active `item_units` rows and are still persisted as strings for backward compatibility. `qty`, `id`, and timestamps are backend-managed.
   * @returns {Promise<ApiMessageDataResponse<Item>>}
   * @throws {ValidationApiError} if validation fails, both category fields are sent, units are inactive/missing, or a deleted-name collision requires restore guidance (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect None; stock is not mutated here and `qty` remains backend-controlled.
   */
  create(payload) {
    return this.client.request({
      method: "POST",
      path: "/items",
      body: payload
    });
  }
  /**
   * Updates an item using the backend's partial-update semantics.
   *
   * @endpoint PUT /api/v1/items/{id}
   * @access   admin | gudang
   * @param payload - Partial update. When changing category, send exactly one of `item_category_id` or `item_category_name`. If `unit_base` or `unit_convert` is sent, each must resolve to an active item unit.
   * @returns {Promise<ApiMessageDataResponse<Item>>}
   * @throws {ValidationApiError} if validation fails, both category fields are sent, or unit/category constraints fail (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the item does not exist or is soft-deleted (404)
   * @sideeffect None; stock is not mutated here and `qty` remains backend-controlled.
   */
  update(id, payload) {
    return this.client.request({
      method: "PUT",
      path: `/items/${id}`,
      body: payload
    });
  }
  /**
   * Soft-deletes an item.
   *
   * @endpoint DELETE /api/v1/items/{id}
   * @access   admin
   * @returns {Promise<ApiMessageResponse>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the item does not exist or is already soft-deleted (404)
   * @sideeffect Sets `deleted_at`; the row remains restorable.
   */
  delete(id) {
    return this.client.request({
      method: "DELETE",
      path: `/items/${id}`
    });
  }
  /**
   * Restores a soft-deleted item.
   *
   * @endpoint PATCH /api/v1/items/{id}/restore
   * @access   admin
   * @returns {Promise<ApiMessageDataResponse<Item>>}
   * @throws {ValidationApiError} if an active item already owns the name or the referenced category/units are inactive (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the item does not exist (404)
   * @sideeffect Clears `deleted_at`. If the item is already active, backend returns the current resource idempotently.
   */
  restore(id) {
    return this.client.request({
      method: "PATCH",
      path: `/items/${id}/restore`
    });
  }
};
function buildItemsQuery(query) {
  const result = {};
  if (query.page !== void 0) {
    result.page = query.page;
  }
  if (query.perPage !== void 0) {
    result.perPage = query.perPage;
  }
  if (query.item_category_id !== void 0) {
    result.item_category_id = query.item_category_id;
  }
  if (query.is_active !== void 0) {
    result.is_active = query.is_active;
  }
  if (query.q !== void 0) {
    result.q = query.q;
  }
  if (query.search !== void 0) {
    result.search = query.search;
  }
  if (query.sortBy !== void 0) {
    result.sortBy = query.sortBy;
  }
  if (query.sortDir !== void 0) {
    result.sortDir = query.sortDir;
  }
  if (query.created_at_from !== void 0) {
    result.created_at_from = query.created_at_from;
  }
  if (query.created_at_to !== void 0) {
    result.created_at_to = query.created_at_to;
  }
  if (query.updated_at_from !== void 0) {
    result.updated_at_from = query.updated_at_from;
  }
  if (query.updated_at_to !== void 0) {
    result.updated_at_to = query.updated_at_to;
  }
  return result;
}

// src/sdk/resources/itemUnits.ts
var ItemUnitsResource = class {
  constructor(client) {
    this.client = client;
  }
  client;
  /**
   * Lists item units with pagination, filtering, and optional full lookup reads.
   *
   * @endpoint GET /api/v1/item-units
   * @access   admin | gudang
   * @param query - Supports `paginate`, `page`, `perPage`, `q`/`search` (`q` wins), `sortBy`, `sortDir`, `created_at_from/to`, and `updated_at_from/to`. Unknown params return 400. Soft-deleted rows are excluded. `paginate=false` keeps the same envelope and sets `meta.paginated=false`.
   * @returns {Promise<ApiListResponse<ItemUnit>>}
   * @throws {ValidationApiError} if query validation fails (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect None
   */
  list(query) {
    return this.client.request({
      method: "GET",
      path: "/item-units",
      ...query ? { query: buildLookupQuery3(query) } : {}
    });
  }
  /**
   * Returns one active item unit.
   *
   * @endpoint GET /api/v1/item-units/{id}
   * @access   admin | gudang
   * @returns {Promise<ApiDataResponse<ItemUnit>>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the unit does not exist or is soft-deleted (404)
   * @sideeffect None
   */
  get(id) {
    return this.client.request({
      method: "GET",
      path: `/item-units/${id}`
    });
  }
  /**
   * Creates an item unit.
   *
   * @endpoint POST /api/v1/item-units
   * @access   admin
   * @param payload - Writable fields: `name`. Name uniqueness applies to active rows only; if a deleted-name collision exists, backend returns restore guidance with `errors.restore_id`.
   * @returns {Promise<ApiMessageDataResponse<ItemUnit>>}
   * @throws {ValidationApiError} if validation fails or the name conflicts (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect None
   */
  create(payload) {
    return this.client.request({
      method: "POST",
      path: "/item-units",
      body: payload
    });
  }
  /**
   * Updates an active item unit.
   *
   * @endpoint PUT /api/v1/item-units/{id}
   * @access   admin
   * @param payload - Writable fields: `name`. Active-only uniqueness rules still apply.
   * @returns {Promise<ApiMessageDataResponse<ItemUnit>>}
   * @throws {ValidationApiError} if validation fails or the name conflicts (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the unit does not exist or is soft-deleted (404)
   * @sideeffect None
   */
  update(id, payload) {
    return this.client.request({
      method: "PUT",
      path: `/item-units/${id}`,
      body: payload
    });
  }
  /**
   * Soft-deletes an item unit.
   *
   * @endpoint DELETE /api/v1/item-units/{id}
   * @access   admin
   * @returns {Promise<ApiMessageResponse>}
   * @throws {ValidationApiError} if active items still reference the unit (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the unit does not exist or is already soft-deleted (404)
   * @sideeffect Sets `deleted_at`; the row remains restorable.
   */
  delete(id) {
    return this.client.request({
      method: "DELETE",
      path: `/item-units/${id}`
    });
  }
  /**
   * Restores a soft-deleted item unit.
   *
   * @endpoint PATCH /api/v1/item-units/{id}/restore
   * @access   admin
   * @returns {Promise<ApiMessageDataResponse<ItemUnit>>}
   * @throws {ValidationApiError} if restore fails because an active row already owns the name (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the unit does not exist (404)
   * @sideeffect Clears `deleted_at` when restore succeeds.
   */
  restore(id) {
    return this.client.request({
      method: "PATCH",
      path: `/item-units/${id}/restore`
    });
  }
};
function buildLookupQuery3(query) {
  const result = {};
  if (query.paginate !== void 0) result.paginate = query.paginate ? "true" : "false";
  if (query.page !== void 0) result.page = query.page;
  if (query.perPage !== void 0) result.perPage = query.perPage;
  if (query.q !== void 0) result.q = query.q;
  if (query.search !== void 0) result.search = query.search;
  if (query.sortBy !== void 0) result.sortBy = query.sortBy;
  if (query.sortDir !== void 0) result.sortDir = query.sortDir;
  if (query.created_at_from !== void 0) result.created_at_from = query.created_at_from;
  if (query.created_at_to !== void 0) result.created_at_to = query.created_at_to;
  if (query.updated_at_from !== void 0) result.updated_at_from = query.updated_at_from;
  if (query.updated_at_to !== void 0) result.updated_at_to = query.updated_at_to;
  return result;
}

// src/sdk/resources/mealTimes.ts
var MealTimesResource = class {
  constructor(client) {
    this.client = client;
  }
  client;
  /**
   * Lists meal times with pagination, filtering, and optional full lookup reads.
   *
   * @endpoint GET /api/v1/meal-times
   * @access   admin | gudang
   *
   * @param query - Supports `paginate`, `page`, `perPage`, `q`/`search` (`q` wins), `sortBy`, `sortDir`, `created_at_from/to`, and `updated_at_from/to`. Unknown params return 400. `paginate=false` keeps the same envelope and sets `meta.paginated=false`.
   * @returns {Promise<ApiListResponse<MealTime>>}
   *
   * @throws {ValidationApiError} if query validation fails (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   *
   * @sideeffect None
   */
  list(query) {
    return this.client.request({
      method: "GET",
      path: "/meal-times",
      ...query ? { query: buildLookupQuery4(query) } : {}
    });
  }
};
function buildLookupQuery4(query) {
  const result = {};
  if (query.paginate !== void 0) result.paginate = query.paginate ? "true" : "false";
  if (query.page !== void 0) result.page = query.page;
  if (query.perPage !== void 0) result.perPage = query.perPage;
  if (query.q !== void 0) result.q = query.q;
  if (query.search !== void 0) result.search = query.search;
  if (query.sortBy !== void 0) result.sortBy = query.sortBy;
  if (query.sortDir !== void 0) result.sortDir = query.sortDir;
  if (query.created_at_from !== void 0) result.created_at_from = query.created_at_from;
  if (query.created_at_to !== void 0) result.created_at_to = query.created_at_to;
  if (query.updated_at_from !== void 0) result.updated_at_from = query.updated_at_from;
  if (query.updated_at_to !== void 0) result.updated_at_to = query.updated_at_to;
  return result;
}

// src/sdk/resources/menus.ts
var MenusResource = class {
  constructor(client) {
    this.client = client;
  }
  client;
  /**
   * Lists fixed menu package headers.
   *
   * @endpoint GET /api/v1/menus
   * @access   admin | gudang | dapur
   * @returns {Promise<MenusListResponse>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect None
   */
  list() {
    return this.client.request({
      method: "GET",
      path: "/menus"
    });
  }
  /**
   * Lists menu slot assignments.
   *
   * @endpoint GET /api/v1/menu-dishes
   * @access   admin | gudang | dapur
   * @returns {Promise<MenuSlotsListResponse>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect None
   */
  slots() {
    return this.client.request({
      method: "GET",
      path: "/menu-dishes"
    });
  }
  /**
   * Assigns a dish to a menu slot.
   *
   * @endpoint POST /api/v1/menu-dishes
   * @access   admin | dapur
   * @param payload - Writable fields: `menu_id`, `meal_time_id`, `dish_id`. Occupied slots are rejected; this is not an upsert endpoint.
   * @returns {Promise<ApiMessageDataResponse<MenuSlot>>}
   * @throws {ValidationApiError} if validation fails or the slot is already occupied (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect Creates a menu slot assignment.
   */
  assignSlot(payload) {
    return this.client.request({
      method: "POST",
      path: "/menu-dishes",
      body: payload
    });
  }
  /**
   * Updates a menu slot assignment.
   *
   * @endpoint PUT /api/v1/menu-dishes/{id}
   * @access   admin | dapur
   * @returns {Promise<ApiMessageDataResponse<MenuSlot>>}
   * @throws {ValidationApiError} if validation fails or the target slot conflicts (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the slot assignment does not exist (404)
   * @sideeffect Replaces slot assignment metadata.
   */
  updateSlot(id, payload) {
    return this.client.request({
      method: "PUT",
      path: `/menu-dishes/${id}`,
      body: payload
    });
  }
  /**
   * Deletes a menu slot assignment.
   *
   * @endpoint DELETE /api/v1/menu-dishes/{id}
   * @access   admin | dapur
   * @returns {Promise<ApiMessageResponse>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the slot assignment does not exist (404)
   * @sideeffect Permanently deletes the slot assignment.
   */
  deleteSlot(id) {
    return this.client.request({
      method: "DELETE",
      path: `/menu-dishes/${id}`
    });
  }
};

// src/sdk/resources/menuSchedules.ts
var MenuSchedulesResource = class {
  constructor(client) {
    this.client = client;
  }
  client;
  /**
   * Lists manual schedule overrides.
   *
   * @endpoint GET /api/v1/menu-schedules
   * @access   admin | gudang | dapur
   * @returns {Promise<MenuSchedulesListResponse>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect None
   */
  list() {
    return this.client.request({
      method: "GET",
      path: "/menu-schedules"
    });
  }
  /**
   * Returns one manual schedule override.
   *
   * @endpoint GET /api/v1/menu-schedules/{id}
   * @access   admin | gudang | dapur
   * @returns {Promise<ApiDataResponse<MenuSchedule>>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the schedule does not exist (404)
   * @sideeffect None
   */
  get(id) {
    return this.client.request({
      method: "GET",
      path: `/menu-schedules/${id}`
    });
  }
  /**
   * Creates a manual day-of-month override.
   *
   * @endpoint POST /api/v1/menu-schedules
   * @access   admin | dapur
   * @returns {Promise<MenuScheduleCreateResponse>}
   * @throws {ValidationApiError} if validation fails or the day-of-month override already exists (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect Creates one override row in `menu_schedules`.
   */
  create(payload) {
    return this.client.request({
      method: "POST",
      path: "/menu-schedules",
      body: payload
    });
  }
  /**
   * Updates a manual day-of-month override.
   *
   * @endpoint PUT /api/v1/menu-schedules/{id}
   * @access   admin | dapur
   * @returns {Promise<MenuScheduleCreateResponse>}
   * @throws {ValidationApiError} if validation fails or uniqueness rules fail (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the schedule does not exist (404)
   * @sideeffect Updates one override row in `menu_schedules`.
   */
  update(id, payload) {
    return this.client.request({
      method: "PUT",
      path: `/menu-schedules/${id}`,
      body: payload
    });
  }
  /**
   * Resolves the effective menu calendar.
   *
   * @endpoint GET /api/v1/menu-calendar
   * @access   admin | gudang | dapur
   * @param query - Send exactly one of `date`, `month`, or `start_date` + `end_date`. Resolution order is: Feb 29 -> Package 9, day 31 -> Package 11, manual `menu_schedules` override, then the default day pattern.
   * @returns {Promise<MenuCalendarResponse>}
   * @throws {ValidationApiError} if query validation fails (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect None
   */
  calendarProjection(query) {
    return this.client.request({
      method: "GET",
      path: "/menu-calendar",
      ...query ? { query: buildMenuCalendarQuery(query) } : {}
    });
  }
};
function buildMenuCalendarQuery(query) {
  const result = {};
  if (query.month !== void 0) result.month = query.month;
  if (query.date !== void 0) result.date = query.date;
  if (query.start_date !== void 0) result.start_date = query.start_date;
  if (query.end_date !== void 0) result.end_date = query.end_date;
  return result;
}

// src/sdk/resources/notifications.ts
var NotificationsResource = class {
  constructor(client) {
    this.client = client;
  }
  client;
  /**
   * Lists the authenticated user's notifications.
   *
   * @endpoint GET /api/v1/notifications
   * @access   authenticated
   * @param query - Supports `page`, `perPage`, `paginate`, `is_read`, `type`, `q`, `sortBy`, and `sortDir`. `paginate=false` keeps the same envelope and sets `meta.paginated=false`.
   * @returns {Promise<ApiListResponse<Notification>>}
   * @throws {ValidationApiError} if query validation fails (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @sideeffect None
   */
  list(query) {
    return this.client.request({
      method: "GET",
      path: "/notifications",
      ...query ? { query: buildNotificationsQuery(query) } : {}
    });
  }
  /**
   * Marks one notification as read for the current user.
   *
   * @endpoint POST /api/v1/notifications/{id}/read
   * @access   authenticated
   * @returns {Promise<ApiMessageResponse>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {NotFoundApiError} if the notification does not exist or does not belong to the caller (404)
   * @sideeffect Updates the notification's `is_read` flag.
   */
  markAsRead(id) {
    return this.client.request({
      method: "POST",
      path: `/notifications/${id}/read`
    });
  }
  /**
   * Marks all notifications as read for the current user.
   *
   * @endpoint POST /api/v1/notifications/read-all
   * @access   authenticated
   * @returns {Promise<ApiMessageResponse>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @sideeffect Updates `is_read=true` for all notifications owned by the caller.
   */
  markAllAsRead() {
    return this.client.request({
      method: "POST",
      path: "/notifications/read-all"
    });
  }
  /**
   * Deletes one notification owned by the current user.
   *
   * @endpoint DELETE /api/v1/notifications/{id}
   * @access   authenticated
   * @returns {Promise<ApiMessageResponse>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {NotFoundApiError} if the notification does not exist or does not belong to the caller (404)
   * @sideeffect Permanently deletes the matching notification row.
   */
  delete(id) {
    return this.client.request({
      method: "DELETE",
      path: `/notifications/${id}`
    });
  }
  /**
   * Deletes all notifications owned by the current user.
   *
   * @endpoint DELETE /api/v1/notifications
   * @access   authenticated
   * @returns {Promise<ApiMessageResponse>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @sideeffect Permanently deletes all notifications owned by the caller.
   */
  deleteAll() {
    return this.client.request({
      method: "DELETE",
      path: "/notifications"
    });
  }
};
function buildNotificationsQuery(query) {
  const result = {};
  if (query.page !== void 0) result.page = query.page;
  if (query.perPage !== void 0) result.perPage = query.perPage;
  if (query.is_read !== void 0) result.is_read = query.is_read;
  if (query.type !== void 0) result.type = query.type;
  if (query.q !== void 0) result.q = query.q;
  if (query.sortBy !== void 0) result.sortBy = query.sortBy;
  if (query.sortDir !== void 0) result.sortDir = query.sortDir;
  if (query.paginate !== void 0) result.paginate = query.paginate;
  return result;
}

// src/sdk/resources/roles.ts
var RolesResource = class {
  constructor(client) {
    this.client = client;
  }
  client;
  /**
   * Lists all available app roles with pagination, filtering, and optional full lookup reads.
   *
   * @endpoint GET /api/v1/roles
   * @access   admin
   *
   * @param query - Supports `paginate`, `page`, `perPage`, `q`/`search` (`q` wins), `sortBy`, `sortDir`, `created_at_from/to`, and `updated_at_from/to`. Unknown params return 400. Soft-deleted rows are excluded. `paginate=false` keeps the same envelope and sets `meta.paginated=false`.
   * @returns {Promise<ApiListResponse<Role>>}
   *
   * @throws {ValidationApiError} if query validation fails (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   *
   * @sideeffect None
   */
  list(query) {
    return this.client.request({
      method: "GET",
      path: "/roles",
      ...query ? { query: buildRoleQuery(query) } : {}
    });
  }
};
function buildRoleQuery(query) {
  const result = {};
  if (query.paginate !== void 0) result.paginate = query.paginate ? "true" : "false";
  if (query.page !== void 0) result.page = query.page;
  if (query.perPage !== void 0) result.perPage = query.perPage;
  if (query.q !== void 0) result.q = query.q;
  if (query.search !== void 0) result.search = query.search;
  if (query.sortBy !== void 0) result.sortBy = query.sortBy;
  if (query.sortDir !== void 0) result.sortDir = query.sortDir;
  if (query.created_at_from !== void 0) result.created_at_from = query.created_at_from;
  if (query.created_at_to !== void 0) result.created_at_to = query.created_at_to;
  if (query.updated_at_from !== void 0) result.updated_at_from = query.updated_at_from;
  if (query.updated_at_to !== void 0) result.updated_at_to = query.updated_at_to;
  return result;
}

// src/sdk/resources/spk.ts
var SpkResource = class {
  constructor(client) {
    this.client = client;
  }
  client;
  /**
   * Resolves the SPK basah menu-calendar projection.
   *
   * @endpoint GET /api/v1/spk/basah/menu-calendar
   * @access   admin | gudang | dapur
   * @param query - Send exactly one of `date`, `month`, or `start_date` + `end_date`.
   * @returns {Promise<SpkMenuCalendarResponse>}
   * @throws {ValidationApiError} if query validation fails (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect None
   */
  basahMenuCalendar(query) {
    return this.client.request({
      method: "GET",
      path: "/spk/basah/menu-calendar",
      ...query ? { query: buildMenuCalendarQuery2(query) } : {}
    });
  }
  /**
   * Previews same-day operational stock consumption for basah preparation.
   *
   * @endpoint POST /api/v1/spk/basah/operational-stock-preview
   * @access   admin | dapur
   * @returns {Promise<OperationalStockPreviewResponse>}
   * @throws {ValidationApiError} if validation fails (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect None; this is a calculation helper only.
   */
  operationalStockPreview(payload) {
    return this.client.request({
      method: "POST",
      path: "/spk/basah/operational-stock-preview",
      body: payload
    });
  }
  /**
   * Generates a basah SPK version.
   *
   * @endpoint POST /api/v1/spk/basah/generate
   * @access   admin | dapur
   * @param payload - Basah generation input. Recommendations follow `((daily_patients × 1.05) × composition_qty) - current_stock`, clamped to 0.
   * @returns {Promise<SpkBasahGenerateResponse>}
   * @throws {ValidationApiError} if validation fails (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect Creates a new history/version row. Does not create stock transactions and does not mutate stock.
   */
  generateBasah(payload) {
    return this.client.request({
      method: "POST",
      path: "/spk/basah/generate",
      body: payload
    });
  }
  /**
   * Lists SPK basah history versions.
   *
   * @endpoint GET /api/v1/spk/basah/history
   * @access   admin | dapur | gudang
   * @returns {Promise<SpkBasahHistoryListResponse>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect None
   */
  listBasah() {
    return this.client.request({
      method: "GET",
      path: "/spk/basah/history"
    });
  }
  /**
   * Returns one SPK basah history version.
   *
   * @endpoint GET /api/v1/spk/basah/history/{id}
   * @access   admin | dapur | gudang
   * @returns {Promise<SpkBasahDetailResponse>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the history row does not exist (404)
   * @sideeffect None
   */
  getBasah(id) {
    return this.client.request({
      method: "GET",
      path: `/spk/basah/history/${id}`
    });
  }
  /**
   * Overrides one basah recommendation row.
   *
   * @endpoint POST /api/v1/spk/basah/history/{id}/override
   * @access   admin | dapur
   * @returns {Promise<SpkOverrideResponse>}
   * @throws {ValidationApiError} if validation fails (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the SPK history row or recommendation does not exist (404)
   * @sideeffect Updates override metadata only; no stock mutation occurs.
   */
  overrideBasah(id, payload) {
    return this.client.request({
      method: "POST",
      path: `/spk/basah/history/${id}/override`,
      body: payload
    });
  }
  /**
   * Posts one basah SPK to stock.
   *
   * @endpoint POST /api/v1/spk/basah/history/{id}/post-stock
   * @access   admin
   * @returns {Promise<SpkPostStockResponse>}
   * @throws {ValidationApiError} if the SPK cannot be posted or was already finalized (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the SPK history row does not exist (404)
   * @sideeffect Creates a stock transaction and finalizes the SPK with `is_finish=true`. This action can only happen once per SPK version.
   */
  postBasahStock(id) {
    return this.client.request({
      method: "POST",
      path: `/spk/basah/history/${id}/post-stock`
    });
  }
  /**
   * Resolves the SPK kering/pengemas menu-calendar projection.
   *
   * @endpoint GET /api/v1/spk/kering-pengemas/menu-calendar
   * @access   admin | gudang | dapur
   * @param query - Send exactly one of `date`, `month`, or `start_date` + `end_date`.
   * @returns {Promise<SpkMenuCalendarResponse>}
   * @throws {ValidationApiError} if query validation fails (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect None
   */
  keringPengemasMenuCalendar(query) {
    return this.client.request({
      method: "GET",
      path: "/spk/kering-pengemas/menu-calendar",
      ...query ? { query: buildMenuCalendarQuery2(query) } : {}
    });
  }
  /**
   * Generates a kering/pengemas SPK version.
   *
   * @endpoint POST /api/v1/spk/kering-pengemas/generate
   * @access   admin | dapur
   * @param payload - Monthly generation input. Recommendations follow `(prev_month_actual_usage × 1.10) - current_stock`, clamped to 0.
   * @returns {Promise<SpkKeringPengemasGenerateResponse>}
   * @throws {ValidationApiError} if validation fails (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect Creates a new history/version row. Does not create stock transactions and does not mutate stock.
   */
  generateKeringPengemas(payload) {
    return this.client.request({
      method: "POST",
      path: "/spk/kering-pengemas/generate",
      body: payload
    });
  }
  /**
   * Lists kering/pengemas SPK history versions.
   *
   * @endpoint GET /api/v1/spk/kering-pengemas/history
   * @access   admin | dapur | gudang
   * @returns {Promise<SpkKeringPengemasHistoryListResponse>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect None
   */
  listKeringPengemas() {
    return this.client.request({
      method: "GET",
      path: "/spk/kering-pengemas/history"
    });
  }
  /**
   * Returns one kering/pengemas SPK history version.
   *
   * @endpoint GET /api/v1/spk/kering-pengemas/history/{id}
   * @access   admin | dapur | gudang
   * @returns {Promise<SpkKeringPengemasDetailResponse>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the history row does not exist (404)
   * @sideeffect None
   */
  getKeringPengemas(id) {
    return this.client.request({
      method: "GET",
      path: `/spk/kering-pengemas/history/${id}`
    });
  }
  /**
   * Overrides one kering/pengemas recommendation row.
   *
   * @endpoint POST /api/v1/spk/kering-pengemas/history/{id}/override
   * @access   admin | dapur
   * @returns {Promise<SpkOverrideResponse>}
   * @throws {ValidationApiError} if validation fails (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the SPK history row or recommendation does not exist (404)
   * @sideeffect Updates override metadata only; no stock mutation occurs.
   */
  overrideKeringPengemas(id, payload) {
    return this.client.request({
      method: "POST",
      path: `/spk/kering-pengemas/history/${id}/override`,
      body: payload
    });
  }
  /**
   * Posts one kering/pengemas SPK to stock.
   *
   * @endpoint POST /api/v1/spk/kering-pengemas/history/{id}/post-stock
   * @access   admin
   * @returns {Promise<SpkPostStockResponse>}
   * @throws {ValidationApiError} if the SPK cannot be posted or was already finalized (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the SPK history row does not exist (404)
   * @sideeffect Creates a stock transaction and finalizes the SPK with `is_finish=true`. This action can only happen once per SPK version.
   */
  postKeringPengemasStock(id) {
    return this.client.request({
      method: "POST",
      path: `/spk/kering-pengemas/history/${id}/post-stock`
    });
  }
  /**
   * Returns a stock-transaction prefill payload derived from an SPK.
   *
   * @endpoint GET /api/v1/spk/stock-in-prefill/{id}
   * @access   admin | dapur
   * @returns {Promise<SpkStockInPrefillResponse>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the SPK history row does not exist (404)
   * @sideeffect None; this helper does not mutate stock.
   */
  stockInPrefill(id) {
    return this.client.request({
      method: "GET",
      path: `/spk/stock-in-prefill/${id}`
    });
  }
};
function buildMenuCalendarQuery2(query) {
  const result = {};
  if (query.month !== void 0) result.month = query.month;
  if (query.date !== void 0) result.date = query.date;
  if (query.start_date !== void 0) result.start_date = query.start_date;
  if (query.end_date !== void 0) result.end_date = query.end_date;
  return result;
}

// src/sdk/resources/stockTransactions.ts
var StockTransactionsResource = class {
  constructor(client) {
    this.client = client;
  }
  client;
  /**
   * Lists stock transactions with pagination, filtering, and search.
   *
   * @endpoint GET /api/v1/stock-transactions
   * @access   admin | gudang
   * @param query - Supports `page`, `perPage`, `q`/`search` on `spk_id` (`q` wins), `sortBy`, `sortDir`, `type_id`, `status_id`, `transaction_date_from/to`, `created_at_from/to`, and `updated_at_from/to`. Unknown params return 400.
   * @returns {Promise<ApiListResponse<StockTransaction>>}
   * @throws {ValidationApiError} if query validation fails (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect None
   */
  list(query) {
    return this.client.request({
      method: "GET",
      path: "/stock-transactions",
      ...query ? { query: buildStockTransactionsQuery(query) } : {}
    });
  }
  /**
   * Returns a stock transaction header only.
   *
   * @endpoint GET /api/v1/stock-transactions/{id}
   * @access   admin | gudang
   * @returns {Promise<ApiDataResponse<StockTransaction>>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the transaction does not exist (404)
   * @sideeffect None
   */
  get(id) {
    return this.client.request({
      method: "GET",
      path: `/stock-transactions/${id}`
    });
  }
  /**
   * Returns the stock transaction detail rows only.
   *
   * @endpoint GET /api/v1/stock-transactions/{id}/details
   * @access   admin | gudang
   * @returns {Promise<ApiDataResponse<StockTransactionDetail[]>>} Each row includes `item_name`, `item_category_id`, `item_category_name`, `satuan` (base unit label from `items.unit_base`), `qty`, `input_qty`, and `input_unit`.
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the transaction does not exist (404)
   * @sideeffect None
   */
  details(id) {
    return this.client.request({
      method: "GET",
      path: `/stock-transactions/${id}/details`
    });
  }
  /**
   * Creates a stock transaction.
   *
   * @endpoint POST /api/v1/stock-transactions
   * @access   admin | gudang
   * @param payload - Send exactly one of `type_id` or `type_name`, plus `transaction_date`, optional `spk_id`, and `details`. Each detail supports `item_id`, `qty`, and optional `input_unit`. `user_id` is derived from the Bearer token and cannot be sent by the client. `input_unit="base"` stores qty as submitted; `input_unit="convert"` stores qty × `items.conversion_base`; backend always persists `input_qty` and normalizes response `qty` to base units.
   * @returns {Promise<ApiMessageDataResponse<StockTransactionCreateResult>>}
   * @throws {ValidationApiError} if validation fails, both type fields are sent, duplicate items exist in one request, or an OUT transaction would drive stock negative (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect Mutates `items.qty` immediately because normal transactions are created with `APPROVED` status.
   */
  create(payload) {
    return this.client.request({
      method: "POST",
      path: "/stock-transactions",
      body: payload
    });
  }
  /**
   * Applies an admin-only direct stock correction for one item.
   *
   * @endpoint POST /api/v1/stock-transactions/direct-corrections
   * @access   admin
   * @param payload - Required fields: `transaction_date`, `item_id`, `expected_current_qty`, `target_qty`, and `reason`. Backend derives `IN` or `OUT` from `target_qty - expected_current_qty` and rejects the request if actual stock no longer matches `expected_current_qty`.
   * @returns {Promise<ApiMessageDataResponse<StockTransactionCreateResult>>}
   * @throws {ValidationApiError} if validation fails or optimistic concurrency rejects the correction (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect Mutates `items.qty` immediately through a final approved ledger transaction.
   */
  directCorrection(payload) {
    return this.client.request({
      method: "POST",
      path: "/stock-transactions/direct-corrections",
      body: payload
    });
  }
  /**
   * Submits a revision for an existing transaction.
   *
   * @endpoint POST /api/v1/stock-transactions/{id}/submit-revision
   * @access   admin | gudang
   * @param payload - Same detail contract as create. Revisions always create a child transaction with `is_revision=true` and `PENDING` status.
   * @returns {Promise<ApiMessageDataResponse<StockTransactionRevisionResult>>}
   * @throws {ValidationApiError} if validation fails (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the parent transaction does not exist (404)
   * @sideeffect Does not mutate `items.qty`.
   */
  submitRevision(id, payload) {
    return this.client.request({
      method: "POST",
      path: `/stock-transactions/${id}/submit-revision`,
      body: payload
    });
  }
  /**
   * Approves a revision transaction.
   *
   * @endpoint POST /api/v1/stock-transactions/{id}/approve
   * @access   admin
   * @returns {Promise<ApiMessageDataResponse<StockTransactionModerationResult>>}
   * @throws {ValidationApiError} if the revision is not approvable (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the revision does not exist (404)
   * @sideeffect Mutates `items.qty` by applying the net difference between parent and revision details, not by replaying the revision as a second additive movement.
   */
  approve(id) {
    return this.client.request({
      method: "POST",
      path: `/stock-transactions/${id}/approve`
    });
  }
  /**
   * Rejects a revision transaction.
   *
   * @endpoint POST /api/v1/stock-transactions/{id}/reject
   * @access   admin
   * @returns {Promise<ApiMessageDataResponse<StockTransactionModerationResult>>}
   * @throws {ValidationApiError} if the revision is not rejectable (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the revision does not exist (404)
   * @sideeffect Does not mutate `items.qty`.
   */
  reject(id) {
    return this.client.request({
      method: "POST",
      path: `/stock-transactions/${id}/reject`
    });
  }
};
function buildStockTransactionsQuery(query) {
  const result = {};
  if (query.page !== void 0) {
    result.page = query.page;
  }
  if (query.perPage !== void 0) {
    result.perPage = query.perPage;
  }
  if (query.q !== void 0) result.q = query.q;
  if (query.search !== void 0) result.search = query.search;
  if (query.sortBy !== void 0) result.sortBy = query.sortBy;
  if (query.sortDir !== void 0) result.sortDir = query.sortDir;
  if (query.type_id !== void 0) result.type_id = query.type_id;
  if (query.status_id !== void 0) result.status_id = query.status_id;
  if (query.transaction_date_from !== void 0) result.transaction_date_from = query.transaction_date_from;
  if (query.transaction_date_to !== void 0) result.transaction_date_to = query.transaction_date_to;
  if (query.created_at_from !== void 0) result.created_at_from = query.created_at_from;
  if (query.created_at_to !== void 0) result.created_at_to = query.created_at_to;
  if (query.updated_at_from !== void 0) result.updated_at_from = query.updated_at_from;
  if (query.updated_at_to !== void 0) result.updated_at_to = query.updated_at_to;
  return result;
}

// src/sdk/resources/transactionTypes.ts
var TransactionTypesResource = class {
  constructor(client) {
    this.client = client;
  }
  client;
  /**
   * Lists transaction types with pagination, filtering, and optional full lookup reads.
   *
   * @endpoint GET /api/v1/transaction-types
   * @access   admin | gudang
   *
   * @param query - Supports `paginate`, `page`, `perPage`, `q`/`search` (`q` wins), `sortBy`, `sortDir`, `created_at_from/to`, and `updated_at_from/to`. Unknown params return 400. Soft-deleted rows are excluded. `paginate=false` keeps the same envelope and sets `meta.paginated=false`.
   * @returns {Promise<ApiListResponse<TransactionType>>}
   *
   * @throws {ValidationApiError} if query validation fails (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   *
   * @sideeffect None
   */
  list(query) {
    return this.client.request({
      method: "GET",
      path: "/transaction-types",
      ...query ? { query: buildLookupQuery5(query) } : {}
    });
  }
};
function buildLookupQuery5(query) {
  const result = {};
  if (query.paginate !== void 0) result.paginate = query.paginate ? "true" : "false";
  if (query.page !== void 0) result.page = query.page;
  if (query.perPage !== void 0) result.perPage = query.perPage;
  if (query.q !== void 0) result.q = query.q;
  if (query.search !== void 0) result.search = query.search;
  if (query.sortBy !== void 0) result.sortBy = query.sortBy;
  if (query.sortDir !== void 0) result.sortDir = query.sortDir;
  if (query.created_at_from !== void 0) result.created_at_from = query.created_at_from;
  if (query.created_at_to !== void 0) result.created_at_to = query.created_at_to;
  if (query.updated_at_from !== void 0) result.updated_at_from = query.updated_at_from;
  if (query.updated_at_to !== void 0) result.updated_at_to = query.updated_at_to;
  return result;
}

// src/sdk/resources/users.ts
var UsersResource = class {
  constructor(client) {
    this.client = client;
  }
  client;
  /**
   * Lists active users with pagination, filtering, and search.
   *
   * @endpoint GET /api/v1/users
   * @access   admin
   * @param query - Supports `page`, `perPage`, `q`/`search` (`q` wins), `sortBy`, `sortDir`, `role_id`, `is_active`, `created_at_from/to`, and `updated_at_from/to`. Unknown params return 400. Soft-deleted users are excluded.
   * @returns {Promise<ApiListResponse<User>>}
   * @throws {ValidationApiError} if query validation fails (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect None
   */
  list(query) {
    return this.client.request({
      method: "GET",
      path: "/users",
      ...query ? { query: buildUsersQuery(query) } : {}
    });
  }
  /**
   * Returns one active user.
   *
   * @endpoint GET /api/v1/users/{id}
   * @access   admin
   * @returns {Promise<ApiDataResponse<User>>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the user does not exist or is soft-deleted (404)
   * @sideeffect None
   */
  get(id) {
    return this.client.request({
      method: "GET",
      path: `/users/${id}`
    });
  }
  /**
   * Creates a user.
   *
   * @endpoint POST /api/v1/users
   * @access   admin
   * @param payload - Writable fields: `name`, `username`, `password`, optional `email`, optional `is_active`, and exactly one of `role_id` or `role_name`.
   * @returns {Promise<ApiMessageDataResponse<User>>}
   * @throws {ValidationApiError} if validation fails, both role fields are sent, or a deleted-username collision requires restore guidance (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect Creates a new user account and synced auth state.
   */
  create(payload) {
    return this.client.request({
      method: "POST",
      path: "/users",
      body: payload
    });
  }
  /**
   * Updates a user's profile and role assignment.
   *
   * @endpoint PUT /api/v1/users/{id}
   * @access   admin
   * @param payload - Partial update. When changing role, send exactly one of `role_id` or `role_name`.
   * @returns {Promise<ApiMessageDataResponse<User>>}
   * @throws {ValidationApiError} if validation fails or both role fields are sent (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the user does not exist or is soft-deleted (404)
   * @sideeffect Updates role/profile fields and keeps auth flags synchronized.
   */
  update(id, payload) {
    return this.client.request({
      method: "PUT",
      path: `/users/${id}`,
      body: payload
    });
  }
  /**
   * Activates a user account.
   *
   * @endpoint PATCH /api/v1/users/{id}/activate
   * @access   admin
   * @returns {Promise<ApiMessageDataResponse<User>>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the user does not exist or is soft-deleted (404)
   * @sideeffect Sets `is_active=true` and syncs the auth `active` flag.
   */
  activate(id) {
    return this.client.request({
      method: "PATCH",
      path: `/users/${id}/activate`
    });
  }
  /**
   * Deactivates a user account.
   *
   * @endpoint PATCH /api/v1/users/{id}/deactivate
   * @access   admin
   * @returns {Promise<ApiMessageDataResponse<User>>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the user does not exist or is soft-deleted (404)
   * @sideeffect Sets `is_active=false` and syncs the auth `active` flag. Existing tokens remain valid until separately revoked.
   */
  deactivate(id) {
    return this.client.request({
      method: "PATCH",
      path: `/users/${id}/deactivate`
    });
  }
  /**
   * Changes another user's password.
   *
   * @endpoint PATCH /api/v1/users/{id}/password
   * @access   admin
   * @param payload - Writable fields: `password` only.
   * @returns {Promise<ApiMessageResponse>}
   * @throws {ValidationApiError} if validation fails (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the user does not exist or is soft-deleted (404)
   * @sideeffect Revokes all access tokens for the target user.
   */
  changePassword(id, payload) {
    return this.client.request({
      method: "PATCH",
      path: `/users/${id}/password`,
      body: payload
    });
  }
  /**
   * Soft-deletes a user.
   *
   * @endpoint DELETE /api/v1/users/{id}
   * @access   admin
   * @returns {Promise<ApiMessageResponse>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the user does not exist or is already soft-deleted (404)
   * @sideeffect Sets `deleted_at` and revokes all access tokens for the target user.
   */
  delete(id) {
    return this.client.request({
      method: "DELETE",
      path: `/users/${id}`
    });
  }
  /**
   * Restores a soft-deleted user.
   *
   * @endpoint PATCH /api/v1/users/{id}/restore
   * @access   admin
   * @returns {Promise<ApiMessageDataResponse<User>>}
   * @throws {ValidationApiError} if an active user already owns the username or the assigned role is inactive (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the user does not exist (404)
   * @sideeffect Clears `deleted_at`. If the user is already active, backend returns the current resource idempotently.
   */
  restore(id) {
    return this.client.request({
      method: "PATCH",
      path: `/users/${id}/restore`
    });
  }
};
function buildUsersQuery(query) {
  const result = {};
  if (query.page !== void 0) result.page = query.page;
  if (query.perPage !== void 0) result.perPage = query.perPage;
  if (query.q !== void 0) result.q = query.q;
  if (query.search !== void 0) result.search = query.search;
  if (query.sortBy !== void 0) result.sortBy = query.sortBy;
  if (query.sortDir !== void 0) result.sortDir = query.sortDir;
  if (query.role_id !== void 0) result.role_id = query.role_id;
  if (query.is_active !== void 0) result.is_active = query.is_active;
  if (query.created_at_from !== void 0) result.created_at_from = query.created_at_from;
  if (query.created_at_to !== void 0) result.created_at_to = query.created_at_to;
  if (query.updated_at_from !== void 0) result.updated_at_from = query.updated_at_from;
  if (query.updated_at_to !== void 0) result.updated_at_to = query.updated_at_to;
  return result;
}

// src/sdk/resources/dashboard.ts
var DashboardResource = class {
  client;
  constructor(client) {
    this.client = client;
  }
  /**
   * Returns the dashboard aggregate payload for the authenticated user's role.
   *
   * @endpoint GET /api/v1/dashboard
   * @access   admin | gudang | dapur
   * @returns {Promise<DashboardResponse>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role or the account is inactive (403)
   * @sideeffect None
   */
  async getAggregate() {
    return this.client.request({
      method: "GET",
      path: "/dashboard"
    });
  }
};

// src/sdk/resources/reports.ts
var ReportsResource = class {
  client;
  constructor(client) {
    this.client = client;
  }
  /**
   * Returns the stock report dataset.
   *
   * @endpoint GET /api/v1/reports/stocks
   * @access   admin | gudang | dapur
   * @param params - Must include `period_start` and `period_end`. Unknown params return 400.
   * @returns {Promise<ReportResponse>}
   * @throws {ValidationApiError} if the period is missing, malformed, or reversed (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect None
   */
  async getStocks(params) {
    return this.client.request({
      method: "GET",
      path: "/reports/stocks",
      query: { ...params }
    });
  }
  /**
   * Returns the stock transaction report dataset.
   *
   * @endpoint GET /api/v1/reports/transactions
   * @access   admin | gudang | dapur
   * @param params - Must include `period_start` and `period_end`. Unknown params return 400.
   * @returns {Promise<ReportResponse>}
   * @throws {ValidationApiError} if the period is missing, malformed, or reversed (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect None
   */
  async getTransactions(params) {
    return this.client.request({
      method: "GET",
      path: "/reports/transactions",
      query: { ...params }
    });
  }
  /**
   * Returns the SPK history report dataset.
   *
   * @endpoint GET /api/v1/reports/spk-history
   * @access   admin | gudang | dapur
   * @param params - Must include `period_start` and `period_end`. Unknown params return 400.
   * @returns {Promise<ReportResponse>}
   * @throws {ValidationApiError} if the period is missing, malformed, or reversed (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect None
   */
  async getSpkHistory(params) {
    return this.client.request({
      method: "GET",
      path: "/reports/spk-history",
      query: { ...params }
    });
  }
  /**
   * Returns the evaluation report dataset.
   *
   * @endpoint GET /api/v1/reports/evaluation
   * @access   admin | gudang | dapur
   * @param params - Must include `period_start` and `period_end`. Unknown params return 400.
   * @returns {Promise<ReportResponse>}
   * @throws {ValidationApiError} if the period is missing, malformed, or reversed (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect None
   */
  async getEvaluation(params) {
    return this.client.request({
      method: "GET",
      path: "/reports/evaluation",
      query: { ...params }
    });
  }
};

// src/sdk/resources/stockOpnames.ts
var StockOpnamesResource = class {
  client;
  constructor(client) {
    this.client = client;
  }
  /**
   * Creates a stock opname draft.
   *
   * @endpoint POST /api/v1/stock-opnames
   * @access   admin | gudang
   * @returns {Promise<StockOpnameActionResponse>}
   * @throws {ValidationApiError} if validation fails (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect Creates a draft opname only; no stock mutation occurs.
   */
  async create(request) {
    return this.client.request({
      method: "POST",
      path: "/stock-opnames",
      body: request
    });
  }
  /**
   * Returns one stock opname header and detail set.
   *
   * @endpoint GET /api/v1/stock-opnames/{id}
   * @access   admin | gudang
   * @returns {Promise<StockOpnameResponse>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the opname does not exist (404)
   * @sideeffect None
   */
  async get(id) {
    return this.client.request({
      method: "GET",
      path: `/stock-opnames/${id}`
    });
  }
  /**
   * Submits a stock opname draft for approval.
   *
   * @endpoint POST /api/v1/stock-opnames/{id}/submit
   * @access   admin | gudang
   * @returns {Promise<StockOpnameActionResponse>}
   * @throws {ValidationApiError} if the draft is not submittable (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the opname does not exist (404)
   * @sideeffect Changes workflow state only; no stock mutation occurs.
   */
  async submit(id) {
    return this.client.request({
      method: "POST",
      path: `/stock-opnames/${id}/submit`
    });
  }
  /**
   * Approves a submitted stock opname.
   *
   * @endpoint POST /api/v1/stock-opnames/{id}/approve
   * @access   admin
   * @returns {Promise<StockOpnameActionResponse>}
   * @throws {ValidationApiError} if the opname is not approvable (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the opname does not exist (404)
   * @sideeffect Changes workflow state only; no stock mutation occurs.
   */
  async approve(id) {
    return this.client.request({
      method: "POST",
      path: `/stock-opnames/${id}/approve`
    });
  }
  /**
   * Rejects a submitted stock opname.
   *
   * @endpoint POST /api/v1/stock-opnames/{id}/reject
   * @access   admin
   * @returns {Promise<StockOpnameActionResponse>}
   * @throws {ValidationApiError} if the opname is not rejectable (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the opname does not exist (404)
   * @sideeffect Changes workflow state only; no stock mutation occurs.
   */
  async reject(id, request) {
    return this.client.request({
      method: "POST",
      path: `/stock-opnames/${id}/reject`,
      body: request
    });
  }
  /**
   * Posts approved stock opname variances to the ledger.
   *
   * @endpoint POST /api/v1/stock-opnames/{id}/post
   * @access   admin
   * @returns {Promise<StockOpnameActionResponse>}
   * @throws {ValidationApiError} if the opname is not postable (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the opname does not exist (404)
   * @sideeffect Mutates stock by generating `OPNAME_ADJUSTMENT` ledger transactions.
   */
  async post(id) {
    return this.client.request({
      method: "POST",
      path: `/stock-opnames/${id}/post`
    });
  }
};

// src/sdk/index.ts
var CapstoneSdk = class {
  client;
  approvalStatuses;
  auth;
  dailyPatients;
  dishes;
  dishCompositions;
  itemCategories;
  roles;
  items;
  itemUnits;
  mealTimes;
  menus;
  menuSchedules;
  notifications;
  spk;
  stockTransactions;
  transactionTypes;
  users;
  dashboard;
  reports;
  stockOpnames;
  constructor(options) {
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
  setAccessToken(token) {
    this.client.setAccessToken(token);
  }
  /**
   * Clears the in-memory bearer token used by the shared client.
   */
  clearAccessToken() {
    this.client.clearAccessToken();
  }
};
function createCapstoneSdk(options) {
  return new CapstoneSdk(options);
}
export {
  ApiClient,
  ApiError,
  ApprovalStatusesResource,
  AuthResource,
  AuthenticationApiError,
  AuthorizationApiError,
  CapstoneSdk,
  DailyPatientsResource,
  DashboardResource,
  DishCompositionsResource,
  DishesResource,
  ItemCategoriesResource,
  ItemUnitsResource,
  ItemsResource,
  MealTimesResource,
  MenuSchedulesResource,
  MenusResource,
  NotFoundApiError,
  NotificationsResource,
  ReportsResource,
  RolesResource,
  SpkResource,
  StockOpnamesResource,
  StockTransactionsResource,
  TransactionTypesResource,
  UsersResource,
  ValidationApiError,
  createCapstoneSdk,
  toApiError
};
