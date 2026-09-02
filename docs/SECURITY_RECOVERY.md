# KOPI MENTENG ERP — SECURITY & RECOVERY PLAYBOOK

## 1. Prinsip utama

Sistem **tidak menyediakan secret backdoor, magic password, hidden URL, atau bypass permission tersembunyi**.

Recovery harus menggunakan kontrol yang dapat diaudit:
- autentikasi normal;
- permission dan organization scope;
- platform-admin untuk operasi organisasi yang memang membutuhkan hak platform;
- audit log;
- backup dan prosedur restore;
- rotasi credential/token ketika terjadi kompromi.

Developer Console bukan backdoor. Route platform memakai `platform.admin`, dan frontend developer route juga mensyaratkan akun developer.

## 2. Saat akun admin dicurigai dikompromikan

1. Cabut token/session pengguna melalui prosedur admin yang tersedia.
2. Nonaktifkan atau koreksi membership/permission menggunakan jalur administrasi resmi.
3. Rotasikan credential provider yang dicurigai, termasuk token WhatsApp/provider dan secret payment bila relevan.
4. Audit aktivitas pengguna berdasarkan audit log dan request ID.
5. Periksa perubahan organization context, payroll, attendance, dan finance.
6. Restore dari backup hanya jika integritas database tidak dapat dipercaya.

## 3. Recovery akses administrator

Jika seluruh akun aplikasi kehilangan akses, lakukan recovery dari lingkungan server/database yang dikendalikan organisasi menggunakan prosedur operasional internal. Buat atau pulihkan akun administrator secara langsung pada database setelah operator terverifikasi, kemudian segera:

- ubah password;
- rotasikan session/token;
- validasi membership tenant/company/branch;
- validasi role/permission;
- cek audit log;
- hapus akses sementara yang tidak diperlukan.

Jangan menambahkan password universal atau endpoint tersembunyi untuk recovery.

## 4. Database safety

Sebelum migrasi besar atau recovery:
- backup database;
- simpan backup di lokasi terpisah;
- validasi kemampuan restore;
- catat waktu, operator, dan tujuan recovery.

Finance/ERP data harus dikembalikan melalui backup atau prosedur koreksi yang dapat diaudit, bukan dengan menghapus jejak transaksi.

## 5. File dan secret

Jangan menaruh secret produksi di source control. Gunakan environment/runtime secret storage untuk:
- `APP_KEY`;
- database credentials;
- Sanctum/session credentials;
- payment credentials;
- WhatsApp/Twilio credentials.

PDF payroll yang berisi informasi sensitif harus disimpan pada storage yang sesuai kebijakan organisasi dan tidak dibuat publicly accessible kecuali provider WhatsApp memang memerlukan media URL yang dapat dijangkau secara aman.

## 6. Queue recovery

Untuk payroll WhatsApp:

```bash
php artisan queue:work
```

Jika job gagal, periksa queue failed jobs, error message notification, dan audit log sebelum retry.

Jangan mengubah status payroll kembali menjadi unpaid hanya untuk memicu ulang pengiriman. Gunakan mekanisme notification/retry yang memang tersedia.

## 7. Audit checklist setelah recovery

Pastikan:
- akun yang dipulihkan memiliki role yang benar;
- context tenant/company/branch/location benar;
- tidak ada permission tambahan yang tidak diperlukan;
- tidak ada perubahan payroll tanpa jejak audit;
- fiscal period tetap konsisten;
- tidak ada legacy accounting write path yang aktif;
- full regression test dijalankan sebelum deployment.

## 8. Incident record

Setiap recovery sebaiknya dicatat dengan:
- tanggal/waktu;
- operator;
- incident ID;
- penyebab;
- data/sistem yang terdampak;
- tindakan yang dilakukan;
- backup yang digunakan;
- hasil verifikasi;
- tindakan pencegahan berikutnya.

Dokumen ini sengaja menghindari secret backdoor. Recovery yang dapat diaudit jauh lebih aman daripada akses tersembunyi yang sulit dikendalikan.
