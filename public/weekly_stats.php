<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/db.php';
$user_id = get_user_id();
$labels = [];
$data = [];
$now = new DateTime();
$monday = (clone $now)->modify('monday this week');
for($i=0;$i<7;$i++) {
  $tgl = (clone $monday)->modify("+{$i} day")->format('Y-m-d');
  $labels[] = $tgl;
  $q = $conn->query("SELECT COUNT(*) as jml FROM tugas WHERE user_id=$user_id AND status='selesai' AND DATE(deadline)='$tgl'");
  $data[] = (int)$q->fetch_assoc()['jml'];
}
echo json_encode(['labels'=>$labels,'data'=>$data]);
