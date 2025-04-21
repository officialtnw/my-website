/*
 Navicat Premium Data Transfer

 Source Server         : Databases
 Source Server Type    : MySQL
 Source Server Version : 80041 (8.0.41)
 Source Host           : localhost:3306
 Source Schema         : strv_db

 Target Server Type    : MySQL
 Target Server Version : 80041 (8.0.41)
 File Encoding         : 65001

 Date: 19/04/2025 01:32:03
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for checkin_form_templates
-- ----------------------------
DROP TABLE IF EXISTS `checkin_form_templates`;
CREATE TABLE `checkin_form_templates`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `coach_id` int NOT NULL,
  `form_type` enum('daily','weekly') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `form_fields` json NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `coach_id`(`coach_id` ASC) USING BTREE,
  CONSTRAINT `checkin_form_templates_ibfk_1` FOREIGN KEY (`coach_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of checkin_form_templates
-- ----------------------------
INSERT INTO `checkin_form_templates` VALUES (1, 22, 'weekly', '{\"0\": {\"name\": \"Average Weekly Weight Last Week\", \"type\": \"number\"}, \"1\": {\"name\": \"Average Weekly Weight This Week\", \"type\": \"number\"}, \"2\": {\"name\": \"Weekly Weight Loss or gain\", \"type\": \"number\"}, \"3\": {\"name\": \"How did you go this week with your Diet/Macros. Did you deviate ? If so briefly explain.\", \"type\": \"text\"}, \"4\": {\"name\": \"Do you want any meals changed, if so which meal and your preference?\", \"type\": \"text\"}, \"6\": {\"name\": \"Average Daily Steps\", \"type\": \"number\"}, \"7\": {\"name\": \"How do you feel/overall well being?\", \"type\": \"text\"}, \"8\": {\"name\": \"How was your gym performance and pumps this week (did you progress on most exercises)\", \"type\": \"text\"}, \"9\": {\"name\": \"Quality of sleep\", \"type\": \"rating\"}, \"10\": {\"name\": \"Hunger\", \"type\": \"rating\"}, \"11\": {\"name\": \"Digestion\", \"type\": \"rating\"}, \"12\": {\"name\": \"Stress\", \"type\": \"rating\"}, \"13\": {\"name\": \"Fatigue\", \"type\": \"rating\"}, \"14\": {\"name\": \"Energy\", \"type\": \"rating\"}, \"15\": {\"name\": \"Recovery\", \"type\": \"rating\"}, \"16\": {\"name\": \"Libido\", \"type\": \"rating\"}, \"17\": {\"name\": \"Blood Pressure\", \"type\": \"number\"}, \"18\": {\"name\": \"Glucose level\", \"type\": \"number\"}, \"19\": {\"name\": \"Any other Info or questions?\", \"type\": \"text\"}}', '2025-04-03 23:42:44', '2025-04-08 07:01:56');

-- ----------------------------
-- Table structure for checkin_submission_logs
-- ----------------------------
DROP TABLE IF EXISTS `checkin_submission_logs`;
CREATE TABLE `checkin_submission_logs`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `form_type` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `submission_data` json NOT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `error_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 8 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of checkin_submission_logs
-- ----------------------------
INSERT INTO `checkin_submission_logs` VALUES (1, 27, 'weekly', '{\"Steps\": \"9\", \"Weight\": \"9\", \"Recovery\": \"5\", \"Training\": \"9\"}', 'success', NULL, '2025-04-05 04:06:26');
INSERT INTO `checkin_submission_logs` VALUES (2, 22, 'daily', '{\"Mood\": \"5\", \"Notes\": \"\", \"Weight\": \"69\"}', 'success', NULL, '2025-04-05 10:31:36');
INSERT INTO `checkin_submission_logs` VALUES (3, 22, 'daily', '{\"Mood\": \"5\", \"Notes\": \"9767\", \"Weight\": \"69\"}', 'success', NULL, '2025-04-05 10:31:49');
INSERT INTO `checkin_submission_logs` VALUES (4, 22, 'daily', '{\"Mood\": \"10\", \"Notes\": \"lol\", \"Weight\": \"99\"}', 'success', NULL, '2025-04-05 10:32:10');
INSERT INTO `checkin_submission_logs` VALUES (5, 27, 'daily', '{\"Mood\": \"10\", \"Notes\": \"Strong\", \"Weight\": \"81\"}', 'success', NULL, '2025-04-08 05:58:00');
INSERT INTO `checkin_submission_logs` VALUES (6, 27, 'weekly', '{\"drt\": \"10\", \"Steps\": \"1000\", \"Weight\": \"94\", \"Movement\": \"10\", \"Recovery\": \"5\", \"Training\": \"4\"}', 'success', NULL, '2025-04-08 06:50:07');
INSERT INTO `checkin_submission_logs` VALUES (7, 27, 'weekly', '{\"Energy\": \"8\", \"Hunger\": \"5\", \"Libido\": \"5\", \"Stress\": \"5\", \"Fatigue\": \"8\", \"Recovery\": \"7\", \"Digestion\": \"2\", \"Glucose level\": \"10\", \"Blood Pressure\": \"10\", \"Quality of sleep\": \"8\", \"Average Daily Steps\": \"10000\", \"Weekly Weight Loss or gain\": \"10\", \"Any other Info or questions?\": \"nope\", \"Average Weekly Weight Last Week\": \"85\", \"Average Weekly Weight This Week\": \"95\", \"How do you feel/overall well being?\": \"good\", \"Do you want any meals changed, if so which meal and your preference?\": \"Bad\", \"How was your gym performance and pumps this week (did you progress on most exercises)\": \"good\", \"How did you go this week with your Diet/Macros. Did you deviate ? If so briefly explain.\": \"Good\"}', 'success', NULL, '2025-04-08 08:20:59');

-- ----------------------------
-- Table structure for checkin_submissions
-- ----------------------------
DROP TABLE IF EXISTS `checkin_submissions`;
CREATE TABLE `checkin_submissions`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `coach_id` int NOT NULL DEFAULT 0,
  `form_type` enum('daily','weekly') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `submission_data` json NOT NULL,
  `submitted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `needs_response` tinyint(1) NULL DEFAULT 1,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `user_id`(`user_id` ASC) USING BTREE,
  INDEX `coach_id`(`coach_id` ASC) USING BTREE,
  CONSTRAINT `checkin_submissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `checkin_submissions_ibfk_2` FOREIGN KEY (`coach_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 8 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of checkin_submissions
-- ----------------------------
INSERT INTO `checkin_submissions` VALUES (1, 27, 22, 'weekly', '{\"Steps\": \"9\", \"Weight\": \"9\", \"Recovery\": \"5\", \"Training\": \"9\"}', '2025-04-05 08:06:26', 1);
INSERT INTO `checkin_submissions` VALUES (2, 22, 22, 'daily', '{\"Mood\": \"5\", \"Notes\": \"\", \"Weight\": \"69\"}', '2025-04-05 14:31:36', 1);
INSERT INTO `checkin_submissions` VALUES (3, 22, 22, 'daily', '{\"Mood\": \"5\", \"Notes\": \"9767\", \"Weight\": \"69\"}', '2025-04-05 14:31:49', 1);
INSERT INTO `checkin_submissions` VALUES (4, 22, 22, 'daily', '{\"Mood\": \"10\", \"Notes\": \"lol\", \"Weight\": \"99\"}', '2025-04-05 14:32:10', 1);
INSERT INTO `checkin_submissions` VALUES (5, 27, 22, 'daily', '{\"Mood\": \"10\", \"Notes\": \"Strong\", \"Weight\": \"81\"}', '2025-04-08 09:58:00', 1);
INSERT INTO `checkin_submissions` VALUES (6, 27, 22, 'weekly', '{\"drt\": \"10\", \"Steps\": \"1000\", \"Weight\": \"94\", \"Movement\": \"10\", \"Recovery\": \"5\", \"Training\": \"4\"}', '2025-04-08 10:50:07', 1);
INSERT INTO `checkin_submissions` VALUES (7, 27, 22, 'weekly', '{\"Energy\": \"8\", \"Hunger\": \"5\", \"Libido\": \"5\", \"Stress\": \"5\", \"Fatigue\": \"8\", \"Recovery\": \"7\", \"Digestion\": \"2\", \"Glucose level\": \"10\", \"Blood Pressure\": \"10\", \"Quality of sleep\": \"8\", \"Average Daily Steps\": \"10000\", \"Weekly Weight Loss or gain\": \"10\", \"Any other Info or questions?\": \"nope\", \"Average Weekly Weight Last Week\": \"85\", \"Average Weekly Weight This Week\": \"95\", \"How do you feel/overall well being?\": \"good\", \"Do you want any meals changed, if so which meal and your preference?\": \"Bad\", \"How was your gym performance and pumps this week (did you progress on most exercises)\": \"good\", \"How did you go this week with your Diet/Macros. Did you deviate ? If so briefly explain.\": \"Good\"}', '2025-04-08 12:20:59', 1);

-- ----------------------------
-- Table structure for checkins
-- ----------------------------
DROP TABLE IF EXISTS `checkins`;
CREATE TABLE `checkins`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NULL DEFAULT NULL,
  `weight` decimal(5, 2) NULL DEFAULT NULL,
  `recovery` int NULL DEFAULT NULL,
  `energy` int NULL DEFAULT NULL,
  `steps` int NULL DEFAULT NULL,
  `checkin_date` date NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `user_id`(`user_id` ASC) USING BTREE,
  CONSTRAINT `checkins_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of checkins
-- ----------------------------

-- ----------------------------
-- Table structure for consultations
-- ----------------------------
DROP TABLE IF EXISTS `consultations`;
CREATE TABLE `consultations`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `preferred_date` date NOT NULL,
  `preferred_time` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 31 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of consultations
-- ----------------------------
INSERT INTO `consultations` VALUES (5, 'Hans', 'hanzanadahami@gmail.com', '0422196208', '2025-03-29', 'late_afternoon', '2025-03-28 00:27:26');
INSERT INTO `consultations` VALUES (6, 'Dineth', 'dddineth@gmail.com', '0406737398', '2025-03-29', 'late_afternoon', '2025-03-28 00:28:05');
INSERT INTO `consultations` VALUES (7, 'adeesha', 'adeesha.perera18@gmail.com', '0467987654', '2025-03-31', 'morning', '2025-03-31 05:19:16');
INSERT INTO `consultations` VALUES (8, 'YjHTZIYI', 'dyereloyizap2004@gmail.com', '9054653713', '2025-04-01', 'late_afternoon', '2025-04-01 01:11:53');
INSERT INTO `consultations` VALUES (9, 'QAdEbOlAaAVSR', 'rgrantcu1983@gmail.com', '3705885265', '2025-04-02', 'late_afternoon', '2025-04-02 01:44:58');
INSERT INTO `consultations` VALUES (10, 'kzaEKxvlGGKf', 'djipruitcz@gmail.com', '5602544362', '2025-04-02', 'late_afternoon', '2025-04-02 13:55:00');
INSERT INTO `consultations` VALUES (11, 'MyName', 'vyybzeqr@testing-your-form.info', '+98 0658091686', '2004-04-09', 'Preferred Time *', '2025-04-04 00:00:43');
INSERT INTO `consultations` VALUES (12, 'yymoxMKKVHYaBjz', 'diksdownst1987@gmail.com', '6657050477', '2025-04-05', 'late_afternoon', '2025-04-05 01:04:10');
INSERT INTO `consultations` VALUES (13, 'NUaMsJPiVxfRkcp', 'josh.dino535646@yahoo.com', '5731854335', '2025-04-05', 'late_afternoon', '2025-04-05 03:10:23');
INSERT INTO `consultations` VALUES (14, 'sGmDyxcbLTbbHM', 'vangbelindtd@gmail.com', '4025507723', '2025-04-05', 'late_afternoon', '2025-04-05 05:07:10');
INSERT INTO `consultations` VALUES (15, 'qWwtkqhTgMUKzm', 'priddleu@gmail.com', '3821672584', '2025-04-06', 'late_afternoon', '2025-04-06 04:20:04');
INSERT INTO `consultations` VALUES (16, 'WPoILKWNwd', 'bdjid38@gmail.com', '5001888673', '2025-04-07', 'late_afternoon', '2025-04-07 14:19:17');
INSERT INTO `consultations` VALUES (17, 'fKVcORkfyZJZ', 'ashliioneillq@gmail.com', '5830344205', '2025-04-07', 'late_afternoon', '2025-04-07 15:31:39');
INSERT INTO `consultations` VALUES (18, 'mtNrjmYJRRD', 'djisbertayala19@gmail.com', '4634803095', '2025-04-08', 'late_afternoon', '2025-04-08 06:54:58');
INSERT INTO `consultations` VALUES (19, 'gTPgjEiMBcvutGH', 'bemrobert722608@yahoo.com', '5590149889', '2025-04-08', 'late_afternoon', '2025-04-08 10:39:11');
INSERT INTO `consultations` VALUES (20, 'nECxUNRPIB', 'fergusonmorton55@gmail.com', '4096306516', '2025-04-09', 'late_afternoon', '2025-04-09 02:52:37');
INSERT INTO `consultations` VALUES (21, 'DOJXbJitOfOq', 'trung.borgeson351206@yahoo.com', '4942675016', '2025-04-09', 'late_afternoon', '2025-04-09 12:31:36');
INSERT INTO `consultations` VALUES (22, 'dtDFomAZyIUYtRg', 'penningtonadisony1982@gmail.com', '5664990371', '2025-04-09', 'late_afternoon', '2025-04-09 16:49:49');
INSERT INTO `consultations` VALUES (23, 'uPCYhznzCAtShHu', 'delfiyamcknightu20@gmail.com', '7604336998', '2025-04-10', 'late_afternoon', '2025-04-09 20:36:20');
INSERT INTO `consultations` VALUES (24, 'WTeHKDihb', 'djaredscr@gmail.com', '7310790894', '2025-04-10', 'late_afternoon', '2025-04-10 15:59:58');
INSERT INTO `consultations` VALUES (25, 'EnqfOVAznFnZSB', 'gensjg@gmail.com', '3606034162', '2025-04-11', 'late_afternoon', '2025-04-10 21:00:52');
INSERT INTO `consultations` VALUES (26, 'cMAYPYqTimYJ', 'cordovamaksainpe2003@gmail.com', '5988735872', '2025-04-12', 'late_afternoon', '2025-04-12 04:03:42');
INSERT INTO `consultations` VALUES (27, 'vIrfMKXDzCE', 'ylaney1983@gmail.com', '4491348039', '2025-04-14', 'late_afternoon', '2025-04-14 16:04:20');
INSERT INTO `consultations` VALUES (28, 'buoSgiGzDameU', 'ginasantos620075@yahoo.com', '9503342594', '2025-04-15', 'late_afternoon', '2025-04-14 20:50:03');
INSERT INTO `consultations` VALUES (29, 'gvMrvWwEdCypuG', 'yotkinlonga5@gmail.com', '5256432102', '2025-04-18', 'late_afternoon', '2025-04-18 10:06:46');
INSERT INTO `consultations` VALUES (30, 'Hello', 'wwyrpxkh@testing-your-form.info', '+98 3438870495', '2013-04-07', 'Preferred Time *', '2025-04-18 14:20:23');

-- ----------------------------
-- Table structure for exercises
-- ----------------------------
DROP TABLE IF EXISTS `exercises`;
CREATE TABLE `exercises`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL,
  `category` enum('Push','Pull','Legs','Core') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'Push',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 37 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of exercises
-- ----------------------------
INSERT INTO `exercises` VALUES (1, 'Bench Press', 'A compound exercise targeting the chest, shoulders, and triceps.', 'Push', '2025-03-28 06:29:27');
INSERT INTO `exercises` VALUES (2, 'Incline Bench Press', 'Targets the upper chest, shoulders, and triceps.', 'Push', '2025-03-28 06:29:27');
INSERT INTO `exercises` VALUES (3, 'Dumbbell Flyes', 'Isolation exercise for the chest, focusing on the pectorals.', 'Push', '2025-03-28 06:29:27');
INSERT INTO `exercises` VALUES (4, 'Cable Crossovers', 'Isolation for the chest, emphasizing the inner and outer pecs.', 'Push', '2025-03-28 06:29:27');
INSERT INTO `exercises` VALUES (5, 'Push-Ups', 'Bodyweight exercise for the chest, shoulders, and triceps.', 'Push', '2025-03-28 06:29:27');
INSERT INTO `exercises` VALUES (6, 'Incline Dumbbell Press', 'Targets the upper chest with a focus on stabilization.', 'Push', '2025-03-28 06:29:27');
INSERT INTO `exercises` VALUES (7, 'Pull-Ups', 'Compound exercise for the lats, biceps, and upper back.', 'Pull', '2025-03-28 06:29:27');
INSERT INTO `exercises` VALUES (8, 'Barbell Row', 'Targets the lats, rhomboids, and traps.', 'Pull', '2025-03-28 06:29:27');
INSERT INTO `exercises` VALUES (9, 'Lat Pulldown', 'Machine exercise for the lats and upper back.', 'Pull', '2025-03-28 06:29:27');
INSERT INTO `exercises` VALUES (10, 'Seated Cable Row', 'Focuses on the mid-back, lats, and biceps.', 'Pull', '2025-03-28 06:29:27');
INSERT INTO `exercises` VALUES (11, 'Dumbbell Pullover', 'Targets the lats and chest, with some tricep involvement.', 'Pull', '2025-03-28 06:29:27');
INSERT INTO `exercises` VALUES (12, 'Overhead Press', 'Compound exercise for the deltoids, triceps, and upper chest.', 'Push', '2025-03-28 06:29:27');
INSERT INTO `exercises` VALUES (13, 'Lateral Raises', 'Isolation for the medial deltoids (side shoulders).', 'Push', '2025-03-28 06:29:27');
INSERT INTO `exercises` VALUES (14, 'Front Raises', 'Isolation for the anterior deltoids (front shoulders).', 'Push', '2025-03-28 06:29:27');
INSERT INTO `exercises` VALUES (15, 'Rear Delt Flyes', 'Isolation for the posterior deltoids (rear shoulders).', 'Pull', '2025-03-28 06:29:27');
INSERT INTO `exercises` VALUES (16, 'Arnold Press', 'Dumbbell press variation for all three deltoid heads.', 'Push', '2025-03-28 06:29:27');
INSERT INTO `exercises` VALUES (17, 'Bicep Curl (Barbell)', 'Isolation for the biceps.', 'Pull', '2025-03-28 06:29:27');
INSERT INTO `exercises` VALUES (18, 'Dumbbell Hammer Curl', 'Targets the biceps and brachialis.', 'Pull', '2025-03-28 06:29:27');
INSERT INTO `exercises` VALUES (19, 'Tricep Dips', 'Compound exercise for the triceps, also hits the chest and shoulders.', 'Push', '2025-03-28 06:29:27');
INSERT INTO `exercises` VALUES (20, 'Tricep Pushdown (Cable)', 'Isolation for the triceps.', 'Push', '2025-03-28 06:29:27');
INSERT INTO `exercises` VALUES (21, 'Concentration Curl', 'Isolation for the biceps, focusing on peak contraction.', 'Pull', '2025-03-28 06:29:27');
INSERT INTO `exercises` VALUES (22, 'Squats', 'A lower body exercise targeting quads, hamstrings, and glutes.', 'Legs', '2025-03-28 06:29:27');
INSERT INTO `exercises` VALUES (23, 'Deadlift', 'A full-body exercise focusing on the posterior chain.', 'Legs', '2025-03-28 06:29:27');
INSERT INTO `exercises` VALUES (24, 'Front Squat', 'Compound exercise emphasizing the quads and core.', 'Legs', '2025-03-28 06:29:27');
INSERT INTO `exercises` VALUES (25, 'Romanian Deadlift', 'Targets the hamstrings, glutes, and lower back.', 'Legs', '2025-03-28 06:29:27');
INSERT INTO `exercises` VALUES (26, 'Leg Press', 'Machine exercise for the quads, hamstrings, and glutes.', 'Legs', '2025-03-28 06:29:27');
INSERT INTO `exercises` VALUES (27, 'Lunges', 'Targets the quads, glutes, and hamstrings, with balance benefits.', 'Legs', '2025-03-28 06:29:27');
INSERT INTO `exercises` VALUES (28, 'Leg Extension', 'Isolation for the quads.', 'Legs', '2025-03-28 06:29:27');
INSERT INTO `exercises` VALUES (29, 'Leg Curl (Lying)', 'Isolation for the hamstrings.', 'Legs', '2025-03-28 06:29:27');
INSERT INTO `exercises` VALUES (30, 'Calf Raises (Standing)', 'Isolation for the calves (gastrocnemius).', 'Legs', '2025-03-28 06:29:27');
INSERT INTO `exercises` VALUES (31, 'Calf Raises (Seated)', 'Isolation for the calves (soleus).', 'Legs', '2025-03-28 06:29:27');
INSERT INTO `exercises` VALUES (32, 'Plank', 'Bodyweight exercise for the core, focusing on stability.', 'Core', '2025-03-28 06:29:27');
INSERT INTO `exercises` VALUES (33, 'Hanging Leg Raise', 'Targets the lower abs and hip flexors.', 'Core', '2025-03-28 06:29:27');
INSERT INTO `exercises` VALUES (34, 'Russian Twists', 'Works the obliques and transverse abdominis.', 'Core', '2025-03-28 06:29:27');
INSERT INTO `exercises` VALUES (35, 'Cable Woodchoppers', 'Targets the obliques with rotational movement.', 'Core', '2025-03-28 06:29:27');
INSERT INTO `exercises` VALUES (36, 'Reverse Barbell Curl', NULL, 'Pull', '2025-03-28 09:03:51');

-- ----------------------------
-- Table structure for plans
-- ----------------------------
DROP TABLE IF EXISTS `plans`;
CREATE TABLE `plans`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `plan_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `price` decimal(10, 2) NOT NULL,
  `max_clients` int NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 4 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of plans
-- ----------------------------
INSERT INTO `plans` VALUES (1, 'basic_plan', 'Basic Plan', 35.00, 25, 'Up to 25 clients, basic features');
INSERT INTO `plans` VALUES (2, 'essential_plan', 'Essential Plan', 69.00, 50, 'Up to 50 clients, advanced features');
INSERT INTO `plans` VALUES (3, 'growth_plan', 'Growth Plan', 99.00, 200, 'Up to 200 clients, all features');

-- ----------------------------
-- Table structure for prescribed_working_sets
-- ----------------------------
DROP TABLE IF EXISTS `prescribed_working_sets`;
CREATE TABLE `prescribed_working_sets`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `prescribed_workout_id` int NOT NULL,
  `set_number` int NOT NULL,
  `prescribed_reps` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `prescribed_weight` decimal(5, 2) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `prescribed_workout_id`(`prescribed_workout_id` ASC) USING BTREE,
  CONSTRAINT `prescribed_working_sets_ibfk_1` FOREIGN KEY (`prescribed_workout_id`) REFERENCES `prescribed_workouts` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 64 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of prescribed_working_sets
-- ----------------------------
INSERT INTO `prescribed_working_sets` VALUES (23, 10, 1, '10-12', NULL);
INSERT INTO `prescribed_working_sets` VALUES (24, 10, 2, '12-15', NULL);
INSERT INTO `prescribed_working_sets` VALUES (26, 12, 1, '10-12', NULL);
INSERT INTO `prescribed_working_sets` VALUES (27, 12, 2, '10-12', NULL);
INSERT INTO `prescribed_working_sets` VALUES (28, 13, 1, '10-12', NULL);
INSERT INTO `prescribed_working_sets` VALUES (29, 13, 2, '12-15', NULL);
INSERT INTO `prescribed_working_sets` VALUES (30, 17, 1, '10-12', NULL);
INSERT INTO `prescribed_working_sets` VALUES (31, 18, 1, '10-12', NULL);
INSERT INTO `prescribed_working_sets` VALUES (32, 18, 2, '10-12', NULL);
INSERT INTO `prescribed_working_sets` VALUES (33, 19, 1, '10-12', NULL);
INSERT INTO `prescribed_working_sets` VALUES (34, 19, 2, '10-12', NULL);
INSERT INTO `prescribed_working_sets` VALUES (35, 20, 1, '10-15', NULL);
INSERT INTO `prescribed_working_sets` VALUES (36, 20, 2, '12-15', NULL);
INSERT INTO `prescribed_working_sets` VALUES (37, 21, 1, '10-12', NULL);
INSERT INTO `prescribed_working_sets` VALUES (38, 21, 2, '10-12', NULL);
INSERT INTO `prescribed_working_sets` VALUES (39, 22, 1, '10-15', NULL);
INSERT INTO `prescribed_working_sets` VALUES (40, 22, 2, '10-12', NULL);
INSERT INTO `prescribed_working_sets` VALUES (41, 23, 1, '15', NULL);
INSERT INTO `prescribed_working_sets` VALUES (42, 23, 2, '10', NULL);
INSERT INTO `prescribed_working_sets` VALUES (43, 24, 1, '12-15', NULL);
INSERT INTO `prescribed_working_sets` VALUES (44, 24, 2, '12-15', NULL);
INSERT INTO `prescribed_working_sets` VALUES (45, 25, 1, '6-8', NULL);
INSERT INTO `prescribed_working_sets` VALUES (46, 25, 2, '8', NULL);
INSERT INTO `prescribed_working_sets` VALUES (47, 26, 1, '12-15', NULL);
INSERT INTO `prescribed_working_sets` VALUES (48, 26, 2, '12-16', NULL);
INSERT INTO `prescribed_working_sets` VALUES (49, 27, 1, '12', NULL);
INSERT INTO `prescribed_working_sets` VALUES (50, 27, 2, '14', NULL);
INSERT INTO `prescribed_working_sets` VALUES (51, 28, 1, '15', NULL);
INSERT INTO `prescribed_working_sets` VALUES (52, 28, 2, '12', NULL);
INSERT INTO `prescribed_working_sets` VALUES (53, 29, 1, '20', NULL);
INSERT INTO `prescribed_working_sets` VALUES (54, 29, 2, '15', NULL);
INSERT INTO `prescribed_working_sets` VALUES (55, 30, 1, '10', NULL);
INSERT INTO `prescribed_working_sets` VALUES (56, 31, 1, '50', NULL);
INSERT INTO `prescribed_working_sets` VALUES (57, 32, 1, '10-12', NULL);
INSERT INTO `prescribed_working_sets` VALUES (58, 33, 1, '10-12', NULL);
INSERT INTO `prescribed_working_sets` VALUES (59, 34, 1, '10-15', NULL);
INSERT INTO `prescribed_working_sets` VALUES (60, 34, 2, '12-15', NULL);
INSERT INTO `prescribed_working_sets` VALUES (61, 35, 1, '15', NULL);
INSERT INTO `prescribed_working_sets` VALUES (62, 35, 2, '10-12', NULL);
INSERT INTO `prescribed_working_sets` VALUES (63, 36, 1, '10-12', NULL);

-- ----------------------------
-- Table structure for prescribed_workouts
-- ----------------------------
DROP TABLE IF EXISTS `prescribed_workouts`;
CREATE TABLE `prescribed_workouts`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `exercise_id` int NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `user_id`(`user_id` ASC) USING BTREE,
  INDEX `prescribed_workouts_ibfk_2`(`exercise_id` ASC) USING BTREE,
  CONSTRAINT `prescribed_workouts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `prescribed_workouts_ibfk_2` FOREIGN KEY (`exercise_id`) REFERENCES `exercises` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 37 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of prescribed_workouts
-- ----------------------------
INSERT INTO `prescribed_workouts` VALUES (10, 6, 1, 'Monday', '2025-03-28 00:31:58');
INSERT INTO `prescribed_workouts` VALUES (12, 4, 1, 'Monday', '2025-03-28 07:00:25');
INSERT INTO `prescribed_workouts` VALUES (13, 4, 3, 'Monday', '2025-03-28 07:00:51');
INSERT INTO `prescribed_workouts` VALUES (17, 4, 5, 'Monday', '2025-03-28 07:06:46');
INSERT INTO `prescribed_workouts` VALUES (18, 4, 14, 'Monday', '2025-03-28 07:07:19');
INSERT INTO `prescribed_workouts` VALUES (19, 4, 12, 'Monday', '2025-03-28 07:07:39');
INSERT INTO `prescribed_workouts` VALUES (20, 4, 19, 'Monday', '2025-03-28 07:12:35');
INSERT INTO `prescribed_workouts` VALUES (21, 4, 7, 'Wednesday', '2025-03-28 07:13:04');
INSERT INTO `prescribed_workouts` VALUES (22, 4, 8, 'Wednesday', '2025-03-28 07:13:44');
INSERT INTO `prescribed_workouts` VALUES (23, 4, 9, 'Wednesday', '2025-03-28 07:14:15');
INSERT INTO `prescribed_workouts` VALUES (24, 4, 15, 'Wednesday', '2025-03-28 07:14:51');
INSERT INTO `prescribed_workouts` VALUES (25, 4, 17, 'Wednesday', '2025-03-28 07:15:09');
INSERT INTO `prescribed_workouts` VALUES (26, 4, 29, 'Friday', '2025-03-28 07:15:37');
INSERT INTO `prescribed_workouts` VALUES (27, 4, 27, 'Friday', '2025-03-28 07:16:04');
INSERT INTO `prescribed_workouts` VALUES (28, 4, 26, 'Friday', '2025-03-28 07:16:21');
INSERT INTO `prescribed_workouts` VALUES (29, 4, 31, 'Friday', '2025-03-28 07:16:40');
INSERT INTO `prescribed_workouts` VALUES (30, 4, 33, 'Friday', '2025-03-28 07:16:56');
INSERT INTO `prescribed_workouts` VALUES (31, 4, 32, 'Friday', '2025-03-28 07:17:22');
INSERT INTO `prescribed_workouts` VALUES (32, 3, 5, 'Monday', '2025-03-28 07:22:57');
INSERT INTO `prescribed_workouts` VALUES (33, 9, 13, 'Monday', '2025-03-28 09:02:18');
INSERT INTO `prescribed_workouts` VALUES (34, 3, 14, 'Monday', '2025-03-28 11:30:56');
INSERT INTO `prescribed_workouts` VALUES (35, 3, 16, 'Monday', '2025-03-28 11:31:10');
INSERT INTO `prescribed_workouts` VALUES (36, 9, 3, 'Monday', '2025-03-30 11:07:39');

-- ----------------------------
-- Table structure for ranks
-- ----------------------------
DROP TABLE IF EXISTS `ranks`;
CREATE TABLE `ranks`  (
  `rank_id` int NOT NULL,
  `rank_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `max_clients` int NULL DEFAULT NULL,
  `can_manage_users` tinyint(1) NULL DEFAULT 0,
  `can_manage_clients` tinyint(1) NULL DEFAULT 0,
  `requires_subscription` tinyint(1) NULL DEFAULT 0,
  PRIMARY KEY (`rank_id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of ranks
-- ----------------------------
INSERT INTO `ranks` VALUES (1, 'Owner', 1000000, 1, 1, 0);
INSERT INTO `ranks` VALUES (2, 'Coach', 25, 0, 1, 1);
INSERT INTO `ranks` VALUES (3, 'Client', 0, 0, 0, 0);

-- ----------------------------
-- Table structure for subscription_plans
-- ----------------------------
DROP TABLE IF EXISTS `subscription_plans`;
CREATE TABLE `subscription_plans`  (
  `plan_id` int NOT NULL AUTO_INCREMENT,
  `plan_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `rank_id` int NOT NULL,
  `max_clients` int NULL DEFAULT NULL,
  `price` decimal(10, 2) NOT NULL,
  PRIMARY KEY (`plan_id`) USING BTREE,
  INDEX `rank_id`(`rank_id` ASC) USING BTREE,
  CONSTRAINT `subscription_plans_ibfk_1` FOREIGN KEY (`rank_id`) REFERENCES `ranks` (`rank_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 4 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of subscription_plans
-- ----------------------------
INSERT INTO `subscription_plans` VALUES (1, 'basic', 2, 20, 1.00);
INSERT INTO `subscription_plans` VALUES (2, 'pro', 2, 50, 2.00);
INSERT INTO `subscription_plans` VALUES (3, 'elite', 2, NULL, 3.00);

-- ----------------------------
-- Table structure for subscriptions
-- ----------------------------
DROP TABLE IF EXISTS `subscriptions`;
CREATE TABLE `subscriptions`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `stripe_customer_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `stripe_subscription_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `plan_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `max_clients` int NULL DEFAULT 0,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `user_id`(`user_id` ASC) USING BTREE,
  CONSTRAINT `subscriptions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 20 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of subscriptions
-- ----------------------------
INSERT INTO `subscriptions` VALUES (6, 9, 'cus_S1bMOsIVYKomLx', 'sub_1R7YAUIKQRhPPdf8MNMN5xcx', 'paid', '2025-03-28 04:20:49', '2025-03-28 04:28:08', 'testing_plan', 0);
INSERT INTO `subscriptions` VALUES (7, 10, 'cus_S1bMOsIVYKomLx', NULL, 'pending', '2025-03-28 04:30:00', '2025-03-28 04:30:00', 'testing_plan', 0);
INSERT INTO `subscriptions` VALUES (8, 11, 'cus_S1bgQTIe481Hpt', NULL, 'pending', '2025-03-28 04:40:41', '2025-03-28 04:40:41', 'testing_plan', 0);
INSERT INTO `subscriptions` VALUES (10, 13, 'cus_S1c96wneu63Es6', NULL, 'pending', '2025-03-28 05:09:29', '2025-03-28 05:09:29', 'testing_plan', 0);
INSERT INTO `subscriptions` VALUES (11, 14, 'cus_S1cJFbm4hbSqPa', 'sub_1R7Z6xIKQRhPPdf8RlCU9cKf', 'paid', '2025-03-28 05:20:15', '2025-03-28 05:22:10', 'testing_plan', 0);
INSERT INTO `subscriptions` VALUES (12, 15, 'cus_S1cNWX85cM96RX', 'sub_1R7Z9WIKQRhPPdf8sgWoLmu8', 'paid', '2025-03-28 05:23:48', '2025-03-28 05:24:49', 'testing_plan', 0);
INSERT INTO `subscriptions` VALUES (13, 4, NULL, NULL, 'friends_family', '2025-03-28 06:59:31', '2025-03-28 06:59:31', 'friends_family', 0);
INSERT INTO `subscriptions` VALUES (14, 16, 'cus_S1g0RU9hXkSVCj', 'sub_1R7cgKIKQRhPPdf8tstnJEyj', 'paid', '2025-03-28 09:08:52', '2025-03-28 09:10:57', '1_session_week', 0);
INSERT INTO `subscriptions` VALUES (15, 20, NULL, 'sub_1R9el2IKQRhPPdf8HvGPJQDb', 'paid', '2025-04-02 23:48:19', '2025-04-02 23:48:19', 'basic', 25);
INSERT INTO `subscriptions` VALUES (16, 21, NULL, 'sub_1R9euUIKQRhPPdf8QNHSX3Un', 'paid', '2025-04-02 23:57:56', '2025-04-02 23:57:56', 'basic', 25);
INSERT INTO `subscriptions` VALUES (17, 22, NULL, 'sub_1R9f8tIKQRhPPdf80usuz5cx', 'paid', '2025-04-03 00:12:58', '2025-04-03 00:12:58', 'pro', 75);
INSERT INTO `subscriptions` VALUES (18, 23, NULL, 'sub_1R9fDLIKQRhPPdf8IWKaZiZj', 'paid', '2025-04-03 00:17:25', '2025-04-03 00:17:25', 'pro', 50);
INSERT INTO `subscriptions` VALUES (19, 26, NULL, 'sub_1R9fzhIKQRhPPdf8cs26W9de', 'paid', '2025-04-03 01:07:27', '2025-04-03 01:07:27', 'basic', 20);

-- ----------------------------
-- Table structure for users
-- ----------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `first_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `last_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `rank_perms` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fitness_goals` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL,
  `protein` int NULL DEFAULT NULL COMMENT 'Grams of protein per day',
  `carbs` int NULL DEFAULT NULL COMMENT 'Grams of carbohydrates per day',
  `fats` int NULL DEFAULT NULL COMMENT 'Grams of fats per day',
  `unit_preference` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT 'kg',
  `stripe_customer_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `current_weight` decimal(5, 2) NULL DEFAULT NULL,
  `age` int NULL DEFAULT NULL,
  `package_weeks` int NULL DEFAULT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `start_weight` decimal(5, 2) NULL DEFAULT NULL,
  `checkin_day` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `coach_id` int NOT NULL DEFAULT -1,
  `max_clients` int NULL DEFAULT 0,
  `parent_coach_id` int NULL DEFAULT NULL,
  `referral_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `registered_ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `last_ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `country` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `subscription_plan` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `subscription_status` enum('pending','paid') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT 'pending',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `email`(`email` ASC) USING BTREE,
  UNIQUE INDEX `email_2`(`email` ASC) USING BTREE,
  UNIQUE INDEX `referral_code`(`referral_code` ASC) USING BTREE,
  INDEX `users_ibfk_3`(`coach_id` ASC) USING BTREE,
  INDEX `users_ibfk_5`(`parent_coach_id` ASC) USING BTREE,
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`coach_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `users_ibfk_2` FOREIGN KEY (`parent_coach_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `users_ibfk_3` FOREIGN KEY (`coach_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `users_ibfk_5` FOREIGN KEY (`parent_coach_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE SET NULL
) ENGINE = InnoDB AUTO_INCREMENT = 49 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of users
-- ----------------------------
INSERT INTO `users` VALUES (1, 'tnw', '', 'lol@gmail.com', '$2y$10$EbM8lv0fG6ufl5OVFUmTI.Zpuv9C71smLpwKbBiXmWeCs40eR2CZS', 1, '2025-03-27 09:00:42', 'Rich', 500, 1000, 20, 'kg', NULL, 84.00, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 'OWNER1380', NULL, '172.69.166.12', NULL, NULL, 'pending');
INSERT INTO `users` VALUES (3, 'Thanujaya', '', 'officialtnw@gmail.com', '$2y$10$TbUqkC/rC6kYZ9OOMPfRIeMcIFxcIFmOqwl9/oX1foKG.1uiWXpPe', 1, '2025-03-28 03:41:17', 'Increase Muscle Mass', 264, 200, 50, 'kg', NULL, 76.00, NULL, NULL, NULL, NULL, NULL, 3, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'pending');
INSERT INTO `users` VALUES (4, 'Tas', '', 'tastsapas@gmail.com', '$2y$10$RQXU3vrgsLX0mUc8jVnoMOxjmsJDi0DKH9Yg/dvCtpA3v2Ti6c5IC', 0, '2025-03-28 04:21:20', NULL, NULL, NULL, NULL, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, NULL, NULL, '172.69.186.184', NULL, NULL, 'pending');
INSERT INTO `users` VALUES (5, 'Hans', '', 'hanzanadahami@gmail.com', '$2y$10$29KvIr21l7EmDrSj0gUET.Hr8VjffVkQtETXJT8x0128RHlHNlV82', 0, '2025-03-28 04:28:28', NULL, NULL, NULL, NULL, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 5, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'pending');
INSERT INTO `users` VALUES (6, 'Dimiya', '', 'dddineth@gmail.com', '$2y$10$1dkNnD/BNoc7iaSMAIVZoOM/m3XH3CDhmruuRr0QTNjplCGqfZdCS', 0, '2025-03-28 04:28:49', 'Rich', 1000, 1000, 1000, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 6, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'pending');
INSERT INTO `users` VALUES (7, 'test', '', 'test@strv.com', '$2y$10$FzuvbmqVW92jhK/eioFFsOZ/eRrlrWvcg5amTzE54J3NNNH1qOBY.', 2, '2025-03-28 06:06:36', '', NULL, NULL, NULL, 'kg', NULL, 59.00, NULL, NULL, NULL, NULL, NULL, 6, 25, NULL, 'COACH7774', NULL, NULL, NULL, NULL, 'pending');
INSERT INTO `users` VALUES (9, 'Adeesha', '', 'a@gmail.com', '$2y$10$h9W3DOlttNY3ZkBbxVi8rOlXchqJOvxNJbWEsI/GaBWH1DCD0nPIi', 0, '2025-03-28 08:20:34', NULL, NULL, NULL, NULL, 'kg', 'cus_S1bMOsIVYKomLx', NULL, NULL, NULL, NULL, NULL, NULL, 9, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'pending');
INSERT INTO `users` VALUES (10, 'Jane', '', 'Jane@gmail.com', '$2y$10$fDwO4R85ORWbhkV9tEQ3Q.8EeEkCqIoUs.583402KfnsWAPvfEITO', 0, '2025-03-28 08:29:43', NULL, NULL, NULL, NULL, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'pending');
INSERT INTO `users` VALUES (11, 'Bob', '', 'bob@gmail.com', '$2y$10$v1MSBft/ccIwbKNU/TzMfuKU9taUur6OBCCBrCJkp4qleLX/FI4/q', 0, '2025-03-28 08:40:24', NULL, NULL, NULL, NULL, 'kg', 'cus_S1bgQTIe481Hpt', NULL, NULL, NULL, NULL, NULL, NULL, 11, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'pending');
INSERT INTO `users` VALUES (12, 'anna', '', 'anna@gmail.com', '$2y$10$jm.mnZIaBTdjoL6/8WjJ/.mzOmmQ/lt.bbecYIvJZY/JxZwFu0QNm', 0, '2025-03-28 08:54:25', NULL, NULL, NULL, NULL, 'kg', 'cus_S1bu8Cvoo2xKI1', NULL, NULL, NULL, NULL, NULL, NULL, 12, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'pending');
INSERT INTO `users` VALUES (13, 'james', '', 'james@gmail.com', '$2y$10$XzKE8vTHRlAttb1FtglQu.RGYo8lPCjAPojqxNOqmM1an3Ob7kN9O', 0, '2025-03-28 09:09:09', NULL, NULL, NULL, NULL, 'kg', 'cus_S1c96wneu63Es6', NULL, NULL, NULL, NULL, NULL, NULL, 13, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'pending');
INSERT INTO `users` VALUES (14, 'TJ', '', 'tj@lol.com', '$2y$10$m0pwhKU2fQGvVqyrFxXHb.C2satt5P4ZGn1lHR5RonBYX2XcbQTmC', 0, '2025-03-28 09:20:02', NULL, NULL, NULL, NULL, 'kg', 'cus_S1cJFbm4hbSqPa', NULL, NULL, NULL, NULL, NULL, NULL, 14, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'pending');
INSERT INTO `users` VALUES (15, 'Kaizer', '', 'Kaizer@gmail.com', '$2y$10$CKM7I6AmupLep0ljcwtSZuwP8tEFgcpe4Ov5mu206tdI/haY1Dr6C', 0, '2025-03-28 09:23:24', NULL, NULL, NULL, NULL, 'kg', 'cus_S1cNWX85cM96RX', NULL, NULL, NULL, NULL, NULL, NULL, 15, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'pending');
INSERT INTO `users` VALUES (16, 'David', '', 'david@gmail.com', '$2y$10$n6zG7tX06ftx8rXIDeB.BeVshG3qNtLLWRFqVvcchxwTtSqOUO0dW', 0, '2025-03-28 13:07:37', NULL, NULL, NULL, NULL, 'kg', 'cus_S1g0RU9hXkSVCj', NULL, NULL, NULL, NULL, NULL, NULL, 16, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'pending');
INSERT INTO `users` VALUES (17, 'CZYKmugGM', '', 'djipruitcz@gmail.com', '$2y$10$lTXd0M9J.rXDKxcJ2Gqhm.xbrIeeR.5sNm24uP5sRuyHwYKJlxNou', 0, '2025-04-02 17:55:05', NULL, NULL, NULL, NULL, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 17, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'pending');
INSERT INTO `users` VALUES (18, '', '', '', '$2y$10$UPhVCMkMlNEsIP2TehsPEOZWS/IpdiJYGcFEUTyiMla58m5b9Z8Uy', 0, '2025-04-02 17:55:07', NULL, NULL, NULL, NULL, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 18, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'pending');
INSERT INTO `users` VALUES (19, 'test1', '', 'test1@gmail.com', '$2y$10$A04ax9JXlX4fZcPVhYh6iOFxqxkJnWGno9/W4ftjXk27ITtV2vl1S', 2, '2025-04-03 03:35:53', NULL, NULL, NULL, NULL, 'kg', 'cus_S3mEuBkKOPITVc', NULL, NULL, NULL, NULL, NULL, NULL, 19, 0, NULL, 'COACH19125', '172.69.176.138', '172.69.176.138', '0', 'basic', 'pending');
INSERT INTO `users` VALUES (20, 'test2', '', 'test2@gmail.com', '$2y$10$gqRe1bNIjpnfcn0hgpOac...8LdG2AmITCiDPCB85t.jBPWUsB5we', 2, '2025-04-03 03:45:56', NULL, NULL, NULL, NULL, 'kg', 'cus_S3mHQCAgeQ1a5r', NULL, NULL, NULL, NULL, NULL, 'Friday', 20, 25, NULL, 'COACH20839', '104.23.175.91', '104.23.175.91', '0', 'basic', 'paid');
INSERT INTO `users` VALUES (21, 'coach', '', 'coach@gmail.com', '$2y$10$9hp3loSjNheQKmnC5KxkTuoh1lNOJV3lWPEY/cN8RIz6KqAkfN75u', 2, '2025-04-03 03:57:15', NULL, NULL, NULL, NULL, 'kg', 'cus_S3mTbwY40hBlQk', NULL, NULL, NULL, NULL, NULL, NULL, 21, 25, NULL, 'COACH21272', '162.158.88.8', '162.158.88.8', '0', 'basic', 'paid');
INSERT INTO `users` VALUES (22, 'coach2', '', 'coach2@gmail.com', '$2y$10$hVA.bfBnrhjmABKRxnGPyOBtQFqdQX5F7oQAcFrqiBf3kqO7Uu7Em', 2, '2025-04-03 04:11:50', NULL, NULL, NULL, NULL, 'kg', 'cus_S3mhLT8lN4pHRq', NULL, NULL, NULL, NULL, NULL, NULL, 22, 75, NULL, 'COACH22621', '172.71.124.32', '162.158.162.37', '0', 'pro', 'paid');
INSERT INTO `users` VALUES (23, 'coach4', '', 'coach4@gmqi.com', '$2y$10$/t/.MSenkiFVBIvImDH3DujyGuA/f69ewL0dG5ebLfzEIolLfr3wm', 2, '2025-04-03 04:16:59', NULL, NULL, NULL, NULL, 'kg', 'cus_S3mmDmtoH4qYAJ', NULL, NULL, NULL, NULL, NULL, NULL, 23, 50, NULL, 'COACH23391', '162.158.189.72', '162.158.189.72', '0', 'pro', 'paid');
INSERT INTO `users` VALUES (24, 'TJ', 'Lol', 'tj@gmail.com', '$2y$10$4Cge.wViTqrAF/WR8KqyxeP52YBaed1pvqVHkb.Jhe9yo4tV29oRC', 3, '2025-04-03 04:50:35', NULL, NULL, NULL, NULL, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 20, 0, NULL, NULL, '162.158.189.204', '162.158.189.204', 'UK', NULL, 'pending');
INSERT INTO `users` VALUES (25, 'TJ', 'thz', 'TJz@gmail.com', '$2y$10$EVfYVzcqIqU.x3HXn1uIHemI5ioHKBH9l7ZixRqVDf5tDYBCL7nw.', 3, '2025-04-03 04:55:26', NULL, NULL, NULL, NULL, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 22, 0, 22, NULL, '172.70.142.109', '172.70.142.109', 'UK', NULL, 'pending');
INSERT INTO `users` VALUES (26, 'TJZZ', 'TJZ', 'lols@gmail.com', '$2y$10$ikNfJvLeiCo3NVu3Zxa/Zeu8j.vNLVFAfVvuuBVd8QvVaUtcOWo1.', 2, '2025-04-03 05:06:32', NULL, NULL, NULL, NULL, 'kg', 'cus_S3na3XfEUTgDNv', NULL, NULL, NULL, NULL, NULL, NULL, 26, 20, NULL, 'COACH26262', '172.71.124.54', '172.71.124.54', '0', 'basic', 'paid');
INSERT INTO `users` VALUES (27, 'Adeesha', 'Perera', 'ada@gmail.com', '$2y$10$oic97BAyY5hEHR/WpA1ekuvjiNKZ6yctXTSjvV5gqT//JRs7gkdTO', 3, '2025-04-03 05:47:38', NULL, NULL, NULL, NULL, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, 'Tuesday', 22, 0, 22, NULL, '172.69.166.114', '172.71.124.227', 'UK', NULL, 'pending');
INSERT INTO `users` VALUES (28, 'g', 'gg', 'g@gmail.com', '$2y$10$URHTqF.RbIPtZqN7xfk2DuhrUbqTqzgNZqbHFSEdNzDyTojMbLviq', 3, '2025-04-03 03:00:15', NULL, NULL, NULL, NULL, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 22, 0, 22, NULL, NULL, NULL, NULL, NULL, 'pending');
INSERT INTO `users` VALUES (29, 'Adeesha', 'Ranuli', 'add@gmail.com', '$2y$10$7V3Z6G5/eRlL/5Zx0ySLKeUR7HggrG2Yb3PP/4Cf8ZuVIsT9LLm9q', 3, '2025-04-03 03:18:06', NULL, NULL, NULL, NULL, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 1, NULL, NULL, '172.70.189.98', NULL, NULL, 'pending');
INSERT INTO `users` VALUES (30, 'tMvcktjx', 'sGephdTR', 'diksdownst1987@gmail.com', '$2y$10$LxMcNj5jC.6BiwSoORHHiulb86GPStD5jkHVwkZiaD1v1hIluv7.i', 2, '2025-04-05 05:04:14', NULL, NULL, NULL, NULL, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 30, NULL, NULL, NULL, '172.71.25.139', '172.71.25.139', '0', 'elite', 'pending');

-- ----------------------------
-- Table structure for users_copy1
-- ----------------------------
DROP TABLE IF EXISTS `users_copy1`;
CREATE TABLE `users_copy1`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `first_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `last_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `rank_perms` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fitness_goals` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL,
  `protein` int NULL DEFAULT NULL COMMENT 'Grams of protein per day',
  `carbs` int NULL DEFAULT NULL COMMENT 'Grams of carbohydrates per day',
  `fats` int NULL DEFAULT NULL COMMENT 'Grams of fats per day',
  `unit_preference` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT 'kg',
  `stripe_customer_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `current_weight` decimal(5, 2) NULL DEFAULT NULL,
  `age` int NULL DEFAULT NULL,
  `package_weeks` int NULL DEFAULT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `start_weight` decimal(5, 2) NULL DEFAULT NULL,
  `checkin_day` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `coach_id` int NOT NULL DEFAULT 0,
  `max_clients` int NULL DEFAULT 0,
  `parent_coach_id` int NULL DEFAULT NULL,
  `referral_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `registered_ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `last_ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `country` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `subscription_plan` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `subscription_status` enum('pending','paid') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT 'pending',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `email`(`email` ASC) USING BTREE,
  UNIQUE INDEX `email_2`(`email` ASC) USING BTREE,
  UNIQUE INDEX `referral_code`(`referral_code` ASC) USING BTREE,
  INDEX `users_ibfk_3`(`coach_id` ASC) USING BTREE,
  INDEX `users_ibfk_5`(`parent_coach_id` ASC) USING BTREE,
  CONSTRAINT `users_copy1_ibfk_1` FOREIGN KEY (`coach_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `users_copy1_ibfk_2` FOREIGN KEY (`parent_coach_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `users_copy1_ibfk_3` FOREIGN KEY (`coach_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `users_copy1_ibfk_4` FOREIGN KEY (`parent_coach_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE SET NULL
) ENGINE = InnoDB AUTO_INCREMENT = 33 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of users_copy1
-- ----------------------------
INSERT INTO `users_copy1` VALUES (1, 'tnw', '', 'lol@gmail.com', '$2y$10$EbM8lv0fG6ufl5OVFUmTI.Zpuv9C71smLpwKbBiXmWeCs40eR2CZS', 1, '2025-03-27 09:00:42', 'Rich', 500, 1000, 20, 'kg', NULL, 84.00, NULL, NULL, NULL, NULL, NULL, 1, 0, NULL, 'OWNER1380', NULL, '172.70.188.106', NULL, NULL, 'pending');
INSERT INTO `users_copy1` VALUES (3, 'Thanujaya', '', 'officialtnw@gmail.com', '$2y$10$TbUqkC/rC6kYZ9OOMPfRIeMcIFxcIFmOqwl9/oX1foKG.1uiWXpPe', 1, '2025-03-28 03:41:17', 'Increase Muscle Mass', 264, 200, 50, 'kg', NULL, 76.00, NULL, NULL, NULL, NULL, NULL, 3, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'pending');
INSERT INTO `users_copy1` VALUES (4, 'Tas', '', 'tastsapas@gmail.com', '$2y$10$RQXU3vrgsLX0mUc8jVnoMOxjmsJDi0DKH9Yg/dvCtpA3v2Ti6c5IC', 0, '2025-03-28 04:21:20', NULL, NULL, NULL, NULL, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, 0, NULL, NULL, NULL, '172.69.186.62', NULL, NULL, 'pending');
INSERT INTO `users_copy1` VALUES (5, 'Hans', '', 'hanzanadahami@gmail.com', '$2y$10$29KvIr21l7EmDrSj0gUET.Hr8VjffVkQtETXJT8x0128RHlHNlV82', 0, '2025-03-28 04:28:28', NULL, NULL, NULL, NULL, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 5, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'pending');
INSERT INTO `users_copy1` VALUES (6, 'Dimiya', '', 'dddineth@gmail.com', '$2y$10$1dkNnD/BNoc7iaSMAIVZoOM/m3XH3CDhmruuRr0QTNjplCGqfZdCS', 0, '2025-03-28 04:28:49', 'Rich', 1000, 1000, 1000, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 6, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'pending');
INSERT INTO `users_copy1` VALUES (7, 'test', '', 'test@strv.com', '$2y$10$FzuvbmqVW92jhK/eioFFsOZ/eRrlrWvcg5amTzE54J3NNNH1qOBY.', 2, '2025-03-28 06:06:36', '', NULL, NULL, NULL, 'kg', NULL, 59.00, NULL, NULL, NULL, NULL, NULL, 6, 25, NULL, 'COACH7774', NULL, NULL, NULL, NULL, 'pending');
INSERT INTO `users_copy1` VALUES (9, 'Adeesha', '', 'a@gmail.com', '$2y$10$h9W3DOlttNY3ZkBbxVi8rOlXchqJOvxNJbWEsI/GaBWH1DCD0nPIi', 0, '2025-03-28 08:20:34', NULL, NULL, NULL, NULL, 'kg', 'cus_S1bMOsIVYKomLx', NULL, NULL, NULL, NULL, NULL, NULL, 9, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'pending');
INSERT INTO `users_copy1` VALUES (10, 'Jane', '', 'Jane@gmail.com', '$2y$10$fDwO4R85ORWbhkV9tEQ3Q.8EeEkCqIoUs.583402KfnsWAPvfEITO', 0, '2025-03-28 08:29:43', NULL, NULL, NULL, NULL, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'pending');
INSERT INTO `users_copy1` VALUES (11, 'Bob', '', 'bob@gmail.com', '$2y$10$v1MSBft/ccIwbKNU/TzMfuKU9taUur6OBCCBrCJkp4qleLX/FI4/q', 0, '2025-03-28 08:40:24', NULL, NULL, NULL, NULL, 'kg', 'cus_S1bgQTIe481Hpt', NULL, NULL, NULL, NULL, NULL, NULL, 11, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'pending');
INSERT INTO `users_copy1` VALUES (12, 'anna', '', 'anna@gmail.com', '$2y$10$jm.mnZIaBTdjoL6/8WjJ/.mzOmmQ/lt.bbecYIvJZY/JxZwFu0QNm', 0, '2025-03-28 08:54:25', NULL, NULL, NULL, NULL, 'kg', 'cus_S1bu8Cvoo2xKI1', NULL, NULL, NULL, NULL, NULL, NULL, 12, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'pending');
INSERT INTO `users_copy1` VALUES (13, 'james', '', 'james@gmail.com', '$2y$10$XzKE8vTHRlAttb1FtglQu.RGYo8lPCjAPojqxNOqmM1an3Ob7kN9O', 0, '2025-03-28 09:09:09', NULL, NULL, NULL, NULL, 'kg', 'cus_S1c96wneu63Es6', NULL, NULL, NULL, NULL, NULL, NULL, 13, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'pending');
INSERT INTO `users_copy1` VALUES (14, 'TJ', '', 'tj@lol.com', '$2y$10$m0pwhKU2fQGvVqyrFxXHb.C2satt5P4ZGn1lHR5RonBYX2XcbQTmC', 0, '2025-03-28 09:20:02', NULL, NULL, NULL, NULL, 'kg', 'cus_S1cJFbm4hbSqPa', NULL, NULL, NULL, NULL, NULL, NULL, 14, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'pending');
INSERT INTO `users_copy1` VALUES (15, 'Kaizer', '', 'Kaizer@gmail.com', '$2y$10$CKM7I6AmupLep0ljcwtSZuwP8tEFgcpe4Ov5mu206tdI/haY1Dr6C', 0, '2025-03-28 09:23:24', NULL, NULL, NULL, NULL, 'kg', 'cus_S1cNWX85cM96RX', NULL, NULL, NULL, NULL, NULL, NULL, 15, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'pending');
INSERT INTO `users_copy1` VALUES (16, 'David', '', 'david@gmail.com', '$2y$10$n6zG7tX06ftx8rXIDeB.BeVshG3qNtLLWRFqVvcchxwTtSqOUO0dW', 0, '2025-03-28 13:07:37', NULL, NULL, NULL, NULL, 'kg', 'cus_S1g0RU9hXkSVCj', NULL, NULL, NULL, NULL, NULL, NULL, 16, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'pending');
INSERT INTO `users_copy1` VALUES (17, 'CZYKmugGM', '', 'djipruitcz@gmail.com', '$2y$10$lTXd0M9J.rXDKxcJ2Gqhm.xbrIeeR.5sNm24uP5sRuyHwYKJlxNou', 0, '2025-04-02 17:55:05', NULL, NULL, NULL, NULL, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 17, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'pending');
INSERT INTO `users_copy1` VALUES (18, '', '', '', '$2y$10$UPhVCMkMlNEsIP2TehsPEOZWS/IpdiJYGcFEUTyiMla58m5b9Z8Uy', 0, '2025-04-02 17:55:07', NULL, NULL, NULL, NULL, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 18, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'pending');
INSERT INTO `users_copy1` VALUES (19, 'test1', '', 'test1@gmail.com', '$2y$10$A04ax9JXlX4fZcPVhYh6iOFxqxkJnWGno9/W4ftjXk27ITtV2vl1S', 2, '2025-04-03 03:35:53', NULL, NULL, NULL, NULL, 'kg', 'cus_S3mEuBkKOPITVc', NULL, NULL, NULL, NULL, NULL, NULL, 19, 0, NULL, 'COACH19125', '172.69.176.138', '172.69.176.138', '0', 'basic', 'pending');
INSERT INTO `users_copy1` VALUES (20, 'test2', '', 'test2@gmail.com', '$2y$10$gqRe1bNIjpnfcn0hgpOac...8LdG2AmITCiDPCB85t.jBPWUsB5we', 2, '2025-04-03 03:45:56', NULL, NULL, NULL, NULL, 'kg', 'cus_S3mHQCAgeQ1a5r', NULL, NULL, NULL, NULL, NULL, 'Friday', 20, 25, NULL, 'COACH20839', '104.23.175.91', '104.23.175.91', '0', 'basic', 'paid');
INSERT INTO `users_copy1` VALUES (21, 'coach', '', 'coach@gmail.com', '$2y$10$9hp3loSjNheQKmnC5KxkTuoh1lNOJV3lWPEY/cN8RIz6KqAkfN75u', 2, '2025-04-03 03:57:15', NULL, NULL, NULL, NULL, 'kg', 'cus_S3mTbwY40hBlQk', NULL, NULL, NULL, NULL, NULL, NULL, 21, 25, NULL, 'COACH21272', '162.158.88.8', '162.158.88.8', '0', 'basic', 'paid');
INSERT INTO `users_copy1` VALUES (22, 'coach2', '', 'coach2@gmail.com', '$2y$10$hVA.bfBnrhjmABKRxnGPyOBtQFqdQX5F7oQAcFrqiBf3kqO7Uu7Em', 2, '2025-04-03 04:11:50', NULL, NULL, NULL, NULL, 'kg', 'cus_S3mhLT8lN4pHRq', NULL, NULL, NULL, NULL, NULL, NULL, 22, 75, NULL, 'COACH22621', '172.71.124.32', '172.71.81.177', '0', 'pro', 'paid');
INSERT INTO `users_copy1` VALUES (23, 'coach4', '', 'coach4@gmqi.com', '$2y$10$/t/.MSenkiFVBIvImDH3DujyGuA/f69ewL0dG5ebLfzEIolLfr3wm', 2, '2025-04-03 04:16:59', NULL, NULL, NULL, NULL, 'kg', 'cus_S3mmDmtoH4qYAJ', NULL, NULL, NULL, NULL, NULL, NULL, 23, 50, NULL, 'COACH23391', '162.158.189.72', '162.158.189.72', '0', 'pro', 'paid');
INSERT INTO `users_copy1` VALUES (24, 'TJ', 'Lol', 'tj@gmail.com', '$2y$10$4Cge.wViTqrAF/WR8KqyxeP52YBaed1pvqVHkb.Jhe9yo4tV29oRC', 3, '2025-04-03 04:50:35', NULL, NULL, NULL, NULL, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 20, 0, NULL, NULL, '162.158.189.204', '162.158.189.204', 'UK', NULL, 'pending');
INSERT INTO `users_copy1` VALUES (25, 'TJ', 'thz', 'TJz@gmail.com', '$2y$10$EVfYVzcqIqU.x3HXn1uIHemI5ioHKBH9l7ZixRqVDf5tDYBCL7nw.', 3, '2025-04-03 04:55:26', NULL, NULL, NULL, NULL, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 22, 0, 22, NULL, '172.70.142.109', '172.70.142.109', 'UK', NULL, 'pending');
INSERT INTO `users_copy1` VALUES (26, 'TJZZ', 'TJZ', 'lols@gmail.com', '$2y$10$ikNfJvLeiCo3NVu3Zxa/Zeu8j.vNLVFAfVvuuBVd8QvVaUtcOWo1.', 2, '2025-04-03 05:06:32', NULL, NULL, NULL, NULL, 'kg', 'cus_S3na3XfEUTgDNv', NULL, NULL, NULL, NULL, NULL, NULL, 26, 20, NULL, 'COACH26262', '172.71.124.54', '172.71.124.54', '0', 'basic', 'paid');
INSERT INTO `users_copy1` VALUES (27, 'Adeesha', 'Perera', 'ada@gmail.com', '$2y$10$oic97BAyY5hEHR/WpA1ekuvjiNKZ6yctXTSjvV5gqT//JRs7gkdTO', 3, '2025-04-03 05:47:38', NULL, NULL, NULL, NULL, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, 'Saturday', 22, 0, 22, NULL, '172.69.166.114', '162.158.189.185', 'UK', NULL, 'pending');
INSERT INTO `users_copy1` VALUES (28, 'g', 'gg', 'g@gmail.com', '$2y$10$URHTqF.RbIPtZqN7xfk2DuhrUbqTqzgNZqbHFSEdNzDyTojMbLviq', 3, '2025-04-03 03:00:15', NULL, NULL, NULL, NULL, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 22, 0, 22, NULL, NULL, NULL, NULL, NULL, 'pending');
INSERT INTO `users_copy1` VALUES (29, 'Adeesha', 'Ranuli', 'add@gmail.com', '$2y$10$7V3Z6G5/eRlL/5Zx0ySLKeUR7HggrG2Yb3PP/4Cf8ZuVIsT9LLm9q', 3, '2025-04-03 03:18:06', NULL, NULL, NULL, NULL, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 1, NULL, NULL, '172.70.189.98', NULL, NULL, 'pending');
INSERT INTO `users_copy1` VALUES (30, 'tMvcktjx', 'sGephdTR', 'diksdownst1987@gmail.com', '$2y$10$LxMcNj5jC.6BiwSoORHHiulb86GPStD5jkHVwkZiaD1v1hIluv7.i', 2, '2025-04-05 05:04:14', NULL, NULL, NULL, NULL, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 30, NULL, NULL, NULL, '172.71.25.139', '172.71.25.139', '0', 'elite', 'pending');

-- ----------------------------
-- Table structure for weight_history
-- ----------------------------
DROP TABLE IF EXISTS `weight_history`;
CREATE TABLE `weight_history`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `weight` double NOT NULL,
  `unit` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `date_recorded` datetime NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `user_id`(`user_id` ASC) USING BTREE,
  CONSTRAINT `weight_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 22 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of weight_history
-- ----------------------------
INSERT INTO `weight_history` VALUES (1, 3, 74, 'kg', '2025-03-28 10:36:37');
INSERT INTO `weight_history` VALUES (2, 3, 80, 'kg', '2025-03-28 10:36:52');
INSERT INTO `weight_history` VALUES (3, 3, 65, 'kg', '2025-03-28 10:37:11');
INSERT INTO `weight_history` VALUES (4, 3, 70, 'kg', '2025-03-28 10:43:51');
INSERT INTO `weight_history` VALUES (5, 3, 75, 'kg', '2025-03-28 10:43:56');
INSERT INTO `weight_history` VALUES (6, 3, 74, 'kg', '2025-03-28 10:44:02');
INSERT INTO `weight_history` VALUES (7, 3, 75, 'kg', '2025-03-28 10:44:10');
INSERT INTO `weight_history` VALUES (8, 3, 75, 'kg', '2025-03-28 10:44:14');
INSERT INTO `weight_history` VALUES (9, 3, 76, 'kg', '2025-03-28 10:44:19');
INSERT INTO `weight_history` VALUES (10, 7, 55, 'kg', '2025-03-28 21:36:14');
INSERT INTO `weight_history` VALUES (11, 7, 55, 'kg', '2025-03-28 21:36:21');
INSERT INTO `weight_history` VALUES (12, 7, 55, 'kg', '2025-03-28 21:36:25');
INSERT INTO `weight_history` VALUES (13, 7, 59, 'kg', '2025-03-28 21:36:33');
INSERT INTO `weight_history` VALUES (14, 1, 80, 'kg', '2025-03-31 00:43:58');
INSERT INTO `weight_history` VALUES (15, 1, 85, 'kg', '2025-03-31 00:44:05');
INSERT INTO `weight_history` VALUES (16, 1, 82, 'kg', '2025-03-31 00:44:21');
INSERT INTO `weight_history` VALUES (17, 1, 81, 'kg', '2025-03-31 00:44:36');
INSERT INTO `weight_history` VALUES (18, 1, 84, 'kg', '2025-03-31 00:44:47');
INSERT INTO `weight_history` VALUES (19, 1, 84, 'kg', '2025-03-31 01:01:10');
INSERT INTO `weight_history` VALUES (20, 1, 84, 'kg', '2025-03-31 01:01:19');
INSERT INTO `weight_history` VALUES (21, 1, 84, 'kg', '2025-03-31 01:07:46');

-- ----------------------------
-- Table structure for weight_logs
-- ----------------------------
DROP TABLE IF EXISTS `weight_logs`;
CREATE TABLE `weight_logs`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `weight` decimal(5, 2) NOT NULL,
  `unit` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `logged_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `user_id`(`user_id` ASC) USING BTREE,
  CONSTRAINT `weight_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of weight_logs
-- ----------------------------

-- ----------------------------
-- Table structure for workout_logs
-- ----------------------------
DROP TABLE IF EXISTS `workout_logs`;
CREATE TABLE `workout_logs`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `prescribed_working_set_id` int NOT NULL,
  `user_id` int NOT NULL,
  `logged_reps` int NOT NULL,
  `logged_weight` decimal(5, 2) NOT NULL,
  `logged_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `prescribed_working_set_id`(`prescribed_working_set_id` ASC) USING BTREE,
  INDEX `user_id`(`user_id` ASC) USING BTREE,
  CONSTRAINT `workout_logs_ibfk_1` FOREIGN KEY (`prescribed_working_set_id`) REFERENCES `prescribed_working_sets` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `workout_logs_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 9 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of workout_logs
-- ----------------------------
INSERT INTO `workout_logs` VALUES (8, 57, 3, 10, 20.00, '2025-03-28 07:23:24');

SET FOREIGN_KEY_CHECKS = 1;
