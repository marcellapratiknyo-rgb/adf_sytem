<?php
/**
 * PUBLIC WEBSITE - Booking Page
 * Complete booking flow
 */

define('PUBLIC_ACCESS', true);
require_once './includes/config.php';
require_once './includes/database.php';

$pageTitle = 'Pesan Kamar - ' . BUSINESS_NAME;
$additionalCSS = ['css/booking.css'];
$additionalJS = ['js/booking.js'];

// Get all room types for reference
$db = PublicDatabase::getInstance();
$roomTypes = $db->fetchAll("
    SELECT DISTINCT rt.id, rt.type_name, rt.base_price, rt.description
    FROM room_types rt
    ORDER BY rt.base_price ASC
");

?>
<?php include './includes/header.php'; ?>

<section class="section booking-page">
    <div class="container">
        <h1 style="margin-bottom: 1rem;">Pesan Kamar</h1>
        <p style="color: #64748b; margin-bottom: 2rem;">Proses booking aman dan mudah</p>
        
        <div class="booking-container">
            <!-- Left side: Search & Room Selection -->
            <div class="booking-column booking-search">
                <div class="search-box card">
                    <h3>1. Pilih Tanggal & Kamar</h3>
                    
                    <div class="form-group">
                        <label>Check In*</label>
                        <input type="date" id="bookingCheckIn" min="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Check Out*</label>
                        <input type="date" id="bookingCheckOut" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Jumlah Tamu*</label>
                        <select id="bookingGuests">
                            <option value="1">1 Tamu</option>
                            <option value="2" selected>2 Tamu</option>
                            <option value="3">3 Tamu</option>
                            <option value="4">4+ Tamu</option>
                        </select>
                    </div>
                    
                    <button class="btn btn-primary btn-block" onclick="searchAvailableRooms()">
                        <span>Cari Ketersediaan</span>
                        <span id="searchLoader" style="display: none; margin-left: 0.5rem;">
                            <i data-feather="loader"></i>
                        </span>
                    </button>
                </div>
                
                <!-- Available Rooms List -->
                <div id="availableRoomsList" style="display: none; margin-top: 2rem;">
                    <div class="card">
                        <h3>Kamar Tersedia</h3>
                        <div id="roomsContainer"></div>
                    </div>
                </div>
                
                <!-- No Rooms Message -->
                <div id="noRoomsMessage" style="display: none; margin-top: 2rem;">
                    <div class="alert alert-warning">
                        Maaf, tidak ada kamar tersedia untuk tanggal yang dipilih. Silakan coba tanggal lain.
                    </div>
                </div>
            </div>
            
            <!-- Right side: Guest Info & Summary -->
            <div class="booking-column booking-form">
                <div class="info-box card" id="bookingSummary" style="display: none;">
                    <h3>2. Data Tamu</h3>
                    
                    <div class="form-group">
                        <label>Nama Lengkap*</label>
                        <input type="text" id="guestName" placeholder="Contoh: Budi Santoso" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Email*</label>
                        <input type="email" id="guestEmail" placeholder="example@email.com" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Nomor Telepon*</label>
                        <input type="text" id="guestPhone" placeholder="08123456789" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Permintaan Khusus (Opsional)</label>
                        <textarea id="guestRequest" placeholder="Contoh: Near window, High floor, dll" rows="4"></textarea>
                    </div>
                    
                    <!-- Booking Summary -->
                    <div class="summary-box">
                        <h4 style="margin-bottom: 1rem;">Ringkasan Pesanan</h4>
                        
                        <div class="summary-row">
                            <span>Kamar:</span>
                            <strong id="summaryRoom">-</strong>
                        </div>
                        
                        <div class="summary-row">
                            <span>Check In:</span>
                            <strong id="summaryCheckIn">-</strong>
                        </div>
                        
                        <div class="summary-row">
                            <span>Check Out:</span>
                            <strong id="summaryCheckOut">-</strong>
                        </div>
                        
                        <div class="summary-row">
                            <span>Malam:</span>
                            <strong id="summaryNights">0</strong>
                        </div>
                        
                        <div class="summary-row">
                            <span>Harga per Malam:</span>
                            <strong id="summaryRoomPrice">Rp 0</strong>
                        </div>
                        
                        <div style="border-top: 2px solid #e2e8f0; padding-top: 1rem; margin-top: 1rem;" class="summary-row">
                            <span style="font-weight: 600;">Total Harga:</span>
                            <strong id="summaryTotalPrice" style="color: #6366f1; font-size: 1.3rem;">Rp 0</strong>
                        </div>
                    </div>
                    
                    <button class="btn btn-primary btn-block" onclick="proceedToPayment()" style="margin-top: 2rem;">
                        Lanjutkan ke Pembayaran
                    </button>
                </div>
                
                <!-- No Room Selected Message -->
                <div class="card" id="selectRoomMessage">
                    <p style="text-align: center; color: #94a3b8; padding: 2rem 0;">
                        <i data-feather="arrow-left" style="display: block; margin: 0 auto 1rem;"></i>
                        Pilih kamar dari daftar tersedia
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Hidden form for payment processing -->
<form id="paymentForm" method="POST" style="display: none;">
    <input type="hidden" id="bookingId" name="booking_id">
    <input type="hidden" id="bookingCode" name="booking_code">
    <input type="hidden" id="paymentAmount" name="amount">
</form>

<?php include './includes/footer.php'; ?>
