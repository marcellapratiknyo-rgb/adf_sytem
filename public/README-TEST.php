<?php
/**
 * QUICK TESTING GUIDE & LINKS
 * Access this file to start testing the booking website
 */
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Testing Guide - Narayana Hotel Website</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 2rem;
            border-radius: 1rem 1rem 0 0;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .header h1 {
            color: #1e293b;
            margin-bottom: 0.5rem;
        }
        
        .header p {
            color: #64748b;
            font-size: 1.1rem;
        }
        
        .content {
            background: white;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .section {
            margin-bottom: 2rem;
        }
        
        .section h2 {
            color: #1e293b;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .links-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .link-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 1.5rem;
            border-radius: 0.75rem;
            text-decoration: none;
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .link-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.4);
        }
        
        .link-card h3 {
            margin-bottom: 0.5rem;
            font-size: 1.25rem;
        }
        
        .link-card p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.95rem;
        }
        
        .steps {
            background: #f0f4ff;
            border-left: 4px solid #667eea;
            padding: 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
        }
        
        .steps ol {
            margin-left: 1.5rem;
            color: #1e293b;
        }
        
        .steps li {
            margin-bottom: 0.75rem;
            line-height: 1.6;
        }
        
        .steps strong {
            color: #667eea;
        }
        
        .test-data {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .test-data strong {
            color: #1e293b;
            display: block;
            margin-bottom: 0.5rem;
        }
        
        .test-data code {
            background: #1e293b;
            color: #e2e8f0;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-family: 'Courier New', monospace;
        }
        
        .footer {
            background: #1e293b;
            color: white;
            padding: 2rem;
            border-radius: 0 0 1rem 1rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            font-size: 0.9rem;
            text-align: center;
        }
        
        .status-chip {
            display: inline-block;
            background: #10b981;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🧪 Testing Guide - Narayana Hotel Website</h1>
            <p>Local development environment testing</p>
            <span class="status-chip">✅ Ready to Test</span>
        </div>
        
        <div class="content">
            <!-- Quick Links -->
            <div class="section">
                <h2>🔗 Quick Links</h2>
                <div class="links-grid">
                    <a href="../" class="link-card">
                        <h3>🏠 Homepage</h3>
                        <p>View hotel brochure & featured rooms</p>
                    </a>
                    <a href="../booking.php" class="link-card">
                        <h3>📋 Booking Page</h3>
                        <p>Test complete booking flow</p>
                    </a>
                    <a href="test.php" class="link-card">
                        <h3>🧪 Verification Test</h3>
                        <p>Check database & config status</p>
                    </a>
                </div>
            </div>
            
            <!-- Testing Steps -->
            <div class="section">
                <h2>📝 Testing Checklist</h2>
                
                <div class="steps">
                    <h3 style="color: #667eea; margin-bottom: 1rem;">Homepage Testing</h3>
                    <ol>
                        <li><strong>Visual Check</strong>
                            <ul style="margin-left: 1.5rem; margin-top: 0.5rem;">
                                <li>Hero section dengan search bar ditampilkan</li>
                                <li>Featured rooms section tampil dengan 6 kamar</li>
                                <li>Navigation menu responsive</li>
                                <li>Footer terlihat sempurna</li>
                            </ul>
                        </li>
                        <li><strong>Mobile Responsive</strong>
                            <ul style="margin-left: 1.5rem; margin-top: 0.5rem;">
                                <li>Resize browser untuk testing mobile (375px width)</li>
                                <li>Menu mobile toggle berfungsi</li>
                                <li>Search bar responsive</li>
                            </ul>
                        </li>
                    </ol>
                </div>
                
                <div class="steps">
                    <h3 style="color: #667eea; margin-bottom: 1rem;">Booking Flow Testing</h3>
                    <ol>
                        <li><strong>Search Availability</strong>
                            <ul style="margin-left: 1.5rem; margin-top: 0.5rem;">
                                <li>Pilih check-in: <code>besok</code></li>
                                <li>Pilih check-out: <code>3 hari kemudian</code></li>
                                <li>Tekan "Cari Ketersediaan"</li>
                                <li>API response muncul (list kamar) ✓</li>
                            </ul>
                        </li>
                        <li><strong>Select Room</strong>
                            <ul style="margin-left: 1.5rem; margin-top: 0.5rem;">
                                <li>Klik salah satu kamar dari list</li>
                                <li>Kamar ter-highlight (border biru)</li>
                                <li>Form data tamu muncul di sebelah kanan</li>
                                <li>Summary otomatis terisi (harga, malam, dll)</li>
                            </ul>
                        </li>
                        <li><strong>Fill Guest Info</strong>
                            <ul style="margin-left: 1.5rem; margin-top: 0.5rem;">
                                <li>Input nama: <code>Budi Santoso</code></li>
                                <li>Input email: <code>budi@email.com</code></li>
                                <li>Input phone: <code>081234567890</code></li>
                                <li>Input request (opsional)</li>
                            </ul>
                        </li>
                        <li><strong>Create Booking</strong>
                            <ul style="margin-left: 1.5rem; margin-top: 0.5rem;">
                                <li>Klik "Lanjutkan ke Pembayaran"</li>
                                <li>Check konsol browser (F12) untuk response</li>
                                <li>Cek database: booking baru harus tercipta ✓</li>
                                <li>Guest baru harus tercipta ✓</li>
                            </ul>
                        </li>
                    </ol>
                </div>
            </div>
            
            <!-- Test Data -->
            <div class="section">
                <h2>🗂️ Test Data yang Tersedia</h2>
                
                <div class="test-data">
                    <strong>Room Types (3 tipe):</strong>
                    Buka database untuk melihat, atau akses <code>GET /api/get-available-rooms.php</code>
                </div>
                
                <div class="test-data">
                    <strong>Rooms (12 kamar):</strong>
                    Sudah ada di database, siap untuk di-book
                </div>
                
                <div class="test-data">
                    <strong>Test Dates:</strong>
                    <code>Check In: <?php echo date('Y-m-d', strtotime('+1 day')); ?></code> → 
                    <code>Check Out: <?php echo date('Y-m-d', strtotime('+4 days')); ?></code><br>
                    (Atau gunakan tanggal lain, asalkan depan hari ini)
                </div>
            </div>
            
            <!-- API Testing -->
            <div class="section">
                <h2>🔌 API Testing (Browser Console)</h2>
                
                <div class="steps">
                    <p>Buka browser console (F12) dan copy paste:</p>
                    <code style="display: block; margin: 1rem 0; padding: 1rem; background: #1e293b; color: #e2e8f0; border-radius: 0.5rem; overflow-x: auto;">
fetch('/adf_system/public/api/get-available-rooms.php?check_in=<?php echo date('Y-m-d', strtotime('+1 day')); ?>&check_out=<?php echo date('Y-m-d', strtotime('+4 days')); ?>&guests=2')<br>
.then(r => r.json())<br>
.then(d => console.log(d))
                    </code>
                    <p style="margin-top: 1rem; color: #64748b;">
                        Akan menampilkan list kamar yang tersedia dalam format JSON
                    </p>
                </div>
            </div>
            
            <!-- Known Issues / Notes -->
            <div class="section">
                <h2>⚠️ Catatan Penting</h2>
                <div style="background: #fffbeb; border-left: 4px solid #f59e0b; padding: 1.5rem; border-radius: 0.5rem;">
                    <p style="margin-bottom: 1rem;">
                        <strong>Payment Gateway:</strong> Belum diintegrasikan. Button "Lanjutkan ke Pembayaran" akan show notification sukses tapi belum proses pembayaran ke Midtrans.
                    </p>
                    <p>
                        <strong>Database:</strong> Data booking akan masuk ke <code>bookings</code> dan <code>guests</code> table. Cek dengan:<br>
                        <code>SELECT * FROM bookings ORDER BY id DESC LIMIT 1;</code>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p>💡 Tips: Buka browser DevTools (F12) untuk melihat console logs dan network requests</p>
            <p style="margin-top: 0.5rem;">Build Date: <?php echo date('d M Y H:i'); ?></p>
        </div>
    </div>
</body>
</html>
