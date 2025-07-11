<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_once __DIR__ . '/../config/db.php';

// --- Format tanggal Indonesia (misal: Kamis, 10 Jul) ---
if (!function_exists('tgl_indo_edlink')) {
  function tgl_indo_edlink($date)
  {
    $hari = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
    $bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $d = date('w', strtotime($date));
    $day = $hari[$d];
    $tgl = date('d', strtotime($date));
    $bln = $bulan[(int)date('m', strtotime($date)) - 1];
    return "$day, $tgl $bln";
  }
}

$user_id = get_user_id();
// Ambil data user beserta avatar, fallback jika kolom avatar belum ada
$user = $conn->query("SELECT * FROM users WHERE id = $user_id");
if ($user && $user->num_rows > 0) {
  $user = $user->fetch_assoc();
} else {
  $user = ['username' => 'User', 'avatar' => null];
}
$tugas = $conn->query("SELECT * FROM tugas WHERE user_id = $user_id ORDER BY deadline ASC");
$tugas_all = $conn->query("SELECT * FROM tugas WHERE user_id = $user_id ORDER BY deadline ASC");
$acara = $conn->query("SELECT * FROM acara WHERE user_id = $user_id ORDER BY tanggal ASC");
$acara_all = $conn->query("SELECT * FROM acara WHERE user_id = $user_id ORDER BY tanggal ASC");
$total = $conn->query("SELECT COUNT(*) as jml FROM tugas WHERE user_id = $user_id")->fetch_assoc()['jml'];
$done = $conn->query("SELECT COUNT(*) as jml FROM tugas WHERE user_id = $user_id AND status = 'selesai'")->fetch_assoc()['jml'];
$acara_count = $conn->query("SELECT COUNT(*) as jml FROM acara WHERE user_id = $user_id")->fetch_assoc()['jml'];
$progress = $total ? round($done / $total * 100) : 0;
$err_tugas = $err_acara = '';
// Proses tambah tugas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_tugas'])) {
  $nama = trim($_POST['nama_tugas'] ?? '');
  $desk = trim($_POST['deskripsi_tugas'] ?? '');
  $deadline = $_POST['deadline_tugas'] ?? '';
  if ($nama === '' || $deadline === '') {
    $err_tugas = 'Nama tugas & deadline wajib diisi!';
  } else {
    $stmt = $conn->prepare('INSERT INTO tugas (user_id, nama_tugas, deskripsi, deadline, status, created_at) VALUES (?, ?, ?, ?, "belum", NOW())');
    $stmt->bind_param('isss', $user_id, $nama, $desk, $deadline);
    $stmt->execute();
    header('Location: dashboard.php');
    exit;
  }
}
// Proses tambah acara
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_acara'])) {
  $nama = trim($_POST['nama_acara'] ?? '');
  $desk = trim($_POST['deskripsi_acara'] ?? '');
  $tanggal = $_POST['tanggal_acara'] ?? '';
  if ($nama === '' || $tanggal === '') {
    $err_acara = 'Nama acara & tanggal wajib diisi!';
  } else {
    $stmt = $conn->prepare('INSERT INTO acara (user_id, nama_acara, deskripsi, tanggal, created_at) VALUES (?, ?, ?, ?, NOW())');
    $stmt->bind_param('isss', $user_id, $nama, $desk, $tanggal);
    $stmt->execute();
    header('Location: dashboard.php');
    exit;
  }
}
// Tambahan: Proses upload avatar
$err_avatar = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
  $file = $_FILES['avatar'];
  if ($file['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    if (in_array($ext, $allowed)) {
      $newName = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
      $uploadPath = __DIR__ . '/../uploads/' . $newName;
      if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        // Pastikan kolom avatar sudah ada di database (opsional, sebaiknya dilakukan sekali saja)
        $check = $conn->query("SHOW COLUMNS FROM users LIKE 'avatar'");
        if ($check && $check->num_rows == 0) {
          if (!$conn->query("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL")) {
            $err_avatar = 'Gagal menambah kolom avatar: ' . $conn->error;
          }
        }
        // Update avatar pakai prepared statement
        $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
        if ($stmt) {
          $stmt->bind_param('si', $newName, $user_id);
          if (!$stmt->execute()) {
            $err_avatar = 'Gagal update avatar: ' . $stmt->error;
          } else {
            // Ambil ulang data user dari database agar $user['avatar'] terupdate
            $res = $conn->query("SELECT * FROM users WHERE id = $user_id");
            if ($res && $res->num_rows > 0) {
              $user = $res->fetch_assoc();
              header('Location: dashboard.php');
              exit;
            } else {
              $err_avatar = 'Gagal mengambil data user setelah update avatar.';
            }
          }
        } else {
          $err_avatar = 'Gagal prepare statement update avatar: ' . $conn->error;
        }
      } else {
        $err_avatar = 'Gagal upload file. Pastikan folder uploads/ writeable.';
      }
    } else {
      $err_avatar = 'Format file tidak didukung. Hanya jpg, jpeg, png, gif.';
    }
  } else {
    $err_avatar = 'Upload error: ' . $file['error'];
  }
}
// Tambahan: Proses hapus avatar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_avatar'])) {
  if (!empty($user['avatar'])) {
    $avatarFile = __DIR__ . '/../uploads/' . $user['avatar'];
    if (file_exists($avatarFile)) {
      @unlink($avatarFile);
    }
    $stmt = $conn->prepare("UPDATE users SET avatar = NULL WHERE id = ?");
    if ($stmt) {
      $stmt->bind_param('i', $user_id);
      $stmt->execute();
    }
    // Refresh data user
    $res = $conn->query("SELECT * FROM users WHERE id = $user_id");
    if ($res && $res->num_rows > 0) {
      $user = $res->fetch_assoc();
    }
  }
  header('Location: dashboard.php');
  exit;
}
// Motivasi harian
$motivasi = [
  'Jangan tunda pekerjaan, sukses dimulai dari langkah kecil hari ini!',
  'Fokus pada proses, bukan hasil. Setiap tugas yang selesai adalah kemenangan.',
  'Kamu lebih kuat dari rasa malasmu. Yuk, selesaikan satu tugas lagi!',
  'Waktu terbaik untuk memulai adalah sekarang. Semangat!',
  'Setiap hari adalah kesempatan baru untuk jadi lebih baik.'
];
$mot_today = $motivasi[date('z') % count($motivasi)];
// Tampilkan avatar user jika ada, jika tidak tampilkan inisial
$avatar_path = (!empty($user['avatar']) && file_exists(__DIR__ . '/../uploads/' . $user['avatar']))
  ? '../uploads/' . $user['avatar']
  : 'profile_image.php?name=' . urlencode($user['username']);
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.min.css" />
  <style>
    .profile-img {
      width: 48px;
      height: 48px;
      border-radius: 50%;
      object-fit: cover;
      background: #eee;
    }

    .calendar-edlink {
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 2px 8px #0001;
      padding: 1.5rem;
    }

    .calendar-edlink .weekdays {
      display: flex;
      justify-content: space-between;
      font-weight: 600;
      color: #6c757d;
    }

    .calendar-edlink .days {
      display: flex;
      justify-content: space-between;
      margin-top: 0.5rem;
    }

    .calendar-edlink .day {
      text-align: center;
      width: 36px;
      height: 36px;
      line-height: 36px;
      border-radius: 50%;
      position: relative;
      font-weight: 500;
    }

    .calendar-edlink .today {
      background: #0d8abc;
      color: #fff;
    }

    .calendar-edlink .dot {
      position: absolute;
      left: 50%;
      transform: translateX(-50%);
      bottom: 4px;
      width: 8px;
      height: 8px;
      border-radius: 50%;
    }

    .calendar-edlink .dot.green {
      background: #28a745;
    }

    .calendar-edlink .dot.red {
      background: #dc3545;
    }

    .calendar-edlink .dot.orange {
      background: #fd7e14;
    }

    .theme-dark {
      background: #181c24 !important;
      color: #e0e0e0 !important;
    }

    .theme-dark .bg-white {
      background: #23272f !important;
      color: #e0e0e0 !important;
    }

    .theme-dark .calendar-edlink {
      background: #23272f !important;
      color: #e0e0e0 !important;
    }

    .theme-dark .form-control,
    .theme-dark .list-group-item {
      background: #23272f !important;
      color: #e0e0e0 !important;
    }

    .theme-dark .navbar {
      background: #222b3a !important;
    }

    .theme-dark .dropdown-menu {
      background: #23272f !important;
      color: #e0e0e0 !important;
    }

    .theme-dark .btn-light {
      background: #23272f !important;
      color: #e0e0e0 !important;
      border: 1px solid #444;
    }

    .card-task,
    .card-event,
    .list-group-item,
    .calendar-edlink,
    .bg-white,
    .shadow-sm,
    .form-control,
    .btn,
    .dropdown-menu {
      transition: background 0.3s, color 0.3s, box-shadow 0.3s;
    }

    .list-group-item:hover,
    .btn:hover,
    .calendar-edlink .day:hover {
      box-shadow: 0 2px 12px #0d8abc33;
      transform: translateY(-2px) scale(1.03);
      transition: 0.2s;
    }

    .calendar-edlink .day {
      cursor: pointer;
    }

    .avatar-upload {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .avatar-upload input[type=file] {
      display: none;
    }

    .avatar-upload label {
      cursor: pointer;
      color: #0d8abc;
      text-decoration: underline;
    }

    @media (min-width: 992px) {
      .sidebar-right {
        position: sticky;
        top: 32px;
        height: fit-content;
      }
    }
  </style>
  <script>
    function cekNotifikasi() {
      fetch('cek_notif.php')
        .then(res => res.json())
        .then(function(data) {
          if (data.tugas && data.tugas.length > 0) {
            let pesan = 'Tugas berikut sudah mencapai deadline!\n';
            data.tugas.forEach(function(t) {
              pesan += '- ' + t.nama_tugas + ' (Deadline: ' + t.deadline + ')\n';
            });
            alert(pesan);
          }
        });
    }
    setInterval(cekNotifikasi, 60000);
    window.onload = function() {
      cekNotifikasi();
    };
  </script>
</head>

<body class="bg-light">
  <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow">
    <div class="container-fluid">
      <a class="navbar-brand fw-bold" href="#"><i class="fa-solid fa-calendar-check"></i> Fokus & Selesai</a>
      <div class="d-flex align-items-center gap-3">
        <button class="btn btn-light" id="themeToggle" title="Ganti Tema"><i class="fa-solid fa-moon"></i></button>
        <div class="dropdown">
          <button class="btn position-relative" id="notifDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="color:white;">
            <i class="fa-solid fa-bell fa-lg"></i>
            <span id="notif-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="display:none;">!</span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notifDropdown" style="min-width:260px;">
            <li><span class="dropdown-item-text fw-bold">Notifikasi</span></li>
            <li>
              <hr class="dropdown-divider">
            </li>
            <li>
              <div id="notif-area" class="small text-muted px-3">Tidak ada notifikasi baru.</div>
            </li>
          </ul>
        </div>
        <div class="dropdown">
          <button class="btn d-flex align-items-center gap-2 text-white" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="background:transparent;">
            <img id="avatar-img" src="<?= $avatar_path ?>" class="profile-img" alt="profile">
            <span class="fw-semibold"><i class="fa-solid fa-user"></i> <?= esc($user['username']) ?></span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
            <li>
              <form class="avatar-upload px-3 py-2" enctype="multipart/form-data" method="post" id="avatarForm">
                <input type="file" id="avatarfile" name="avatar" accept="image/*">
                <label for="avatarfile"><i class="fa-solid fa-image"></i> Ganti Foto</label>
              </form>
            </li>
            <li>
              <form method="post" class="px-3 py-2">
                <input type="hidden" name="delete_avatar" value="1">
                <button type="submit" class="btn btn-link text-danger p-0" style="text-decoration:underline;"><i class="fa-solid fa-trash"></i> Hapus Foto Profil</button>
              </form>
            </li>
            <li>
              <hr class="dropdown-divider">
            </li>
            <li><button class="dropdown-item" id="showStatsBtn" type="button"><i class="fa-solid fa-chart-bar"></i> Statistik Mingguan</button></li>
            <li>
              <hr class="dropdown-divider">
            </li>
            <li><a class="dropdown-item text-danger" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
          </ul>
        </div>
      </div>
    </div>
  </nav>
  <div class="container-fluid py-4">
    <div class="row g-4">
      <!-- Sidebar Kiri: Kalender & Daftar Tugas/Acara -->
      <div class="col-lg-3 sidebar-col">
        <?php if ($err_avatar): ?><div class="alert alert-danger"><?= esc($err_avatar) ?></div><?php endif; ?>
        <!-- --- Kalender Bulanan dengan Navigasi dan Jadwal Harian --- -->
        <?php
        $bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('m');
        $tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');
        $today = date('Y-m-d');
        $selected = isset($_GET['tgl']) ? $_GET['tgl'] : $today;
        $firstDay = new DateTime("$tahun-$bulan-01");
        $startDay = (int)$firstDay->format('N'); // 1=Senin
        $daysInMonth = (int)$firstDay->format('t');
        $prevMonth = $bulan == 1 ? 12 : $bulan - 1;
        $prevYear = $bulan == 1 ? $tahun - 1 : $tahun;
        $nextMonth = $bulan == 12 ? 1 : $bulan + 1;
        $nextYear = $bulan == 12 ? $tahun + 1 : $tahun;
        $bulanNama = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        ?>
        <div class="calendar-edlink mb-4">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <a class="btn btn-sm btn-outline-primary" href="?bulan=<?= $prevMonth ?>&tahun=<?= $prevYear ?>&tgl=<?= $selected ?>">&lt;</a>
            <span class="fw-bold fs-5"><?= $bulanNama[$bulan] ?> <?= $tahun ?></span>
            <a class="btn btn-sm btn-outline-primary" href="?bulan=<?= $nextMonth ?>&tahun=<?= $nextYear ?>&tgl=<?= $selected ?>">&gt;</a>
          </div>
          <div class="weekdays mb-1" style="display:flex;justify-content:space-between;">
            <?php $hari = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
            foreach ($hari as $h) echo '<span style="font-weight:600;color:#888;font-size:1.1em;">' . $h . '</span>'; ?>
          </div>
          <div class="days mb-3" style="flex-wrap:nowrap;display:flex;flex-direction:row;gap:18px;justify-content:space-between;">
            <?php
            $now = isset($selected) ? new DateTime($selected) : new DateTime();
            $monday = (clone $now)->modify('monday this week');
            for ($i = 0; $i < 7; $i++) {
              $tgl = (clone $monday)->modify("+{$i} day");
              $dateStr = $tgl->format('Y-m-d');
              $dayNum = $tgl->format('d');
              $isToday = $dateStr === date('Y-m-d');
              $isSelected = $dateStr === $selected;
              $dot = '';
              $sql = "SELECT COUNT(*) as jml FROM tugas WHERE user_id=$user_id AND DATE(deadline)='$dateStr'";
              $rs = $conn->query($sql);
              $hasTugas = $rs && $rs->fetch_assoc()['jml'] > 0;
              $sql2 = "SELECT COUNT(*) as jml FROM acara WHERE user_id=$user_id AND tanggal='$dateStr'";
              $rs2 = $conn->query($sql2);
              $hasAcara = $rs2 && $rs2->fetch_assoc()['jml'] > 0;
              if ($hasTugas || $hasAcara) $dot = '<div style="width:6px;height:6px;border-radius:50%;background:#16a34a;margin:0 auto;margin-top:2px;"></div>';
              echo '<div style="text-align:center;">';
              echo '<a href="?tgl=' . $dateStr . '" style="text-decoration:none;">';
              echo '<div style="width:32px;height:32px;line-height:32px;border-radius:50%;font-weight:600;font-size:1.1em;' . ($isToday ? 'background:#16a34a;color:#fff;' : ($isSelected ? 'border:2px solid #16a34a;color:#16a34a;' : 'color:#222;')) . '">' . $dayNum . '</div>';
              echo '</a>';
              echo $dot;
              echo '</div>';
            }
            ?>
          </div>
          <div class="text-center mb-2">
            <span class="fw-bold fs-5">Jadwal Hari Ini</span>
            <div class="d-flex justify-content-center align-items-center gap-2 mt-1 mb-2">
              <span class="fs-6 fw-bold" style="letter-spacing:0.5px;"> <?= tgl_indo_edlink($selected) ?> </span>
            </div>
          </div>
          <div class="bg-white rounded shadow-sm p-2 mb-2">
            <?php
            $show_tgl = $selected;
            $tugas_tgl = $conn->query("SELECT * FROM tugas WHERE user_id=$user_id AND DATE(deadline)='$show_tgl' ORDER BY deadline ASC");
            $acara_tgl = $conn->query("SELECT * FROM acara WHERE user_id=$user_id AND tanggal='$show_tgl' ORDER BY tanggal ASC");
            ?>
            <?php if ($tugas_tgl->num_rows == 0 && $acara_tgl->num_rows == 0): ?>
              <div class="text-muted small text-center">Tidak ada jadwal di tanggal ini.</div>
            <?php endif; ?>
            <?php while ($t = $tugas_tgl->fetch_assoc()): ?>
              <div class="card shadow-sm mb-2" style="border-radius:16px;">
                <div class="card-body p-3 d-flex flex-column gap-2">
                  <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="badge rounded-pill bg-<?= $t['status'] === 'selesai' ? 'success' : 'warning' ?> px-3 py-2" style="font-size:0.9em;"> <?= $t['status'] === 'selesai' ? 'Selesai' : 'Berlangsung' ?> </span>
                    <span class="fw-bold">Tugas</span>
                  </div>
                  <div class="fw-semibold fs-6"> <?= esc($t['nama_tugas']) ?> </div>
                  <?php if (!empty($t['deskripsi'])): ?><div class="text-muted small"> <?= esc($t['deskripsi']) ?> </div><?php endif; ?>
                  <div class="d-flex justify-content-between align-items-center mt-2">
                    <span class="text-secondary small"><i class="fa fa-clock"></i> <?= date('H:i', strtotime($t['deadline'])) ?></span>
                  </div>
                </div>
              </div>
            <?php endwhile; ?>
            <?php while ($a = $acara_tgl->fetch_assoc()): ?>
              <div class="card shadow-sm mb-2" style="border-radius:16px;">
                <div class="card-body p-3 d-flex flex-column gap-2">
                  <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="badge rounded-pill bg-info text-white px-3 py-2" style="font-size:0.9em;">Acara</span>
                    <span class="fw-bold">Acara</span>
                  </div>
                  <div class="fw-semibold fs-6"> <?= esc($a['nama_acara']) ?> </div>
                  <?php if (!empty($a['deskripsi'])): ?><div class="text-muted small"> <?= esc($a['deskripsi']) ?> </div><?php endif; ?>
                  <div class="d-flex justify-content-between align-items-center mt-2">
                    <span class="text-secondary small"><i class="fa fa-calendar"></i> <?= date('H:i', strtotime($a['tanggal'] . ' 00:00')) ?></span>
                  </div>
                </div>
              </div>
            <?php endwhile; ?>
          </div>
        </div>
        <!-- Circular Progress Statistik -->
        <div class="bg-white rounded shadow-sm p-3 mb-4 text-center">
          <h6 class="fw-bold mb-3"><i class="fa-solid fa-chart-simple"></i> Statistik</h6>
          <div class="d-flex justify-content-center align-items-center mb-2">
            <svg width="80" height="80">
              <circle cx="40" cy="40" r="34" stroke="#eee" stroke-width="8" fill="none" />
              <circle cx="40" cy="40" r="34" stroke="#0d8abc" stroke-width="8" fill="none" stroke-dasharray="<?= 2 * pi() * 34 ?>" stroke-dashoffset="<?= 2 * pi() * 34 * (1 - $progress / 100) ?>" style="transition:stroke-dashoffset 1s;" />
              <text x="40" y="46" text-anchor="middle" font-size="20" fill="#0d8abc" font-weight="bold"><?= $progress ?>%</text>
            </svg>
          </div>
          <div>Total Tugas: <span class="fw-bold text-primary"><?= $total ?></span></div>
          <div>Tugas Selesai: <span class="fw-bold text-success"><?= $done ?></span></div>
          <div>Total Acara: <span class="fw-bold text-info"><?= $acara_count ?></span></div>
        </div>
      </div>
      <!-- Tengah: Form Tambah Tugas & Acara -->
      <div class="col-lg-6">
        <div class="bg-white rounded shadow-sm p-4 mb-4">
          <h5 class="fw-bold mb-4"><i class="fa-solid fa-plus"></i> Tambah Tugas</h5>
          <?php if ($err_tugas): ?><div class="alert alert-danger"><?= esc($err_tugas) ?></div><?php endif; ?>
          <form method="post" class="row g-3 mb-4">
            <div class="col-md-6">
              <input type="text" name="nama_tugas" class="form-control" placeholder="Nama Tugas" required>
            </div>
            <div class="col-md-6">
              <input type="datetime-local" name="deadline_tugas" class="form-control" required>
            </div>
            <div class="col-12">
              <textarea name="deskripsi_tugas" class="form-control" placeholder="Deskripsi"></textarea>
            </div>
            <div class="col-12">
              <button type="submit" name="add_tugas" class="btn btn-success w-100">Tambah Tugas</button>
            </div>
          </form>
        </div>
        <div class="bg-white rounded shadow-sm p-4">
          <h5 class="fw-bold mb-4"><i class="fa-solid fa-calendar-plus"></i> Tambah Acara</h5>
          <?php if ($err_acara): ?><div class="alert alert-danger"><?= esc($err_acara) ?></div><?php endif; ?>
          <form method="post" class="row g-3">
            <div class="col-md-6">
              <input type="text" name="nama_acara" class="form-control" placeholder="Nama Acara" required>
            </div>
            <div class="col-md-6">
              <input type="date" name="tanggal_acara" class="form-control" required>
            </div>
            <div class="col-12">
              <textarea name="deskripsi_acara" class="form-control" placeholder="Deskripsi"></textarea>
            </div>
            <div class="col-12">
              <button type="submit" name="add_acara" class="btn btn-info w-100 text-white">Tambah Acara</button>
            </div>
          </form>
        </div>
      </div>
      <!-- Sidebar Kanan: Catatan & Checklist -->
      <div class="col-lg-3 sidebar-col">
        <div class="bg-white rounded shadow-sm p-3 mb-4">
          <h6 class="fw-bold mb-3"><i class="fa-solid fa-clipboard-check"></i> Catatan & Checklist</h6>
          <form id="noteForm" class="mb-2">
            <input type="text" class="form-control mb-2" id="noteInput" placeholder="Tambah catatan/checklist...">
            <button type="submit" class="btn btn-primary w-100">Tambah</button>
          </form>
          <ul class="list-group" id="noteList"></ul>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Statistik Mingguan -->
  <div class="modal fade" id="weeklyStatsModal" tabindex="-1" aria-labelledby="weeklyStatsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="weeklyStatsModalLabel"><i class="fa-solid fa-chart-bar"></i> Statistik Mingguan</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <canvas id="weeklyChartModal" height="120" style="width:100%;max-width:400px;"></canvas>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <script>
    // Theme Switcher
    const themeToggle = document.getElementById('themeToggle');
    themeToggle.onclick = function() {
      document.body.classList.toggle('theme-dark');
      localStorage.setItem('theme', document.body.classList.contains('theme-dark') ? 'dark' : 'light');
      themeToggle.innerHTML = document.body.classList.contains('theme-dark') ? '<i class="fa-solid fa-sun"></i>' : '<i class="fa-solid fa-moon"></i>';
    };
    if (localStorage.getItem('theme') === 'dark') {
      document.body.classList.add('theme-dark');
      themeToggle.innerHTML = '<i class="fa-solid fa-sun"></i>';
    }
    // Avatar Upload
    const avatarfile = document.getElementById('avatarfile');
    if (avatarfile) {
      avatarfile.onchange = function(e) {
        if (e.target.files.length > 0) {
          document.getElementById('avatarForm').submit();
        }
      };
    }
    // Checklist/Notes
    const noteForm = document.getElementById('noteForm');
    const noteInput = document.getElementById('noteInput');
    const noteList = document.getElementById('noteList');

    function renderNotes() {
      noteList.innerHTML = '';
      let notes = JSON.parse(localStorage.getItem('notes') || '[]');
      notes.forEach((n, i) => {
        let li = document.createElement('li');
        li.className = 'list-group-item d-flex justify-content-between align-items-center';
        li.innerHTML = '<span>' + n + '</span><button class="btn btn-sm btn-danger" onclick="removeNote(' + i + ')"><i class="fa-solid fa-trash"></i></button>';
        noteList.appendChild(li);
      });
    }

    function removeNote(i) {
      let notes = JSON.parse(localStorage.getItem('notes') || '[]');
      notes.splice(i, 1);
      localStorage.setItem('notes', JSON.stringify(notes));
      renderNotes();
    }
    if (noteForm) {
      noteForm.onsubmit = function(e) {
        e.preventDefault();
        let val = noteInput.value.trim();
        if (val) {
          let notes = JSON.parse(localStorage.getItem('notes') || '[]');
          notes.push(val);
          localStorage.setItem('notes', JSON.stringify(notes));
          noteInput.value = '';
          renderNotes();
        }
      };
      renderNotes();
    }
    // Statistik Mingguan
    window.addEventListener('DOMContentLoaded', () => {
      fetch('weekly_stats.php').then(r => r.json()).then(data => {
        new Chart(document.getElementById('weeklyChart').getContext('2d'), {
          type: 'bar',
          data: {
            labels: data.labels,
            datasets: [{
              label: 'Tugas Selesai',
              data: data.data,
              backgroundColor: '#0d8abc'
            }]
          },
          options: {
            scales: {
              y: {
                beginAtZero: true
              }
            }
          }
        });
      });
    });
    // Sound Notification
    let notifSound = new Audio('https://cdn.pixabay.com/audio/2022/07/26/audio_124bfa4c3b.mp3');

    function cekNotifikasiNavbar() {
      fetch('cek_notif.php')
        .then(res => res.json())
        .then(data => {
          let notifArea = document.getElementById('notif-area');
          let notifBadge = document.getElementById('notif-badge');
          if (data.tugas && data.tugas.length > 0) {
            notifBadge.style.display = '';
            let pesan = '';
            data.tugas.forEach(t => {
              pesan += '<div class="mb-1">' +
                '<i class="fa-solid fa-circle-exclamation text-danger"></i> ' +
                '<b>' + t.nama_tugas + '</b> (Deadline: ' + t.deadline + ')</div>';
            });
            notifArea.innerHTML = pesan;
            notifSound.play();
          } else {
            notifBadge.style.display = 'none';
            notifArea.innerHTML = '<span class="text-muted">Tidak ada notifikasi baru.</span>';
          }
        });
    }
    setInterval(cekNotifikasiNavbar, 60000);
    window.onload = function() {
      cekNotifikasiNavbar();
    };
    // Statistik Mingguan di Modal
    let weeklyChartModal = null;
    const weeklyStatsModal = new bootstrap.Modal(document.getElementById('weeklyStatsModal'));
    document.getElementById('showStatsBtn').onclick = function() {
      weeklyStatsModal.show();
    };
    document.getElementById('weeklyStatsModal').addEventListener('shown.bs.modal', function() {
      fetch('weekly_stats.php').then(r => r.json()).then(data => {
        if (weeklyChartModal) weeklyChartModal.destroy();
        weeklyChartModal = new Chart(document.getElementById('weeklyChartModal').getContext('2d'), {
          type: 'bar',
          data: {
            labels: data.labels,
            datasets: [{
              label: 'Tugas Selesai',
              data: data.data,
              backgroundColor: '#0d8abc'
            }]
          },
          options: {
            scales: {
              y: {
                beginAtZero: true
              }
            }
          }
        });
      });
    });
    document.getElementById('weeklyStatsModal').addEventListener('hidden.bs.modal', function() {
      if (weeklyChartModal) {
        weeklyChartModal.destroy();
        weeklyChartModal = null;
      }
    });
  </script>
</body>

</html>