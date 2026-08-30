# Sistem Manajemen Cuti (Leave Management System)

## Overview
Sistem leave management yang terintegrasi dengan attendance tracking. Ketika cuti disetujui, sistem otomatis membuat attendance record untuk setiap hari cuti.

## Fitur Utama

### 1. Jenis-jenis Cuti
- **cuti_tahunan**: Cuti tahunan/liburan
- **sakit**: Cuti karena sakit
- **izin**: Izin (keluarga, acara, dll)
- **libur_nasional**: Hari libur nasional
- **lainnya**: Jenis cuti lainnya

### 2. Status Leave
- **pending**: Menunggu persetujuan
- **approved**: Sudah disetujui
- **rejected**: Ditolak

### 3. Attendance Status
Setelah leave disetujui, status attendance otomatis berubah menjadi jenis cuti:
- `hadir`: Present
- `terlambat`: Late
- `izin`: Izin
- `sakit`: Sakit
- `cuti_tahunan`: Cuti tahunan
- `libur_nasional`: Libur nasional
- `lainnya`: Lainnya

## API Endpoints

### Leave Management

#### 1. List Semua Leave
```
GET /api/leaves
Query Parameters:
  - employee_id: UUID (filter by employee)
  - status: pending|approved|rejected
  - month: 1-12 (filter by month)

Response:
{
  "success": true,
  "data": [
    {
      "id": 1,
      "employee_id": "uuid...",
      "type": "cuti_tahunan",
      "start_date": "2026-09-01",
      "end_date": "2026-09-05",
      "reason": "Liburan keluarga",
      "status": "pending",
      "approved_at": null,
      "employee": { ... }
    }
  ]
}
```

#### 2. Buat Pengajuan Cuti
```
POST /api/leaves
Body:
{
  "employee_id": "uuid...",
  "type": "cuti_tahunan|sakit|izin|libur_nasional|lainnya",
  "start_date": "2026-09-01",
  "end_date": "2026-09-05",
  "reason": "Liburan keluarga" (optional)
}

Response:
{
  "success": true,
  "message": "Leave request created successfully",
  "data": { ... }
}
```

#### 3. Lihat Detail Leave
```
GET /api/leaves/{leave_id}

Response:
{
  "success": true,
  "data": { ... }
}
```

#### 4. Setujui Leave
```
POST /api/leaves/{leave_id}/approve

Otomatis:
- Update status menjadi 'approved'
- Set approved_at timestamp
- Set approved_by (authenticated user)
- Buat attendance records untuk setiap hari di periode cuti
- Status attendance otomatis mengikuti jenis cuti

Response:
{
  "success": true,
  "message": "Leave approved successfully",
  "data": { ... }
}
```

#### 5. Tolak Leave
```
POST /api/leaves/{leave_id}/reject

Response:
{
  "success": true,
  "message": "Leave rejected successfully",
  "data": { ... }
}
```

#### 6. Hapus Leave
```
DELETE /api/leaves/{leave_id}

Note: Jika leave sudah approved, akan menghapus attendance records yang terkait
```

### Attendance Report

#### 7. Get Attendance Report untuk Bulan Tertentu
```
GET /api/leaves/attendance/report
Query Parameters:
  - employee_id: UUID (required)
  - month: 1-12 (required)
  - year: 2026 (required)

Response:
{
  "success": true,
  "data": {
    "attendance": [
      {
        "date": "2026-09-01",
        "status": "cuti_tahunan",
        "clock_in": "00:00:00",
        "clock_out": null,
        "late_minute": 0,
        "is_leave": true,
        "notes": "Liburan keluarga"
      },
      {
        "date": "2026-09-02",
        "status": "hadir",
        "clock_in": "08:05:30",
        "clock_out": "17:30:00",
        "late_minute": 5,
        "is_leave": false,
        "notes": null
      }
    ],
    "summary": {
      "total_workdays": 20,
      "total_late": 2,
      "total_leave": 5,
      "breakdown": {
        "hadir": 20,
        "cuti_tahunan": 5,
        "terlambat": 2
      }
    }
  }
}
```

## Workflow Contoh

### Scenario: Karyawan Mengajukan Cuti

1. **Employee submit leave request**
```bash
curl -X POST http://localhost:8000/api/leaves \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "employee_id": "123e4567-e89b-12d3-a456-426614174000",
    "type": "cuti_tahunan",
    "start_date": "2026-09-01",
    "end_date": "2026-09-05",
    "reason": "Liburan keluarga"
  }'
```

2. **Manager melihat leave requests yang pending**
```bash
curl "http://localhost:8000/api/leaves?status=pending" \
  -H "Authorization: Bearer {token}"
```

3. **Manager approve leave**
```bash
curl -X POST http://localhost:8000/api/leaves/1/approve \
  -H "Authorization: Bearer {token}"
```

**Otomatis terjadi:**
- Leave status berubah menjadi `approved`
- Attendance records dibuat untuk tanggal 2026-09-01 s/d 2026-09-05
- Setiap attendance record memiliki status `cuti_tahunan`
- Attendance records dilinkkan ke leave record via `leave_id`

4. **Cek attendance report**
```bash
curl "http://localhost:8000/api/leaves/attendance/report?employee_id=123e4567-e89b-12d3-a456-426614174000&month=9&year=2026" \
  -H "Authorization: Bearer {token}"
```

Response akan menunjukkan:
- 5 hari dengan status `cuti_tahunan`
- Summary menampilkan `total_leave: 5`

## Model Relationships

### Employee
```php
$employee->leaves()      // Semua leave requests
$employee->attendances() // Semua attendance records
```

### Leave
```php
$leave->employee()       // Relasi ke employee
$leave->approvedBy()     // User yang approve (jika sudah disetujui)
```

### Attendance
```php
$attendance->employee()  // Relasi ke employee
$attendance->leave()     // Relasi ke leave (jika ada)
$attendance->isLeave()   // Check jika status adalah leave type
$attendance->isPresent() // Check jika status adalah 'hadir'
$attendance->isLate()    // Check jika late
```

## Helper Methods

### Leave Model
```php
// Check apakah employee sedang cuti di tanggal tertentu
Leave::isOnLeave($employeeId, $date);

// Get leave type untuk tanggal tertentu
Leave::getLeaveType($employeeId, $date);
```

### Attendance Model
```php
// Check jika attendance adalah leave
$attendance->isLeave();

// Check jika attendance hadir
$attendance->isPresent();

// Check jika attendance terlambat
$attendance->isLate();
```

## Database Schema

### leaves table
```
id              bigint (PK)
employee_id     uuid (FK to employees)
type            enum (cuti_tahunan, sakit, izin, libur_nasional, lainnya)
start_date      date
end_date        date
reason          text (nullable)
status          enum (pending, approved, rejected)
approved_at     timestamp (nullable)
approved_by     bigint (FK to users, nullable)
created_at      timestamp
updated_at      timestamp

Unique Index: [employee_id, start_date]
```

### attendances table (updated)
```
id              bigint (PK)
employee_id     uuid (FK to employees)
tanggal         date
clock_in        datetime
clock_out       datetime (nullable)
status          varchar (hadir, terlambat, izin, sakit, cuti_tahunan, libur_nasional, lainnya)
late_minute     int
leave_id        bigint (FK to leaves, nullable) ← ADDED
notes           text (nullable) ← ADDED
created_at      timestamp
updated_at      timestamp

Unique Index: [employee_id, tanggal]
```

## Automatic Features (Observer)

### LeaveObserver
- **Updated Event**: Ketika leave di-approve, otomatis buat attendance records
  - Untuk setiap hari dalam range cuti
  - Set status attendance = leave type
  - Linkkan dengan leave_id
  - Copy reason ke attendance notes
  
- **Deleted Event**: Ketika leave dihapus, otomatis hapus attendance records yang terkait

## Payroll Integration

Saat generate payroll, sistem akan:
1. Query attendance untuk bulan tersebut
2. Filter attendance dengan `isLeave()` == true
3. Hitung working days (status = 'hadir')
4. Potong gaji sesuai jenis cuti (configurable)

## Audit Trail

Setiap leave record mencatat:
- `created_at`: Tanggal pengajuan
- `approved_at`: Tanggal persetujuan
- `approved_by`: User yang approve (untuk audit)

## Best Practices

1. **Always validate dates**: Pastikan end_date >= start_date
2. **Check existing leaves**: Cegah duplicate leave requests untuk periode yang sama
3. **Reason is important**: Catat alasan untuk keperluan audit dan HR reports
4. **Soft delete consideration**: Pertimbangkan soft delete untuk leave records yang sudah diproses
5. **Notification**: Implement notification ketika leave di-approve/reject

## Future Enhancements

1. Leave balance tracking (cuti tahunan quota)
2. Approval workflow multi-level (department head → HR → Director)
3. Email notifications
4. Leave replacement/substitution handling
5. Annual leave carryover policies
6. Integration with public holiday calendar
