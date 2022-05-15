DROP USER 'nopermuser'@'localhost';
CREATE USER 'nopermuser'@'localhost' IDENTIFIED BY 'password';
GRANT USAGE ON *.* TO 'nopermuser'@'localhost' WITH GRANT OPTION;
FLUSH PRIVILEGES;

DROP TABLE IF EXISTS `people`;
DROP TABLE IF EXISTS `animal`;
DROP TABLE IF EXISTS `tbl_users`;
DROP TABLE IF EXISTS `tbl_eyes`;
DROP TABLE IF EXISTS `empty_table`;

CREATE TABLE `people` (
  `id`       INT          NOT NULL AUTO_INCREMENT,
  `name`     VARCHAR(255) NOT NULL DEFAULT '0',
  `nickname` VARCHAR(255) NULL     DEFAULT '0',
  `age`      INT          NOT NULL DEFAULT '0',
  `awesome`  TINYINT(1)   NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  INDEX `awesome` (`awesome`)
)
  COLLATE = 'latin1_swedish_ci';

CREATE TABLE `animal` (
  `id`             INT          NOT NULL AUTO_INCREMENT,
  `name`           VARCHAR(255) NOT NULL DEFAULT '0',
  `number_of_legs` INT          NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
)
  COLLATE = 'latin1_swedish_ci';

CREATE TABLE `tbl_eyes` (
  `id`    INT          NOT NULL AUTO_INCREMENT,
  `color` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY (`color`)
)
  COLLATE = 'utf8_general_ci'
  ENGINE = InnoDB;

CREATE TABLE `tbl_users` (
  `id`     INT          NOT NULL AUTO_INCREMENT,
  `name`   VARCHAR(255) NOT NULL,
  `_eyeId` INT          NOT NULL,
  `age`    INT          NULL     DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_eye` (`_eyeId`),
  CONSTRAINT `FK_EyeId` FOREIGN KEY (`_eyeId`) REFERENCES `tbl_eyes` (`id`)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT
)
  COLLATE = 'utf8_general_ci'
  ENGINE = InnoDB;

CREATE TABLE `empty_table` (
  `id`    INT          NOT NULL AUTO_INCREMENT,
  `description` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`)
)
  COLLATE = 'utf8_general_ci'
  ENGINE = InnoDB;
