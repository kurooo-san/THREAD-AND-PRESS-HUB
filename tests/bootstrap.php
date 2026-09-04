<?php
// Test bootstrap — minimal setup for unit tests that don't need DB.
// Defines pure helper functions extracted from includes/config.php so we can
// test them without booting sessions / connecting to MySQL.

if (!function_exists('sanitizeInputPure')) {
    function sanitizeInputPure(string $data): string {
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('calculateDiscountPure')) {
    function calculateDiscountPure(string $type): float {
        $rates = ['regular' => 0.0, 'pwd' => 0.20, 'senior' => 0.20];
        return $rates[$type] ?? 0.0;
    }
}

if (!function_exists('applyDiscountPure')) {
    function applyDiscountPure(float $subtotal, float $rate): array {
        $discount = round($subtotal * $rate, 2);
        return [
            'discount_amount' => $discount,
            'total'           => round($subtotal - $discount, 2),
        ];
    }
}

if (!function_exists('validateCouponPure')) {
    /**
     * Pure coupon validator (no DB). Coupon shape:
     *   ['code', 'is_active', 'valid_from', 'valid_until',
     *    'min_subtotal', 'max_uses', 'times_used',
     *    'discount_type' => 'percent|fixed', 'discount_value']
     */
    function validateCouponPure(array $coupon, float $subtotal, ?int $now = null): array {
        $now = $now ?? time();

        if (empty($coupon['is_active'])) {
            return ['ok' => false, 'message' => 'Coupon inactive', 'discount' => 0.0];
        }
        if (!empty($coupon['valid_from']) && strtotime($coupon['valid_from']) > $now) {
            return ['ok' => false, 'message' => 'Not yet valid', 'discount' => 0.0];
        }
        if (!empty($coupon['valid_until']) && strtotime($coupon['valid_until']) < $now) {
            return ['ok' => false, 'message' => 'Expired', 'discount' => 0.0];
        }
        if ($subtotal < (float)($coupon['min_subtotal'] ?? 0)) {
            return ['ok' => false, 'message' => 'Below minimum', 'discount' => 0.0];
        }
        if (!empty($coupon['max_uses']) && (int)$coupon['times_used'] >= (int)$coupon['max_uses']) {
            return ['ok' => false, 'message' => 'Coupon used up', 'discount' => 0.0];
        }

        $value = (float)$coupon['discount_value'];
        $discount = ($coupon['discount_type'] === 'percent')
            ? round($subtotal * ($value / 100), 2)
            : min($value, $subtotal);

        return ['ok' => true, 'message' => 'OK', 'discount' => $discount];
    }
}
