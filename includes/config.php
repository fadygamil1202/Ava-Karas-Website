<?php
/**
 * ملف الإعدادات الرئيسي - عدّل البيانات دي بس ومتلمسش باقي الملف
 * هتلاقي البيانات دي في hPanel -> Databases -> MySQL Databases
 */

// ---------- بيانات قاعدة البيانات ----------
define('DB_HOST', 'localhost');                  // غالبًا localhost في Hostinger
define('DB_NAME', 'avakarasteam');           // اسم القاعدة اللي عملتها
define('DB_USER', 'root');            // يوزر القاعدة
define('DB_PASS', '');              // باسورد القاعدة

// ---------- إعدادات عامة ----------
date_default_timezone_set('Africa/Cairo');

// ---------- بدء الجلسة (Session) ----------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---------- الاتصال بقاعدة البيانات عن طريق PDO ----------
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('تعذر الاتصال بقاعدة البيانات. تأكد من بيانات الاتصال في config.php. (' . $e->getMessage() . ')');
}
