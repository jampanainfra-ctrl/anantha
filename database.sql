CREATE DATABASE IF NOT EXISTS anantha CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE anantha;

CREATE TABLE IF NOT EXISTS admin_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS event_bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_reference VARCHAR(80) DEFAULT NULL,
  referred_by_reference VARCHAR(80) DEFAULT NULL,
  client_name VARCHAR(160) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  whatsapp_number VARCHAR(30) DEFAULT NULL,
  email VARCHAR(160) DEFAULT NULL,
  date_of_birth DATE DEFAULT NULL,
  occupation VARCHAR(160) DEFAULT NULL,
  pin_code VARCHAR(12) DEFAULT NULL,
  area_from VARCHAR(180) DEFAULT NULL,
  city_district VARCHAR(160) DEFAULT NULL,
  client_state VARCHAR(160) DEFAULT NULL,
  aadhaar_number VARCHAR(20) DEFAULT NULL,
  aadhaar_image VARCHAR(255) DEFAULT NULL,
  event_date DATE NOT NULL,
  event_type VARCHAR(120) DEFAULT NULL,
  guests INT DEFAULT NULL,
  package_id INT DEFAULT NULL,
  note TEXT DEFAULT NULL,
  accepted_terms_snapshot TEXT DEFAULT NULL,
  booking_status ENUM('pending','proposal_sent','confirmed','event_happening','event_over','cancelled') NOT NULL DEFAULT 'pending',
  payment_status ENUM('pending','advance_paid','partial_paid','paid') NOT NULL DEFAULT 'pending',
  proposal_amount DECIMAL(12,2) DEFAULT NULL,
  proposal_note TEXT DEFAULT NULL,
  referral_eligible TINYINT(1) NOT NULL DEFAULT 0,
  referral_referrer_bonus DECIMAL(12,2) NOT NULL DEFAULT 0,
  referral_referred_bonus DECIMAL(12,2) NOT NULL DEFAULT 0,
  referral_note TEXT DEFAULT NULL,
  created_by ENUM('public','admin') NOT NULL DEFAULT 'public',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY idx_booking_reference (booking_reference),
  INDEX idx_event_date (event_date),
  INDEX idx_booking_status (booking_status)
);

CREATE TABLE IF NOT EXISTS event_packages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  package_name VARCHAR(160) NOT NULL,
  guest_range VARCHAR(120) NOT NULL,
  price DECIMAL(12,2) DEFAULT NULL,
  discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
  discount_reason VARCHAR(255) DEFAULT NULL,
  display_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  payment_mode ENUM('cash','upi','bank_transfer','card','other') NOT NULL DEFAULT 'cash',
  transaction_id VARCHAR(180) DEFAULT NULL,
  paid_on DATE NOT NULL,
  note TEXT DEFAULT NULL,
  verification_status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  verified_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_payments_booking FOREIGN KEY (booking_id) REFERENCES event_bookings(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS event_inspections (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT NOT NULL UNIQUE,
  lights_ok TINYINT(1) NOT NULL DEFAULT 0,
  rooms_ok TINYINT(1) NOT NULL DEFAULT 0,
  lawn_ok TINYINT(1) NOT NULL DEFAULT 0,
  hall_ok TINYINT(1) NOT NULL DEFAULT 0,
  washrooms_ok TINYINT(1) NOT NULL DEFAULT 0,
  cleanup_ok TINYINT(1) NOT NULL DEFAULT 0,
  inspection_note TEXT DEFAULT NULL,
  inspected_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_event_inspections_booking FOREIGN KEY (booking_id) REFERENCES event_bookings(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS event_penalties (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT NOT NULL,
  penalty_title VARCHAR(160) NOT NULL,
  penalty_reason TEXT NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_event_penalties_booking FOREIGN KEY (booking_id) REFERENCES event_bookings(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS rate_card_settings (
  setting_key VARCHAR(80) PRIMARY KEY,
  label VARCHAR(160) NOT NULL,
  setting_value DECIMAL(12,2) NOT NULL DEFAULT 0,
  suffix VARCHAR(80) DEFAULT NULL,
  display_order INT NOT NULL DEFAULT 0,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS site_terms (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  term_text TEXT NOT NULL,
  display_order INT NOT NULL DEFAULT 0,
  is_booking_required TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_name VARCHAR(160) NOT NULL,
  event_type VARCHAR(120) DEFAULT NULL,
  rating TINYINT NOT NULL DEFAULT 5,
  review_text TEXT NOT NULL,
  status ENUM('pending','published','hidden') NOT NULL DEFAULT 'pending',
  created_by ENUM('public','admin') NOT NULL DEFAULT 'public',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS gallery_images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  category VARCHAR(120) NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  display_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS gallery_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_name VARCHAR(120) NOT NULL UNIQUE,
  display_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS blog_images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  page_slug VARCHAR(160) NOT NULL,
  title VARCHAR(180) NOT NULL,
  alt_text VARCHAR(255) NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  display_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_blog_images_page (page_slug, is_active, display_order)
);

INSERT INTO admin_users (name, email, password_hash)
SELECT 'Admin', 'admin@anantha.local', '$2y$10$2o8CePAR08n0QUoSR/zWUe31LDvdAy0icSwmZL5JCH1mUvD5HB7tO'
WHERE NOT EXISTS (SELECT 1 FROM admin_users WHERE email = 'admin@anantha.local');

INSERT INTO gallery_images (title, category, image_path, display_order, is_active)
SELECT 'Evening celebration', 'Events', 'assets/gallery-celebration-evening.png', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM gallery_images);

INSERT INTO gallery_images (title, category, image_path, display_order, is_active)
SELECT 'Day lawn', 'Venue', 'assets/gallery-day-lawn.png', 2, 1
WHERE NOT EXISTS (SELECT 1 FROM gallery_images WHERE title = 'Day lawn');

INSERT INTO gallery_images (title, category, image_path, display_order, is_active)
SELECT 'Celebration room', 'Rooms', 'assets/room-birthday-celebration.png', 3, 1
WHERE NOT EXISTS (SELECT 1 FROM gallery_images WHERE title = 'Celebration room');

INSERT INTO gallery_categories (category_name, display_order, is_active)
SELECT 'Events', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM gallery_categories WHERE category_name = 'Events');

INSERT INTO gallery_categories (category_name, display_order, is_active)
SELECT 'Venue', 2, 1
WHERE NOT EXISTS (SELECT 1 FROM gallery_categories WHERE category_name = 'Venue');

INSERT INTO gallery_categories (category_name, display_order, is_active)
SELECT 'Rooms', 3, 1
WHERE NOT EXISTS (SELECT 1 FROM gallery_categories WHERE category_name = 'Rooms');

INSERT INTO blog_images (page_slug, title, image_path, alt_text, display_order, is_active)
SELECT 'reception-venue-visakhapatnam', 'Reception lawn setup', 'assets/occasion-reception.png', 'Outdoor wedding reception setup on a private event lawn', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM blog_images WHERE page_slug = 'reception-venue-visakhapatnam' AND image_path = 'assets/occasion-reception.png');

INSERT INTO blog_images (page_slug, title, image_path, alt_text, display_order, is_active)
SELECT 'reception-venue-visakhapatnam', 'Evening reception celebration', 'assets/gallery-celebration-evening.png', 'Evening celebration setup suited for a wedding reception', 2, 1
WHERE NOT EXISTS (SELECT 1 FROM blog_images WHERE page_slug = 'reception-venue-visakhapatnam' AND image_path = 'assets/gallery-celebration-evening.png');

INSERT INTO blog_images (page_slug, title, image_path, alt_text, display_order, is_active)
SELECT 'reception-venue-visakhapatnam', 'Reception lawn and rooms', 'assets/gallery-day-lawn.png', 'Daylight lawn and rooms for a reception venue walkthrough', 3, 1
WHERE NOT EXISTS (SELECT 1 FROM blog_images WHERE page_slug = 'reception-venue-visakhapatnam' AND image_path = 'assets/gallery-day-lawn.png');

INSERT INTO event_packages (package_name, guest_range, display_order, is_active)
SELECT 'Friends Meetup', 'Up to 10 guests', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM event_packages);

INSERT INTO event_packages (package_name, guest_range, display_order, is_active)
SELECT 'Family Celebration', '11 to 100 guests', 2, 1
WHERE NOT EXISTS (SELECT 1 FROM event_packages WHERE package_name = 'Family Celebration');

INSERT INTO event_packages (package_name, guest_range, display_order, is_active)
SELECT 'Wedding Gathering', '101 to 300 guests', 3, 1
WHERE NOT EXISTS (SELECT 1 FROM event_packages WHERE package_name = 'Wedding Gathering');

INSERT INTO rate_card_settings (setting_key, label, setting_value, suffix, display_order)
SELECT 'cleaning_per_guest', 'Cleaning per guest', 10, 'Rs', 1
WHERE NOT EXISTS (SELECT 1 FROM rate_card_settings WHERE setting_key = 'cleaning_per_guest');

INSERT INTO rate_card_settings (setting_key, label, setting_value, suffix, display_order)
SELECT 'cleaning_minimum', 'Cleaning minimum - up to 50', 500, 'Rs', 2
WHERE NOT EXISTS (SELECT 1 FROM rate_card_settings WHERE setting_key = 'cleaning_minimum');

INSERT INTO rate_card_settings (setting_key, label, setting_value, suffix, display_order)
SELECT 'security_deposit', 'Security deposit', 10000, 'Rs', 3
WHERE NOT EXISTS (SELECT 1 FROM rate_card_settings WHERE setting_key = 'security_deposit');

INSERT INTO rate_card_settings (setting_key, label, setting_value, suffix, display_order)
SELECT 'electricity_per_unit', 'Electricity per unit', 19, 'Rs', 4
WHERE NOT EXISTS (SELECT 1 FROM rate_card_settings WHERE setting_key = 'electricity_per_unit');

INSERT INTO rate_card_settings (setting_key, label, setting_value, suffix, display_order)
SELECT 'maximum_capacity', 'Maximum capacity', 300, 'guests', 5
WHERE NOT EXISTS (SELECT 1 FROM rate_card_settings WHERE setting_key = 'maximum_capacity');

INSERT INTO rate_card_settings (setting_key, label, setting_value, suffix, display_order)
SELECT 'advance_percent', 'Advance', 50, '% of booking amount', 6
WHERE NOT EXISTS (SELECT 1 FROM rate_card_settings WHERE setting_key = 'advance_percent');

INSERT INTO rate_card_settings (setting_key, label, setting_value, suffix, display_order)
SELECT 'hold_duration_hours', 'Hold duration', 48, 'hours', 7
WHERE NOT EXISTS (SELECT 1 FROM rate_card_settings WHERE setting_key = 'hold_duration_hours');

INSERT INTO rate_card_settings (setting_key, label, setting_value, suffix, display_order)
SELECT 'referral_eligibility_days', 'Referral eligibility window', 60, 'days', 8
WHERE NOT EXISTS (SELECT 1 FROM rate_card_settings WHERE setting_key = 'referral_eligibility_days');

INSERT INTO rate_card_settings (setting_key, label, setting_value, suffix, display_order)
SELECT 'referral_bonus_amount', 'Referral bonus amount', 2000, 'Rs', 9
WHERE NOT EXISTS (SELECT 1 FROM rate_card_settings WHERE setting_key = 'referral_bonus_amount');

INSERT INTO rate_card_settings (setting_key, label, setting_value, suffix, display_order)
SELECT 'referral_new_member_bonus', 'New member referral bonus', 2000, 'Rs', 10
WHERE NOT EXISTS (SELECT 1 FROM rate_card_settings WHERE setting_key = 'referral_new_member_bonus');

INSERT INTO rate_card_settings (setting_key, label, setting_value, suffix, display_order)
SELECT 'referral_bonus_cap', 'Referral bonus maximum', 5000, 'Rs', 11
WHERE NOT EXISTS (SELECT 1 FROM rate_card_settings WHERE setting_key = 'referral_bonus_cap');

INSERT INTO rate_card_settings (setting_key, label, setting_value, suffix, display_order)
SELECT 'referral_max_count', 'Maximum paid referrals', 5, 'referrals', 12
WHERE NOT EXISTS (SELECT 1 FROM rate_card_settings WHERE setting_key = 'referral_max_count');

INSERT INTO site_terms (title, term_text, display_order, is_booking_required, is_active)
SELECT 'Date hold', 'A requested date is held for 48 hours. Confirmation follows an advance of 50% of the booking amount.', 1, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM site_terms);

INSERT INTO site_terms (title, term_text, display_order, is_booking_required, is_active)
SELECT 'Capacity', 'Maximum venue capacity is 300 guests. Only one event is held per day.', 2, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM site_terms WHERE title = 'Capacity');

INSERT INTO site_terms (title, term_text, display_order, is_booking_required, is_active)
SELECT 'Check-in and check-out', 'Check-in is 11:00 AM. Check-out is 10:00 AM the following day. Late checkout is Rs. 5,000 per hour or part thereof, subject to availability.', 3, 0, 1
WHERE NOT EXISTS (SELECT 1 FROM site_terms WHERE title = 'Check-in and check-out');

INSERT INTO site_terms (title, term_text, display_order, is_booking_required, is_active)
SELECT 'Payments', 'Any payment submitted online or by transaction ID is pending until verified by the admin team.', 4, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM site_terms WHERE title = 'Payments');

INSERT INTO site_terms (title, term_text, display_order, is_booking_required, is_active)
SELECT 'Security deposit', 'A refundable deposit of Rs. 10,000 applies to every booking and is refunded within 48 hours after inspection, subject to damages or recoverable charges.', 5, 0, 1
WHERE NOT EXISTS (SELECT 1 FROM site_terms WHERE title = 'Security deposit');

INSERT INTO site_terms (title, term_text, display_order, is_booking_required, is_active)
SELECT 'Cancellation', 'More than 30 days before the event: full advance refund. Between 30 and 15 days: 50% advance refund. Within 15 days: advance is not refundable. One reschedule is permitted, subject to availability.', 6, 0, 1
WHERE NOT EXISTS (SELECT 1 FROM site_terms WHERE title = 'Cancellation');

INSERT INTO site_terms (title, term_text, display_order, is_booking_required, is_active)
SELECT 'Property rules', 'The host is responsible for guests and vendors. Lawn protection, sound, alcohol permit, fire safety and vendor clearance rules must be followed.', 7, 0, 1
WHERE NOT EXISTS (SELECT 1 FROM site_terms WHERE title = 'Property rules');

INSERT INTO reviews (client_name, event_type, rating, review_text, status, created_by)
SELECT 'Sravani K.', 'Wedding', 5, 'The lawn felt private, fresh and beautifully maintained. Our wedding evening looked elegant, and the team helped our families settle in comfortably.', 'published', 'admin'
WHERE NOT EXISTS (SELECT 1 FROM reviews WHERE client_name = 'Sravani K.' AND event_type = 'Wedding');

INSERT INTO reviews (client_name, event_type, rating, review_text, status, created_by)
SELECT 'Rahul Varma', 'Reception', 5, 'A peaceful venue with enough space for guests, food counters and photos. The one-event-per-day approach made everything feel relaxed.', 'published', 'admin'
WHERE NOT EXISTS (SELECT 1 FROM reviews WHERE client_name = 'Rahul Varma' AND event_type = 'Reception');

INSERT INTO reviews (client_name, event_type, rating, review_text, status, created_by)
SELECT 'Mounika Reddy', 'Birthday Celebration', 5, 'We hosted a family birthday here and loved the clean rooms, open lawn and warm support. It felt personal from start to finish.', 'published', 'admin'
WHERE NOT EXISTS (SELECT 1 FROM reviews WHERE client_name = 'Mounika Reddy' AND event_type = 'Birthday Celebration');

INSERT INTO reviews (client_name, event_type, rating, review_text, status, created_by)
SELECT 'Kiran & Deepthi', 'Engagement', 4, 'The location was easy for our guests, and the outdoor setup looked lovely in the evening lights. A very pleasant experience.', 'published', 'admin'
WHERE NOT EXISTS (SELECT 1 FROM reviews WHERE client_name = 'Kiran & Deepthi' AND event_type = 'Engagement');
