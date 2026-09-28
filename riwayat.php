<?php
/**
 * =====================================================================
 * FILE: riwayat.php
 * FUNGSI: Menampilkan riwayat sewa yang sudah Selesai, dengan filter
 * tanggal dan pencarian nama penyewa/mobil.
 * =====================================================================
 */
require_once __DIR__ . '/functions.php';
requireLogin();

$pdo = getConnection();

$keyword     = trim($_GET['q'] ?? '');
$filterMulai = $_GET['dari'] ?? date('Y-m-01');
$filterAkhir = $_GET['sampai'] ?? date('Y-m-d');

$sql = "
    SELECT s.id, s.tanggal_mulai, s.tanggal_kembali, s.total_hari, s.total_biaya, s.catatan,
           m.plat_nomor, m.merk_model, m.tipe,
           p.nama_lengkap AS nama_penyewa, p.no_hp
    FROM sewa s
    JOIN mobil m ON m.id = s.mobil_id
    JOIN penyewa p ON p.id = s.penyewa_id
    WHERE s.status = 'Selesai'
      AND s.tanggal_kembali BETWEEN :dari AND :sampai
";
$params = ['dari' => $filterMulai, 'sampai' => $filterAkhir];

if (!empty($keyword)) {
    $sql .= ' AND (p.nama_lengkap LIKE :keyword OR m.merk_model LIKE :keyword OR m.plat_nomor LIKE :keyword)';
    $params['keyword'] = '%' . $keyword . '%';
}
$sql .= ' ORDER BY s.tanggal_kembali DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$riwayat = $stmt->fetchAll();

// Total pendapatan pada rentang filter yang aktif
$totalPendapatan = array_sum(array_column($riwayat, 'total_biaya'));

$pageTitle  = 'Riwayat Transaksi';
$activeMenu = 'riwayat';
require_once __DIR__ . '/header.php';
?>

<div class="card card-glass mb-3">
    <div class="card-body">
        <form method="GET" action="riwayat.php" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small">Dari Tanggal</label>
                <input type="date" name="dari" class="form-control" value="<?= clean($filterMulai) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small">Sampai Tanggal</label>
                <input type="date" name="sampai" class="form-control" value="<?= clean($filterAkhir) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small">Cari Penyewa/Mobil</label>
                <input type="text" name="q" class="form-control" placeholder="Nama penyewa atau mobil..." value="<?= clean($keyword) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary-mobil w-100"><i class="bi bi-search"></i> Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card card-glass">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock-history"></i> Riwayat Transaksi (<?= count($riwayat) ?> transaksi)</span>
        <span class="fw-bold" style="color:#4ade80;">Total: <?= formatRupiah($totalPendapatan) ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Penyewa</th>
                        <th>Mobil</th>
                        <th>Mulai</th>
                        <th>Kembali</th>
                        <th>Durasi</th>
                        <th>Biaya</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($riwayat)): ?>
                        <tr><td colspan="7" class="text-center text-muted-light py-4">Tidak ada riwayat transaksi pada rentang tanggal ini.</td></tr>
                    <?php else: ?>
                        <?php foreach ($riwayat as $i => $r): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td class="fw-semibold"><?= clean($r['nama_penyewa']) ?><br><small class="text-muted-light"><?= clean($r['no_hp']) ?></small></td>
                                <td><?= clean($r['plat_nomor']) ?> - <?= clean($r['merk_model']) ?><br><small class="text-muted-light"><?= clean($r['tipe']) ?></small></td>
                                <td><?= formatTanggalIndo($r['tanggal_mulai']) ?></td>
                                <td><?= formatTanggalIndo($r['tanggal_kembali']) ?></td>
                                <td><?= (int) $r['total_hari'] ?> hari</td>
                                <td class="fw-semibold"><?= formatRupiah($r['total_biaya']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
