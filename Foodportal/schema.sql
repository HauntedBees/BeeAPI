SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
CREATE DATABASE IF NOT EXISTS food DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE food;

CREATE TABLE country (
  id bigint(20) NOT NULL,
  ckey varchar(5) NOT NULL,
  name varchar(100) NOT NULL,
  description varchar(2000) NOT NULL,
  population int(11) NOT NULL,
  popEstimate int(11) NOT NULL,
  area int(11) NOT NULL,
  independence varchar(30) NOT NULL,
  indFrom varchar(50) DEFAULT NULL,
  languages varchar(600) NOT NULL,
  demonym varchar(100) NOT NULL,
  currency varchar(100) NOT NULL,
  motto varchar(100) DEFAULT NULL,
  foodURL varchar(200) NOT NULL,
  musicURL varchar(200) NOT NULL,
  realFirstLetter char(3) DEFAULT NULL,
  focusArea char(2) DEFAULT NULL,
  notes text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE country_neighbor (
  country bigint(20) NOT NULL,
  neighbor bigint(20) DEFAULT NULL,
  shell bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE diet (
  id bigint(20) NOT NULL,
  name varchar(50) NOT NULL,
  emoji varchar(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE dish (
  id bigint(20) NOT NULL,
  name varchar(50) NOT NULL,
  emoji varchar(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ingredient (
  id bigint(20) NOT NULL,
  name varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE recipe (
  id bigint(20) NOT NULL,
  country bigint(20) NOT NULL,
  name varchar(120) NOT NULL,
  dish bigint(20) NOT NULL,
  url varchar(500) NOT NULL,
  date date NOT NULL,
  img varchar(50) NOT NULL,
  databee varchar(50) DEFAULT NULL,
  description varchar(1500) NOT NULL,
  favorite bit(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE recipe_diet (
  recipe bigint(20) NOT NULL,
  diet bigint(20) NOT NULL,
  optional bit(1) NOT NULL,
  description varchar(500) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE recipe_ingredient (
  recipe bigint(20) NOT NULL,
  ingredient bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE shell_country (
  id bigint(20) NOT NULL,
  countryCode varchar(5) NOT NULL,
  name varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE song (
  id bigint(20) NOT NULL,
  country bigint(20) NOT NULL,
  name varchar(200) NOT NULL,
  url varchar(200) NOT NULL,
  favorite bit(1) NOT NULL,
  translation varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


ALTER TABLE country
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY ckey (ckey) USING BTREE;

ALTER TABLE country_neighbor
  ADD KEY country (country),
  ADD KEY neighbor (neighbor),
  ADD KEY shell (shell);

ALTER TABLE diet
  ADD PRIMARY KEY (id),
  ADD KEY name (name);

ALTER TABLE dish
  ADD PRIMARY KEY (id),
  ADD KEY name (name);

ALTER TABLE ingredient
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY name (name);

ALTER TABLE recipe
  ADD PRIMARY KEY (id),
  ADD KEY country (country),
  ADD KEY dish (dish);

ALTER TABLE recipe_diet
  ADD KEY recipe (recipe),
  ADD KEY diet (diet);

ALTER TABLE recipe_ingredient
  ADD KEY recipe (recipe),
  ADD KEY ingredient (ingredient);

ALTER TABLE shell_country
  ADD PRIMARY KEY (id);

ALTER TABLE song
  ADD PRIMARY KEY (id),
  ADD KEY country (country);


ALTER TABLE country
  MODIFY id bigint(20) NOT NULL AUTO_INCREMENT;

ALTER TABLE diet
  MODIFY id bigint(20) NOT NULL AUTO_INCREMENT;

ALTER TABLE dish
  MODIFY id bigint(20) NOT NULL AUTO_INCREMENT;

ALTER TABLE ingredient
  MODIFY id bigint(20) NOT NULL AUTO_INCREMENT;

ALTER TABLE recipe
  MODIFY id bigint(20) NOT NULL AUTO_INCREMENT;

ALTER TABLE shell_country
  MODIFY id bigint(20) NOT NULL AUTO_INCREMENT;

ALTER TABLE song
  MODIFY id bigint(20) NOT NULL AUTO_INCREMENT;


ALTER TABLE country_neighbor
  ADD CONSTRAINT country_neighbor_ibfk_1 FOREIGN KEY (country) REFERENCES country (id),
  ADD CONSTRAINT country_neighbor_ibfk_2 FOREIGN KEY (neighbor) REFERENCES country (id),
  ADD CONSTRAINT country_neighbor_ibfk_3 FOREIGN KEY (shell) REFERENCES shell_country (id);

ALTER TABLE recipe
  ADD CONSTRAINT recipe_ibfk_1 FOREIGN KEY (country) REFERENCES country (id),
  ADD CONSTRAINT recipe_ibfk_2 FOREIGN KEY (dish) REFERENCES dish (id);

ALTER TABLE recipe_diet
  ADD CONSTRAINT recipe_diet_ibfk_1 FOREIGN KEY (recipe) REFERENCES recipe (id),
  ADD CONSTRAINT recipe_diet_ibfk_2 FOREIGN KEY (diet) REFERENCES diet (id);

ALTER TABLE recipe_ingredient
  ADD CONSTRAINT recipe_ingredient_ibfk_1 FOREIGN KEY (recipe) REFERENCES recipe (id),
  ADD CONSTRAINT recipe_ingredient_ibfk_2 FOREIGN KEY (ingredient) REFERENCES ingredient (id);

ALTER TABLE song
  ADD CONSTRAINT song_ibfk_1 FOREIGN KEY (country) REFERENCES country (id);
