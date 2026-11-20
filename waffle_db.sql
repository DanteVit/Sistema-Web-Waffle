CREATE DATABASE IF NOT EXISTS `waffle_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `waffle_db`;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE `configurations` (
  `id_config` int(11) NOT NULL AUTO_INCREMENT,
  `bg_theme` varchar(50) NOT NULL DEFAULT 'fondo-default',
  `text_color` varchar(7) NOT NULL DEFAULT '#ffcf2f',
  `font_family` varchar(50) NOT NULL DEFAULT 'font-comic',
  `bg_image` varchar(255) NOT NULL DEFAULT 'images/logo-original.png',
  PRIMARY KEY (`id_config`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `configurations` (`id_config`, `bg_theme`, `text_color`, `font_family`, `bg_image`) VALUES
(1, 'fondo-default', '#ffcf2f', 'font-comic', 'images/logo-original.png'),
(2, 'fondo-default', '#ffcf2f', 'font-comic', 'images/logo-original.png'),
(3, 'fondo-default', '#ffcf2f', 'font-comic', 'images/logo-original.png');

CREATE TABLE `users` (
  `id_user` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('user','moderator','admin') NOT NULL DEFAULT 'user',
  `points` int(11) NOT NULL DEFAULT 500,
  `avatar` varchar(255) NOT NULL DEFAULT 'images/guest.png',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `id_configuracion` int(11) NOT NULL,
  PRIMARY KEY (`id_user`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `fk_config` (`id_configuracion`),
  CONSTRAINT `fk_config` FOREIGN KEY (`id_configuracion`) REFERENCES `configurations` (`id_config`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` (`id_user`, `username`, `email`, `password_hash`, `role`, `points`, `avatar`, `created_at`, `id_configuracion`) VALUES
(1, 'Test1', 'test@gmail.com', '8a863b145dc6e4ed7ac41c08f7536c476ebac7509e028ed2b49f8bd5a3562b9f', 'user', 500, 'images/guest.png', '2026-11-09 23:26:18', 1),
(2, 'admin', 'admin123@gmail.com', '240be518fabd2724ddb6f04eeb1da5967448d7e831c08c8fa822809f74c720a9', 'admin', 9999, 'images/guest.png', '2026-11-14 16:00:00', 2),
(3, 'moderador', 'mod@gmail.com', '09fbcc458fb3430db7ec54ee95635e7fbc06ccfd982b49c55ec53ab8ac2397e7', 'moderator', 1000, 'images/guest.png', '2026-11-16 20:00:00', 3);

CREATE TABLE `user_items` (
  `id_user_item` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL,
  `item_id` varchar(50) NOT NULL,
  `purchased_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_user_item`),
  UNIQUE KEY `unique_user_item` (`id_user`, `item_id`),
  CONSTRAINT `fk_user_items_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `sesiones` (
  `id_sesiones` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `Token` varchar(64) NOT NULL,
  PRIMARY KEY (`id_sesiones`),
  KEY `fk_user_sesions` (`user_id`),
  CONSTRAINT `fk_user_sesions` FOREIGN KEY (`user_id`) REFERENCES `users` (`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `games` (
  `id_game` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  PRIMARY KEY (`id_game`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `genres` (
  `id_genre` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id_genre`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `genres` (`id_genre`, `name`) VALUES
(1, 'accion'),
(2, 'aventura'),
(3, 'estrategia'),
(4, 'plataformas'),
(5, 'clasicos');

CREATE TABLE `games_genres` (
  `id_games_genre` int(11) NOT NULL AUTO_INCREMENT,
  `id_id_games` int(11) NOT NULL,
  `id_genre` int(11) NOT NULL,
  PRIMARY KEY (`id_games_genre`),
  KEY `fk_id_games` (`id_id_games`),
  KEY `fk_genre` (`id_genre`),
  CONSTRAINT `fk_genre` FOREIGN KEY (`id_genre`) REFERENCES `genres` (`id_genre`),
  CONSTRAINT `fk_id_games` FOREIGN KEY (`id_id_games`) REFERENCES `games` (`id_game`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `library` (
  `id_library` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL,
  `id_games` int(11) NOT NULL,
  PRIMARY KEY (`id_library`),
  KEY `fk_id_user` (`id_user`),
  KEY `fk_id_games_library` (`id_games`),
  CONSTRAINT `fk_id_games_library` FOREIGN KEY (`id_games`) REFERENCES `games` (`id_game`) ON DELETE CASCADE,
  CONSTRAINT `fk_id_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `publications` (
  `id_publication` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_publication`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `users_publications` (
  `id_users_publications` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL,
  `id_publications` int(11) NOT NULL,
  PRIMARY KEY (`id_users_publications`),
  KEY `fk_id_publications` (`id_publications`),
  KEY `fk_id_users_publications` (`id_user`),
  CONSTRAINT `fk_id_publications` FOREIGN KEY (`id_publications`) REFERENCES `publications` (`id_publication`) ON DELETE CASCADE,
  CONSTRAINT `fk_id_users_publications` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `ratings` (
  `id_rating` int(11) NOT NULL AUTO_INCREMENT,
  `stars` int(11) NOT NULL CHECK (`stars` >= 1 and `stars` <= 5),
  `rated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_rating`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `ratings_publications` (
  `id_ratings_publications` int(11) NOT NULL AUTO_INCREMENT,
  `id_rating` int(11) NOT NULL,
  `id_publications` int(11) NOT NULL,
  PRIMARY KEY (`id_ratings_publications`),
  KEY `fk_id_rating` (`id_rating`),
  KEY `fk_id_publication_ratings` (`id_publications`),
  CONSTRAINT `fk_id_publication_ratings` FOREIGN KEY (`id_publications`) REFERENCES `publications` (`id_publication`) ON DELETE CASCADE,
  CONSTRAINT `fk_id_rating` FOREIGN KEY (`id_rating`) REFERENCES `ratings` (`id_rating`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `users_ratings` (
  `id_users_ratings` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL,
  `id_rating` int(11) NOT NULL,
  PRIMARY KEY (`id_users_ratings`),
  KEY `fk_id_user_rating` (`id_user`),
  KEY `fk_id_rating_user` (`id_rating`),
  CONSTRAINT `fk_id_rating_user` FOREIGN KEY (`id_rating`) REFERENCES `ratings` (`id_rating`) ON DELETE CASCADE,
  CONSTRAINT `fk_id_user_rating` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;