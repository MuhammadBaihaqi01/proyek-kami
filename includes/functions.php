<?php
// Fungsi utilitas umum
function esc($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function get_user_id() {
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}
?>
