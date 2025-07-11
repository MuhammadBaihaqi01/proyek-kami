<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username === '' || $password === '') {
        $err = 'Username dan password wajib diisi!';
    } elseif (login($username, $password)) {
        redirect('dashboard.php');
    } else {
        $err = 'Username atau password salah!';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card">
                <div class="card-header text-center">Login</div>
                <div class="card-body">
                    <?php if (isset($_GET['register']) && $_GET['register'] === 'success'): ?>
                        <div class="alert alert-success">Registrasi berhasil! Silakan login.</div>
                    <?php endif; ?>
                    <?php if ($err): ?>
                        <div class="alert alert-danger"><?= esc($err) ?></div>
                    <?php endif; ?>
                    <form method="post">
                        <div class="mb-3">
                            <label>Username</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Login</button>
                    </form>
                    <div class="mt-3 text-center">
                        Belum punya akun? <a href="register.php">Daftar</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
