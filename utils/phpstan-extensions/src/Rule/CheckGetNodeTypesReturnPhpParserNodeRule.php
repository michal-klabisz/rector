<?php

declare(strict_types=1);

namespace Rector\PHPStanExtensions\Rule;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\ShouldNotHappenException;

/**
 * @see \Rector\PHPStanExtensions\Tests\Rule\CheckGetNodeTypesReturnPhpParserNodeRule\CheckGetNodeTypesReturnPhpParserNodeRuleTest
 */
final class CheckGetNodeTypesReturnPhpParserNodeRule implements Rule
{
    /**
     * @var string
     */
    public const ERROR = "%s::getNodeTypes() returns class names that are not instances of %s:\n%s";

    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    /**
     * @param ClassMethod $node
     * @return string[]
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if ($node->name->toString() !== 'getNodeTypes') {
            return [];
        }

        if ($this->shouldSkip($node, $scope)) {
            return [];
        }

        $incorrectClassNames = $this->getIncorrectClassNames($node);
        if ($incorrectClassNames === null) {
            return [];
        }

        return [sprintf(
            self::ERROR,
            $scope->getClassReflection()
                ->getName(),
            Node::class,
            implode(",\n", $incorrectClassNames)
        )];
    }

    private function shouldSkip(ClassMethod $classMethod, Scope $scope): bool
    {
        if ($scope->getClassReflection() === null) {
            return true;
        }

        if ($scope->getClassReflection()->isInterface()) {
            return true;
        }

        $nodeFinder = new NodeFinder();
        /** @var Return_[] $returns */
        $returns = $nodeFinder->findInstanceOf($classMethod, Return_::class);

        if (count($returns) === 1) {
            return false;
        }

        throw new ShouldNotHappenException('More than one return statement exist');
    }

    /**
     * @return string[]|null
     */
    private function getIncorrectClassNames(ClassMethod $classMethod): ?array
    {
        $nodeFinder = new NodeFinder();
        /** @var Return_|null $return */
        $return = $nodeFinder->findFirstInstanceOf($classMethod, Return_::class);
        if ($return === null) {
            return null;
        }

        /** @var Array_|null $arrayNode */
        $arrayNode = $return->expr;
        if ($arrayNode === null) {
            return null;
        }

        $incorrectClassNames = [];
        foreach ($arrayNode->items as $item) {
            /** @var FullyQualified $class */
            $class = $item->value->class;
            if (! is_a($class->toString(), Node::class, true)) {
                $incorrectClassNames[] = $class->toString();
            }
        }

        if ($incorrectClassNames === []) {
            return null;
        }

        return $incorrectClassNames;
    }
}
