<?php

class SqlFormatter {
    const UNDEFINED_TOKEN           = 'undefined';
    const FIRST_LEVEL_KEYWORD_TOKEN = 'first_level_keyword';
    const KEYWORD_TOKEN             = 'keyword';
    const CHAR_TOKEN                = 'char';
    const QUOTE_TOKEN               = 'quote';
    const OPENING_PARENTHESES_TOKEN = 'opening_parentheses';
    const CLOSING_PARENTHESES_TOKEN = 'closing_parentheses';

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
        'wrapChars'     => array(','),
        'wrapWords'     => array('or', 'and'),
        'quoteChars'    => '\'`"',
        'coverUpChar' => '_'
    );

    public function __construct($options = array())
    {
        $this->options = array_merge($this->options, $options);
    }

    public function format($query)
    {
        $tokens = $this->getTokens($query);
return;
        $query = $this->wrapFirstLevelKeywords($query);
        $query = $this->wrapWords($query);
        $query = $this->wrapParentheses($query);
        $query = $this->wrapChars($query);
        $query = ltrim($query, "\n");

        return $query;
    }

    private function getTokens($query)
    {
        $quoteTokens  = $this->getQuoteTokens($query);
        $query        = $this->coverUpTokens($query, $quoteTokens);

        $tokens =
             $this->getTokensByPatterns($query,
                array_map(array($this, 'getWordPattern'), $this->options['firstLevelKeywords']),
                self::FIRST_LEVEL_KEYWORD_TOKEN)

             +

             $this->getTokensByPatterns($query,
                array_map(array($this, 'getWordPattern'), $this->options['wrapWords']),
                self::KEYWORD_TOKEN)

             +

             $this->getTokensByPatterns($query,
                array_map(array($this, 'getCharPattern'), $this->options['wrapChars']),
                self::CHAR_TOKEN)

             +

             $this->getTokensByPatterns($query, array('/\(/'),
                self::OPENING_PARENTHESES_TOKEN)

             +

             $this->getTokensByPatterns($query, array('/\)/'),
                self::CLOSING_PARENTHESES_TOKEN)

             +

             $quoteTokens;


        $tokens += $this->getUndefinedTokens($tokens, $query);
        ksort($tokens);

        return $tokens;
    }

    private function getUndefinedTokens($tokens, $query)
    {
        ksort($tokens);

        $startIndex      = 0;
        $undefinedTokens = array();

        foreach ($tokens as $index => $token) {
            $undefinedTokenValue = substr($query, $startIndex, $index - $startIndex);

            if (strlen($undefinedTokenValue)) {
                $undefinedTokens[$startIndex] = array(
                    'type' => self::UNDEFINED_TOKEN,
                    'value' => $undefinedTokenValue
                );
            }

            $startIndex = $index + strlen($token['value']);
        }

        $undefinedTokenValue = substr($query, $startIndex, strlen($query) - $startIndex);

        if (strlen($undefinedTokenValue)) {
            $undefinedTokens[$startIndex] = array(
                'type' => self::UNDEFINED_TOKEN,
                'value' => $undefinedTokenValue
            );
        }

        return $undefinedTokens;
    }


    private function getTokensByPatterns($query, $patterns, $type) {
        $result = array();

        foreach ($patterns as $pattern)
        {
            preg_match_all($pattern, $query, $m, PREG_OFFSET_CAPTURE);

            foreach($m[0] as $match) {
                $result[$match[1]] = array(
                    'type'  => $type,
                    'value' => $match[0]
                );
            }
        }

        return $result;

    }

    private function getQuoteTokens($query)
    {
        $result = array();

        $chars = $this->options['quoteChars'];
        preg_match_all("/(?<!\\\\)(\\\\\\\\)*([${chars}])/", $query, $m,  PREG_OFFSET_CAPTURE );

        $relevant = null;
        foreach ($m[2] as $quoteMatch) {
            if (is_null($relevant)) {
                $relevant = $quoteMatch;
            } elseif ($quoteMatch[0] === $relevant[0]) {
                $result[$relevant[1]] = array(
                    'type'  => self::QUOTE_TOKEN,
                    'value' => substr($query, $relevant[1], $quoteMatch[1] - $relevant[1] + 1)
                );

                $relevant = null;
            }
        }

        return $result;
    }

    private function coverUpTokens($query, $tokens)
    {
        foreach ($tokens as $index => $token) {
            $length = strlen($token['value']);
            $query  = substr_replace($query,  str_repeat($this->options['coverUpChar'], $length),
                $index, $length);
        }

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

    private function getCharPattern($char)
    {
        return $this->getPattern($char);
    }

    private function getWordPattern($word)
    {
        return $this->getPattern("\b$word\b", 'i');
    }

    private function getPattern($pattern, $modifier = '')
    {
        return '/' . $pattern . '/' . $modifier;
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
