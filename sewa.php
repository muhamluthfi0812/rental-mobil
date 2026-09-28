<?php
/**
 * =====================================================================
 * FILE: sewa.php
 * FUNGSI: Core system transaksi sewa mobil. Dua aksi utama, masing-
 * masing ATOMIC menggunakan DB Transaction:
 *   1. MULAI SEWA        -> insert baris sewa (status Berlangsung) +
 *                            mobil jadi status 'Disewa'
 *   2. KEMBALIKAN MOBIL   -> hitung durasi & biaya otomatis (per hari,
 *                            dibulatkan ke atas), update sewa jadi
 *                            status 'Selesai' + mobil kembali 'Tersedia'
 * =====================================================================
 */
require_once __DIR__ . '/functions.php';
requireLogin();

$pdo = getConnection();

// --- MULAI SEWA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'mulai_sewa') {
    verifyCsrfToken($_POST['csrf_token'] ?? null);

    $mobil_id                = $_POST['mobil_id'] ?? '';
    $penyewa_id              = $_POST['penyewa_id'] ?? '';
    $tanggal_mulai            = $_POST['tanggal_mulai'] ?? date('Y-m-d');
    $tanggal_rencana_selesai  = $_POST['tanggal_rencana_selesai'] ?? '';
    $catatan                  = trim($_POST['catatan'] ?? '');

    if (empty($mobil_id) || !ctype_digit((string) $mobil_id)) {
        setFlash('danger', 'Mobil wajib dipilih.');
        redirect('sewa.php');
    }
    if (empty($penyewa_id) || !ctype_digit((string) $penyewa_id)) {
        setFlash('danger', 'Penyewa wajib dipilih.');
        redirect('sewa.php');
    }
    if (empty($tanggal_rencana_selesai) || !DateTime::createFromFormat('Y-m-d', $tanggal_rencana_selesai)) {
        setFlash('danger', 'Rencana tanggal selesai wajib diisi dan valid.');
        redirect('sewa.php');
    }
    if ($tanggal_rencana_selesai < $tanggal_mulai) {
        setFlash('danger', 'Rencana tanggal selesai tidak boleh sebelum tanggal mulai.');
        redirect('sewa.php');
    }

    // Cek ulang status mobil terkini agar tidak double-booking
    $stmtMobil = $pdo->prepare('SELECT id, status FROM mobil WHERE id = :id LIMIT 1');
    $stmtMobil->execute(['id' => $mobil_id]);
    $mobil = $stmtMobil->fetch();

    if (!$mobil) {
        setFlash('danger', 'Data mobil tidak ditemukan.');
    } elseif ($mobil['status'] !== 'Tersedia') {
        setFlash('danger', 'Mobil ini sedang tidak tersedia (statusnya: ' . $mobil['status'] . ').');
    } else {
        // ============= MULAI DATABASE TRANSACTION =============
        try {
            $pdo->beginTransaction();

            // 1. Insert baris sewa baru
            $stmtInsert = $pdo->prepare('
                INSERT INTO sewa (mobil_id, penyewa_id, tanggal_mulai, tanggal_rencana_selesai, status, catatan)
                VALUES (:mobil_id, :penyewa_id, :tanggal_mulai, :tanggal_rencana_selesai, "Berlangsung", :catatan)
            ');
            $stmtInsert->execute([
                'mobil_id'                 => $mobil_id,
                'penyewa_id'               => $penyewa_id,
                'tanggal_mulai'             => $tanggal_mulai,
                'tanggal_rencana_selesai'   => $tanggal_rencana_selesai,
                'catatan'                   => $catatan ?: null,
            ]);

            // 2. Update status mobil menjadi Disewa
            $stmtUpdate = $pdo->prepare('UPDATE mobil SET status = "Disewa" WHERE id = :id');
            $stmtUpdate->execute(['id' => $mobil_id]);

            $pdo->commit();
            setFlash('success', 'Sewa mobil berhasil dimulai. Status mobil otomatis berubah menjadi Disewa.');
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('danger', 'Gagal memulai sewa: ' . $e->getMessage());
        }
        // ============= AKHIR DATABASE TRANSACTION =============
    }

    redirect('sewa.php');
}

// --- KEMBALIKAN MOBIL (selesaikan sewa) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'kembalikan') {
    verifyCsrfToken($_POST['csrf_token'] ?? null);

    $sewa_id         = $_POST['sewa_id'] ?? '';
    $tanggal_kembali = $_POST['tanggal_kembali'] ?? date('Y-m-d');

    if (empty($sewa_id) || !ctype_digit((string) $sewa_id)) {
        setFlash('danger', 'Data sewa tidak valid.');
        redirect('sewa.php');
    }
    if (!DateTime::createFromFormat('Y-m-d', $tanggal_kembali)) {
        setFlash('danger', 'Tanggal kembali tidak valid.');
        redirect('sewa.php');
    }

    // Ambil data sewa beserta harga mobil terkait
    $stmtCek = $pdo->prepare('
        SELECT s.id, s.mobil_id, s.tanggal_mulai, s.status,
               m.harga_per_hari, m.merk_model,
               p.nama_lengkap AS nama_penyewa
        FROM sewa s
        JOIN mobil m ON m.id = s.mobil_id
        JOIN penyewa p ON p.id = s.penyewa_id
        WHERE s.id = :id LIMIT 1
    ');
    $stmtCek->execute(['id' => $sewa_id]);
    $sewa = $stmtCek->fetch();

    if (!$sewa) {
        setFlash('danger', 'Data sewa tidak ditemukan.');
        redirect('sewa.php');
    }
    if ($sewa['status'] !== 'Berlangsung') {
        setFlash('danger', 'Sewa ini sudah pernah diselesaikan sebelumnya.');
        redirect('sewa.php');
    }
    if ($tanggal_kembali < $sewa['tanggal_mulai']) {
        setFlash('danger', 'Tanggal kembali tidak boleh sebelum tanggal mulai sewa.');
        redirect('sewa.php');
    }

    // Hitung durasi & biaya otomatis
    $hasil = hitungBiayaSewaMobil($sewa['tanggal_mulai'], $tanggal_kembali, (float) $sewa['harga_per_hari']);

    // ============= MULAI DATABASE TRANSACTION =============
    try {
        $pdo->beginTransaction();

        // 1. Update sewa: tandai Selesai, catat tanggal kembali, total hari, dan biaya
        $stmtUpdateSewa = $pdo->prepare('
            UPDATE sewa
            SET status = "Selesai", tanggal_kembali = :tanggal_kembali, total_hari = :total_hari, total_biaya = :total_biaya
            WHERE id = :id
        ');
        $stmtUpdateSewa->execute([
            'tanggal_kembali' => $tanggal_kembali,
            'total_hari'      => $hasil['hari'],
            'total_biaya'     => $hasil['biaya'],
            'id'              => $sewa_id,
        ]);

        // 2. Update status mobil kembali menjadi Tersedia
        $stmtUpdateMobil = $pdo->prepare('UPDATE mobil SET status = "Tersedia" WHERE id = :id');
        $stmtUpdateMobil->execute(['id' => $sewa['mobil_id']]);

        $pdo->commit();
        setFlash('success', 'Mobil "' . $sewa['merk_model'] . '" berhasil dikembalikan. Total: ' . formatRupiah($hasil['biaya']) . ' (' . $hasil['hari'] . ' hari).');
    } catch (Exception $e) {
        $pdo->rollBack();
        setFlash('danger', 'Gagal memproses pengembalian: ' . $e->getMessage());
    }
    // ============= AKHIR DATABASE TRANSACTION =============

    redirect('sewa.php');
}

// --- AMBIL DATA UNTUK DITAMPILKAN ---
$mobilTersedia = $pdo->query("SELECT id, plat_nomor, merk_model, tipe, harga_per_hari FROM mobil WHERE status = 'Tersedia' ORDER BY plat_nomor ASC")->fetchAll();
$semuaPenyewa  = $pdo->query("SELECT id, nama_lengkap, no_ktp FROM penyewa ORDER BY nama_lengkap ASC")->fetchAll();

$sewaBerlangsung = $pdo->query("
    SELECT s.id, s.tanggal_mulai, s.tanggal_rencana_selesai, s.catatan,
           m.plat_nomor, m.merk_model, m.tipe, m.harga_per_hari,
           p.nama_lengkap AS nama_penyewa, p.no_hp
    FROM sewa s
    JOIN mobil m ON m.id = s.mobil_id
    JOIN penyewa p ON p.id = s.penyewa_id
    WHERE s.status = 'Berlangsung'
    ORDER BY s.tanggal_mulai ASC
")->fetchAll();

$csrfToken  = generateCsrfToken();
$pageTitle  = 'Sewa Berlangsung';
$activeMenu = 'sewa';
require_once __DIR__ . '/header.php';
?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card card-glass">
            <div class="card-header"><i class="bi bi-play-circle"></i> Mulai Sewa Baru</div>
            <div class="card-body">
                <?php if (empty($mobilTersedia)): ?>
                    <div class="alert alert-warning mb-0">
                        Semua mobil sedang disewa atau maintenance. Tunggu mobil kembali, atau cek menu "Data Mobil".
                    </div>
                <?php elseif (empty($semuaPenyewa)): ?>
                    <div class="alert alert-warning mb-0">
                        Belum ada data penyewa. Silakan tambahkan dulu di menu "Data Penyewa".
                    </div>
                <?php else: ?>
                    <form method="POST" action="sewa.php">
                        <input type="hidden" name="csrf_token" value="<?= clean($csrfToken) ?>">
                        <input type="hidden" name="aksi" value="mulai_sewa">

                        <div class="mb-3">
                            <label class="form-label">Pilih Mobil (Tersedia)</label>
                            <select class="form-select" name="mobil_id" required>
                                <option value="">-- Pilih Mobil --</option>
                                <?php foreach ($mobilTersedia as $m): ?>
                                    <option value="<?= (int) $m['id'] ?>">
                                        <?= clean($m['plat_nomor']) ?> - <?= clean($m['merk_model']) ?> (<?= formatRupiah($m['harga_per_hari']) ?>/hari)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Pilih Penyewa</label>
                            <select class="form-select" name="penyewa_id" required>
                                <option value="">-- Pilih Penyewa --</option>
                                <?php foreach ($semuaPenyewa as $p): ?>
                                    <option value="<?= (int) $p['id'] ?>">
                                        <?= clean($p['nama_lengkap']) ?> (KTP: <?= clean($p['no_ktp']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Tanggal Mulai</label>
                            <input type="date" class="form-control" name="tanggal_mulai" required value="<?= date('Y-m-d') ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Rencana Tanggal Selesai</label>
                            <input type="date" class="form-control" name="tanggal_rencana_selesai" required value="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Catatan (Opsional)</label>
                            <textarea class="form-control" name="catatan" rows="2" placeholder="Contoh: bayar DP 50%, ambil di lokasi A"></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary-mobil w-100">
                            <i class="bi bi-key-fill"></i> Mulai Sewa
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card card-glass">
            <div class="card-header"><i class="bi bi-car-front"></i> Sedang Berlangsung (<?= count($sewaBerlangsung) ?>)</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Mobil</th>
                                <th>Penyewa</th>
                                <th>Mulai</th>
                                <th>Rencana Selesai</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($sewaBerlangsung)): ?>
                                <tr><td colspan="5" class="text-center text-muted-light py-4">Tidak ada sewa yang sedang berlangsung saat ini.</td></tr>
                            <?php else: ?>
                                <?php foreach ($sewaBerlangsung as $s): ?>
                                    <?php $terlambat = $s['tanggal_rencana_selesai'] < date('Y-m-d'); ?>
                                    <tr>
                                        <td class="fw-semibold"><?= clean($s['plat_nomor']) ?> - <?= clean($s['merk_model']) ?><br><small class="text-muted-light"><?= clean($s['tipe']) ?></small></td>
                                        <td><?= clean($s['nama_penyewa']) ?><br><small class="text-muted-light"><?= clean($s['no_hp']) ?></small></td>
                                        <td><?= formatTanggalIndo($s['tanggal_mulai']) ?></td>
                                        <td>
                                            <?= formatTanggalIndo($s['tanggal_rencana_selesai']) ?>
                                            <?php if ($terlambat): ?>
                                                <br><span class="badge bg-warning text-dark">Jatuh tempo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-outline-danger"
                                                data-bs-toggle="modal" data-bs-target="#modalKembalikan"
                                                onclick='bukaModalKembalikan(<?= json_encode($s, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                                <i class="bi bi-arrow-return-left"></i> Kembalikan
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Pengembalian -->
<div class="modal fade" id="modalKembalikan" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="sewa.php">
                <input type="hidden" name="csrf_token" value="<?= clean($csrfToken) ?>">
                <input type="hidden" name="aksi" value="kembalikan">
                <input type="hidden" name="sewa_id" id="form_sewa_id">

                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-arrow-return-left"></i> Konfirmasi Pengembalian Mobil</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Mobil: <strong id="detail_mobil"></strong></p>
                    <p>Penyewa: <strong id="detail_penyewa"></strong></p>
                    <div class="mb-3">
                        <label class="form-label">Tanggal Kembali</label>
                        <input type="date" class="form-control" name="tanggal_kembali" id="form_tanggal_kembali" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <p class="text-muted-light small mb-0">
                        Total biaya akan dihitung otomatis berdasarkan jumlah hari (dibulatkan ke atas, minimum 1 hari) dikali harga sewa per hari.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Proses Pengembalian</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function bukaModalKembalikan(data) {
        document.getElementById('form_sewa_id').value = data.id;
        document.getElementById('detail_mobil').innerText = data.plat_nomor + ' - ' + data.merk_model;
        document.getElementById('detail_penyewa').innerText = data.nama_penyewa;
    }
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
