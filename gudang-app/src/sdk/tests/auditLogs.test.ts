import { describe, expect, it, vi } from "vitest";

import { CapstoneSdk } from "..";

describe("AuditLogsResource", () => {
    it("loads list endpoint", async () => {
        const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
            new Response(JSON.stringify({ data: [], meta: {}, links: {} }), {
                status: 200,
                headers: { "content-type": "application/json" }
            })
        );

        const sdk = new CapstoneSdk({ fetchImplementation: fetchMock });
        await sdk.auditLogs.list();

        const [url, init] = fetchMock.mock.calls[0] ?? [];
        expect(url).toBe("http://127.0.0.1:8080/api/v1/audit-logs");
        expect(init?.method).toBe("GET");
    });

    it("loads types endpoint", async () => {
        const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
            new Response(JSON.stringify({ actionTypes: ["create"], moduleTypes: [], tableNames: [] }), {
                status: 200,
                headers: { "content-type": "application/json" }
            })
        );

        const sdk = new CapstoneSdk({ fetchImplementation: fetchMock });
        await sdk.auditLogs.types();

        const [url, init] = fetchMock.mock.calls[0] ?? [];
        expect(url).toBe("http://127.0.0.1:8080/api/v1/audit-logs/types");
        expect(init?.method).toBe("GET");
    });
});