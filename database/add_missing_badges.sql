-- Badge System Migration
-- Adds missing badges to match the current badge schema
-- Run this after updating to the new badge system

-- Add badge ID 4 and 5 if they don't exist
INSERT IGNORE INTO `badges` (`badge_id`, `badge_name`, `badge_description`, `badge_icon`, `points_required`, `created_at`) VALUES
(4, 'Active Helper', 'Logged consumption 10 times', NULL, 100, NOW()),
(5, 'Power User', 'Earned 500 points from tracking', NULL, 500, NOW());

-- Alternative: Update existing badges to match logic
UPDATE `badges` SET 
    `badge_description` = 'Add 5 items to your inventory'
WHERE `badge_id` = 1;

UPDATE `badges` SET 
    `badge_description` = 'Log 20+ consumption actions'
WHERE `badge_id` = 2;

UPDATE `badges` SET 
    `badge_description` = 'Earn 200+ points through tracking'
WHERE `badge_id` = 3;

