INSERT IGNORE INTO
	Persons
VALUES
	(
		4,
		'Nilsen',
		'Johan',
		'Bakken 2',
		'Stavanger'
	)
ON DUPLICATE KEY UPDATE
	c=c+1;