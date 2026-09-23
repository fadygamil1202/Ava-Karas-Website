<?php
require '../includes/config.php';
require '../includes/functions.php';
require_attendance_access();
require '../includes/xlsx/xlsxwriter.class.php';

// الخدام (فقط المعتمدين والنشطين)
$khadam = $pdo->query("
    SELECT id, name FROM users WHERE role = 'khadem' AND status = 'approved' AND active = 1 ORDER BY name
")->fetchAll();

// المخدومين مع اسم خادمهم
$mokhdomeen = $pdo->query("
    SELECT m.id, m.name, m.khadem_id, u.name AS khadem_name
    FROM mokhdomeen m LEFT JOIN users u ON u.id = m.khadem_id
    WHERE m.active = 1
    ORDER BY u.name IS NULL, u.name, m.name
")->fetchAll();

// نجمّع المخدومين تحت كل خادم
$byKhadem = [];
$unassigned = [];
foreach ($mokhdomeen as $m) {
    if ($m['khadem_id']) {
        $byKhadem[$m['khadem_id']][] = $m;
    } else {
        $unassigned[] = $m;
    }
}

$writer = new XLSXWriter();
$writer->setRightToLeft(true);
$sheet = 'الحضور';

// صف تاريخ الاجتماع - قابل للتعديل قبل الاستخدام
$writer->writeSheetRow($sheet, ['تاريخ الاجتماع (عدّله لو الاجتماع مش النهاردة):', date('Y-m-d')]);
$writer->writeSheetRow($sheet, []); // صف فاضي

// صف العناوين الفعلي
$writer->writeSheetRow($sheet, ['ID', 'النوع', 'الاسم', 'تحت إشراف', 'الحالة (حاضر / غايب / معتذر)']);

// الخدام ومخدوميهم مجمّعين تحت بعض
foreach ($khadam as $k) {
    $writer->writeSheetRow($sheet, [$k['id'], 'خادم', $k['name'], '', '']);
    if (!empty($byKhadem[$k['id']])) {
        foreach ($byKhadem[$k['id']] as $m) {
            $writer->writeSheetRow($sheet, [$m['id'], 'مخدوم', $m['name'], $k['name'], '']);
        }
    }
}

// المخدومين بدون خادم (لو فيه)
if (!empty($unassigned)) {
    $writer->writeSheetRow($sheet, ['', '', 'بدون خادم', '', '']);
    foreach ($unassigned as $m) {
        $writer->writeSheetRow($sheet, [$m['id'], 'مخدوم', $m['name'], '', '']);
    }
}

// نتأكد مفيش أي مخرجات متراكمة (مسافات أو تحذيرات PHP) قبل ما نبعت الملف
// عشان أي حرف زيادة قبل بيانات الـ xlsx بيبوظ الملف بالكامل في إكسل
while (ob_get_level() > 0) {
    ob_end_clean();
}

$filename = 'قالب_حضور_' . date('Y-m-d') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
$writer->writeToStdOut();
exit;
