<?php
require 'includes/config.php';
require 'includes/functions.php';
require_login();

$user = current_user();
if ($user['role'] === 'admin') {
    redirect('admin/index.php');
}

$q = trim($_GET['q'] ?? '');

$sql = "
  SELECT m.*,
    (SELECT MAX(visit_date) FROM visits v WHERE v.mokhdoom_id = m.id) AS last_visit_date,
    (SELECT COUNT(*) FROM visits v WHERE v.mokhdoom_id = m.id) AS visits_count
  FROM mokhdomeen m
  WHERE m.khadem_id = ? AND m.active = 1
";
$params = [$user['id']];
if ($q !== '') {
    $sql .= " AND (m.name LIKE ? OR m.lagna LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
$sql .= " ORDER BY (last_visit_date IS NULL) DESC, last_visit_date ASC, m.name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$mokhdomeen = $stmt->fetchAll();

// إحصائيات (على كل مخدومي الخادم، مش بس نتيجة البحث)
$allStmt = $pdo->prepare("
  SELECT m.id, (SELECT MAX(visit_date) FROM visits v WHERE v.mokhdoom_id = m.id) AS last_visit_date
  FROM mokhdomeen m WHERE m.khadem_id = ? AND m.active = 1
");
$allStmt->execute([$user['id']]);
$all = $allStmt->fetchAll();
$total = count($all);
$never = count(array_filter($all, fn($m) => !$m['last_visit_date']));
$over30 = count(array_filter($all, fn($m) => $m['last_visit_date'] && days_since($m['last_visit_date']) > 30));

$needsVisitationIds = get_needs_visitation_ids($pdo);
$needsVisitationCount = count(array_intersect_key($needsVisitationIds, array_flip(array_column($all, 'id'))));

$upcomingBirthdaysCount = count(get_upcoming_birthdays($pdo, $user['id'], 7));

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>مخدوميّ - فريق الأنبا كاراس</title>
<link rel="icon" type="image/png" href="assets/favicon.png">
<link rel="stylesheet" href="assets/style.css?v=2">
</head>
<body>
<div class="topbar">
  <a href="khadem.php" class="brand"><img src="assets/logo.png" alt="لوجو فريق الأنبا كاراس" class="logo"> فريق الأنبا كاراس</a>
  <div class="who">
    <?php if (can_manage_attendance()): ?>
      <a href="admin/attendance_upload.php" class="btn secondary">رفع الحضور</a>
    <?php endif; ?>
    <a href="birthdays.php" class="btn secondary">
      🎂 أعياد الميلاد<?php if ($upcomingBirthdaysCount): ?> <span class="count"><?= $upcomingBirthdaysCount ?></span><?php endif; ?>
    </a>
    <span><?= h($user['name']) ?></span>
    <a href="logout.php" class="btn secondary">خروج</a>
  </div>
</div>

<main>
  <?php if ($flash): ?>
    <div class="<?= $flash['type'] === 'success' ? 'success-msg' : 'error-msg' ?>"><?= h($flash['message']) ?></div>
  <?php endif; ?>

  <div class="cards-row">
    <div class="stat-card"><div class="num"><?= $total ?></div><div class="lbl">إجمالي المخدومين</div></div>
    <div class="stat-card"><div class="num"><?= $never ?></div><div class="lbl">لم يُفتقدوا أبدًا</div></div>
    <div class="stat-card"><div class="num"><?= $over30 ?></div><div class="lbl">تأخر افتقادهم +30 يوم</div></div>
    <div class="stat-card"><div class="num"><?= $needsVisitationCount ?></div><div class="lbl">محتاج افتقاد (غياب متكرر)</div></div>
  </div>

  <form method="get" class="toolbar">
    <input type="text" name="q" placeholder="بحث بالاسم أو اللجنة..." value="<?= h($q) ?>">
    <button type="submit" class="secondary">بحث</button>
    <?php if ($q !== ''): ?><a href="khadem.php" class="btn secondary">مسح البحث</a><?php endif; ?>
  </form>

  <?php if (count($mokhdomeen) === 0): ?>
    <div class="empty"><?= $q !== '' ? 'مفيش مخدومين مطابقين' : 'مفيش مخدومين متسندين لك حاليًا' ?></div>
  <?php else: ?>
    <div class="list">
      <?php foreach ($mokhdomeen as $m): ?>
        <a href="mokhdoom.php?id=<?= $m['id'] ?>" class="person-card">
          <div class="info">
            <div class="name"><?= h($m['name']) ?></div>
            <div class="meta">
              <?php if ($m['age']): ?><span><?= h($m['age']) ?> سنة</span><?php endif; ?>
              <?php if ($m['lagna']): ?><span><?= h($m['lagna']) ?></span><?php endif; ?>
              <?php if ($m['phone']): ?><span><?= h($m['phone']) ?></span><?php endif; ?>
            </div>
          </div>
          <div style="display:flex; flex-direction:column; gap:4px; align-items:flex-end;">
            <?= visit_badge($m['last_visit_date']) ?>
            <?php if (isset($needsVisitationIds[$m['id']])): ?>
              <span class="badge danger">⚠ محتاج افتقاد</span>
            <?php endif; ?>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>
</body>
</html>
