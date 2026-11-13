<?php
declare(strict_types=1);

/**
 * Aturan: required|max:255|min:8|email|int|in:a,b|same:field|unique:tabel,kolom[,ignore_id]
 * Return: array error per field (kosong = valid)
 */
function validate(array $data, array $rules): array
{
    $errors = [];
    foreach ($rules as $field => $ruleStr) {
        $value = $data[$field] ?? null;
        if ($value !== null && !is_scalar($value)) {
            $errors[$field] = "$field tidak valid.";
            continue;
        }
        if (is_string($value)) $value = trim($value);
        $empty = ($value === null || $value === '');

        foreach (explode('|', $ruleStr) as $rule) {
            [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);

            if ($name === 'required' && $empty) { $errors[$field] = "$field wajib diisi."; break; }
            if ($empty) continue;

            $fail = match ($name) {
                'max'   => mb_strlen((string) $value) > (int) $arg ? "$field maksimal $arg karakter." : null,
                'min'   => mb_strlen((string) $value) < (int) $arg ? "$field minimal $arg karakter." : null,
                'email' => filter_var($value, FILTER_VALIDATE_EMAIL) === false ? "$field harus berupa email valid." : null,
                'int'   => filter_var($value, FILTER_VALIDATE_INT) === false ? "$field harus berupa angka bulat." : null,
                'in'    => !in_array((string) $value, explode(',', (string) $arg), true) ? "$field tidak valid." : null,
                'same'  => $value !== ($data[$arg] ?? null) ? "$field tidak sama dengan $arg." : null,
                'unique'=> validate_unique((string) $value, (string) $arg) ? null : "$field sudah digunakan.",
                default => null,
            };
            if ($fail) { $errors[$field] = $fail; break; }
        }
    }
    return $errors;
}

function validate_unique(string $value, string $arg): bool
{
    [$table, $col, $ignoreId] = array_pad(explode(',', $arg), 3, null);
    // nama tabel/kolom tidak bisa di-bind, jadi whitelist ketat
    if (!preg_match('/^[a-z_]+$/', (string) $table) || !preg_match('/^[a-z_]+$/', (string) $col)) {
        throw new InvalidArgumentException('Aturan unique tidak valid.');
    }
    $sql = "SELECT 1 FROM `$table` WHERE `$col` = ?" . ($ignoreId ? ' AND id <> ?' : '') . ' LIMIT 1';
    $stmt = db()->prepare($sql);
    $stmt->execute($ignoreId ? [$value, (int) $ignoreId] : [$value]);
    return $stmt->fetchColumn() === false;
}
