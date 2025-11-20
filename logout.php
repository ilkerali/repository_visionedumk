<?php
/**
 * Kullanıcı Çıkış İşlemi
 * 
 * Bu sayfa kullanıcının güvenli bir şekilde sistemden çıkış yapmasını sağlar.
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';

startSession();

// Çıkış işlemini gerçekleştir
logoutUser();

// Login sayfasına yönlendir
header("Location: login.php?logout=1");
exit();
?>