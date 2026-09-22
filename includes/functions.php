<?php
/**
 * دوال مساعدة عامة تُستخدم في كل صفحات الموقع
 * لازم يتعمل له require بعد config.php
 */

// تهريب النصوص قبل عرضها (حماية من XSS)
function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// المستخدم الحالي المسجل دخوله (أو null لو مفيش)
function current_user() {
    return $_SESSION['user'] ?? null;
}

// مسار صفحة اللوجين صح، سواء الصفحة الحالية في الجذر أو جوه مجلد admin/
function login_url() {
    return (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') !== false) ? '../login.php' : 'login.php';
}

// لازم يكون مسجل دخول، غير كده يوديه لصفحة اللوجين
function require_login() {
    if (!current_user()) {
        redirect(login_url());
    }
}

// لازم يكون أدمن، غير كده يرفض الدخول
function require_admin() {
    require_login();
    if (current_user()['role'] !== 'admin') {
        http_response_code(403);
        die('غير مصرح لك بالدخول لهذه الصفحة.');
    }
}

// الأدمن، أو خادم اتديله صلاحية "مسؤول الحضور" تحديدًا
function can_manage_attendance() {
    $u = current_user();
    return $u && ($u['role'] === 'admin' || !empty($u['can_manage_attendance']));
}

// لازم يكون أدمن أو مسؤول حضور، غير كده يرفض الدخول
function require_attendance_access() {
    require_login();
    if (!can_manage_attendance()) {
        http_response_code(403);
        die('غير مصرح لك بالدخول لهذه الصفحة.');
    }
}

// تنسيق التاريخ بالعربي
function fmt_date($date) {
    if (!$date) return null;
    $ts = strtotime($date);
    $months = ['يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];
    return date('j', $ts) . ' ' . $months[(int)date('n', $ts) - 1] . ' ' . date('Y', $ts);
}

// عدد الأيام منذ تاريخ معين
function days_since($date) {
    if (!$date) return null;
    $diff = (new DateTime())->diff(new DateTime($date));
    return (int)$diff->days;
}

// شارة حالة آخر افتقاد (لون حسب المدة)
function visit_badge($lastDate) {
    if (!$lastDate) return '<span class="badge danger">لم يُفتقد بعد</span>';
    $days = days_since($lastDate);
    if ($days <= 14) return "<span class=\"badge ok\">قبل $days يوم</span>";
    if ($days <= 30) return "<span class=\"badge warn\">قبل $days يوم</span>";
    return "<span class=\"badge danger\">قبل $days يوم</span>";
}

/**
 * بيرجع مصفوفة بمخدومي الخادم اللي عيد ميلادهم جاي خلال $days يوم (شامل النهارده)،
 * مرتبة بالأقرب الأول. كل عنصر فيه next_birthday (تاريخ أقرب عيد ميلاد) وdays_left وturning_age.
 */
function get_upcoming_birthdays(PDO $pdo, int $khademId, int $days = 7): array {
    $stmt = $pdo->prepare("
      SELECT id, name, phone, lagna, birth_date
      FROM mokhdomeen
      WHERE khadem_id = ? AND active = 1 AND birth_date IS NOT NULL
    ");
    $stmt->execute([$khademId]);
    $all = $stmt->fetchAll();

    $today = new DateTime('today');
    $upcoming = [];
    foreach ($all as $m) {
        $bd = new DateTime($m['birth_date']);
        $next = new DateTime($today->format('Y') . '-' . $bd->format('m-d'));
        if ($next < $today) {
            $next->modify('+1 year');
        }
        $daysLeft = (int)$today->diff($next)->days;
        if ($daysLeft <= $days) {
            $m['next_birthday'] = $next;
            $m['days_left'] = $daysLeft;
            $m['turning_age'] = (int)$bd->diff($next)->y;
            $upcoming[] = $m;
        }
    }
    usort($upcoming, fn($a, $b) => $a['days_left'] <=> $b['days_left']);
    return $upcoming;
}

// عدد الغيابات المتتالية اللي تستوجب علامة "محتاج افتقاد" في قايمة الخادم
if (!defined('ABSENCE_THRESHOLD')) {
    define('ABSENCE_THRESHOLD', 2);
}

/**
 * بيرجع مصفوفة [mokhdoom_id => true] لكل المخدومين اللي غابوا آخر ABSENCE_THRESHOLD
 * اجتماعات ورا بعض من غير أي افتقاد جديد بعد أول غيبة في السلسلة دي.
 * العلامة بتتشال تلقائي أول ما يتسجل افتقاد بعد بداية سلسلة الغياب.
 */
function get_needs_visitation_ids(PDO $pdo): array {
    $mokhdoomIds = $pdo->query("SELECT id FROM mokhdomeen WHERE active = 1")->fetchAll(PDO::FETCH_COLUMN);
    $needsVisit = [];

    $attStmt = $pdo->prepare("
        SELECT me.meeting_date, a.status
        FROM attendance a JOIN meetings me ON me.id = a.meeting_id
        WHERE a.person_type = 'mokhdoom' AND a.person_id = ?
        ORDER BY me.meeting_date DESC
        LIMIT " . (int)ABSENCE_THRESHOLD . "
    ");
    $visitStmt = $pdo->prepare("SELECT MAX(visit_date) d FROM visits WHERE mokhdoom_id = ? AND visit_date >= ?");

    foreach ($mokhdoomIds as $id) {
        $attStmt->execute([$id]);
        $recent = $attStmt->fetchAll();
        if (count($recent) < ABSENCE_THRESHOLD) continue;

        $allAbsent = true;
        foreach ($recent as $r) {
            if ($r['status'] !== 'غايب') { $allAbsent = false; break; }
        }
        if (!$allAbsent) continue;

        $streakStartDate = end($recent)['meeting_date']; // أقدم اجتماع في سلسلة الغياب
        $visitStmt->execute([$id, $streakStartDate]);
        $hasNewerVisit = (bool)$visitStmt->fetch()['d'];
        if (!$hasNewerVisit) {
            $needsVisit[$id] = true;
        }
    }
    return $needsVisit;
}

// إجمالي الطلبات المعلّقة (تسجيل خدام جدد + استعادة كلمة مرور) - بتظهر كرقم على تاب "طلبات التسجيل"
function pending_actions_count($pdo) {
    $reg = $pdo->query("SELECT COUNT(*) c FROM users WHERE status = 'pending'")->fetch()['c'];
    $reset = $pdo->query("SELECT COUNT(*) c FROM password_reset_requests")->fetch()['c'];
    return (int)$reg + (int)$reset;
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

// رسائل فلاش (تظهر مرة واحدة بعد إعادة التوجيه)
function set_flash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash() {
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
