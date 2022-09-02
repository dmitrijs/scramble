<?php

namespace Dedoc\Scramble\Support\BuiltInExtensions;

use Dedoc\Scramble\Extensions\TypeToOpenApiSchemaExtension;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Dedoc\Scramble\Support\Generator\{Response, Schema, Types\ArrayType, Types\BooleanType, Types\IntegerType, Types\ObjectType as OpenApiObjectType, Types\StringType};
use Dedoc\Scramble\Support\Type\{Generic, ObjectType, Type};

class LengthAwarePaginatorOpenApi extends TypeToOpenApiSchemaExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof Generic
            && $type->type->name === LengthAwarePaginator::class
            && count($type->genericTypes) === 1
            && $type->genericTypes[0] instanceof ObjectType;
    }

    public function toResponse(Generic $type)
    {
        $collectingClassType = $type->genericTypes[0];

        if (! $collectingClassType->isInstanceOf(JsonResource::class)) {
            return null;
        }

        if (! ($collectingType = $this->openApiTransformer->transform($collectingClassType))) {
            return null;
        }

        $type = new OpenApiObjectType;
        $type->addProperty('data', (new ArrayType())->setItems($collectingType));
        $type->addProperty(
            'links',
            (new OpenApiObjectType)
                ->addProperty('first', (new StringType)->nullable(true))
                ->addProperty('last', (new StringType)->nullable(true))
                ->addProperty('prev', (new StringType)->nullable(true))
                ->addProperty('next', (new StringType)->nullable(true))
                ->setRequired(['first', 'last', 'prev', 'next'])
        );
        $type->addProperty(
            'meta',
            (new OpenApiObjectType)
                ->addProperty('current_page', new IntegerType)
                ->addProperty('from', (new IntegerType)->nullable(true))
                ->addProperty('last_page', new IntegerType)
                ->addProperty('links', (new ArrayType)->setItems(
                    (new OpenApiObjectType)
                        ->addProperty('url', (new StringType)->nullable(true))
                        ->addProperty('label', new StringType)
                        ->addProperty('active', new BooleanType)
                        ->setRequired(['url', 'label', 'active'])
                )->setDescription('Generated paginator links.'))
                ->addProperty('path', (new StringType)->nullable(true)->setDescription('Base path for paginator generated URLs.'))
                ->addProperty('per_page', (new IntegerType)->setDescription('Number of items shown per page.'))
                ->addProperty('to', (new IntegerType)->nullable(true)->setDescription('Number of the last item in the slice.'))
                ->addProperty('total', (new IntegerType)->setDescription('Total number of items being paginated.'))
                ->setRequired(['current_page', 'from', 'last_page', 'links', 'path', 'per_page', 'to', 'total'])
        );
        $type->setRequired(['data', 'links', 'meta']);

        return Response::make(200)
            ->description('Paginated set of `'.$this->components->uniqueSchemaName($collectingClassType->name).'`')
            ->setContent('application/json', Schema::fromType($type));
    }
}
