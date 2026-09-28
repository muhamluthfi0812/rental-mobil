<?php
/**
 * =====================================================================
 * FILE: login.php
 * FUNGSI: Autentikasi admin/operator rental mobil.
 *
 * MODE: LOGIN BYPASS - kredensial admin/admin123 di-hardcode langsung di
 * sini, TIDAK dicek ke database sama sekali. Cocok untuk development/
 * testing/demo cepat tanpa perlu setup tabel users atau generate hash
 * password.
 *
 * PERINGATAN: JANGAN pakai mode ini untuk rental yang dipasang di server
 * publik / komputer kasir yang bisa diakses banyak orang, karena siapa
 * pun yang membaca source code ini otomatis tahu passwordnya. Untuk versi
 * aman (cek ke database dengan password_hash/password_verify), lihat blok
 * "MODE DATABASE (aman)" yang di-comment di bawah - tinggal uncomment dan
 * buat tabel users sendiri jika suatu saat ingin upgrade.
 * =====================================================================
 */
require_once __DIR__ . '/functions.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? null);

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $errors[] = 'Username dan password wajib diisi.';

    // ---------------------------------------------------------------
    // MODE BYPASS: cek langsung ke kredensial hardcode, tanpa database.
    // ---------------------------------------------------------------
    } elseif ($username === 'admin' && $password === 'admin123') {
        session_regenerate_id(true);
        $_SESSION['user_id']      = 1; // id dummy (bukan 0, karena isLoggedIn() memakai empty())
        $_SESSION['username']     = 'admin';
        $_SESSION['nama_lengkap'] = 'Administrator Rental Mobil';

        setFlash('success', 'Selamat datang kembali, Administrator Rental Mobil!');
        redirect('dashboard.php');
    } else {
        $errors[] = 'Username atau password yang Anda masukkan salah.';

        /**
         * -----------------------------------------------------------
         * MODE DATABASE (aman, untuk production): hapus blok "elseif"
         * bypass di atas, lalu uncomment kode di bawah ini. Anda perlu
         * membuat tabel users sendiri (kolom: id, username, password,
         * nama_lengkap) dan mengisi password-nya dengan hasil
         * password_hash() di PHP, bukan teks biasa.
         * -----------------------------------------------------------
         *
         * $pdo = getConnection();
         * $stmt = $pdo->prepare('SELECT id, username, password, nama_lengkap FROM users WHERE username = :username LIMIT 1');
         * $stmt->execute(['username' => $username]);
         * $user = $stmt->fetch();
         *
         * if ($user && password_verify($password, $user['password'])) {
         *     session_regenerate_id(true);
         *     $_SESSION['user_id']      = $user['id'];
         *     $_SESSION['username']     = $user['username'];
         *     $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
         *
         *     setFlash('success', 'Selamat datang kembali, ' . $user['nama_lengkap'] . '!');
         *     redirect('dashboard.php');
         * } else {
         *     $errors[] = 'Username atau password yang Anda masukkan salah.';
         * }
         */
    }
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Rental Mobil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Inter', sans-serif;
            background: radial-gradient(circle at top left, #1c2b52, #0d1321 60%);
        }
        .login-card {
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 20px;
            background: #16213e;
            box-shadow: 0 10px 40px rgba(0,0,0,0.4);
            color: #e9edf5;
        }
        .brand-title { background: linear-gradient(90deg, #ffb703, #fb8500); -webkit-background-clip: text; background-clip: text; color: transparent; font-weight: 700; }
        .form-control { background-color: #0f1830; border: 1px solid rgba(255,255,255,0.12); color: #e9edf5; }
        .form-control:focus { background-color: #0f1830; color: #fff; border-color: #fb8500; box-shadow: 0 0 0 .2rem rgba(251,133,0,.25); }
        .input-group-text { background-color: #0f1830; border: 1px solid rgba(255,255,255,0.12); color: #9aa5c2; }
        .btn-mobil { background: linear-gradient(90deg, #fb8500, #ffb703); border: none; color: #0d1321; font-weight: 700; }
        .btn-mobil:hover { opacity: .9; color: #0d1321; }
    </style>
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">
            <div class="card login-card">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <i class="bi bi-car-front-fill" style="font-size: 2.5rem; color: #fb8500;"></i>
                        <h4 class="mt-2 mb-0 brand-title">Rental Mobil</h4>
                        <small style="color:#9aa5c2;">Silakan login untuk melanjutkan</small>
                    </div>

                    <div class="alert alert-info small py-2">
                        <i class="bi bi-info-circle"></i> Mode testing: login dengan <strong>admin</strong> / <strong>admin123</strong>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <?php foreach ($errors as $error): ?>
                                <div><?= clean($error) ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="login.php">
                        <input type="hidden" name="csrf_token" value="<?= clean($csrfToken) ?>">

                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control" name="username" required autofocus
                                       value="<?= clean($_POST['username'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" name="password" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-mobil w-100">
                            <i class="bi bi-box-arrow-in-right"></i> Login
                        </button>
                    </form>
                </div>
            </div>
            <p class="text-center mt-3 small" style="color:#5c6584;">&copy; <?= date('Y') ?> Sistem Rental Mobil</p>
        </div>
    </div>
</div>
</body>
</html>
