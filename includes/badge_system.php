<?php
/**
 * Badge and Level Management System
 * Handles badge unlocking logic and level calculations
 */

/**
 * Check and award badges for user actions
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @param string $action_type Type of action (ADD_ITEM, CONSUME_ITEM, etc)
 * @return array Array of newly unlocked badges
 */
function checkAndAwardBadges($conn, $user_id) {
    $newly_unlocked = [];
    
    // Get user's current stats
    $stats_stmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT ci.item_id) as total_items_added,
            SUM(CASE WHEN ciu.update_type = 'consumed' THEN 1 ELSE 0 END) as total_consumed,
            (SELECT total_points FROM user_points WHERE user_id = ?) as total_points,
            COUNT(DISTINCT ub.badge_id) as badges_earned
        FROM customer_items ci
        LEFT JOIN customer_inventory_updates ciu ON ci.item_id = ciu.item_id
        LEFT JOIN user_badges ub ON ub.user_id = ?
        WHERE ci.created_by = ?
    ");
    $stats_stmt->bind_param("iii", $user_id, $user_id, $user_id);
    $stats_stmt->execute();
    $stats = $stats_stmt->get_result()->fetch_assoc();
    
    // Define badge conditions
    $badge_conditions = [
        // Badge ID 1: Newbie Organizer - Add 5 items
        [
            'badge_id' => 1,
            'condition' => ($stats['total_items_added'] ?? 0) >= 5,
            'name' => 'Newbie Organizer'
        ],
        // Badge ID 2: Waste Warrior - Log consumption 20+ times
        [
            'badge_id' => 2,
            'condition' => ($stats['total_consumed'] ?? 0) >= 20,
            'name' => 'Waste Warrior'
        ],
        // Badge ID 3: Inventory Master - Earn 200+ points
        [
            'badge_id' => 3,
            'condition' => ($stats['total_points'] ?? 0) >= 200,
            'name' => 'Inventory Master'
        ],
        // Badge ID 4: Active Helper - Consume 10+ items
        [
            'badge_id' => 4,
            'condition' => ($stats['total_consumed'] ?? 0) >= 10,
            'name' => 'Active Helper'
        ],
        // Badge ID 5: Power User - Earn 500+ points
        [
            'badge_id' => 5,
            'condition' => ($stats['total_points'] ?? 0) >= 500,
            'name' => 'Power User'
        ],
    ];
    
    // Check each badge condition
    foreach ($badge_conditions as $badge) {
        if ($badge['condition']) {
            // Check if user already has this badge
            $check_stmt = $conn->prepare("SELECT user_badge_id FROM user_badges WHERE user_id = ? AND badge_id = ?");
            $check_stmt->bind_param("ii", $user_id, $badge['badge_id']);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows === 0) {
                // Award the badge
                $award_stmt = $conn->prepare("INSERT INTO user_badges (user_id, badge_id) VALUES (?, ?)");
                $award_stmt->bind_param("ii", $user_id, $badge['badge_id']);
                if ($award_stmt->execute()) {
                    $newly_unlocked[] = $badge['name'];
                }
                $award_stmt->close();
            }
            $check_stmt->close();
        }
    }
    
    $stats_stmt->close();
    return $newly_unlocked;
}

/**
 * Calculate user's level based on points
 * @param int $points Total points earned
 * @return array Array with 'level' and 'next_level_points'
 */
function calculateLevel($points = 0) {
    $level_thresholds = [
        1 => 0,
        2 => 50,
        3 => 150,
        4 => 300,
        5 => 500,
        6 => 750,
        7 => 1050,
        8 => 1400,
        9 => 1800,
        10 => 2250
    ];
    
    $current_level = 1;
    $next_level_points = $level_thresholds[2];
    
    for ($level = 10; $level >= 1; $level--) {
        if (isset($level_thresholds[$level]) && $points >= $level_thresholds[$level]) {
            $current_level = $level;
            $next_level_points = isset($level_thresholds[$level + 1]) ? $level_thresholds[$level + 1] : null;
            break;
        }
    }
    
    return [
        'level' => $current_level,
        'current_points' => $points,
        'points_for_current_level' => $level_thresholds[$current_level],
        'next_level_points' => $next_level_points,
        'progress_percentage' => $next_level_points ? round((($points - $level_thresholds[$current_level]) / ($next_level_points - $level_thresholds[$current_level])) * 100, 1) : 100,
        'points_to_next_level' => $next_level_points ? max(0, $next_level_points - $points) : 0
    ];
}

/**
 * Get all available badges
 * @param mysqli $conn Database connection
 * @return mysqli_result Result set of badges
 */
function getAllBadges($conn) {
    $stmt = $conn->prepare("SELECT badge_id, badge_name, badge_description, badge_icon, points_required FROM badges ORDER BY badge_id ASC");
    $stmt->execute();
    return $stmt->get_result();
}

/**
 * Get user's earned badges
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @return mysqli_result Result set of user's badges
 */
function getUserBadges($conn, $user_id) {
    $stmt = $conn->prepare("
        SELECT b.badge_id, b.badge_name, b.badge_description, b.badge_icon, ub.unlocked_at
        FROM user_badges ub
        INNER JOIN badges b ON ub.badge_id = b.badge_id
        WHERE ub.user_id = ?
        ORDER BY ub.unlocked_at DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result();
}

/**
 * Get user's level and progress
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @return array Level information
 */
function getUserLevel($conn, $user_id) {
    $stmt = $conn->prepare("SELECT total_points FROM user_points WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $total_points = $result['total_points'] ?? 0;
    return calculateLevel($total_points);
}

/**
 * Get level icon/SVG based on level
 * @param int $level Level number
 * @return string SVG icon markup
 */
function getLevelIcon($level) {
    $icons = [
        1 => '<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" fill="#8B4513" stroke="#654321"/><text x="12" y="16" text-anchor="middle" font-size="10" font-weight="bold" fill="#fff">1</text></svg>',
        2 => '<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" fill="#C0C0C0" stroke="#A8A8A8"/><text x="12" y="16" text-anchor="middle" font-size="10" font-weight="bold" fill="#333">2</text></svg>',
        3 => '<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" fill="#3B82F6" stroke="#2563EB"/><text x="12" y="16" text-anchor="middle" font-size="10" font-weight="bold" fill="#fff">3</text></svg>',
        4 => '<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" fill="#22C55E" stroke="#16A34A"/><text x="12" y="16" text-anchor="middle" font-size="10" font-weight="bold" fill="#fff">4</text></svg>',
        5 => '<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" fill="#EAB308" stroke="#CA8A04"/><text x="12" y="16" text-anchor="middle" font-size="10" font-weight="bold" fill="#fff">5</text></svg>',
        6 => '<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" fill="#EF4444" stroke="#DC2626"/><text x="12" y="16" text-anchor="middle" font-size="10" font-weight="bold" fill="#fff">6</text></svg>',
        7 => '<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" fill="#A855F7" stroke="#9333EA"/><text x="12" y="16" text-anchor="middle" font-size="10" font-weight="bold" fill="#fff">7</text></svg>',
        8 => '<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" fill="#FFD700" stroke="#FFC700"/></svg>',
        9 => '<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" fill="#FFD700" stroke="#FFC700"/><circle cx="12" cy="12" r="3" fill="#fff"/></svg>',
        10 => '<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L4 7v10l8 5 8-5V7z" fill="#FFD700" stroke="#FFC700"/><circle cx="12" cy="12" r="4" fill="#fff"/></svg>'
    ];
    return $icons[$level] ?? '<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>';
}

/**
 * Get level name based on level number
 * @param int $level Level number
 * @return string Level name
 */
function getLevelName($level) {
    $names = [
        1 => 'Newcomer',
        2 => 'Organizer',
        3 => 'Coordinator',
        4 => 'Guardian',
        5 => 'Champion',
        6 => 'Master',
        7 => 'Expert',
        8 => 'Sage',
        9 => 'Legend',
        10 => 'Mythical'
    ];
    return $names[$level] ?? 'Unknown';
}

?>