<?php
require '../includes/config.php';
require '../includes/functions.php';
require_admin();

$error = '';
$insertedCount = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_FILES['file']['tmp_name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'من فضلك ارفع ملف CSV صحيح';
    } else {
        $handle = fopen($_FILES['file']['tmp_name'], 'r');
        if (!$handle) {
            $error = 'تعذر قراءة الملف';
        } else {
            // نشيل BOM بتاع UTF-8 لو موجود عشان العربي يتقرأ صح
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") rewind($handle);

            $header = fgetcsv($handle);
            if (!$header) {
                $error = 'الملف فاضي أو غير صحيح';
            } else {
                // تطبيع أسماء الأعمدة (إنجليزي أو عربي)
                $map = [];
                foreach ($header as $i => $col) {
                    $col = trim($col);
                    $key = null;
                    if (in_array($col, ['name', 'الاسم'])) $key = 'name';
                    elseif (in_array($col, ['phone', 'رقم الموبايل', 'الموبايل'])) $key = 'phone';
                    elseif (in_array($col, ['address', 'العنوان'])) $key = 'address';
                    elseif (in_array($col, ['age', 'السن'])) $key = 'age';
                    elseif (in_array($col, ['birth_date', 'تاريخ الميلاد'])) $key = 'birth_date';
                    elseif (in_array($col, ['lagna', 'اللجنة'])) $key = 'lagna';
                    if ($key) $map[$i] = $key;
                }

                $insertedCount = 0;
                $stmt = $pdo->prepare('INSERT INTO mokhdomeen (name, phone, address, age, birth_date, lagna) VALUES (?,?,?,?,?,?)');
                $pdo->beginTransaction();
                while (($row = fgetcsv($handle)) !== false) {
                    $data = ['name' => null, 'phone' => null, 'address' => null, 'age' => null, 'birth_date' => null, 'lagna' => null];
                    foreach ($map as $i => $key) {
                        $data[$key] = isset($row[$i]) ? trim($row[$i]) : null;
                    }
                    if (empty($data['name'])) continue;
                    $birthDateVal = null;
                    if (!empty($data['birth_date']) && strtotime($data['birth_date'])) {
                        $birthDateVal = date('Y-m-d', strtotime($data['birth_date']));
                    }
                    $stmt->execute([
                        $data['name'],
                        $data['phone'] ?: null,
                        $data['address'] ?: null,
                        $data['age'] !== null && $data['age'] !== '' ? (int)$data['age'] : null,
                        $birthDateVal,
                        $data['lagna'] ?: null,
                    ]);
                    $insertedCount++;
                }
                $pdo->commit();
            }
            fclose($handle);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>استيراد مخدومين - فريق الأنبا كاراس</title>
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
  <div class="section-title">استيراد مخدومين من ملف CSV</div>

  <div class="card-box">
    <?php if ($insertedCount !== null): ?>
      <div class="success-msg">تم استيراد <?= $insertedCount ?> مخدوم بنجاح.</div>
      <a href="mokhdomeen.php" class="btn full" style="text-align:center;">الرجوع لقائمة المخدومين</a>
    <?php else: ?>
      <?php if ($error): ?>
        <div class="error-msg"><?= h($error) ?></div>
      <?php endif; ?>

      <p style="color:var(--muted); font-size:.85rem;">
        الأعمدة المطلوبة في أول صف بالملف: <code>name, phone, address, age, birth_date, lagna</code>
        (أو بالعربي: الاسم، رقم الموبايل، العنوان، السن، تاريخ الميلاد، اللجنة)
      </p>

      <form method="post" enctype="multipart/form-data">
        <div class="field">
          <input type="file" name="file" accept=".csv" required>
        </div>
        <div class="actions">
          <a href="mokhdomeen.php" class="btn secondary">إلغاء</a>
          <button type="submit">استيراد</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</main>
</body>
</html>
