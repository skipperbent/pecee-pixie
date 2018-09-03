CREATE USER 'nopermuser'@'%' identified by 'password'; FLUSH PRIVILEGES;

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

INSERT INTO `people` (`id`,`name`,`nickname`,`age`,`awesome`) VALUES
(1,'Simon','ponylover94',12,1),
(2,'Peter',NULL,40,0),
(3,'Bobby','peter',20,1);

INSERT INTO `animal` (`id`,`name`,`number_of_legs`) VALUES
(1,'mouse',28),
(2,'horse',4),
(3,'cat',8);
