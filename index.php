<?php
/**
 * =====================================================================
 * FILE: index.php
 * FUNGSI: Entry point aplikasi. Akses http://localhost:8000/ akan
 * otomatis diarahkan ke dashboard (jika sudah login) atau login.
 * =====================================================================
 */
require_once __DIR__ . '/functions.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
} else {
    redirect('login.php');
}
