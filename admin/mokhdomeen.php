<?php
require '../includes/config.php';
require '../includes/functions.php';
require_admin();

// حذف مخدوم (حذف ناعم - بيتشال من القايمة بس بيفضل محفوظ في القاعدة)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $pdo->prepare('UPDATE mokhdomeen SET active = 0 WHERE id = ?')->execute([$id]);
    set_flash('success', 'تم حذف المخدوم بنجاح');
    redirect('mokhdomeen.php');
}

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
    $sql .= " AND (m.name LIKE ? OR m.lagna LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
$sql .= " ORDER BY m.name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$mokhdomeen = $stmt->fetchAll();

$pendingCount = pending_actions_count($pdo);
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>إدارة المخدومين - فريق الأنبا كاراس</title>
<link rel="icon" type="image/png" href="../assets/favicon.png">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="topbar">
  <a href="index.php" class="brand"><img src="../assets/logo.png" alt="لوجو فريق الأنبا كاراس" class="logo"> فريق الأنبا كاراس</a>
  <div class="tabs">
    <a href="index.php" class="tab">نظرة عامة</a>
    <a href="mokhdomeen.php" class="tab active">المخدومين</a>
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
  <div class="section-title">إدارة المخدومين (<?= count($mokhdomeen) ?>)</div>

  <?php if ($flash): ?>
    <div class="<?= $flash['type'] === 'success' ? 'success-msg' : 'error-msg' ?>"><?= h($flash['message']) ?></div>
  <?php endif; ?>

  <form method="get" class="toolbar">
    <input type="text" name="q" placeholder="بحث بالاسم أو اللجنة..." value="<?= h($q) ?>">
    <button type="submit" class="secondary">بحث</button>
    <?php if ($q !== ''): ?><a href="mokhdomeen.php" class="btn secondary">مسح البحث</a><?php endif; ?>
    <div class="spacer"></div>
    <a href="mokhdomeen_import.php" class="btn secondary">استيراد CSV</a>
    <a href="mokhdoom_form.php" class="btn">إضافة مخدوم</a>
  </form>

  <?php if (count($mokhdomeen) === 0): ?>
    <div class="empty">مفيش مخدومين مطابقين</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>الاسم</th><th>السن</th><th>اللجنة</th><th>الموبايل</th><th>الخادم</th><th>آخر افتقاد</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($mokhdomeen as $m): ?>
            <tr>
              <td><?= h($m['name']) ?></td>
              <td><?= $m['age'] ? h($m['age']) : '-' ?></td>
              <td><?= h($m['lagna'] ?: '-') ?></td>
              <td><?= h($m['phone'] ?: '-') ?></td>
              <td><?= $m['khadem_name'] ? h($m['khadem_name']) : '<span class="badge neutral">بدون خادم</span>' ?></td>
              <td><?= $m['last_visit_date'] ? fmt_date($m['last_visit_date']) : '<span class="badge danger">لم يُفتقد</span>' ?></td>
              <td style="white-space:nowrap;">
                <a href="mokhdoom_form.php?id=<?= $m['id'] ?>" class="btn secondary">تعديل</a>
                <form method="post" style="display:inline;" onsubmit="return confirm('حذف <?= h(addslashes($m['name'])) ?>؟');">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $m['id'] ?>">
                  <button type="submit" class="danger">حذف</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</main>
</body>
</html>
