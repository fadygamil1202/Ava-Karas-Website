<?php
require '../includes/config.php';
require '../includes/functions.php';
require_attendance_access();
require '../includes/xlsx/SimpleXLSX.php';

$isAdmin = current_user()['role'] === 'admin';
$homeUrl = $isAdmin ? 'index.php' : '../khadem.php';

use Shuchkin\SimpleXLSX;

const ALLOWED_STATUSES = ['حاضر', 'غايب', 'معتذر'];
const HEADER_ROWS = 3; // صف التاريخ + صف فاضي + صف العناوين

$errors = [];
$summary = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_FILES['file']['tmp_name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'من فضلك ارفع ملف الإكسيل';
    } else {
        $xlsx = SimpleXLSX::parse($_FILES['file']['tmp_name']);
        if (!$xlsx) {
            $errors[] = 'تعذر قراءة الملف: ' . SimpleXLSX::parseError();
        } else {
            $rows = $xlsx->rows();

            // ---- استخراج تاريخ الاجتماع من الهيدر ----
            $meetingDate = null;
            $dateCell = trim($rows[0][1] ?? '');
            $ts = strtotime($dateCell);
            if ($dateCell === '' || $ts === false) {
                $errors[] = 'تاريخ الاجتماع في أول خلية بالملف غير صحيح أو فاضي';
            } else {
                $meetingDate = date('Y-m-d', $ts);
            }

            // ---- تجهيز بيانات الخدام والمخدومين الموجودين فعليًا، للتحقق منها ----
            $validKhademIds = $pdo->query("SELECT id FROM users WHERE role = 'khadem'")->fetchAll(PDO::FETCH_COLUMN);
            $validKhademIds = array_flip($validKhademIds);
            $validMokhdoomIds = $pdo->query("SELECT id FROM mokhdomeen")->fetchAll(PDO::FETCH_COLUMN);
            $validMokhdoomIds = array_flip($validMokhdoomIds);

            $typeMap = ['خادم' => 'khadem', 'مخدوم' => 'mokhdoom'];

            $validRows = [];
            $rowCount = count($rows);

            for ($i = HEADER_ROWS; $i < $rowCount; $i++) {
                $row = $rows[$i];
                $excelRowNum = $i + 1; // رقم الصف زي ما هيبان في إكسيل (1-indexed)

                $idRaw     = trim($row[0] ?? '');
                $typeRaw   = trim($row[1] ?? '');
                $nameRaw   = trim($row[2] ?? '');
                $statusRaw = trim($row[4] ?? '');

                // صف فاصل زي "بدون خادم" - من غير ID ولا نوع - نتجاهله
                if ($idRaw === '' && $typeRaw === '') {
                    continue;
                }

                if ($idRaw === '' || !ctype_digit($idRaw)) {
                    $errors[] = "صف $excelRowNum: عمود ID غير صحيح أو فاضي" . ($nameRaw !== '' ? " (الاسم: $nameRaw)" : '');
                    continue;
                }
                if (!isset($typeMap[$typeRaw])) {
                    $errors[] = "صف $excelRowNum: النوع لازم يكون \"خادم\" أو \"مخدوم\" بالظبط، مش \"$typeRaw\"";
                    continue;
                }
                $personType = $typeMap[$typeRaw];
                $personId = (int)$idRaw;

                if ($personType === 'khadem' && !isset($validKhademIds[$personId])) {
                    $errors[] = "صف $excelRowNum: مفيش خادم بالـ ID ده ($personId)";
                    continue;
                }
                if ($personType === 'mokhdoom' && !isset($validMokhdoomIds[$personId])) {
                    $errors[] = "صف $excelRowNum: مفيش مخدوم بالـ ID ده ($personId)";
                    continue;
                }

                if ($statusRaw === '' || !in_array($statusRaw, ALLOWED_STATUSES, true)) {
                    $errors[] = "صف $excelRowNum: الحالة \"$statusRaw\" غير صحيحة، لازم تكون حاضر أو غايب أو معتذر";
                    continue;
                }

                $validRows[] = [
                    'person_id'   => $personId,
                    'person_type' => $personType,
                    'status'      => $statusRaw,
                ];
            }

            if (empty($errors) && empty($validRows)) {
                $errors[] = 'الملف فاضي، مفيش أي صفوف حضور فيه';
            }

            // ---- لو كل حاجة سليمة، نحفظ في القاعدة ----
            if (empty($errors)) {
                $pdo->beginTransaction();
                try {
                    // نجيب أو ننشئ الاجتماع بالتاريخ ده
                    $stmt = $pdo->prepare('SELECT id FROM meetings WHERE meeting_date = ?');
                    $stmt->execute([$meetingDate]);
                    $meeting = $stmt->fetch();

                    $wasExisting = (bool)$meeting;
                    if ($meeting) {
                        $meetingId = $meeting['id'];
                        // نمسح الحضور القديم بتاع نفس الاجتماع عشان نستبدله بالجديد
                        $pdo->prepare('DELETE FROM attendance WHERE meeting_id = ?')->execute([$meetingId]);
                    } else {
                        $pdo->prepare('INSERT INTO meetings (meeting_date) VALUES (?)')->execute([$meetingDate]);
                        $meetingId = $pdo->lastInsertId();
                    }

                    $insertStmt = $pdo->prepare(
                        'INSERT INTO attendance (meeting_id, person_id, person_type, status, recorded_by) VALUES (?,?,?,?,?)'
                    );
                    $counts = ['khadem' => ['حاضر' => 0, 'غايب' => 0, 'معتذر' => 0], 'mokhdoom' => ['حاضر' => 0, 'غايب' => 0, 'معتذر' => 0]];
                    foreach ($validRows as $r) {
                        $insertStmt->execute([$meetingId, $r['person_id'], $r['person_type'], $r['status'], current_user()['id']]);
                        $counts[$r['person_type']][$r['status']]++;
                    }

                    $pdo->commit();

                    $summary = [
                        'meeting_date' => $meetingDate,
                        'was_existing' => $wasExisting,
                        'total' => count($validRows),
                        'counts' => $counts,
                    ];
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $errors[] = 'حصل خطأ غير متوقع أثناء الحفظ: ' . $e->getMessage();
                }
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
<title>رفع الحضور - فريق الأنبا كاراس</title>
<link rel="icon" type="image/png" href="../assets/favicon.png">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="topbar">
  <a href="<?= h($homeUrl) ?>" class="brand"><img src="../assets/logo.png" alt="لوجو فريق الأنبا كاراس" class="logo"> فريق الأنبا كاراس</a>
  <div class="who">
    <span><?= h(current_user()['name']) ?><?= $isAdmin ? ' (أدمن)' : ' (مسؤول الحضور)' ?></span>
    <a href="../logout.php" class="btn secondary">خروج</a>
  </div>
</div>

<main style="max-width:640px;">
  <div class="section-title">رفع حضور الاجتماع الأسبوعي</div>

  <?php if ($summary): ?>
    <div class="card-box">
      <div class="success-msg" style="margin-bottom:0;">
        <?= $summary['was_existing'] ? 'تم تحديث' : 'تم تسجيل' ?> حضور اجتماع <?= fmt_date($summary['meeting_date']) ?> بنجاح
        <?= $summary['was_existing'] ? '(استبدلنا بيانات كانت متسجلة قبل كده لنفس اليوم)' : '' ?>
      </div>
    </div>

    <div class="section-title">ملخص الحضور</div>
    <div class="cards-row">
      <div class="stat-card"><div class="num"><?= $summary['counts']['khadem']['حاضر'] ?></div><div class="lbl">خدام حاضرين</div></div>
      <div class="stat-card"><div class="num"><?= $summary['counts']['khadem']['غايب'] ?></div><div class="lbl">خدام غايبين</div></div>
      <div class="stat-card"><div class="num"><?= $summary['counts']['khadem']['معتذر'] ?></div><div class="lbl">خدام معتذرين</div></div>
      <div class="stat-card"><div class="num"><?= $summary['counts']['mokhdoom']['حاضر'] ?></div><div class="lbl">مخدومين حاضرين</div></div>
      <div class="stat-card"><div class="num"><?= $summary['counts']['mokhdoom']['غايب'] ?></div><div class="lbl">مخدومين غايبين</div></div>
      <div class="stat-card"><div class="num"><?= $summary['counts']['mokhdoom']['معتذر'] ?></div><div class="lbl">مخدومين معتذرين</div></div>
    </div>

    <a href="attendance_upload.php" class="btn secondary">رفع ملف تاني</a>
  <?php else: ?>

    <div class="card-box">
      <p style="color:var(--muted); font-size:.88rem; margin-top:0;">
        1. حمّل قالب الحضور من صفحة المخدومين، واملاه في الاجتماع من غير نت.<br>
        2. ارفع نفس الملف هنا بعد الاجتماع، وهيتسجل تلقائي.
      </p>
      <a href="attendance_template.php" class="btn secondary full" style="text-align:center; margin-bottom:16px;">تحميل قالب الحضور</a>

      <?php if (!empty($errors)): ?>
        <div class="error-msg">
          <strong>فيه <?= count($errors) ?> مشكلة في الملف، محدش اتسجل لحد ما تتصلح:</strong>
          <ul style="margin:8px 0 0; padding-inline-start:20px;">
            <?php foreach ($errors as $e): ?>
              <li><?= h($e) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data">
        <div class="field">
          <label>ملف حضور الاجتماع (.xlsx)</label>
          <input type="file" name="file" accept=".xlsx" required>
        </div>
        <button class="full" type="submit">رفع وتسجيل الحضور</button>
      </form>
    </div>

  <?php endif; ?>
</main>
</body>
</html>
