<?php

namespace App\GraphQL\Queries;

use App\Models\User;
use Rebing\GraphQL\Support\Query;
use GraphQL\Type\Definition\Type;
use Rebing\GraphQL\Support\SelectFields;
use Closure;
use GraphQL\Type\Definition\ResolveInfo;

class UsersQuery extends Query
{
    protected $attributes = [
        'name' => 'users',
        'description' => 'Get list of users with profiles and related data'
    ];

    public function type(): Type
    {
        // Retourne une liste de type 'User' (défini dans UserType)
        return Type::listOf(\GraphQL::type('User'));
    }

    public function args(): array
    {
        return []; // Pas d'arguments nécessaires ici (on récupère tous les users)
    }

    public function resolve($root, array $args, $context, ResolveInfo $resolveInfo, Closure $getSelectFields)
    {
        /** @var SelectFields $fields */
        $fields = $getSelectFields();

        // Champs simples à sélectionner sur la table user
        $select = $fields->getSelect();

        // Relations demandées par la requête GraphQL (ex : profile, profile.profileGuide, etc.)
        $with = $fields->getRelations();

        // On récupère les users avec les champs et relations demandées
        return User::select($select)
            ->with($with)
            ->get();
    }
}
