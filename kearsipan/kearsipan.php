<?php
session_start();
require __DIR__ . '/koneksi.php';
date_default_timezone_set('Asia/Jakarta');

function h($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function go($url) { header('Location: ' . $url); exit; }

$aksi = $_GET['aksi'] ?? 'daftar';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$jenisFilter = $_GET['jenis'] ?? 'semua';
if (!in_array($jenisFilter, ['semua','masuk','keluar'], true)) $jenisFilter = 'semua';
$error = '';

// Hapus arsip melalui POST agar tidak terhapus hanya karena tautan dibuka.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus'])) {
    $hapusId = (int)$_POST['hapus'];
    $stmt = $koneksi->prepare('DELETE FROM arsip WHERE id = ?');
    $stmt->execute([$hapusId]);
    go('kearsipan.php?jenis=' . urlencode($jenisFilter) . '&pesan=hapus');
}

// Simpan data baru atau perubahan data.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_arsip'])) {
    $jenis = $_POST['jenis_surat'] ?? 'masuk';
    $nomor = trim($_POST['nomor_surat'] ?? '');
    $tanggal = $_POST['tanggal_surat'] ?? '';
    $diterima = $_POST['tanggal_diterima'] ?? '';
    $asal = trim($_POST['asal_tujuan'] ?? '');
    $perihal = trim($_POST['perihal'] ?? '');
    $tahun = (int)($_POST['tahun'] ?? 0);
    $drive = trim($_POST['link_drive'] ?? '');
    $formId = (int)($_POST['id'] ?? 0);

    if (!in_array($jenis, ['masuk','keluar'], true) || $nomor === '' || $tanggal === '' || $asal === '' || $perihal === '' || $tahun < 2000 || $tahun > 2100) {
        $error = 'Mohon isi semua kolom wajib dengan benar.';
        $aksi = $formId > 0 ? 'edit' : 'tambah';
        $id = $formId;
    } else {
        if ($jenis === 'keluar' || $diterima === '') $diterima = null;
        if ($drive === '') $drive = null;
        if ($formId > 0) {
            $sql = 'UPDATE arsip SET jenis_surat=?, nomor_surat=?, tanggal_surat=?, tanggal_diterima=?, asal_tujuan=?, perihal=?, tahun=?, link_drive=? WHERE id=?';
            $stmt = $koneksi->prepare($sql);
            $stmt->execute([$jenis,$nomor,$tanggal,$diterima,$asal,$perihal,$tahun,$drive,$formId]);
            go('kearsipan.php?aksi=detail&id=' . $formId . '&pesan=edit');
        } else {
            $sql = 'INSERT INTO arsip (jenis_surat, nomor_surat, tanggal_surat, tanggal_diterima, asal_tujuan, perihal, tahun, link_drive) VALUES (?,?,?,?,?,?,?,?)';
            $stmt = $koneksi->prepare($sql);
            $stmt->execute([$jenis,$nomor,$tanggal,$diterima,$asal,$perihal,$tahun,$drive]);
            $newId = (int)$koneksi->lastInsertId();
            go('kearsipan.php?aksi=detail&id=' . $newId . '&pesan=tambah');
        }
    }
}

$row = null;
if (in_array($aksi, ['detail','edit'], true)) {
    $stmt = $koneksi->prepare('SELECT * FROM arsip WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) { $aksi = 'daftar'; $error = 'Data arsip tidak ditemukan.'; }
}

$cari = trim($_GET['cari'] ?? '');
$tahunFilter = (int)($_GET['tahun'] ?? 0);
$params = [];
$sql = 'SELECT * FROM arsip WHERE 1=1';
if ($jenisFilter !== 'semua') { $sql .= ' AND jenis_surat = ?'; $params[] = $jenisFilter; }
if ($cari !== '') {
    $sql .= ' AND (nomor_surat LIKE ? OR perihal LIKE ? OR asal_tujuan LIKE ?)';
    $like = '%' . $cari . '%'; array_push($params, $like, $like, $like);
}
if ($tahunFilter > 0) { $sql .= ' AND tahun = ?'; $params[] = $tahunFilter; }
$sql .= ' ORDER BY tanggal_surat DESC, id DESC';
$stmt = $koneksi->prepare($sql); $stmt->execute($params); $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalMasuk = (int)$koneksi->query("SELECT COUNT(*) FROM arsip WHERE jenis_surat='masuk'")->fetchColumn();
$totalKeluar = (int)$koneksi->query("SELECT COUNT(*) FROM arsip WHERE jenis_surat='keluar'")->fetchColumn();
$daftarTahun = $koneksi->query('SELECT DISTINCT tahun FROM arsip ORDER BY tahun DESC')->fetchAll(PDO::FETCH_COLUMN);

if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="arsip_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Jenis Surat','Nomor Surat','Tanggal Surat','Tanggal Diterima','Asal/Tujuan','Perihal','Tahun','Link Drive']);
    foreach ($data as $r) fputcsv($out, [$r['jenis_surat'],$r['nomor_surat'],$r['tanggal_surat'],$r['tanggal_diterima'],$r['asal_tujuan'],$r['perihal'],$r['tahun'],$r['link_drive']]);
    fclose($out); exit;
}

$bulan = ['', 'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$tanggalHariIni = date('j') . ' ' . $bulan[(int)date('n')] . ' ' . date('Y');
$judul = ['daftar'=>'Manajemen Arsip','tambah'=>'Tambah Arsip','detail'=>'Detail Arsip','edit'=>'Edit Arsip'][$aksi] ?? 'Manajemen Arsip';
$inp = 'w-full text-sm bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 outline-none focus:ring-2 focus:ring-sky-500 text-slate-700';
$form = $row ?? ['id'=>'','jenis_surat'=>'masuk','nomor_surat'=>'','tanggal_surat'=>date('Y-m-d'),'tanggal_diterima'=>'','asal_tujuan'=>'','perihal'=>'','tahun'=>date('Y'),'link_drive'=>''];
?>
<!DOCTYPE html>
<html lang="id">
 <head>
  <meta charset="utf-8"/>
  <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
  <title>
   <?= h($judul) ?> - Kearsipan
  </title>
  <!-- Penjaga sesi: hanya akun Kearsipan yang sudah login yang boleh membuka halaman ini -->
  <script src="../js/auth-guard.js" data-role="kearsipan"></script>
  <script src="https://cdn.tailwindcss.com">
  </script>
  <script>
   tailwind.config={theme:{extend:{colors:{bpjsBlue:'#0078C8',bpjsGreen:'#00B140',bpjsYellow:'#D4E116',bgSoft:'#F8FAFC'},boxShadow:{soft:'0 10px 25px -5px rgba(0,0,0,.05),0 8px 10px -6px rgba(0,0,0,.05)'}}}};
  </script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
  <style>
   body{font-family:Inter,sans-serif}
  </style>
 </head>
 <body class="bg-bgSoft text-slate-900 min-h-screen">
  <div class="flex min-h-screen">
   <aside class="w-72 bg-gradient-to-b from-slate-900 to-slate-800 text-white hidden md:flex flex-col shrink-0 shadow-2xl">
    <div class="p-6 flex items-center gap-3 border-b border-slate-700">
     <div class="w-11 h-11 rounded-xl bg-bpjsBlue flex items-center justify-center">
      <i class="fa-solid fa-box-archive text-bpjsYellow text-xl">
      </i>
     </div>
     <div>
      <span class="block text-xs uppercase tracking-wider text-slate-400 font-bold">
       Modul Utama
      </span>
      <span class="text-sm font-extrabold">
       KEARSIPAN
      </span>
     </div>
    </div>
    <nav class="flex-1 p-4 space-y-2 text-sm">
     <a class="flex items-center gap-3 px-4 py-3 rounded-xl <?= $aksi==='daftar' && $jenisFilter==='semua' ? 'bg-sky-600 text-white font-bold' : 'text-slate-300 hover:bg-white/5' ?>" href="kearsipan.php">
      <i class="fa-solid fa-house w-5 text-bpjsGreen">
      </i>
      Semua Arsip
     </a>
     <a class="flex items-center gap-3 px-4 py-3 rounded-xl <?= $jenisFilter==='masuk' ? 'bg-sky-600 text-white font-bold' : 'text-slate-300 hover:bg-white/5' ?>" href="kearsipan.php?jenis=masuk">
      <i class="fa-solid fa-inbox w-5 text-bpjsGreen">
      </i>
      Surat Masuk
     </a>
     <a class="flex items-center gap-3 px-4 py-3 rounded-xl <?= $jenisFilter==='keluar' ? 'bg-sky-600 text-white font-bold' : 'text-slate-300 hover:bg-white/5' ?>" href="kearsipan.php?jenis=keluar">
      <i class="fa-solid fa-paper-plane w-5 text-bpjsGreen">
      </i>
      Surat Keluar
     </a>
     <a class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-300 hover:bg-white/5" href="kearsipan.php?aksi=tambah">
      <i class="fa-solid fa-plus w-5 text-bpjsYellow">
      </i>
      Tambah Arsip
     </a>
    </nav>
        <div class="p-4 border-t border-slate-800/80 bg-slate-900/30 flex flex-col gap-2.5">
        <a href="../index.html" class="w-full bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs py-3 px-4 rounded-xl font-bold transition flex items-center justify-center gap-2 border border-slate-700/50">
            <i class="fa-solid fa-arrow-left text-bpjsLightGreen"></i> Kembali ke Beranda
        </a>
    </div>
   </aside>
   <div class="flex-1 min-w-0">
    <header class="bg-white shadow-sm border-b border-slate-200 px-5 md:px-8 py-5 flex flex-wrap justify-between items-center gap-3">
     <div>
      <h1 class="text-xl font-black">
       <?= h($judul) ?>
      </h1>
      <p class="text-xs text-slate-500 mt-1">
       Kelola data surat masuk dan surat keluar dalam satu halaman
      </p>
     </div>
     <span class="text-xs bg-emerald-50 text-emerald-700 px-4 py-2 rounded-xl font-bold">
      <i class="fa-solid fa-circle-check mr-1">
      </i>
      Data Aktif · <?= h($tanggalHariIni) ?>
     </span>
    </header>
    <main class="p-5 md:p-8 max-w-[1600px] mx-auto space-y-7">
     <?php if ($error !== ''): ?>
     <div class="bg-red-50 text-red-700 border border-red-100 rounded-xl p-4 text-sm">
      <?= h($error) ?>
     </div>
     <?php endif; ?>
<?php if (isset($_GET['pesan'])): ?>
     <div class="bg-emerald-50 text-emerald-700 border border-emerald-100 rounded-xl p-4 text-sm">
      <?php $msg=['tambah'=>'Arsip berhasil ditambahkan.','edit'=>'Arsip berhasil diperbarui.','hapus'=>'Arsip berhasil dihapus.']; echo h($msg[$_GET['pesan']] ?? 'Perubahan berhasil disimpan.'); ?>
     </div>
     <?php endif; ?>
<?php if ($aksi==='tambah' || $aksi==='edit'): ?>
     <section class="bg-white p-5 md:p-8 rounded-3xl shadow-soft border border-slate-100 max-w-4xl">
      <h2 class="font-extrabold text-lg mb-1">
       <?= $aksi==='edit'?'Ubah data arsip':'Formulir arsip baru' ?>
      </h2>
      <p class="text-sm text-slate-500 mb-6">
       Kolom bertanda wajib harus diisi.
      </p>
      <form action="kearsipan.php?aksi=<?= h($aksi) ?><?= $aksi==='edit'?'&id='.(int)$id:'' ?>" class="grid grid-cols-1 md:grid-cols-2 gap-5" method="POST">
       <input name="id" type="hidden" value="<?= h($form['id']) ?>"/>
       <div>
        <label class="block text-xs font-bold text-slate-600 mb-2">
         Jenis Surat *
        </label>
        <select class="<?= $inp ?>" name="jenis_surat" required="">
         <option value="masuk" zzphpblock0019zz="">
          Surat Masuk
         </option>
         <option value="keluar" zzphpblock0020zz="">
          Surat Keluar
         </option>
        </select>
       </div>
       <div>
        <label class="block text-xs font-bold text-slate-600 mb-2">
         Nomor Surat *
        </label>
        <input class="<?= $inp ?>" name="nomor_surat" required="" value="<?= h($form['nomor_surat']) ?>"/>
       </div>
       <div>
        <label class="block text-xs font-bold text-slate-600 mb-2">
         Tanggal Surat *
        </label>
        <input class="<?= $inp ?>" name="tanggal_surat" required="" type="date" value="<?= h($form['tanggal_surat']) ?>"/>
       </div>
       <div>
        <label class="block text-xs font-bold text-slate-600 mb-2">
         Tanggal Diterima (surat masuk)
        </label>
        <input class="<?= $inp ?>" name="tanggal_diterima" type="date" value="<?= h($form['tanggal_diterima']) ?>"/>
       </div>
       <div>
        <label class="block text-xs font-bold text-slate-600 mb-2">
         Asal / Tujuan *
        </label>
        <input class="<?= $inp ?>" name="asal_tujuan" required="" value="<?= h($form['asal_tujuan']) ?>"/>
       </div>
       <div>
        <label class="block text-xs font-bold text-slate-600 mb-2">
         Perihal *
        </label>
        <input class="<?= $inp ?>" name="perihal" required="" value="<?= h($form['perihal']) ?>"/>
       </div>
       <div>
        <label class="block text-xs font-bold text-slate-600 mb-2">
         Tahun *
        </label>
        <input class="<?= $inp ?>" max="2100" min="2000" name="tahun" required="" type="number" value="<?= h($form['tahun']) ?>"/>
       </div>
       <div>
        <label class="block text-xs font-bold text-slate-600 mb-2">
         Link Google Drive
        </label>
        <input class="<?= $inp ?>" name="link_drive" placeholder="https://drive.google.com/..." type="url" value="<?= h($form['link_drive']) ?>"/>
       </div>
       <div class="md:col-span-2 flex flex-wrap justify-end gap-3 pt-4 border-t border-slate-100">
        <a class="px-5 py-3 rounded-xl bg-slate-100 text-slate-700 text-sm font-bold" href="kearsipan.php">
         Batal
        </a>
        <button class="px-6 py-3 rounded-xl bg-bpjsBlue text-white text-sm font-bold" name="simpan_arsip" value="1">
         <i class="fa-solid fa-floppy-disk mr-2">
         </i>
         <?= $aksi==='edit'?'Simpan Perubahan':'Simpan Arsip' ?>
        </button>
       </div>
      </form>
     </section>
     <?php elseif ($aksi==='detail' && $row): ?>
     <section class="bg-white p-5 md:p-8 rounded-3xl shadow-soft border border-slate-100 max-w-4xl">
      <div class="flex flex-wrap justify-between items-center gap-3 mb-6">
       <div>
        <h2 class="font-extrabold text-lg">
         Informasi Arsip
        </h2>
        <p class="text-sm text-slate-500">
         Detail lengkap surat yang dipilih.
        </p>
       </div>
       <span class="px-3 py-2 rounded-xl text-xs font-bold <?= $row['jenis_surat']==='masuk'?'bg-emerald-50 text-emerald-700':'bg-indigo-50 text-indigo-700' ?>">
        <?= $row['jenis_surat']==='masuk'?'Surat Masuk':'Surat Keluar' ?>
       </span>
      </div>
      <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
       <?php $fields=['Nomor Surat'=>'nomor_surat','Tanggal Surat'=>'tanggal_surat','Tanggal Diterima'=>'tanggal_diterima','Asal / Tujuan'=>'asal_tujuan','Perihal'=>'perihal','Tahun'=>'tahun']; foreach($fields as $label=>$key): ?>
       <div class="rounded-xl bg-slate-50 p-4">
        <dt class="text-xs text-slate-500 font-bold mb-1">
         <?= h($label) ?>
        </dt>
        <dd class="text-sm font-semibold break-words">
         <?= h($row[$key] ?: '-') ?>
        </dd>
       </div>
       <?php endforeach; ?>
      </dl>
      <div class="flex flex-wrap gap-3 mt-6">
       <?php if(!empty($row['link_drive'])): ?>
       <a class="px-4 py-3 rounded-xl bg-emerald-50 text-emerald-700 text-sm font-bold" href="<?= h($row['link_drive']) ?>" rel="noopener noreferrer" target="_blank">
        <i class="fa-solid fa-arrow-up-right-from-square mr-2">
        </i>
        Lihat Berkas
       </a>
       <?php endif; ?>
       <a class="px-4 py-3 rounded-xl bg-amber-50 text-amber-700 text-sm font-bold" href="kearsipan.php?aksi=edit&amp;id=<?= (int)$row['id'] ?>">
        Edit Arsip
       </a>
       <a class="px-4 py-3 rounded-xl bg-slate-100 text-slate-700 text-sm font-bold" href="kearsipan.php?jenis=<?= h($row['jenis_surat']) ?>">
        Kembali ke Daftar
       </a>
      </div>
     </section>
     <?php else: ?>
     <section class="grid grid-cols-1 sm:grid-cols-3 gap-5">
      <div class="bg-white p-6 rounded-3xl shadow-soft border border-slate-100">
       <p class="text-xs font-extrabold text-slate-400 uppercase">
        Surat Masuk
       </p>
       <p class="text-4xl font-black mt-2 text-bpjsGreen">
        <?= number_format($totalMasuk,0,',','.') ?>
       </p>
      </div>
      <div class="bg-white p-6 rounded-3xl shadow-soft border border-slate-100">
       <p class="text-xs font-extrabold text-slate-400 uppercase">
        Surat Keluar
       </p>
       <p class="text-4xl font-black mt-2 text-indigo-600">
        <?= number_format($totalKeluar,0,',','.') ?>
       </p>
      </div>
      <div class="bg-white p-6 rounded-3xl shadow-soft border border-slate-100">
       <p class="text-xs font-extrabold text-slate-400 uppercase">
        Hasil Ditampilkan
       </p>
       <p class="text-4xl font-black mt-2 text-bpjsBlue">
        <?= number_format(count($data),0,',','.') ?>
       </p>
      </div>
     </section>
     <section class="bg-white p-5 md:p-7 rounded-3xl shadow-soft border border-slate-100 space-y-5" id="table-section">
      <div class="flex flex-wrap items-center justify-between gap-4">
       <div>
        <h2 class="text-base font-extrabold">
         Daftar Arsip
        </h2>
        <p class="text-xs text-slate-500 mt-1">
         <?= count($data) ?> data ditemukan.
        </p>
       </div>
       <div class="flex gap-2">
        <a class="bg-bpjsGreen text-white px-4 py-3 rounded-xl text-xs font-bold" href="kearsipan.php?aksi=tambah">
         <i class="fa-solid fa-plus mr-1">
         </i>
         Tambah Arsip
        </a>
        <?php $ep=$_GET; $ep['export']=1; ?>
        <a class="bg-slate-800 text-white px-4 py-3 rounded-xl text-xs font-bold" href="kearsipan.php?<?= h(http_build_query($ep)) ?>">
         <i class="fa-solid fa-file-csv mr-1 text-bpjsYellow">
         </i>
         Export CSV
        </a>
       </div>
      </div>
      <form action="kearsipan.php#table-section" class="grid grid-cols-1 md:grid-cols-4 gap-3" method="GET">
       <input name="aksi" type="hidden" value="daftar"/>
       <input class="<?= $inp ?>" name="cari" placeholder="Cari nomor surat, perihal, asal/tujuan..." value="<?= h($cari) ?>"/>
       <select class="<?= $inp ?>" name="jenis">
        <option value="semua" zzphpblock0058zz="">
         Semua jenis
        </option>
        <option value="masuk" zzphpblock0059zz="">
         Surat masuk
        </option>
        <option value="keluar" zzphpblock0060zz="">
         Surat keluar
        </option>
       </select>
       <select class="<?= $inp ?>" name="tahun">
        <option value="0">
         Semua tahun
        </option>
        <?php foreach($daftarTahun as $t): ?>
        <option value="<?= (int)$t ?>" zzphpblock0064zz="">
         <?= (int)$t ?>
        </option>
        <?php endforeach; ?>
       </select>
       <div class="flex gap-2">
        <button class="flex-1 bg-bpjsBlue text-white px-4 py-3 rounded-xl text-xs font-bold">
         Cari
        </button>
        <a class="bg-slate-100 px-4 py-3 rounded-xl text-xs font-bold flex items-center" href="kearsipan.php">
         Reset
        </a>
       </div>
      </form>
      <div class="overflow-x-auto">
       <table class="w-full text-left border-collapse">
        <thead>
         <tr class="bg-slate-50 text-slate-500 text-xs font-bold uppercase border-b">
          <th class="p-3">
           Jenis
          </th>
          <th class="p-3">
           Nomor Surat
          </th>
          <th class="p-3">
           Tanggal
          </th>
          <th class="p-3">
           Asal / Tujuan
          </th>
          <th class="p-3">
           Perihal
          </th>
          <th class="p-3">
           Tahun
          </th>
          <th class="p-3">
           Aksi
          </th>
         </tr>
        </thead>
        <tbody class="text-sm divide-y divide-slate-100">
         <?php if($data): foreach($data as $r): ?>
         <tr class="hover:bg-slate-50">
          <td class="p-3">
           <span class="px-2 py-1 rounded-lg text-xs font-bold <?= $r['jenis_surat']==='masuk'?'bg-emerald-50 text-emerald-700':'bg-indigo-50 text-indigo-700' ?>">
            <?= $r['jenis_surat']==='masuk'?'Masuk':'Keluar' ?>
           </span>
          </td>
          <td class="p-3 text-xs font-bold text-bpjsBlue whitespace-nowrap">
           <?= h($r['nomor_surat']) ?>
          </td>
          <td class="p-3 text-xs whitespace-nowrap">
           <?= h($r['tanggal_surat']) ?>
          </td>
          <td class="p-3 font-semibold">
           <?= h($r['asal_tujuan']) ?>
          </td>
          <td class="p-3">
           <?= h($r['perihal']) ?>
          </td>
          <td class="p-3">
           <?= h($r['tahun']) ?>
          </td>
          <td class="p-3">
           <div class="flex flex-wrap gap-1.5">
            <a class="bg-blue-50 text-blue-700 px-3 py-2 rounded-lg text-xs font-bold" href="kearsipan.php?aksi=detail&amp;id=<?= (int)$r['id'] ?>">
             Detail
            </a>
            <a class="bg-amber-50 text-amber-700 px-3 py-2 rounded-lg text-xs font-bold" href="kearsipan.php?aksi=edit&amp;id=<?= (int)$r['id'] ?>">
             Edit
            </a>
            <form action="kearsipan.php?jenis=<?= h($jenisFilter) ?>" method="POST" onsubmit="return confirm('Yakin ingin menghapus arsip ini?')">
             <button class="bg-red-50 text-red-700 px-3 py-2 rounded-lg text-xs font-bold" name="hapus" value="<?= (int)$r['id'] ?>">
              Hapus
             </button>
            </form>
           </div>
          </td>
         </tr>
         <?php endforeach; else: ?>
         <tr>
          <td class="text-center py-10 text-slate-400" colspan="7">
           Tidak ada arsip yang cocok.
          </td>
         </tr>
         <?php endif; ?>
        </tbody>
       </table>
      </div>
     </section>
     <?php endif; ?>
    </main>
   </div>
  </div>
 </body>
</html>
