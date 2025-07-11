<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_once __DIR__ . '/../config/db.php';

$user_id = get_user_id();
$now = date('Y-m-d H:i:s');
$sql = "SELECT nama_tugas, deadline FROM tugas WHERE user_id = $user_id AND status = 'belum' AND deadline <= '$now'";
$tugas = $conn->query($sql);
$data = [];
while($t = $tugas->fetch_assoc()) $data[] = $t;
echo json_encode(['tugas' => $data]);
