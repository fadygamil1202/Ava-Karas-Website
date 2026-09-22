<?php
require '../includes/config.php';
require '../includes/functions.php';
require_admin();

// حذف مستخدم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id === current_user()['id']) {
        set_flash('error', 'لا يمكنك حذف حسابك الخاص');
    } else {
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
        set_flash('success', 'تم حذف المستخدم بنجاح');
    }
    redirect('khadam.php');
}

// تفعيل/تعطيل سريع
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_active') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id !== current_user()['id']) {
        $pdo->prepare('UPDATE users SET active = NOT active WHERE id = ?')->execute([$id]);
    }
    redirect('khadam.php');
}

// منح/سحب صلاحية "مسؤول الحضور" (للخدام بس، الأدمن أصلًا عنده كل الصلاحيات)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_attendance_permission') {
    $id = (int)($_POST['id'] ?? 0);
    $pdo->prepare("UPDATE users SET can_manage_attendance = NOT can_manage_attendance WHERE id = ? AND role = 'khadem'")->execute([$id]);
    redirect('khadam.php');
}

$users = $pdo->query("
  SELECT u.*, (SELECT COUNT(*) FROM mokhdomeen m WHERE m.khadem_id = u.id AND m.active = 1) AS mokhdomeen_count
  FROM users u
  WHERE u.status = 'approved'
  ORDER BY u.role DESC, u.name ASC
")->fetchAll();

$pendingCount = pending_actions_count($pdo);
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>إدارة الخدام - فريق الأنبا كاراس</title>
<link rel="icon" type="image/png" href="../assets/favicon.png">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="topbar">
  <a href="index.php" class="brand"><img src="../assets/logo.png" alt="لوجو فريق الأنبا كاراس" class="logo"> فريق الأنبا كاراس</a>
  <div class="tabs">
    <a href="index.php" class="tab">نظرة عامة</a>
    <a href="mokhdomeen.php" class="tab">المخدومين</a>
    <a href="khadam.php" class="tab active">الخدام</a>
    <a href="assign.php" class="tab">التوزيع</a>
    <a href="requests.php" class="tab">طلبات التسجيل <?php if ($pendingCount): ?><span class="count"><?= $pendingCount ?></span><?php endif; ?></a>
  </div>
  <div class="who">
    <span><?= h(current_user()['name']) ?> (أدمن)</span>
    <a href="../logout.php" class="btn secondary">خروج</a>
  </div>
</div>

<main>
  <div class="section-title">إدارة الخدام والمستخدمين (<?= count($users) ?>)</div>

  <?php if ($flash): ?>
    <div class="<?= $flash['type'] === 'success' ? 'success-msg' : 'error-msg' ?>"><?= h($flash['message']) ?></div>
  <?php endif; ?>

  <div class="toolbar">
    <div class="spacer"></div>
    <a href="khadem_form.php" class="btn">إضافة خادم / أدمن</a>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>الاسم</th><th>اسم المستخدم</th><th>الدور</th><th>عدد المخدومين</th><th>الحالة</th><th>مسؤول الحضور</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td><?= h($u['name']) ?></td>
            <td><?= h($u['username']) ?></td>
            <td><?= $u['role'] === 'admin' ? 'أدمن' : 'خادم' ?></td>
            <td><?= $u['role'] === 'khadem' ? $u['mokhdomeen_count'] : '-' ?></td>
            <td>
              <?php if ($u['active']): ?>
                <span class="badge ok">نشط</span>
              <?php else: ?>
                <span class="badge neutral">معطّل</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($u['role'] === 'admin'): ?>
                <span class="badge neutral">-</span>
              <?php else: ?>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="action" value="toggle_attendance_permission">
                  <input type="hidden" name="id" value="<?= $u['id'] ?>">
                  <?php if ($u['can_manage_attendance']): ?>
                    <span class="badge ok">مفعّلة</span>
                    <button type="submit" class="secondary">سحب الصلاحية</button>
                  <?php else: ?>
                    <span class="badge neutral">غير مفعّلة</span>
                    <button type="submit" class="secondary">منح الصلاحية</button>
                  <?php endif; ?>
                </form>
              <?php endif; ?>
            </td>
            <td style="white-space:nowrap;">
              <a href="khadem_form.php?id=<?= $u['id'] ?>" class="btn secondary">تعديل</a>
              <?php if ($u['id'] !== current_user()['id']): ?>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="action" value="toggle_active">
                  <input type="hidden" name="id" value="<?= $u['id'] ?>">
                  <button type="submit" class="secondary"><?= $u['active'] ? 'تعطيل' : 'تفعيل' ?></button>
                </form>
                <form method="post" style="display:inline;" onsubmit="return confirm('حذف <?= h(addslashes($u['name'])) ?>؟ هيتم إلغاء تعيين مخدوميه.');">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $u['id'] ?>">
                  <button type="submit" class="danger">حذف</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>
</body>
</html>
