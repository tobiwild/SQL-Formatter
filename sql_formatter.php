<?php

class SqlFormatter {
    const UNDEFINED_TOKEN           = 'undefined';
    const FIRST_LEVEL_KEYWORD_TOKEN = 'first_level_keyword';
    const KEYWORD_TOKEN             = 'keyword';
    const CHAR_TOKEN                = 'char';
    const QUOTE_TOKEN               = 'quote';
    const OPENING_PARENTHESES_TOKEN = 'opening_parentheses';
    const CLOSING_PARENTHESES_TOKEN = 'closing_parentheses';
    const WHITESPACE_TOKEN          = 'whitespace';

    private $options = array(
        'firstLevelKeywords' => array(
            'select',
            'delete',
            'from',
            'where',
            'inner join',
            'left join',
            'order by',
            '(on duplicate key )?update',
            'set',
            'insert',
            'into',
            'ignore',
            'limit',
            'group by',
            'values',
        ),
        'wrapChars'       => array(','),
        'wrapWords'       => array('or', 'and'),
        'quoteChars'      => '\'`"',
        'coverUpChar'     => '_',
	'tabChar'         => "\t",
        'highlightTokens' => array(
            self::FIRST_LEVEL_KEYWORD_TOKEN,
            self::KEYWORD_TOKEN,
            self::QUOTE_TOKEN,
        )
    );

    public function __construct($options = array())
    {
        $this->options = array_merge($this->options, $options);
    }

    public function format($query, $asHtml = false)
    {
        $tokens = $this->getTokens($query);
        $tokens = $this->mergeTokensSideBySide($tokens, self::FIRST_LEVEL_KEYWORD_TOKEN);

        $wrapTokens = array(
            self::FIRST_LEVEL_KEYWORD_TOKEN,
            self::KEYWORD_TOKEN,
            self::CHAR_TOKEN,
            self::OPENING_PARENTHESES_TOKEN,
        );

        $level      = 0;
        $minLevels  = array();
        $lines      = array();

        $levelDown    = function() use (&$level, &$minLevels) { $level <= end($minLevels) || $level--; };
        $levelUp      = function() use (&$level) { $level++; };
        $beginNewLine = function() use (&$lines) {
            $lines[] = array(
                'value' => '',
                'level' => 0
            );
        };

        $addToLine = function($t) use (&$lines, &$beginNewLine) {
            $c = count($lines);

            $line = $lines[$c-1]['value'];

            $t = preg_replace('/\s+$/', ' ', $t);
            if ($line === '' || preg_match('/\s+$/', $line)) {
               $t = ltrim($t);
            } else {
               $t = preg_replace('/^\s+/', ' ', $t);
            }

            $lines[$c-1]['value'] .= $t;
        };

        $beginNewLine();
        foreach( $tokens as $token ) {

            if ($token['type'] === self::FIRST_LEVEL_KEYWORD_TOKEN) {
                $beginNewLine();
                $levelDown();
            } elseif ($token['type'] === self::CLOSING_PARENTHESES_TOKEN) {
                $beginNewLine();
                $level = array_pop($minLevels) - 1;
            }

            $lines[count($lines)-1]['level'] = $level;

            $addToLine($asHtml ? $this->formatTokenHtml($token) : $token['value']);

            if (in_array($token['type'], $wrapTokens)) {
                $beginNewLine();
            }

            if (in_array($token['type'], array(self::FIRST_LEVEL_KEYWORD_TOKEN, self::OPENING_PARENTHESES_TOKEN))) {
                $levelUp();

                if ($token['type'] === self::OPENING_PARENTHESES_TOKEN) {
                    $minLevels[] = $level;
                }
            }
        }

	$tabChar = $this->options['tabChar'];
        $lines   = array_map(function($l) use ($tabChar) { return str_repeat($tabChar, $l['level']) . rtrim($l['value'], ' '); }, $lines);
        $lines   = array_filter($lines, function($l) { return trim($l) !== ''; });

        $result = implode("\n", $lines);

        return $result;
    }

    public function formatAsHtml($query)
    {
        $result = $this->format($query, true);

        return preg_replace_callback("/(\t*)(.+)/", function ($m)  {
            $level = strlen($m[1]);
            return "<span class=\"level$level\">" . $m[2] . '</span>';
        }, $result);
    }

    private function formatTokenHtml($token)
    {
        $result = htmlentities($token['value']);

        if (in_array($token['type'], $this->options['highlightTokens'])) {
            $result = '<span class="' . $token['type'] . '">' . $result . '</span>';
        }

        return $result;
    }

    private function mergeTokensSideBySide($tokens, $type)
    {
        $result = array();

        $types = array($type, self::WHITESPACE_TOKEN);

        foreach ($tokens as $token) {
            $last = array_pop($result);

            if ($last && $last['type'] === $type && in_array($token['type'], $types)) {
                $last['type']   = $type;
                $last['value'] .= $token['value'];

                $result[] = $last;
            } else {
                if ($last) {
                    $result[] = $last;
                }
                $result[] = $token;
            }
        }

        return $result;
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


        $tokens += $this->getRemainingTokens($tokens, $query);
        ksort($tokens);

        return $tokens;
    }

    private function getRemainingTokens($tokens, $query)
    {
        ksort($tokens);

        $startIndex      = 0;
        $undefinedTokens = array();

        foreach ($tokens as $index => $token) {
            $undefinedTokenValue = substr($query, $startIndex, $index - $startIndex);

            if (strlen($undefinedTokenValue)) {
                $undefinedTokens[$startIndex] = $this->getTokenByString($undefinedTokenValue);
            }

            $startIndex = $index + strlen($token['value']);
        }

        $undefinedTokenValue = substr($query, $startIndex, strlen($query) - $startIndex);

        if (strlen($undefinedTokenValue)) {
            $undefinedTokens[$startIndex] = $this->getTokenByString($undefinedTokenValue);
        }

        return $undefinedTokens;
    }

    private function getTokenByString($string)
    {
        return array(
            'type'  => trim($string) === '' ?  self::WHITESPACE_TOKEN : self::UNDEFINED_TOKEN,
            'value' => $string
        );
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
}
