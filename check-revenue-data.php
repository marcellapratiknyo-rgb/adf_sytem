<?php
require_once 'config/config.php';
$pdo = new PDO('mysql:host='.DB_HOST.';dbname=adf_narayana_hotel', DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== BOOKINGS (checked_in or confirmed) ===\n";
$stmt = $pdo->query('SELECT b.id, g.guest_name, b.status, b.total_price, b.final_price, b.paid_amount, b.check_in_date, b.check_out_date FROM bookings b LEFT JOIN guests g ON b.guest_id = g.id WHERE b.status IN ("confirmed", "checked_in") ORDER BY b.id DESC LIMIT 10');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== BOOKING_PAYMENTS ===\n";
$stmt = $pdo->query('SELECT * FROM booking_payments ORDER BY id DESC LIMIT 10');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== TODAY REVENUE (booking_payments) ===\n";
$today = date('Y-m-d');
echo "Today: $today\n";
$stmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) as total FROM booking_payments WHERE DATE(payment_date) = ?');
$stmt->execute([$today]);
print_r($stmt->fetch(PDO::FETCH_ASSOC));

echo "\n=== THIS MONTH REVENUE ===\n";
$thisMonth = date('Y-m');
echo "Month: $thisMonth\n";
$stmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) as total FROM booking_payments WHERE DATE_FORMAT(payment_date, "%Y-%m") = ?');
$stmt->execute([$thisMonth]);
print_r($stmt->fetch(PDO::FETCH_ASSOC));

echo "\n=== SUM OF paid_amount from bookings (checked_in) ===\n";
$stmt = $pdo->query('SELECT SUM(paid_amount) as total_paid, SUM(final_price) as total_price FROM bookings WHERE status = "checked_in"');
print_r($stmt->fetch(PDO::FETCH_ASSOC));
