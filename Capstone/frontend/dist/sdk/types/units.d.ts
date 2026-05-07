export interface Unit {
    id: number;
    name: string;
    created_at: string | null;
    updated_at: string | null;
}
export interface CreateUnitRequest {
    name: string;
}
export interface UpdateUnitRequest {
    name?: string;
}
