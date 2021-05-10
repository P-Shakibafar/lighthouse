<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Execution\DataLoader\RelationAggregateLoader;
use Nuwave\Lighthouse\Execution\DataLoader\RelationAvgLoader;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\RootType;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class WithAvgDirective extends WithRelationAggregateDirective implements FieldMiddleware, FieldManipulator
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Eager-load the avg of an Eloquent relation if the field is queried.

Note that this does not return a value for the field, the count is simply
prefetched, assuming it is used to compute the field value. Use `@count`
if the field should simply return the relation count.
"""
directive @withAvg(
  """
  Specify the relationship method name in the model class.
  """
  relation: String!

  """
  Specify the relationship column name in the model class.
  """
  column: String!

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]
) repeatable on FIELD_DEFINITION
GRAPHQL;
    }

    public function manipulateFieldDefinition(DocumentAST &$documentAST, FieldDefinitionNode &$fieldDefinition, ObjectTypeDefinitionNode &$parentType)
    {
        if (RootType::isRootType($parentType->name->value)) {
            throw new DefinitionException("Can not use @{$this->name()} on fields of a root type.");
        }

        $relation = $this->directiveArgValue('relation');
        if (! is_string($relation)) {
            throw new DefinitionException("You must specify the argument relation in the {$this->name()} directive on {$this->definitionNode->name->value}.");
        }
    }

    protected function relationName(): string
    {
        /**
         * We validated the argument during schema manipulation.
         *
         * @var string $relation
         */
        $relation = $this->directiveArgValue('relation');

        return $relation;
    }

    protected function relationColumn(): string
    {
        /**
         * @var string $column
         */
        $column = $this->directiveArgValue('column');

        return $column;
    }

    /**
     * @return RelationAvgLoader
     */
    protected function relationAggregateLoader(ResolveInfo $resolveInfo): RelationAggregateLoader
    {
        return new RelationAvgLoader(
            $this->decorateBuilder($resolveInfo)
        );
    }
}