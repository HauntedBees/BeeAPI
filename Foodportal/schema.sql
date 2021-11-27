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
  emoji varchar(6) NOT NULL,
  spiceOnly bit(1) DEFAULT b'0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE flavor (
  id bigint(20) NOT NULL,
  name varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ingredient (
  id bigint(20) NOT NULL,
  name varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE license (
  id bigint(20) NOT NULL,
  code varchar(20) NOT NULL,
  url varchar(150) NOT NULL
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

CREATE TABLE seasoning (
  id bigint(20) NOT NULL,
  name varchar(50) NOT NULL,
  origin varchar(100) NOT NULL,
  description text NOT NULL,
  emoji varchar(8) NOT NULL,
  type int(11) NOT NULL,
  species varchar(100) NOT NULL,
  imagedesc varchar(200) NOT NULL,
  imagename varchar(200) NOT NULL,
  imageauthor varchar(200) NOT NULL,
  imageurl varchar(500) NOT NULL,
  authorurl varchar(500) NOT NULL,
  license bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE seasoning_dish (
  seasoning bigint(20) NOT NULL,
  dish bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE seasoning_flavor (
  seasoning bigint(20) NOT NULL,
  flavor bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE seasoning_ingredient (
  seasoning bigint(20) NOT NULL,
  ingredient bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE seasoning_recipe (
  id bigint(20) NOT NULL,
  seasoning bigint(20) NOT NULL,
  name varchar(100) NOT NULL,
  url varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE seasoning_relationship (
  seasoning1 bigint(20) NOT NULL,
  seasoning2 bigint(20) NOT NULL,
  relationship int(11) NOT NULL COMMENT '0 = pairs with\r\n1 = component of\r\n2 = related to'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE seasoning_synonym (
  seasoning bigint(20) NOT NULL,
  synonym varchar(50) NOT NULL
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

ALTER TABLE flavor
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY id (id),
  ADD UNIQUE KEY name (name);

ALTER TABLE ingredient
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY name (name);

ALTER TABLE license
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY code (code);

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

ALTER TABLE seasoning
  ADD PRIMARY KEY (id),
  ADD KEY license (license);

ALTER TABLE seasoning_dish
  ADD KEY seasoning (seasoning),
  ADD KEY dish (dish);

ALTER TABLE seasoning_flavor
  ADD KEY flavor (flavor),
  ADD KEY seasoning (seasoning);

ALTER TABLE seasoning_ingredient
  ADD KEY seasoning (seasoning),
  ADD KEY ingredient (ingredient);

ALTER TABLE seasoning_recipe
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY id (id),
  ADD KEY seasoning (seasoning);

ALTER TABLE seasoning_relationship
  ADD KEY seasoning1 (seasoning1),
  ADD KEY seasoning2 (seasoning2);

ALTER TABLE seasoning_synonym
  ADD UNIQUE KEY synonym (synonym),
  ADD KEY seasoning (seasoning);

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

ALTER TABLE flavor
  MODIFY id bigint(20) NOT NULL AUTO_INCREMENT;

ALTER TABLE ingredient
  MODIFY id bigint(20) NOT NULL AUTO_INCREMENT;

ALTER TABLE license
  MODIFY id bigint(20) NOT NULL AUTO_INCREMENT;

ALTER TABLE recipe
  MODIFY id bigint(20) NOT NULL AUTO_INCREMENT;

ALTER TABLE seasoning
  MODIFY id bigint(20) NOT NULL AUTO_INCREMENT;

ALTER TABLE seasoning_recipe
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

ALTER TABLE seasoning
  ADD CONSTRAINT seasoning_ibfk_1 FOREIGN KEY (license) REFERENCES license (id);

ALTER TABLE seasoning_dish
  ADD CONSTRAINT seasoning_dish_ibfk_1 FOREIGN KEY (seasoning) REFERENCES seasoning (id),
  ADD CONSTRAINT seasoning_dish_ibfk_2 FOREIGN KEY (dish) REFERENCES dish (id);

ALTER TABLE seasoning_flavor
  ADD CONSTRAINT seasoning_flavor_ibfk_1 FOREIGN KEY (flavor) REFERENCES flavor (id),
  ADD CONSTRAINT seasoning_flavor_ibfk_2 FOREIGN KEY (seasoning) REFERENCES seasoning (id);

ALTER TABLE seasoning_ingredient
  ADD CONSTRAINT seasoning_ingredient_ibfk_1 FOREIGN KEY (seasoning) REFERENCES seasoning (id),
  ADD CONSTRAINT seasoning_ingredient_ibfk_2 FOREIGN KEY (ingredient) REFERENCES ingredient (id);

ALTER TABLE seasoning_recipe
  ADD CONSTRAINT seasoning_recipe_ibfk_1 FOREIGN KEY (seasoning) REFERENCES seasoning (id);

ALTER TABLE seasoning_relationship
  ADD CONSTRAINT seasoning_relationship_ibfk_1 FOREIGN KEY (seasoning1) REFERENCES seasoning (id),
  ADD CONSTRAINT seasoning_relationship_ibfk_2 FOREIGN KEY (seasoning2) REFERENCES seasoning (id);

ALTER TABLE seasoning_synonym
  ADD CONSTRAINT seasoning_synonym_ibfk_1 FOREIGN KEY (seasoning) REFERENCES seasoning (id);

ALTER TABLE song
  ADD CONSTRAINT song_ibfk_1 FOREIGN KEY (country) REFERENCES country (id);