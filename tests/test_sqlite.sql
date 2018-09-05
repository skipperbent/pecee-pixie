CREATE TABLE `people` (
  `id` integer  NOT NULL PRIMARY KEY,
  `name` text  NOT NULL DEFAULT '0',
  `nickname` text  NULL DEFAULT '0',
  `age` integer  NOT NULL DEFAULT 0,
  `awesome` integer  NOT NULL DEFAULT 0
);
CREATE INDEX `idx_awesome` ON people(`awesome`);
CREATE TABLE `animal` (
  `id` integer  NOT NULL PRIMARY KEY,
  `name` text NOT NULL DEFAULT '0',
  `number_of_legs` integer NOT NULL DEFAULT '0'
);
INSERT INTO `people` (`id`,`name`,`nickname`,`age`,`awesome`)
VALUES
       (1,'Simon','ponylover94',12,1),
       (2,'Peter',NULL,40,0),
       (3,'Bobby','peter',20,1);
INSERT INTO `animal` (`id`,`name`,`number_of_legs`)
VALUES
       (1,'mouse',28),
       (2,'horse',4),
       (3,'cat',8);
