<?php
require 'includes/config.php';
require 'includes/functions.php';
require_login();

$user = current_user();
if ($user['role'] === 'admin') {
    redirect('admin/index.php');
}

$upcoming = get_upcoming_birthdays($pdo, $user['id'], 7);

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>أعياد الميلاد - فريق الأنبا كاراس</title>
<link rel="icon" type="image/png" href="assets/favicon.png">
<link rel="stylesheet" href="assets/style.css?v=2">
</head>
<body>
<div class="topbar">
  <a href="khadem.php" class="brand"><img src="assets/logo.png" alt="لوجو فريق الأنبا كاراس" class="logo"> فريق الأنبا كاراس</a>
  <div class="who">
    <span><?= h($user['name']) ?></span>
    <a href="logout.php" class="btn secondary">خروج</a>
  </div>
</div>

<main>
  <div class="section-title">🎂 أعياد الميلاد في الأسبوع الجاي</div>

  <p style="color:var(--muted); font-size:.9rem; margin-top:-8px;">
    <a href="khadem.php">← رجوع لقائمة مخدوميّ</a>
  </p>

  <?php if (count($upcoming) === 0): ?>
    <div class="empty">مفيش أعياد ميلاد لمخدوميك في الأسبوع الجاي</div>
  <?php else: ?>
    <div class="list">
      <?php foreach ($upcoming as $m): ?>
        <a href="mokhdoom.php?id=<?= $m['id'] ?>" class="person-card">
          <div class="info">
            <div class="name">🎉 <?= h($m['name']) ?></div>
            <div class="meta">
              <span><?= fmt_date($m['next_birthday']->format('Y-m-d')) ?></span>
              <span>هيكمل <?= $m['turning_age'] ?> سنة</span>
              <?php if ($m['lagna']): ?><span><?= h($m['lagna']) ?></span><?php endif; ?>
              <?php if ($m['phone']): ?><span><?= h($m['phone']) ?></span><?php endif; ?>
            </div>
          </div>
          <?php if ($m['days_left'] === 0): ?>
            <span class="badge ok">النهارده!</span>
          <?php elseif ($m['days_left'] === 1): ?>
            <span class="badge warn">بكرة</span>
          <?php else: ?>
            <span class="badge neutral">بعد <?= $m['days_left'] ?> يوم</span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>
</body>
</html>
