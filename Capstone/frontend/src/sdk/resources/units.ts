import type { ApiClient } from "../client";
import type {
  ApiDataResponse,
  ApiMessageDataResponse,
  ApiMessageResponse,
  CreateUnitRequest,
  Unit,
  UpdateUnitRequest,
} from "../types";

export class UnitsResource {
  public constructor(private readonly client: ApiClient) {}

  public list(): Promise<ApiDataResponse<Unit[]>> {
    return this.client.request<ApiDataResponse<Unit[]>>({
      method: "GET",
      path: "/units",
    });
  }

  public create(payload: CreateUnitRequest): Promise<ApiMessageDataResponse<Unit>> {
    return this.client.request<ApiMessageDataResponse<Unit>>({
      method: "POST",
      path: "/units",
      body: payload,
    });
  }

  public update(id: number, payload: UpdateUnitRequest): Promise<ApiMessageDataResponse<Unit>> {
    return this.client.request<ApiMessageDataResponse<Unit>>({
      method: "PUT",
      path: `/units/${id}`,
      body: payload,
    });
  }

  public delete(id: number): Promise<ApiMessageResponse> {
    return this.client.request<ApiMessageResponse>({
      method: "DELETE",
      path: `/units/${id}`,
    });
  }
}
