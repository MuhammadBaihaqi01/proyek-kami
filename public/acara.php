<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_once __DIR__ . '/../config/db.php';

$user_id = get_user_id();
$err = '';
// Tambah acara
if (isset($_POST['add'])) {
    $nama = trim($_POST['nama_acara'] ?? '');
    $desk = trim($_POST['deskripsi'] ?? '');
    $tanggal = $_POST['tanggal'] ?? '';
    if ($nama === '' || $tanggal === '') {
        $err = 'Nama acara & tanggal wajib diisi!';
    } else {
        $stmt = $conn->prepare('INSERT INTO acara (user_id, nama_acara, deskripsi, tanggal, created_at) VALUES (?, ?, ?, ?, NOW())');
        $stmt->bind_param('isss', $user_id, $nama, $desk, $tanggal);
        $stmt->execute();
        header('Location: acara.php'); exit;
    }
}
// Hapus acara
if (isset($_GET['del'])) {
    $id = (int)$_GET['del'];
    $conn->query("DELETE FROM acara WHERE id = $id AND user_id = $user_id");
    header('Location: acara.php'); exit;
}
// Edit acara
if (isset($_POST['edit_id'])) {
    $id = (int)$_POST['edit_id'];
    $nama = trim($_POST['edit_nama'] ?? '');
    $desk = trim($_POST['edit_deskripsi'] ?? '');
    $tanggal = $_POST['edit_tanggal'] ?? '';
    if ($nama && $tanggal) {
        $stmt = $conn->prepare('UPDATE acara SET nama_acara=?, deskripsi=?, tanggal=? WHERE id=? AND user_id=?');
        $stmt->bind_param('sssii', $nama, $desk, $tanggal, $id, $user_id);
        $stmt->execute();
        header('Location: acara.php'); exit;
    }
}
$acara = $conn->query("SELECT * FROM acara WHERE user_id = $user_id ORDER BY tanggal ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manajemen Acara</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-4">
    <h3>Tambah Acara</h3>
    <?php if ($err): ?><div class="alert alert-danger"><?= esc($err) ?></div><?php endif; ?>
    <form method="post" class="row g-3 mb-4">
        <div class="col-md-4">
            <input type="text" name="nama_acara" class="form-control" placeholder="Nama Acara" required>
        </div>
        <div class="col-md-4">
            <input type="text" name="deskripsi" class="form-control" placeholder="Deskripsi">
        </div>
        <div class="col-md-3">
            <input type="date" name="tanggal" class="form-control" required>
        </div>
        <div class="col-md-1">
            <button type="submit" name="add" class="btn btn-success">Tambah</button>
        </div>
    </form>
    <h4>Daftar Acara</h4>
    <table class="table table-bordered table-striped">
        <thead><tr><th>Nama</th><th>Deskripsi</th><th>Tanggal</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php while($a = $acara->fetch_assoc()): ?>
        <tr>
            <td><?= esc($a['nama_acara']) ?></td>
            <td><?= esc($a['deskripsi']) ?></td>
            <td><?= esc($a['tanggal']) ?></td>
            <td>
                <button class="btn btn-sm btn-info" onclick="editAcara(<?= $a['id'] ?>, '<?= esc(addslashes($a['nama_acara'])) ?>', '<?= esc(addslashes($a['deskripsi'])) ?>', '<?= $a['tanggal'] ?>')">Edit</button>
                <a href="?del=<?= $a['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus acara?')">Hapus</a>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    <!-- Modal Edit -->
    <div class="modal" tabindex="-1" id="editModal">
      <div class="modal-dialog">
        <div class="modal-content">
          <form method="post">
            <div class="modal-header"><h5 class="modal-title">Edit Acara</h5></div>
            <div class="modal-body">
              <input type="hidden" name="edit_id" id="edit_id">
              <div class="mb-3">
                <label>Nama Acara</label>
                <input type="text" name="edit_nama" id="edit_nama" class="form-control" required>
              </div>
              <div class="mb-3">
                <label>Deskripsi</label>
                <input type="text" name="edit_deskripsi" id="edit_deskripsi" class="form-control">
              </div>
              <div class="mb-3">
                <label>Tanggal</label>
                <input type="date" name="edit_tanggal" id="edit_tanggal" class="form-control" required>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
              <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
          </form>
        </div>
      </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function editAcara(id, nama, desk, tanggal) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_nama').value = nama;
    document.getElementById('edit_deskripsi').value = desk;
    document.getElementById('edit_tanggal').value = tanggal;
    var modal = new bootstrap.Modal(document.getElementById('editModal'));
    modal.show();
}
</script>
</body>
</html>
