<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_once __DIR__ . '/../config/db.php';

$user_id = get_user_id();
$err = '';
// Tambah tugas
if (isset($_POST['add'])) {
    $nama = trim($_POST['nama_tugas'] ?? '');
    $desk = trim($_POST['deskripsi'] ?? '');
    $deadline = $_POST['deadline'] ?? '';
    if ($nama === '' || $deadline === '') {
        $err = 'Nama tugas & deadline wajib diisi!';
    } else {
        $stmt = $conn->prepare('INSERT INTO tugas (user_id, nama_tugas, deskripsi, deadline, status, created_at) VALUES (?, ?, ?, ?, "belum", NOW())');
        $stmt->bind_param('isss', $user_id, $nama, $desk, $deadline);
        $stmt->execute();
        header('Location: tugas.php'); exit;
    }
}
// Hapus tugas
if (isset($_GET['del'])) {
    $id = (int)$_GET['del'];
    $conn->query("DELETE FROM tugas WHERE id = $id AND user_id = $user_id");
    header('Location: tugas.php'); exit;
}
// Ubah status
if (isset($_GET['done'])) {
    $id = (int)$_GET['done'];
    $conn->query("UPDATE tugas SET status = 'selesai' WHERE id = $id AND user_id = $user_id");
    header('Location: tugas.php'); exit;
}
// Edit tugas
if (isset($_POST['edit_id'])) {
    $id = (int)$_POST['edit_id'];
    $nama = trim($_POST['edit_nama'] ?? '');
    $desk = trim($_POST['edit_deskripsi'] ?? '');
    $deadline = $_POST['edit_deadline'] ?? '';
    if ($nama && $deadline) {
        $stmt = $conn->prepare('UPDATE tugas SET nama_tugas=?, deskripsi=?, deadline=? WHERE id=? AND user_id=?');
        $stmt->bind_param('sssii', $nama, $desk, $deadline, $id, $user_id);
        $stmt->execute();
        header('Location: tugas.php'); exit;
    }
}
$tugas = $conn->query("SELECT * FROM tugas WHERE user_id = $user_id ORDER BY deadline ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manajemen Tugas</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-4">
    <h3>Tambah Tugas</h3>
    <?php if ($err): ?><div class="alert alert-danger"><?= esc($err) ?></div><?php endif; ?>
    <form method="post" class="row g-3 mb-4">
        <div class="col-md-4">
            <input type="text" name="nama_tugas" class="form-control" placeholder="Nama Tugas" required>
        </div>
        <div class="col-md-4">
            <input type="text" name="deskripsi" class="form-control" placeholder="Deskripsi">
        </div>
        <div class="col-md-3">
            <input type="datetime-local" name="deadline" class="form-control" required>
        </div>
        <div class="col-md-1">
            <button type="submit" name="add" class="btn btn-success">Tambah</button>
        </div>
    </form>
    <h4>Daftar Tugas</h4>
    <table class="table table-bordered table-striped">
        <thead><tr><th>Nama</th><th>Deskripsi</th><th>Deadline</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php while($t = $tugas->fetch_assoc()): ?>
        <tr>
            <td><?= esc($t['nama_tugas']) ?></td>
            <td><?= esc($t['deskripsi']) ?></td>
            <td><?= esc($t['deadline']) ?></td>
            <td><span class="badge bg-<?= $t['status']==='selesai'?'success':'warning' ?>"><?= esc($t['status']) ?></span></td>
            <td>
                <?php if ($t['status'] !== 'selesai'): ?>
                <a href="?done=<?= $t['id'] ?>" class="btn btn-sm btn-success">Selesai</a>
                <?php endif; ?>
                <button class="btn btn-sm btn-info" onclick="editTugas(<?= $t['id'] ?>, '<?= esc(addslashes($t['nama_tugas'])) ?>', '<?= esc(addslashes($t['deskripsi'])) ?>', '<?= $t['deadline'] ?>')">Edit</button>
                <a href="?del=<?= $t['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus tugas?')">Hapus</a>
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
            <div class="modal-header"><h5 class="modal-title">Edit Tugas</h5></div>
            <div class="modal-body">
              <input type="hidden" name="edit_id" id="edit_id">
              <div class="mb-3">
                <label>Nama Tugas</label>
                <input type="text" name="edit_nama" id="edit_nama" class="form-control" required>
              </div>
              <div class="mb-3">
                <label>Deskripsi</label>
                <input type="text" name="edit_deskripsi" id="edit_deskripsi" class="form-control">
              </div>
              <div class="mb-3">
                <label>Deadline</label>
                <input type="datetime-local" name="edit_deadline" id="edit_deadline" class="form-control" required>
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
function editTugas(id, nama, desk, deadline) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_nama').value = nama;
    document.getElementById('edit_deskripsi').value = desk;
    document.getElementById('edit_deadline').value = deadline.replace(' ', 'T');
    var modal = new bootstrap.Modal(document.getElementById('editModal'));
    modal.show();
}
</script>
</body>
</html>
