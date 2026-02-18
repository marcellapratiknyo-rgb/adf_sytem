<?php
/**
 * PUBLIC WEBSITE API - Get Available Rooms
 * Check room availability for given dates
 */

define('PUBLIC_ACCESS', true);
header('Content-Type: application/json; charset=utf-8');

require_once '../includes/config.php';
require_once '../includes/database.php';

try {
    // Get parameters
    $checkIn = trim($_GET['check_in'] ?? '');
    $checkOut = trim($_GET['check_out'] ?? '');
    $roomTypeId = intval($_GET['room_type'] ?? 0);
    $guests = intval($_GET['guests'] ?? 1);
    
    // Validate dates
    if (!$checkIn || !$checkOut) {
        throw new Exception('Check-in and check-out dates are required');
    }
    
    if (strtotime($checkIn) >= strtotime($checkOut)) {
        throw new Exception('Check-in date must be before check-out date');
    }
    
    if (strtotime($checkIn) < strtotime(date('Y-m-d'))) {
        throw new Exception('Check-in date cannot be in the past');
    }
    
    $db = PublicDatabase::getInstance();
    
    // Build query to find available rooms
    $sql = "
        SELECT r.id, r.room_number, rt.id as room_type_id, rt.type_name, rt.base_price, 
               rt.max_occupancy, rt.description, rt.color_code
        FROM rooms r
        LEFT JOIN room_types rt ON r.room_type_id = rt.id
        WHERE r.status IN ('available', 'cleaning')
        AND rt.max_occupancy >= ?
        AND r.id NOT IN (
            SELECT DISTINCT r2.id 
            FROM rooms r2
            INNER JOIN bookings b ON r2.id = b.room_id
            WHERE b.status IN ('pending', 'confirmed', 'checked_in')
            AND (
                (b.check_in_date < ? AND b.check_out_date > ?)
                OR (b.check_in_date >= ? AND b.check_in_date < ?)
            )
        )
    ";
    
    $params = [
        $guests,
        $checkOut,
        $checkIn,
        $checkIn,
        $checkOut
    ];
    
    // Add room type filter if specified
    if ($roomTypeId > 0) {
        $sql .= " AND rt.id = ?";
        $params[] = $roomTypeId;
    }
    
    $sql .= " ORDER BY rt.base_price ASC, r.room_number ASC";
    
    $availableRooms = $db->fetchAll($sql, $params);
    
    // Calculate total nights and total price
    $checkInDate = new DateTime($checkIn);
    $checkOutDate = new DateTime($checkOut);
    $interval = $checkInDate->diff($checkOutDate);
    $totalNights = $interval->days;
    
    // Add calculated fields
    foreach ($availableRooms as &$room) {
        $room['total_nights'] = $totalNights;
        $room['total_price'] = $room['base_price'] * $totalNights;
    }
    
    json_response([
        'success' => true,
        'data' => [
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'total_nights' => $totalNights,
            'available_rooms' => $availableRooms
        ]
    ]);
    
} catch (Exception $e) {
    json_response([
        'success' => false,
        'error' => $e->getMessage()
    ], 400);
}
?>
