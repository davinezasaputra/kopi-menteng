# ERP End-to-End UAT Runbook

Dokumen ini adalah checklist pengujian manual untuk menjalankan sistem dari kondisi database bersih, mulai dari login Developer sampai seluruh alur ERP diuji. Fokusnya adalah memverifikasi UI, API, status transaksi, inventory, accounting, closing, audit trail, dan organization scope secara bersama-sama.

## 1. Persiapan

Gunakan database lokal/UAT yang aman untuk di-reset.

### Backend

```bash
cd pos-menteng-backend
php artisan migrate:fresh --seed
php artisan serve
```

Set password UAT di `.env`:

```env
SEED_DEVELOPER_PASSWORD=<password-uji-lokal>
```

Jangan commit password.

### Frontend

```bash
cd pos-menteng-frontend
npm ci
npm run build
npm run lint
npm run dev
```

Admin Login menggunakan backend `POST /api/login` dan setelah berhasil menuju `/dashboard`.

## 2. Baseline Setelah Reset

Pastikan hasil awal:

| Item | Expected | Status |
|---|---|---|
| User | Hanya Developer | ☐ |
| Developer | `davin-eza@mahasiswa.ubb.ac.id` | ☐ |
| Tenant | `DEMO` | ☐ |
| Company | `KM` | ☐ |
| Branch | `MTG` | ☐ |
| Warehouse | `MAIN` | ☐ |
| Role definitions | Tersedia, tanpa user demo lain | ☐ |
| Products | Produk demo tersedia | ☐ |
| Suppliers | Supplier demo tersedia | ☐ |
| Customers | Customer demo bila schema mendukung | ☐ |
| ERP Accounts | COA dasar tersedia | ☐ |
| Budget | Tahun berjalan tersedia | ☐ |
| Approval Matrix | Purchasing + Sales tersedia | ☐ |
| Operational transactions | Belum ada transaksi UAT | ☐ |

## 3. Fase A — Login & Session

### A1 Login berhasil

1. Buka Admin Login.
2. Login sebagai Developer.
3. Pastikan redirect ke Dashboard.
4. Pastikan session/token terbentuk.
5. Refresh halaman.
6. Pastikan tetap login.

### A2 Login gagal

1. Logout.
2. Masukkan password salah.
3. Pastikan error tampil.
4. Pastikan dashboard tidak dapat diakses.

### A3 Logout

1. Login lagi.
2. Logout.
3. Pastikan kembali ke login.
4. Coba buka halaman private secara langsung.

## 4. Fase B — Developer Console & Organization

Periksa Developer Console: tenant, company, branch, license, subscription, tenant admin, permissions, dan license events.

Uji perubahan yang tersedia dan pastikan data berubah setelah save lalu tetap benar setelah refresh.

Uji organization context dan pastikan data mengikuti tenant/company/branch/location context yang aktif.

## 5. Fase C — RBAC

Baseline hanya memiliki Developer. Untuk negative authorization test, buat satu akun uji non-developer sementara dari Developer Console/Users, berikan role minimum, login melalui browser/incognito terpisah, lalu uji endpoint/menu yang seharusnya ditolak. Hapus akun uji setelah selesai.

Catatan: Developer tidak cocok untuk negative permission test karena akses developer memang lebih tinggi.

## 6. Fase D — Master Data & Inventory

### D1 Category

Uji create, read, update jika tersedia.

### D2 Product

Uji create/edit, activate/deactivate, category, harga, dan tampilan stock.

### D3 Import Menu Excel

1. Siapkan file xlsx kecil dengan row valid.
2. Import.
3. Periksa hasil sukses/gagal.
4. Periksa product yang terbentuk/berubah.
5. Ulangi dengan row invalid.

Expected: data valid masuk; data invalid ditolak dengan pesan jelas.

### D4 Inventory Operations

Uji stock adjustment dan catat sebelum/sesudah quantity, reserved quantity, available quantity, dan costing bila tampil. Pastikan audit trail tercatat.

## 7. Fase E — Purchasing End-to-End

Happy path:

`Supplier → Requisition → Submit → Purchase Order → Submit → Approve → Goods Receipt → Supplier Invoice → Supplier Payment`

### E1 Supplier

Buat supplier UAT.

### E2 Requisition

Buat requisition dengan product, quantity, warehouse.

### E3 Requisition Submit

Submit dan verifikasi status.

### E4 Purchase Order

Buat PO dan uji supplier, warehouse, item, quantity, unit cost, discount, tax/PPN, grand total.

### E5 Approval State Machine

Uji `Draft → Submit → Approve`.

Buat dokumen lain untuk Reject dan Cancel. Action ilegal harus ditolak.

### E6 Goods Receipt

Buat receipt dari PO. Verifikasi quantity diterima, outstanding PO, inventory balance, dan histori. Coba receipt melebihi quantity yang diperbolehkan.

### E7 Supplier Invoice

Verifikasi invoice number, invoice date, due date, amount/outstanding, dan hubungan dengan goods receipt.

### E8 Supplier Payment

Uji pembayaran sebagian lalu penuh bila tersedia. Outstanding dan status invoice harus mengikuti saldo. Periksa journal accounting.

### E9 Return + Credit Note

Uji supplier return dari receipt valid, dampak inventory, dan credit note bila tersedia.

### E10 Purchasing Reports

Periksa dashboard purchasing, supplier performance, AP aging, PO reconciliation, budget, dan approval matrix. Angka harus konsisten dengan transaksi UAT.

## 8. Fase F — Sales & Fulfillment

Uji berurutan:

`Sales Order → Submit → Approve → Fulfillment → Pick → Pack → Shipment → Sales Invoice → Customer Payment`

Tambahkan negative test Reject/Cancel dan Sales Return.

Verifikasi quantity, price, discount, tax, stock movement, outstanding, receivable, dan journal.

## 9. Fase G — POS

Uji transaksi:

- satu item
- beberapa item
- quantity > 1
- discount
- PPN
- metode pembayaran yang tersedia

Verifikasi subtotal, discount, tax, grand total, payment, change, stock movement, dan journal.

### Baseline nota

Sebelum ada perubahan fitur nota, simpan screenshot nota saat ini dan catat seluruh elemen yang tampil:

- logo
- nama perusahaan
- alamat
- branch
- nomor transaksi
- tanggal/waktu
- kasir
- item
- qty
- harga
- discount
- PPN
- total
- metode pembayaran
- footer
- informasi tambahan lain

## 10. Fase H — Finance & Accounting

Uji:

- ERP Accounts
- ERP Journals
- Trial Balance
- Profit & Loss
- Balance Sheet
- Cash Book
- Reconciliation

### Fiscal Closing

1. Pastikan transaksi periode uji selesai.
2. Close fiscal period.
3. Verifikasi status closed.
4. Coba membuat/post transaksi bertanggal pada periode tertutup.
5. Pastikan sistem menolak posting ilegal.

## 11. Fase I — HRM & Payroll

Uji Employees.

Uji Attendance:

- clock-in
- clock-out
- hadir
- sakit
- terlambat
- absen
- pulang cepat bila tersedia
- off-duty
- export CSV

Uji attendance rules, grace period, penalty, payroll automation, payroll calculation, dan payroll accounting.

## 12. Fase J — Business Rules & Receipt Settings

Periksa attendance settings, penalty settings, bill template, dan PPN configuration.

Untuk setiap perubahan: Save → Refresh → pastikan tersimpan → jalankan transaksi terkait → verifikasi efeknya.

## 13. Fase K — Audit & Security

Periksa audit trail untuk login/logout, master data changes, create/update/delete, approval/rejection, payment, stock adjustment, closing, context switching, dan developer actions.

Pastikan:

- endpoint terlarang ditolak
- tenant/company/branch scope konsisten
- closed period memblokir posting ilegal
- developer controls tidak dapat digunakan user biasa
- tidak ada cross-tenant data leak

## 14. Fase L — Regression

```bash
cd pos-menteng-backend
php artisan test

cd ../pos-menteng-frontend
npm ci
npm run build
npm run lint
```

Target minimum: backend pass, frontend build pass, lint exit code 0, tidak ada error JavaScript fatal, dan tidak ada request API kritikal yang gagal.

## 15. Format Laporan Hasil

### PASS

```text
CASE: E6 Goods Receipt
STATUS: PASS
EXPECTED: Receipt berhasil dan stok bertambah.
ACTUAL: Receipt berhasil dan stok bertambah 10.
DATA TERBENTUK: PO=..., Receipt=...
SCREENSHOT: ...
```

### FAIL

```text
CASE: E6 Goods Receipt
STATUS: FAIL
URL: /purchasing
LANGKAH:
1. Pilih PO ...
2. Pilih warehouse ...
3. Input qty ...
4. Klik Save
EXPECTED:
Receipt berhasil dan stok bertambah.
ACTUAL:
500 Internal Server Error.
ERROR:
[paste error]
SCREENSHOT:
[attach screenshot]
ID DATA:
PO=...
Receipt=...
```

## 16. Prioritas Bug

P0: data hilang/korup, security bypass, cross-tenant leak, accounting fatal.

P1: transaksi utama gagal, stock tidak sinkron, journal tidak terbentuk, closing dapat dibypass.

P2: fungsi berjalan tetapi hasil/validasi/UI salah.

P3: kosmetik, typo, warning, ergonomi.

## 17. Setelah UAT Selesai

Kirim seluruh hasil PASS/FAIL, screenshot, error, dan ID transaksi. Jangan menutup bug hanya berdasarkan tampilan UI; cocokkan dengan database/API dan accounting impact.

Pekerjaan nota tahap berikutnya akan mencakup upload logo perusahaan sebagai file, validasi file, storage berdasarkan scope organisasi, preview logo, logo pada nota, editor semua elemen configurable, template untuk POS, preservasi nota historis, layout thermal printer, konsistensi PPN/discount/payment, serta audit log perubahan template.
