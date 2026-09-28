<?php
/**
 * =====================================================================
 * FILE: header.php
 * FUNGSI: Template bagian atas (head, navbar, sidebar) bertema otomotif.
 * =====================================================================
 */
if (!isLoggedIn()) {
    requireLogin();
}
$pageTitle = $pageTitle ?? 'Rental Mobil';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= clean($pageTitle) ?> - Rental Mobil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background: #0d1321;
            font-family: 'Inter', sans-serif;
            color: #e9edf5;
        }
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #14213d 0%, #0d1321 100%);
            border-right: 1px solid rgba(255,255,255,0.06);
        }
        .sidebar a { color: #9aa5c2; text-decoration: none; }
        .sidebar a.active, .sidebar a:hover { background: linear-gradient(90deg, #fb8500, #ffb703); color: #0d1321; font-weight: 700; }
        .sidebar .nav-link { padding: 0.75rem 1rem; border-radius: 10px; margin-bottom: 6px; font-weight: 500; transition: all .15s; }
        .brand-title { background: linear-gradient(90deg, #ffb703, #fb8500); -webkit-background-clip: text; background-clip: text; color: transparent; font-weight: 700; }
        .card-stat, .card-glass {
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 16px;
            background: #16213e;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            color: #e9edf5;
        }
        .card-glass .card-header { background: transparent; border-bottom: 1px solid rgba(255,255,255,0.07); color: #c3cbe6; font-weight: 600; }
        .table { color: #e9edf5; }
        .table > :not(caption) > * > * { background-color: transparent; color: #e9edf5; }
        .table-hover > tbody > tr:hover > * { background-color: rgba(255,255,255,0.04); }
        .table-light, thead.table-light th { background-color: #1b2a4a !important; color: #c3cbe6 !important; border-color: rgba(255,255,255,0.07); }
        .form-control, .form-select {
            background-color: #0f1830; border: 1px solid rgba(255,255,255,0.12); color: #e9edf5;
        }
        .form-control:focus, .form-select:focus {
            background-color: #0f1830; color: #fff; border-color: #fb8500; box-shadow: 0 0 0 .2rem rgba(251,133,0,.25);
        }
        .modal-content { background-color: #16213e; color: #e9edf5; }
        .btn-primary-mobil {
            background: linear-gradient(90deg, #fb8500, #ffb703);
            border: none; color: #0d1321; font-weight: 700;
        }
        .btn-primary-mobil:hover { opacity: .9; color: #0d1321; }
        .unit-card {
            border-radius: 18px;
            padding: 1.25rem;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.08);
            transition: transform .15s;
        }
        .unit-card:hover { transform: translateY(-3px); }
        .unit-tersedia { background: linear-gradient(135deg, #0f5132, #14532d); }
        .unit-disewa   { background: linear-gradient(135deg, #7f1d1d, #991b1b); }
        .unit-maintenance { background: linear-gradient(135deg, #78350f, #92400e); }
        .text-muted-light { color: #9aa5c2; }
    </style>
</head>
<body>
<div class="d-flex">
    <nav class="sidebar p-3" style="width: 250px;">
        <h5 class="brand-title mb-4"><i class="bi bi-car-front-fill"></i> Rental Mobil</h5>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= ($activeMenu ?? '') === 'dashboard' ? 'active' : '' ?>" href="dashboard.php">
                    <i class="bi bi-speedometer2 me-2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($activeMenu ?? '') === 'sewa' ? 'active' : '' ?>" href="sewa.php">
                    <i class="bi bi-key-fill me-2"></i> Sewa Berlangsung
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($activeMenu ?? '') === 'riwayat' ? 'active' : '' ?>" href="riwayat.php">
                    <i class="bi bi-clock-history me-2"></i> Riwayat Transaksi
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($activeMenu ?? '') === 'mobil' ? 'active' : '' ?>" href="mobil.php">
                    <i class="bi bi-car-front me-2"></i> Data Mobil
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($activeMenu ?? '') === 'penyewa' ? 'active' : '' ?>" href="penyewa.php">
                    <i class="bi bi-people-fill me-2"></i> Data Penyewa
                </a>
            </li>
            <li class="nav-item mt-4">
                <a class="nav-link text-danger" href="logout.php">
                    <i class="bi bi-power me-2"></i> Logout
                </a>
            </li>
        </ul>
    </nav>

    <main class="flex-fill p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0"><?= clean($pageTitle) ?></h4>
            <span class="text-muted-light">
                <i class="bi bi-person-circle"></i> <?= clean($_SESSION['nama_lengkap'] ?? 'Admin') ?>
            </span>
        </div>
        <?php showFlash(); ?>
