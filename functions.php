<?php
/**
 * =====================================================================
 * FILE: functions.php
 * FUNGSI: Kumpulan fungsi utilitas: session, sanitasi (anti XSS),
 * flash message, format tampilan, proteksi CSRF, dan kalkulasi biaya sewa.
 * =====================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

function clean(?string $data): string
{
    if ($data === null) {
        return '';
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        setFlash('danger', 'Silakan login terlebih dahulu untuk mengakses halaman ini.');
        redirect('login.php');
    }
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type'    => $type,
        'message' => $message,
    ];
}

function showFlash(): void
{
    if (!empty($_SESSION['flash'])) {
        $type    = clean($_SESSION['flash']['type']);
        $message = clean($_SESSION['flash']['message']);
        echo <<<HTML
        <div class="alert alert-{$type} alert-dismissible fade show" role="alert">
            {$message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        HTML;
        unset($_SESSION['flash']);
    }
}

function formatRupiah($angka): string
{
    return 'Rp ' . number_format((float) $angka, 0, ',', '.');
}

function formatTanggalIndo(?string $tanggal): string
{
    if (empty($tanggal)) {
        return '-';
    }
    $bulanIndo = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];
    $ts = strtotime($tanggal);
    return date('d', $ts) . ' ' . $bulanIndo[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}

function badgeStatusMobil(string $status): string
{
    return match ($status) {
        'Tersedia'    => 'success',
        'Disewa'      => 'danger',
        'Maintenance' => 'warning',
        default       => 'secondary',
    };
}

function badgeStatusSewa(string $status): string
{
    return match ($status) {
        'Berlangsung' => 'primary',
        'Selesai'     => 'success',
        default       => 'secondary',
    };
}

/**
 * Menghitung durasi sewa (dalam hari, dibulatkan ke atas - praktik umum
 * rental mobil: sewa yang belum genap 24 jam tetap dihitung 1 hari penuh,
 * dan minimum sewa adalah 1 hari) dan total biaya.
 *
 * @param string $tanggalMulai   Format Y-m-d
 * @param string $tanggalKembali Format Y-m-d
 * @param float  $hargaPerHari
 * @return array{hari: int, biaya: float}
 */
function hitungBiayaSewaMobil(string $tanggalMulai, string $tanggalKembali, float $hargaPerHari): array
{
    $mulai   = new DateTime($tanggalMulai);
    $kembali = new DateTime($tanggalKembali);
    $selisihHari = (int) $mulai->diff($kembali)->days;

    // Minimum sewa 1 hari, meskipun mobil dikembalikan di hari yang sama
    $hari = max(1, $selisihHari);
    $biaya = $hari * $hargaPerHari;

    return ['hari' => $hari, 'biaya' => $biaya];
}

function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(?string $token): void
{
    if (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        setFlash('danger', 'Sesi form tidak valid atau kedaluwarsa. Silakan coba lagi.');
        redirect($_SERVER['HTTP_REFERER'] ?? 'dashboard.php');
    }
}
