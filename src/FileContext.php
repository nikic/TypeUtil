<?php declare(strict_types=1);

namespace TypeUtil;

use PhpParser\Node\Stmt;

class FileContext {
    /** @var string */
    public $path;
    /** @var string */
    public $code;
    /** @var Stmt[] */
    public $stmts;

    public function __construct(string $path, string $code, array $stmts) {
        $this->path = $path;
        $this->code = $code;
        $this->stmts = $stmts;
    }
}
