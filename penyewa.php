<?php
/**
 * =====================================================================
 * FILE: penyewa.php
 * FUNGSI: CRUD penuh untuk data Penyewa/Pelanggan.
 * =====================================================================
 */
require_once __DIR__ . '/functions.php';
requireLogin();

$pdo = getConnection();
$errors = [];

// --- TAMBAH / EDIT PENYEWA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'simpan_penyewa') {
    verifyCsrfToken($_POST['csrf_token'] ?? null);

    $id           = $_POST['id'] ?? '';
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $no_ktp       = trim($_POST['no_ktp'] ?? '');
    $no_hp        = trim($_POST['no_hp'] ?? '');
    $alamat       = trim($_POST['alamat'] ?? '');

    if (empty($nama_lengkap)) {
        $errors[] = 'Nama lengkap wajib diisi.';
    }
    if (empty($no_ktp) || !ctype_digit($no_ktp) || strlen($no_ktp) !== 16) {
        $errors[] = 'Nomor KTP harus terdiri dari 16 digit angka.';
    }
    if (empty($no_hp)) {
        $errors[] = 'Nomor HP wajib diisi.';
    }

    if (empty($errors)) {
        if (empty($id)) {
            $cek = $pdo->prepare('SELECT id FROM penyewa WHERE no_ktp = :no_ktp');
            $cek->execute(['no_ktp' => $no_ktp]);
        } else {
            $cek = $pdo->prepare('SELECT id FROM penyewa WHERE no_ktp = :no_ktp AND id != :id');
            $cek->execute(['no_ktp' => $no_ktp, 'id' => $id]);
        }

        if ($cek->fetch()) {
            setFlash('danger', 'Nomor KTP "' . $no_ktp . '" sudah terdaftar atas penyewa lain.');
        } elseif (empty($id)) {
            $stmt = $pdo->prepare('
                INSERT INTO penyewa (nama_lengkap, no_ktp, no_hp, alamat)
                VALUES (:nama_lengkap, :no_ktp, :no_hp, :alamat)
            ');
            $stmt->execute([
                'nama_lengkap' => $nama_lengkap,
                'no_ktp'       => $no_ktp,
                'no_hp'        => $no_hp,
                'alamat'       => $alamat ?: null,
            ]);
            setFlash('success', 'Data penyewa "' . $nama_lengkap . '" berhasil ditambahkan.');
        } else {
            $stmt = $pdo->prepare('
                UPDATE penyewa
                SET nama_lengkap = :nama_lengkap, no_ktp = :no_ktp, no_hp = :no_hp, alamat = :alamat
                WHERE id = :id
            ');
            $stmt->execute([
                'nama_lengkap' => $nama_lengkap,
                'no_ktp'       => $no_ktp,
                'no_hp'        => $no_hp,
                'alamat'       => $alamat ?: null,
                'id'           => $id,
            ]);
            setFlash('success', 'Data penyewa "' . $nama_lengkap . '" berhasil diperbarui.');
        }
    } else {
        setFlash('danger', implode(' ', $errors));
    }

    redirect('penyewa.php');
}

// --- HAPUS PENYEWA ---
if (isset($_GET['aksi']) && $_GET['aksi'] === 'hapus' && isset($_GET['id'])) {
    verifyCsrfToken($_GET['csrf_token'] ?? null);
    $id = (int) $_GET['id'];

    $cekRiwayat = $pdo->prepare('SELECT COUNT(*) AS jumlah FROM sewa WHERE penyewa_id = :id');
    $cekRiwayat->execute(['id' => $id]);
    $adaRiwayat = $cekRiwayat->fetch()['jumlah'] > 0;

    if ($adaRiwayat) {
        setFlash('danger', 'Penyewa tidak dapat dihapus karena memiliki riwayat transaksi sewa.');
    } else {
        $stmt = $pdo->prepare('DELETE FROM penyewa WHERE id = :id');
        $stmt->execute(['id' => $id]);
        setFlash('success', 'Data penyewa berhasil dihapus.');
    }

    redirect('penyewa.php');
}

// --- AMBIL DATA (dengan pencarian) ---
$keyword = trim($_GET['q'] ?? '');

if (!empty($keyword)) {
    $stmt = $pdo->prepare('
        SELECT * FROM penyewa
        WHERE nama_lengkap LIKE :keyword OR no_ktp LIKE :keyword OR no_hp LIKE :keyword
        ORDER BY nama_lengkap ASC
    ');
    $stmt->execute(['keyword' => '%' . $keyword . '%']);
} else {
    $stmt = $pdo->query('SELECT * FROM penyewa ORDER BY nama_lengkap ASC');
}
$daftarPenyewa = $stmt->fetchAll();

$csrfToken  = generateCsrfToken();
$pageTitle  = 'Data Penyewa';
$activeMenu = 'penyewa';
require_once __DIR__ . '/header.php';
?>

<div class="card card-glass">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <form method="GET" action="penyewa.php" class="d-flex" role="search">
            <input type="text" name="q" class="form-control form-control-sm me-2" placeholder="Cari nama/KTP/HP..." value="<?= clean($keyword) ?>" style="width: 220px;">
            <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
        </form>
        <button class="btn btn-primary-mobil btn-sm" data-bs-toggle="modal" data-bs-target="#modalPenyewa" onclick="bukaModalTambah()">
            <i class="bi bi-person-plus"></i> Tambah Penyewa
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Nama Lengkap</th>
                        <th>No. KTP</th>
                        <th>No. HP</th>
                        <th>Alamat</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($daftarPenyewa)): ?>
                        <tr><td colspan="6" class="text-center text-muted-light py-4">Tidak ada data penyewa.</td></tr>
                    <?php else: ?>
                        <?php foreach ($daftarPenyewa as $i => $p): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td class="fw-semibold"><?= clean($p['nama_lengkap']) ?></td>
                                <td><?= clean($p['no_ktp']) ?></td>
                                <td><?= clean($p['no_hp']) ?></td>
                                <td><?= clean($p['alamat'] ?? '-') ?></td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-light"
                                        data-bs-toggle="modal" data-bs-target="#modalPenyewa"
                                        onclick='bukaModalEdit(<?= json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <a class="btn btn-sm btn-outline-danger"
                                       href="penyewa.php?aksi=hapus&id=<?= (int) $p['id'] ?>&csrf_token=<?= clean($csrfToken) ?>"
                                       onclick="return confirm('Yakin ingin menghapus data <?= clean($p['nama_lengkap']) ?>?')">
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

<div class="modal fade" id="modalPenyewa" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="penyewa.php">
                <input type="hidden" name="csrf_token" value="<?= clean($csrfToken) ?>">
                <input type="hidden" name="aksi" value="simpan_penyewa">
                <input type="hidden" name="id" id="form_id">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalPenyewaLabel">Tambah Penyewa</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap</label>
                        <input type="text" class="form-control" name="nama_lengkap" id="form_nama_lengkap" required maxlength="100">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nomor KTP</label>
                        <input type="text" class="form-control" name="no_ktp" id="form_no_ktp" required maxlength="16" pattern="\d{16}" title="Harus 16 digit angka">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nomor HP</label>
                        <input type="text" class="form-control" name="no_hp" id="form_no_hp" required maxlength="20">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Alamat (Opsional)</label>
                        <textarea class="form-control" name="alamat" id="form_alamat" rows="2"></textarea>
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
        document.getElementById('modalPenyewaLabel').innerText = 'Tambah Penyewa';
        document.getElementById('form_id').value = '';
        document.getElementById('form_nama_lengkap').value = '';
        document.getElementById('form_no_ktp').value = '';
        document.getElementById('form_no_hp').value = '';
        document.getElementById('form_alamat').value = '';
    }

    function bukaModalEdit(data) {
        document.getElementById('modalPenyewaLabel').innerText = 'Edit Penyewa - ' + data.nama_lengkap;
        document.getElementById('form_id').value = data.id;
        document.getElementById('form_nama_lengkap').value = data.nama_lengkap;
        document.getElementById('form_no_ktp').value = data.no_ktp;
        document.getElementById('form_no_hp').value = data.no_hp;
        document.getElementById('form_alamat').value = data.alamat ?? '';
    }
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
