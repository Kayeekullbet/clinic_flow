<?php
// booking_pasien.php  (KHUSUS PASIEN)

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// ========= CEK JWT & ROLE =========
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

if ($role !== 'admin' && $role !== 'dokter' && $role !== 'pasien') {
    header('Location: index.php');
    exit;
}

// mode teleconsult atau onsite biasa
$isTeleconsult = isset($_GET['mode']) && $_GET['mode'] === 'teleconsult';

// ========= AMBIL DATA PASIEN =========
$stmtPasien = $pdo->prepare("
    SELECT p.id AS pasien_id, u.nama
    FROM pasien p
    JOIN users u ON p.user_id = u.id
    WHERE p.user_id = :uid
    LIMIT 1
");
$stmtPasien->execute([':uid' => $user_id]);
$pasien = $stmtPasien->fetch();

if (!$pasien) {
    die('Akun belum terhubung ke data pasien. Hubungi admin.');
}
$pasien_id = (int)$pasien['pasien_id'];

// ========= FUNGSI AMBIL JADWAL =========
function getJadwalList(PDO $pdo) {
    $sql = "
        SELECT j.id,
               j.tanggal,
               j.jam_mulai,
               j.jam_selesai,
               d.id       AS dokter_id,
               du.nama    AS nama_dokter,
               d.spesialis,
               j.kuota,
               (
                 SELECT COUNT(*)
                 FROM booking b
                 WHERE b.jadwal_id = j.id
               ) AS jumlah_booking
        FROM jadwal_dokter j
        JOIN dokter d  ON j.dokter_id = d.id
        JOIN users du  ON d.user_id   = du.id
        WHERE j.tanggal >= CURDATE()
        ORDER BY j.tanggal, j.jam_mulai
    ";
    return $pdo->query($sql)->fetchAll();
}

$jadwal_list = getJadwalList($pdo);

// ========= PROSES SUBMIT BOOKING =========
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jadwal_id = (int)($_POST['jadwal_id'] ?? 0);

    if ($jadwal_id === 0) {
        $error = "Jadwal dokter wajib dipilih.";
    } else {
        // cek kuota jadwal
        $cek_stmt = $pdo->prepare("
            SELECT j.kuota,
                   (
                     SELECT COUNT(*)
                     FROM booking b
                     WHERE b.jadwal_id = j.id
                   ) AS terisi,
                   j.dokter_id,
                   j.tanggal,
                   j.jam_mulai
            FROM jadwal_dokter j
            WHERE j.id = :id
        ");
        $cek_stmt->execute([':id' => $jadwal_id]);
        $jadwal = $cek_stmt->fetch();

        if (!$jadwal) {
            $error = "Jadwal tidak ditemukan.";
        } elseif ($jadwal['terisi'] >= $jadwal['kuota']) {
            $error = "Kuota jadwal ini sudah penuh.";
        } else {
            try {
                $pdo->beginTransaction();

                // insert ke booking (onsite / teleconsult tetap dicatat di sini)
                $now = date('Y-m-d H:i:s');
                $ins_booking = $pdo->prepare("
                    INSERT INTO booking (pasien_id, dokter_id, jadwal_id, waktu_booking)
                    VALUES (:pasien_id, :dokter_id, :jadwal_id, :waktu_booking)
                ");
                $ins_booking->execute([
                    ':pasien_id'     => $pasien_id,
                    ':dokter_id'     => $jadwal['dokter_id'],
                    ':jadwal_id'     => $jadwal_id,
                    ':waktu_booking' => $now,
                ]);
                $booking_id = (int)$pdo->lastInsertId();

                // nomor antrian (per jadwal) — untuk kunjungan onsite
                $cek_antrian = $pdo->prepare("
                    SELECT COUNT(*) AS total
                    FROM antrian a
                    JOIN booking b ON a.booking_id = b.id
                    WHERE b.jadwal_id = :jadwal_id
                ");
                $cek_antrian->execute([':jadwal_id' => $jadwal_id]);
                $ra = $cek_antrian->fetch();
                $no_antrian = ((int)$ra['total']) + 1;

                $ins_antrian = $pdo->prepare("
                    INSERT INTO antrian (booking_id, nomor_antrian, status)
                    VALUES (:booking_id, :nomor_antrian, 'menunggu')
                ");
                $ins_antrian->execute([
                    ':booking_id'    => $booking_id,
                    ':nomor_antrian' => $no_antrian,
                ]);

                // JIKA MODE TELECONSULT → BUAT RECORD TELECONSULT + REDIRECT KE RUANG CHAT
                if ($isTeleconsult) {
                    $tanggal = $jadwal['tanggal'];
                    $jam     = substr($jadwal['jam_mulai'], 0, 5); // HH:MM
                    $jadwal_waktu = $tanggal . ' ' . $jam . ':00';

                    $room_code = 'TC-' . bin2hex(random_bytes(4));

                    $ins_tc = $pdo->prepare("
                        INSERT INTO teleconsult_appointments (pasien_id, dokter_id, jadwal_waktu, status, room_code)
                        VALUES (:pasien_id, :dokter_id, :jadwal_waktu, 'confirmed', :room_code)
                    ");
                    $ins_tc->execute([
                        ':pasien_id'    => $pasien_id,
                        ':dokter_id'    => $jadwal['dokter_id'],
                        ':jadwal_waktu' => $jadwal_waktu,
                        ':room_code'    => $room_code,
                    ]);

                    $tc_id = (int)$pdo->lastInsertId();

                    $pdo->commit();

                    header('Location: teleconsult_room.php?id=' . $tc_id);
                    exit;
                }

                // kalau bukan teleconsult → booking biasa
                $pdo->commit();

                $success = "Booking berhasil. Nomor antrian Anda: " . $no_antrian;
                $jadwal_list = getJadwalList($pdo);
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Terjadi kesalahan saat menyimpan booking.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title><?php echo $isTeleconsult ? 'Booking Teleconsult' : 'Booking Jadwal Dokter'; ?> - ClinicFlow</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        body { background:#f3f4f8; }
        .hero-booking {
            margin-top:1rem; margin-bottom:1.25rem;
            padding:1.35rem 1.5rem;
            border-radius:1rem;
            background: radial-gradient(circle at top left,#22c55e,#16a34a,#15803d);
            color:#ecfdf5;
            position:relative; overflow:hidden;
        }
        .hero-booking::after{
            content:""; position:absolute; right:-40px; top:-40px;
            width:140px; height:140px;
            border-radius:999px; background:rgba(255,255,255,.18);
            filter:blur(3px);
        }
        .hero-pill{
            display:inline-flex; align-items:center; gap:.35rem;
            padding:.2rem .7rem; border-radius:999px;
            background:rgba(15,23,42,.18); font-size:.78rem;
        }
        .hero-dot{
            width:9px; height:9px; border-radius:999px; background:#bbf7d0;
            position:relative;
        }
        .hero-dot::after{
            content:""; position:absolute; inset:-4px; border-radius:inherit;
            border:2px solid rgba(187,247,208,0.7);
            animation:pulse 1.4s ease-out infinite;
        }
        @keyframes pulse{
            0%{transform:scale(.8);opacity:1;}
            100%{transform:scale(1.3);opacity:0;}
        }
        .card-soft{
            border-radius:1rem; border:none;
            box-shadow:0 .5rem 1.5rem rgba(15,23,42,.10);
        }
        .alert-soft-success{background:#ecfdf3;border:1px solid #bbf7d0;color:#166534;}
        .alert-soft-danger{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;}
        table.jadwal-table{width:100%;border-collapse:collapse;}
        table.jadwal-table th,table.jadwal-table td{
            padding:.55rem .75rem;border-bottom:1px solid #e5e7eb;font-size:.88rem;
        }
        table.jadwal-table th{background:#f3f4f6;font-weight:600;}
        table.jadwal-table tr:nth-child(even) td{background:#f9fafb;}
        table.jadwal-table tr:hover td{background:#eff6ff;}
        .badge-kuota{
            border-radius:999px;padding:.15rem .55rem;font-size:.75rem;
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="hero-booking">
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <h3 class="mb-1">
                <?php echo $isTeleconsult ? 'Booking Teleconsult' : 'Booking Jadwal Dokter'; ?>
            </h3>
            <p class="mb-2">
                Hai, <?php echo htmlspecialchars($pasien['nama']); ?>.
                Pilih jadwal yang tersedia untuk membuat booking baru.
            </p>
            <div class="hero-pill">
                <span class="hero-dot"></span>
                Mode: <?php echo $isTeleconsult ? 'Teleconsult' : 'Kunjungan Klinik'; ?> • <?php echo date('d M Y'); ?>
            </div>
        </div>
        <div class="text-end d-none d-md-block">
            <small class="d-block mb-1 text-emerald-100">Navigasi cepat</small>
            <a href="antrian_hari_ini.php" class="btn btn-light btn-sm mb-1">
                <i class="bi bi-people me-1"></i>Lihat Antrian
            </a><br>
            <a href="dashboard_pasien.php" class="btn btn-outline-light btn-sm">
                Dashboard
            </a>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card card-soft">
            <div class="card-body">
                <h5 class="mb-3">Form Booking</h5>

                <?php if ($success): ?>
                    <div class="alert alert-soft-success py-2 mb-3">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-soft-danger py-2 mb-3">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <div class="mb-3">
                        <label class="form-label small text-muted">Jadwal Dokter</label>
                        <select name="jadwal_id" class="form-select form-select-sm" required>
                            <option value="">-- Pilih Jadwal --</option>
                            <?php foreach ($jadwal_list as $j): ?>
                                <?php
                                    $sisa  = (int)$j['kuota'] - (int)$j['jumlah_booking'];
                                    $label = date('d M Y', strtotime($j['tanggal'])) . ' | ' .
                                             substr($j['jam_mulai'],0,5) . '-' . substr($j['jam_selesai'],0,5) .
                                             ' | ' . $j['nama_dokter'] . ' (' . $j['spesialis'] . ')' .
                                             ' | sisa: ' . $sisa;
                                ?>
                                <option
                                    value="<?php echo (int)$j['id']; ?>"
                                    <?php echo $sisa <= 0 ? 'disabled' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted d-block mt-1">
                            Jadwal dengan kuota habis akan dinonaktifkan.
                        </small>
                    </div>

                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-check-circle me-1"></i>
                        <?php echo $isTeleconsult ? 'Booking Teleconsult' : 'Simpan Booking'; ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card card-soft">
            <div class="card-body">
                <h5 class="mb-3">Jadwal Dokter Tersedia</h5>
                <div class="table-responsive">
                    <table class="jadwal-table">
                        <tr>
                            <th>Tanggal</th>
                            <th>Jam</th>
                            <th>Dokter</th>
                            <th>Kuota</th>
                            <th>Terisi</th>
                            <th>Sisa</th>
                        </tr>
                        <?php if (count($jadwal_list) === 0): ?>
                            <tr><td colspan="6">Belum ada jadwal dokter.</td></tr>
                        <?php else: ?>
                            <?php foreach ($jadwal_list as $j): ?>
                                <?php
                                    $sisa  = (int)$j['kuota'] - (int)$j['jumlah_booking'];
                                    $kelas = $sisa <= 0 ? 'text-danger' : ($sisa <= 2 ? 'text-warning' : 'text-success');
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($j['tanggal']); ?></td>
                                    <td><?php echo substr($j['jam_mulai'],0,5) . ' - ' . substr($j['jam_selesai'],0,5); ?></td>
                                    <td><?php echo htmlspecialchars($j['nama_dokter']) . ' (' . htmlspecialchars($j['spesialis']) . ')'; ?></td>
                                    <td><?php echo (int)$j['kuota']; ?></td>
                                    <td><?php echo (int)$j['jumlah_booking']; ?></td>
                                    <td class="<?php echo $kelas; ?>">
                                        <span class="badge-kuota bg-light">
                                            <?php echo (int)$sisa; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
</body>
</html>