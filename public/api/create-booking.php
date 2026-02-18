<?php
/**
 * PUBLIC WEBSITE API - Create Booking
 * Create new booking from public website
 */

define('PUBLIC_ACCESS', true);
header('Content-Type: application/json; charset=utf-8');

require_once '../includes/config.php';
require_once '../includes/database.php';

try {
    // Only allow POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception('Invalid request data');
    }
    
    // Validate input
    $guestName = trim($input['guest_name'] ?? '');
    $guestEmail = trim($input['guest_email'] ?? '');
    $guestPhone = trim($input['guest_phone'] ?? '');
    $roomId = intval($input['room_id'] ?? 0);
    $checkIn = trim($input['check_in'] ?? '');
    $checkOut = trim($input['check_out'] ?? '');
    $guests = intval($input['guests'] ?? 1);
    $specialRequest = trim($input['special_request'] ?? '');
    
    // Validate required fields
    if (!$guestName) throw new Exception('Guest name is required');
    if (!$guestEmail) throw new Exception('Guest email is required');
    if (!$guestPhone) throw new Exception('Guest phone is required');
    if (!$roomId) throw new Exception('Room is required');
    if (!$checkIn || !$checkOut) throw new Exception('Check-in and check-out dates are required');
    
    // Validate email format
    if (!filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }
    
    // Validate dates
    if (strtotime($checkIn) >= strtotime($checkOut)) {
        throw new Exception('Check-in date must be before check-out date');
    }
    
    if (strtotime($checkIn) < strtotime(date('Y-m-d'))) {
        throw new Exception('Check-in date cannot be in the past');
    }
    
    $db = PublicDatabase::getInstance();
    
    // Verify room exists and is available
    $room = $db->fetchOne("
        SELECT r.id, r.room_number, rt.base_price, rt.max_occupancy
        FROM rooms r
        LEFT JOIN room_types rt ON r.room_type_id = rt.id
        WHERE r.id = ?
    ", [$roomId]);
    
    if (!$room) {
        throw new Exception('Room not found');
    }
    
    // Check availability again (ensure it wasn't booked by someone else)
    $conflicts = $db->fetchAll("
        SELECT id FROM bookings 
        WHERE room_id = ? 
        AND status IN ('pending', 'confirmed', 'checked_in')
        AND (
            (check_in_date < ? AND check_out_date > ?)
            OR (check_in_date >= ? AND check_in_date < ?)
        )
    ", [$roomId, $checkOut, $checkIn, $checkIn, $checkOut]);
    
    if (!empty($conflicts)) {
        throw new Exception('Room is no longer available for selected dates');
    }
    
    if ($guests > $room['max_occupancy']) {
        throw new Exception('Number of guests exceeds room capacity');
    }
    
    // Calculate prices
    $checkInDate = new DateTime($checkIn);
    $checkOutDate = new DateTime($checkOut);
    $interval = $checkInDate->diff($checkOutDate);
    $totalNights = $interval->days;
    $roomPrice = $room['base_price'];
    $totalPrice = $roomPrice * $totalNights;
    
    $db->beginTransaction();
    
    try {
        // Create guest record
        $guestData = [
            'guest_name' => $guestName,
            'id_card_type' => 'passport',
            'id_card_number' => 'TEMP-' . date('YmdHis') . '-' . rand(1000, 9999),
            'phone' => $guestPhone,
            'email' => $guestEmail,
            'nationality' => 'Indonesia',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $guestId = $db->insert('guests', $guestData);
        
        // Generate booking code
        $bookingCode = 'BK-' . date('Ymd') . '-' . str_pad($guestId, 5, '0', STR_PAD_LEFT);
        
        // Create booking
        $bookingData = [
            'booking_code' => $bookingCode,
            'guest_id' => $guestId,
            'room_id' => $roomId,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'status' => 'pending',
            'adults' => $guests,
            'children' => 0,
            'room_price' => $roomPrice,
            'total_nights' => $totalNights,
            'total_price' => $totalPrice,
            'final_price' => $totalPrice,
            'booking_source' => 'online',
            'special_request' => $specialRequest,
            'payment_status' => 'unpaid',
            'paid_amount' => 0,
            'notes' => 'Created via public website',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $bookingId = $db->insert('bookings', $bookingData);
        
        $db->commit();
        
        // Return booking details for payment
        json_response([
            'success' => true,
            'data' => [
                'booking_id' => $bookingId,
                'booking_code' => $bookingCode,
                'guest_name' => $guestName,
                'guest_email' => $guestEmail,
                'room_number' => $room['room_number'],
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'total_nights' => $totalNights,
                'room_price' => $roomPrice,
                'total_price' => $totalPrice,
                'payment_required' => $totalPrice
            ]
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw new Exception('Failed to create booking: ' . $e->getMessage());
    }
    
} catch (Exception $e) {
    json_response([
        'success' => false,
        'error' => $e->getMessage()
    ], 400);
}
?>
