<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_once __DIR__ . '/../config/db.php';

$user_id = get_user_id();
$events = [];
// Tugas
$tugas = $conn->query("SELECT id, nama_tugas as title, deadline as start FROM tugas WHERE user_id = $user_id");
while($t = $tugas->fetch_assoc()) {
    $t['color'] = '#007bff'; // biru
    $t['url'] = 'tugas.php?edit=' . $t['id'];
    $events[] = $t;
}
// Acara
$acara = $conn->query("SELECT id, nama_acara as title, tanggal as start FROM acara WHERE user_id = $user_id");
while($a = $acara->fetch_assoc()) {
    $a['color'] = '#28a745'; // hijau
    $a['url'] = 'acara.php?edit=' . $a['id'];
    $events[] = $a;
}
echo json_encode($events);
