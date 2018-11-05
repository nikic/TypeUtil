<?php declare(strict_types=1);

namespace TypeUtil;

class Options {
    /** @var bool */
    public $strictTypes = false;

    /** @var bool */
    public $nullableTypes = false;
    /** @var bool */
    public $iterable = false;

    public static function fromPhpVersion(string $version) {
        $options = new Options();

        if (version_compare($version, '7.0', '>=')) {
            $options->strictTypes = true;
        }

        if (version_compare($version, '7.1', '>=')) {
            $options->nullableTypes = true;
            $options->iterable = true;
        }

        return $options;
    }

    public function setCliOption(string $option): bool {
        $knownOptions = [
            'strict-types' => 'strictTypes',
            'nullable-types' => 'nullableTypes',
            'iterable' => 'iterable',
        ];

        if (!preg_match('/--(no-)?([a-z-]+)/', $option, $matches)) {
            return false;
        }

        $option = $matches[2];
        if (!isset($knownOptions[$option])) {
            return false;
        }

        $this->{$knownOptions[$option]} = $matches[1] === '';
        return true;
    }
}