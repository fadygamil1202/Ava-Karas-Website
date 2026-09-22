<?php
require '../includes/config.php';
require '../includes/functions.php';
require_admin();

// إحصائيات سريعة
$totalMokhdomeen = $pdo->query("SELECT COUNT(*) c FROM mokhdomeen WHERE active = 1")->fetch()['c'];
$totalKhadam     = $pdo->query("SELECT COUNT(*) c FROM users WHERE role = 'khadem' AND status = 'approved' AND active = 1")->fetch()['c'];
$unassigned      = $pdo->query("SELECT COUNT(*) c FROM mokhdomeen WHERE active = 1 AND khadem_id IS NULL")->fetch()['c'];
$neverVisited    = $pdo->query("SELECT COUNT(*) c FROM mokhdomeen m WHERE m.active = 1 AND NOT EXISTS (SELECT 1 FROM visits v WHERE v.mokhdoom_id = m.id)")->fetch()['c'];
$notVisited30    = $pdo->query("
    SELECT COUNT(*) c FROM mokhdomeen m WHERE m.active = 1 AND (
      (SELECT MAX(visit_date) FROM visits v WHERE v.mokhdoom_id = m.id) IS NULL
      OR DATEDIFF(CURDATE(), (SELECT MAX(visit_date) FROM visits v WHERE v.mokhdoom_id = m.id)) > 30
    )
")->fetch()['c'];
$visitsThisMonth = $pdo->query("SELECT COUNT(*) c FROM visits WHERE YEAR(visit_date) = YEAR(CURDATE()) AND MONTH(visit_date) = MONTH(CURDATE())")->fetch()['c'];
$pendingCount    = pending_actions_count($pdo);

// كل المخدومين مع آخر افتقاد وعدد الأيام، مرتبين بالأكثر تأخرًا الأول
$q = trim($_GET['q'] ?? '');
$sql = "
  SELECT m.*, u.name AS khadem_name,
    (SELECT MAX(visit_date) FROM visits v WHERE v.mokhdoom_id = m.id) AS last_visit_date
  FROM mokhdomeen m
  LEFT JOIN users u ON u.id = m.khadem_id
  WHERE m.active = 1
";
$params = [];
if ($q !== '') {
    $sql .= " AND (m.name LIKE ? OR m.lagna LIKE ? OR u.name LIKE ?)";
    $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}
$sql .= " ORDER BY (last_visit_date IS NULL) DESC, last_visit_date ASC, m.name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$allMokhdomeen = $stmt->fetchAll();

// أداء الخدام: عدد المخدومين، افتقادات الشهر ده، نوعها، ونسبة المخدومين اللي اتافتقدوا الشهر ده
$khadamList = $pdo->query("
    SELECT id, name FROM users WHERE role = 'khadem' AND status = 'approved' AND active = 1 ORDER BY name
")->fetchAll();

$assignedStmt = $pdo->prepare("SELECT COUNT(*) c FROM mokhdomeen WHERE khadem_id = ? AND active = 1");
$typesStmt = $pdo->prepare("
    SELECT status, COUNT(*) c FROM visits
    WHERE khadem_id = ? AND YEAR(visit_date) = YEAR(CURDATE()) AND MONTH(visit_date) = MONTH(CURDATE())
    GROUP BY status
");
$distinctStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT mokhdoom_id) c FROM visits
    WHERE khadem_id = ? AND YEAR(visit_date) = YEAR(CURDATE()) AND MONTH(visit_date) = MONTH(CURDATE())
");

$khadamStats = [];
foreach ($khadamList as $k) {
    $assignedStmt->execute([$k['id']]);
    $assigned = (int)$assignedStmt->fetch()['c'];

    $typesStmt->execute([$k['id']]);
    $types = $typesStmt->fetchAll();
    $visitsCount = array_sum(array_column($types, 'c'));

    $distinctStmt->execute([$k['id']]);
    $distinctVisited = (int)$distinctStmt->fetch()['c'];

    $percentage = $assigned > 0 ? round(($distinctVisited / $assigned) * 100) : null;

    $khadamStats[] = [
        'name'       => $k['name'],
        'assigned'   => $assigned,
        'visits'     => $visitsCount,
        'types'      => $types,
        'percentage' => $percentage,
    ];
}

// نسبة حضور الاجتماع الأسبوعي: لكل خادم ولكل مخدوم، من إجمالي الاجتماعات المسجّلة
$totalMeetings = (int)$pdo->query("SELECT COUNT(*) c FROM meetings")->fetch()['c'];

$khademAttendanceStats = [];
if ($totalMeetings > 0) {
    $rows = $pdo->query("
        SELECT u.id, u.name,
          COUNT(a.id) AS recorded,
          SUM(a.status = 'حاضر') AS present
        FROM users u
        LEFT JOIN attendance a ON a.person_id = u.id AND a.person_type = 'khadem'
        WHERE u.role = 'khadem' AND u.status = 'approved' AND u.active = 1
        GROUP BY u.id, u.name
        ORDER BY u.name
    ")->fetchAll();
    foreach ($rows as $r) {
        $recorded = (int)$r['recorded'];
        $khademAttendanceStats[] = [
            'name'       => $r['name'],
            'present'    => (int)$r['present'],
            'recorded'   => $recorded,
            'percentage' => $recorded > 0 ? round(((int)$r['present'] / $recorded) * 100) : null,
        ];
    }
}

$mokhdoomAttendanceStats = [];
if ($totalMeetings > 0) {
    $rows = $pdo->query("
        SELECT m.id, m.name, u.name AS khadem_name,
          COUNT(a.id) AS recorded,
          SUM(a.status = 'حاضر') AS present
        FROM mokhdomeen m
        LEFT JOIN users u ON u.id = m.khadem_id
        LEFT JOIN attendance a ON a.person_id = m.id AND a.person_type = 'mokhdoom'
        WHERE m.active = 1
        GROUP BY m.id, m.name, u.name
        ORDER BY m.name
    ")->fetchAll();
    foreach ($rows as $r) {
        $recorded = (int)$r['recorded'];
        $mokhdoomAttendanceStats[] = [
            'name'        => $r['name'],
            'khadem_name' => $r['khadem_name'],
            'present'     => (int)$r['present'],
            'recorded'    => $recorded,
            'percentage'  => $recorded > 0 ? round(((int)$r['present'] / $recorded) * 100) : null,
        ];
    }
}

// شارة نسبة موحّدة بنفس ألوان الشارات المستخدمة في الصفحة دي
function attendance_pct_badge($pct) {
    if ($pct === null) return '<span class="badge neutral">لا يوجد تسجيل حضور</span>';
    if ($pct >= 70) return "<span class=\"badge ok\">$pct%</span>";
    if ($pct >= 30) return "<span class=\"badge warn\">$pct%</span>";
    return "<span class=\"badge danger\">$pct%</span>";
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>لوحة التحكم - فريق الأنبا كاراس</title>
<link rel="icon" type="image/png" href="../assets/favicon.png">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="topbar">
  <a href="index.php" class="brand"><img src="../assets/logo.png" alt="لوجو فريق الأنبا كاراس" class="logo"> فريق الأنبا كاراس</a>
  <div class="tabs">
    <a href="index.php" class="tab active">نظرة عامة</a>
    <a href="mokhdomeen.php" class="tab">المخدومين</a>
    <a href="khadam.php" class="tab">الخدام</a>
    <a href="assign.php" class="tab">التوزيع</a>
    <a href="requests.php" class="tab">طلبات التسجيل <?php if ($pendingCount): ?><span class="count"><?= $pendingCount ?></span><?php endif; ?></a>
  </div>
  <div class="who">
    <span><?= h(current_user()['name']) ?> (أدمن)</span>
    <a href="../logout.php" class="btn secondary">خروج</a>
  </div>
</div>

<main>
  <div class="section-title">نظرة عامة</div>
  <div class="cards-row">
    <div class="stat-card"><div class="num"><?= $totalMokhdomeen ?></div><div class="lbl">إجمالي المخدومين</div></div>
    <div class="stat-card"><div class="num"><?= $totalKhadam ?></div><div class="lbl">عدد الخدام</div></div>
    <div class="stat-card"><div class="num"><?= $unassigned ?></div><div class="lbl">مخدومين بدون خادم</div></div>
    <div class="stat-card"><div class="num"><?= $neverVisited ?></div><div class="lbl">لم يُفتقدوا أبدًا</div></div>
    <div class="stat-card"><div class="num"><?= $notVisited30 ?></div><div class="lbl">تأخر افتقادهم +30 يوم</div></div>
    <div class="stat-card"><div class="num"><?= $visitsThisMonth ?></div><div class="lbl">افتقادات هذا الشهر</div></div>
  </div>

  <?php if ($pendingCount > 0): ?>
    <div class="section-title">تنبيه</div>
    <a href="requests.php" class="card-box" style="display:block; text-decoration:none; color:inherit; border-color:var(--warn);">
      فيه <strong><?= $pendingCount ?></strong> طلب محتاج مراجعتك (تسجيل جديد أو استعادة باسورد) ← اضغط هنا
    </a>
  <?php endif; ?>

  <div class="section-title">كل المخدومين — مرتبين بالأكثر تأخرًا في الافتقاد</div>

  <form method="get" class="toolbar">
    <input type="text" name="q" placeholder="بحث بالاسم أو اللجنة أو الخادم..." value="<?= h($q) ?>">
    <button type="submit" class="secondary">بحث</button>
    <?php if ($q !== ''): ?><a href="index.php" class="btn secondary">مسح البحث</a><?php endif; ?>
  </form>

  <?php if (count($allMokhdomeen) === 0): ?>
    <div class="empty">مفيش مخدومين مطابقين</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>الاسم</th><th>اللجنة</th><th>الخادم</th><th>آخر افتقاد</th><th>منذ كام يوم</th></tr>
        </thead>
        <tbody>
          <?php foreach ($allMokhdomeen as $m): ?>
            <tr style="cursor:pointer;" onclick="location.href='../mokhdoom.php?id=<?= $m['id'] ?>'">
              <td><?= h($m['name']) ?></td>
              <td><?= h($m['lagna'] ?: '-') ?></td>
              <td><?= $m['khadem_name'] ? h($m['khadem_name']) : '<span class="badge neutral">بدون خادم</span>' ?></td>
              <td><?= $m['last_visit_date'] ? fmt_date($m['last_visit_date']) : '-' ?></td>
              <td><?= visit_badge($m['last_visit_date']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <div class="section-title">أداء الخدام — الشهر الحالي</div>

  <?php if (count($khadamStats) === 0): ?>
    <div class="empty">مفيش خدام معتمدين حاليًا</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>الخادم</th><th>عدد المخدومين</th><th>افتقادات الشهر</th><th>نوعها</th><th>نسبة المُفتقدين</th></tr>
        </thead>
        <tbody>
          <?php foreach ($khadamStats as $ks): ?>
            <tr>
              <td><?= h($ks['name']) ?></td>
              <td><?= $ks['assigned'] ?></td>
              <td><?= $ks['visits'] ?></td>
              <td>
                <?php if (empty($ks['types'])): ?>
                  <span class="badge neutral">لا يوجد</span>
                <?php else: ?>
                  <?php foreach ($ks['types'] as $t): ?>
                    <span class="badge neutral"><?= h($t['status']) ?> ×<?= $t['c'] ?></span>
                  <?php endforeach; ?>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($ks['percentage'] === null): ?>
                  <span class="badge neutral">لا يوجد مخدومين</span>
                <?php elseif ($ks['percentage'] >= 70): ?>
                  <span class="badge ok"><?= $ks['percentage'] ?>%</span>
                <?php elseif ($ks['percentage'] >= 30): ?>
                  <span class="badge warn"><?= $ks['percentage'] ?>%</span>
                <?php else: ?>
                  <span class="badge danger"><?= $ks['percentage'] ?>%</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <div class="section-title">نسبة حضور الاجتماع الأسبوعي — الخدام</div>

  <?php if ($totalMeetings === 0): ?>
    <div class="empty">لسه مفيش اجتماعات متسجلة (ارفع حضور اجتماع من صفحة "رفع الحضور")</div>
  <?php elseif (count($khademAttendanceStats) === 0): ?>
    <div class="empty">مفيش خدام معتمدين حاليًا</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>الخادم</th><th>عدد الاجتماعات المسجّل له حضور فيها</th><th>عدد مرات الحضور</th><th>نسبة الحضور</th></tr>
        </thead>
        <tbody>
          <?php foreach ($khademAttendanceStats as $ks): ?>
            <tr>
              <td><?= h($ks['name']) ?></td>
              <td><?= $ks['recorded'] ?></td>
              <td><?= $ks['present'] ?></td>
              <td><?= attendance_pct_badge($ks['percentage']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <div class="section-title">نسبة حضور الاجتماع الأسبوعي — المخدومين</div>

  <?php if ($totalMeetings === 0): ?>
    <div class="empty">لسه مفيش اجتماعات متسجلة (ارفع حضور اجتماع من صفحة "رفع الحضور")</div>
  <?php elseif (count($mokhdoomAttendanceStats) === 0): ?>
    <div class="empty">مفيش مخدومين حاليًا</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>المخدوم</th><th>الخادم</th><th>عدد الاجتماعات المسجّل له حضور فيها</th><th>عدد مرات الحضور</th><th>نسبة الحضور</th></tr>
        </thead>
        <tbody>
          <?php foreach ($mokhdoomAttendanceStats as $ms): ?>
            <tr>
              <td><?= h($ms['name']) ?></td>
              <td><?= $ms['khadem_name'] ? h($ms['khadem_name']) : '<span class="badge neutral">بدون خادم</span>' ?></td>
              <td><?= $ms['recorded'] ?></td>
              <td><?= $ms['present'] ?></td>
              <td><?= attendance_pct_badge($ms['percentage']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</main>
</body>
</html>
