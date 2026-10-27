<?php
// Koneksi ke SQLite (satu file database, tanpa MySQL / XAMPP)
// Folder database ada di folder utama website (sejajar dengan folder php), bukan di dalam php.
$file_db = dirname(__DIR__) . "/database/kearsipan.db";
if (!is_dir(dirname(__DIR__) . "/database")) {
    mkdir(dirname(__DIR__) . "/database", 0777, true);
}
try {
    $koneksi = new PDO("sqlite:" . $file_db);
    $koneksi->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $koneksi->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Koneksi gagal: " . $e->getMessage());
}

// Buat tabel otomatis kalau belum ada
$koneksi->exec("CREATE TABLE IF NOT EXISTS arsip (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    jenis_surat VARCHAR(10) NOT NULL,
    nomor_surat VARCHAR(100) NOT NULL,
    tanggal_surat DATE NOT NULL,
    tanggal_diterima DATE NULL,
    asal_tujuan VARCHAR(150) NOT NULL,
    perihal VARCHAR(255) NOT NULL,
    tahun INT NOT NULL,
    link_drive VARCHAR(255) NULL
)");

// Isi data contoh (dummy) hanya kalau tabel masih kosong
if ($koneksi->query("SELECT COUNT(*) FROM arsip")->fetchColumn() == 0) {
    $contoh = [
        ['masuk',  '001/UND/I/2024',    '2024-01-10', '2024-01-12', 'Dinas Pendidikan ',     'Undangan Rapat Koordinasi',    2024, 'https://drive.google.com/dummy-link-1'],
        ['masuk',  '014/PMH/III/2024',  '2024-03-05', '2024-03-07', 'PT Contoh Sejahtera ',  'Permohonan Kerja Sama',        2024, 'https://drive.google.com/dummy-link-2'],
        ['masuk',  '027/EDR/II/2025',   '2025-02-18', '2025-02-20', 'Kementerian Contoh',   'Surat Edaran Libur Nasional',  2025, 'https://drive.google.com/dummy-link-3'],
        ['masuk',  '045/UND/VI/2025',   '2025-06-02', '2025-06-03', 'Universitas Contoh ',   'Undangan Seminar Nasional',    2025, 'https://drive.google.com/dummy-link-4'],
        ['masuk',  '003/PMH/I/2026',    '2026-01-15', '2026-01-16', 'Badan Contoh ',         'Permohonan Data Pegawai',      2026, 'https://drive.google.com/dummy-link-5'],
        ['keluar', '101/KLR/II/2024',   '2024-02-20', null,         'Dinas Kesehatan ',      'Balasan Undangan Rapat',       2024, 'https://drive.google.com/dummy-link-6'],
        ['keluar', '056/KLR/IX/2024',   '2024-09-11', null,         'PT Mitra Contoh',      'Persetujuan Kerja Sama',       2024, 'https://drive.google.com/dummy-link-7'],
        ['keluar', '078/KLR/IV/2025',   '2025-04-22', null,         'Kementerian Contoh ',   'Laporan Kegiatan Triwulan I',  2025, 'https://drive.google.com/dummy-link-8'],
        ['keluar', '092/KLR/VIII/2025', '2025-08-14', null,         'Universitas Contoh ',   'Pemberitahuan Jadwal Kunjungan', 2025, 'https://drive.google.com/dummy-link-9'],
        ['keluar', '011/KLR/II/2026',   '2026-02-09', null,         'Badan Contoh ',         'Pengiriman Dokumen Pendukung', 2026, 'https://drive.google.com/dummy-link-10'],
    ];
    $stmt = $koneksi->prepare("INSERT INTO arsip
        (jenis_surat, nomor_surat, tanggal_surat, tanggal_diterima, asal_tujuan, perihal, tahun, link_drive)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($contoh as $baris) {
        $stmt->execute($baris);
    }
}
