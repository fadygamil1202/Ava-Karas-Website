<?php
require '../includes/config.php';
require '../includes/functions.php';
require_admin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
$isEdit = $id > 0;
$error = '';

// جلب المخدوم الحالي لو تعديل
$mokhdoom = [
    'name' => '', 'phone' => '', 'address' => '', 'hobby' => '', 'birth_date' => '', 'lagna' => '', 'notes' => '', 'khadem_id' => null,
];
if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM mokhdomeen WHERE id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) {
        set_flash('error', 'المخدوم غير موجود');
        redirect('mokhdomeen.php');
    }
    $mokhdoom = $found;
}

// قائمة الخدام لاختيار من عليهم
$khadam = $pdo->query("SELECT id, name FROM users WHERE role = 'khadem' AND status = 'approved' AND active = 1 ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = trim($_POST['name'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $address   = trim($_POST['address'] ?? '');
    $hobby     = trim($_POST['hobby'] ?? '');
    $birthDate = trim($_POST['birth_date'] ?? '');
    $lagna     = trim($_POST['lagna'] ?? '');
    $notes     = trim($_POST['notes'] ?? '');
    $khadem_id = $_POST['khadem_id'] !== '' ? (int)$_POST['khadem_id'] : null;

    // نحتفظ بالقيم المُدخلة لو حصل خطأ عشان الفورم متتصفرش
    $mokhdoom = compact('name', 'phone', 'address', 'hobby', 'birth_date', 'lagna', 'notes', 'khadem_id');

    if ($name === '') {
        $error = 'اسم المخدوم مطلوب';
    } elseif ($birthDate !== '' && !strtotime($birthDate)) {
        $error = 'تاريخ الميلاد غير صحيح';
    } else {
        $birthDateVal = $birthDate !== '' ? date('Y-m-d', strtotime($birthDate)) : null;
        if ($isEdit) {
            $stmt = $pdo->prepare(
                'UPDATE mokhdomeen SET name=?, phone=?, address=?, hobby=?, birth_date=?, lagna=?, notes=?, khadem_id=? WHERE id=?'
            );
            $stmt->execute([$name, $phone ?: null, $address ?: null, $hobby ?: null, $birthDateVal, $lagna ?: null, $notes ?: null, $khadem_id, $id]);
            set_flash('success', 'تم حفظ التعديلات بنجاح');
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO mokhdomeen (name, phone, address, hobby, birth_date, lagna, notes, khadem_id) VALUES (?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([$name, $phone ?: null, $address ?: null, $hobby ?: null, $birthDateVal, $lagna ?: null, $notes ?: null, $khadem_id]);
            set_flash('success', 'تم إضافة المخدوم بنجاح');
        }
        redirect('mokhdomeen.php');
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $isEdit ? 'تعديل مخدوم' : 'إضافة مخدوم' ?> - فريق الأنبا كاراس</title>
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
  <div class="section-title"><?= $isEdit ? 'تعديل بيانات مخدوم' : 'إضافة مخدوم جديد' ?></div>

  <div class="card-box">
    <?php if ($error): ?>
      <div class="error-msg"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>

      <div class="field">
        <label>الاسم</label>
        <input type="text" name="name" value="<?= h($mokhdoom['name']) ?>" required autofocus>
      </div>
      <div class="field">
        <label>رقم الموبايل</label>
        <input type="text" name="phone" value="<?= h($mokhdoom['phone']) ?>">
      </div>
      <div class="field">
        <label>العنوان</label>
        <input type="text" name="address" value="<?= h($mokhdoom['address']) ?>">
      </div>
      <div class="field">
        <label>الهواية</label>
        <input type="text" name="hobby" value="<?= h($mokhdoom['hobby']) ?>">
      </div>
      <div class="field">
        <label>تاريخ الميلاد</label>
        <input type="date" name="birth_date" value="<?= h($mokhdoom['birth_date'] ?? '') ?>">
      </div>
      <div class="field">
        <label>اللجنة</label>
        <input type="text" name="lagna" value="<?= h($mokhdoom['lagna']) ?>">
      </div>
      <div class="field">
        <label>الخادم المسؤول</label>
        <select name="khadem_id">
          <option value="">بدون خادم</option>
          <?php foreach ($khadam as $k): ?>
            <option value="<?= $k['id'] ?>" <?= (string)$mokhdoom['khadem_id'] === (string)$k['id'] ? 'selected' : '' ?>>
              <?= h($k['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>ملاحظات</label>
        <textarea name="notes" rows="3"><?= h($mokhdoom['notes']) ?></textarea>
      </div>

      <div class="actions">
        <a href="mokhdomeen.php" class="btn secondary">إلغاء</a>
        <button type="submit"><?= $isEdit ? 'حفظ التعديلات' : 'إضافة' ?></button>
      </div>
    </form>
  </div>
</main>
</body>
</html>
