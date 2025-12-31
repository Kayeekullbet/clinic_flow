
<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// kunci rahasia JWT (pastikan sama dengan di config.php)
$JWT_SECRET = $JWT_SECRET ?? 'ganti_dengan_kunci_rahasia_yang_panjang';

// cek token JWT dari cookie (kalau sudah login)
$role = null;

if (!empty($_COOKIE['token'])) {
    try {
        $decoded = JWT::decode($_COOKIE['token'], new Key($JWT_SECRET, 'HS256'));
        $data    = (array) $decoded->data;
        $role    = $data['role'] ?? null;
    } catch (Exception $e) {
        // token invalid / expired → anggap belum login
        $role = null;
    }
}

// kalau sudah login, arahkan ke dashboard sesuai role
if ($role === 'admin') {
    header('Location: dashboard_admin.php');
    exit;
} elseif ($role === 'dokter') {
    header('Location: dashboard_dokter.php');
    exit;
} elseif ($role === 'pasien') {
    header('Location: dashboard_pasien.php');
    exit;
}

// tampung pesan error dari proses_login.php (pakai query string)
$error = '';
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'empty') {
        $error = 'Email dan password wajib diisi.';
    } elseif ($_GET['error'] === 'invalid') {
        $error = 'Email atau password salah.';
    }
}
?>

<?php include 'header.php'; ?>

<style>
    body {
        min-height: 100vh;
        background: radial-gradient(circle at top left, #0d6efd 0, #20c997 35%, #f8f9fa 80%);
    }

    .login-wrapper {
        min-height: calc(100vh - 80px);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .login-card {
        border: none;
        border-radius: 1.2rem;
        overflow: hidden;
        background: rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(18px);
        box-shadow: 0 20px 45px rgba(15, 23, 42, 0.35);
    }

    .login-illustration {
        background: linear-gradient(145deg, rgba(13,110,253,0.95), rgba(32,201,151,0.95));
        color: #fff;
    }

    .login-illustration h3 {
        font-weight: 600;
    }

    .badge-pill-soft {
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.14);
        padding: 0.25rem 0.75rem;
        font-size: 0.75rem;
    }

    .pulse-dot {
        width: 10px;
        height: 10px;
        border-radius: 999px;
        background: #0df3a5;
        box-shadow: 0 0 0 0 rgba(13, 243, 165, 0.5);
        animation: pulse 1.5s infinite;
    }

    @keyframes pulse {
        0%   { box-shadow: 0 0 0 0 rgba(13, 243, 165, 0.5); }
        70%  { box-shadow: 0 0 0 10px rgba(13, 243, 165, 0); }
        100% { box-shadow: 0 0 0 0 rgba(13, 243, 165, 0); }
    }

    .login-form-card {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(25px);
    }
</style>

<div class="login-wrapper">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <div class="card login-card">
                    <div class="row g-0">
                        <!-- Kolom kiri: ilustrasi / info -->
                        <div class="col-md-5 d-none d-md-flex login-illustration flex-column justify-content-between p-4">
                            <div>
                                <div class="d-flex align-items-center mb-3">
                                    <div class="pulse-dot me-2"></div>
                                    <span class="fw-semibold">ClinicFlow</span>
                                </div>
                                <h3 class="mb-2">Portal Klinik Modern</h3>
                                <p class="mb-3">
                                    Kelola jadwal dokter, booking pasien, dan antrian secara real-time
                                    dalam satu sistem terintegrasi.
                                </p>

                                <div class="d-flex flex-wrap gap-2 mt-3">
                                    <span class="badge-pill-soft">
                                        Jadwal Dokter Terupdate
                                    </span>
                                    <span class="badge-pill-soft">
                                        Booking Pasien Online
                                    </span>
                                    <span class="badge-pill-soft">
                                        Manajemen Antrian
                                    </span>
                                </div>
                            </div>

                            <div class="small opacity-75">
                                “Healthcare that flows” – pastikan data klinik selalu aman dan terkelola dengan baik.
                            </div>
                        </div>

                        <!-- Kolom kanan: form login -->
                        <div class="col-md-7">
                            <div class="login-form-card h-100 p-4 p-md-5">
                                <h4 class="mb-3 text-center">Login ClinicFlow</h4>
                                <p class="text-muted text-center mb-4">
                                    Masuk untuk mengelola layanan klinik Anda.
                                </p>

                                <?php if (!empty($error)) : ?>
                                    <div class="alert alert-danger py-2">
                                        <?php echo htmlspecialchars($error); ?>
                                    </div>
                                <?php endif; ?>

                                <form method="post" action="proses_login.php">
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input
                                            type="text"
                                            name="username"
                                            class="form-control"
                                            required
                                            autofocus
                                            placeholder="contoh@clinicflow.com"
                                        >
                                    </div>

                                    <div class="mb-2">
                                        <label class="form-label">Password</label>
                                        <div class="input-group">
                                            <input
                                                type="password"
                                                name="password"
                                                id="password"
                                                class="form-control"
                                                required
                                            >
                                            <button
                                                class="btn btn-outline-secondary"
                                                type="button"
                                                id="togglePassword"
                                            >
                                                👁
                                            </button>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mb-3 small">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="rememberMe">
                                            <label class="form-check-label" for="rememberMe">
                                                Ingat saya
                                            </label>
                                        </div>
                                        <!-- Link ke halaman lupa password -->
                                        <a href="forgot_password.php" class="text-decoration-none">
                                            Lupa password?
                                        </a>
                                    </div>

                                    <button type="submit" class="btn btn-primary w-100 mb-2">
                                        Login
                                    </button>

                                    <div class="text-center mt-2">
                                        <small class="text-muted">Belum punya akun?</small><br>
                                        <a href="register_pasien.php">Daftar sebagai Pasien</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div> <!-- row -->
                </div> <!-- card -->
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('togglePassword').addEventListener('click', function () {
    const pwdInput = document.getElementById('password');
    const type = pwdInput.getAttribute('type') === 'password' ? 'text' : 'password';
    pwdInput.setAttribute('type', type);

    // optional: ganti icon
    this.textContent = type === 'password' ? '👁' : '🙈';
});
</script>

<?php include 'footer.php'; ?>