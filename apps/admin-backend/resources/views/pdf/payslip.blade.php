<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Slip Gaji {{ $payslip->reference_code }}</title>
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
  .header { text-align: center; border-bottom: 2px solid #059669; padding-bottom: 10px; margin-bottom: 16px; }
  .header h1 { margin: 0; font-size: 18px; color: #059669; }
  .header p { margin: 2px 0; font-size: 11px; color: #555; }
  table.info { width: 100%; margin-bottom: 14px; }
  table.info td { padding: 3px 4px; vertical-align: top; }
  table.items { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
  table.items th, table.items td { border: 1px solid #ccc; padding: 7px 8px; }
  table.items th { background: #ecfdf5; text-align: left; }
  td.num, th.num { text-align: right; }
  .total td { font-weight: bold; background: #f0fdf4; font-size: 13px; }
  .footer { margin-top: 24px; font-size: 10px; color: #666; }
  .sign { margin-top: 40px; display: flex; justify-content: flex-end; }
</style>
</head>
<body>
  <div class="header">
    <h1>SLIP GAJI</h1>
    <p><strong>{{ $company['name'] }}</strong></p>
    <p>{{ $company['address'] }} &bull; {{ $company['phone'] }}</p>
  </div>

  <table class="info">
    <tr>
      <td style="width:25%">No. Referensi</td><td style="width:25%">: <strong>{{ $payslip->reference_code }}</strong></td>
      <td style="width:25%">Periode</td><td>: {{ $period->name }}</td>
    </tr>
    <tr>
      <td>Nama</td><td>: <strong>{{ $employee->name }}</strong></td>
      <td>Tanggal Terbit</td><td>: {{ $payslip->published_at?->format('d M Y') ?? '-' }}</td>
    </tr>
    <tr>
      <td>Kode Karyawan</td><td>: {{ $employee->employee_code }}</td>
      <td>Hari Kerja</td><td>: {{ $item->work_days }} hari</td>
    </tr>
    <tr>
      <td>Jabatan</td><td>: {{ $employee->position ?? '-' }}</td>
      <td></td><td></td>
    </tr>
  </table>

  <table class="items">
    <thead>
      <tr><th>Uraian</th><th class="num">Jumlah (Rp)</th></tr>
    </thead>
    <tbody>
      <tr><td>Gaji Pokok</td><td class="num">{{ number_format($item->base_salary, 0, ',', '.') }}</td></tr>
      <tr><td>Insentif Kehadiran</td><td class="num">{{ number_format($item->attendance_comp, 0, ',', '.') }}</td></tr>
      <tr><td>Lembur</td><td class="num">{{ number_format($item->overtime, 0, ',', '.') }}</td></tr>
      <tr><td>Tunjangan</td><td class="num">{{ number_format($item->allowances, 0, ',', '.') }}</td></tr>
      <tr><td>Bonus</td><td class="num">{{ number_format($item->bonuses, 0, ',', '.') }}</td></tr>
      <tr><td>Reimburse Disetujui</td><td class="num">{{ number_format($item->reimbursements, 0, ',', '.') }}</td></tr>
      <tr><td>Potongan</td><td class="num">({{ number_format($item->deductions, 0, ',', '.') }})</td></tr>
      <tr class="total"><td>Total Diterima</td><td class="num">Rp {{ number_format($item->net, 0, ',', '.') }}</td></tr>
    </tbody>
  </table>

  <div class="footer">
    <p>Dokumen ini diterbitkan secara elektronik dan sah tanpa tanda tangan basah.<br>
    Referensi: {{ $payslip->reference_code }} &bull; Diterbitkan: {{ $payslip->published_at?->format('d M Y H:i') ?? '-' }} WIB</p>
  </div>
</body>
</html>
