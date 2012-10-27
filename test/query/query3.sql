INSERT
	INTO Postcode_tbl_2 (
		postcode,
		longitude,
		latitude
	)
SELECT
	postcode,
	longitude,
	latitude
FROM
	Postcode_tbl
WHERE
	postcode LIKE 'CM%';