<?php

namespace App\GraphQL\Types;

use Rebing\GraphQL\Support\Type as GraphQLType;
use GraphQL\Type\Definition\Type;
use Rebing\GraphQL\Support\Facades\GraphQL; // <-- ajout import
use App\Models\ProfileGroupe;

class ProfileGroupeType extends GraphQLType
{
    protected $attributes = [
        'name' => 'ProfileGroupe',
        'description' => 'Profil Groupe',
        'model' => ProfileGroupe::class,
    ];

    public function fields(): array
    {
        return [
            'id' => [
                'type' => Type::nonNull(Type::int()),
                'description' => 'ID ProfileGroupe',
            ],
            'nom_groupe' => [
                'type' => Type::string(),
                'description' => 'Nom du groupe',
            ],
            'id_album_photo' => [
                'type' => Type::int(),
                'description' => 'ID album photo',
            ],
            'id_annonce' => [
                'type' => Type::int(),
                'description' => 'ID annonce',
            ],
            'cin_responsable' => [
                'type' => Type::string(),
                'description' => 'CIN du responsable',
            ],
            'profile' => [
                'type' => GraphQL::type('Profile'), // <-- sans anti-slash
                'description' => 'Profil parent',
            ],
        ];
    }
}
