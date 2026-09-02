<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Slip Gaji {{ $payroll->period }}</title>
    <style>
        @page { margin: 24px; }
        body { font-family: DejaVu Sans, sans-serif; color: #292524; font-size: 12px; }
        h1 { margin: 0 0 4px; font-size: 20px; }
        .muted { color: #78716c; }
        .box { border: 1px solid #d6d3d1; padding: 12px; margin: 12px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 6px 0; vertical-align: top; }
        .label { color: #78716c; width: 42%; }
        .amount { text-align: right; }
        .total { border-top: 2px solid #44403c; font-weight: 700; font-size: 14px; }
    </style>
</head>
<body>
    <h1>KOPI MENTENG</h1>
    <div class="muted">Slip Gaji / Payroll Slip</div>

    <div class="box">
        <table>
            <tr><td class="label">Periode</td><td>{{ $payroll->period }}</td></tr>
            <tr><td class="label">Karyawan</td><td>{{ $payroll->employee?->name ?? 'Karyawan' }}</td></tr>
            <tr><td class="label">Jabatan</td><td>{{ $payroll->employee?->position ?? '-' }}</td></tr>
            <tr><td class="label">Status Pembayaran</td><td>{{ $payroll->is_paid ? 'PAID' : 'PENDING' }}</td></tr>
        </table>
    </div>

    <div class="box">
        <table>
            <tr><td>Gaji Pokok</td><td class="amount">Rp {{ number_format((float) $payroll->base_salary, 0, ',', '.') }}</td></tr>
            <tr><td>Tunjangan</td><td class="amount">Rp {{ number_format((float) $payroll->allowance, 0, ',', '.') }}</td></tr>
            <tr><td>Potongan</td><td class="amount">Rp {{ number_format((float) $payroll->deduction, 0, ',', '.') }}</td></tr>
            <tr class="total"><td>Total Gaji</td><td class="amount">Rp {{ number_format((float) $payroll->total_salary, 0, ',', '.') }}</td></tr>
        </table>
    </div>

    <div class="box">
        <div><strong>Ringkasan Kehadiran</strong></div>
        <table>
            <tr><td class="label">Hari tercatat</td><td>{{ $attendanceSummary['attendance_days'] }}</td></tr>
            <tr><td class="label">Hari terlambat</td><td>{{ $attendanceSummary['late_days'] }}</td></tr>
            <tr><td class="label">Menit terlambat</td><td>{{ $attendanceSummary['late_minutes'] }}</td></tr>
            <tr><td class="label">Hari tidak hadir</td><td>{{ $attendanceSummary['absence_days'] }}</td></tr>
            <tr><td class="label">Hari sakit</td><td>{{ $attendanceSummary['sick_days'] }}</td></tr>
        </table>
    </div>

    <div class="muted">Dokumen ini dibuat otomatis oleh sistem Kopi Menteng.</div>
</body>
</html>
