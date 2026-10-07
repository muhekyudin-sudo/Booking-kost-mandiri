<?php
require dirname(__DIR__) . '/app/db.php';
$pdo = db();

// Akun contoh (password di-hash)
$ins = $pdo->prepare("INSERT INTO users (name,email,password,phone,role,created_at,updated_at) VALUES (?,?,?,?,?,NOW(),NOW())");
$ins->execute(['Admin Kostomah', 'admin@kostomah.test',  password_hash('admin12345',  PASSWORD_BCRYPT), '081200000001', 'admin']);
$ins->execute(['Budi Penyewa',   'tenant@kostomah.test', password_hash('tenant12345', PASSWORD_BCRYPT), '081200000002', 'tenant']);

// Kota
$city = $pdo->prepare("INSERT INTO cities (name,slug,created_at,updated_at) VALUES (?,?,NOW(),NOW())");
$city->execute(['Yogyakarta', 'yogyakarta']); $yogya = (int)$pdo->lastInsertId();
$city->execute(['Bandung',    'bandung']);    $bdg   = (int)$pdo->lastInsertId();

// Gedung
$prop = $pdo->prepare("INSERT INTO properties (city_id,name,address,description,created_at,updated_at) VALUES (?,?,?,?,NOW(),NOW())");
$prop->execute([$yogya, 'Kostomah Malioboro', 'Jl. Contoh No. 1, Yogyakarta', 'Dekat pusat kota dan stasiun.']); $p1 = (int)$pdo->lastInsertId();
$prop->execute([$bdg,   'Kostomah Dago',      'Jl. Contoh No. 2, Bandung',    'Suasana sejuk, dekat kampus.']);   $p2 = (int)$pdo->lastInsertId();

// Kelas kamar + harga + unit
$cls   = $pdo->prepare("INSERT INTO room_classes (property_id,name,description,facilities,created_at,updated_at) VALUES (?,?,?,?,NOW(),NOW())");
$price = $pdo->prepare("INSERT INTO price_matrices (room_class_id,daily_price,weekly_price,monthly_price,yearly_price,created_at,updated_at) VALUES (?,?,?,?,?,NOW(),NOW())");
$unit  = $pdo->prepare("INSERT INTO room_units (room_class_id,room_number,status,created_at,updated_at) VALUES (?,?,?,NOW(),NOW())");

$data = [
  [$p1, 'Standard',  'Kamar nyaman untuk mahasiswa.', ['Kasur','Lemari','Wifi'],                        [100000,  500000, 1200000, 13000000], ['101','102','103']],
  [$p1, 'Deluxe',    'Lebih luas dengan AC.',         ['AC','Wifi','Kamar Mandi Dalam'],                [150000,  800000, 1800000, 20000000], ['201','202']],
  [$p2, 'Executive', 'Paling lengkap.',               ['AC','Wifi','Kamar Mandi Dalam','Water Heater'], [200000, 1100000, 2500000, 28000000], ['301','302']],
];
foreach ($data as [$pid, $name, $desc, $fac, $pr, $rooms]) {
    $cls->execute([$pid, $name, $desc, json_encode($fac)]);
    $cid = (int)$pdo->lastInsertId();
    $price->execute([$cid, ...$pr]);
    foreach ($rooms as $r) $unit->execute([$cid, $r, 'available']);
}
echo "Data contoh berhasil dibuat.\n";