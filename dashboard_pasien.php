<?php
// dashboard_pasien.php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// ====== CEK JWT & ROLE PASIEN ======
$role    = null;
$user_id = null;

if (!empty($_COOKIE['token'])) {
    try {
        $decoded = JWT::decode($_COOKIE['token'], new Key($JWT_SECRET, 'HS256'));
        $data    = (array) $decoded->data;
        $role    = $data['role']    ?? null;
        $user_id = $data['user_id'] ?? null;
    } catch (Exception $e) {
        $role    = null;
        $user_id = null;
    }
}

if ($role !== 'pasien' || !$user_id) {
    header('Location: index.php');
    exit;
}

// ====== AMBIL DATA PASIEN & RINGKASAN (PDO) ======

// data pasien + user
$stmtPasien = $pdo->prepare("
    SELECT p.id AS pasien_id, u.nama
    FROM pasien p
    JOIN users u ON p.user_id = u.id
    WHERE p.user_id = :uid
    LIMIT 1
");
$stmtPasien->execute([':uid' => $user_id]);
$pasien = $stmtPasien->fetch();

$pasienId = $pasien ? (int)$pasien['pasien_id'] : 0;

// jumlah booking aktif (tanggal >= hari ini)
$totalBooking = 0;
$totalAntrianHariIni = 0;

if ($pasienId) {
    $stmtBook = $pdo->prepare("
        SELECT COUNT(*) AS jml
        FROM booking b
        JOIN jadwal_dokter j ON b.jadwal_id = j.id
        WHERE b.pasien_id = :pid
          AND j.tanggal >= CURDATE()
    ");
    $stmtBook->execute([':pid' => $pasienId]);
    $totalBooking = (int)($stmtBook->fetch()['jml'] ?? 0);

    $stmtAnt = $pdo->prepare("
        SELECT COUNT(*) AS jml
        FROM antrian a
        JOIN booking b ON a.booking_id = b.id
        JOIN jadwal_dokter j ON b.jadwal_id = j.id
        WHERE b.pasien_id = :pid
          AND j.tanggal = CURDATE()
    ");
    $stmtAnt->execute([':pid' => $pasienId]);
    $totalAntrianHariIni = (int)($stmtAnt->fetch()['jml'] ?? 0);
}

include 'header.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Pasien - ClinicFlow</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        body {
            background: #f3f4f8;
        }
        .navbar-brand {
            font-weight: 600;
        }
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #0ea5e9, #2563eb);
            color: #fff;
        }
        .sidebar .nav-link {
            color: #e5ecff;
            border-radius: .5rem;
            padding: .6rem .9rem;
            font-size: .9rem;
        }
        .sidebar .nav-link.active,
        .sidebar .nav-link:hover {
            background-color: rgba(255,255,255,0.18);
            color: #fff;
        }
        .pill-role {
            padding: .18rem .6rem;
            border-radius: 999px;
            background: rgba(15,23,42,0.08);
            font-size: .75rem;
        }
        .stat-card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 .5rem 1.4rem rgba(15,23,42,0.10);
        }
        .stat-icon {
            width: 38px;
            height: 38px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        .hero-patient {
            border-radius: 1rem;
            padding: 1.4rem 1.6rem;
            background: radial-gradient(circle at top left, #38bdf8, #2563eb, #1d4ed8);
            color: #eff6ff;
            margin-bottom: 1.4rem;
            position: relative;
            overflow: hidden;
        }
        .hero-patient::after {
            content: "";
            position: absolute;
            right: -40px;
            top: -40px;
            width: 140px;
            height: 140px;
            background: rgba(255,255,255,0.17);
            border-radius: 999px;
            filter: blur(3px);
        }
        .hero-dot {
            width: 9px;
            height: 9px;
            border-radius: 999px;
            background-color: #22c55e;
            position: relative;
        }
        .hero-dot::after {
            content: "";
            position: absolute;
            inset: -4px;
            border-radius: inherit;
            border: 2px solid rgba(34,197,94,0.6);
            animation: pulse 1.4s ease-out infinite;
        }
        @keyframes pulse {
            0% { transform: scale(0.8); opacity: 1; }
            100% { transform: scale(1.3); opacity: 0; }
        }
        .appt-card {
            border-radius: 1rem;
            border: none;
            box-shadow: 0 .45rem 1.3rem rgba(15,23,42,0.10);
        }
        .badge-soft {
            border-radius: 999px;
            padding: .2rem .6rem;
            font-size: .78rem;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">
            <i class="bi bi-heart-pulse-fill text-primary me-1"></i>ClinicFlow Pasien
        </a>
        <div class="d-flex align-items-center">
            <span class="me-3 text-muted small">
                Anda login sebagai <strong>pasien</strong>
            </span>
            <a href="logout.php" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-box-arrow-right me-1"></i>Logout
            </a>
        </div>
    </div>
</nav>

<div class="col-md-4">
    <div class="card mb-3">
        <div class="card-body">
            <h6 class="card-title">Teleconsult</h6>
            <p class="card-text small">
                Masuk ke ruang konsultasi online dengan dokter.
            </p>
            <a href="teleconsult_room.php?id=1" class="btn btn-outline-primary btn-sm">
                Masuk Ruang Teleconsult
            </a>
        </div>
    </div>
</div>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <aside class="col-md-3 col-lg-2 p-3 sidebar">
            <h6 class="text-uppercase text-white-50 mb-3">Menu</h6>
            <nav class="nav nav-pills flex-column gap-1">
                <a href="dashboard_pasien.php" class="nav-link active">
                    <i class="bi bi-speedometer2 me-2"></i>Dashboard
                </a>
                <a href="booking_pasien.php" class="nav-link">
                    <i class="bi bi-calendar-plus me-2"></i>Booking Jadwal Dokter
                </a>
                <a href="antrian_hari_ini.php" class="nav-link">
                    <i class="bi bi-people-fill me-2"></i>Antrian Hari Ini
                </a>
            </nav>
        </aside>

        <!-- Konten -->
        <main class="col-md-9 col-lg-10 p-4">
            <div class="hero-patient mb-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h3 class="mb-1">
                            Halo, <?php echo htmlspecialchars($pasien['nama'] ?? 'Pasien'); ?>
                        </h3>
                        <p class="mb-2">
                            Kelola booking dan pantau antrian klinik dalam satu tampilan.
                        </p>
                        <div class="d-flex align-items-center gap-2">
                            <span class="hero-dot"></span>
                            <span class="pill-role">Role: Pasien • <?php echo date('d M Y'); ?></span>
                        </div>
                    </div>
                    <div class="text-end d-none d-md-block">
                        <small class="d-block mb-1 text-sky-100">Navigasi cepat</small>
                        <a href="booking_pasien.php" class="btn btn-light btn-sm mb-1">
                            <i class="bi bi-calendar-plus me-1"></i>Booking Baru
                        </a><br>
                        <a href="antrian_hari_ini.php" class="btn btn-outline-light btn-sm">
                            Lihat Antrian
                        </a>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6 col-xl-4">
                    <div class="card stat-card">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted text-uppercase small mb-1">Booking Aktif</div>
                                <div class="fs-3 fw-semibold mb-0">
                                    <?php echo $totalBooking; ?>
                                </div>
                                <small class="text-muted">Jadwal yang akan datang.</small>
                            </div>
                            <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                                <i class="bi bi-calendar-check"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-4">
                    <div class="card stat-card">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted text-uppercase small mb-1">Antrian Hari Ini</div>
                                <div class="fs-3 fw-semibold mb-0">
                                    <?php echo $totalAntrianHariIni; ?>
                                </div>
                                <small class="text-muted">Antrian yang terkait akun ini.</small>
                            </div>
                            <div class="stat-icon bg-success bg-opacity-10 text-success">
                                <i class="bi bi-people"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card appt-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Langkah Cepat</span>
                    <span class="badge-soft bg-light text-muted d-none d-sm-inline">
                        Semua data booking tersimpan aman di server.
                    </span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="border rounded-3 p-3 h-100 bg-light">
                                <h6 class="mb-2">
                                    <i class="bi bi-calendar2-week me-1 text-primary"></i>
                                    Buat Booking
                                </h6>
                                <p class="small text-muted mb-3">
                                    Pilih dokter dan jadwal yang tersedia, lalu konfirmasi booking secara online.
                                </p>
                                <a href="booking_pasien.php" class="btn btn-primary btn-sm">
                                    Booking Jadwal Dokter
                                </a>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded-3 p-3 h-100 bg-light">
                                <h6 class="mb-2">
                                    <i class="bi bi-people-fill me-1 text-success"></i>
                                    Lihat Antrian
                                </h6>
                                <p class="small text-muted mb-3">
                                    Cek nomor antrian dan jam praktek untuk kunjungan hari ini.
                                </p>
                                <a href="antrian_hari_ini.php" class="btn btn-success btn-sm">
                                    Lihat Antrian Hari Ini
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

</body>
</html>
<?php include 'footer.php'; ?>