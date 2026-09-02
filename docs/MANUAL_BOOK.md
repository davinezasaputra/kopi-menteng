# KOPI MENTENG ERP — MANUAL BOOK

## 1. Login dan Organization Context

1. Login memakai akun yang telah diberikan akses.
2. Setelah login, pilih `Organization Scope` pada sidebar bila user memiliki lebih dari satu membership.
3. Semua data mengikuti tenant, company, branch, dan location aktif. Jangan mengubah ID context melalui request manual; backend tetap menjadi sumber otorisasi.

## 2. Karyawan

Buka `HRM → Karyawan` untuk melihat dan mengelola data karyawan sesuai permission.

## 3. Attendance

Buka `HRM → Kontrol Absensi`.

### Clock-in
Pilih karyawan, lalu tekan `Clock-in`. Sistem memakai aturan jam masuk dan toleransi pada `Administration → Business Rules → Attendance Rules`. Bila melewati batas toleransi, status otomatis menjadi `terlambat` dan menit keterlambatan dicatat.

### Clock-out
Pilih karyawan, lalu tekan `Clock-out`. Sistem mencatat waktu pulang. Bila pulang sebelum aturan yang ditetapkan, menit pulang cepat dicatat dan status dapat menjadi `pulang_cepat`.

### Ubah status manual
Pada kolom Action pilih:
- `Hadir`
- `Sakit`
- `Late`
- `Absence`

Perubahan dicatat ke audit log.

### Off-duty satu karyawan
Tekan `Off-duty`, pilih satu karyawan, tanggal, dan alasan/catatan. Sistem membuat atau memperbarui attendance tanggal tersebut menjadi `offduty`.

### Export attendance
Pilih periode bulan, kemudian tekan `Export CSV`. File berisi tanggal, nama karyawan, jabatan, status, clock-in, clock-out, keterlambatan, pulang cepat, dan catatan.

## 4. Payroll

Buka `HRM → HRD & Penggajian`.

### Auto-fill payroll
Saat membuat slip gaji, sistem dapat menjalankan auto-fill. Data diambil dari gaji pokok karyawan dan attendance pada periode payroll. Potongan attendance dihitung berdasarkan rule denda yang aktif.

### Membayar payroll
Tekan `Bayar`. Pembayaran diproses bersama jurnal ERP sesuai aturan finance closing.

Setelah status menjadi paid, automation payroll dapat:
1. membuat PDF slip gaji;
2. membuat antrean WhatsApp;
3. mengirim PDF sebagai attachment/media melalui provider WhatsApp yang telah dikonfigurasi;
4. menyimpan status dan error pengiriman.

### Monitoring WhatsApp
Buka tab `WhatsApp` pada halaman HRM untuk melihat status queued, processing, sent, atau failed.

## 5. Menu Import Excel

Buka `ERP → Inventory → Import Menu Excel`.

1. Download template CSV.
2. Isi `name` dan `price` sebagai kolom wajib.
3. `category`, `description`, dan `is_active` bersifat opsional.
4. Upload file `.xlsx`, `.xls`, atau `.csv` maksimal 5 MB.
5. Klik `Mulai Import Menu`.

Produk dengan nama sama dalam tenant aktif akan diperbarui. Produk baru dibuat sebagai record baru. Hasil import menampilkan jumlah dibuat, diperbarui, dan gagal.

## 6. Bill Template dan PPN

Buka `Administration → Business Rules → Bill Template & PPN`.

Pengaturan yang tersedia:
- judul bill;
- subjudul;
- nama usaha;
- alamat;
- footer;
- PPN (%);
- tampilkan PPN;
- tampilkan diskon;
- tampilkan kasir;
- status template aktif.

PPN yang disimpan digunakan pada kalkulasi checkout POS untuk memisahkan DPP dan pajak dari harga jual yang sudah termasuk pajak.

## 7. Attendance Rules dan Denda

Pada tab `Attendance Rules`, atur:
- jam clock-in;
- toleransi clock-in;
- jam clock-out;
- toleransi clock-out;
- opsi auto absence;
- rule denda keterlambatan;
- rule denda absence.

`Fixed` berarti nominal tetap. `Percentage` berarti persentase dari gaji pokok.

## 8. WhatsApp Troubleshooting

Jika payroll sudah paid tetapi WhatsApp gagal:
- pastikan queue worker berjalan;
- pastikan variabel Twilio lengkap;
- pastikan `APP_URL` atau `WHATSAPP_MEDIA_BASE_URL` menghasilkan URL PDF yang dapat diakses provider;
- cek tab WhatsApp untuk pesan error;
- periksa audit log bila kegagalan perlu dilacak.

## 9. Operasional Harian yang Disarankan

Pagi: buka shift, clock-in karyawan, dan periksa attendance.

Siang/sore: lakukan perubahan status manual hanya saat diperlukan dan isi catatan.

Akhir hari: clock-out dan periksa pulang cepat.

Akhir periode: generate/auto-fill payroll, review potongan, bayar payroll, lalu monitor antrean WhatsApp.

Untuk perubahan harga atau pajak: update Business Rules sebelum transaksi berikutnya dan validasi preview bill.
