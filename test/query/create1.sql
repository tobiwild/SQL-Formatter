CREATE TABLE `booking` (
	`id` bigint(
		20
	) NOT NULL AUTO_INCREMENT,
	`salutation` tinyint(
		4
	) DEFAULT NULL,
	`firstname` varchar(
		255
	) NOT NULL,
	`lastname` varchar(
		255
	) NOT NULL,
	`street` varchar(
		255
	) NOT NULL,
	`zip` varchar(
		5
	) NOT NULL,
	`city` varchar(
		255
	) NOT NULL,
	`phone` varchar(
		255
	) NOT NULL,
	`email` varchar(
		255
	) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;