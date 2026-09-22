<?php
require 'includes/config.php';
require 'includes/functions.php';

if (current_user()) {
    redirect(current_user()['role'] === 'admin' ? 'admin/index.php' : 'khadem.php');
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'من فضلك ادخل اسم المستخدم أو رقم الموبايل وكلمة المرور الجديدة';
    } elseif (strlen($password) < 6) {
        $error = 'كلمة المرور الجديدة لازم تكون 6 أحرف على الأقل';
    } elseif ($password !== $confirm) {
        $error = 'كلمة المرور وتأكيدها مش متطابقين';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR phone = ?) AND status = 'approved' AND active = 1");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        // بنعرض نفس رسالة النجاح سواء اليوزرنيم موجود أو لأ، عشان محدش يعرف يجرب يوزرنيمات
        if ($user) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            // نشيل أي طلب قديم ليه ونحط الطلب الجديد بدل منه
            $pdo->prepare('DELETE FROM password_reset_requests WHERE user_id = ?')->execute([$user['id']]);
            $pdo->prepare('INSERT INTO password_reset_requests (user_id, new_password_hash) VALUES (?, ?)')
                ->execute([$user['id'], $hash]);
        }
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>استعادة كلمة المرور - فريق الأنبا كاراس</title>
<link rel="icon" type="image/png" href="assets/favicon.png">
<link rel="stylesheet" href="assets/style.css?v=2">
</head>
<body>
<div class="login-wrap">
  <div class="login-box">
    <img src="assets/logo.png" alt="لوجو فريق الأنبا كاراس" class="hero-logo">
    <h1>استعادة كلمة المرور</h1>
    <p class="sub">اكتب اليوزرنيم بتاعك والباسورد الجديد اللي عايزه، وهيتفعّل بعد موافقة الأدمن</p>

    <?php if ($success): ?>
      <div class="success-msg">
        تم إرسال طلبك. لو اسم المستخدم ده مسجل عندنا، هيتفعّل الباسورد الجديد بعد موافقة الأدمن.
      </div>
      <a href="login.php" class="btn full" style="text-align:center;">رجوع لتسجيل الدخول</a>
    <?php else: ?>

      <?php if ($error): ?>
        <div class="error-msg"><?= h($error) ?></div>
      <?php endif; ?>

      <form method="post" action="forgot_password.php">
        <div class="field">
          <label>اسم المستخدم أو رقم الموبايل</label>
          <input type="text" name="username" value="<?= h($_POST['username'] ?? '') ?>" required autofocus>
        </div>
        <div class="field">
          <label>كلمة المرور الجديدة</label>
          <input type="password" name="password" required>
        </div>
        <div class="field">
          <label>تأكيد كلمة المرور الجديدة</label>
          <input type="password" name="confirm" required>
        </div>
        <button class="full" type="submit">إرسال الطلب</button>
      </form>

      <p class="hint"><a href="login.php">رجوع لتسجيل الدخول</a></p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
