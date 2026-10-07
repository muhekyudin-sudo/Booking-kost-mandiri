<?php
function page_catalog(): void
{
    $cityId  = (int)($_GET['city_id'] ?? 0);
    $classId = (int)($_GET['room_class_id'] ?? 0);

    $where  = [];
    $params = [];
    if ($cityId > 0)  { $where[] = 'c.id = ?';  $params[] = $cityId; }
    if ($classId > 0) { $where[] = 'rc.id = ?'; $params[] = $classId; }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "SELECT p.id AS property_id, p.name AS property_name, p.address,
                   c.name AS city_name,
                   rc.id AS class_id, rc.name AS class_name, rc.facilities,
                   pm.daily_price, pm.weekly_price, pm.monthly_price, pm.yearly_price,
                   (SELECT COUNT(*) FROM room_units ru
                     WHERE ru.room_class_id = rc.id AND ru.status = 'available') AS available_units
            FROM room_classes rc
            JOIN properties p ON p.id = rc.property_id
            JOIN cities c     ON c.id = p.city_id
            LEFT JOIN price_matrices pm ON pm.room_class_id = rc.id
            $whereSql
            ORDER BY c.name, p.name, rc.id";
    $st = db()->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll();

    $cities  = db()->query('SELECT id, name FROM cities ORDER BY name')->fetchAll();
    $classes = db()->query(
        'SELECT rc.id, rc.name, p.name AS property_name
           FROM room_classes rc JOIN properties p ON p.id = rc.property_id
          ORDER BY p.name, rc.id'
    )->fetchAll();

    view('catalog/index', [
        'title' => 'Katalog Kost', 'rows' => $rows, 'cities' => $cities,
        'classes' => $classes, 'cityId' => $cityId, 'classId' => $classId,
    ]);
}

function page_property(int $id): void
{
    $st = db()->prepare(
        'SELECT p.*, c.name AS city_name FROM properties p JOIN cities c ON c.id = p.city_id WHERE p.id = ?'
    );
    $st->execute([$id]);
    $property = $st->fetch();
    if (!$property) {
        http_response_code(404);
        view('404', ['title' => 'Gedung tidak ditemukan']);
        return;
    }

    $st = db()->prepare(
        'SELECT rc.*, pm.daily_price, pm.weekly_price, pm.monthly_price, pm.yearly_price
           FROM room_classes rc LEFT JOIN price_matrices pm ON pm.room_class_id = rc.id
          WHERE rc.property_id = ? ORDER BY rc.id'
    );
    $st->execute([$id]);
    $classes = $st->fetchAll();

    $st = db()->prepare(
        'SELECT ru.* FROM room_units ru JOIN room_classes rc ON rc.id = ru.room_class_id
          WHERE rc.property_id = ? ORDER BY ru.room_number'
    );
    $st->execute([$id]);
    $unitsByClass = [];
    foreach ($st->fetchAll() as $u) {
        $unitsByClass[$u['room_class_id']][] = $u;
    }

    view('catalog/property', [
        'title' => $property['name'], 'property' => $property,
        'classes' => $classes, 'unitsByClass' => $unitsByClass,
    ]);
}