
<?php
// Penyimpanan data arsip dalam file JSON.
// Folder database ada di folder utama website (sejajar dengan folder php), bukan di dalam php.
$dataFile = dirname(__DIR__) . '/database/arsip.json';

if (!is_dir(dirname(__DIR__) . '/database')) {
    mkdir(dirname(__DIR__) . '/database', 0777, true);
}

if (!file_exists($dataFile)) {
    file_put_contents($dataFile, '[]', LOCK_EX);
}

// Membaca semua data arsip.
function arsip_all() {
    global $dataFile;

    $json = file_get_contents($dataFile);
    $rows = json_decode($json, true);

    return is_array($rows) ? $rows : [];
}

// Mengubah data arsip dan menyimpan hasilnya.
function arsip_ubah($callback) {
    global $dataFile;

    $fp = fopen($dataFile, 'c+');

    if (!$fp) {
        throw new RuntimeException('File penyimpanan arsip tidak bisa dibuka.');
    }

    try {
        if (!flock($fp, LOCK_EX)) {
            throw new RuntimeException('Gagal mengunci file arsip.');
        }

        rewind($fp);
        $json = stream_get_contents($fp);
        $rows = json_decode($json ?: '[]', true);

        if (!is_array($rows)) {
            $rows = [];
        }

        $hasil = $callback($rows);
        $rowsBaru = $hasil['rows'] ?? $rows;

        rewind($fp);
        if (!ftruncate($fp, 0)) {
            throw new RuntimeException('Gagal mengosongkan file arsip.');
        }

        $ditulis = fwrite(
            $fp,
            json_encode(
                array_values($rowsBaru),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            )
        );

        if ($ditulis === false) {
            throw new RuntimeException('Gagal menyimpan data arsip.');
        }

        fflush($fp);
        return $hasil['res'] ?? true;
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}
