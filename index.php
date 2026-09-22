<?php
require 'includes/config.php';
require 'includes/functions.php';

$user = current_user();

// لو مسجل دخول بالفعل، وديه على صفحته الصح على طول
if ($user) {
    redirect($user['role'] === 'admin' ? 'admin/index.php' : 'khadem.php');
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>فريق الأنبا كاراس</title>
<link rel="icon" type="image/png" href="assets/favicon.png">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-box" style="text-align:center;">
    <img src="assets/logo.png" alt="لوجو فريق الأنبا كاراس" class="hero-logo" style="margin-bottom:8px;">
    <h1 style="margin-bottom:10px;">فريق الأنبا كاراس</h1>
    <p class="sub" style="margin-bottom:28px;">
      متابعة أسهل لمخدومينا، وتنظيم أفضل لخدمتنا — كل خادم يشوف مخدوميه، ويسجّل افتقاده أول بأول.
    </p>

    <a href="login.php" class="btn full" style="margin-bottom:12px; text-align:center;">تسجيل الدخول</a>
    <a href="register.php" class="btn secondary full" style="text-align:center;">سجّل كخادم جديد</a>

    <p class="hint" style="margin-top:24px;">نسيت كلمة المرور؟ <a href="forgot_password.php">من هنا</a></p>
  </div>
</div>
</body>
</html>
