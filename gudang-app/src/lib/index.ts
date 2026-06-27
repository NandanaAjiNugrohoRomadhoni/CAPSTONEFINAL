import { createCapstoneSdk } from "../sdk";

const sdk = createCapstoneSdk({
  baseUrl: process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://localhost:8080",
  fetchImplementation: (...args) => globalThis.fetch(...args),
});

export default sdk;
