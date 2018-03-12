CREATE TABLE `people` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`name` VARCHAR(255) NOT NULL DEFAULT '0',
	`nickname` VARCHAR(255) NULL DEFAULT '0',
  `age` INT NOT NULL DEFAULT '0',
	`awesome` TINYINT(1) NOT NULL DEFAULT '0',
	PRIMARY KEY (`id`),
	INDEX `awesome` (`awesome`)
)
COLLATE='latin1_swedish_ci'
;

CREATE TABLE `animal` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`name` VARCHAR(255) NOT NULL DEFAULT '0',
	`number_of_legs` INT NOT NULL DEFAULT '0',
	PRIMARY KEY (`id`)
)
COLLATE='latin1_swedish_ci'
;
