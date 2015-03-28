<?php declare(strict_types=1);

namespace TypeUtil;

/** String that can be modified without invalidating offsets into it */
class MutableString {
    use NoDynamicProperties;

    private $string;
    // [[pos, len, newString]]
    private $modifications = [];

    public function __construct(string $string) {
        $this->string = $string;
    }

    public function insert(int $pos, string $newString) {
        $this->modifications[] = [$pos, 0, $newString];
    }

    public function remove(int $pos, int $len) {
        $this->modifications[] = [$pos, $len, ''];
    }

    public function indexOf(string $str, int $startPos) {
        return strpos($this->string, $str, $startPos);
    }

    public function getOrigString() : string {
        return $this->string;
    }

    public function getModifiedString() : string {
        // Sort by position
        usort($this->modifications, function($a, $b) {
            return $a[0] <=> $b[0];
        });

        $result = '';
        $startPos = 0;
        foreach ($this->modifications as list($pos, $len, $newString)) {
            $result .= substr($this->string, $startPos, $pos - $startPos);
            $result .= $newString;
            $startPos = $pos + $len;
        }
        $result .= substr($this->string, $startPos);
        return $result;
    }
}

