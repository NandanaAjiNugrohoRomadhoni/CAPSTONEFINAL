"use client";

import { useEffect, useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import DeleteConfirmModal from "@/components/feedback/DeleteConfirmModal";
import SuccessModal from "@/components/feedback/SuccessModal";
import sdk from "@/lib";
import UserModal, {
  type EditableUser,
  type RoleOption,
} from "@/components/users/UserModal";
import {
  AdminPageHeading,
  FilterSearch,
  MiniActionButton,
  Pagination,
  PrimaryAction,
  SurfaceCard,
  ThemedSelect,
} from "@/components/admin/ui";
import { getRoleLabel, useAuthStore } from "@/store/authStore";

type ManagedUser = EditableUser & {
  is_active: boolean;
  created_at: string;
  role?: {
    id: number;
    name: string;
  };
};

type SuccessState = {
  headline: string;
  message: string;
} | null;

type UsersListResponse = Awaited<ReturnType<typeof sdk.users.list>>;
type UserRow = UsersListResponse["data"][number];
type UsersListQuery = NonNullable<Parameters<typeof sdk.users.list>[0]>;

function formatDate(value: string): string {
  return new Intl.DateTimeFormat("id-ID", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
  }).format(new Date(value));
}

function getErrorMessage(error: unknown, fallback: string): string {
  if (
    typeof error === "object" &&
    error !== null &&
    "body" in error &&
    typeof (error as { body?: unknown }).body === "object" &&
    (error as {
      body?: { message?: unknown; errors?: Record<string, unknown> };
    }).body !== null
  ) {
    const body = (error as {
      body: { message?: unknown; errors?: Record<string, unknown> };
    }).body;

    if (body.errors && typeof body.errors === "object") {
      const firstError = Object.values(body.errors).find(
        (value) => typeof value === "string" && value.trim().length > 0,
      );

      if (typeof firstError === "string") {
        return firstError;
      }
    }

    if (typeof body.message === "string") {
      return body.message;
    }
  }

  if (error instanceof Error && error.message) {
    return error.message;
  }

  return fallback;
}

export default function UsersPage() {
  const router = useRouter();
  const currentUser = useAuthStore((state) => state.user);
  const [users, setUsers] = useState<ManagedUser[]>([]);
  const [roles, setRoles] = useState<RoleOption[]>([]);
  const [search, setSearch] = useState("");
  const [roleFilter, setRoleFilter] = useState("all");
  const [statusFilter, setStatusFilter] = useState("all");
  const [loading, setLoading] = useState(true);
  const [pageError, setPageError] = useState<string | null>(null);
  const [open, setOpen] = useState(false);
  const [modalMode, setModalMode] = useState<"create" | "edit">("create");
  const [selectedUser, setSelectedUser] = useState<EditableUser | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [modalError, setModalError] = useState<string | null>(null);
  const [createModalVersion, setCreateModalVersion] = useState(0);
  const [successState, setSuccessState] = useState<SuccessState>(null);
  const [deleteTarget, setDeleteTarget] = useState<ManagedUser | null>(null);
  const [deleteError, setDeleteError] = useState<string | null>(null);
  const [deleting, setDeleting] = useState(false);
  const [refreshOnSuccessClose, setRefreshOnSuccessClose] = useState(false);
  const [currentPage, setCurrentPage] = useState(1);
  const [totalRecords, setTotalRecords] = useState(0);

  async function loadData(page = currentPage) {
    setLoading(true);
    setPageError(null);

    try {
      const query: UsersListQuery = {
        page,
        perPage: 10,
        sortBy: "created_at",
        sortDir: "DESC",
      };

      const keyword = search.trim();
      if (keyword.length > 0) {
        query.search = keyword;
      }

      if (roleFilter !== "all") {
        const selectedRole = roles.find((role) => role.name === roleFilter);
        if (selectedRole) {
          query.role_id = selectedRole.id;
        }
      }

      if (statusFilter === "active") {
        query.is_active = true;
      } else if (statusFilter === "inactive") {
        query.is_active = false;
      }

      const usersResponse = await sdk.users.list(query);
      const nextRows = (usersResponse.data ?? []) as ManagedUser[];
      const visibleRows =
        currentUser?.role?.name === "admin"
          ? nextRows.filter((user) => user.role?.name !== "admin")
          : nextRows;

      setUsers(visibleRows);
      setTotalRecords(usersResponse.meta?.total ?? visibleRows.length);
    } catch (error) {
      setPageError(getErrorMessage(error, "Gagal memuat data pengguna."));
    } finally {
      setLoading(false);
    }
  }

  async function ensureRoles() {
    if (roles.length > 0) return;
    try {
      const rolesResponse = await sdk.roles.list({
        paginate: false,
        sortBy: "id",
        sortDir: "ASC",
      });
      setRoles(rolesResponse.data as RoleOption[]);
    } catch (err) {
      console.error("Failed to load roles:", err);
    }
  }

  useEffect(() => {
    void ensureRoles();
  }, []);

  const manageableRoles = useMemo(
    () => (currentUser?.role?.name === "admin" ? roles.filter((role) => role.name !== "admin") : roles),
    [currentUser?.role?.name, roles],
  );

  useEffect(() => {
    setCurrentPage(1);
  }, [search, roleFilter, statusFilter]);

  const totalPages = Math.max(1, Math.ceil(totalRecords / 10));

  useEffect(() => {
    if (currentPage > totalPages) {
      setCurrentPage(totalPages);
    }
  }, [currentPage, totalPages]);

  useEffect(() => {
    void loadData(currentPage);
  }, [currentPage, search, roleFilter, statusFilter]);

  async function openCreateModal() {
    setModalMode("create");
    setSelectedUser(null);
    setModalError(null);
    setCreateModalVersion((current) => current + 1);
    setOpen(true);
    await ensureRoles();
  }

  async function openEditModal(user: ManagedUser) {
    setModalMode("edit");
    setSelectedUser({
      id: user.id,
      name: user.name,
      username: user.username,
      email: user.email,
      role_id: user.role_id,
    });
    setModalError(null);
    setOpen(true);
    await ensureRoles();
  }

  async function handleSubmit(payload: {
    name: string;
    username: string;
    password?: string;
    role_id: number;
  }) {
    setSubmitting(true);
    setModalError(null);
    const previousUsers = users;

    try {
      if (modalMode === "create") {
        const roleMatch = roles.find((role) => role.id === payload.role_id);
        const optimisticUser: ManagedUser = {
          id: Date.now(),
          name: payload.name,
          username: payload.username,
          email: null,
          role_id: payload.role_id,
          is_active: true,
          created_at: new Date().toISOString(),
          role: roleMatch
            ? {
                id: roleMatch.id,
                name: roleMatch.name,
              }
            : undefined,
        };
        setUsers((current) => [...current, optimisticUser]);
        await sdk.users.create({
          name: payload.name,
          username: payload.username,
          password: payload.password ?? "",
          role_id: payload.role_id,
        });
        setSuccessState({
          headline: "Akun Berhasil Ditambahkan",
          message: `Akun ${payload.name} berhasil ditambahkan ke daftar pengguna.`,
        });
        setRefreshOnSuccessClose(true);
      } else if (selectedUser) {
        const roleMatch = roles.find((role) => role.id === payload.role_id);
        setUsers((current) =>
          current.map((item) =>
            item.id === selectedUser.id
              ? {
                  ...item,
                  name: payload.name,
                  username: payload.username,
                  role_id: payload.role_id,
                  role: roleMatch
                    ? {
                        id: roleMatch.id,
                        name: roleMatch.name,
                      }
                    : item.role,
                }
              : item,
          ),
        );
        await sdk.users.update(selectedUser.id, {
          name: payload.name,
          username: payload.username,
          role_id: payload.role_id,
        });

        if (payload.password && payload.password.trim().length > 0) {
          await sdk.users.changePassword(selectedUser.id, {
            password: payload.password.trim(),
          });
        }

        setSuccessState({
          headline: "Akun Berhasil Diedit",
          message: `Informasi akun ${payload.name} berhasil diperbarui.`,
        });
        setRefreshOnSuccessClose(true);
      }

      setOpen(false);
      await loadData(modalMode === "create" ? 1 : currentPage);
      router.refresh();
    } catch (error) {
      setUsers(previousUsers);
      setModalError(getErrorMessage(error, "Gagal menyimpan pengguna."));
    } finally {
      setSubmitting(false);
    }
  }

  async function handleToggleStatus(user: ManagedUser) {
    const previousUsers = users;
    setUsers((current) =>
      current.map((item) =>
        item.id === user.id ? { ...item, is_active: !item.is_active } : item,
      ),
    );

    try {
      if (user.is_active) {
        await sdk.users.deactivate(user.id);
        setSuccessState({
          headline: "Akun Berhasil Dinonaktifkan",
          message: `Akun ${user.name} telah dinonaktifkan.`,
        });
      } else {
        await sdk.users.activate(user.id);
        setSuccessState({
          headline: "Akun Berhasil Diaktifkan",
          message: `Akun ${user.name} telah diaktifkan kembali.`,
        });
      }

      await loadData(currentPage);
      router.refresh();
    } catch (error) {
      setUsers(previousUsers);
      setPageError(getErrorMessage(error, "Gagal memperbarui status pengguna."));
    }
  }

  function openDeleteModal(user: ManagedUser) {
    setDeleteTarget(user);
    setDeleteError(null);
  }

  function closeDeleteModal() {
    if (deleting) {
      return;
    }

    setDeleteTarget(null);
    setDeleteError(null);
  }

  async function handleDelete() {
    if (!deleteTarget) {
      return;
    }

    setDeleting(true);
    setDeleteError(null);
    const previousUsers = users;
    setUsers((current) => current.filter((item) => item.id !== deleteTarget.id));

    try {
      await sdk.users.delete(deleteTarget.id);
      await loadData(currentPage);
      router.refresh();
      setSuccessState({
        headline: "Akun Berhasil Dihapus",
        message: "",
      });
      setDeleteTarget(null);
    } catch (error) {
      setUsers(previousUsers);
      setDeleteError(getErrorMessage(error, "Gagal menghapus pengguna."));
    } finally {
      setDeleting(false);
    }
  }

  return (
    <div className="space-y-6">
      <AdminPageHeading
        title="Manajemen Pengguna"
        action={<PrimaryAction onClick={openCreateModal}>Buat Akun Pengguna</PrimaryAction>}
      />

      <SurfaceCard className="overflow-hidden">
        <div className="flex flex-wrap items-center gap-3 border-b bg-[#F8FAFC] px-5 py-4">
          <div className="w-full max-w-[320px]">
            <FilterSearch
              placeholder="Cari Nama / Username"
              value={search}
              onChange={setSearch}
              readOnly={false}
            />
          </div>

          <ThemedSelect
            className="min-w-[170px]"
            value={roleFilter}
            onChange={setRoleFilter}
            options={[
              { value: "all", label: "Semua Role" },
              ...manageableRoles.map((role) => ({
                value: role.name,
                label: getRoleLabel(role.name),
              })),
            ]}
          />

          <ThemedSelect
            className="min-w-[180px]"
            value={statusFilter}
            onChange={setStatusFilter}
            options={[
              { value: "all", label: "Semua Status" },
              { value: "active", label: "Aktif" },
              { value: "inactive", label: "Nonaktif" },
            ]}
          />
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-base">
            <thead>
              <tr className="bg-[#F1F5F9] text-[11px] font-semibold uppercase tracking-wide text-[#64748B]">
                <th className="px-6 py-3">Tanggal</th>
                <th className="px-6 py-3 text-left">Nama Pengguna</th>
                <th className="px-6 py-3 text-left">Username</th>
                <th className="px-6 py-3">Role</th>
                <th className="px-6 py-3">Status</th>
                <th className="px-6 py-3 text-center">Aksi</th>
              </tr>
            </thead>

            <tbody className="bg-white text-base text-[#334155]">
              {loading ? (
                <tr>
                  <td className="px-6 py-10 text-center text-[#94A3B8]" colSpan={6}>
                    Memuat data pengguna...
                  </td>
                </tr>
              ) : users.length === 0 ? (
                <tr>
                  <td className="px-6 py-10 text-center text-[#94A3B8]" colSpan={6}>
                    Tidak ada pengguna yang cocok dengan filter saat ini.
                  </td>
                </tr>
              ) : (
                users.map((user) => (
                  <tr
                    key={user.id}
                    className="border-t border-[#E2E8F0] transition hover:bg-[#F8FAFC]"
                  >
                    <td className="px-6 py-4 text-center text-[#475569]">
                      {formatDate(user.created_at)}
                    </td>
                    <td className="px-6 py-4 font-semibold text-[#16213E]">{user.name}</td>
                    <td className="px-6 py-4 text-[#475569]">{user.username}</td>
                    <td className="px-6 py-4 text-center text-[#475569]">
                      {getRoleLabel(user.role?.name)}
                    </td>
                    <td className="px-6 py-4 text-center">
                      <span
                        className={`inline-flex rounded-full px-3 py-1 text-sm font-semibold ${
                          user.is_active
                            ? "bg-[#DCFCE7] text-[#16A34A]"
                            : "bg-[#FEE2E2] text-[#DC2626]"
                        }`}
                      >
                        {user.is_active ? "Aktif" : "Nonaktif"}
                      </span>
                    </td>
                    <td className="px-6 py-4 text-center">
                      <div className="flex flex-wrap justify-center gap-2">
                        <MiniActionButton onClick={() => openEditModal(user)}>
                          Edit
                        </MiniActionButton>
                        <MiniActionButton
                          onClick={() => void handleToggleStatus(user)}
                        >
                          {user.is_active ? "Nonaktifkan" : "Aktifkan"}
                        </MiniActionButton>
                        <MiniActionButton onClick={() => openDeleteModal(user)} tone="danger">
                          Hapus
                        </MiniActionButton>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        <Pagination
          currentPage={currentPage}
          totalPages={totalPages}
          onPageChange={setCurrentPage}
          totalLabel={
            totalRecords > 0
              ? `${(currentPage - 1) * 10 + 1}-${Math.min(currentPage * 10, totalRecords)} dari ${totalRecords} pengguna`
              : "0 dari 0 pengguna"
          }
        />
        {pageError ? (
          <div className="border-t border-[#E2E8F0] bg-[#FFF7ED] px-6 py-3 text-sm text-red-600">
            {pageError}
          </div>
        ) : null}
      </SurfaceCard>

      <UserModal
        key={
          modalMode === "create"
            ? `create-${createModalVersion}`
            : `edit-${selectedUser?.id ?? "unknown"}`
        }
        open={open}
        mode={modalMode}
        roles={manageableRoles}
        initialUser={selectedUser}
        submitting={submitting}
        error={modalError}
        onClose={() => setOpen(false)}
        onSubmit={handleSubmit}
      />

      <SuccessModal
        open={successState !== null}
        title="Berhasil"
        headline={successState?.headline ?? ""}
        message={successState?.message ?? ""}
        onClose={() => {
          setSuccessState(null);

          if (refreshOnSuccessClose) {
            setRefreshOnSuccessClose(false);
            void loadData(currentPage);
            router.refresh();
          }
        }}
      />

      <DeleteConfirmModal
        open={deleteTarget !== null}
        headline="Hapus akun ini?"
        description="Apakah anda yakin untuk menghapus akun ini?"
        submitting={deleting}
        error={deleteError}
        onClose={closeDeleteModal}
        onConfirm={handleDelete}
      />
    </div>
  );
}
