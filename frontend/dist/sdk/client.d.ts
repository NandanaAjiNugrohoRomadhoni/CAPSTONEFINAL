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
export declare class ApiClient {
    private readonly baseUrl;
    private readonly apiBasePath;
    private readonly defaultHeaders;
    private readonly fetchImplementation;
    private readonly getAccessToken?;
    private accessToken;
    constructor(options?: ApiClientOptions);
    setAccessToken(token: string | null): void;
    clearAccessToken(): void;
    request<TResponse>(options: RequestOptions): Promise<TResponse>;
    private buildUrl;
    private resolveAccessToken;
}
