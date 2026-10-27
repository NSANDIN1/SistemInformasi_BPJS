<?php
// api.php - unggah dan daftar file Excel / PDF Monthly Report Divisi Pelayanan.
//   GET  api.php?aksi=daftar   -> daftar file yang pernah diunggah
//   POST api.php?aksi=unggah   -> field "file" (.xlsx .xls .pdf) dan "bulan" (0-12, opsional, untuk PDF)
// File disimpan permanen di pelayanan/uploads/, catatan unggahan di database/pelayanan.db (SQLite,
// sama seperti Kearsipan), dan isi laporan ditulis ke js/data.js oleh tools/proses_unggahan.py.
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Jakarta');

const MAKS_UKURAN = 25 * 1024 * 1024;   // 25 MB

function keluar($data, $kode = 200) { http_response_code($kode); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }

$dirUtama = dirname(__DIR__);
$dirDb = $dirUtama . '/database';
$dirUnggah = __DIR__ . '/uploads';
foreach ([$dirDb, $dirUnggah] as $d) { if (!is_dir($d)) { @mkdir($d, 0777, true); } }

try {
    $db = new PDO('sqlite:' . $dirDb . '/pelayanan.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec("CREATE TABLE IF NOT EXISTS unggahan (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nama_asli VARCHAR(255) NOT NULL,
        nama_simpan VARCHAR(255) NOT NULL,
        jenis VARCHAR(10) NOT NULL,
        bulan VARCHAR(40) NULL,
        ukuran INT NOT NULL,
        tgl_unggah DATETIME NOT NULL,
        status VARCHAR(10) NOT NULL,
        pesan VARCHAR(500) NULL
    )");
} catch (Exception $e) {
    keluar(['ok' => false, 'pesan' => 'Database tidak bisa dibuka (pastikan PHP mendukung SQLite dan folder database bisa ditulis).'], 500);
}

$aksi = $_GET['aksi'] ?? 'daftar';

if ($aksi === 'daftar') {
    $baris = $db->query('SELECT id, nama_asli, nama_simpan, jenis, bulan, ukuran, tgl_unggah, status, pesan
                         FROM unggahan ORDER BY id DESC LIMIT 100')->fetchAll();
    keluar(['ok' => true, 'daftar' => $baris]);
}

if ($aksi !== 'unggah' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    keluar(['ok' => false, 'pesan' => 'Permintaan tidak dikenal.'], 400);
}

// ---------- validasi file
$f = $_FILES['file'] ?? null;
if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) { keluar(['ok' => false, 'pesan' => 'Belum ada file yang dipilih.'], 400); }
if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE || $f['size'] > MAKS_UKURAN) {
    keluar(['ok' => false, 'pesan' => 'Ukuran file terlalu besar (maksimal 25 MB, atau batas upload_max_filesize di php.ini).'], 400);
}
if ($f['error'] !== UPLOAD_ERR_OK) { keluar(['ok' => false, 'pesan' => 'File gagal diunggah (kode ' . (int)$f['error'] . ').'], 400); }

$namaAsli = basename(str_replace('\\', '/', $f['name']));
$ext = strtolower(pathinfo($namaAsli, PATHINFO_EXTENSION));
if (!in_array($ext, ['xlsx', 'xls', 'pdf'], true)) {
    keluar(['ok' => false, 'pesan' => 'Format file harus .xlsx, .xls, atau .pdf.'], 400);
}
$kepala = file_get_contents($f['tmp_name'], false, null, 0, 8);
$cocok = ($ext === 'pdf' && strncmp($kepala, '%PDF', 4) === 0)
      || ($ext === 'xlsx' && strncmp($kepala, "PK", 2) === 0)
      || ($ext === 'xls' && strncmp($kepala, "\xD0\xCF\x11\xE0", 4) === 0);
if (!$cocok) { keluar(['ok' => false, 'pesan' => 'Isi file tidak sesuai dengan ekstensinya (' . $ext . ').'], 400); }

$bulanNama = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$bulanPilih = (int)($_POST['bulan'] ?? 0);
if ($bulanPilih < 0 || $bulanPilih > 12) { $bulanPilih = 0; }

// ---------- simpan permanen (nama acak agar tidak bisa menimpa / ditebak)
$namaSimpan = date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
$tujuan = $dirUnggah . '/' . $namaSimpan;
if (!move_uploaded_file($f['tmp_name'], $tujuan)) {
    keluar(['ok' => false, 'pesan' => 'File tidak bisa disimpan. Pastikan folder pelayanan/uploads bisa ditulis.'], 500);
}

// ---------- baca isi file (Python memakai alat yang sudah ada), satu proses pada satu waktu
$hasil = ['ok' => false, 'pesan' => 'Fungsi exec() dinonaktifkan di php.ini, jadi isi file belum bisa dibaca. File tetap tersimpan.'];
if (function_exists('exec') && stripos((string)ini_get('disable_functions'), 'exec') === false) {
    $skrip = __DIR__ . '/tools/proses_unggahan.py';
    $jenisProses = $ext === 'pdf' ? 'pdf' : 'excel';
    $args = escapeshellarg($skrip) . ' ' . $jenisProses . ' ' . escapeshellarg($tujuan) . ' ' . $bulanPilih
          . ' ' . escapeshellarg('uploads/' . $namaSimpan) . ' ' . escapeshellarg($namaAsli);
    $kunci = fopen($dirDb . '/pelayanan.lock', 'c');
    if ($kunci) { flock($kunci, LOCK_EX); }
    $hasil = ['ok' => false, 'pesan' => 'Python belum terpasang atau tidak ditemukan di server. File tetap tersimpan.'];
    foreach (['python3', 'python', 'py -3'] as $py) {
        $out = []; $kode = 0;
        @exec($py . ' ' . $args . ' 2>&1', $out, $kode);
        $baris = trim((string)end($out));
        $j = json_decode($baris, true);
        if (is_array($j) && isset($j['ok'])) { $hasil = $j; break; }
        if ($out) { $hasil = ['ok' => false, 'pesan' => 'Gagal menjalankan pembaca data: ' . mb_substr(implode(' ', $out), 0, 200)]; }
    }
    if ($kunci) { flock($kunci, LOCK_UN); fclose($kunci); }
}

// ---------- catat
$bulanCatat = null;
if (!empty($hasil['bulan'])) {
    $bulanCatat = implode(', ', array_map(function ($b) use ($bulanNama) { return $bulanNama[(int)$b] ?? $b; }, $hasil['bulan']));
}
$stmt = $db->prepare('INSERT INTO unggahan (nama_asli, nama_simpan, jenis, bulan, ukuran, tgl_unggah, status, pesan)
                      VALUES (?,?,?,?,?,?,?,?)');
$stmt->execute([$namaAsli, $namaSimpan, $ext === 'pdf' ? 'PDF' : 'Excel', $bulanCatat, (int)$f['size'],
                date('Y-m-d H:i:s'), $hasil['ok'] ? 'berhasil' : 'gagal', mb_substr((string)$hasil['pesan'], 0, 500)]);

keluar(['ok' => (bool)$hasil['ok'], 'pesan' => $hasil['pesan'], 'tersimpan' => true, 'bulan' => $bulanCatat]);
