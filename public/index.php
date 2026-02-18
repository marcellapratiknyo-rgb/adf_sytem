<?php
/**
 * PUBLIC WEBSITE - Homepage
 * Beautiful hotel brochure with booking call-to-action
 */

define('PUBLIC_ACCESS', true);
require_once './includes/config.php';
require_once './includes/database.php';

$pageTitle = 'Luxury Resort - ' . BUSINESS_NAME;
$additionalCSS = ['css/homepage.css'];

// Get hotel settings and featured rooms
$db = PublicDatabase::getInstance();
try {
    $featuredRooms = $db->fetchAll("
        SELECT r.id, r.room_number, rt.type_name, rt.base_price, rt.description, rt.color_code
        FROM rooms r
        LEFT JOIN room_types rt ON r.room_type_id = rt.id
        WHERE r.status = 'available'
        ORDER BY rt.base_price DESC
        LIMIT 6
    ");
} catch (Exception $e) {
    $featuredRooms = [];
}

?>
<?php include './includes/header.php'; ?>

<!-- Hero Section -->
<section class="hero hero-large">
    <div class="hero-content">
        <h1>Selamat Datang di Narayana</h1>
        <p>Hotel Mewah di Kepulauan Karimunjawa<br>Pengalaman Menginap Tak Terlupakan Menanti Anda</p>
        
        <div class="hero-search">
            <div>
                <label>Check In</label>
                <input type="date" id="heroCheckIn" min="<?php echo date('Y-m-d'); ?>">
            </div>
            <div>
                <label>Check Out</label>
                <input type="date" id="heroCheckOut" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
            </div>
            <div>
                <label>Tamu</label>
                <select id="heroGuests">
                    <option value="1">1 Tamu</option>
                    <option value="2">2 Tamu</option>
                    <option value="3">3 Tamu</option>
                    <option value="4">4+ Tamu</option>
                </select>
            </div>
            <button class="btn btn-primary" onclick="searchRooms()">Cek Ketersediaan</button>
        </div>
    </div>
</section>

<!-- About Section -->
<section class="section">
    <div class="container">
        <div class="grid grid-2">
            <div>
                <h2>Tentang Narayana</h2>
                <p>
                    Narayana Karimunjawa adalah resort mewah yang dirancang khusus untuk memberikan 
                    pengalaman menginap premium di tengah keindahan alam Kepulauan Karimunjawa.
                </p>
                <p>
                    Dengan fasilitas kelas dunia, pelayanan terbaik, dan pemandangan laut yang spektakuler, 
                    kami berkomitmen untuk membuat liburan Anda menjadi kenangan indah selamanya.
                </p>
                <div style="margin-top: 1.5rem;">
                    <a href="<?php echo baseUrl('rooms.php'); ?>" class="btn btn-primary">Lihat Semua Kamar</a>
                </div>
            </div>
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 0.5rem; min-height: 300px; display: flex; align-items: center; justify-content: center; color: white; text-align: center;">
                <img src="<?php echo assetUrl('images/hero-placeholder.jpg'); ?>" alt="Narayana Hotel" style="width: 100%; height: 300px; object-fit: cover; border-radius: 0.5rem;">
            </div>
        </div>
    </div>
</section>

<!-- Featured Rooms -->
<section class="section dark">
    <div class="container">
        <h2 style="color: white; margin-bottom: 2rem; text-align: center;">Pilihan Kamar Premium</h2>
        
        <div class="grid grid-3">
            <?php foreach ($featuredRooms as $room): ?>
            <div class="card room-card">
                <div class="card-image" style="background: <?php echo htmlize($room['color_code'] ?? '#667eea'); ?>; display: flex; align-items: center; justify-content: center; color: white; font-size: 3rem;">
                    🏨
                </div>
                <div class="card-body">
                    <h3 class="card-title"><?php echo htmlize($room['type_name']); ?></h3>
                    <p class="card-text"><?php echo htmlize($room['description'] ?? 'Kamar dengan fasilitas lengkap dan kenyamanan maksimal'); ?></p>
                    <div style="margin: 1rem 0;">
                        <div class="card-price"><?php echo formatCurrency($room['base_price']); ?></div>
                        <div style="font-size: 0.85rem; color: #999; margin-top: 0.25rem;">per malam</div>
                    </div>
                    <a href="<?php echo baseUrl('booking.php?room=' . $room['id']); ?>" class="btn btn-primary btn-block">Pesan Sekarang</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Amenities Section -->
<section class="section">
    <div class="container">
        <h2 style="text-align: center; margin-bottom: 2rem;">Fasilitas Lengkap</h2>
        
        <div class="grid grid-4">
            <?php
            $amenities = [
                ['icon' => '🏊', 'name' => 'Kolam Renang Infinity', 'desc' => 'Nikmati pemandangan laut sambil berenang'],
                ['icon' => '🍽️', 'name' => 'Restoran & Bar', 'desc' => 'Menu internasional dan lokal berkualitas'],
                ['icon' => '🧘', 'name' => 'Spa & Wellness', 'desc' => 'Perawatan tubuh dengan teknik tradisional'],
                ['icon' => '🎾', 'name' => 'Water Sports', 'desc' => 'Aktivitas air seru untuk keluarga'],
                ['icon' => '📶', 'name' => 'WiFi Gratis', 'desc' => 'Internet cepat di seluruh area resort'],
                ['icon' => '🚗', 'name' => 'Parkir Gratis', 'desc' => 'Parkir aman untuk semua tamu'],
                ['icon' => '🎕', 'name' => 'Concierge 24/7', 'desc' => 'Layanan tamu sepanjang waktu'],
                ['icon' => '🏋️', 'name' => 'Fitness Center', 'desc' => 'Gym modern dengan peralatan terlengkap'],
            ];
            
            foreach ($amenities as $amenity):
            ?>
            <div class="amenity-card">
                <div class="amenity-icon"><?php echo $amenity['icon']; ?></div>
                <h4><?php echo $amenity['name']; ?></h4>
                <p><?php echo $amenity['desc']; ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Call to Action -->
<section class="section dark">
    <div class="container" style="text-align: center;">
        <h2 style="color: white; margin-bottom: 1rem;">Siap untuk Liburan Impian Anda?</h2>
        <p style="color: rgba(255, 255, 255, 0.8); margin-bottom: 2rem; font-size: 1.1rem;">
            Pesan kamar Anda sekarang dan dapatkan penawaran spesial untuk early bird
        </p>
        <a href="<?php echo baseUrl('booking.php'); ?>" class="btn btn-primary" style="font-size: 1.1rem; padding: 1rem 2rem;">
            Mulai Booking Sekarang
        </a>
    </div>
</section>

<!-- Contact Section -->
<section class="section">
    <div class="container">
        <h2 style="text-align: center; margin-bottom: 2rem;">Hubungi Kami</h2>
        
        <div class="grid grid-2">
            <div>
                <h3>Informasi Kontak</h3>
                <p>
                    <strong>Alamat:</strong><br>
                    <?php echo htmlize(getConfig('address')); ?>
                </p>
                <p>
                    <strong>Telepon:</strong><br>
                    <a href="tel:<?php echo htmlize(getConfig('phone')); ?>">
                        <?php echo htmlize(getConfig('phone')); ?>
                    </a>
                </p>
                <p>
                    <strong>Email:</strong><br>
                    <a href="mailto:<?php echo htmlize(getConfig('email')); ?>">
                        <?php echo htmlize(getConfig('email')); ?>
                    </a>
                </p>
                <p>
                    <strong>Jam Layanan:</strong><br>
                    Senin - Minggu: 08:00 - 22:00 WIB
                </p>
            </div>
            
            <div>
                <h3>Kirim Pesan</h3>
                <form class="contact-form" onsubmit="handleContactForm(event)">
                    <div class="form-group">
                        <input type="text" placeholder="Nama Anda" required>
                    </div>
                    <div class="form-group">
                        <input type="email" placeholder="Email Anda" required>
                    </div>
                    <div class="form-group">
                        <textarea placeholder="Pesan Anda" rows="4"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Kirim Pesan</button>
                </form>
            </div>
        </div>
    </div>
</section>

<?php include './includes/footer.php'; ?>
