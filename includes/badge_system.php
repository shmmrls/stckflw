<?php
/**
 * Badge and Level Management System
 * Handles badge unlocking logic and level calculations
 */

/**
 * Check and award badges for user actions with group-type-specific context
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @return array Array of newly unlocked badges
 */
function checkAndAwardBadges($conn, $user_id) {
    $newly_unlocked = [];
    
    // Get all groups user belongs to
    $groups_stmt = $conn->prepare("
        SELECT g.group_id, g.group_name, g.group_type
        FROM groups g
        INNER JOIN group_members gm ON g.group_id = gm.group_id
        WHERE gm.user_id = ?
    ");
    $groups_stmt->bind_param("i", $user_id);
    $groups_stmt->execute();
    $user_groups = $groups_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $groups_stmt->close();
    
    // Check badges for each group individually
    foreach ($user_groups as $group) {
        $group_id = $group['group_id'];
        $group_type = $group['group_type'];
        
        // Get stats for this specific group
        $stats_stmt = $conn->prepare("
            SELECT 
                COUNT(DISTINCT ci.item_id) as total_items_added,
                SUM(CASE WHEN ciu.update_type = 'consumed' THEN 1 ELSE 0 END) as total_consumed,
                SUM(CASE WHEN ciu.update_type = 'added' THEN 1 ELSE 0 END) as total_actions,
                (SELECT total_points FROM user_points WHERE user_id = ?) as total_points,
                COUNT(DISTINCT ub.badge_id) as badges_earned
            FROM customer_items ci
            LEFT JOIN customer_inventory_updates ciu ON ci.item_id = ciu.item_id
            LEFT JOIN user_badges ub ON ub.user_id = ? AND ub.badge_id IN (1,2,3,4,5)
            WHERE ci.created_by = ? AND ci.group_id = ?
        ");
        $stats_stmt->bind_param("iiii", $user_id, $user_id, $user_id, $group_id);
        $stats_stmt->execute();
        $stats = $stats_stmt->get_result()->fetch_assoc();
        $stats_stmt->close();
        
        // Define group-type-specific badge conditions
        $badge_conditions = [];
        
        switch ($group_type) {
            case 'household':
                $badge_conditions = [
                    // Badge ID 1: Family Organizer - Add 5 items
                    [
                        'badge_id' => 1,
                        'condition' => ($stats['total_items_added'] ?? 0) >= 5,
                        'name' => 'Family Organizer'
                    ],
                    // Badge ID 2: Waste Reducer - Log consumption 15+ times
                    [
                        'badge_id' => 2,
                        'condition' => ($stats['total_consumed'] ?? 0) >= 15,
                        'name' => 'Waste Reducer'
                    ],
                    // Badge ID 3: Smart Shopper - Earn 150+ points
                    [
                        'badge_id' => 3,
                        'condition' => ($stats['total_points'] ?? 0) >= 150,
                        'name' => 'Smart Shopper'
                    ],
                    // Badge ID 4: Active Member - Consume 8+ items
                    [
                        'badge_id' => 4,
                        'condition' => ($stats['total_consumed'] ?? 0) >= 8,
                        'name' => 'Active Member'
                    ],
                    // Badge ID 5: Household Hero - Earn 300+ points
                    [
                        'badge_id' => 5,
                        'condition' => ($stats['total_points'] ?? 0) >= 300,
                        'name' => 'Household Hero'
                    ],
                ];
                break;
                
            case 'co_living':
                $badge_conditions = [
                    // Badge ID 1: Roommate Coordinator - Add 3 items
                    [
                        'badge_id' => 1,
                        'condition' => ($stats['total_items_added'] ?? 0) >= 3,
                        'name' => 'Roommate Coordinator'
                    ],
                    // Badge ID 2: Shared Resource Manager - Log consumption 25+ times
                    [
                        'badge_id' => 2,
                        'condition' => ($stats['total_consumed'] ?? 0) >= 25,
                        'name' => 'Shared Resource Manager'
                    ],
                    // Badge ID 3: Community Leader - Earn 200+ points
                    [
                        'badge_id' => 3,
                        'condition' => ($stats['total_points'] ?? 0) >= 200,
                        'name' => 'Community Leader'
                    ],
                    // Badge ID 4: Team Player - Consume 12+ items
                    [
                        'badge_id' => 4,
                        'condition' => ($stats['total_consumed'] ?? 0) >= 12,
                        'name' => 'Team Player'
                    ],
                    // Badge ID 5: Co-living Champion - Earn 400+ points
                    [
                        'badge_id' => 5,
                        'condition' => ($stats['total_points'] ?? 0) >= 400,
                        'name' => 'Co-living Champion'
                    ],
                ];
                break;
                
            case 'small_business':
                $badge_conditions = [
                    // Badge ID 1: Inventory Manager - Add 10 items
                    [
                        'badge_id' => 1,
                        'condition' => ($stats['total_items_added'] ?? 0) >= 10,
                        'name' => 'Inventory Manager'
                    ],
                    // Badge ID 2: Efficiency Expert - Log consumption 30+ times
                    [
                        'badge_id' => 2,
                        'condition' => ($stats['total_consumed'] ?? 0) >= 30,
                        'name' => 'Efficiency Expert'
                    ],
                    // Badge ID 3: Business Pro - Earn 250+ points
                    [
                        'badge_id' => 3,
                        'condition' => ($stats['total_points'] ?? 0) >= 250,
                        'name' => 'Business Pro'
                    ],
                    // Badge ID 4: Staff Leader - Consume 15+ items
                    [
                        'badge_id' => 4,
                        'condition' => ($stats['total_consumed'] ?? 0) >= 15,
                        'name' => 'Staff Leader'
                    ],
                    // Badge ID 5: Operations Master - Earn 500+ points
                    [
                        'badge_id' => 5,
                        'condition' => ($stats['total_points'] ?? 0) >= 500,
                        'name' => 'Operations Master'
                    ],
                ];
                break;
                
            default:
                // Fallback to original generic badges
                $badge_conditions = [
                    [
                        'badge_id' => 1,
                        'condition' => ($stats['total_items_added'] ?? 0) >= 5,
                        'name' => 'Newbie Organizer'
                    ],
                    [
                        'badge_id' => 2,
                        'condition' => ($stats['total_consumed'] ?? 0) >= 20,
                        'name' => 'Waste Warrior'
                    ],
                    [
                        'badge_id' => 3,
                        'condition' => ($stats['total_points'] ?? 0) >= 200,
                        'name' => 'Inventory Master'
                    ],
                    [
                        'badge_id' => 4,
                        'condition' => ($stats['total_consumed'] ?? 0) >= 10,
                        'name' => 'Active Helper'
                    ],
                    [
                        'badge_id' => 5,
                        'condition' => ($stats['total_points'] ?? 0) >= 500,
                        'name' => 'Power User'
                    ],
                ];
                break;
        }
        
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
    }
    
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
 * Get user's group type
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @return string Group type
 */
function getUserGroupType($conn, $user_id) {
    $stmt = $conn->prepare("
        SELECT g.group_type 
        FROM groups g
        JOIN group_members gm ON g.group_id = gm.group_id
        WHERE gm.user_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['group_type'] ?? 'household';
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
 * Get user's level and progress with group context
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @return array Level information with group type
 */
function getUserLevelWithGroup($conn, $user_id) {
    $level_info = getUserLevel($conn, $user_id);
    $level_info['group_type'] = getUserGroupType($conn, $user_id);
    return $level_info;
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
 * Get level name based on level number and group type
 * @param int $level Level number
 * @param string $group_type Group type (household, co_living, small_business)
 * @return string Level name
 */
function getLevelName($level, $group_type = null) {
    // Default names if no group type specified
    $default_names = [
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
    
    // Group-type specific names
    $household_names = [
        1 => 'Family Member',
        2 => 'Helper',
        3 => 'Organizer',
        4 => 'Planner',
        5 => 'Manager',
        6 => 'Super Parent',
        7 => 'Family Expert',
        8 => 'Household Master',
        9 => 'Family Legend',
        10 => 'Ultimate Parent'
    ];
    
    $co_living_names = [
        1 => 'New Roommate',
        2 => 'Contributor',
        3 => 'Team Player',
        4 => 'Coordinator',
        5 => 'Community Leader',
        6 => 'House Manager',
        7 => 'Resource Expert',
        8 => 'Co-living Master',
        9 => 'Community Legend',
        10 => 'Ultimate Roommate'
    ];
    
    $business_names = [
        1 => 'Trainee',
        2 => 'Staff Member',
        3 => 'Operator',
        4 => 'Supervisor',
        5 => 'Manager',
        6 => 'Team Leader',
        7 => 'Operations Expert',
        8 => 'Business Master',
        9 => 'Executive',
        10 => 'CEO Level'
    ];
    
    switch ($group_type) {
        case 'household':
            return $household_names[$level] ?? $default_names[$level] ?? 'Unknown';
        case 'co_living':
            return $co_living_names[$level] ?? $default_names[$level] ?? 'Unknown';
        case 'small_business':
            return $business_names[$level] ?? $default_names[$level] ?? 'Unknown';
        default:
            return $default_names[$level] ?? 'Unknown';
    }
}

?>