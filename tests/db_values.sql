INSERT INTO `people` (`id`, `name`, `nickname`, `age`, `awesome`)
VALUES (1, 'Simon', 'ponylover94', 12, 1),
       (2, 'Peter', NULL, 40, 0),
       (3, 'Bobby', 'peter', 20, 1);

INSERT INTO `animal` (`id`, `name`, `number_of_legs`)
VALUES (1, 'mouse', 28),
       (2, 'horse', 4),
       (3, 'cat', 8);

INSERT INTO `tbl_eyes` (`color`)
VALUES ('blue'),
       ('brown'),
       ('green');

INSERT INTO `tbl_users` (`name`, `_eyeId`, `age`)
VALUES ('John', 1, 33),
       ('Jack', 2, 5),
       ('Simon', 3, 84),
       ('Matt', 3, 56),
       ('Richard', 3, 22),
       ('Ken', 2, 13);
