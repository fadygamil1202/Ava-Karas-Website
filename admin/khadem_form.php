<?php
require '../includes/config.php';
require '../includes/functions.php';
require_admin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
$isEdit = $id > 0;
$error = '';

$u = ['name' => '', 'username' => '', 'phone' => '', 'address' => '', 'lagna' => '', 'birth_date' => '', 'role' => 'khadem', 'active' => 1];
if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) {
        set_flash('error', 'المستخدم غير موجود');
        redirect('khadam.php');
    }
    $u = $found;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = trim($_POST['name'] ?? '');
    $username   = trim($_POST['username'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $address    = trim($_POST['address'] ?? '');
    $lagna      = trim($_POST['lagna'] ?? '');
    $birthDate  = trim($_POST['birth_date'] ?? '');
    $role       = ($_POST['role'] ?? 'khadem') === 'admin' ? 'admin' : 'khadem';
    $active     = isset($_POST['active']) ? 1 : 0;
    $password   = $_POST['password'] ?? '';

    $u = array_merge($u, compact('name', 'username', 'phone', 'address', 'lagna', 'birth_date', 'role', 'active'));

    if ($name === '' || $username === '' || $phone === '') {
        $error = 'الاسم واسم المستخدم ورقم الموبايل مطلوبين';
    } elseif (!$isEdit && strlen($password) < 6) {
        $error = 'كلمة المرور لازم تكون 6 أحرف على الأقل';
    } elseif (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
        $error = 'اسم المستخدم لازم يكون حروف إنجليزي وأرقام و "_" فقط';
    } elseif (!preg_match('/^01[0-9]{9}$/', $phone)) {
        $error = 'رقم الموبايل لازم يكون رقم مصري صحيح (01 ثم 9 أرقام)';
    } elseif ($birthDate !== '' && !strtotime($birthDate)) {
        $error = 'تاريخ الميلاد غير صحيح';
    } else {
        // تأكد إن اسم المستخدم أو رقم الموبايل مش مكررين
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
        $stmt->execute([$username, $id]);
        $phoneStmt = $pdo->prepare('SELECT id FROM users WHERE phone = ? AND id != ?');
        $phoneStmt->execute([$phone, $id]);

        if ($stmt->fetch()) {
            $error = 'اسم المستخدم ده مُستخدم بالفعل';
        } elseif ($phoneStmt->fetch()) {
            $error = 'رقم الموبايل ده مُستخدم بالفعل في حساب تاني';
        } else {
            $addressVal = $address ?: null;
            $lagnaVal = $lagna ?: null;
            $birthDateVal = $birthDate !== '' ? date('Y-m-d', strtotime($birthDate)) : null;

            if ($isEdit) {
                if ($password !== '') {
                    if (strlen($password) < 6) {
                        $error = 'كلمة المرور الجديدة لازم تكون 6 أحرف على الأقل';
                    } else {
                        $hash = password_hash($password, PASSWORD_BCRYPT);
                        $stmt = $pdo->prepare('UPDATE users SET name=?, username=?, phone=?, address=?, lagna=?, birth_date=?, role=?, active=?, password_hash=? WHERE id=?');
                        $stmt->execute([$name, $username, $phone, $addressVal, $lagnaVal, $birthDateVal, $role, $active, $hash, $id]);
                    }
                } else {
                    $stmt = $pdo->prepare('UPDATE users SET name=?, username=?, phone=?, address=?, lagna=?, birth_date=?, role=?, active=? WHERE id=?');
                    $stmt->execute([$name, $username, $phone, $addressVal, $lagnaVal, $birthDateVal, $role, $active, $id]);
                }
                if (!$error) {
                    set_flash('success', 'تم حفظ التعديلات بنجاح');
                    redirect('khadam.php');
                }
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                // حساب بيعمله الأدمن بيبقى معتمد (approved) فورًا
                $stmt = $pdo->prepare(
                    'INSERT INTO users (name, username, password_hash, phone, address, lagna, birth_date, role, status, active) VALUES (?,?,?,?,?,?,?,?,"approved",?)'
                );
                $stmt->execute([$name, $username, $hash, $phone, $addressVal, $lagnaVal, $birthDateVal, $role, $active]);
                set_flash('success', 'تم إضافة المستخدم بنجاح');
                redirect('khadam.php');
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
<title><?= $isEdit ? 'تعديل مستخدم' : 'إضافة خادم / أدمن' ?> - فريق الأنبا كاراس</title>
<link rel="icon" type="image/png" href="../assets/favicon.png">
<link rel="stylesheet" href="../assets/style.css?v=2">
</head>
<body>
<div class="topbar">
  <a href="index.php" class="brand"><img src="../assets/logo.png" alt="لوجو فريق الأنبا كاراس" class="logo"> فريق الأنبا كاراس</a>
  <div class="who">
    <span><?= h(current_user()['name']) ?> (أدمن)</span>
    <a href="../logout.php" class="btn secondary">خروج</a>
  </div>
</div>

<main style="max-width:560px;">
  <div class="section-title"><?= $isEdit ? 'تعديل مستخدم' : 'إضافة خادم / أدمن جديد' ?></div>

  <div class="card-box">
    <?php if ($error): ?>
      <div class="error-msg"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>

      <div class="field">
        <label>الاسم</label>
        <input type="text" name="name" value="<?= h($u['name']) ?>" required autofocus>
      </div>
      <div class="field">
        <label>اسم المستخدم</label>
        <input type="text" name="username" value="<?= h($u['username']) ?>" required>
      </div>
      <div class="field">
        <label><?= $isEdit ? 'كلمة مرور جديدة (سيبها فاضية لو مش هتغيّرها)' : 'كلمة المرور' ?></label>
        <input type="password" name="password" <?= $isEdit ? '' : 'required' ?>>
      </div>
      <div class="field">
        <label>رقم الموبايل</label>
        <input type="text" name="phone" value="<?= h($u['phone']) ?>" placeholder="01xxxxxxxxx" required>
      </div>
      <div class="field">
        <label>العنوان</label>
        <input type="text" name="address" value="<?= h($u['address'] ?? '') ?>">
      </div>
      <div class="field">
        <label>اللجنة</label>
        <input type="text" name="lagna" value="<?= h($u['lagna'] ?? '') ?>">
      </div>
      <div class="field">
        <label>تاريخ الميلاد</label>
        <input type="date" name="birth_date" value="<?= h($u['birth_date'] ?? '') ?>">
      </div>
      <div class="field">
        <label>الدور</label>
        <select name="role">
          <option value="khadem" <?= $u['role'] === 'khadem' ? 'selected' : '' ?>>خادم</option>
          <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>أدمن</option>
        </select>
      </div>
      <div class="field checkbox-row">
        <input type="checkbox" name="active" id="active" <?= $u['active'] ? 'checked' : '' ?>>
        <label for="active" style="margin:0;">حساب نشط</label>
      </div>

      <div class="actions">
        <a href="khadam.php" class="btn secondary">إلغاء</a>
        <button type="submit"><?= $isEdit ? 'حفظ' : 'إضافة' ?></button>
      </div>
    </form>
  </div>
</main>
</body>
</html>
