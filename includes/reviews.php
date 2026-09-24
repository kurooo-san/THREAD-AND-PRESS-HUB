<?php

/**
 * Product reviews — shared between shop.php (star summaries on the grid)
 * and product.php (full list + submission form).
 *
 * Everything is guarded by reviewsTableExists(), so the storefront behaves
 * exactly as before if migrate_product_reviews.sql has not been run.
 *
 * Requires includes/config.php first.
 */

if (!function_exists('reviewsTableExists')) {

    function reviewsTableExists(): bool
    {
        global $conn;
        static $has = null;
        if ($has !== null) {
            return $has;
        }
        $r = $conn->query("SHOW TABLES LIKE 'product_reviews'");
        $has = ($r !== false && $r->num_rows > 0);
        return $has;
    }

    /**
     * Average rating and count for many products at once.
     *
     * The shop grid renders 35+ cards; querying per card would be 35 round
     * trips, so ids are batched into a single grouped query.
     *
     * @param int[] $productIds
     * @return array<int, array{avg: float, count: int}>
     */
    function reviewSummaries(array $productIds): array
    {
        global $conn;
        $out = [];
        if (!reviewsTableExists()) {
            return $out;
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if (!$ids) {
            return $out;
        }

        // Ids are cast to int above, so this interpolation cannot carry input.
        $list = implode(',', $ids);
        $sql  = "SELECT product_id, AVG(rating) AS avg_rating, COUNT(*) AS review_count
                 FROM product_reviews
                 WHERE status = 'published' AND product_id IN ($list)
                 GROUP BY product_id";
        $res = $conn->query($sql);
        if (!$res) {
            return $out;
        }
        while ($row = $res->fetch_assoc()) {
            $out[(int) $row['product_id']] = [
                'avg'   => round((float) $row['avg_rating'], 2),
                'count' => (int) $row['review_count'],
            ];
        }
        return $out;
    }

    /** Convenience wrapper for a single product. */
    function reviewSummary(int $productId): array
    {
        $all = reviewSummaries([$productId]);
        return $all[$productId] ?? ['avg' => 0.0, 'count' => 0];
    }

    /**
     * Published reviews for a product, newest first, with the reviewer name.
     *
     * @return array<int, array<string,mixed>>
     */
    function productReviews(int $productId, int $limit = 20): array
    {
        global $conn;
        if (!reviewsTableExists()) {
            return [];
        }
        $limit = max(1, min(100, $limit));
        $stmt = $conn->prepare(
            "SELECT r.*, u.fullname
             FROM product_reviews r
             JOIN users u ON u.id = r.user_id
             WHERE r.product_id = ? AND r.status = 'published'
             ORDER BY r.created_at DESC
             LIMIT $limit"
        );
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $productId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /**
     * How many stars each score received, for the 5/4/3/2/1 breakdown bars.
     *
     * @return array<int,int> keyed 1..5, always all five keys present
     */
    function reviewDistribution(int $productId): array
    {
        global $conn;
        $dist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        if (!reviewsTableExists()) {
            return $dist;
        }
        $stmt = $conn->prepare(
            "SELECT rating, COUNT(*) AS c
             FROM product_reviews
             WHERE product_id = ? AND status = 'published'
             GROUP BY rating"
        );
        if (!$stmt) {
            return $dist;
        }
        $stmt->bind_param('i', $productId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $r = (int) $row['rating'];
            if (isset($dist[$r])) {
                $dist[$r] = (int) $row['c'];
            }
        }
        $stmt->close();
        return $dist;
    }

    /**
     * True when the user has a non-cancelled order containing this product.
     * This is what gates the review form and sets the "Verified Purchase"
     * badge — without it anyone could rate a product they never bought.
     */
    function hasPurchasedProduct(int $userId, int $productId): bool
    {
        global $conn;
        if ($userId <= 0 || $productId <= 0) {
            return false;
        }
        $stmt = $conn->prepare(
            "SELECT 1
             FROM order_items oi
             JOIN orders o ON o.id = oi.order_id
             WHERE o.user_id = ? AND oi.product_id = ? AND o.status <> 'cancelled'
             LIMIT 1"
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ii', $userId, $productId);
        $stmt->execute();
        $found = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $found;
    }

    /** The signed-in user's own review, so the form can pre-fill for editing. */
    function userReview(int $userId, int $productId): ?array
    {
        global $conn;
        if (!reviewsTableExists() || $userId <= 0) {
            return null;
        }
        $stmt = $conn->prepare("SELECT * FROM product_reviews WHERE user_id = ? AND product_id = ? LIMIT 1");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('ii', $userId, $productId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /**
     * Insert or update the user's review. The UNIQUE (product_id, user_id)
     * index makes the upsert safe against double submits.
     *
     * @return array{ok: bool, error: ?string}
     */
    function saveProductReview(int $userId, int $productId, int $rating, string $title, string $body): array
    {
        global $conn;

        if (!reviewsTableExists()) {
            return ['ok' => false, 'error' => 'Reviews are not available yet.'];
        }
        if ($rating < 1 || $rating > 5) {
            return ['ok' => false, 'error' => 'Please choose a rating between 1 and 5 stars.'];
        }
        if (!hasPurchasedProduct($userId, $productId)) {
            return ['ok' => false, 'error' => 'Only customers who have ordered this item can review it.'];
        }

        $title = mb_substr(trim($title), 0, 120);
        $body  = mb_substr(trim($body), 0, 2000);

        $stmt = $conn->prepare(
            "INSERT INTO product_reviews (product_id, user_id, rating, title, body, is_verified)
             VALUES (?, ?, ?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE
                rating = VALUES(rating),
                title  = VALUES(title),
                body   = VALUES(body)"
        );
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Could not save your review. Please try again.'];
        }
        $stmt->bind_param('iiiss', $productId, $userId, $rating, $title, $body);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok
            ? ['ok' => true, 'error' => null]
            : ['ok' => false, 'error' => 'Could not save your review. Please try again.'];
    }

    /**
     * Star row. $value may be fractional; the half state is drawn with a
     * clipped overlay so 4.3 does not silently render as 4.
     */
    function renderStars(float $value, int $px = 16, bool $showValue = false): string
    {
        $value = max(0.0, min(5.0, $value));
        $pct   = ($value / 5) * 100;
        $uid   = 'st' . bin2hex(random_bytes(3));

        $star = '<svg viewBox="0 0 24 24" width="' . $px . '" height="' . $px . '" aria-hidden="true">'
              . '<path d="M12 2.6l2.9 5.9 6.5.9-4.7 4.6 1.1 6.5-5.8-3-5.8 3 1.1-6.5L2.6 9.4l6.5-.9z"/></svg>';

        $row = '';
        for ($i = 0; $i < 5; $i++) {
            $row .= $star;
        }

        $out  = '<span class="rv-stars" role="img" aria-label="' . number_format($value, 1) . ' out of 5 stars">';
        $out .=   '<span class="rv-stars-bg">' . $row . '</span>';
        $out .=   '<span class="rv-stars-fg" style="width:' . $pct . '%;">' . $row . '</span>';
        $out .= '</span>';

        if ($showValue) {
            $out .= '<span class="rv-stars-num">' . number_format($value, 1) . '</span>';
        }
        return $out;
    }
}
