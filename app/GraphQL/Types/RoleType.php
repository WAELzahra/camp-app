<?php

namespace App\GraphQL\Types;

use Rebing\GraphQL\Support\Type as GraphQLType;
use GraphQL\Type\Definition\Type;
use Rebing\GraphQL\Support\Facades\GraphQL;
use App\Models\Role;

class RoleType extends GraphQLType
{
    protected $attributes = [
        'name' => 'Role',
        'description' => 'Type rôle utilisateur',
        'model' => Role::class,
    ];

    public function fields(): array
    {
        return [
            'id' => [
                'type' => Type::nonNull(Type::int()),
                'description' => 'ID du rôle',
            ],
            'name' => [
                'type' => Type::string(),
                'description' => 'nom de role',
            ],
            'description' => [
                'type' => Type::string(),
                'description' => 'Description du rôle',
            ],
            'icon' => [
                'type' => Type::string(),
                'description' => 'Icône du rôle',
            ],
            'users' => [
                'type' => Type::listOf(GraphQL::type('User')),
                'description' => 'Liste des utilisateurs avec ce rôle',
            ],
        ];
    }
}
