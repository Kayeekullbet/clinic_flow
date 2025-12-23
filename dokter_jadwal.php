<?php
// jadwal_dokter_saya.php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// ========== CEK JWT & ROLE DOKTER ==========
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

if ($role !== 'dokter' || !$user_id) {
    header('Location: index.php');
    exit;
}

// ========== AMBIL DATA DOKTER & JADWAL (PDO) ==========

// data dokter berdasar user_id di token
$dokter_stmt = $pdo->prepare("SELECT * FROM dokter WHERE user_id = :uid LIMIT 1");
$dokter_stmt->execute([':uid' => $user_id]);
$dokter = $dokter_stmt->fetch();

$jadwal_rows = [];
if ($dokter) {
    $dokter_id = (int)$dokter['id'];

    $sql = "
        SELECT *
        FROM jadwal_dokter
        WHERE dokter_id = :dokter_id
          AND (
                tanggal > CURDATE()
                OR (tanggal = CURDATE() AND jam_selesai > CURTIME())
              )
        ORDER BY tanggal, jam_mulai
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':dokter_id' => $dokter_id]);
    $jadwal_rows = $stmt->fetchAll();
}

include 'header.php';
?>

<style>
    .page-hero {
        margin-top: 1rem;
        margin-bottom: 1.25rem;
        padding: 1.5rem 1.75rem;
        border-radius: 1rem;
        background: radial-gradient(circle at top left, #22c55e, #16a34a, #14532d);
        color: #ecfdf5;
        position: relative;
        overflow: hidden;
    }
    .page-hero::after {
        content: "";
        position: absolute;
        right: -40px;
        top: -40px;
        width: 140px;
        height: 140px;
        border-radius: 999px;
        background: rgba(255,255,255,0.18);
        filter: blur(3px);
    }
    .pill-badge {
        display: inline-flex;
        align-items: center;
        gap: .25rem;
        padding: .25rem .7rem;
        border-radius: 999px;
        background: rgba(15,23,42,0.15);
        font-size: .75rem;
    }
    .schedule-card {
        border-radius: 1rem;
        border: none;
        box-shadow: 0 .5rem 1.5rem rgba(15,23,42,0.10);
        overflow: hidden;
    }
    .schedule-list {
        max-height: 420px;
        overflow-y: auto;
        padding-right: .25rem;
    }
    .schedule-item {
        border-left: 4px solid #16a34a;
        border-radius: .75rem;
        padding: .75rem .9rem;
        margin-bottom: .65rem;
        background: linear-gradient(135deg, #f9fafb, #f0fdf4);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: .75rem;
        transition: transform .15s ease, box-shadow .15s ease;
    }
    .schedule-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 .4rem 1rem rgba(15,23,42,0.15);
    }
    .schedule-date {
        font-weight: 600;
        font-size: .9rem;
    }
    .schedule-time {
        font-size: .85rem;
        color: #4b5563;
    }
    .schedule-quota {
        font-size: .8rem;
        padding: .25rem .6rem;
        border-radius: 999px;
        background: #ecfdf5;
        color: #166534;
        border: 1px solid #bbf7d0;
    }
    .empty-state {
        padding: 2.5rem 1rem;
        text-align: center;
        color: #6b7280;
    }
    .pulse-dot {
        width: 10px;
        height: 10px;
        border-radius: 999px;
        background-color: #22c55e;
        position: relative;
    }
    .pulse-dot::after {
        content: "";
        position: absolute;
        inset: -4px;
        border-radius: inherit;
        border: 2px solid rgba(34,197,94,0.6);
        animation: pulse 1.3s ease-out infinite;
    }
    @keyframes pulse {
        0% { transform: scale(0.8); opacity: 1; }
        100% { transform: scale(1.3); opacity: 0; }
    }
</style>

<div class="page-hero">
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <h3 class="mb-1">Jadwal Praktik Saya</h3>
            <?php if ($dokter): ?>
                <p class="mb-2">
                    <strong><?php echo htmlspecialchars($dokter['nama']); ?></strong>
                    • <?php echo htmlspecialchars($dokter['spesialis']); ?>
                </p>
            <?php else: ?>
                <p class="mb-2">Akun dokter belum dihubungkan ke data dokter.</p>
            <?php endif; ?>
            <div class="d-flex gap-2 align-items-center">
                <span class="pill-badge">
                    <span class="pulse-dot"></span>
                    Online • <?php echo date('d M Y'); ?>
                </span>
                <span class="pill-badge">
                    <?php echo count($jadwal_rows); ?> jadwal aktif
                </span>
            </div>
        </div>
        <div class="text-end d-none d-md-block">
            <small class="d-block mb-1 text-emerald-100">Mode Dokter</small>
            <a href="dashboard_dokter.php" class="btn btn-sm btn-light">
                Kembali ke Dashboard
            </a>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-7">
        <div class="card schedule-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="mb-0">Daftar Jadwal Mendatang</h5>
                        <small class="text-muted">Jadwal setelah waktu sekarang akan tampil di sini.</small>
                    </div>
                </div>

                <?php if (!$dokter): ?>
                    <div class="empty-state">
                        <p class="mb-1">Data dokter belum dikaitkan dengan akun ini.</p>
                        <small class="text-muted">Hubungi admin untuk menghubungkan akun ke data dokter.</small>
                    </div>
                <?php elseif (count($jadwal_rows) === 0): ?>
                    <div class="empty-state">
                        <p class="mb-1">Belum ada jadwal praktik yang akan datang.</p>
                        <small class="text-muted">Silakan buat atau minta admin menambahkan jadwal baru.</small>
                    </div>
                <?php else: ?>
                    <div class="schedule-list">
                        <?php foreach ($jadwal_rows as $j): ?>
                            <div class="schedule-item">
                                <div>
                                    <div class="schedule-date">
                                        <?php echo date('d M Y', strtotime($j['tanggal'])); ?>
                                    </div>
                                    <div class="schedule-time">
                                        <?php
                                            echo substr($j['jam_mulai'],0,5) .
                                                ' - ' .
                                                substr($j['jam_selesai'],0,5);
                                        ?>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <span class="schedule-quota">
                                        Kuota: <?php echo (int)$j['kuota']; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-5">
        <div class="card schedule-card">
            <div class="card-body">
                <h5 class="mb-3">Ringkasan Hari Ini</h5>
                <?php
                $todayCount = 0;
                foreach ($jadwal_rows as $j) {
                    if ($j['tanggal'] === date('Y-m-d')) {
                        $todayCount++;
                    }
                }
                ?>
                <p class="mb-1">
                    Jadwal hari ini:
                    <strong><?php echo $todayCount; ?></strong>
                </p>
                <p class="text-muted mb-3">
                    Jadwal yang lewat otomatis disembunyikan dari daftar aktif.
                </p>

                <hr>

                <h6 class="mb-2">Tips</h6>
                <ul class="small text-muted mb-0">
                    <li>Pastikan jam mulai dan selesai tidak saling bertumpukan.</li>
                    <li>Kuota sebaiknya menyesuaikan durasi konsultasi per pasien.</li>
                    <li>Hubungi admin jika ingin menambah atau mengubah jadwal.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>