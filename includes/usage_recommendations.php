<?php
/**
 * Usage Recommendations System
 * Provides recipe suggestions and usage tips based on available items
 * Helps reduce waste and improve item usage
 */

if (!function_exists('getDBConnection')) {
    require_once __DIR__ . '/config.php';
}

/**
 * Get items expiring soon for a user
 */
function getExpiringSoonItems($conn, $user_id, $days = 7) {
    $query = "
        SELECT 
            ci.item_id,
            ci.item_name,
            ci.quantity,
            ci.unit,
            ci.expiry_date,
            ci.expiry_status,
            c.category_name,
            DATEDIFF(ci.expiry_date, CURDATE()) as days_until_expiry
        FROM customer_items ci
        JOIN categories c ON ci.category_id = c.category_id
        JOIN groups g ON ci.group_id = g.group_id
        JOIN group_members gm ON g.group_id = gm.group_id
        WHERE gm.user_id = ?
        AND ci.expiry_date >= CURDATE()
        AND ci.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
        AND ci.quantity > 0
        ORDER BY ci.expiry_date ASC, ci.item_name ASC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $user_id, $days);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get all available items for a user (not expired, quantity > 0)
 */
function getAvailableItems($conn, $user_id) {
    $query = "
        SELECT 
            ci.item_id,
            ci.item_name,
            ci.quantity,
            ci.unit,
            ci.expiry_date,
            ci.expiry_status,
            c.category_name,
            c.category_id,
            DATEDIFF(ci.expiry_date, CURDATE()) as days_until_expiry
        FROM customer_items ci
        JOIN categories c ON ci.category_id = c.category_id
        JOIN groups g ON ci.group_id = g.group_id
        JOIN group_members gm ON g.group_id = gm.group_id
        WHERE gm.user_id = ?
        AND ci.expiry_date >= CURDATE()
        AND ci.quantity > 0
        ORDER BY c.category_name, ci.expiry_date ASC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get items grouped by category
 */
function getItemsByCategory($conn, $user_id) {
    $items = getAvailableItems($conn, $user_id);
    $grouped = [];
    
    foreach ($items as $item) {
        $category = $item['category_name'];
        if (!isset($grouped[$category])) {
            $grouped[$category] = [];
        }
        $grouped[$category][] = $item;
    }
    
    return $grouped;
}

/**
 * Recipe database with ingredients and instructions
 */
function getRecipeDatabase() {
    return [
        // Dairy-based recipes
        [
            'id' => 1,
            'name' => 'Classic Cheese Omelette',
            'category' => 'Breakfast',
            'prep_time' => '10 min',
            'servings' => 2,
            'difficulty' => 'Easy',
            'ingredients' => ['Dairy', 'Meat'],
            'primary_items' => ['cheese', 'egg', 'milk'],
            'description' => 'A fluffy omelette with melted cheese',
            'instructions' => [
                'Beat eggs with a splash of milk',
                'Heat butter in a pan over medium heat',
                'Pour eggs and let set for 1-2 minutes',
                'Add shredded cheese and fold omelette',
                'Cook until cheese melts and serve hot'
            ],
            'tips' => 'Use cheese that\'s nearing expiry - it melts perfectly!'
        ],
        [
            'id' => 2,
            'name' => 'Yogurt Parfait',
            'category' => 'Breakfast',
            'prep_time' => '5 min',
            'servings' => 1,
            'difficulty' => 'Easy',
            'ingredients' => ['Dairy', 'Produce'],
            'primary_items' => ['yogurt', 'fruit', 'berry'],
            'description' => 'Healthy layered yogurt with fruits',
            'instructions' => [
                'Layer yogurt in a glass',
                'Add fresh or frozen berries',
                'Top with granola if available',
                'Drizzle with honey',
                'Serve immediately'
            ],
            'tips' => 'Perfect for using yogurt close to expiry date'
        ],
        
        // Meat-based recipes
        [
            'id' => 3,
            'name' => 'Stir-Fried Chicken & Vegetables',
            'category' => 'Main Course',
            'prep_time' => '20 min',
            'servings' => 4,
            'difficulty' => 'Medium',
            'ingredients' => ['Meat', 'Produce', 'Pantry'],
            'primary_items' => ['chicken', 'vegetable', 'onion', 'garlic'],
            'description' => 'Quick and healthy stir-fry',
            'instructions' => [
                'Cut chicken into bite-sized pieces',
                'Heat oil in a wok or large pan',
                'Stir-fry chicken until golden',
                'Add vegetables and cook until tender-crisp',
                'Season with soy sauce and serve with rice'
            ],
            'tips' => 'Great way to use vegetables nearing expiry'
        ],
        [
            'id' => 4,
            'name' => 'Beef Tacos',
            'category' => 'Main Course',
            'prep_time' => '15 min',
            'servings' => 4,
            'difficulty' => 'Easy',
            'ingredients' => ['Meat', 'Produce', 'Dairy'],
            'primary_items' => ['beef', 'tomato', 'cheese', 'lettuce'],
            'description' => 'Flavorful ground beef tacos',
            'instructions' => [
                'Brown ground beef in a pan',
                'Add taco seasoning and water',
                'Simmer until thickened',
                'Warm tortillas and fill with beef',
                'Top with cheese, lettuce, and tomatoes'
            ],
            'tips' => 'Use up fresh produce before it spoils'
        ],
        
        // Produce-focused recipes
        [
            'id' => 5,
            'name' => 'Fresh Garden Salad',
            'category' => 'Salad',
            'prep_time' => '10 min',
            'servings' => 2,
            'difficulty' => 'Easy',
            'ingredients' => ['Produce', 'Pantry'],
            'primary_items' => ['lettuce', 'tomato', 'cucumber', 'carrot'],
            'description' => 'Crisp mixed vegetable salad',
            'instructions' => [
                'Wash and chop all vegetables',
                'Toss in a large bowl',
                'Mix olive oil, vinegar, salt, and pepper',
                'Drizzle dressing over salad',
                'Toss and serve immediately'
            ],
            'tips' => 'Perfect for using vegetables close to expiry'
        ],
        [
            'id' => 6,
            'name' => 'Vegetable Soup',
            'category' => 'Soup',
            'prep_time' => '30 min',
            'servings' => 6,
            'difficulty' => 'Easy',
            'ingredients' => ['Produce', 'Pantry'],
            'primary_items' => ['potato', 'carrot', 'onion', 'celery'],
            'description' => 'Hearty homemade vegetable soup',
            'instructions' => [
                'Chop all vegetables into bite-sized pieces',
                'Sauté onions in a large pot',
                'Add remaining vegetables and broth',
                'Simmer for 20-25 minutes',
                'Season with herbs and serve hot'
            ],
            'tips' => 'Excellent way to use multiple vegetables at once'
        ],
        
        // Frozen items
        [
            'id' => 7,
            'name' => 'Berry Smoothie Bowl',
            'category' => 'Breakfast',
            'prep_time' => '5 min',
            'servings' => 1,
            'difficulty' => 'Easy',
            'ingredients' => ['Frozen', 'Dairy', 'Produce'],
            'primary_items' => ['berry', 'banana', 'yogurt', 'milk'],
            'description' => 'Thick and creamy smoothie bowl',
            'instructions' => [
                'Blend frozen berries with yogurt and milk',
                'Pour into a bowl',
                'Top with fresh fruit and granola',
                'Add a drizzle of honey',
                'Enjoy with a spoon'
            ],
            'tips' => 'Great for using frozen berries and ripe bananas'
        ],
        
        // Pantry staples
        [
            'id' => 8,
            'name' => 'Pasta Primavera',
            'category' => 'Main Course',
            'prep_time' => '20 min',
            'servings' => 4,
            'difficulty' => 'Easy',
            'ingredients' => ['Pantry', 'Produce', 'Dairy'],
            'primary_items' => ['pasta', 'vegetable', 'tomato', 'cheese'],
            'description' => 'Light pasta with fresh vegetables',
            'instructions' => [
                'Cook pasta according to package directions',
                'Sauté vegetables in olive oil',
                'Add cooked pasta to vegetables',
                'Toss with cheese and herbs',
                'Serve hot with extra cheese'
            ],
            'tips' => 'Versatile recipe - use any vegetables you have'
        ],
        [
            'id' => 9,
            'name' => 'Fried Rice',
            'category' => 'Main Course',
            'prep_time' => '15 min',
            'servings' => 4,
            'difficulty' => 'Easy',
            'ingredients' => ['Pantry', 'Produce', 'Meat'],
            'primary_items' => ['rice', 'egg', 'vegetable', 'onion'],
            'description' => 'Classic vegetable fried rice',
            'instructions' => [
                'Use day-old rice for best results',
                'Scramble eggs and set aside',
                'Stir-fry vegetables in hot oil',
                'Add rice and soy sauce',
                'Mix in eggs and serve hot'
            ],
            'tips' => 'Perfect for using leftover rice and vegetables'
        ],
        
        // Mixed recipes
        [
            'id' => 10,
            'name' => 'Chicken Caesar Salad',
            'category' => 'Salad',
            'prep_time' => '15 min',
            'servings' => 2,
            'difficulty' => 'Easy',
            'ingredients' => ['Meat', 'Produce', 'Dairy'],
            'primary_items' => ['chicken', 'lettuce', 'cheese'],
            'description' => 'Classic Caesar with grilled chicken',
            'instructions' => [
                'Grill or pan-fry chicken breast',
                'Chop romaine lettuce',
                'Toss with Caesar dressing',
                'Top with sliced chicken and cheese',
                'Add croutons and serve'
            ],
            'tips' => 'Use grilled chicken within 2 days of cooking'
        ]
    ];
}

/**
 * Get recipe suggestions based on available items
 */
function getRecipeSuggestions($conn, $user_id, $prioritize_expiring = true) {
    $available_items = getAvailableItems($conn, $user_id);
    $expiring_items = getExpiringSoonItems($conn, $user_id, 7);
    $recipes = getRecipeDatabase();
    
    // Create item name lookup (lowercase for matching)
    $item_names = array_map('strtolower', array_column($available_items, 'item_name'));
    $expiring_names = array_map('strtolower', array_column($expiring_items, 'item_name'));
    
    // Get available categories
    $categories = array_unique(array_column($available_items, 'category_name'));
    
    $suggestions = [];
    
    foreach ($recipes as $recipe) {
        $match_score = 0;
        $has_expiring = false;
        $matched_items = [];
        
        // Check category match
        foreach ($recipe['ingredients'] as $required_category) {
            if (in_array($required_category, $categories)) {
                $match_score += 10;
            }
        }
        
        // Check primary items match
        foreach ($recipe['primary_items'] as $primary_item) {
            foreach ($item_names as $available_item) {
                if (strpos($available_item, $primary_item) !== false || 
                    strpos($primary_item, $available_item) !== false) {
                    $match_score += 20;
                    $matched_items[] = $available_item;
                    
                    // Bonus if item is expiring
                    if (in_array($available_item, $expiring_names)) {
                        $match_score += 30;
                        $has_expiring = true;
                    }
                    break;
                }
            }
        }
        
        // Only suggest if there's a reasonable match
        if ($match_score >= 20) {
            $suggestions[] = [
                'recipe' => $recipe,
                'match_score' => $match_score,
                'has_expiring_items' => $has_expiring,
                'matched_items' => array_unique($matched_items)
            ];
        }
    }
    
    // Sort by match score
    usort($suggestions, function($a, $b) use ($prioritize_expiring) {
        if ($prioritize_expiring) {
            // Prioritize recipes with expiring items
            if ($a['has_expiring_items'] != $b['has_expiring_items']) {
                return $b['has_expiring_items'] - $a['has_expiring_items'];
            }
        }
        return $b['match_score'] - $a['match_score'];
    });
    
    return array_slice($suggestions, 0, 6); // Return top 6 matches
}

/**
 * Get usage tips for specific items
 */
function getUsageTips($item_name, $category, $days_until_expiry) {
    $tips = [];
    
    // Category-specific tips
    $category_tips = [
        'Dairy' => [
            'general' => 'Store in the coldest part of your refrigerator',
            'expiring' => 'Use in cooking - heat extends usability for dishes',
            'ideas' => ['Add to smoothies', 'Use in baking', 'Make cheese sauce', 'Freeze for later use']
        ],
        'Meat' => [
            'general' => 'Keep refrigerated at 40°F or below',
            'expiring' => 'Cook immediately and refrigerate or freeze',
            'ideas' => ['Cook and freeze portions', 'Make stir-fry', 'Grill for meal prep', 'Add to pasta dishes']
        ],
        'Produce' => [
            'general' => 'Store in crisper drawer for best freshness',
            'expiring' => 'Cook, chop and freeze, or make soup',
            'ideas' => ['Make vegetable stock', 'Roast for meal prep', 'Blend into smoothies', 'Create stir-fry']
        ],
        'Frozen' => [
            'general' => 'Keep frozen until ready to use',
            'expiring' => 'Use in cooked dishes or smoothies',
            'ideas' => ['Add to soups', 'Make smoothie bowls', 'Use in baked goods', 'Thaw and eat']
        ],
        'Beverages' => [
            'general' => 'Refrigerate after opening',
            'expiring' => 'Use in recipes or freeze into ice cubes',
            'ideas' => ['Make ice cubes', 'Use in cooking', 'Create cocktails', 'Freeze for popsicles']
        ],
        'Pantry' => [
            'general' => 'Store in cool, dry place',
            'expiring' => 'Use in your next meal',
            'ideas' => ['Make a large batch', 'Combine into mixed dishes', 'Share with neighbors', 'Donate if unopened']
        ]
    ];
    
    $cat_tip = $category_tips[$category] ?? $category_tips['Pantry'];
    
    // Add urgency-based tips
    if ($days_until_expiry <= 1) {
        $tips[] = [
            'urgency' => 'high',
            'title' => 'Use Immediately',
            'message' => 'This item expires ' . ($days_until_expiry == 0 ? 'today' : 'tomorrow') . '! ' . $cat_tip['expiring'],
            'ideas' => $cat_tip['ideas']
        ];
    } elseif ($days_until_expiry <= 3) {
        $tips[] = [
            'urgency' => 'medium',
            'title' => 'Use Within 3 Days',
            'message' => 'Plan to use this item soon. ' . $cat_tip['expiring'],
            'ideas' => $cat_tip['ideas']
        ];
    } elseif ($days_until_expiry <= 7) {
        $tips[] = [
            'urgency' => 'low',
            'title' => 'Use This Week',
            'message' => 'Add to your meal plan this week. ' . $cat_tip['general'],
            'ideas' => array_slice($cat_tip['ideas'], 0, 2)
        ];
    } else {
        $tips[] = [
            'urgency' => 'none',
            'title' => 'Storage Tip',
            'message' => $cat_tip['general'],
            'ideas' => []
        ];
    }
    
    return $tips;
}

/**
 * Get comprehensive usage recommendations for a user
 */
function getUsageRecommendations($conn, $user_id) {
    $expiring_items = getExpiringSoonItems($conn, $user_id, 7);
    $recipes = getRecipeSuggestions($conn, $user_id, true);
    
    $recommendations = [];
    
    foreach ($expiring_items as $item) {
        $tips = getUsageTips($item['item_name'], $item['category_name'], $item['days_until_expiry']);
        
        $recommendations[] = [
            'item' => $item,
            'tips' => $tips
        ];
    }
    
    return [
        'expiring_items' => $recommendations,
        'recipe_suggestions' => $recipes,
        'total_expiring' => count($expiring_items)
    ];
}

/**
 * Get statistics for dashboard
 */
function getUsageStats($conn, $user_id) {
    $expiring_1day = count(getExpiringSoonItems($conn, $user_id, 1));
    $expiring_3days = count(getExpiringSoonItems($conn, $user_id, 3));
    $expiring_7days = count(getExpiringSoonItems($conn, $user_id, 7));
    $total_items = count(getAvailableItems($conn, $user_id));
    
    return [
        'expiring_today' => $expiring_1day,
        'expiring_3days' => $expiring_3days,
        'expiring_week' => $expiring_7days,
        'total_items' => $total_items,
        'at_risk_percentage' => $total_items > 0 ? round(($expiring_7days / $total_items) * 100) : 0
    ];
}