-- TripSync Database Schema
-- Created for Week 1 of the Development Plan

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('customer','partner','admin') NOT NULL DEFAULT 'customer',
  `status` enum('active','inactive','pending') NOT NULL DEFAULT 'pending',
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT 'default-avatar.jpg',
  `nic_number` varchar(20) DEFAULT NULL,
  `license_number` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `wallets`
--

CREATE TABLE `wallets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `balance` decimal(15,2) DEFAULT 0.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `wallets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `travel_plans`
--

CREATE TABLE `travel_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `travelers` int(11) DEFAULT 1,
  `status` enum('planning','confirmed','completed','cancelled') DEFAULT 'planning',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `travel_plans_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `destinations`
--

CREATE TABLE `destinations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `plan_id` int(11) NOT NULL,
  `day_number` int(11) NOT NULL,
  `location_name` varchar(255) NOT NULL,
  `notes` text DEFAULT NULL,
  `arrival_time` time DEFAULT NULL,
  `departure_time` time DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `plan_id` (`plan_id`),
  CONSTRAINT `destinations_ibfk_1` FOREIGN KEY (`plan_id`) REFERENCES `travel_plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `hotels`
--

CREATE TABLE `hotels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `location` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price_per_night` decimal(10,2) NOT NULL,
  `stars` decimal(2,1) DEFAULT 0.0,
  `image_path` varchar(255) DEFAULT NULL,
  `amenities` text DEFAULT NULL, -- Comma separated or JSON string
  `category` enum('Luxury','Resort','Boutique','Economy') DEFAULT 'Economy',
  `status` enum('available','booked','maintenance') DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `owner_id` int(11) DEFAULT NULL, -- Partner's user_id
  `type` enum('sedan','suv','van','tuk-tuk') NOT NULL,
  `model` varchar(100) NOT NULL,
  `year` int(4) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `reg_number` varchar(50) NOT NULL,
  `capacity` int(11) NOT NULL,
  `price_per_day` decimal(10,2) NOT NULL,
  `status` enum('available','booked','maintenance') DEFAULT 'available',
  `image_path` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `features` text DEFAULT NULL, -- Comma separated features
  `rating` decimal(2,1) DEFAULT 0.0,
  `reviews_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `reg_number` (`reg_number`),
  KEY `owner_id` (`owner_id`),
  CONSTRAINT `vehicles_ibfk_1` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `plan_id` int(11) DEFAULT NULL,
  `type` enum('hotel','vehicle','tour') NOT NULL,
  `item_id` varchar(100) DEFAULT NULL, -- Reference to internal table or external API ID
  `reference_no` varchar(20) NOT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime DEFAULT NULL,
  `pickup_location` varchar(255) DEFAULT NULL,
  `dropoff_location` varchar(255) DEFAULT NULL,
  `with_driver` tinyint(1) DEFAULT 0,
  `assigned_partner_id` int(11) DEFAULT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `status` enum('pending','confirmed','completed','cancelled','refunded') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference_no` (`reference_no`),
  KEY `user_id` (`user_id`),
  KEY `plan_id` (`plan_id`),
  KEY `assigned_partner_id` (`assigned_partner_id`),
  CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`plan_id`) REFERENCES `travel_plans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bookings_ibfk_3` FOREIGN KEY (`assigned_partner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `type` enum('credit','debit') NOT NULL,
  `method` enum('payhere','bank_transfer','cash') NOT NULL,
  `status` enum('pending','completed','failed','refunded') DEFAULT 'pending',
  `transaction_ref` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `booking_id` (`booking_id`),
  CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `support_tickets`
--

CREATE TABLE `support_tickets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `priority` enum('low','medium','high','emergency') DEFAULT 'medium',
  `status` enum('open','in_progress','resolved','closed') DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `support_tickets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `target_id` int(11) DEFAULT NULL, -- Partner ID if reviewing a driver
  `rating` int(1) NOT NULL CHECK (`rating` >= 1 AND `rating` <= 5),
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `booking_id` (`booking_id`),
  KEY `reviewer_id` (`reviewer_id`),
  KEY `target_id` (`target_id`),
  CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reviews_ibfk_3` FOREIGN KEY (`target_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `trip_tracking`
--

CREATE TABLE `trip_tracking` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `booking_id` (`booking_id`),
  CONSTRAINT `trip_tracking_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Insert Sample Data
--

INSERT INTO `users` (`name`, `email`, `password`, `role`, `status`) VALUES
('Admin', 'admin@tripsync.lk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active'), -- Password: password
('John Doe', 'john@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', 'active'),
('Driver Kamal', 'kamal@driver.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'partner', 'active');

INSERT INTO `hotels` (`name`, `location`, `description`, `price_per_night`, `stars`, `image_path`, `amenities`, `category`) VALUES
('Cinnamon Grand Colombo', 'Colombo', 'A premier five-star hotel in the heart of Colombo, offering luxurious rooms and world-class dining.', 25000.00, 4.8, 'cinnamon_grand.jpg', 'Free WiFi, Pool, Restaurant, Spa, Gym', 'Luxury'),
('Jetwing Lighthouse Royale', 'Galle', 'Overlooking the Indian Ocean, this iconic hotel designed by Geoffrey Bawa offers a blend of local heritage and modern luxury.', 32000.00, 4.7, 'jetwing_lighthouse.jpg', 'Beachfront, Pool, Restaurant, Bar, Free WiFi', 'Resort'),
('Heritance Kandalama', 'Dambulla', 'An architectural masterpiece embedded in a cliff, surrounded by lush forest and overlooking the Kandalama Tank.', 28000.00, 4.9, 'heritance_kandalama.jpg', 'Eco-friendly, Pool, Restaurant, Spa, Forest View', 'Luxury'),
('The Grand Hotel', 'Nuwara Eliya', 'Step back in time to the British colonial era at this prestigious hotel in the misty hills of Nuwara Eliya.', 18000.00, 4.5, 'grand_hotel.jpg', 'Garden, High Tea, Restaurant, BilliardsRoom, Free WiFi', 'Boutique');

INSERT INTO `vehicles` (`owner_id`, `type`, `model`, `year`, `color`, `reg_number`, `capacity`, `price_per_day`, `image_path`, `description`, `features`, `rating`, `reviews_count`) VALUES
(3, 'van', 'Toyota KDH Super Long', 2018, 'White', 'WP CAP-1234', 12, 8500.00, 'vehicle_kdh_van.jpg', 'Spacious and comfortable van ideal for large groups and family trips.', 'AC, GPS, Driver Included, Leather Seats', 4.7, 156),
(3, 'sedan', 'Toyota Prius Hybrid', 2020, 'Silver', 'WP CAD-5678', 4, 5500.00, 'vehicle_prius.jpg', 'Efficient hybrid sedan perfect for city travel and small families.', 'AC, GPS, Automatic, Hybrid', 4.5, 203),
(3, 'suv', 'Toyota Land Cruiser V8', 2021, 'Black', 'WP CBM-9012', 7, 15000.00, 'vehicle_land_cruiser.jpg', 'Luxury SUV providing ultimate comfort and off-road capability.', 'AC, 4WD, Sunroof, Leather Seats', 4.8, 89),
(3, 'sedan', 'Suzuki Alto', 2019, 'Red', 'WP CAQ-3456', 4, 3500.00, 'vehicle_alto.jpg', 'Economical and easy to drive, perfect for budget-conscious travelers.', 'AC, Manual, Insurance', 4.2, 98),
(3, 'van', 'Nissan Caravan NV350', 2017, 'Blue', 'WP CAS-7890', 12, 10000.00, 'vehicle_nissan_caravan.jpg', 'Reliable van with ample space for passengers and luggage.', 'AC, GPS, Driver Included, High Roof', 4.4, 112),
(3, 'sedan', 'Toyota Axio', 2018, 'Pearl White', 'WP CAR-4567', 4, 4500.00, 'vehicle_axio.jpg', 'Comfortable and reliable sedan for all types of travel.', 'AC, GPS, Automatic, Insurance', 4.3, 145);

COMMIT;
