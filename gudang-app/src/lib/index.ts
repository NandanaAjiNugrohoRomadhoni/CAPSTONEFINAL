import { createCapstoneSdk } from "../sdk";

const getApiBaseUrl = () => {
  const envUrl = process.env.NEXT_PUBLIC_API_BASE_URL;

  if (typeof window !== "undefined") {
    const hostname = window.location.hostname;
    const port = window.location.port;
    const isLocalhostDev =
      (hostname === "localhost" || hostname === "127.0.0.1") &&
      port !== "4400" &&
      port !== "8080" &&
      port !== "8081";

    if (!isLocalhostDev) {
      return window.location.origin;
    }
  }

  return envUrl ?? "http://localhost:8081";
};

const sdk = createCapstoneSdk({
  baseUrl: getApiBaseUrl(),
  fetchImplementation: (...args) => globalThis.fetch(...args),
});

export default sdk;
