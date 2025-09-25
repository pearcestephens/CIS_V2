-- 001_shipping_pricing.sql
-- Schema for carriers, services, containers, surcharges, and pricing rules

START TRANSACTION;

-- Carriers (e.g., NZ Post, NZ Couriers/GSS)
CREATE TABLE IF NOT EXISTS carriers (
  carrier_id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Service families (e.g., Letter, Box, Ticket)
CREATE TABLE IF NOT EXISTS carrier_services (
  service_id INT AUTO_INCREMENT PRIMARY KEY,
  carrier_id INT NOT NULL,
  code VARCHAR(64) NOT NULL,
  name VARCHAR(150) NOT NULL,
  UNIQUE KEY uniq_carrier_service (carrier_id, code),
  CONSTRAINT fk_services_carrier FOREIGN KEY (carrier_id) REFERENCES carriers(carrier_id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Containers (for dimensional presets, optional per service)
CREATE TABLE IF NOT EXISTS containers (
  container_id INT AUTO_INCREMENT PRIMARY KEY,
  carrier_id INT NOT NULL,
  service_id INT NULL,
  code VARCHAR(64) NOT NULL,
  name VARCHAR(150) NOT NULL,
  length_mm INT NULL,
  width_mm INT NULL,
  height_mm INT NULL,
  max_weight_grams INT NULL,
  max_units INT NULL,
  UNIQUE KEY uniq_container (carrier_id, code),
  CONSTRAINT fk_containers_carrier FOREIGN KEY (carrier_id) REFERENCES carriers(carrier_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_containers_service FOREIGN KEY (service_id) REFERENCES carrier_services(service_id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Base prices (per container or per service when container is NULL)
CREATE TABLE IF NOT EXISTS pricing_rules (
  rule_id INT AUTO_INCREMENT PRIMARY KEY,
  carrier_id INT NOT NULL,
  service_id INT NULL,
  container_id INT NULL,
  price DECIMAL(10,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'NZD',
  effective_from DATE NULL,
  effective_to DATE NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_pricing_carrier FOREIGN KEY (carrier_id) REFERENCES carriers(carrier_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_pricing_service FOREIGN KEY (service_id) REFERENCES carrier_services(service_id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_pricing_container FOREIGN KEY (container_id) REFERENCES containers(container_id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Surcharges (per carrier)
CREATE TABLE IF NOT EXISTS surcharges (
  surcharge_id INT AUTO_INCREMENT PRIMARY KEY,
  carrier_id INT NOT NULL,
  code VARCHAR(64) NOT NULL,
  name VARCHAR(150) NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  UNIQUE KEY uniq_surcharge (carrier_id, code),
  CONSTRAINT fk_surcharge_carrier FOREIGN KEY (carrier_id) REFERENCES carriers(carrier_id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
