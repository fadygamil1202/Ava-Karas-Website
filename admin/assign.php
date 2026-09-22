<?php
require '../includes/config.php';
require '../includes/functions.php';
require_admin();

$khadam = $pdo->query("SELECT id, name FROM users WHERE role = 'khadem' AND status = 'approved' AND active = 1 ORDER BY name")->fetchAll();

// معالجة الإسناد الجماعي
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids = array_map('intval', $_POST['mokhdoom_ids'] ?? []);
    $target = $_POST['khadem_target'] ?? '';

    if (empty($ids)) {
        set_flash('error', 'اختار مخدومين الأول');
    } elseif ($target === '') {
        set_flash('error', 'اختار خادم أو إلغاء التعيين');
    } else {
        $khademId = $target === 'unassign' ? null : (int)$target;
        $stmt = $pdo->prepare('UPDATE mokhdomeen SET khadem_id = ? WHERE id = ?');
        foreach ($ids as $id) {
            $stmt->execute([$khademId, $id]);
        }
        set_flash('success', 'تم تحديث ' . count($ids) . ' مخدوم بنجاح');
    }
    // نحافظ على نفس فلاتر البحث بعد الحفظ
    $qs = http_build_query(['q' => $_POST['q'] ?? '', 'filter' => $_POST['filter'] ?? '']);
    redirect('assign.php?' . $qs);
}

$q = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? '';

$sql = "
  SELECT m.*, u.name AS khadem_name
  FROM mokhdomeen m
  LEFT JOIN users u ON u.id = m.khadem_id
  WHERE m.active = 1
";
$params = [];
if ($q !== '') {
    $sql .= " AND m.name LIKE ?";
    $params[] = "%$q%";
}
if ($filter === 'unassigned') {
    $sql .= " AND m.khadem_id IS NULL";
} elseif ($filter !== '') {
    $sql .= " AND m.khadem_id = ?";
    $params[] = (int)$filter;
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
<title>توزيع المخدومين - فريق الأنبا كاراس</title>
<link rel="icon" type="image/png" href="../assets/favicon.png">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="topbar">
  <a href="index.php" class="brand"><img src="../assets/logo.png" alt="لوجو فريق الأنبا كاراس" class="logo"> فريق الأنبا كاراس</a>
  <div class="tabs">
    <a href="index.php" class="tab">نظرة عامة</a>
    <a href="mokhdomeen.php" class="tab">المخدومين</a>
    <a href="khadam.php" class="tab">الخدام</a>
    <a href="assign.php" class="tab active">التوزيع</a>
    <a href="requests.php" class="tab">طلبات التسجيل <?php if ($pendingCount): ?><span class="count"><?= $pendingCount ?></span><?php endif; ?></a>
  </div>
  <div class="who">
    <span><?= h(current_user()['name']) ?> (أدمن)</span>
    <a href="../logout.php" class="btn secondary">خروج</a>
  </div>
</div>

<main>
  <div class="section-title">توزيع المخدومين على الخدام</div>

  <?php if ($flash): ?>
    <div class="<?= $flash['type'] === 'success' ? 'success-msg' : 'error-msg' ?>"><?= h($flash['message']) ?></div>
  <?php endif; ?>

  <form method="get" class="toolbar">
    <input type="text" name="q" placeholder="بحث بالاسم..." value="<?= h($q) ?>">
    <select name="filter" onchange="this.form.submit()">
      <option value="">كل المخدومين</option>
      <option value="unassigned" <?= $filter === 'unassigned' ? 'selected' : '' ?>>بدون خادم فقط</option>
      <?php foreach ($khadam as $k): ?>
        <option value="<?= $k['id'] ?>" <?= $filter === (string)$k['id'] ? 'selected' : '' ?>><?= h($k['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="secondary">بحث</button>
  </form>

  <?php if (count($mokhdomeen) === 0): ?>
    <div class="empty">مفيش مخدومين مطابقين</div>
  <?php else: ?>
    <form method="post" id="assignForm">
      <input type="hidden" name="q" value="<?= h($q) ?>">
      <input type="hidden" name="filter" value="<?= h($filter) ?>">

      <div class="toolbar">
        <select name="khadem_target" required>
          <option value="" disabled selected>اختر خادم لإسناد المحدد...</option>
          <option value="unassign">-- إلغاء التعيين --</option>
          <?php foreach ($khadam as $k): ?>
            <option value="<?= $k['id'] ?>"><?= h($k['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" onclick="return confirm('تأكيد إسناد المخدومين المحددين؟');">إسناد المحدد</button>
        <div class="spacer"></div>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr><th><input type="checkbox" id="checkAll"></th><th>الاسم</th><th>اللجنة</th><th>الخادم الحالي</th></tr>
          </thead>
          <tbody>
            <?php foreach ($mokhdomeen as $m): ?>
              <tr>
                <td><input type="checkbox" name="mokhdoom_ids[]" value="<?= $m['id'] ?>" class="rowChk"></td>
                <td><?= h($m['name']) ?></td>
                <td><?= h($m['lagna'] ?: '-') ?></td>
                <td><?= $m['khadem_name'] ? h($m['khadem_name']) : '<span class="badge neutral">بدون خادم</span>' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </form>
  <?php endif; ?>
</main>

<script>
  var checkAll = document.getElementById('checkAll');
  if (checkAll) {
    checkAll.addEventListener('change', function () {
      document.querySelectorAll('.rowChk').forEach(function (c) { c.checked = checkAll.checked; });
    });
  }
</script>
</body>
</html>
