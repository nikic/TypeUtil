<?php declare(strict_types=1);

namespace TypeUtil;

class CliParser {
    public function parseOptions(array $argv) {
        $rest = [];
        $flags = [];
        $phpVersion = '7.2';
        for ($i = 1, $c = count($argv); $i < $c; $i++) {
            $arg = $argv[$i];
            if ($arg === '--php') {
                $i++;
                if ($i >= $c) {
                    throw new \Exception('--php must be followed by version');
                }
                $phpVersion = $argv[$i];
            } else if ($arg[0] === '-') {
                $flags[] = $arg;
            } else {
                $rest[] = $arg;
            }
        }

        $options = Options::fromPhpVersion($phpVersion);
        foreach ($flags as $flag) {
            if (!$options->setCliOption($flag)) {
                throw new \Exception("Unknown option $flag");
            }
        }
        return [$options, $rest];
    }
}