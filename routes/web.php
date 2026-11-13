<?php
/** @var Router $router */

$router->get('/',       [CatalogController::class, 'index']);
$router->get('/katalog/gedung/{property_id}', [CatalogController::class, 'property']);
$router->get('/booking/checkout/{unit_id}', [BookingController::class, 'checkout']);
$router->post('/booking/checkout/{unit_id}', [BookingController::class, 'store']);
$router->get('/files/bukti/{payment_id}', [BookingController::class, 'proofFile'], ['auth']);
$router->get('/login',  [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/register', [AuthController::class, 'showRegister']);
$router->post('/register', [AuthController::class, 'register']);
$router->post('/logout',[AuthController::class, 'logout'], ['auth']);
$router->get('/accountmenu/security.php', [AccountMenuController::class, 'security'], ['auth']);
$router->post('/accountmenu/security/password', [PasswordResetController::class, 'requestForCurrentUser'], ['auth']);
$router->get('/accountmenu/settings.php', [AccountMenuController::class, 'settings'], ['auth']);
$router->post('/accountmenu/settings/profile', [AccountMenuController::class, 'updateProfile'], ['auth']);
// Lupa password lewat email (publik)
$router->get('/lupa-password', [PasswordResetController::class, 'showForgot']);
$router->post('/lupa-password', [PasswordResetController::class, 'forgot']);
$router->get('/reset-password', [PasswordResetController::class, 'showReset']);
$router->post('/reset-password', [PasswordResetController::class, 'reset']);

$router->group('/tenant', ['auth', 'role:tenant'], function (Router $r) {
    $r->get('', [DashboardController::class, 'tenant']);
    $r->get('/bookings', [BookingController::class, 'myBookings']);
    $r->get('/bookings/{booking_id}', [BookingController::class, 'show']);
    $r->post('/bookings/{booking_id}/settlement', [BookingController::class, 'startSettlement']);
    $r->post('/payments/{payment_id}/proof', [BookingController::class, 'uploadProof']);
});

$router->group('/admin', ['auth', 'role:admin'], function (Router $r) {
    $r->get('', [DashboardController::class, 'admin']);
    $r->get('/master/{resource}', [AdminController::class, 'masterData']);
    $r->post('/master/{resource}', [AdminController::class, 'storeMasterData']);
    $r->post('/master/{resource}/{id}', [AdminController::class, 'updateMasterData']);
    $r->post('/master/{resource}/{id}/delete', [AdminController::class, 'deleteMasterData']);

    // Modul transaksi: Admin hanya GET (middleware readonly)
    $r->group('/bookings', ['readonly'], function (Router $r) {
        $r->get('', [AdminController::class, 'transactions']);
        $r->post('/{booking_id}/approve', function () { echo 'TIDAK BOLEH tercapai oleh Admin'; });
    });
});

$router->group('/resepsionis', ['auth', 'role:receptionist', 'property'], function (Router $r) {
    $r->get('/pilih-petugas', [StaffController::class, 'select']);
    $r->post('/pilih-petugas', [StaffController::class, 'storeSelection']);
    $r->post('/ganti-petugas', [StaffController::class, 'change']);

    $r->group('', ['staff'], function (Router $r) {
        $r->get('', [DashboardController::class, 'receptionist']);
        $r->get('/bookings', [ReceptionistController::class, 'bookings']);
        $r->post('/payments/{payment_id}/approve', [ReceptionistController::class, 'approve']);
        $r->post('/payments/{payment_id}/reject', [ReceptionistController::class, 'reject']);
        $r->post('/bookings/{booking_id}/cancel', [ReceptionistController::class, 'cancel']);
        $r->post('/bookings/{booking_id}/settle-cash', [ReceptionistController::class, 'settleCash']);
        $r->get('/kasir', [ReceptionistController::class, 'cashier']);
        $r->post('/kasir', [ReceptionistController::class, 'storeCashier']);
    });
});
