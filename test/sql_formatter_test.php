<?php
require_once(__DIR__ . '/../sql_formatter.php');

function colorizeQuery($query) {
    return preg_replace("/\t/", "\e[41m$0\e[42m",
        preg_replace('/.*/', "\e[42m$0\e[0m", $query));
}

$formatter = new SqlFormatter();

foreach (glob(__DIR__ . '/query/*.sql') as $file) {
    $originalQuery    = file_get_contents($file);
    $unformattedQuery = str_replace(array("\n", "\t"), ' ', $originalQuery);
    $formattedQuery   = $formatter->format($unformattedQuery);

    if ($originalQuery === $formattedQuery) {
        echo $file . ' is correct' . PHP_EOL;
    } else {
        echo $file . ' is not correct:' . PHP_EOL;
        echo 'expected:' . PHP_EOL . colorizeQuery($originalQuery) . PHP_EOL;
        echo 'actual:' . PHP_EOL . colorizeQuery($formattedQuery) . PHP_EOL;
    }
}
