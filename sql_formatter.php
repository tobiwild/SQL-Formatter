<?php

class SqlFormatter {
    private $options = array(
        'firstLevelKeywords' => array(
            'select',
            "(delete (ignore )?)?from",
            'where',
            'inner join',
            'left join',
            'order by',
            '(on duplicate key )?update',
            'set',
            'insert (ignore )?into',
            'limit',
            'group by',
            'values',
        ),
        'wrapChars' => array(','),
        'wrapWords' => array('or', 'and')
    );

    public function __construct($options = array())
    {
        $this->options = array_merge($this->options, $options);
    }

    public function format($query)
    {
        $query = $this->wrapFirstLevelKeywords($query);
        $query = $this->wrapWords($query);
        $query = $this->wrapParentheses($query);
        $query = $this->wrapChars($query);
        $query = ltrim($query, "\n");

        return $query;
    }

    public function formatAsHtml($query)
    {
        $result = $this->format($query);

        return preg_replace_callback("/(\t*)(.+)/", function ($m)  {
            $level = strlen($m[1]);
            return "<span class=\"level$level\">" . htmlentities($m[2]) . '</span>';
        }, $result);
    }

    private function wrapFirstLevelKeywords($query)
    {
        return preg_replace(
            array_map(array($this, 'getFirstLevelKeywordPattern'), $this->options['firstLevelKeywords']),
            "\n$1\n\t",
            $query);
    }

    private function wrapChars($result)
    {
        return $this->wrapString(array_map(array($this, 'getCharPattern'), $this->options['wrapChars']), $result);
    }

    private function wrapWords($result)
    {
        return $this->wrapString(array_map(array($this, 'getWordPattern'), $this->options['wrapWords']), $result);
    }

    private function wrapString($wrapPatterns, $result)
    {
        return preg_replace_callback("/(\t*).+/", function ($m) use ($wrapPatterns) {
            return preg_replace(
                $wrapPatterns,
                "$1\n" . $m[1],
                $m[0]);
        }, $result);
    }

    private function getFirstLevelKeywordPattern($keyword)
    {
        return $this->getWordPattern($keyword, "[\n ]*");
    }

    private function getCharPattern($char)
    {
        return $this->getPattern('(' . $char . ")[\n ]*");
    }

    private function getWordPattern($word, $prePattern = '')
    {
        return $this->getPattern($prePattern . "(\b$word\b)[\n ]*", 'i');
    }

    private function getPattern($pattern, $modifier = '')
    {
        $lookAheadEvenQuotes = "(?=[^']*('[^']*'[^']*)*$)";

        return '/' . $pattern . $lookAheadEvenQuotes . '/' . $modifier;
    }

    private function wrapParentheses($result)
    {
        $pattern = $this->getPattern("(\t*)([^\n()]*\()[\n ]*(((?>[^()]+)|(?R))*)\)", 'm');

        return preg_replace_callback($pattern, function ($m) {
            $line = $m[1] . $m[2] . "\n" . rtrim($m[3], ' ');
            return str_replace("\n", "\n\t" . $m[1], $line) . "\n" . $m[1] .")";
        }, $result);
    }
}
