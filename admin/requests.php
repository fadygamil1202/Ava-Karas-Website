<?php
require '../includes/config.php';
require '../includes/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? '';

    // ---- طلبات تسجيل خادم جديد ----
    if ($type === 'registration') {
        $id     = (int)($_POST['id'] ?? 0);
        $action = $_POST['action'] ?? '';

        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND status = 'pending'");
        $stmt->execute([$id]);
        $target = $stmt->fetch();

        if ($target) {
            if ($action === 'approve') {
                $pdo->prepare("UPDATE users SET status = 'approved' WHERE id = ?")->execute([$id]);
                set_flash('success', 'تم قبول ' . $target['name'] . ' وبقى يقدر يدخل بحسابه');
            } elseif ($action === 'reject') {
                $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
                set_flash('success', 'تم رفض وحذف طلب ' . $target['name']);
            }
        }
    }

    // ---- طلبات استعادة كلمة المرور ----
    if ($type === 'reset') {
        $id     = (int)($_POST['id'] ?? 0);
        $action = $_POST['action'] ?? '';

        $stmt = $pdo->prepare("
            SELECT r.*, u.name AS user_name FROM password_reset_requests r
            JOIN users u ON u.id = r.user_id
            WHERE r.id = ?
        ");
        $stmt->execute([$id]);
        $req = $stmt->fetch();

        if ($req) {
            if ($action === 'approve') {
                // بنطبّق الباسورد اللي اليوزر كتبه بنفسه من غير ما نشوفه إحنا
                $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                    ->execute([$req['new_password_hash'], $req['user_id']]);
                $pdo->prepare('DELETE FROM password_reset_requests WHERE id = ?')->execute([$id]);
                set_flash('success', 'تم تحديث كلمة مرور ' . $req['user_name'] . ' بنجاح');
            } elseif ($action === 'reject') {
                $pdo->prepare('DELETE FROM password_reset_requests WHERE id = ?')->execute([$id]);
                set_flash('success', 'تم رفض طلب استعادة كلمة المرور');
            }
        }
    }

    redirect('requests.php');
}

$pendingRegistrations = $pdo->query("SELECT * FROM users WHERE status = 'pending' ORDER BY created_at ASC")->fetchAll();
$resetRequests = $pdo->query("
    SELECT r.*, u.name AS user_name, u.username FROM password_reset_requests r
    JOIN users u ON u.id = r.user_id
    ORDER BY r.created_at ASC
")->fetchAll();

$pendingCount = pending_actions_count($pdo);
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>طلبات التسجيل - فريق الأنبا كاراس</title>
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
    <a href="assign.php" class="tab">التوزيع</a>
    <a href="requests.php" class="tab active">طلبات التسجيل <?php if ($pendingCount): ?><span class="count"><?= $pendingCount ?></span><?php endif; ?></a>
  </div>
  <div class="who">
    <span><?= h(current_user()['name']) ?> (أدمن)</span>
    <a href="../logout.php" class="btn secondary">خروج</a>
  </div>
</div>

<main>
  <?php if ($flash): ?>
    <div class="<?= $flash['type'] === 'success' ? 'success-msg' : 'error-msg' ?>"><?= h($flash['message']) ?></div>
  <?php endif; ?>

  <div class="section-title">طلبات تسجيل خدام جدد (<?= count($pendingRegistrations) ?>)</div>

  <?php if (count($pendingRegistrations) === 0): ?>
    <div class="empty">مفيش طلبات تسجيل معلّقة حاليًا</div>
  <?php else: ?>
    <div class="list">
      <?php foreach ($pendingRegistrations as $u): ?>
        <div class="person-card" style="cursor:default;">
          <div class="info">
            <div class="name"><?= h($u['name']) ?></div>
            <div class="meta">
              <span>يوزر: <?= h($u['username']) ?></span>
              <?php if ($u['phone']): ?><span>📞 <?= h($u['phone']) ?></span><?php endif; ?>
              <span>طلب بتاريخ: <?= fmt_date($u['created_at']) ?></span>
            </div>
          </div>
          <div style="display:flex; gap:8px;">
            <form method="post" onsubmit="return confirm('تأكيد قبول هذا الخادم؟');">
              <input type="hidden" name="type" value="registration">
              <input type="hidden" name="id" value="<?= $u['id'] ?>">
              <input type="hidden" name="action" value="approve">
              <button type="submit">قبول</button>
            </form>
            <form method="post" onsubmit="return confirm('تأكيد رفض وحذف هذا الطلب؟');">
              <input type="hidden" name="type" value="registration">
              <input type="hidden" name="id" value="<?= $u['id'] ?>">
              <input type="hidden" name="action" value="reject">
              <button type="submit" class="danger">رفض</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="section-title">طلبات استعادة كلمة المرور (<?= count($resetRequests) ?>)</div>

  <?php if (count($resetRequests) === 0): ?>
    <div class="empty">مفيش طلبات استعادة كلمة مرور حاليًا</div>
  <?php else: ?>
    <div class="list">
      <?php foreach ($resetRequests as $r): ?>
        <div class="person-card" style="cursor:default;">
          <div class="info">
            <div class="name"><?= h($r['user_name']) ?></div>
            <div class="meta">
              <span>يوزر: <?= h($r['username']) ?></span>
              <span>طلب بتاريخ: <?= fmt_date($r['created_at']) ?></span>
              <span class="badge neutral">الباسورد الجديد محفوظ ومشفّر، مش ظاهر لحد</span>
            </div>
          </div>
          <div style="display:flex; gap:8px;">
            <form method="post" onsubmit="return confirm('تأكيد تحديث كلمة المرور بالجديدة اللي كتبها اليوزر؟');">
              <input type="hidden" name="type" value="reset">
              <input type="hidden" name="id" value="<?= $r['id'] ?>">
              <input type="hidden" name="action" value="approve">
              <button type="submit">موافقة وتحديث الباسورد</button>
            </form>
            <form method="post" onsubmit="return confirm('تأكيد رفض هذا الطلب؟');">
              <input type="hidden" name="type" value="reset">
              <input type="hidden" name="id" value="<?= $r['id'] ?>">
              <input type="hidden" name="action" value="reject">
              <button type="submit" class="danger">رفض</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>
</body>
</html>
