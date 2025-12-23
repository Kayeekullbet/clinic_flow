<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$JWT_SECRET = $JWT_SECRET ?? 'ganti_dengan_kunci_rahasia_yang_panjang';

// ====== CEK TOKEN JWT & ROLE DOKTER ======
$role    = null;
$user_id = null;
$nama    = '';

if (!empty($_COOKIE['token'])) {
    try {
        $decoded = JWT::decode($_COOKIE['token'], new Key($JWT_SECRET, 'HS256'));
        $data    = (array)$decoded->data;
        $role    = $data['role']    ?? null;
        $user_id = $data['user_id'] ?? null;
        $nama    = $data['nama']    ?? '';
    } catch (Exception $e) {
        $role    = null;
        $user_id = null;
    }
}

if ($role !== 'dokter') {
    header('Location: index.php');
    exit;
}

// ====== DAPATKAN ID DOKTER (TABEL dokter) DARI user_id ======
$stmtDokter = $pdo->prepare("
    SELECT d.id AS dokter_id
    FROM dokter d
    WHERE d.user_id = :uid
    LIMIT 1
");
$stmtDokter->execute([':uid' => $user_id]);
$rowDokter = $stmtDokter->fetch(PDO::FETCH_ASSOC);

if (!$rowDokter) {
    die('Akun ini belum terhubung ke data dokter. Hubungi admin.');
}
$dokter_id = (int)$rowDokter['dokter_id'];

// ====== HITUNG RINGKASAN (JADWAL & ANTRIAN) ======
$today = date('Y-m-d');

// jumlah jadwal akan datang
$stmtJadwal = $pdo->prepare("
    SELECT COUNT(*) AS total
    FROM jadwal_dokter
    WHERE dokter_id = :dokter_id
      AND tanggal >= :today
");
$stmtJadwal->execute([
    ':dokter_id' => $dokter_id,
    ':today'     => $today,
]);
$jadwalCount = (int)$stmtJadwal->fetchColumn();

// jumlah antrian hari ini
$stmtAntrian = $pdo->prepare("
    SELECT COUNT(*) AS total
    FROM antrian a
    JOIN booking b   ON a.booking_id = b.id
    JOIN jadwal_dokter j ON b.jadwal_id = j.id
    WHERE j.dokter_id = :dokter_id
      AND j.tanggal   = :today
");
$stmtAntrian->execute([
    ':dokter_id' => $dokter_id,
    ':today'     => $today,
]);
$antrianCount = (int)$stmtAntrian->fetchColumn();

// ====== TELECONSULT HARI INI UNTUK DOKTER INI ======
$stmtTc = $pdo->prepare("
    SELECT t.id,
           t.jadwal_waktu,
           u.nama AS pasien_nama
    FROM teleconsult_appointments t
    JOIN users u ON t.pasien_id = u.id
    WHERE t.dokter_id = :uid
      AND DATE(t.jadwal_waktu) = :today
    ORDER BY t.jadwal_waktu ASC
");
$stmtTc->execute([
    ':uid'   => $user_id,   // karena di teleconsult_appointments kita pakai dokter_id = users.id
    ':today' => $today,
]);
$tc_list = $stmtTc->fetchAll(PDO::FETCH_ASSOC);
$tcCount = count($tc_list);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Dokter - ClinicFlow</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <style>
        body { background-color: #f4f6f9; }
        .navbar-brand { font-weight: 600; }
        .sidebar {
            min-height: 100vh;
            background-color: #0d6efd;
            color: #fff;
        }
        .sidebar a { color: #e9ecef; text-decoration: none; }
        .sidebar a.active,
        .sidebar a:hover {
            background-color: rgba(255, 255, 255, 0.15);
            color: #fff;
        }
        .sidebar .nav-link {
            padding: 0.75rem 1rem;
            border-radius: 0.375rem;
        }
        .page-title { font-weight: 600; margin-bottom: 0.5rem; }
        .card { border: none; box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.08); }
        .logout-link { cursor: pointer; }
        .table-sm td, .table-sm th { padding: .4rem .6rem; font-size: .85rem; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">
            <i class="bi bi-heart-pulse-fill text-primary me-1"></i>ClinicFlow Dokter
        </a>
        <div class="d-flex align-items-center">
            <span class="me-3 text-muted small">
                Anda login sebagai <strong><?php echo htmlspecialchars($nama ?: 'dokter'); ?></strong>
            </span>
            <a href="logout.php" class="btn btn-outline-danger btn-sm logout-link">
                <i class="bi bi-box-arrow-right me-1"></i>Logout
            </a>
        </div>
    </div>
</nav>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <aside class="col-md-3 col-lg-2 p-3 sidebar">
            <h6 class="text-uppercase text-white-50 mb-3">Menu</h6>
            <nav class="nav nav-pills flex-column gap-1">
                <a href="dashboard_dokter.php" class="nav-link active">
                    <i class="bi bi-speedometer2 me-2"></i>Dashboard
                </a>
                <a href="dokter_jadwal.php" class="nav-link">
                    <i class="bi bi-calendar-check me-2"></i>Jadwal Praktik Saya
                </a>
                <a href="antrian_hari_ini.php" class="nav-link">
                    <i class="bi bi-people-fill me-2"></i>Antrian Pasien Hari Ini
                </a>
            </nav>
        </aside>

        <!-- Konten -->
        <main class="col-md-9 col-lg-10 p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h2 class="page-title">Dashboard Dokter</h2>
                    <p class="text-muted mb-0">
                        Selamat datang, <?php echo htmlspecialchars($nama ?: 'Dokter'); ?>.
                    </p>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="text-muted text-uppercase small">Jadwal Praktik</h6>
                            <p class="fs-4 mb-1" id="jadwalCount"><?php echo $jadwalCount; ?></p>
                            <p class="text-muted mb-0">Jadwal praktik yang akan datang.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="text-muted text-uppercase small">Antrian Hari Ini</h6>
                            <p class="fs-4 mb-1" id="antrianCount"><?php echo $antrianCount; ?></p>
                            <p class="text-muted mb-0">Jumlah pasien di antrian hari ini.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="text-muted text-uppercase small">
                                Teleconsult Hari Ini
                            </h6>
                            <p class="fs-4 mb-1"><?php echo $tcCount; ?></p>
                            <p class="text-muted mb-0">
                                Sesi teleconsult terjadwal untuk hari ini.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Teleconsult List -->
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Teleconsult Hari Ini</span>
                    <small class="text-muted">
                        <?php echo date('d M Y'); ?>
                    </small>
                </div>
                <div class="card-body">
                    <?php if ($tcCount === 0): ?>
                        <p class="text-muted mb-0">
                            Belum ada jadwal teleconsult untuk hari ini.
                        </p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 40%;">Pasien</th>
                                        <th style="width: 35%;">Jadwal</th>
                                        <th style="width: 25%;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($tc_list as $t): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($t['pasien_nama']); ?></td>
                                        <td>
                                            <?php echo date('d M Y H:i', strtotime($t['jadwal_waktu'])); ?>
                                        </td>
                                        <td>
                                            <a href="teleconsult_room.php?id=<?php echo (int)$t['id']; ?>"
                                               class="btn btn-sm btn-primary">
                                                <i class="bi bi-chat-dots me-1"></i>Masuk Ruang
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Catatan Cepat -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Catatan Cepat</span>
                    <button class="btn btn-sm btn-outline-primary" id="btnClearNotes">
                        Bersihkan
                    </button>
                </div>
                <div class="card-body">
                    <textarea id="quickNotes" class="form-control" rows="4"
                        placeholder="Tulis catatan singkat untuk pasien atau jadwal hari ini..."></textarea>
                    <small class="text-muted d-block mt-1">
                        Catatan ini hanya tersimpan di browser (localStorage).
                    </small>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const notesKey = 'clinicflow_dokter_notes';
    const notesArea = document.getElementById('quickNotes');
    const btnClear  = document.getElementById('btnClearNotes');

    const saved = localStorage.getItem(notesKey);
    if (saved) {
        notesArea.value = saved;
    }

    notesArea.addEventListener('input', function () {
        localStorage.setItem(notesKey, notesArea.value);
    });

    btnClear.addEventListener('click', function () {
        if (confirm('Hapus semua catatan?')) {
            notesArea.value = '';
            localStorage.removeItem(notesKey);
        }
    });
});
</script>
</body>
</html>