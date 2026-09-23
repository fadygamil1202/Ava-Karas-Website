<?php
require 'includes/config.php';
require 'includes/functions.php';

if (current_user()) {
    redirect(current_user()['role'] === 'admin' ? 'admin/index.php' : 'khadem.php');
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = trim($_POST['name'] ?? '');
    $username   = trim($_POST['username'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $address    = trim($_POST['address'] ?? '');
    $lagna      = trim($_POST['lagna'] ?? '');
    $birthDate  = trim($_POST['birth_date'] ?? '');
    $password   = $_POST['password'] ?? '';
    $confirm    = $_POST['confirm'] ?? '';

    if ($name === '' || $username === '' || $phone === '' || $password === '') {
        $error = 'من فضلك املأ الاسم واسم المستخدم ورقم الموبايل وكلمة المرور';
    } elseif (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
        $error = 'اسم المستخدم لازم يكون حروف إنجليزي وأرقام و "_" فقط، من 3 لـ 50 حرف';
    } elseif (!preg_match('/^01[0-9]{9}$/', $phone)) {
        $error = 'رقم الموبايل لازم يكون رقم مصري صحيح (01 ثم 9 أرقام)، من غير مسافات أو رموز';
    } elseif ($birthDate !== '' && !strtotime($birthDate)) {
        $error = 'تاريخ الميلاد غير صحيح';
    } elseif (strlen($password) < 6) {
        $error = 'كلمة المرور لازم تكون 6 أحرف على الأقل';
    } elseif ($password !== $confirm) {
        $error = 'كلمة المرور وتأكيدها مش متطابقين';
    } else {
        // تأكد إن اسم المستخدم أو رقم الموبايل مش مستخدمين في حساب موجود بالفعل (بغض النظر عن حالة الأحرف)
        $stmt = $pdo->prepare('SELECT id FROM users WHERE LOWER(username) = LOWER(?)');
        $stmt->execute([$username]);
        $phoneStmt = $pdo->prepare('SELECT id FROM users WHERE phone = ?');
        $phoneStmt->execute([$phone]);

        if ($stmt->fetch()) {
            $error = 'اسم المستخدم ده مُستخدم بالفعل';
        } elseif ($phoneStmt->fetch()) {
            $error = 'رقم الموبايل ده مُستخدم بالفعل في حساب تاني';
        } else {
            try {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare(
                    "INSERT INTO users (name, username, password_hash, phone, address, lagna, birth_date, role, status, active)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'khadem', 'pending', 1)"
                );
                $stmt->execute([
                    $name, $username, $hash, $phone,
                    $address ?: null,
                    $lagna ?: null,
                    $birthDate !== '' ? date('Y-m-d', strtotime($birthDate)) : null,
                ]);
                $success = true;
            } catch (PDOException $e) {
                // شبكة أمان لو حصل تعارض لحظي (نفس الرقم أو اليوزرنيم اتسجل من حد تاني في نفس الوقت بالظبط)
                if ($e->getCode() === '23000') {
                    $error = 'اسم المستخدم أو رقم الموبايل ده مُستخدم بالفعل';
                } else {
                    throw $e;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>تسجيل خادم جديد - فريق الأنبا كاراس</title>
<link rel="icon" type="image/png" href="assets/favicon.png">
<link rel="stylesheet" href="assets/style.css?v=2">
</head>
<body>
<div class="login-wrap">
  <div class="login-box">
    <img src="assets/logo.png" alt="لوجو فريق الأنبا كاراس" class="hero-logo">
    <h1>تسجيل خادم جديد</h1>

    <?php if ($success): ?>
      <div class="success-msg">
        تم إرسال طلبك بنجاح. هيتفعّل حسابك بعد موافقة الأدمن عليه.
      </div>
      <a href="login.php" class="btn full" style="text-align:center;">رجوع لتسجيل الدخول</a>
    <?php else: ?>
      <p class="sub">هيتم مراجعة طلبك من الأدمن قبل ما تقدر تدخل</p>

      <?php if ($error): ?>
        <div class="error-msg"><?= h($error) ?></div>
      <?php endif; ?>

      <form method="post" action="register.php">
        <div class="field">
          <label>الاسم بالكامل</label>
          <input type="text" name="name" value="<?= h($_POST['name'] ?? '') ?>" required autofocus>
        </div>
        <div class="field">
          <label>اسم المستخدم (إنجليزي)</label>
          <input type="text" name="username" value="<?= h($_POST['username'] ?? '') ?>" required>
        </div>
        <div class="field">
          <label>رقم الموبايل</label>
          <input type="text" name="phone" value="<?= h($_POST['phone'] ?? '') ?>" placeholder="01xxxxxxxxx" required>
        </div>
        <div class="field">
          <label>العنوان</label>
          <input type="text" name="address" value="<?= h($_POST['address'] ?? '') ?>">
        </div>
        <div class="field">
          <label>اللجنة</label>
          <input type="text" name="lagna" value="<?= h($_POST['lagna'] ?? '') ?>">
        </div>
        <div class="field">
          <label>تاريخ الميلاد</label>
          <input type="date" name="birth_date" value="<?= h($_POST['birth_date'] ?? '') ?>">
        </div>
        <div class="field">
          <label>كلمة المرور</label>
          <input type="password" name="password" required>
        </div>
        <div class="field">
          <label>تأكيد كلمة المرور</label>
          <input type="password" name="confirm" required>
        </div>
        <button class="full" type="submit">إرسال طلب التسجيل</button>
      </form>

      <p class="hint">عندك حساب بالفعل؟ <a href="login.php">سجّل دخولك</a></p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
