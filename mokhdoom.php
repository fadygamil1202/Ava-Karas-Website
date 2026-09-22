<?php
require 'includes/config.php';
require 'includes/functions.php';
require_login();

$user = current_user();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM mokhdomeen WHERE id = ? AND active = 1');
$stmt->execute([$id]);
$mokhdoom = $stmt->fetch();

if (!$mokhdoom) {
    set_flash('error', 'المخدوم غير موجود');
    redirect($user['role'] === 'admin' ? 'admin/mokhdomeen.php' : 'khadem.php');
}

// صلاحية العرض: الأدمن يشوف أي حد، الخادم يشوف مخدوميه بس
$canAccess = ($user['role'] === 'admin') || ((int)$mokhdoom['khadem_id'] === (int)$user['id']);
if (!$canAccess) {
    http_response_code(403);
    die('غير مصرح لك بعرض هذا المخدوم.');
}

$backUrl = $user['role'] === 'admin' ? 'admin/mokhdomeen.php' : 'khadem.php';

// إضافة افتقاد جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_visit') {
    $visit_date = $_POST['visit_date'] ?? '';
    $status = trim($_POST['status'] ?? 'تم الافتقاد');
    $notes = trim($_POST['notes'] ?? '');

    if ($visit_date === '') {
        set_flash('error', 'تاريخ الافتقاد مطلوب');
    } else {
        $stmt = $pdo->prepare('INSERT INTO visits (mokhdoom_id, khadem_id, visit_date, status, notes) VALUES (?,?,?,?,?)');
        $stmt->execute([$mokhdoom['id'], $user['id'], $visit_date, $status, $notes ?: null]);
        set_flash('success', 'تم حفظ الافتقاد بنجاح');
    }
    redirect('mokhdoom.php?id=' . $mokhdoom['id']);
}

// حذف افتقاد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_visit') {
    $visitId = (int)($_POST['visit_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM visits WHERE id = ?');
    $stmt->execute([$visitId]);
    $visit = $stmt->fetch();

    if ($visit && ($user['role'] === 'admin' || (int)$visit['khadem_id'] === (int)$user['id'])) {
        $pdo->prepare('DELETE FROM visits WHERE id = ?')->execute([$visitId]);
        set_flash('success', 'تم حذف الافتقاد');
    } else {
        set_flash('error', 'مش مسموح تحذف هذا الافتقاد');
    }
    redirect('mokhdoom.php?id=' . $mokhdoom['id']);
}

$stmt = $pdo->prepare("
  SELECT v.*, u.name AS khadem_name
  FROM visits v JOIN users u ON u.id = v.khadem_id
  WHERE v.mokhdoom_id = ?
  ORDER BY v.visit_date DESC, v.id DESC
");
$stmt->execute([$mokhdoom['id']]);
$visits = $stmt->fetchAll();

$flash = get_flash();
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($mokhdoom['name']) ?> - فريق الأنبا كاراس</title>
<link rel="icon" type="image/png" href="assets/favicon.png">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="topbar">
  <a href="<?= h($backUrl) ?>" class="brand"><img src="assets/logo.png" alt="لوجو فريق الأنبا كاراس" class="logo"> فريق الأنبا كاراس</a>
  <div class="who">
    <span><?= h($user['name']) ?></span>
    <a href="logout.php" class="btn secondary">خروج</a>
  </div>
</div>

<main style="max-width:640px;">
  <a href="<?= h($backUrl) ?>" class="hint" style="display:inline-block; margin-bottom:14px;">→ رجوع للقائمة</a>

  <div class="card-box">
    <div class="section-title" style="margin-top:0;"><?= h($mokhdoom['name']) ?></div>
    <div class="meta" style="color:var(--muted); font-size:.9rem; line-height:2;">
      <?php if ($mokhdoom['phone']): ?>📞 <?= h($mokhdoom['phone']) ?><br><?php endif; ?>
      <?php if ($mokhdoom['address']): ?>🏠 <?= h($mokhdoom['address']) ?><br><?php endif; ?>
      <?php if ($mokhdoom['hobby']): ?>🎨 <?= h($mokhdoom['hobby']) ?><br><?php endif; ?>
      <?php if ($mokhdoom['lagna']): ?>👥 لجنة: <?= h($mokhdoom['lagna']) ?><br><?php endif; ?>
    </div>

    <?php if ($flash): ?>
      <div class="<?= $flash['type'] === 'success' ? 'success-msg' : 'error-msg' ?>" style="margin-top:14px;"><?= h($flash['message']) ?></div>
    <?php endif; ?>

    <form method="post" style="margin-top:16px;">
      <input type="hidden" name="action" value="add_visit">
      <input type="hidden" name="id" value="<?= $mokhdoom['id'] ?>">

      <div class="field">
        <label>تاريخ الافتقاد</label>
        <input type="date" name="visit_date" value="<?= $today ?>" required>
      </div>
      <div class="field">
        <label>الحالة</label>
        <select name="status">
          <option>تم الافتقاد</option>
          <option>لم يتم الرد</option>
          <option>مكالمة تليفونية</option>
          <option>غير موجود بالمنطقة</option>
        </select>
      </div>
      <div class="field">
        <label>ملاحظات</label>
        <textarea name="notes" rows="3" placeholder="ملاحظات عن الزيارة..."></textarea>
      </div>
      <button class="full" type="submit">حفظ الافتقاد</button>
    </form>
  </div>

  <div class="section-title">سجل الافتقاد</div>
  <?php if (count($visits) === 0): ?>
    <div class="empty">لا يوجد افتقادات مسجلة بعد</div>
  <?php else: ?>
    <div class="list">
      <?php foreach ($visits as $v): ?>
        <div class="visit-item">
          <div>
            <div class="d"><?= fmt_date($v['visit_date']) ?> — <?= h($v['status']) ?></div>
            <?php if ($v['notes']): ?><div class="n"><?= h($v['notes']) ?></div><?php endif; ?>
            <div class="n">بواسطة: <?= h($v['khadem_name']) ?></div>
          </div>
          <?php if ($user['role'] === 'admin' || (int)$v['khadem_id'] === (int)$user['id']): ?>
            <form method="post" onsubmit="return confirm('حذف هذا الافتقاد؟');">
              <input type="hidden" name="action" value="delete_visit">
              <input type="hidden" name="id" value="<?= $mokhdoom['id'] ?>">
              <input type="hidden" name="visit_id" value="<?= $v['id'] ?>">
              <button type="submit" class="small-x">حذف</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>
</body>
</html>
