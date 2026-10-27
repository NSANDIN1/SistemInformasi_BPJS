<?php
include "koneksi.php";

// Ambil kata kunci dan tahun dari link (kalau ada)
$cari  = isset($_GET['cari']) ? trim($_GET['cari']) : "";
$tahun = isset($_GET['tahun']) ? (int) $_GET['tahun'] : 0;
$kata  = "%" . $cari . "%";

if ($tahun > 0) {
    $sql = "SELECT * FROM arsip
            WHERE jenis_surat = 'keluar'
            AND (nomor_surat LIKE ? OR perihal LIKE ? OR asal_tujuan LIKE ?)
            AND tahun = ?
            ORDER BY tanggal_surat DESC";
    $stmt = $koneksi->prepare($sql);
    $stmt->execute([$kata, $kata, $kata, $tahun]);
} else {
    $sql = "SELECT * FROM arsip
            WHERE jenis_surat = 'keluar'
            AND (nomor_surat LIKE ? OR perihal LIKE ? OR asal_tujuan LIKE ?)
            ORDER BY tanggal_surat DESC";
    $stmt = $koneksi->prepare($sql);
    $stmt->execute([$kata, $kata, $kata]);
}
$data = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat Keluar - Kearsipan</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="layout">

    <?php include "menu.php"; ?>

    <div class="konten">
        <h1>Surat Keluar</h1>
        <form method="GET" action="">
            <input type="text" name="cari" placeholder="Cari nomor surat, perihal, atau tujuan..." value="<?php echo htmlspecialchars($cari); ?>">
            <select name="tahun">
                <option value="0">Semua Tahun</option>
                <?php foreach ([2024, 2025, 2026] as $t) { ?>
                    <option value="<?php echo $t; ?>" <?php if ($tahun == $t) echo 'selected'; ?>><?php echo $t; ?></option>
                <?php } ?>
            </select>
            <button type="submit">Cari</button>
            <a href="keluar.php">Reset</a>
        </form>
        <br>

        <table border="1" cellpadding="6">
            <tr>
                <th>No</th>
                <th>Nomor Surat</th>
                <th>Tanggal Surat</th>
                <th>Tujuan</th>
                <th>Perihal</th>
                <th>Tahun</th>
                <th>Aksi</th>
            </tr>

            <?php
            $no = 1;
            foreach ($data as $row) {
            ?>
            <tr>
                <td><?php echo $no++; ?></td>
                <td><?php echo htmlspecialchars($row['nomor_surat']); ?></td>
                <td><?php echo $row['tanggal_surat']; ?></td>
                <td><?php echo htmlspecialchars($row['asal_tujuan']); ?></td>
                <td><?php echo htmlspecialchars($row['perihal']); ?></td>
                <td><?php echo $row['tahun']; ?></td>
                <td>
                    <a href="detail.php?id=<?php echo $row['id']; ?>">Detail</a> |
                    <a href="edit.php?id=<?php echo $row['id']; ?>">Edit</a> |
                    <a href="hapus.php?id=<?php echo $row['id']; ?>" onclick="return confirm('Yakin ingin menghapus arsip ini?')">Hapus</a>
                </td>
            </tr>
            <?php } ?>
        </table>
    </div>

</div>
</body>
</html>