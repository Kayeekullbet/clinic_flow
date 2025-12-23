<?php
// antrian_hari_ini.php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// ====== CEK JWT & ROLE (saat ini admin saja) ======
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

if ($role !== 'admin' && $role !== 'dokter' && $role !== 'pasien') {
    header('Location: index.php');
    exit;
}

// ====== LOGIKA ANTRIAN HARI INI (PDO) ======
$tanggal = isset($_GET['tanggal']) && $_GET['tanggal'] !== ''
    ? $_GET['tanggal']
    : date('Y-m-d');

$sql = "
    SELECT a.nomor_antrian,
           u.nama  AS nama_pasien,
           p.no_hp,
           du.nama AS nama_dokter,
           d.spesialis,
           j.tanggal,
           j.jam_mulai,
           j.jam_selesai,
           b.waktu_booking
    FROM antrian a
    JOIN booking b       ON a.booking_id = b.id
    JOIN pasien p        ON b.pasien_id = p.id
    JOIN users u         ON p.user_id   = u.id
    JOIN dokter d        ON b.dokter_id = d.id
    JOIN users du        ON d.user_id   = du.id
    JOIN jadwal_dokter j ON b.jadwal_id = j.id
    WHERE j.tanggal = :tanggal
    ORDER BY j.jam_mulai, a.nomor_antrian
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':tanggal' => $tanggal]);
$rows = $stmt->fetchAll();

include 'header.php';
?>

<style>
    body {
        background-color: #f4f6fb;
    }
    .queue-hero {
        margin-top: 1rem;
        margin-bottom: 1.25rem;
        padding: 1.3rem 1.5rem;
        border-radius: 1rem;
        background: linear-gradient(135deg, #2563eb, #0ea5e9);
        color: #eff6ff;
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: relative;
        overflow: hidden;
    }
    .queue-hero::after {
        content: "";
        position: absolute;
        right: -40px;
        top: -40px;
        width: 140px;
        height: 140px;
        background: rgba(255,255,255,0.18);
        border-radius: 999px;
        filter: blur(3px);
    }
    .queue-hero h3 {
        margin-bottom: .25rem;
    }
    .queue-hero small {
        opacity: .9;
    }
    .queue-hero-pill {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        padding: .25rem .7rem;
        border-radius: 999px;
        background: rgba(15,23,42,0.18);
        font-size: .78rem;
    }
    .queue-hero-dot {
        width: 8px;
        height: 8px;
        border-radius: 999px;
        background-color: #22c55e;
        position: relative;
    }
    .queue-hero-dot::after {
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

    .filter-card {
        background: #ffffff;
        border-radius: .9rem;
        padding: .8rem 1rem;
        box-shadow: 0 0.4rem 1.2rem rgba(15,23,42,0.06);
        margin-bottom: 1rem;
    }

    .summary-card {
        background: #ffffff;
        border-radius: .9rem;
        padding: .9rem 1rem;
        box-shadow: 0 0.4rem 1.2rem rgba(15,23,42,0.06);
        margin-bottom: 1rem;
    }
    .summary-label {
        font-size: .78rem;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #6b7280;
        margin-bottom: .2rem;
    }
    .summary-value {
        font-size: 1.3rem;
        font-weight: 600;
    }

    table.antrian-table {
        width: 100%;
        border-collapse: collapse;
        background-color: #fff;
        box-shadow: 0 0.6rem 1.7rem rgba(15,23,42,0.08);
        border-radius: .9rem;
        overflow: hidden;
    }
    table.antrian-table th,
    table.antrian-table td {
        padding: .65rem .9rem;
        border-bottom: 1px solid #e5e7eb;
        text-align: left;
        font-size: .9rem;
    }
    table.antrian-table th {
        background-color: #f3f4f6;
        font-weight: 600;
    }
    table.antrian-table tr:nth-child(even) td {
        background-color: #f9fafb;
    }
    table.antrian-table tr:hover td {
        background-color: #eff6ff;
    }
    .badge-dokter {
        background: #eef2ff;
        color: #3730a3;
        border-radius: 999px;
        padding: .18rem .6rem;
        font-size: .78rem;
        border: 1px solid #c7d2fe;
    }
    .badge-time {
        background: #ecfeff;
        color: #0369a1;
        border-radius: 999px;
        padding: .18rem .6rem;
        font-size: .78rem;
        border: 1px solid #bae6fd;
    }
</style>

<div class="queue-hero">
    <div>
        <h3 class="mb-0">Antrian Pasien</h3>
        <small>Monitoring antrian pasien di klinik untuk tanggal
            <strong><?php echo htmlspecialchars(date('d M Y', strtotime($tanggal))); ?></strong>
        </small>
        <div class="mt-2 queue-hero-pill">
            <span class="queue-hero-dot"></span>
            Sistem aktif • Admin
        </div>
    </div>
    <div class="text-end d-none d-md-block">
        <small class="d-block mb-1">Total Antrian</small>
        <span class="badge bg-light text-dark fs-6">
            <?php echo count($rows); ?>
        </span>
    </div>
</div>

<div class="row g-3 mb-2">
    <div class="col-md-7">
        <div class="filter-card">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-auto">
                    <label class="col-form-label small text-muted mb-0">Pilih Tanggal</label>
                </div>
                <div class="col-auto">
                    <input type="date" name="tanggal"
                           value="<?php echo htmlspecialchars($tanggal); ?>"
                           class="form-control form-control-sm" required>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm">
                        Tampilkan
                    </button>
                </div>
                <div class="col-auto">
                    <a href="antrian_hari_ini.php" class="btn btn-outline-secondary btn-sm">
                        Hari Ini
                    </a>
                </div>
            </form>
        </div>
    </div>
    <div class="col-md-5">
        <div class="summary-card d-flex justify-content-between align-items-center">
            <?php
            $totalAntrian = count($rows);
            $firstTime = $totalAntrian > 0
                ? substr($rows[0]['jam_mulai'], 0, 5)
                : '-';
            ?>
            <div>
                <div class="summary-label">Total Antrian</div>
                <div class="summary-value"><?php echo $totalAntrian; ?></div>
            </div>
            <div>
                <div class="summary-label">Jam Mulai Pertama</div>
                <div class="summary-value text-primary"><?php echo $firstTime; ?></div>
            </div>
            <div class="d-none d-sm-block">
                <a href="dashboard_admin.php" class="btn btn-outline-secondary btn-sm">
                    Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<div class="table-responsive">
    <table class="antrian-table">
        <tr>
            <th>No</th>
            <th>No Antrian</th>
            <th>Pasien</th>
            <th>No HP</th>
            <th>Dokter</th>
            <th>Waktu Booking</th>
        </tr>
        <?php if (count($rows) === 0): ?>
        <tr>
            <td colspan="6">Belum ada antrian pada tanggal ini.</td>
        </tr>
        <?php else: ?>
            <?php $no = 1; ?>
            <?php foreach ($rows as $row): ?>
            <tr>
                <td><?php echo $no++; ?></td>
                <td><?php echo htmlspecialchars($row['nomor_antrian']); ?></td>
                <td><?php echo htmlspecialchars($row['nama_pasien']); ?></td>
                <td><?php echo htmlspecialchars($row['no_hp']); ?></td>
                <td>
                    <span class="badge-dokter">
                        <?php echo htmlspecialchars($row['nama_dokter']); ?>
                        (<?php echo htmlspecialchars($row['spesialis']); ?>)
                    </span>
                </td>
                <td>
                    <span class="badge-time">
                        <?php echo htmlspecialchars($row['waktu_booking']); ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>
</div>

<div class="mt-3 d-sm-none">
    <a href="dashboard_admin.php" class="btn btn-outline-secondary btn-sm w-100">
        Kembali ke Dashboard
    </a>
</div>

<?php include 'footer.php'; ?>