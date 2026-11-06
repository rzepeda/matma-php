<?php

declare(strict_types=1);

namespace Matma\Php\Analysis;

use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;

final class PhpAstAnalyzer
{
    private readonly Parser $parser;

    public function __construct()
    {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
    }

    /**
     * @return array{declarations: array, imports: array, references: array, diagnostics: array}
     */
    public function analyze(string $path, string $sourceFile): array
    {
        $content = @file_get_contents($path);

        if ($content === false) {
            return $this->failed('file_unreadable', 'Unable to read PHP source file.', $sourceFile, 0);
        }

        try {
            $statements = $this->parser->parse($content) ?? [];
            $visitor = new DiscoveryVisitor();
            $traverser = new NodeTraverser();
            $traverser->addVisitor(new NameResolver());
            $traverser->addVisitor($visitor);
            $traverser->traverse($statements);

            return [
                'declarations' => $visitor->declarations(),
                'imports' => $visitor->imports(),
                'references' => $visitor->references(),
                'dynamic_references' => $visitor->dynamicReferences(),
                'diagnostics' => [],
            ];
        } catch (Error $error) {
            return $this->failed('parse_error', $error->getMessage(), $sourceFile, $error->getStartLine());
        }
    }

    private function failed(string $code, string $message, string $file, int $line): array
    {
        return [
            'declarations' => [],
            'imports' => [],
            'references' => [],
            'dynamic_references' => [],
            'diagnostics' => [[
                'severity' => 'error',
                'code' => $code,
                'source_file' => $file,
                'line' => $line,
                'message' => $message,
            ]],
        ];
    }
}
