<?php
/**
 * =====================================================================
 * FILE: mobil.php
 * FUNGSI: CRUD penuh untuk data Mobil (Tampil, Tambah, Edit, Hapus).
 * =====================================================================
 */
require_once __DIR__ . '/functions.php';
requireLogin();

$pdo = getConnection();
$errors = [];

// --- TAMBAH / EDIT MOBIL ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'simpan_mobil') {
    verifyCsrfToken($_POST['csrf_token'] ?? null);

    $id             = $_POST['id'] ?? '';
    $plat_nomor     = strtoupper(trim($_POST['plat_nomor'] ?? ''));
    $merk_model     = trim($_POST['merk_model'] ?? '');
    $tipe           = $_POST['tipe'] ?? 'MPV';
    $tahun          = trim($_POST['tahun'] ?? '');
    $harga_per_hari = trim($_POST['harga_per_hari'] ?? '');
    $status         = $_POST['status'] ?? 'Tersedia';

    if (empty($plat_nomor)) {
        $errors[] = 'Plat nomor wajib diisi.';
    }
    if (empty($merk_model)) {
        $errors[] = 'Merk/model wajib diisi.';
    }
    if (!in_array($tipe, ['City Car', 'MPV', 'SUV', 'Sedan', 'Pickup'], true)) {
        $errors[] = 'Tipe mobil tidak valid.';
    }
    if (!empty($tahun) && (!ctype_digit($tahun) || strlen($tahun) !== 4)) {
        $errors[] = 'Tahun harus 4 digit angka.';
    }
    if (!is_numeric($harga_per_hari) || $harga_per_hari <= 0) {
        $errors[] = 'Harga per hari harus berupa angka positif.';
    }
    if (!in_array($status, ['Tersedia', 'Disewa', 'Maintenance'], true)) {
        $errors[] = 'Status tidak valid.';
    }

    // Cegah admin mengubah status jadi Tersedia/Maintenance secara manual
    // padahal mobil sedang punya sewa Berlangsung (harus lewat menu Kembalikan Mobil)
    if (empty($errors) && !empty($id) && $status !== 'Disewa') {
        $cekAktif = $pdo->prepare("SELECT COUNT(*) AS jumlah FROM sewa WHERE mobil_id = :id AND status = 'Berlangsung'");
        $cekAktif->execute(['id' => $id]);
        if ($cekAktif->fetch()['jumlah'] > 0) {
            $errors[] = 'Mobil ini masih memiliki sewa yang berlangsung. Kembalikan dulu lewat menu "Sewa Berlangsung".';
        }
    }

    if (empty($errors)) {
        if (empty($id)) {
            $cek = $pdo->prepare('SELECT id FROM mobil WHERE plat_nomor = :plat_nomor');
            $cek->execute(['plat_nomor' => $plat_nomor]);

            if ($cek->fetch()) {
                setFlash('danger', 'Plat nomor "' . $plat_nomor . '" sudah terdaftar.');
            } else {
                $stmt = $pdo->prepare('
                    INSERT INTO mobil (plat_nomor, merk_model, tipe, tahun, harga_per_hari, status)
                    VALUES (:plat_nomor, :merk_model, :tipe, :tahun, :harga_per_hari, :status)
                ');
                $stmt->execute([
                    'plat_nomor'     => $plat_nomor,
                    'merk_model'     => $merk_model,
                    'tipe'           => $tipe,
                    'tahun'          => $tahun ?: null,
                    'harga_per_hari' => $harga_per_hari,
                    'status'         => $status,
                ]);
                setFlash('success', 'Mobil "' . $merk_model . '" berhasil ditambahkan.');
            }
        } else {
            $cek = $pdo->prepare('SELECT id FROM mobil WHERE plat_nomor = :plat_nomor AND id != :id');
            $cek->execute(['plat_nomor' => $plat_nomor, 'id' => $id]);

            if ($cek->fetch()) {
                setFlash('danger', 'Plat nomor "' . $plat_nomor . '" sudah dipakai mobil lain.');
            } else {
                $stmt = $pdo->prepare('
                    UPDATE mobil
                    SET plat_nomor = :plat_nomor, merk_model = :merk_model, tipe = :tipe,
                        tahun = :tahun, harga_per_hari = :harga_per_hari, status = :status
                    WHERE id = :id
                ');
                $stmt->execute([
                    'plat_nomor'     => $plat_nomor,
                    'merk_model'     => $merk_model,
                    'tipe'           => $tipe,
                    'tahun'          => $tahun ?: null,
                    'harga_per_hari' => $harga_per_hari,
                    'status'         => $status,
                    'id'             => $id,
                ]);
                setFlash('success', 'Data mobil "' . $merk_model . '" berhasil diperbarui.');
            }
        }
    } else {
        setFlash('danger', implode(' ', $errors));
    }

    redirect('mobil.php');
}

// --- HAPUS MOBIL ---
if (isset($_GET['aksi']) && $_GET['aksi'] === 'hapus' && isset($_GET['id'])) {
    verifyCsrfToken($_GET['csrf_token'] ?? null);
    $id = (int) $_GET['id'];

    $cekRiwayat = $pdo->prepare('SELECT COUNT(*) AS jumlah FROM sewa WHERE mobil_id = :id');
    $cekRiwayat->execute(['id' => $id]);
    $adaRiwayat = $cekRiwayat->fetch()['jumlah'] > 0;

    if ($adaRiwayat) {
        setFlash('danger', 'Mobil tidak dapat dihapus karena memiliki riwayat transaksi sewa. Ubah status menjadi "Maintenance" jika mobil ingin dinonaktifkan.');
    } else {
        $stmt = $pdo->prepare('DELETE FROM mobil WHERE id = :id');
        $stmt->execute(['id' => $id]);
        setFlash('success', 'Data mobil berhasil dihapus.');
    }

    redirect('mobil.php');
}

// --- AMBIL DATA ---
$daftarMobil = $pdo->query('SELECT * FROM mobil ORDER BY plat_nomor ASC')->fetchAll();

$csrfToken  = generateCsrfToken();
$pageTitle  = 'Data Mobil';
$activeMenu = 'mobil';
require_once __DIR__ . '/header.php';
?>

<div class="card card-glass">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-car-front"></i> Daftar Mobil</span>
        <button class="btn btn-primary-mobil btn-sm" data-bs-toggle="modal" data-bs-target="#modalMobil" onclick="bukaModalTambah()">
            <i class="bi bi-plus-circle"></i> Tambah Mobil
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Plat Nomor</th>
                        <th>Merk/Model</th>
                        <th>Tipe</th>
                        <th>Tahun</th>
                        <th>Harga/Hari</th>
                        <th>Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($daftarMobil)): ?>
                        <tr><td colspan="8" class="text-center text-muted-light py-4">Belum ada data mobil.</td></tr>
                    <?php else: ?>
                        <?php foreach ($daftarMobil as $i => $m): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td class="fw-semibold"><?= clean($m['plat_nomor']) ?></td>
                                <td><?= clean($m['merk_model']) ?></td>
                                <td><?= clean($m['tipe']) ?></td>
                                <td><?= clean($m['tahun'] ?? '-') ?></td>
                                <td><?= formatRupiah($m['harga_per_hari']) ?></td>
                                <td><span class="badge bg-<?= badgeStatusMobil($m['status']) ?>"><?= clean($m['status']) ?></span></td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-light"
                                        data-bs-toggle="modal" data-bs-target="#modalMobil"
                                        onclick='bukaModalEdit(<?= json_encode($m, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <a class="btn btn-sm btn-outline-danger"
                                       href="mobil.php?aksi=hapus&id=<?= (int) $m['id'] ?>&csrf_token=<?= clean($csrfToken) ?>"
                                       onclick="return confirm('Yakin ingin menghapus mobil <?= clean($m['merk_model']) ?>?')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalMobil" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="mobil.php">
                <input type="hidden" name="csrf_token" value="<?= clean($csrfToken) ?>">
                <input type="hidden" name="aksi" value="simpan_mobil">
                <input type="hidden" name="id" id="form_id">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalMobilLabel">Tambah Mobil</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Plat Nomor</label>
                        <input type="text" class="form-control" name="plat_nomor" id="form_plat_nomor" required maxlength="15" placeholder="Contoh: B 1234 XYZ">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Merk/Model</label>
                        <input type="text" class="form-control" name="merk_model" id="form_merk_model" required maxlength="100" placeholder="Contoh: Toyota Avanza">
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label">Tipe</label>
                            <select class="form-select" name="tipe" id="form_tipe">
                                <option value="City Car">City Car</option>
                                <option value="MPV">MPV</option>
                                <option value="SUV">SUV</option>
                                <option value="Sedan">Sedan</option>
                                <option value="Pickup">Pickup</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Tahun (Opsional)</label>
                            <input type="number" class="form-control" name="tahun" id="form_tahun" min="1990" max="2100" placeholder="2023">
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label">Harga per Hari (Rp)</label>
                        <input type="number" class="form-control" name="harga_per_hari" id="form_harga_per_hari" required min="0" step="5000">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" id="form_status">
                            <option value="Tersedia">Tersedia</option>
                            <option value="Disewa">Disewa</option>
                            <option value="Maintenance">Maintenance</option>
                        </select>
                        <div class="form-text">Status "Disewa" idealnya diatur otomatis lewat menu "Sewa Berlangsung", bukan diubah manual di sini.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary-mobil">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function bukaModalTambah() {
        document.getElementById('modalMobilLabel').innerText = 'Tambah Mobil';
        document.getElementById('form_id').value = '';
        document.getElementById('form_plat_nomor').value = '';
        document.getElementById('form_merk_model').value = '';
        document.getElementById('form_tipe').value = 'MPV';
        document.getElementById('form_tahun').value = '';
        document.getElementById('form_harga_per_hari').value = '';
        document.getElementById('form_status').value = 'Tersedia';
    }

    function bukaModalEdit(data) {
        document.getElementById('modalMobilLabel').innerText = 'Edit Mobil - ' + data.merk_model;
        document.getElementById('form_id').value = data.id;
        document.getElementById('form_plat_nomor').value = data.plat_nomor;
        document.getElementById('form_merk_model').value = data.merk_model;
        document.getElementById('form_tipe').value = data.tipe;
        document.getElementById('form_tahun').value = data.tahun ?? '';
        document.getElementById('form_harga_per_hari').value = data.harga_per_hari;
        document.getElementById('form_status').value = data.status;
    }
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
