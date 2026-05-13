import { createCapstoneSdk } from "../../frontend/src";

export const BASE_URL = "http://127.0.0.1:8080";

export const createClient = () => createCapstoneSdk({
  baseUrl: BASE_URL
});

export const actors = {
  admin: { username: "admin", password: "password123" },
  dapur: { username: "spkgizi", password: "password123" },
  gudang: { username: "gudang", password: "password123" },
  inactive: { username: "inactiveadmin", password: "password123" }
};
