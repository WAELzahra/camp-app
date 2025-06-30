<?php

namespace App\GraphQL\Mutations;

use Rebing\GraphQL\Support\Mutation;
use GraphQL\Type\Definition\Type;
use Rebing\GraphQL\Support\Facades\GraphQL;
use App\Models\User;

class CreateUserMutation extends Mutation
{
    protected $attributes = [
        'name' => 'createUser',
        'description' => 'Créer un utilisateur',
    ];

    public function type(): Type
    {
        return GraphQL::type('User');
    }

    public function args(): array
    {
        return [
            'name' => [
                'type' => Type::nonNull(Type::string()),
                'description' => 'Nom de l’utilisateur',
            ],
            'email' => [
                'type' => Type::nonNull(Type::string()),
                'description' => 'Email de l’utilisateur',
            ],
            'adresse' => [
                'type' => Type::string(),
                'description' => 'Adresse',
            ],
            'phone_number' => [
                'type' => Type::string(),
                'description' => 'Numéro de téléphone',
            ],
            'password' => [
                'type' => Type::nonNull(Type::string()),
                'description' => 'Mot de passe',
            ],
            'role_id' => [
                'type' => Type::int(),
                'description' => 'ID du rôle',
            ],
            'is_active' => [
                'type' => Type::boolean(),
                'description' => 'Statut actif',
            ],
            'ville' => [
                'type' => Type::string(),
                'description' => 'Ville',
            ],
            'date_naissance' => [
                'type' => Type::string(), // tu peux aussi créer un type custom date si tu veux
                'description' => 'Date de naissance',
            ],
            'sexe' => [
                'type' => Type::string(),
                'description' => 'Sexe',
            ],
            'langue' => [
                'type' => Type::string(),
                'description' => 'Langue',
            ],
            'first_login' => [
                'type' => Type::boolean(),
                'description' => 'Premier login',
            ],
            'nombre_signalement' => [
                'type' => Type::int(),
                'description' => 'Nombre de signalements',
            ],
        ];
    }

    public function resolve($root, $args)
    {
        // Hash le mot de passe avant la création
        $args['password'] = bcrypt($args['password']);

        // Création de l'utilisateur avec les données fournies
        return User::create($args);
    }
}
