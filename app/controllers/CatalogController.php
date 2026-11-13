<?php

declare(strict_types=1);

final class CatalogController
{
    public function index(array $params): void
    {
        BookingService::expirePending();
        $cityId = $this->queryId('city_id');
        $provinceId = $this->queryId('province_id');
        $className = $this->queryClassName();
        $kostType = $this->queryKostType();
        $where = [];
        $bindings = [];
        if ($cityId !== null) {
            $where[] = 'p.city_id = ?';
            $bindings[] = $cityId;
        }
        if ($className !== null) {
            $where[] = 'EXISTS (SELECT 1 FROM room_classes selected_class WHERE selected_class.property_id = p.id AND selected_class.name = ?)';
            $bindings[] = $className;
        }
        if ($kostType !== null) {
            $where[] = 'p.kost_type = ?';
            $bindings[] = $kostType;
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $showCities = $cityId === null && $className === null && $kostType === null;
        $properties = [];
        $cities = [];
        if ($showCities) {
            $cityWhere = 'EXISTS (SELECT 1 FROM properties p WHERE p.city_id = c.id)';
            $cityBindings = [];
            if ($provinceId !== null) {
                $cityWhere .= ' AND c.province_id = ?';
                $cityBindings[] = $provinceId;
            }

            $stmt = db()->prepare(
                "SELECT c.id, c.name, c.slug, c.description, c.image,
                        (SELECT COUNT(*) FROM properties p WHERE p.city_id = c.id) AS property_count,
                        (SELECT COUNT(*)
                         FROM properties p
                         JOIN room_classes rc ON rc.property_id = p.id
                         JOIN room_units ru ON ru.room_class_id = rc.id
                         WHERE p.city_id = c.id AND ru.status = 'available') AS available_units,
                        --  harga bulanan mulai dari ...-.... di kartu kota
                         (SELECT MIN(pm.monthly_price) 
                         FROM properties p
                         JOIN room_classes rc ON rc.property_id = p.id
                         JOIN price_matrices pm ON pm.room_class_id = rc.id
                         WHERE p.city_id = c.id AND pm.monthly_price IS NOT NULL) AS min_monthly_price,
                        (SELECT MAX(pm.monthly_price)
                         FROM properties p
                         JOIN room_classes rc ON rc.property_id = p.id
                         JOIN price_matrices pm ON pm.room_class_id = rc.id
                         WHERE p.city_id = c.id AND pm.monthly_price IS NOT NULL) AS max_monthly_price
                 FROM cities c
                 WHERE $cityWhere
                 ORDER BY c.name"
            );
            $stmt->execute($cityBindings);
            $cities = $stmt->fetchAll();
            foreach ($cities as &$city) {
                $city['photo_url'] = $this->cityPhotoUrl(
                    is_string($city['image']) ? $city['image'] : null,
                    (string) $city['slug']
                );
            }
            unset($city);
        } else {
            $stmt = db()->prepare(
                "SELECT p.id, p.name, p.address, p.kost_type, p.image, c.name AS city_name,
                        EXISTS (
                            SELECT 1 FROM users u
                            WHERE u.property_id = p.id AND u.role = 'receptionist' AND u.is_active = 1
                              AND EXISTS (
                                  SELECT 1 FROM receptionist_staff rs
                                  WHERE rs.property_id = p.id AND rs.is_active = 1
                              )
                        ) AS accepts_online,
                        (SELECT COUNT(*) FROM room_units ru
                         JOIN room_classes rc ON rc.id = ru.room_class_id
                         WHERE rc.property_id = p.id AND ru.status = 'available') AS available_units
                 FROM properties p
                 JOIN cities c ON c.id = p.city_id" . $whereSql . '
                 ORDER BY c.name, p.name'
            );
            $stmt->execute($bindings);
            $properties = $stmt->fetchAll();
            foreach ($properties as &$property) {
                $property['photo_url'] = $this->propertyPhotoUrl(
                    is_string($property['image']) ? $property['image'] : null
                );
            }
            unset($property);
        }

        $cityOptionsStmt = db()->prepare('SELECT id, name FROM cities ORDER BY name');
        $cityOptionsStmt->execute();
        $cityOptions = $cityOptionsStmt->fetchAll();
        $classOptionsSql = 'SELECT DISTINCT rc.name FROM room_classes rc';
        $classOptionBindings = [];
        if ($cityId !== null) {
            // Batasi daftar kelas ke gedung yang berada di kota terpilih.
            $classOptionsSql .= ' JOIN properties p ON p.id = rc.property_id WHERE p.city_id = ?';
            $classOptionBindings[] = $cityId;
        }
        $classOptionsSql .= " ORDER BY FIELD(rc.name, 'Kosongan', 'Standard', 'Deluxe', 'Executive'), rc.name";
        $classOptionsStmt = db()->prepare($classOptionsSql);
        $classOptionsStmt->execute($classOptionBindings);
        $classOptions = $classOptionsStmt->fetchAll();

        $classesByProperty = [];
        if ($properties) {
            $propertyIds = array_map(static fn(array $property): int => (int) $property['id'], $properties);
            $classesByProperty = $this->classesForProperties($propertyIds, $className);
        }
        $selectedCityName = null;
        foreach ($cityOptions as $cityOption) {
            if ((int) $cityOption['id'] === $cityId) {
                $selectedCityName = $cityOption['name'];
                break;
            }
        }

        // Daftar provinsi untuk bulatan (hanya yang punya kota berisi gedung).
        $provinces = db()->query(
            "SELECT pr.id, pr.name, pr.slug, pr.image
             FROM provinces pr
             WHERE EXISTS (
                 SELECT 1 FROM cities c
                 JOIN properties p ON p.city_id = c.id
                 WHERE c.province_id = pr.id
             )
             ORDER BY pr.name"
        )->fetchAll();

        // Tambahkan alamat foto untuk tiap provinsi (null kalau belum ada foto).
        foreach ($provinces as &$pr) {
            $pr['photo_url'] = $this->provincePhotoUrl(
                is_string($pr['image']) ? $pr['image'] : null
            );
        }
        unset($pr);

        $selectedProvinceName = null;
        foreach ($provinces as $pr) {
            if ((int) $pr['id'] === $provinceId) {
                $selectedProvinceName = $pr['name'];
                break;
            }
        }
        // Angka untuk 3 kartu statistik di atas katalog.
        $statsStmt = db()->prepare(
            "SELECT (SELECT COUNT(*) FROM properties) AS gedung,
            (SELECT COUNT(DISTINCT city_id) FROM properties) AS kota,
            (SELECT COUNT(*) FROM room_units WHERE status = 'available') AS unit"
        );
        $statsStmt->execute();
        $stats = $statsStmt->fetch();

        view('catalog/index', [
            'extraCss' => ['/assets/css/filters.css', '/assets/css/catalog.css'],
            'stats' => $stats,
            'cities' => $cities,
            'properties' => $properties,
            'classesByProperty' => $classesByProperty,
            'cityOptions' => $cityOptions,
            'classOptions' => $classOptions,
            'selectedCity' => $cityId,
            'selectedCityName' => $selectedCityName,
            'selectedClass' => $className,
            'showCities' => $showCities,
            'provinces' => $provinces,
            'selectedProvince' => $provinceId,
            'selectedProvinceName' => $selectedProvinceName,
            'selectedKostType' => $kostType,
        ]);
    }

    public function property(array $params): void
    {
        BookingService::expirePending();
        $propertyId = filter_var($params['property_id'] ?? null, FILTER_VALIDATE_INT);
        if ($propertyId === false || $propertyId === null || $propertyId < 1) abort(404);

        $stmt = db()->prepare(
            "SELECT p.id, p.name, p.address, p.kost_type, p.description, p.image, c.name AS city_name,
                    EXISTS (
                        SELECT 1 FROM users u
                        WHERE u.property_id = p.id AND u.role = 'receptionist' AND u.is_active = 1
                          AND EXISTS (
                              SELECT 1 FROM receptionist_staff rs
                              WHERE rs.property_id = p.id AND rs.is_active = 1
                          )
                    ) AS accepts_online
             FROM properties p
             JOIN cities c ON c.id = p.city_id
             WHERE p.id = ? LIMIT 1"
        );
        $stmt->execute([$propertyId]);
        $property = $stmt->fetch();
        if (!$property) abort(404);
        $property['photo_url'] = $this->propertyPhotoUrl(
            is_string($property['image']) ? $property['image'] : null
        );

        $classes = $this->classesForProperties([(int) $propertyId]);
        view('catalog/property', [
            'extraCss' => ['/assets/css/catalog.css'],
            'property' => $property,
            'classes' => $classes[(int) $propertyId] ?? [],
        ]);
    }

    private function classesForProperties(array $propertyIds, ?string $className = null): array
    {
        if (!$propertyIds) return [];
        $placeholders = implode(', ', array_fill(0, count($propertyIds), '?'));
        $bindings = $propertyIds;
        $classFilter = '';
        if ($className !== null) {
            $classFilter = ' AND rc.name = ?';
            $bindings[] = $className;
        }
        $stmt = db()->prepare(
            "SELECT rc.id, rc.property_id, rc.name, rc.description, rc.facilities, rc.image,
                    pm.daily_price, pm.weekly_price, pm.monthly_price, pm.yearly_price,
                    (SELECT COUNT(*) FROM room_units ru
                     WHERE ru.room_class_id = rc.id AND ru.status = 'available') AS available_count
             FROM room_classes rc
             LEFT JOIN price_matrices pm ON pm.room_class_id = rc.id
             WHERE rc.property_id IN ($placeholders)$classFilter
             ORDER BY rc.property_id, rc.name"
        );
        $stmt->execute($bindings);
        $classes = $stmt->fetchAll();
        $classIds = array_map(static fn(array $class): int => (int) $class['id'], $classes);
        $unitsByClass = [];
        if ($classIds) {
            $unitPlaceholders = implode(', ', array_fill(0, count($classIds), '?'));
            $unitsStmt = db()->prepare(
                "SELECT id, room_class_id, room_number
                 FROM room_units
                 WHERE status = 'available' AND room_class_id IN ($unitPlaceholders)
                 ORDER BY room_class_id, room_number"
            );
            $unitsStmt->execute($classIds);
            foreach ($unitsStmt->fetchAll() as $unit) {
                $unitsByClass[(int) $unit['room_class_id']][] = $unit;
            }
        }

        $result = [];
        foreach ($classes as $class) {
            $class['facilities_list'] = json_decode((string) ($class['facilities'] ?? '[]'), true);
            if (!is_array($class['facilities_list']) || !array_is_list($class['facilities_list'])) {
                $class['facilities_list'] = [];
            }
            $class['available_units'] = $unitsByClass[(int) $class['id']] ?? [];
            $class['photo_url'] = $this->classPhotoUrl(
                is_string($class['image'] ?? null) ? $class['image'] : null
            );
            $result[(int) $class['property_id']][] = $class;
        }
        return $result;
    }
    private function provincePhotoUrl(?string $image): ?string
    {
        $directory = __DIR__ . '/../../public/assets/img/provinsi/';
        if (
            $image !== null && preg_match('/\A[a-z0-9_-]{1,200}\.(?:jpe?g|png|webp)\z/i', $image)
            && is_file($directory . $image)
        ) {
            return url('/assets/img/provinsi/' . rawurlencode($image));
        }
        return null;
    }

    private function queryId(string $name): ?int
    {
        if (!array_key_exists($name, $_GET) || $_GET[$name] === '') return null;
        $value = filter_var($_GET[$name], FILTER_VALIDATE_INT);
        if ($value === false || $value < 1) abort(422, 'Filter katalog tidak valid.');
        return $value;
    }

    private function cityPhotoUrl(?string $image, string $slug): ?string
    {
        $directory = __DIR__ . '/../../public/assets/img/kota/';
        if (
            $image !== null && preg_match('/\A[a-z0-9_-]{1,200}\.(?:jpe?g|png|webp)\z/i', $image)
            && is_file($directory . $image)
        ) {
            return url('/assets/img/kota/' . rawurlencode($image));
        }

        // Foto lama yang diberi nama sesuai slug tetap didukung.
        $safeSlug = preg_replace('/[^a-z0-9-]/i', '', $slug);
        if ($safeSlug === '') return null;

        foreach (['jpg', 'jpeg', 'png', 'webp'] as $extension) {
            if (is_file($directory . $safeSlug . '.' . $extension)) {
                return url('/assets/img/kota/' . $safeSlug . '.' . $extension);
            }
        }
        return null;
    }
    private function propertyPhotoUrl(?string $image): ?string
    {
        $directory = __DIR__ . '/../../public/assets/img/gedung/';
        if (
            $image !== null && preg_match('/\A[a-z0-9_-]{1,200}\.(?:jpe?g|png|webp)\z/i', $image)
            && is_file($directory . $image)
        ) {
            return url('/assets/img/gedung/' . rawurlencode($image));
        }
        return null;
    }
    private function classPhotoUrl(?string $image): ?string
    {
        $directory = __DIR__ . '/../../public/assets/img/kelas/';
        if (
            $image !== null && preg_match('/\A[a-z0-9_-]{1,200}\.(?:jpe?g|png|webp)\z/i', $image)
            && is_file($directory . $image)
        ) {
            return url('/assets/img/kelas/' . rawurlencode($image));
        }
        return null;
    }
    private function queryKostType(): ?string
    {
        $raw = $_GET['kost_type'] ?? '';
        if (!is_string($raw)) abort(422, 'Filter katalog tidak valid.');
        $value = trim($raw);
        if ($value === '') return null;
        if (!array_key_exists($value, kost_types())) abort(422, 'Filter katalog tidak valid.');
        return $value;
    }

    private function queryClassName(): ?string
    {
        $raw = $_GET['room_class'] ?? '';
        if (!is_string($raw)) abort(422, 'Filter katalog tidak valid.');
        $value = trim($raw);
        if ($value === '') return null;
        if (mb_strlen($value) > 255) abort(422, 'Filter katalog tidak valid.');
        return $value;
    }
}
