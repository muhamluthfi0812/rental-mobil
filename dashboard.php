<?php
/**
 * =====================================================================
 * FILE: dashboard.php
 * FUNGSI: Papan monitor utama - ringkasan analitik + kartu visual
 * setiap mobil (hijau = Tersedia, merah = Disewa, kuning = Maintenance).
 * =====================================================================
 */
require_once __DIR__ . '/functions.php';
requireLogin();

$pdo = getConnection();

// --- Ringkasan jumlah mobil per status ---
$statMobil = $pdo->query("
    SELECT
        COUNT(*) AS total_mobil,
        SUM(CASE WHEN status = 'Tersedia' THEN 1 ELSE 0 END) AS tersedia,
        SUM(CASE WHEN status = 'Disewa' THEN 1 ELSE 0 END) AS disewa,
        SUM(CASE WHEN status = 'Maintenance' THEN 1 ELSE 0 END) AS maintenance
    FROM mobil
")->fetch();

// --- Pendapatan bulan ini (dari sewa yang sudah Selesai) ---
$pendapatanBulanIni = $pdo->prepare("
    SELECT COALESCE(SUM(total_biaya), 0) AS total
    FROM sewa
    WHERE status = 'Selesai' AND MONTH(tanggal_kembali) = MONTH(CURDATE()) AND YEAR(tanggal_kembali) = YEAR(CURDATE())
");
$pendapatanBulanIni->execute();
$pendapatan = $pendapatanBulanIni->fetch()['total'];

// --- Jumlah sewa yang butuh dikembalikan hari ini atau sudah lewat rencana selesai ---
$sewaJatuhTempo = $pdo->query("
    SELECT COUNT(*) AS total FROM sewa WHERE status = 'Berlangsung' AND tanggal_rencana_selesai <= CURDATE()
")->fetch()['total'];

// --- Ambil seluruh mobil beserta info sewa aktif (jika sedang disewa) ---
$daftarMobil = $pdo->query("
    SELECT m.id, m.plat_nomor, m.merk_model, m.tipe, m.harga_per_hari, m.status,
           s.id AS sewa_id, s.tanggal_mulai, s.tanggal_rencana_selesai,
           p.nama_lengkap AS nama_penyewa
    FROM mobil m
    LEFT JOIN sewa s ON s.mobil_id = m.id AND s.status = 'Berlangsung'
    LEFT JOIN penyewa p ON p.id = s.penyewa_id
    ORDER BY m.plat_nomor ASC
")->fetchAll();

$pageTitle  = 'Dashboard';
$activeMenu = 'dashboard';
require_once __DIR__ . '/header.php';
?>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card card-stat">
            <div class="card-body d-flex align-items-center">
                <div class="rounded-circle p-3 me-3" style="background: rgba(251,133,0,0.15);">
                    <i class="bi bi-car-front fs-4" style="color:#fb8500;"></i>
                </div>
                <div>
                    <div class="text-muted-light small">Total Mobil</div>
                    <div class="fs-4 fw-bold"><?= (int) $statMobil['total_mobil'] ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-stat">
            <div class="card-body d-flex align-items-center">
                <div class="rounded-circle p-3 me-3" style="background: rgba(34,197,94,0.15);">
                    <i class="bi bi-check-circle-fill fs-4 text-success"></i>
                </div>
                <div>
                    <div class="text-muted-light small">Mobil Tersedia</div>
                    <div class="fs-4 fw-bold"><?= (int) $statMobil['tersedia'] ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-stat">
            <div class="card-body d-flex align-items-center">
                <div class="rounded-circle p-3 me-3" style="background: rgba(239,68,68,0.15);">
                    <i class="bi bi-key-fill fs-4 text-danger"></i>
                </div>
                <div>
                    <div class="text-muted-light small">Sedang Disewa</div>
                    <div class="fs-4 fw-bold"><?= (int) $statMobil['disewa'] ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-stat">
            <div class="card-body d-flex align-items-center">
                <div class="rounded-circle p-3 me-3" style="background: rgba(250,204,21,0.15);">
                    <i class="bi bi-cash-coin fs-4" style="color:#facc15;"></i>
                </div>
                <div>
                    <div class="text-muted-light small">Pendapatan Bulan Ini</div>
                    <div class="fs-6 fw-bold"><?= formatRupiah($pendapatan) ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($sewaJatuhTempo > 0): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle-fill"></i>
        Ada <strong><?= (int) $sewaJatuhTempo ?></strong> sewa yang rencana pengembaliannya hari ini atau sudah lewat. Cek menu "Sewa Berlangsung".
    </div>
<?php endif; ?>

<h6 class="mb-3 text-muted-light"><i class="bi bi-grid-3x3-gap"></i> Papan Status Mobil</h6>
<div class="row g-3 mb-4">
    <?php if (empty($daftarMobil)): ?>
        <div class="col-12">
            <div class="card card-stat"><div class="card-body text-center text-muted-light py-5">Belum ada data mobil. Tambahkan di menu "Data Mobil".</div></div>
        </div>
    <?php endif; ?>
    <?php foreach ($daftarMobil as $m): ?>
        <div class="col-md-4 col-lg-3">
            <?php
                $kelasWarna = match ($m['status']) {
                    'Tersedia'    => 'unit-tersedia',
                    'Disewa'      => 'unit-disewa',
                    'Maintenance' => 'unit-maintenance',
                    default       => '',
                };
            ?>
            <div class="unit-card <?= $kelasWarna ?> text-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="small opacity-75"><?= clean($m['tipe']) ?></div>
                        <div class="fs-5 fw-bold"><?= clean($m['merk_model']) ?></div>
                        <div class="small opacity-75"><?= clean($m['plat_nomor']) ?></div>
                    </div>
                    <i class="bi <?= $m['status'] === 'Disewa' ? 'bi-key-fill' : ($m['status'] === 'Maintenance' ? 'bi-tools' : 'bi-check-circle') ?> fs-3 opacity-75"></i>
                </div>

                <?php if ($m['status'] === 'Disewa' && $m['nama_penyewa']): ?>
                    <hr class="border-light opacity-25 my-2">
                    <div class="small opacity-75">Penyewa: <?= clean($m['nama_penyewa']) ?></div>
                    <div class="small opacity-75">Mulai: <?= formatTanggalIndo($m['tanggal_mulai']) ?></div>
                    <div class="small opacity-75">Rencana kembali: <?= formatTanggalIndo($m['tanggal_rencana_selesai']) ?></div>
                <?php elseif ($m['status'] === 'Tersedia'): ?>
                    <hr class="border-light opacity-25 my-2">
                    <div class="small opacity-75">Siap disewakan</div>
                    <div class="fw-semibold"><?= formatRupiah($m['harga_per_hari']) ?> / hari</div>
                <?php else: ?>
                    <hr class="border-light opacity-25 my-2">
                    <div class="small opacity-75">Sedang tidak dapat disewakan</div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
