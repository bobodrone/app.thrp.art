-- Adminer 5.4.1 MySQL 8.0.44 dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

SET NAMES utf8mb4;

DROP TABLE IF EXISTS `cache`;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `cache` (`key`, `value`, `expiration`) VALUES
('the-human-response-project-cache-livewire-checksum-failures:::1',	'i:1;',	1784239276),
('the-human-response-project-cache-livewire-checksum-failures:::1:timer',	'i:1784239276;',	1784239276);

DROP TABLE IF EXISTS `cache_locks`;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `creator_applications`;
CREATE TABLE `creator_applications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','approved','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `applied_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `creator_applications_email_index` (`email`),
  KEY `creator_applications_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `creator_applications` (`id`, `email`, `name`, `message`, `status`, `applied_at`, `reviewed_at`) VALUES
(1,	'applicant@example.com',	'Annie Applicant',	'I love answering questions about tea and philosophy and would like to help out on the platform.',	'pending',	'2026-07-14 20:17:11',	NULL),
(2,	'applicant2@example.com',	'Alex Applicant',	'Another pending application — I have experience running Q&A communities and would like to contribute.',	'pending',	'2026-07-15 20:17:11',	NULL),
(3,	'approved@example.com',	'April Applicant',	'I would love to join as a creator to share my knowledge of gardening.',	'approved',	'2026-07-06 20:17:11',	'2026-07-08 20:17:11');

DROP TABLE IF EXISTS `failed_jobs`;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `job_batches`;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `jobs`;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `migrations`;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1,	'0001_01_01_000000_create_users_table',	1),
(2,	'0001_01_01_000001_create_cache_table',	1),
(3,	'0001_01_01_000002_create_jobs_table',	1),
(4,	'2026_07_16_000001_add_role_to_users_table',	1),
(5,	'2026_07_16_000002_create_questions_table',	1),
(6,	'2026_07_16_000003_create_creator_applications_table',	1),
(7,	'2026_07_16_000004_create_pending_email_changes_table',	1);

DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('member','creator','admin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'member',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_role_index` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `remember_token`, `role`, `created_at`, `updated_at`) VALUES
(1,	'Ada Admin',	'admin@example.com',	'2026-07-16 20:17:11',	'$2y$12$yfEoNU0JKVxrf/681/Kjd.8UJBFYL0i85FkF31lw8IpMZNCirutV2',	'Yd3g7sY500',	'admin',	'2026-07-16 20:17:11',	'2026-07-16 20:17:11'),
(2,	'Carl Creator',	'creator@example.com',	'2026-07-16 20:17:11',	'$2y$12$yfEoNU0JKVxrf/681/Kjd.8UJBFYL0i85FkF31lw8IpMZNCirutV2',	'yadVFBCx1L',	'creator',	'2026-07-16 20:17:11',	'2026-07-16 20:17:11'),
(3,	'Clea Creator',	'creator2@example.com',	'2026-07-16 20:17:11',	'$2y$12$yfEoNU0JKVxrf/681/Kjd.8UJBFYL0i85FkF31lw8IpMZNCirutV2',	'qizXXEOpgu',	'creator',	'2026-07-16 20:17:11',	'2026-07-16 20:17:11'),
(4,	'Mia Member',	'member@example.com',	'2026-07-16 20:17:11',	'$2y$12$yfEoNU0JKVxrf/681/Kjd.8UJBFYL0i85FkF31lw8IpMZNCirutV2',	'gLkNUSfxx6',	'member',	'2026-07-16 20:17:11',	'2026-07-16 20:17:11'),
(5,	'Mike Member',	'mike@example.com',	'2026-07-16 20:17:11',	'$2y$12$yfEoNU0JKVxrf/681/Kjd.8UJBFYL0i85FkF31lw8IpMZNCirutV2',	'ah1XpiPN3b',	'member',	'2026-07-16 20:17:11',	'2026-07-16 20:17:11'),
(6,	'Mel Member',	'mel@example.com',	'2026-07-16 20:17:11',	'$2y$12$yfEoNU0JKVxrf/681/Kjd.8UJBFYL0i85FkF31lw8IpMZNCirutV2',	'wE7m8CujKk',	'member',	'2026-07-16 20:17:11',	'2026-07-16 20:17:11'),
(7,	'Tania Spinka',	'maybelle.roberts@example.com',	'2026-07-16 20:17:11',	'$2y$12$yfEoNU0JKVxrf/681/Kjd.8UJBFYL0i85FkF31lw8IpMZNCirutV2',	'bnwuaKgq4v',	'member',	'2026-07-16 20:17:11',	'2026-07-16 20:17:11'),
(8,	'Buddy Boehm',	'merl.tromp@example.net',	'2026-07-16 20:17:11',	'$2y$12$yfEoNU0JKVxrf/681/Kjd.8UJBFYL0i85FkF31lw8IpMZNCirutV2',	'bvFR2tsBES',	'member',	'2026-07-16 20:17:11',	'2026-07-16 20:17:11'),
(9,	'Ms. Vita Langosh DVM',	'hirthe.jesus@example.net',	'2026-07-16 20:17:11',	'$2y$12$yfEoNU0JKVxrf/681/Kjd.8UJBFYL0i85FkF31lw8IpMZNCirutV2',	'bSuqKaQCBK',	'member',	'2026-07-16 20:17:11',	'2026-07-16 20:17:11'),
(10,	'Prof. Jena Gutmann I',	'cletus27@example.org',	'2026-07-16 20:17:11',	'$2y$12$yfEoNU0JKVxrf/681/Kjd.8UJBFYL0i85FkF31lw8IpMZNCirutV2',	'vVn4RUtHEl',	'member',	'2026-07-16 20:17:11',	'2026-07-16 20:17:11'),
(11,	'Lillie Abshire',	'xryan@example.net',	'2026-07-16 20:17:11',	'$2y$12$yfEoNU0JKVxrf/681/Kjd.8UJBFYL0i85FkF31lw8IpMZNCirutV2',	'FNY07KjcP3',	'member',	'2026-07-16 20:17:11',	'2026-07-16 20:17:11'),
(12,	'Sydney Simonis',	'daniela.rohan@example.org',	'2026-07-16 20:17:11',	'$2y$12$yfEoNU0JKVxrf/681/Kjd.8UJBFYL0i85FkF31lw8IpMZNCirutV2',	'z6Yt1h7k4G',	'member',	'2026-07-16 20:17:11',	'2026-07-16 20:17:11'),
(14,	'Dr. Stevie Smitham Jr.',	'edwin.stiedemann@example.com',	'2026-07-16 20:45:11',	'$2y$12$Dtgoz.2rTE8B28.jBuG4E.88QBmugKrurm4SqxmUEZTAY0tFctto6',	'fgrRCd8QG8',	'creator',	'2026-07-16 20:45:11',	'2026-07-16 20:45:11'),
(15,	'Louie Howe',	'ronny61@example.com',	'2026-07-16 20:45:11',	'$2y$12$Dtgoz.2rTE8B28.jBuG4E.88QBmugKrurm4SqxmUEZTAY0tFctto6',	'nXjKyaTfqF',	'member',	'2026-07-16 20:45:11',	'2026-07-16 20:45:11');

DROP TABLE IF EXISTS `pending_email_changes`;
CREATE TABLE `pending_email_changes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `new_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pending_email_changes_token_unique` (`token`),
  KEY `pending_email_changes_user_id_index` (`user_id`),
  CONSTRAINT `pending_email_changes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `questions`;
CREATE TABLE `questions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `content` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('asked','claimed','answered') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'asked',
  `asked_by` bigint unsigned NOT NULL,
  `claimed_by` bigint unsigned DEFAULT NULL,
  `answered_by` bigint unsigned DEFAULT NULL,
  `answer` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `claimed_at` timestamp NULL DEFAULT NULL,
  `answered_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `questions_asked_by_foreign` (`asked_by`),
  KEY `questions_status_index` (`status`),
  KEY `questions_claimed_by_index` (`claimed_by`),
  KEY `questions_answered_by_index` (`answered_by`),
  CONSTRAINT `questions_answered_by_foreign` FOREIGN KEY (`answered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `questions_asked_by_foreign` FOREIGN KEY (`asked_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `questions_claimed_by_foreign` FOREIGN KEY (`claimed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `questions` (`id`, `content`, `status`, `asked_by`, `claimed_by`, `answered_by`, `answer`, `created_at`, `updated_at`, `claimed_at`, `answered_at`) VALUES
(1,	'Mock Turtle; \'but it doesn\'t matter much,\' thought Alice, \'they\'re sure to make out that she wanted much to know, but.',	'answered',	4,	1,	1,	'# sadfas asd fasd fasdf asdf \n\n## asdfadsfs\n\n*adsfdsa* adsf **asdfasdf**:\n\n- alfa\n- beta\n- gamma',	'2026-07-16 20:17:11',	'2026-07-16 21:57:24',	'2026-07-16 21:56:59',	'2026-07-16 21:57:24'),
(2,	'So Bill\'s got to do,\' said Alice in a tone of delight, and rushed at the righthand bit again, and all would change.',	'answered',	4,	1,	1,	'adsfasdfasdfasdf asdfdsa',	'2026-07-16 20:17:11',	'2026-07-16 21:56:20',	'2026-07-16 21:56:15',	'2026-07-16 21:56:20'),
(3,	'Hatter, and, just as well as if he doesn\'t begin.\' But she did it at last, and managed to put down the middle.',	'asked',	4,	NULL,	NULL,	NULL,	'2026-07-16 20:17:11',	'2026-07-16 21:31:18',	NULL,	NULL),
(4,	'TO LEAVE THE COURT.\' Everybody looked at each other for some way, and the Mock Turtle. \'She can\'t explain MYSELF, I\'m.',	'asked',	4,	NULL,	NULL,	NULL,	'2026-07-16 20:17:11',	'2026-07-16 21:31:18',	NULL,	NULL),
(5,	'I needn\'t be so proud as all that.\' \'Well, it\'s got no business of MINE.\' The Queen had only one way of expressing.',	'asked',	7,	NULL,	NULL,	NULL,	'2026-07-16 20:17:11',	'2026-07-16 21:31:18',	NULL,	NULL),
(6,	'Alice thought), and it put more simply--\"Never imagine yourself not to be an old conger-eel, that used to do:-- \'How.',	'asked',	1,	NULL,	NULL,	NULL,	'2026-07-16 20:17:11',	'2026-07-16 21:31:18',	NULL,	NULL),
(7,	'White Rabbit blew three blasts on the OUTSIDE.\' He unfolded the paper as he shook his head sadly. \'Do I look like one.',	'asked',	8,	NULL,	NULL,	NULL,	'2026-07-16 20:17:11',	'2026-07-16 21:31:18',	NULL,	NULL),
(8,	'And I declare it\'s too bad, that it is!\' As she said aloud. \'I must be off, and that if you hold it too long; and that.',	'asked',	9,	NULL,	NULL,	NULL,	'2026-07-16 20:17:11',	'2026-07-16 21:31:18',	NULL,	NULL),
(9,	'Alice began, in a languid, sleepy voice. \'Who are YOU?\' Which brought them back again to the end of the thing Mock.',	'answered',	10,	1,	1,	'asdfdsaadsfsadfasdfasd',	'2026-07-16 20:17:11',	'2026-07-16 21:56:10',	'2026-07-16 21:40:34',	'2026-07-16 21:56:10'),
(10,	'I know?\' said Alice, \'I\'ve often seen them at dinn--\' she checked herself hastily. \'I thought you did,\' said the.',	'asked',	11,	NULL,	NULL,	NULL,	'2026-07-16 20:17:11',	'2026-07-16 21:31:18',	NULL,	NULL),
(11,	'I think you\'d better leave off,\' said the March Hare, who had meanwhile been examining the roses. \'Off with her arms.',	'asked',	12,	NULL,	NULL,	NULL,	'2026-07-16 20:17:11',	'2026-07-16 21:31:18',	NULL,	NULL),
(12,	'What is love?',	'asked',	1,	NULL,	NULL,	NULL,	'2026-07-16 20:36:34',	'2026-07-16 21:52:13',	NULL,	NULL),
(13,	'Lizard, who seemed to be afraid of them!\' \'And who is Dinah, if I might venture to go and live in that soup!\' Alice.',	'asked',	15,	NULL,	NULL,	NULL,	'2026-07-16 20:45:11',	'2026-07-16 21:31:18',	NULL,	NULL),
(14,	'What time is love?',	'answered',	1,	1,	1,	'# Time is\n\n*adsf* adsf **asdf**\n\n- alfa\n- beta\n',	'2026-07-17 05:40:51',	'2026-07-17 05:41:24',	'2026-07-17 05:41:00',	'2026-07-17 05:41:24'),
(15,	'Vad är klockan?',	'answered',	1,	1,	1,	'# klockan är\n\n*vad* du **själv** vill att den ska vara?\n\n- sen\n- tidig',	'2026-07-17 07:10:11',	'2026-07-17 07:10:46',	'2026-07-17 07:10:19',	'2026-07-17 07:10:46');

DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('5sTnYLcz0TP7VIsAScEi8P7TxgOitSCm6L4lQOyz',	NULL,	'::1',	'curl/8.5.0',	'eyJfdG9rZW4iOiJGMWlpSm9UVmpGRDgyTFZUWEw2NlIwWGc5REhTTklzWnVUOTBUM1MzIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cL2xvY2FsaG9zdDo4MDAwXC9xdWVzdGlvbnNcLzEiLCJyb3V0ZSI6InF1ZXN0aW9ucy5zaG93In0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfX0=',	1784267616),
('7ppYVOYKlZIbxM09lVyE2icGz2Vgerh1tdI9S6jE',	NULL,	'::1',	'curl/8.5.0',	'eyJfdG9rZW4iOiJlbVRSQkJwWTBQRmpJYVg1cTRheEg3cVRha0xJMVluSjd3b2lnYjcxIiwidXJsIjp7ImludGVuZGVkIjoiaHR0cDpcL1wvbG9jYWxob3N0OjgwMDBcL215LXF1ZXN0aW9ucyJ9LCJfcHJldmlvdXMiOnsidXJsIjoiaHR0cDpcL1wvbG9jYWxob3N0OjgwMDBcL215LXF1ZXN0aW9ucyIsInJvdXRlIjoibXktcXVlc3Rpb25zIn0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfX0=',	1784267558),
('9VeAtiaPAANATdZp6O04o1sN74uksEf6cYknq72F',	4,	'::1',	'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/149.0.7827.55 Safari/537.36',	'eyJfdG9rZW4iOiJlVGRpakpvYVdZRnlaUGcwWXhxdGIwaHRnMHpoUXd3RFZGOWFkanp5IiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cL2xvY2FsaG9zdDo4MDAwIiwicm91dGUiOiJob21lIn0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfSwibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiOjR9',	1784267966),
('e1sCo0k3fgW17AsNnrW8blGjod1wopMRmTj2635z',	NULL,	'::1',	'curl/8.5.0',	'eyJfdG9rZW4iOiJrUGlTVEtLcU1NYVdlT2tONkF3ZTZDTGlsSVJWYUpST3M0OWJ1VkZWIiwidXJsIjp7ImludGVuZGVkIjoiaHR0cDpcL1wvbG9jYWxob3N0OjgwMDBcL215LXF1ZXN0aW9ucyJ9LCJfcHJldmlvdXMiOnsidXJsIjoiaHR0cDpcL1wvbG9jYWxob3N0OjgwMDBcL215LXF1ZXN0aW9ucyIsInJvdXRlIjoibXktcXVlc3Rpb25zIn0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfX0=',	1784267558),
('G8orpmnujMQ7TYetM0XaqW8c82vFSuEDg0VUp6Fs',	4,	'::1',	'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/149.0.7827.55 Safari/537.36',	'eyJfdG9rZW4iOiJub21IRWVGckZkWGVsRGQ4dzE0RUFmV2tiaFJmVXZndE5wSmtOMEJwIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cL2xvY2FsaG9zdDo4MDAwIiwicm91dGUiOiJob21lIn0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfSwibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiOjR9',	1784267907),
('LDqgK3LPNUtHCiEXnrtHvu6mnAfDoDSfLTne6FAe',	1,	'127.0.0.1',	'Mozilla/5.0 (X11; Linux x86_64; rv:152.0) Gecko/20100101 Firefox/152.0',	'eyJfdG9rZW4iOiJ5U1FFZHJZb3ExTnBoOUtRbGpvS0I5N1JZTjI0RGtSdzBLQnNtOGNkIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cL2xvY2FsaG9zdDo4MDAwIiwicm91dGUiOiJob21lIn0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfSwibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiOjF9',	1784272272),
('ugMNZXLAWx6Ba81oKkPmg3cFiOQdA0NDs2RVb7Tu',	NULL,	'::1',	'curl/8.5.0',	'eyJfdG9rZW4iOiJvNnNiRDlzWmFOYjhVTmFES0J0Z3o1TElDMnZCWnBKalQxOWxKbDFIIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cL2xvY2FsaG9zdDo4MDAwIiwicm91dGUiOiJob21lIn0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfX0=',	1784267572),
('v24pQv2FwHyoqYGG6W8DMJ70iiXwPBrNbvOGG742',	4,	'::1',	'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/149.0.7827.55 Safari/537.36',	'eyJfdG9rZW4iOiJtYkpYVXhFbXJwdnQ0Unp1T1FlTlZHYkxaNkszQnBzV2FwT3h4aVBwIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cL2xvY2FsaG9zdDo4MDAwXC9zZXR0aW5ncyIsInJvdXRlIjoic2V0dGluZ3MifSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119LCJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI6NH0=',	1784267985);

-- 2026-07-22 09:00:03 UTC
