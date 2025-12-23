<?php
// hapus token JWT di cookie
setcookie('token', '', time() - 3600, '/');  // path harus sama dengan saat setcookie
unset($_COOKIE['token']);                   // optional, biar di request ini juga hilang

// kalau dulu masih pakai session, sekalian matikan (aman walau sudah nggak dipakai)
session_start();
$_SESSION = [];
session_destroy();

header('Location: index.php');
exit;