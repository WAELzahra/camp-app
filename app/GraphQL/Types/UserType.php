<?php

namespace App\GraphQL\Types;

use Rebing\GraphQL\Support\Type as GraphQLType;
use GraphQL\Type\Definition\Type;
use Rebing\GraphQL\Support\Facades\GraphQL; // <-- Ajout import
use App\Models\User;

class UserType extends GraphQLType
{
    protected $attributes = [
        'name' => 'User',
        'description' => 'Utilisateur',
        'model' => User::class,
    ];

    public function fields(): array
    {
        return [
            'id' => [
                'type' => Type::nonNull(Type::int()),
                'description' => 'ID utilisateur',
            ],
            'name' => [
                'type' => Type::string(),
                'description' => 'Nom',
            ],
            'adresse' => [
                'type' => Type::string(),
                'description' => 'Adresse',
            ],
            'phone_number' => [
                'type' => Type::string(),
                'description' => 'Numéro de téléphone',
            ],

            'email' => [
                'type' => Type::string(),
                'description' => 'Email',
            ],
            'role' => [
                'type' => GraphQL::type('Role'), // <-- sans \
                'description' => 'Rôle de l\'utilisateur',
            ],
            'profile' => [
                'type' => GraphQL::type('Profile'), // <-- sans \
                'description' => 'Profil utilisateur',
            ],
        ];
    }
}
