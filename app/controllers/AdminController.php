<?php

declare(strict_types=1);

final class AdminController
{
    public function transactions(array $params): void
    {
        $search = strtoupper(trim(is_string($_GET['pnr'] ?? null) ? $_GET['pnr'] : ''));
        if ($search !== '' && !preg_match('/^[23456789A-HJ-NP-Z]{6}$/', $search)) {
            flash('error', 'PNR harus terdiri dari 6 karakter yang valid.');
            redirect('/admin/bookings');
        }

        $stmt = db()->prepare(
            "SELECT b.id, b.pnr_code, b.status AS booking_status, b.booking_type, b.start_date, b.end_date,
                    b.total_amount, b.paid_amount, b.remaining_amount, b.payment_scheme, b.remaining_due_date,
                    u.name AS tenant_name, u.email AS tenant_email,
                    p.name AS property_name, rc.name AS room_class_name, ru.room_number, ru.status AS unit_status,
                    pay.id AS payment_id, pay.amount AS payment_amount, pay.payment_method,
                    pay.status AS payment_status, pay.proof_file, pay.rejection_reason,
                    approver.name AS approved_by_name, approver_staff.name AS approved_by_staff_name
             FROM bookings b
             JOIN users u ON u.id = b.user_id
             JOIN room_units ru ON ru.id = b.room_unit_id
             JOIN room_classes rc ON rc.id = ru.room_class_id
             JOIN properties p ON p.id = rc.property_id
             LEFT JOIN payments pay ON pay.id = (
                 SELECT MAX(p2.id) FROM payments p2 WHERE p2.booking_id = b.id
             )
             LEFT JOIN users approver ON approver.id = pay.approved_by_user_id
             LEFT JOIN receptionist_staff approver_staff ON approver_staff.id = pay.approved_by_staff_id
             WHERE (? = '' OR b.pnr_code = ?)
             ORDER BY b.id DESC"
        );
        $stmt->execute([$search, $search]);
        $bookings = $stmt->fetchAll();
        view('receptionist/bookings', [
            'extraCss' => ['/assets/css/filters.css', '/assets/css/booking.css'],
            'bookings' => $bookings,
            'paymentsByBooking' => BookingService::paymentsForBookings(array_column($bookings, 'id')),
            'search' => $search,
            'staffName' => '',
            'isAdmin' => true,
        ]);
    }

    private const RESOURCES = [
        'provinces' => [
            'title' => 'Provinsi',
            'table' => 'provinces',
            'fields' => [
                'name' => ['label' => 'Nama provinsi', 'type' => 'text', 'required' => true],
                'slug' => ['label' => 'Slug URL', 'type' => 'text', 'required' => true],
            ],
        ],
        'cities' => [
            'title' => 'Kota',
            'table' => 'cities',
            'fields' => [
                'province_id' => ['label' => 'Provinsi', 'type' => 'select', 'options' => 'provinces', 'required' => true],
                'name' => ['label' => 'Nama kota', 'type' => 'text', 'required' => true],
                'slug' => ['label' => 'Slug URL', 'type' => 'text', 'required' => true],
                'description' => ['label' => 'Deskripsi singkat', 'type' => 'textarea', 'max' => 255],
            ],
        ],
        'properties' => [
            'title' => 'Gedung',
            'table' => 'properties',
            'fields' => [
                'city_id' => ['label' => 'Kota', 'type' => 'select', 'options' => 'cities', 'required' => true],
                'name' => ['label' => 'Nama gedung', 'type' => 'text', 'required' => true],
                'address' => ['label' => 'Alamat', 'type' => 'textarea', 'required' => true],
                'kost_type' => [
                    'label' => 'Jenis kost',
                    'type' => 'select',
                    'values' => ['putra' => 'Kost Putra', 'putri' => 'Kost Putri', 'bebas' => 'Kost Bebas'],
                    'required' => true,
                ],
                'description' => ['label' => 'Deskripsi', 'type' => 'textarea'],
            ],
        ],
        'room-classes' => [
            'title' => 'Kelas Kamar',
            'table' => 'room_classes',
            'fields' => [
                'property_id' => ['label' => 'Gedung', 'type' => 'select', 'options' => 'properties', 'required' => true],
                'name' => ['label' => 'Nama kelas', 'type' => 'text', 'required' => true],
                'description' => ['label' => 'Deskripsi', 'type' => 'textarea'],
                'facilities' => ['label' => 'Fasilitas (JSON array)', 'type' => 'json'],
            ],
        ],
        'room-units' => [
            'title' => 'Unit Kamar',
            'table' => 'room_units',
            'fields' => [
                'room_class_id' => ['label' => 'Kelas kamar', 'type' => 'select', 'options' => 'room_classes', 'required' => true],
                'room_number' => ['label' => 'Nomor kamar', 'type' => 'text', 'max' => 50, 'required' => true],
                'status' => [
                    'label' => 'Status',
                    'type' => 'select',
                    'values' => ['available' => 'Tersedia', 'on_hold' => 'Ditahan', 'occupied' => 'Terisi', 'maintenance' => 'Perbaikan'],
                    'required' => true,
                ],
            ],
        ],
        'price-matrices' => [
            'title' => 'Matriks Harga',
            'table' => 'price_matrices',
            'fields' => [
                'room_class_id' => ['label' => 'Kelas kamar', 'type' => 'select', 'options' => 'room_classes', 'required' => true],
                'daily_price' => ['label' => 'Harga harian', 'type' => 'price'],
                'weekly_price' => ['label' => 'Harga mingguan', 'type' => 'price'],
                'monthly_price' => ['label' => 'Harga bulanan', 'type' => 'price'],
                'yearly_price' => ['label' => 'Harga tahunan', 'type' => 'price'],
            ],
        ],
        'receptionists' => [
            'title' => 'Akun Resepsionis',
            'table' => 'users',
            'fields' => [
                'name' => ['label' => 'Nama akun', 'type' => 'text', 'required' => true],
                'email' => ['label' => 'Email', 'type' => 'email', 'required' => true],
                'password' => ['label' => 'Password (kosongkan saat edit bila tidak diganti)', 'type' => 'password'],
                'property_id' => ['label' => 'Gedung', 'type' => 'select', 'options' => 'properties', 'required' => true],
                'is_active' => ['label' => 'Status akun', 'type' => 'active', 'required' => true],
            ],
        ],
        'staff' => [
            'title' => 'Petugas Jaga',
            'table' => 'receptionist_staff',
            'fields' => [
                'property_id' => ['label' => 'Gedung', 'type' => 'select', 'options' => 'properties', 'required' => true],
                'name' => ['label' => 'Nama petugas', 'type' => 'text', 'max' => 100, 'required' => true],
                'is_active' => ['label' => 'Status petugas', 'type' => 'active', 'required' => true],
            ],
        ],
    ];

    public function masterData(array $params): void
    {
        $resource = $this->resource((string) ($params['resource'] ?? ''));
        $config = self::RESOURCES[$resource];
        $fields = $config['fields'];
        $columns = array_merge(['id'], array_keys($fields));
        if ($this->hasImage($resource)) $columns[] = 'image';
        if ($resource === 'receptionists') $columns = ['id', 'name', 'email', 'property_id', 'is_active'];

        // ===== Pencarian & filter (dibuat otomatis dari konfigurasi field) =====
        $options = $this->options($fields);
        $conditions = [];
        $bindings = [];
        if ($resource === 'receptionists') $conditions[] = "role = 'receptionist'";

        // 1) Kotak cari: semua kolom bertipe teks/email pada resource ini.
        $searchColumns = [];
        foreach ($fields as $column => $field) {
            if (in_array($field['type'], ['text', 'email'], true)) $searchColumns[] = $column;
        }
        $search = '';
        if ($searchColumns !== [] && is_string($_GET['q'] ?? null)) {
            $search = mb_substr(trim($_GET['q']), 0, 100);
        }
        if ($search !== '') {
            $like = '%' . addcslashes($search, '\\%_') . '%';
            $parts = [];
            foreach ($searchColumns as $column) {
                $parts[] = "`$column` LIKE ?";
                $bindings[] = $like;
            }
            $conditions[] = '(' . implode(' OR ', $parts) . ')';
        }

        // 2) Dropdown filter: setiap field bertipe pilihan (provinsi, kota, gedung, status, dst).
        $filters = [];
        $filterValues = [];
        foreach ($fields as $column => $field) {
            if (!in_array($field['type'], ['select', 'active'], true)) continue;
            $choices = $field['type'] === 'active'
                ? ['1' => 'Aktif', '0' => 'Nonaktif']
                : ($options[$column] ?? []);
            $filters[] = ['name' => $column, 'label' => $field['label'], 'choices' => $choices];
            $picked = $_GET[$column] ?? '';
            // Nilai yang tidak ada di daftar pilihan diabaikan (aman dari input aneh).
            if (is_string($picked) && $picked !== '' && array_key_exists($picked, $choices)) {
                $filterValues[$column] = $picked;
                $conditions[] = "`$column` = ?";
                $bindings[] = $picked;
            }
        }

        $whereSql = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
        $stmt = db()->prepare(
            'SELECT ' . implode(', ', array_map(static fn(string $column): string => "`$column`", $columns))
                . " FROM `{$config['table']}`$whereSql ORDER BY id DESC"
        );
        $stmt->execute($bindings);

        // Jumlah seluruh data (tanpa filter) untuk teks "Menampilkan X dari Y".
        $totalSql = "SELECT COUNT(*) FROM `{$config['table']}`"
            . ($resource === 'receptionists' ? " WHERE role = 'receptionist'" : '');
        $totalCount = (int) db()->query($totalSql)->fetchColumn();
        $rows = $stmt->fetchAll();
        if ($this->hasImage($resource)) {
            foreach ($rows as &$row) {
                $row['image_url'] = $this->imageUrl($resource, $row['image'] ?? null);
            }
            unset($row);
        }
        view('admin/master-data', [
            'extraCss' => ['/assets/css/masterdata.css'],
            'resource' => $resource,
            'resourceTitle' => $config['title'],
            'resources' => array_map(
                static fn(array $item, string $key): array => ['key' => $key, 'title' => $item['title']],
                self::RESOURCES,
                array_keys(self::RESOURCES)
            ),
            'fields' => $fields,
            'rows' => $rows,
            'options' => $options,
            'searchable' => $searchColumns !== [],
            'search' => $search,
            'filters' => $filters,
            'filterValues' => $filterValues,
            'isFiltered' => $search !== '' || $filterValues !== [],
            'totalCount' => $totalCount,
        ]);
    }

    public function storeMasterData(array $params): void
    {
        $this->saveMasterData((string) ($params['resource'] ?? ''), null);
    }

    public function updateMasterData(array $params): void
    {
        $id = filter_var($params['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id === null || $id < 1) abort(404);
        $this->saveMasterData((string) ($params['resource'] ?? ''), $id);
    }

    public function deleteMasterData(array $params): void
    {
        $resource = $this->resource((string) ($params['resource'] ?? ''));
        $id = filter_var($params['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id === null || $id < 1) abort(404);
        $config = self::RESOURCES[$resource];

        $exists = db()->prepare("SELECT id FROM `{$config['table']}` WHERE id = ?" . ($resource === 'receptionists' ? " AND role = 'receptionist'" : '') . ' LIMIT 1');
        $exists->execute([$id]);
        if ($exists->fetchColumn() === false) abort(404);

        $oldImage = null;
        if ($this->hasImage($resource)) {
            $imageStmt = db()->prepare("SELECT image FROM `{$config['table']}` WHERE id = ?");
            $imageStmt->execute([$id]);
            $oldImage = $imageStmt->fetchColumn();
        }
        $this->assertDeletable($resource, $id);
        $stmt = db()->prepare("DELETE FROM `{$config['table']}` WHERE id = ?" . ($resource === 'receptionists' ? " AND role = 'receptionist'" : ''));
        $stmt->execute([$id]);
        if ($this->hasImage($resource) && is_string($oldImage) && $oldImage !== '') {
            $this->removeImageFile($resource, $oldImage);
        }
        flash('success', $config['title'] . ' berhasil dihapus.');
        redirect('/admin/master/' . $resource);
    }

    private function saveMasterData(string $resourceName, ?int $id): never
    {
        $resource = $this->resource($resourceName);
        $config = self::RESOURCES[$resource];
        $values = $this->validatedValues($resource, $id);
        $oldImage = null;
        $newImage = null;
        $imageChanged = false;

        if ($this->hasImage($resource)) {
            if ($id !== null) {
                $imageStmt = db()->prepare("SELECT image FROM `{$config['table']}` WHERE id = ?");
                $imageStmt->execute([$id]);
                $oldImage = $imageStmt->fetchColumn();
                if ($oldImage === false) abort(404);
            }

            $deleteImage = $_POST['delete_image'] ?? '';
            if (!is_string($deleteImage) || !in_array($deleteImage, ['', '1'], true)) {
                flash('error', 'Pilihan hapus foto tidak valid.');
                redirect('/admin/master/' . $resource);
            }

            $newImage = $this->storeImageUpload($resource);
            if ($newImage !== null) {
                $values['image'] = $newImage;
                $imageChanged = true;
            } elseif ($deleteImage === '1') {
                $values['image'] = null;
                $imageChanged = true;
            } elseif ($id === null) {
                $values['image'] = null;
            }
        }

        try {
            if ($id === null) {
                $columns = array_keys($values);
                $placeholders = array_fill(0, count($columns), '?');
                $sql = "INSERT INTO `{$config['table']}` (" . implode(', ', array_map(static fn(string $column): string => "`$column`", $columns)) . ') VALUES (' . implode(', ', $placeholders) . ')';
                try {
                    $stmt = db()->prepare($sql);
                    $stmt->execute(array_values($values));
                } catch (PDOException $e) {
                    if ($e->getCode() !== '23000') throw $e;
                    if ($newImage !== null) $this->removeImageFile($resource, $newImage);
                    error_log('Admin master-data insert rejected by a database constraint: ' . $e->getMessage());
                    flash('error', 'Data tidak dapat ditambahkan karena bertentangan dengan data yang sudah ada.');
                    redirect('/admin/master/' . $resource);
                }
            } else {
                $exists = db()->prepare("SELECT id FROM `{$config['table']}` WHERE id = ?" . ($resource === 'receptionists' ? " AND role = 'receptionist'" : '') . ' LIMIT 1');
                $exists->execute([$id]);
                if ($exists->fetchColumn() === false) abort(404);

                if ($values !== []) {
                    $assignments = implode(', ', array_map(static fn(string $column): string => "`$column` = ?", array_keys($values)));
                    $sql = "UPDATE `{$config['table']}` SET $assignments WHERE id = ?" . ($resource === 'receptionists' ? " AND role = 'receptionist'" : '');
                    try {
                        $stmt = db()->prepare($sql);
                        $stmt->execute([...array_values($values), $id]);
                    } catch (PDOException $e) {
                        if ($e->getCode() !== '23000') throw $e;
                        if ($newImage !== null) $this->removeImageFile($resource, $newImage);
                        error_log('Admin master-data update rejected by a database constraint: ' . $e->getMessage());
                        flash('error', 'Perubahan tidak dapat disimpan karena bertentangan dengan data yang sudah ada.');
                        redirect('/admin/master/' . $resource);
                    }
                }
            }
        } catch (Throwable $e) {
            if ($newImage !== null) $this->removeImageFile($resource, $newImage);
            throw $e;
        }

        if ($this->hasImage($resource) && $imageChanged && is_string($oldImage) && $oldImage !== '') {
            $this->removeImageFile($resource, $oldImage);
        }

        flash('success', $config['title'] . ($id === null ? ' berhasil ditambahkan.' : ' berhasil diperbarui.'));
        redirect('/admin/master/' . $resource);
    }

    private function validatedValues(string $resource, ?int $id): array
    {
        $config = self::RESOURCES[$resource];
        $values = [];
        foreach ($config['fields'] as $column => $field) {
            $raw = $_POST[$column] ?? '';
            if (is_array($raw)) {
                flash('error', 'Format data tidak valid.');
                redirect('/admin/master/' . $resource);
            }
            $value = $column === 'password' ? (string) $raw : trim((string) $raw);
            if ($column === 'password' && $id !== null && $value === '') continue;
            if ($value === '') {
                if (!empty($field['required']) || ($column === 'password' && $id === null)) {
                    flash('error', $field['label'] . ' wajib diisi.');
                    redirect('/admin/master/' . $resource);
                }
                $values[$column] = null;
                continue;
            }

            if ($field['type'] === 'text' || $field['type'] === 'textarea' || $field['type'] === 'email') {
                $maxLength = $field['max'] ?? (($column === 'address' || $column === 'description') ? 65535 : 255);
                if (
                    mb_strlen($value) > $maxLength
                    || (($column === 'address' || $column === 'description') && strlen($value) > 65535)
                ) {
                    flash('error', $field['label'] . ' terlalu panjang.');
                    redirect('/admin/master/' . $resource);
                }
                if ($field['type'] === 'email') {
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        flash('error', 'Format email tidak valid.');
                        redirect('/admin/master/' . $resource);
                    }
                    $value = mb_strtolower($value);
                }
                if ($column === 'slug' && !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value)) {
                    flash('error', 'Slug hanya boleh berisi huruf kecil, angka, dan tanda hubung.');
                    redirect('/admin/master/' . $resource);
                }
                $values[$column] = $value;
                continue;
            }

            if ($field['type'] === 'password') {
                if (mb_strlen($value) < 8) {
                    flash('error', 'Password minimal 8 karakter.');
                    redirect('/admin/master/' . $resource);
                }
                if (strlen($value) > 72) {
                    flash('error', 'Password maksimal 72 byte.');
                    redirect('/admin/master/' . $resource);
                }
                $hash = password_hash($value, PASSWORD_BCRYPT);
                if ($hash === false) throw new RuntimeException('Password could not be hashed.');
                $values['password'] = $hash;
                continue;
            }

            if ($field['type'] === 'select' || $field['type'] === 'active') {
                $allowed = $field['type'] === 'active'
                    ? ['0', '1']
                    : array_keys($field['values'] ?? $this->options([$column => $field])[$column]);
                if (!in_array($value, array_map('strval', $allowed), true)) {
                    flash('error', 'Pilihan untuk ' . $field['label'] . ' tidak valid.');
                    redirect('/admin/master/' . $resource);
                }
                if ($field['type'] === 'active') {
                    $values[$column] = (int) $value;
                } elseif (isset($field['values'])) {
                    $values[$column] = $value;
                } else {
                    $values[$column] = (int) $value;
                }
                continue;
            }

            if ($field['type'] === 'price') {
                if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $value) || (float) $value <= 0) {
                    flash('error', $field['label'] . ' harus berupa angka lebih besar dari nol, maksimal 2 desimal.');
                    redirect('/admin/master/' . $resource);
                }
                $values[$column] = $value;
                continue;
            }

            if ($field['type'] === 'json') {
                $decoded = json_decode($value, true);
                if (!is_array($decoded) || !array_is_list($decoded)) {
                    flash('error', 'Fasilitas harus berupa JSON array, contoh: ["Kasur", "WiFi"].');
                    redirect('/admin/master/' . $resource);
                }
                foreach ($decoded as $item) {
                    if (!is_string($item) || trim($item) === '' || mb_strlen($item) > 100) {
                        flash('error', 'Setiap fasilitas harus berupa teks 1-100 karakter.');
                        redirect('/admin/master/' . $resource);
                    }
                }
                $values[$column] = json_encode(array_map('trim', $decoded), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            }
        }

        if ($resource === 'receptionists') $values['role'] = 'receptionist';

        if ($resource === 'price-matrices') {
            $hasPrice = false;
            foreach (['daily_price', 'weekly_price', 'monthly_price', 'yearly_price'] as $price) {
                if (isset($values[$price]) && $values[$price] !== null) $hasPrice = true;
            }
            if (!$hasPrice) {
                flash('error', 'Isi minimal satu harga sewa.');
                redirect('/admin/master/' . $resource);
            }
        }

        // Daftar kolom yang harus unik: [kolom, tabel, pesan error]
        $uniqueChecks = [];
        if (isset($values['slug'])) {
            $uniqueChecks[] = [
                'slug',
                $config['table'],   // otomatis 'cities' atau 'provinces'
                $resource === 'provinces' ? 'Slug provinsi sudah digunakan.' : 'Slug kota sudah digunakan.',
            ];
        }
        if (isset($values['email'])) {
            $uniqueChecks[] = ['email', 'users', 'Email sudah digunakan.'];
        }

        foreach ($uniqueChecks as [$uniqueColumn, $table, $message]) {
            $sql = "SELECT id FROM `$table` WHERE `$uniqueColumn` = ?";
            $args = [$values[$uniqueColumn]];
            if ($id !== null) {
                $sql .= ' AND id <> ?';
                $args[] = $id;
            }
            $sql .= ' LIMIT 1';
            $stmt = db()->prepare($sql);
            $stmt->execute($args);
            if ($stmt->fetchColumn() !== false) {
                flash('error', $message);
                redirect('/admin/master/' . $resource);
            }
        }

        if ($resource === 'receptionists') {
            $stmt = db()->prepare('SELECT id FROM users WHERE property_id = ? AND role = ? AND id <> ? LIMIT 1');
            $stmt->execute([$values['property_id'], 'receptionist', $id ?? 0]);
            if ($stmt->fetchColumn() !== false) {
                flash('error', 'Gedung tersebut sudah memiliki akun resepsionis.');
                redirect('/admin/master/' . $resource);
            }
        }

        if ($resource === 'room-units') {
            $stmt = db()->prepare('SELECT id FROM room_units WHERE room_class_id = ? AND room_number = ? AND id <> ? LIMIT 1');
            $stmt->execute([$values['room_class_id'], $values['room_number'], $id ?? 0]);
            if ($stmt->fetchColumn() !== false) {
                flash('error', 'Nomor kamar sudah digunakan pada kelas tersebut.');
                redirect('/admin/master/' . $resource);
            }
        }
        return $values;
    }

    private function options(array $fields): array
    {
        $options = [];
        foreach ($fields as $column => $field) {
            if (isset($field['values'])) {
                $options[$column] = $field['values'];
                continue;
            }
            if (!isset($field['options'])) continue;
            $source = $field['options'];
            $queries = [
                'provinces' => 'SELECT id, name AS label FROM provinces ORDER BY name',
                'cities' => 'SELECT id, name AS label FROM cities ORDER BY name',
                'properties' => 'SELECT id, name AS label FROM properties ORDER BY name',
                'room_classes' => 'SELECT rc.id, CONCAT(p.name, " - ", rc.name) AS label FROM room_classes rc JOIN properties p ON p.id = rc.property_id ORDER BY p.name, rc.name',
            ];
            $stmt = db()->query($queries[$source]);
            $options[$column] = [];
            foreach ($stmt->fetchAll() as $row) $options[$column][(string) $row['id']] = $row['label'];
        }
        return $options;
    }

    private function assertDeletable(string $resource, int $id): void
    {
        $checks = match ($resource) {
            'provinces' => [
                ['SELECT COUNT(*) FROM cities WHERE province_id = ?', 'Provinsi masih digunakan oleh kota. Pindahkan kota ke provinsi lain terlebih dahulu.'],
            ],
            'cities' => [
                ['SELECT COUNT(*) FROM properties WHERE city_id = ?', 'Kota masih digunakan oleh gedung. Pindahkan gedung ke kota lain terlebih dahulu.'],
            ],
            'properties' => [
                ['SELECT COUNT(*) FROM room_classes WHERE property_id = ?', 'Gedung masih memiliki kelas kamar.'],
                ['SELECT COUNT(*) FROM receptionist_staff WHERE property_id = ?', 'Gedung masih memiliki petugas jaga.'],
                ['SELECT COUNT(*) FROM users WHERE property_id = ?', 'Gedung masih terhubung ke akun resepsionis.'],
            ],
            'room-classes' => [
                ['SELECT COUNT(*) FROM room_units WHERE room_class_id = ?', 'Kelas kamar masih memiliki unit.'],
                ['SELECT COUNT(*) FROM price_matrices WHERE room_class_id = ?', 'Kelas kamar masih memiliki matriks harga.'],
            ],
            'room-units' => [
                ['SELECT COUNT(*) FROM bookings WHERE room_unit_id = ?', 'Unit kamar sudah tercatat pada transaksi.'],
            ],
            'staff' => [
                ['SELECT COUNT(*) FROM bookings WHERE created_by_staff_id = ?', 'Petugas sudah tercatat pada transaksi.'],
                ['SELECT COUNT(*) FROM payments WHERE approved_by_staff_id = ?', 'Petugas sudah tercatat pada pembayaran.'],
            ],
            'receptionists' => [
                ['SELECT COUNT(*) FROM bookings WHERE created_by_user_id = ?', 'Akun sudah tercatat pada transaksi.'],
                ['SELECT COUNT(*) FROM payments WHERE approved_by_user_id = ?', 'Akun sudah tercatat pada pembayaran.'],
            ],
            default => [],
        };
        foreach ($checks as [$sql, $message]) {
            $stmt = db()->prepare($sql);
            $stmt->execute([$id]);
            if ((int) $stmt->fetchColumn() > 0) {
                $hint = in_array($resource, ['cities', 'provinces'], true) ? '' : ' Nonaktifkan atau ubah data terkait, bukan menghapusnya.';
                flash('error', $message . $hint);
                redirect('/admin/master/' . $resource);
            }
        }
    }

    // Resource yang punya foto: provinsi, kota, gedung, dan kelas kamar.
    private function hasImage(string $resource): bool
    {
        return in_array($resource, ['cities', 'provinces', 'properties', 'room-classes'], true);
    }

    // Nama input file, folder penyimpanan, dan sebutan di pesan error.
    private function imageSettings(string $resource): array
    {
        return match ($resource) {
            'provinces' => ['field' => 'province_image', 'folder' => 'provinsi', 'label' => 'provinsi'],
            'properties' => ['field' => 'property_image', 'folder' => 'gedung', 'label' => 'gedung'],
            'room-classes' => ['field' => 'class_image', 'folder' => 'kelas', 'label' => 'kelas kamar'],
            default => ['field' => 'city_image', 'folder' => 'kota', 'label' => 'kota'],
        };
    }

    private function storeImageUpload(string $resource): ?string
    {
        // Hanya simpan gambar raster yang ukurannya aman untuk kartu/bulatan.
        $settings = $this->imageSettings($resource);
        $label = $settings['label'];
        $back = '/admin/master/' . $resource;

        $file = $_FILES[$settings['field']] ?? null;
        if ($file === null) return null;
        if (!is_array($file) || !isset($file['error']) || !is_int($file['error'])) {
            flash('error', "Data unggahan foto $label tidak valid.");
            redirect($back);
        }
        if ($file['error'] === UPLOAD_ERR_NO_FILE) return null;
        if ($file['error'] !== UPLOAD_ERR_OK) {
            flash('error', "Foto $label gagal diunggah. Pastikan ukuran file tidak melebihi 5 MB.");
            redirect($back);
        }

        $temporaryPath = $file['tmp_name'] ?? null;
        if (!is_string($temporaryPath) || !is_uploaded_file($temporaryPath)) {
            flash('error', "Berkas foto $label tidak valid.");
            redirect($back);
        }
        $fileSize = filesize($temporaryPath);
        if ($fileSize === false || $fileSize < 1 || $fileSize > 5 * 1024 * 1024) {
            flash('error', "Ukuran foto $label harus lebih dari 0 dan maksimal 5 MB.");
            redirect($back);
        }

        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($fileInfo === false) throw new RuntimeException("Tidak dapat memeriksa tipe berkas foto $label.");
        $mimeType = finfo_file($fileInfo, $temporaryPath);
        finfo_close($fileInfo);
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        if (!is_string($mimeType) || !isset($extensions[$mimeType])) {
            flash('error', "Format foto $label harus JPG, PNG, atau WEBP.");
            redirect($back);
        }

        $directory = $this->imageDirectory($resource);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            flash('error', "Folder penyimpanan foto $label tidak dapat dibuat.");
            redirect($back);
        }
        $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mimeType];
        if (!move_uploaded_file($temporaryPath, $directory . DIRECTORY_SEPARATOR . $filename)) {
            flash('error', "Foto $label gagal disimpan.");
            redirect($back);
        }
        return $filename;
    }

    private function imageDirectory(string $resource): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public'
            . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'img'
            . DIRECTORY_SEPARATOR . $this->imageSettings($resource)['folder'];
    }

    private function imagePath(string $resource, string $filename): ?string
    {
        if (!preg_match('/\A[a-z0-9_-]{1,200}\.(?:jpe?g|png|webp)\z/i', $filename)) return null;
        return $this->imageDirectory($resource) . DIRECTORY_SEPARATOR . $filename;
    }

    private function imageUrl(string $resource, mixed $filename): ?string
    {
        if (!is_string($filename) || ($path = $this->imagePath($resource, $filename)) === null || !is_file($path)) {
            return null;
        }
        return url('/assets/img/' . $this->imageSettings($resource)['folder'] . '/' . rawurlencode($filename));
    }

    private function removeImageFile(string $resource, string $filename): void
    {
        $path = $this->imagePath($resource, $filename);
        if ($path !== null && is_file($path) && !unlink($path)) {
            error_log('Admin could not remove image file: ' . $filename);
            throw new RuntimeException('File foto lama tidak dapat dihapus.');
        }
    }

    private function resource(string $resource): string
    {
        if (!isset(self::RESOURCES[$resource])) abort(404);
        return $resource;
    }
}
