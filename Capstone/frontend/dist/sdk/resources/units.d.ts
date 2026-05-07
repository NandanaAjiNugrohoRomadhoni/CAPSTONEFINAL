import type { ApiClient } from "../client";
import type { ApiDataResponse, ApiMessageDataResponse, ApiMessageResponse, CreateUnitRequest, Unit, UpdateUnitRequest } from "../types";
export declare class UnitsResource {
    private readonly client;
    constructor(client: ApiClient);
    list(): Promise<ApiDataResponse<Unit[]>>;
    create(payload: CreateUnitRequest): Promise<ApiMessageDataResponse<Unit>>;
    update(id: number, payload: UpdateUnitRequest): Promise<ApiMessageDataResponse<Unit>>;
    delete(id: number): Promise<ApiMessageResponse>;
}
