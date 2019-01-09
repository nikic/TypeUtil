<?php declare(strict_types=1);

namespace TypeUtil;

use PhpParser\Lexer;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;

class IntegrationTest extends \PHPUnit\Framework\TestCase {
    /** @dataProvider provideTests */
    public function testMutation(string $name, string $type, Options $options, string $code, string $expected) {
        $lexer = new Lexer\Emulative([
            'usedAttributes' => [
                'comments', 'startLine', 'startFilePos', 'endFilePos',
            ]
        ]);
        $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7, $lexer);
        $fileContext = $this->codeToFileContext($parser, $code);

        switch ($type) {
        case 'add':
            $nameResolver = new NameResolver();
            $extractor = new TypeExtractor($nameResolver);

            $context = getContext($extractor, $nameResolver, [$fileContext]);
            $context->setFileContext($fileContext);

            $modifier = getAddModifier($nameResolver, $extractor, $context, $options);
            $result = $modifier($fileContext);
            break;
        case 'remove':
            $modifier = getRemoveModifier();
            $result = $modifier($fileContext);
            break;
        }

        $this->assertSame($expected, $result, $name);
    }

    public function provideTests() {
        $files = filesInDirs([__DIR__ . '/code'], 'php');
        foreach ($files as $file) {
            $name = $file->getPathName();
            $code = file_get_contents($name);
            list($orig, $expected) = explode('-----', $code);

            $ret = preg_match('/^#!([^\r\n]+)/', $code, $matches);
            assert($ret === 1);

            $cliParser = new CliParser();
            [$options, $rest] = $cliParser->parseOptions(explode(' ', 'type-util --php 7.0 --no-strict-types ' . $matches[1]));
            assert(count($rest) === 1);

            $type = $rest[0];
            $orig = substr($orig, strlen($matches[0]));

            yield [$name, $type, $options, $this->canonicalize($orig), $this->canonicalize($expected)];
        }
    }

    private function codeToFileContext(Parser $parser, string $code) : FileContext {
        return new FileContext('file.php', $code, $parser->parse($code));
    }

    private function canonicalize(string $string) {
        $string = str_replace("\r\n", "\n", $string);
        return trim($string);
    }
}

