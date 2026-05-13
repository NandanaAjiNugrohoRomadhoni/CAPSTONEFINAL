import { toApiError } from "./errors";
import type { QueryParams } from "./types";

export interface RequestOptions {
  method?: "GET" | "POST" | "PUT" | "PATCH" | "DELETE";
  path: string;
  query?: QueryParams;
  body?: unknown;
  headers?: HeadersInit;
}

export interface ApiClientOptions {
  baseUrl?: string;
  apiBasePath?: string;
  accessToken?: string | null;
  getAccessToken?: () => string | null | undefined | Promise<string | null | undefined>;
  defaultHeaders?: HeadersInit;
  fetchImplementation?: typeof fetch;
}

export class ApiClient {
  private readonly baseUrl: string;
  private readonly apiBasePath: string;
  private readonly defaultHeaders: HeadersInit;
  private readonly fetchImplementation: typeof fetch;
  private readonly getAccessToken?: ApiClientOptions["getAccessToken"];
  private accessToken: string | null;

  public constructor(options: ApiClientOptions = {}) {
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

  public setAccessToken(token: string | null): void {
    this.accessToken = token;
  }

  public clearAccessToken(): void {
    this.accessToken = null;
  }

  public async request<TResponse>(options: RequestOptions): Promise<TResponse> {
    const headers = new Headers(this.defaultHeaders);

    for (const [key, value] of new Headers(options.headers).entries()) {
      headers.set(key, value);
    }

    headers.set("Accept", "application/json");

    const token = await this.resolveAccessToken();
    if (token) {
      headers.set("Authorization", `Bearer ${token}`);
    }

    let body: BodyInit | undefined;

    if (options.body !== undefined) {
      headers.set("Content-Type", "application/json");
      body = JSON.stringify(options.body);
    }

    const requestInit: RequestInit = {
      method: options.method ?? "GET",
      headers
    };

    if (body !== undefined) {
      requestInit.body = body;
    }

    const response = await this.fetchImplementation(this.buildUrl(options.path, options.query), requestInit);

    const payload = await parseResponse(response);

    if (!response.ok) {
      throw toApiError(response.status, payload);
    }

    return payload as TResponse;
  }

  private buildUrl(path: string, query?: QueryParams): string {
    const normalizedPath = ensureLeadingSlash(path);
    const url = new URL(`${this.baseUrl}${this.apiBasePath}${normalizedPath}`);

    if (query) {
      for (const [key, value] of Object.entries(query)) {
        if (value === undefined || value === null) {
          continue;
        }

        url.searchParams.set(key, String(value));
      }
    }

    return url.toString();
  }

  private async resolveAccessToken(): Promise<string | null> {
    const dynamicToken = await this.getAccessToken?.();

    if (dynamicToken !== undefined) {
      return dynamicToken ?? null;
    }

    return this.accessToken;
  }
}

async function parseResponse(response: Response): Promise<unknown> {
  if (response.status === 204) {
    return undefined;
  }

  const contentType = response.headers.get("content-type") ?? "";

  if (contentType.includes("application/json")) {
    return response.json();
  }

  const text = await response.text();
  return text.length > 0 ? text : undefined;
}

function trimTrailingSlash(value: string): string {
  return value.replace(/\/+$/, "");
}

function ensureLeadingSlash(value: string): string {
  return value.startsWith("/") ? value : `/${value}`;
}
