export interface ItemCategory {
    id: number;
    name: string;
    created_at: string | null;
    updated_at: string | null;
}
export interface CreateItemCategoryRequest {
    name: string;
}
export interface UpdateItemCategoryRequest {
    name?: string;
}
