DROP TABLE IF EXISTS `people`;
DROP TABLE IF EXISTS `animal`;
DROP TABLE IF EXISTS `tbl_users`;
DROP TABLE IF EXISTS `tbl_eyes`;
CREATE TABLE `people` (
  `id`       integer NOT NULL PRIMARY KEY,
  `name`     text    NOT NULL DEFAULT '0',
  `nickname` text    NULL     DEFAULT '0',
  `age`      integer NOT NULL DEFAULT 0,
  `awesome`  integer NOT NULL DEFAULT 0
);
CREATE INDEX `idx_awesome`
  ON people (`awesome`);
CREATE TABLE `animal` (
  `id`             integer NOT NULL PRIMARY KEY,
  `name`           text    NOT NULL DEFAULT '0',
  `number_of_legs` integer NOT NULL DEFAULT '0'
);


CREATE TABLE `tbl_eyes` (
  `id`    integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  `color` text    NOT NULL
);
CREATE UNIQUE INDEX `idx_color` ON `tbl_eyes` (`color`);

CREATE TABLE `tbl_users` (
  `id`     integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  `name`   text    NOT NULL,
  `_eyeId` integer NOT NULL,
  `age`    integer NULL     DEFAULT NULL,
  CONSTRAINT `FK_EyeId` FOREIGN KEY (`_eyeId`) REFERENCES `tbl_eyes` (`id`)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT
);

--  Note: foreign key support will be enabled
--  https://www.sqlite.org/pragma.html#pragma_foreign_keys
PRAGMA foreign_keys = ON;
