<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$role = null;

if (!empty($_COOKIE['token'])) {
    try {
        $decoded = JWT::decode($_COOKIE['token'], new Key($JWT_SECRET, 'HS256'));
        $data    = (array) $decoded->data;
        $role    = $data['role'] ?? null;
    } catch (Exception $e) {
        $role = null;
    }
}

if ($role !== 'admin') {
    header('Location: index.php');
    exit;
}

include 'header.php';
?>

<style>
    .dashboard-hero {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        border-radius: 1rem;
        padding: 1.75rem 1.75rem 1.5rem;
        color: #fff;
        position: relative;
        overflow: hidden;
    }
    .dashboard-hero::after {
        content: "";
        position: absolute;
        right: -40px;
        top: -40px;
        width: 140px;
        height: 140px;
        background: rgba(255,255,255,0.15);
        border-radius: 999px;
        filter: blur(2px);
    }
    .dashboard-card {
        border: none;
        border-radius: 1rem;
        background: #ffffffcc;
        backdrop-filter: blur(6px);
        box-shadow: 0 0.5rem 1.2rem rgba(15,23,42,0.08);
        transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        cursor: pointer;
    }
    .dashboard-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 0.9rem 1.8rem rgba(15,23,42,0.16);
        border-color: #2563eb;
    }
    .dashboard-icon {
        width: 40px;
        height: 40px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
    }
    .badge-soft {
        background: rgba(15,23,42,0.07);
        color: #111827;
        border-radius: 999px;
        padding: .25rem .75rem;
        font-size: .75rem;
    }
</style>

<div class="mt-4 mb-3">
    <div class="dashboard-hero mb-4">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h2 class="mb-1">Dashboard Admin</h2>
                <p class="mb-2">Kelola data klinik, jadwal dokter, dan antrian pasien dalam satu tempat.</p>
                <span class="badge-soft">Role: Admin • <?php echo date('d M Y'); ?></span>
            </div>
            <div class="text-end d-none d-md-block">
                <small class="d-block mb-1">Status Sistem</small>
                <span class="badge bg-success">Online</span>
            </div>
        </div>
    </div>

    <div class="row g-3" id="adminCards">
        <div class="col-md-4">
            <a href="kelola_dokter.php" class="text-decoration-none text-reset">
                <div class="card dashboard-card h-100" data-label="Dokter & Jadwal">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="dashboard-icon bg-primary bg-opacity-10 text-primary me-2">
                                <i class="bi bi-person-badge"></i>
                            </div>
                            <h5 class="mb-0">Kelola Dokter</h5>
                        </div>
                        <p class="text-muted mb-3">
                            Tambah dan atur jadwal praktik dokter per poli.
                        </p>
                        <span class="badge bg-primary bg-opacity-10 text-primary">
                            Manajemen data
                        </span>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-4">
            <a href="antrian_hari_ini.php" class="text-decoration-none text-reset">
                <div class="card dashboard-card h-100" data-label="Antrian Hari Ini">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="dashboard-icon bg-warning bg-opacity-10 text-warning me-2">
                                <i class="bi bi-people"></i>
                            </div>
                            <h5 class="mb-0">Antrian Hari Ini</h5>
                        </div>
                        <p class="text-muted mb-3">
                            Pantau nomor antrian yang sedang berjalan.
                        </p>
                        <span class="badge bg-warning bg-opacity-10 text-warning">
                            Monitoring
                        </span>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-4">
            <a href="logout.php" class="text-decoration-none text-reset">
                <div class="card dashboard-card h-100" data-label="Logout">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="dashboard-icon bg-danger bg-opacity-10 text-danger me-2">
                                <i class="bi bi-box-arrow-right"></i>
                            </div>
                            <h5 class="mb-0">Keluar Sistem</h5>
                        </div>
                        <p class="text-muted mb-3">
                            Akhiri sesi dan kembali ke halaman login.
                        </p>
                        <span class="badge bg-danger bg-opacity-10 text-danger">
                            Keamanan
                        </span>
                    </div>
                </div>
            </a>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.dashboard-card').forEach(card => {
    card.addEventListener('mouseenter', () => {
        const label = card.getAttribute('data-label');
        console.log('Fokus: ' + label);
    });
});
</script>

<?php include 'footer.php'; ?>