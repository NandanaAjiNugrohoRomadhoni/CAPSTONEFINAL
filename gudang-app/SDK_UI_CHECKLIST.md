# SDK UI Checklist

Frontend tracking for `gudang-app` against the shared SDK in `Capstone/frontend`.

## Used In UI

- [x] `sdk.auth.login`
  - Login page and auth store
- [x] `sdk.auth.me`
  - Session restore / auth guard
- [x] `sdk.auth.logout`
  - Logout flow
- [x] `sdk.roles.list`
  - User management role dropdown/filter
- [x] `sdk.users.list`
  - User management table
- [x] `sdk.users.create`
  - Add user
- [x] `sdk.users.update`
  - Edit user
- [x] `sdk.users.changePassword`
  - Edit user password
- [x] `sdk.users.activate`
  - Activate user
- [x] `sdk.users.deactivate`
  - Deactivate user
- [x] `sdk.users.delete`
  - Soft delete user
- [x] `sdk.itemCategories.list`
  - Data Jenis Bahan table
- [x] `sdk.itemCategories.create`
  - Add jenis bahan
- [x] `sdk.itemCategories.update`
  - Edit jenis bahan
- [x] `sdk.itemCategories.delete`
  - Soft delete jenis bahan
- [x] `sdk.itemUnits.list`
  - Data Satuan table
- [x] `sdk.itemUnits.create`
  - Add satuan
- [x] `sdk.itemUnits.update`
  - Edit satuan
- [x] `sdk.itemUnits.delete`
  - Soft delete satuan

## Not Yet Used In UI

- [ ] `sdk.approvalStatuses.list`
- [ ] `sdk.items.list`
- [ ] `sdk.items.get`
- [ ] `sdk.items.create`
- [ ] `sdk.items.update`
- [ ] `sdk.items.delete`
- [ ] `sdk.stockTransactions.list`
- [ ] `sdk.stockTransactions.get`
- [ ] `sdk.stockTransactions.details`
- [ ] `sdk.stockTransactions.create`
- [ ] `sdk.stockTransactions.submitRevision`
- [ ] `sdk.stockTransactions.approve`
- [ ] `sdk.stockTransactions.reject`
- [ ] `sdk.transactionTypes.list`

## UI Progress By Feature

- [x] Login / session auth
- [x] User management
- [x] Data Jenis Bahan
- [x] Data Satuan
- [ ] Item master data
- [ ] Barang masuk
- [ ] Barang keluar
- [ ] Riwayat transaksi barang
- [ ] Stock dashboards/pages
- [ ] Menu pages
- [ ] SPK pages
- [ ] Reports

## Notes

- Delete flows currently use soft delete for:
  - users
  - jenis bahan
  - satuan
- Frontend should keep following the SDK surface from `Capstone/frontend`, not call backend URLs directly.
