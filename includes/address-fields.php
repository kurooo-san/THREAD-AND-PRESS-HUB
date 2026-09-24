<?php

/**
 * The address input fields, shared by the "add" and "edit" forms on
 * profile.php and by the "use a new address" panel on checkout.php.
 *
 * One copy so the three places can never drift apart in field names or
 * validation hints. The including page sets $v to the row being edited, or
 * an empty array when adding.
 *
 * @var array $v
 */

$v = isset($v) && is_array($v) ? $v : [];

/** Field value, escaped, falling back to '' — and never printing the '-' placeholder. */
$av = static function (string $key) use ($v): string {
    $raw = trim((string) ($v[$key] ?? ''));
    return htmlspecialchars($raw === '-' ? '' : $raw, ENT_QUOTES);
};
?>
<div class="form-group mb-3">
    <label class="form-label" style="font-size:0.82rem; font-weight:600;">Label</label>
    <input type="text" class="form-control" name="label" maxlength="40"
           placeholder="Home, Dorm, Office…" value="<?php echo $av('label'); ?>">
</div>

<div class="form-group mb-3">
    <label class="form-label" style="font-size:0.82rem; font-weight:600;">Street Address *</label>
    <input type="text" class="form-control" name="street_address" maxlength="255" required
           placeholder="House/Unit No., Street Name" value="<?php echo $av('street_address'); ?>">
</div>

<div class="form-group mb-3">
    <label class="form-label" style="font-size:0.82rem; font-weight:600;">Barangay</label>
    <input type="text" class="form-control" name="barangay" maxlength="100"
           placeholder="Barangay" value="<?php echo $av('barangay'); ?>">
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <label class="form-label" style="font-size:0.82rem; font-weight:600;">City *</label>
        <input type="text" class="form-control" name="city" maxlength="100" required
               placeholder="City" value="<?php echo $av('city'); ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label" style="font-size:0.82rem; font-weight:600;">Province *</label>
        <input type="text" class="form-control" name="province" maxlength="100" required
               placeholder="Province" value="<?php echo $av('province'); ?>">
        <small class="text-muted" style="font-size:0.74rem;">Your shipping fee is worked out from this.</small>
    </div>
</div>

<div class="form-group mb-3">
    <label class="form-label" style="font-size:0.82rem; font-weight:600;">Zip Code</label>
    <input type="text" class="form-control" name="zipcode" maxlength="4" inputmode="numeric"
           placeholder="1900" style="max-width:200px;" value="<?php echo $av('zipcode'); ?>">
</div>
