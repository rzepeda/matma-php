<?php

declare(strict_types=1);

namespace Matma\Php\Analysis;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\NodeVisitorAbstract;

final class DiscoveryVisitor extends NodeVisitorAbstract
{
    /** @var list<array{kind: string, class_name: ?string, start_line: int, symbol_id: string}> */
    private array $declarations = [];

    /** @var list<array{alias: string, class_name: string, kind: string}> */
    private array $imports = [];

    /** @var array<string, true> */
    private array $references = [];

    /** @var list<array{kind: string, line: int}> */
    private array $dynamicReferences = [];

    private int $anonymousOrdinal = 0;

    /** @var list<string> */
    private array $namespaceStack = [];

    private string $namespace = '';

    public function enterNode(Node $node): ?Node
    {
        if ($node instanceof Stmt\Namespace_) {
            $this->namespaceStack[] = $this->namespace;
            $this->namespace = $node->name?->toString() ?? '';
        }

        if ($node instanceof Stmt\Use_) {
            foreach ($node->uses as $use) {
                $this->addImport($use, '', $node->type);
            }
        } elseif ($node instanceof Stmt\GroupUse) {
            foreach ($node->uses as $use) {
                $this->addImport($use, $node->prefix->toString().'\\', $node->type);
            }
        }

        if ($node instanceof Stmt\Class_ || $node instanceof Stmt\Interface_
            || $node instanceof Stmt\Trait_ || $node instanceof Stmt\Enum_) {
            $kind = match (true) {
                $node instanceof Stmt\Interface_ => 'interface',
                $node instanceof Stmt\Trait_ => 'trait',
                $node instanceof Stmt\Enum_ => 'enum',
                $node instanceof Stmt\Class_ && $node->name === null => 'anonymous',
                $node instanceof Stmt\Class_ && $node->isAbstract() => 'abstract_class',
                default => 'class',
            };

            if ($kind === 'anonymous') {
                ++$this->anonymousOrdinal;
                $className = null;
                $symbolId = 'anonymous#'.$this->anonymousOrdinal;
            } else {
                $short = $node->name->toString();
                $className = $this->namespace === '' ? $short : $this->namespace.'\\'.$short;
                $symbolId = $className;
            }

            $this->declarations[] = [
                'kind' => $kind,
                'class_name' => $className,
                'start_line' => $node->getStartLine(),
                'symbol_id' => $symbolId,
                'is_final' => $node instanceof Stmt\Class_ && $node->isFinal(),
                'is_readonly' => $node instanceof Stmt\Class_ && $node->isReadonly(),
            ];
        }

        if ($node instanceof Stmt\Class_) {
            $this->addNames($node->extends);
            foreach ($node->implements as $name) {
                $this->addNames($name);
            }
        } elseif ($node instanceof Stmt\Interface_) {
            foreach ($node->extends as $name) {
                $this->addNames($name);
            }
        } elseif ($node instanceof Stmt\Enum_) {
            foreach ($node->implements as $name) {
                $this->addNames($name);
            }
        } elseif ($node instanceof Stmt\TraitUse) {
            foreach ($node->traits as $name) {
                $this->addNames($name);
            }
        } elseif ($node instanceof Expr\New_ || $node instanceof Expr\Instanceof_
            || $node instanceof Expr\StaticCall || $node instanceof Expr\StaticPropertyFetch
            || $node instanceof Expr\ClassConstFetch) {
            $this->addNames($node->class);
        } elseif ($node instanceof Node\Param || $node instanceof Stmt\Property) {
            $this->addNames($node->type);
        } elseif ($node instanceof Stmt\ClassMethod || $node instanceof Stmt\Function_
            || $node instanceof Expr\Closure || $node instanceof Expr\ArrowFunction) {
            $this->addNames($node->returnType);
        } elseif ($node instanceof Stmt\Catch_) {
            foreach ($node->types as $name) {
                $this->addNames($name);
            }
        } elseif ($node instanceof Node\Attribute) {
            $this->addNames($node->name);
        }

        if (($node instanceof Expr\New_
                || $node instanceof Expr\Instanceof_
                || $node instanceof Expr\StaticCall
                || $node instanceof Expr\StaticPropertyFetch
                || $node instanceof Expr\ClassConstFetch)
            && $node->class instanceof Expr) {
            $this->dynamicReferences[] = [
                'kind' => match (true) {
                    $node instanceof Expr\New_ => 'dynamic_new',
                    $node instanceof Expr\Instanceof_ => 'dynamic_instanceof',
                    default => 'dynamic_static_reference',
                },
                'line' => $node->getStartLine(),
            ];
        }

        return null;
    }

    public function leaveNode(Node $node): ?Node
    {
        if ($node instanceof Stmt\Namespace_) {
            $this->namespace = array_pop($this->namespaceStack) ?? '';
        }

        return null;
    }

    /** @return list<array{kind: string, class_name: ?string, start_line: int, symbol_id: string}> */
    public function declarations(): array
    {
        return $this->declarations;
    }

    /** @return list<array{alias: string, class_name: string, kind: string}> */
    public function imports(): array
    {
        usort($this->imports, static fn (array $a, array $b): int =>
            strcmp($a['class_name'], $b['class_name']) ?: strcmp($a['alias'], $b['alias']));

        return $this->imports;
    }

    /** @return list<string> */
    public function references(): array
    {
        $names = array_keys($this->references);
        sort($names, SORT_STRING);

        return $names;
    }

    /** @return list<array{kind: string, line: int}> */
    public function dynamicReferences(): array
    {
        return $this->dynamicReferences;
    }

    private function addImport(Stmt\UseUse $use, string $prefix, int $parentType): void
    {
        $type = $use->type === Stmt\Use_::TYPE_UNKNOWN ? $parentType : $use->type;
        $this->imports[] = [
            'alias' => $use->alias?->toString() ?? $use->name->getLast(),
            'class_name' => $prefix.$use->name->toString(),
            'kind' => match ($type) {
                Stmt\Use_::TYPE_FUNCTION => 'function',
                Stmt\Use_::TYPE_CONSTANT => 'constant',
                default => 'class',
            },
        ];
    }

    private function addNames(Node|string|null $node): void
    {
        if ($node instanceof Node\NullableType) {
            $this->addNames($node->type);
            return;
        }

        if ($node instanceof Node\UnionType || $node instanceof Node\IntersectionType) {
            foreach ($node->types as $type) {
                $this->addNames($type);
            }
            return;
        }

        if (! $node instanceof Name) {
            return;
        }

        $name = ltrim($node->toString(), '\\');
        if ($name !== '' && ! in_array(strtolower($name), ['self', 'static', 'parent'], true)) {
            $this->references[$name] = true;
        }
    }
}
