
<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function jawab(int $status, array $data): never
{
    http_response_code($status);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| KONFIGURASI DATABASE
|--------------------------------------------------------------------------
| Sesuaikan dengan konfigurasi MySQL XAMPP.
| Berdasarkan tes koneksi kamu, MySQL menggunakan port 3308.
*/

$dbHost = '127.0.0.1';
$port   = 3308;
$dbName = 'kepesertaan_db';
$dbUser = 'root';
$dbPass = '';

/*
|--------------------------------------------------------------------------
| KONEKSI DATABASE
|--------------------------------------------------------------------------
*/

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};port={$port};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false
        ]
    );
} catch (Throwable $e) {
    error_log('Koneksi database gagal: ' . $e->getMessage());

    jawab(500, [
        'ok'      => false,
        'message' => 'Koneksi database gagal. Periksa konfigurasi database.'
    ]);
}

/*
|--------------------------------------------------------------------------
| PROSES REQUEST
|--------------------------------------------------------------------------
*/

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    /*
    |--------------------------------------------------------------------------
    | GET: MENGAMBIL DATA DASHBOARD
    |--------------------------------------------------------------------------
    */

    if ($method === 'GET') {
        $stmt = $pdo->query(
            "SELECT data_key, payload
             FROM dashboard_data
             WHERE data_key IN ('rekap', 'perisai')"
        );

        $data = [
            'rekap'  => [],
            'perisai' => []
        ];

        foreach ($stmt->fetchAll() as $row) {
            $key = $row['data_key'];

            $decoded = json_decode(
                (string) $row['payload'],
                true
            );

            if (in_array($key, ['rekap', 'perisai'], true)) {
                $data[$key] = is_array($decoded) ? $decoded : [];
            }
        }

        jawab(200, [
            'ok'      => true,
            'rekap'   => $data['rekap'],
            'perisai' => $data['perisai']
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | HANYA GET DAN POST YANG DIDUKUNG
    |--------------------------------------------------------------------------
    */

    if ($method !== 'POST') {
        header('Allow: GET, POST');

        jawab(405, [
            'ok'      => false,
            'message' => 'Metode request tidak didukung.'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | MEMBACA JSON DARI REQUEST BODY
    |--------------------------------------------------------------------------
    */

    $rawInput = file_get_contents('php://input');

    if ($rawInput === false || trim($rawInput) === '') {
        jawab(400, [
            'ok'      => false,
            'message' => 'Request body kosong.'
        ]);
    }

    $input = json_decode($rawInput, true);

    if (!is_array($input) || json_last_error() !== JSON_ERROR_NONE) {
        jawab(400, [
            'ok'      => false,
            'message' => 'Payload JSON tidak valid.'
        ]);
    }

    $action = $input['action'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | RESET: MENGHAPUS DATA REKAP DAN PERISAI
    |--------------------------------------------------------------------------
    */

    if ($action === 'reset') {
        $stmt = $pdo->prepare(
            "DELETE FROM dashboard_data
             WHERE data_key IN ('rekap', 'perisai')"
        );

        $stmt->execute();

        jawab(200, [
            'ok'      => true,
            'message' => 'Data dashboard berhasil direset.'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDASI ACTION
    |--------------------------------------------------------------------------
    */

    if ($action !== 'save') {
        jawab(400, [
            'ok'      => false,
            'message' => 'Action tidak dikenal.'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDASI DATA
    |--------------------------------------------------------------------------
    */

    foreach (['rekap', 'perisai'] as $key) {
        if (
            !array_key_exists($key, $input) ||
            !is_array($input[$key])
        ) {
            jawab(400, [
                'ok'      => false,
                'message' => "Data {$key} tidak valid."
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SIMPAN DATA KE DATABASE
    |--------------------------------------------------------------------------
    | Transaksi digunakan agar kedua dataset tersimpan bersama-sama.
    */

    $stmt = $pdo->prepare(
        "INSERT INTO dashboard_data
            (data_key, payload, updated_at)
         VALUES
            (:data_key, :payload, NOW())
         ON DUPLICATE KEY UPDATE
            payload = VALUES(payload),
            updated_at = NOW()"
    );

    $pdo->beginTransaction();

    try {
        foreach (['rekap', 'perisai'] as $key) {
            $json = json_encode(
                $input[$key],
                JSON_UNESCAPED_UNICODE |
                JSON_INVALID_UTF8_SUBSTITUTE
            );

            if ($json === false) {
                throw new RuntimeException(
                    "Gagal mengubah data {$key} menjadi JSON."
                );
            }

            $stmt->execute([
                'data_key' => $key,
                'payload'  => $json
            ]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }

    jawab(200, [
        'ok'      => true,
        'message' => 'Data berhasil disimpan ke server.'
    ]);

} catch (Throwable $e) {
    error_log('Operasi API kepesertaan gagal: ' . $e->getMessage());

    jawab(500, [
        'ok'      => false,
        'message' => 'Operasi database gagal. Periksa struktur tabel dan log PHP server.'
    ]);
}
