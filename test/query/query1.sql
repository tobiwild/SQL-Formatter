SELECT
	Bug.ixBug AS ix,
	Bug.ixBugEventLatest AS ixChild,
	Area.nTypeAs nAreaType
FROM
	(
		Bug
		INNER JOIN
			Area ON Bug.ixArea = Area.ixArea
	)
WHERE
	ixBugEventLatest <= N AND
	ixBugEventLatest >= N AND
	Bug.ixBug IN (
		SELECT
			ix
		FROM
			IndexDelta
		WHERE
			sType = 'SELECT a,b FROM c WHERE 1=1 AND 2=2' AND
			fDeleted = N
	) AND
	test = 'lala' AND
	foo in (
		'bar'
	) OR
	kacke like '(anemone)' AND
	dings in (
		1,
		2,
		3
	)
ORDER BY
	ixBugEventLatest DESC
LIMIT
	N;