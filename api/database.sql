CREATE DATABASE IF NOT EXISTS smart_influencing
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE smart_influencing;

CREATE TABLE IF NOT EXISTS brands (
  id INT AUTO_INCREMENT PRIMARY KEY,
  brand_name VARCHAR(120) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  contact VARCHAR(40) DEFAULT '',
  about TEXT,
  owner_name VARCHAR(120) DEFAULT '',
  owner_linkedin VARCHAR(255) DEFAULT '',
  instagram VARCHAR(120) DEFAULT '',
  facebook VARCHAR(255) DEFAULT '',
  twitter VARCHAR(120) DEFAULT ''
);

CREATE TABLE IF NOT EXISTS influencers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  contact VARCHAR(40) DEFAULT '',
  about TEXT,
  experience TEXT,
  hourly_rate DECIMAL(10,2) DEFAULT 0,
  instagram VARCHAR(120) DEFAULT '',
  youtube VARCHAR(255) DEFAULT '',
  facebook VARCHAR(255) DEFAULT '',
  twitter VARCHAR(120) DEFAULT '',
  rating DECIMAL(3,2) DEFAULT 0
);

CREATE TABLE IF NOT EXISTS campaigns (
  id INT AUTO_INCREMENT PRIMARY KEY,
  brand_id INT NOT NULL,
  field VARCHAR(120) NOT NULL,
  overview TEXT NOT NULL,
  work_details TEXT,
  duration VARCHAR(120) DEFAULT '',
  payout DECIMAL(10,2) DEFAULT 0,
  status ENUM('active','closed','completed') DEFAULT 'active',
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_campaign_brand FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS applications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  campaign_id INT NOT NULL,
  influencer_id INT NOT NULL,
  status ENUM('waiting','accepted','rejected') DEFAULT 'waiting',
  progress TINYINT UNSIGNED NOT NULL DEFAULT 0,
  progress_note TEXT,
  progress_updated_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uniq_campaign_influencer (campaign_id, influencer_id),
  CONSTRAINT fk_app_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
  CONSTRAINT fk_app_influencer FOREIGN KEY (influencer_id) REFERENCES influencers(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS wallets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  influencer_id INT NOT NULL UNIQUE,
  total_earnings DECIMAL(12,2) DEFAULT 0,
  account_number VARCHAR(80) DEFAULT '',
  ifsc_code VARCHAR(40) DEFAULT '',
  CONSTRAINT fk_wallet_influencer FOREIGN KEY (influencer_id) REFERENCES influencers(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS chats (
  id INT AUTO_INCREMENT PRIMARY KEY,
  influencer_id INT NOT NULL,
  brand_id INT NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uniq_chat_pair (influencer_id, brand_id),
  CONSTRAINT fk_chat_influencer FOREIGN KEY (influencer_id) REFERENCES influencers(id) ON DELETE CASCADE,
  CONSTRAINT fk_chat_brand FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS brand_shortlists (
  id INT AUTO_INCREMENT PRIMARY KEY,
  brand_id INT NOT NULL,
  influencer_id INT NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uniq_brand_influencer (brand_id, influencer_id),
  CONSTRAINT fk_shortlist_brand FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE,
  CONSTRAINT fk_shortlist_influencer FOREIGN KEY (influencer_id) REFERENCES influencers(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  user_type ENUM('brand','influencer') NOT NULL,
  title VARCHAR(160) NOT NULL,
  message TEXT NOT NULL,
  entity_type VARCHAR(40) DEFAULT '',
  entity_id INT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  chat_id INT NOT NULL,
  sender_id INT NOT NULL,
  sender_type ENUM('brand','influencer') NOT NULL,
  message TEXT NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_message_chat FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE
);

INSERT IGNORE INTO brands (id, brand_name, email, password, contact, about, owner_name, owner_linkedin, instagram, facebook, twitter)
VALUES (
  1,
  'Test Brand',
  'brand@test.com',
  '$2y$10$fPcbxj7Uz4vxA96HfIm32uyf6wM1lL5vmY/GQ8V2M0K5PccfJfCQG',
  '1234567890',
  'This is a test brand.',
  'Test Owner',
  'https://linkedin.com/in/testowner',
  '@testbrand',
  'https://facebook.com/testbrand',
  '@testbrand'
);

INSERT IGNORE INTO influencers (id, name, email, password, contact, about, experience, hourly_rate, instagram, youtube, facebook, twitter, rating)
VALUES (
  1,
  'Test Influencer',
  'influencer@test.com',
  '$2y$10$fPcbxj7Uz4vxA96HfIm32uyf6wM1lL5vmY/GQ8V2M0K5PccfJfCQG',
  '0987654321',
  'This is a test influencer.',
  '5 years of experience in testing.',
  100,
  '@testinfluencer',
  'https://youtube.com/testinfluencer',
  'https://facebook.com/testinfluencer',
  '@testinfluencer',
  4.5
);

INSERT IGNORE INTO campaigns (id, brand_id, field, overview, work_details, duration, payout, status, created_at)
VALUES (
  1,
  1,
  'Fashion',
  'This is a test campaign.',
  'Post one picture on Instagram.',
  '2 weeks',
  500,
  'active',
  '2026-02-01 00:00:00'
);

INSERT IGNORE INTO wallets (id, influencer_id, total_earnings, account_number, ifsc_code)
VALUES (1, 1, 0, '', '');
