"use client";

import { useEffect, useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import { Search } from "lucide-react";
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

  async function loadData() {
    setLoading(true);
    setPageError(null);

    try {
      const usersResponse = await sdk.users.list();
      setUsers(usersResponse.data as ManagedUser[]);
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
    void loadData();
    void ensureRoles();
  }, []);

  const manageableRoles = useMemo(
    () => (currentUser?.role?.name === "admin" ? roles.filter((role) => role.name !== "admin") : roles),
    [currentUser?.role?.name, roles],
  );

  const filteredUsers = useMemo(() => {
    const keyword = search.trim().toLowerCase();

    return users
      .filter((user) => {
        const isHiddenAdminRow =
          currentUser?.role?.name === "admin" && user.role?.name === "admin";

        const matchesSearch =
          keyword.length === 0 ||
          user.name.toLowerCase().includes(keyword) ||
          user.username.toLowerCase().includes(keyword);

        const matchesRole = roleFilter === "all" || user.role?.name === roleFilter;
        const matchesStatus =
          statusFilter === "all" ||
          (statusFilter === "active" && user.is_active) ||
          (statusFilter === "inactive" && !user.is_active);

        return !isHiddenAdminRow && matchesSearch && matchesRole && matchesStatus;
      })
      .sort((left, right) => left.id - right.id);
  }, [currentUser?.role?.name, roleFilter, search, statusFilter, users]);

  useEffect(() => {
    setCurrentPage(1);
  }, [search, roleFilter, statusFilter]);

  const pageSize = 10;
  const totalPages = Math.max(1, Math.ceil(filteredUsers.length / pageSize));
  const paginatedUsers = useMemo(() => {
    const startIndex = (currentPage - 1) * pageSize;
    return filteredUsers.slice(startIndex, startIndex + pageSize);
  }, [currentPage, filteredUsers]);

  useEffect(() => {
    if (currentPage > totalPages) {
      setCurrentPage(totalPages);
    }
  }, [currentPage, totalPages]);

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
      await loadData();
      router.refresh();
    } catch (error) {
      if (modalMode === "create") {
        try {
          const usersResponse = (await sdk.users.list()) as UsersListResponse;
          const refreshedUsers = usersResponse.data as ManagedUser[];
          const hadUserBeforeSubmit = previousUsers.some(
            (user) =>
              user.username.trim().toLowerCase() === payload.username.trim().toLowerCase(),
          );
          const createdUser = refreshedUsers.find(
            (user) =>
              user.username.trim().toLowerCase() === payload.username.trim().toLowerCase(),
          );

          if (createdUser && !hadUserBeforeSubmit) {
            setUsers(refreshedUsers);
            setOpen(false);
            setModalError(null);
            setSuccessState({
              headline: "Akun Berhasil Ditambahkan",
              message: `Akun ${payload.name} berhasil ditambahkan ke daftar pengguna.`,
            });
            setRefreshOnSuccessClose(true);
            router.refresh();
            return;
          }
        } catch {
          // Keep the original error handling below when verification fails.
        }
      }

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

      await loadData();
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
      const deletedName = deleteTarget.name;
      await sdk.users.delete(deleteTarget.id);
      await loadData();
      router.refresh();
      setSuccessState({
        headline: "Akun Berhasil Dihapus",
        message: `Akun ${deletedName} telah dipindahkan ke arsip.`,
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
        subtitle="Untuk melihat dan mengelola informasi pengguna"
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
              ) : paginatedUsers.length === 0 ? (
                <tr>
                  <td className="px-6 py-10 text-center text-[#94A3B8]" colSpan={6}>
                    Tidak ada pengguna yang cocok dengan filter saat ini.
                  </td>
                </tr>
              ) : (
                paginatedUsers.map((user) => (
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
            filteredUsers.length > 0
              ? `${(currentPage - 1) * pageSize + 1}-${Math.min(currentPage * pageSize, filteredUsers.length)} dari ${filteredUsers.length} pengguna`
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
            void loadData();
            router.refresh();
          }
        }}
      />

      <DeleteConfirmModal
        open={deleteTarget !== null}
        headline="Hapus akun ini?"
        description={`Akun ${deleteTarget?.name ?? ""} akan dipindahkan ke arsip dan tidak tampil di daftar utama.`}
        submitting={deleting}
        error={deleteError}
        onClose={closeDeleteModal}
        onConfirm={handleDelete}
      />
    </div>
  );
}
