<?php
require 'includes/config.php';
require 'includes/functions.php';

// لو داخل بالفعل، وديه على صفحته الصح
if (current_user()) {
    redirect(current_user()['role'] === 'admin' ? 'admin/index.php' : 'khadem.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($identifier === '' || $password === '') {
        $error = 'من فضلك ادخل اسم المستخدم أو رقم الموبايل وكلمة المرور';
    } else {
        // يدخل باسم المستخدم أو رقم الموبايل، أيًا منهما (اسم المستخدم مش حساس لحالة الأحرف)
        $stmt = $pdo->prepare('SELECT * FROM users WHERE LOWER(username) = LOWER(?) OR phone = ?');
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $error = 'اسم المستخدم أو كلمة المرور غير صحيحة';
        } elseif (!$user['active']) {
            $error = 'حسابك معطّل حاليًا، كلّم الأدمن';
        } elseif ($user['status'] !== 'approved') {
            $error = 'حسابك لسه تحت المراجعة، هتقدر تدخل بعد ما الأدمن يوافق عليه';
        } else {
            // نجاح تسجيل الدخول
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id'                    => $user['id'],
                'name'                  => $user['name'],
                'username'              => $user['username'],
                'role'                  => $user['role'],
                'can_manage_attendance' => (bool)$user['can_manage_attendance'],
            ];
            redirect($user['role'] === 'admin' ? 'admin/index.php' : 'khadem.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>تسجيل الدخول - فريق الأنبا كاراس</title>
<link rel="icon" type="image/png" href="assets/favicon.png">
<link rel="stylesheet" href="assets/style.css?v=2">
</head>
<body>
<div class="login-wrap">
  <div class="login-box">
    <img src="assets/logo.png" alt="لوجو فريق الأنبا كاراس" class="hero-logo">
    <h1>فريق الأنبا كاراس</h1>
    <p class="sub">سجّل دخولك لمتابعة مخدوميك</p>

    <?php if ($error): ?>
      <div class="error-msg"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" action="login.php">
      <div class="field">
        <label>اسم المستخدم أو رقم الموبايل</label>
        <input type="text" name="username" value="<?= h($_POST['username'] ?? '') ?>" autocomplete="username" required autofocus>
      </div>
      <div class="field">
        <label>كلمة المرور</label>
        <input type="password" name="password" autocomplete="current-password" required>
      </div>
      <button class="full" type="submit">دخول</button>
    </form>

    <p class="hint"><a href="forgot_password.php">نسيت كلمة المرور؟</a></p>
    <p class="hint">لسه معاكش حساب؟ <a href="register.php">سجّل كخادم جديد</a></p>
  </div>
</div>
</body>
</html>
