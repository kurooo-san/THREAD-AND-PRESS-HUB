<?php

/**
 * Saved delivery addresses (max 3 per customer).
 *
 * Every rule about addresses lives here so profile.php and checkout.php can
 * never disagree: the cap, ownership checks, which one is the default, and
 * keeping the users.* columns in step with that default.
 *
 * WHY users.* is still written: checkout prefill, invoices and the
 * delivery-zone lock all read users.street_address / city / province. Rather
 * than chase every reader, the default address is mirrored back into those
 * columns whenever it changes, so old code keeps working untouched.
 *
 * SECURITY: addressGet() and every mutator take the user id and scope their
 * query by it. A customer can never read or change someone else's address by
 * guessing an id.
 *
 * Requires includes/config.php first (for $conn).
 */

if (!defined('ADDRESS_MAX_PER_USER')) {

    /** How many addresses one customer may keep. */
    define('ADDRESS_MAX_PER_USER', 3);

    /** True once migrate_user_addresses.sql has been applied. */
    function addressTableExists(): bool
    {
        global $conn;
        static $exists = null;
        if ($exists === null) {
            $r = $conn->query("SHOW TABLES LIKE 'user_addresses'");
            $exists = $r && $r->num_rows > 0;
        }
        return $exists;
    }

    /**
     * Every address a customer has, default first then newest.
     *
     * @return array<int,array<string,mixed>>
     */
    function addressList(int $userId): array
    {
        global $conn;
        if (!addressTableExists() || $userId <= 0) {
            return [];
        }
        $stmt = $conn->prepare(
            "SELECT * FROM user_addresses
              WHERE user_id = ?
              ORDER BY is_default DESC, id ASC"
        );
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if ($rows === [] && addressBackfillFromProfile($userId)) {
            return addressList($userId);
        }
        return $rows;
    }

    /**
     * register.php stores the signup address on users.* only. The first time
     * such a customer has no saved addresses, copy it in as their default
     * (same rule as the backfill in migrate_user_addresses.sql). Deleting the
     * last address blanks users.*, so a deleted address never comes back.
     */
    function addressBackfillFromProfile(int $userId): bool
    {
        global $conn;
        $stmt = $conn->prepare(
            "INSERT INTO user_addresses (user_id, label, street_address, barangay, city, province, zipcode, is_default)
             SELECT u.id, 'Home', u.street_address, NULLIF(u.barangay, ''),
                    COALESCE(NULLIF(u.city, ''), '-'), COALESCE(NULLIF(u.province, ''), '-'),
                    NULLIF(u.zipcode, ''), 1
               FROM users u
              WHERE u.id = ?
                AND u.street_address IS NOT NULL AND u.street_address <> ''
                AND NOT EXISTS (SELECT 1 FROM user_addresses a WHERE a.user_id = u.id)"
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $userId);
        $ok = $stmt->execute() && $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }

    /**
     * One address, but only if it belongs to this customer.
     *
     * This ownership check is what makes it safe for checkout to take an
     * address id from the browser: a forged id simply returns null.
     */
    function addressGet(int $userId, int $addressId): ?array
    {
        global $conn;
        if (!addressTableExists() || $userId <= 0 || $addressId <= 0) {
            return null;
        }
        $stmt = $conn->prepare("SELECT * FROM user_addresses WHERE id = ? AND user_id = ? LIMIT 1");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('ii', $addressId, $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /** The customer's default address, or null. */
    function addressDefault(int $userId): ?array
    {
        foreach (addressList($userId) as $a) {
            if (!empty($a['is_default'])) {
                return $a;
            }
        }
        return null;
    }

    /** How many they already have. */
    function addressCount(int $userId): int
    {
        return count(addressList($userId));
    }

    /** True when there is still room for another one. */
    function addressCanAdd(int $userId): bool
    {
        return addressCount($userId) < ADDRESS_MAX_PER_USER;
    }

    /**
     * One line, the way it is stored on the order and printed on an invoice.
     */
    function addressFormat(array $a): string
    {
        $parts = array_filter([
            trim((string) ($a['street_address'] ?? '')),
            trim((string) ($a['barangay'] ?? '')),
            trim((string) ($a['city'] ?? '')),
            trim((string) ($a['province'] ?? '')),
            trim((string) ($a['zipcode'] ?? '')),
        ], static fn($v) => $v !== '' && $v !== '-');
        return implode(', ', $parts);
    }

    /**
     * Validate the fields of an address.
     *
     * @return string Empty when valid, otherwise the message to show.
     */
    function addressValidate(array $in): string
    {
        if (trim((string) ($in['street_address'] ?? '')) === '') {
            return 'Street address is required.';
        }
        if (trim((string) ($in['city'] ?? '')) === '') {
            return 'City is required.';
        }
        if (trim((string) ($in['province'] ?? '')) === '') {
            return 'Province is required — it decides your shipping fee.';
        }
        $zip = trim((string) ($in['zipcode'] ?? ''));
        if ($zip !== '' && !preg_match('/^[0-9]{4}$/', $zip)) {
            return 'Zip code should be 4 digits.';
        }
        return '';
    }

    /**
     * Save a new address.
     *
     * The first one a customer saves becomes their default automatically, so
     * they never end up with addresses but no default.
     *
     * @return array{ok:bool, id:?int, error:string}
     */
    function addressAdd(int $userId, array $in): array
    {
        global $conn;
        if (!addressTableExists()) {
            return ['ok' => false, 'id' => null, 'error' => 'Saved addresses are not available yet.'];
        }
        if (!addressCanAdd($userId)) {
            return ['ok' => false, 'id' => null,
                    'error' => 'You can only save up to ' . ADDRESS_MAX_PER_USER . ' addresses. Delete one first.'];
        }
        $err = addressValidate($in);
        if ($err !== '') {
            return ['ok' => false, 'id' => null, 'error' => $err];
        }

        $label   = mb_substr(trim((string) ($in['label'] ?? '')) ?: 'Home', 0, 40);
        $street  = mb_substr(trim((string) $in['street_address']), 0, 255);
        $brgy    = mb_substr(trim((string) ($in['barangay'] ?? '')), 0, 100);
        $city    = mb_substr(trim((string) $in['city']), 0, 100);
        $prov    = mb_substr(trim((string) $in['province']), 0, 100);
        $zip     = mb_substr(trim((string) ($in['zipcode'] ?? '')), 0, 20);
        $isFirst = addressCount($userId) === 0 ? 1 : 0;

        $stmt = $conn->prepare(
            "INSERT INTO user_addresses (user_id, label, street_address, barangay, city, province, zipcode, is_default)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt) {
            return ['ok' => false, 'id' => null, 'error' => 'Could not save the address.'];
        }
        $stmt->bind_param('issssssi', $userId, $label, $street, $brgy, $city, $prov, $zip, $isFirst);
        $ok = $stmt->execute();
        $newId = $ok ? (int) $stmt->insert_id : null;
        $stmt->close();

        if (!$ok) {
            return ['ok' => false, 'id' => null, 'error' => 'Could not save the address.'];
        }
        if ($isFirst === 1) {
            addressSyncDefaultToUser($userId);
        }
        return ['ok' => true, 'id' => $newId, 'error' => ''];
    }

    /**
     * Update one of the customer's own addresses.
     *
     * @return array{ok:bool, error:string}
     */
    function addressUpdate(int $userId, int $addressId, array $in): array
    {
        global $conn;
        $existing = addressGet($userId, $addressId);
        if ($existing === null) {
            return ['ok' => false, 'error' => 'That address was not found.'];
        }
        $err = addressValidate($in);
        if ($err !== '') {
            return ['ok' => false, 'error' => $err];
        }

        $label  = mb_substr(trim((string) ($in['label'] ?? '')) ?: 'Home', 0, 40);
        $street = mb_substr(trim((string) $in['street_address']), 0, 255);
        $brgy   = mb_substr(trim((string) ($in['barangay'] ?? '')), 0, 100);
        $city   = mb_substr(trim((string) $in['city']), 0, 100);
        $prov   = mb_substr(trim((string) $in['province']), 0, 100);
        $zip    = mb_substr(trim((string) ($in['zipcode'] ?? '')), 0, 20);

        $stmt = $conn->prepare(
            "UPDATE user_addresses
                SET label = ?, street_address = ?, barangay = ?, city = ?, province = ?, zipcode = ?
              WHERE id = ? AND user_id = ?"
        );
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Could not update the address.'];
        }
        $stmt->bind_param('ssssssii', $label, $street, $brgy, $city, $prov, $zip, $addressId, $userId);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok && !empty($existing['is_default'])) {
            addressSyncDefaultToUser($userId);
        }
        return ['ok' => $ok, 'error' => $ok ? '' : 'Could not update the address.'];
    }

    /**
     * Delete one. If it was the default, the oldest remaining address is
     * promoted so the customer is never left without one.
     *
     * @return array{ok:bool, error:string}
     */
    function addressDelete(int $userId, int $addressId): array
    {
        global $conn;
        $existing = addressGet($userId, $addressId);
        if ($existing === null) {
            return ['ok' => false, 'error' => 'That address was not found.'];
        }

        $stmt = $conn->prepare("DELETE FROM user_addresses WHERE id = ? AND user_id = ?");
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Could not delete the address.'];
        }
        $stmt->bind_param('ii', $addressId, $userId);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            return ['ok' => false, 'error' => 'Could not delete the address.'];
        }

        if (!empty($existing['is_default'])) {
            // Clear users.* first: otherwise addressList() below would see no
            // addresses and backfill the one just deleted from that copy.
            addressClearUserColumns($userId);
            $remaining = addressList($userId);
            if ($remaining !== []) {
                addressSetDefault($userId, (int) $remaining[0]['id']);
            }
        }
        return ['ok' => true, 'error' => ''];
    }

    /**
     * Make one address the default, clearing the flag on the others.
     *
     * @return array{ok:bool, error:string}
     */
    function addressSetDefault(int $userId, int $addressId): array
    {
        global $conn;
        if (addressGet($userId, $addressId) === null) {
            return ['ok' => false, 'error' => 'That address was not found.'];
        }
        $conn->query("UPDATE user_addresses SET is_default = 0 WHERE user_id = " . (int) $userId);

        $stmt = $conn->prepare("UPDATE user_addresses SET is_default = 1 WHERE id = ? AND user_id = ?");
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Could not set the default address.'];
        }
        $stmt->bind_param('ii', $addressId, $userId);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            addressSyncDefaultToUser($userId);
        }
        return ['ok' => $ok, 'error' => $ok ? '' : 'Could not set the default address.'];
    }

    /**
     * Copy the default address back onto users.* so every existing reader
     * (checkout prefill, invoices, the zone lock) keeps seeing the right one.
     */
    function addressSyncDefaultToUser(int $userId): void
    {
        global $conn;
        $def = addressDefault($userId);
        if ($def === null) {
            addressClearUserColumns($userId);
            return;
        }
        $stmt = $conn->prepare(
            "UPDATE users
                SET street_address = ?, barangay = ?, city = ?, province = ?, zipcode = ?
              WHERE id = ?"
        );
        if (!$stmt) {
            return;
        }
        $street = (string) $def['street_address'];
        $brgy   = (string) ($def['barangay'] ?? '');
        $city   = (string) $def['city'];
        $prov   = (string) $def['province'];
        $zip    = (string) ($def['zipcode'] ?? '');
        $stmt->bind_param('sssssi', $street, $brgy, $city, $prov, $zip, $userId);
        $stmt->execute();
        $stmt->close();
    }

    /** Blank the mirrored columns when the last address is gone. */
    function addressClearUserColumns(int $userId): void
    {
        global $conn;
        $stmt = $conn->prepare(
            "UPDATE users SET street_address = '', barangay = '', city = '', province = '', zipcode = '' WHERE id = ?"
        );
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
    }
}
