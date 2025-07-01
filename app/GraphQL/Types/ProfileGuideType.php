<?php

namespace App\GraphQL\Types;

use Rebing\GraphQL\Support\Type as GraphQLType;
use GraphQL\Type\Definition\Type;
use Rebing\GraphQL\Support\Facades\GraphQL; // <-- Ajout de l'import
use App\Models\ProfileGuide;

class ProfileGuideType extends GraphQLType
{
    protected $attributes = [
        'name' => 'ProfileGuide',
        'description' => 'Profil Guide',
        'model' => ProfileGuide::class,
    ];

    public function fields(): array
    {
        return [
            'id' => [
                'type' => Type::nonNull(Type::int()),
                'description' => 'ID ProfileGuide',
            ],
            'experience' => [
                'type' => Type::string(),
                'description' => 'Expérience',
            ],
            'tarif' => [
                'type' => Type::string(),
                'description' => 'Tarif',
            ],
            'zone_travail' => [
                'type' => Type::string(),
                'description' => 'Zone de travail',
            ],
            'profile' => [
                'type' => GraphQL::type('Profile'), // <-- sans \
                'description' => 'Profil parent',
            ],
        ];
    }
}
