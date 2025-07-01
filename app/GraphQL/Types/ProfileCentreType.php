<?php

namespace App\GraphQL\Types;

use Rebing\GraphQL\Support\Type as GraphQLType;
use GraphQL\Type\Definition\Type;
use Rebing\GraphQL\Support\Facades\GraphQL;  // <-- ajout de l'import
use App\Models\ProfileCentre;

class ProfileCentreType extends GraphQLType
{
    protected $attributes = [
        'name' => 'ProfileCentre',
        'description' => 'Profil Centre',
        'model' => ProfileCentre::class,
    ];

    public function fields(): array
    {
        return [
            'id' => [
                'type' => Type::nonNull(Type::int()),
                'description' => 'ID ProfileCentre',
            ],
            'capacite' => [
                'type' => Type::int(),
                'description' => 'Capacité',
            ],
            'services_offerts' => [
                'type' => Type::string(),
                'description' => 'Services offerts',
            ],
            'document_legal' => [
                'type' => Type::string(),
                'description' => 'Document légal',
            ],
            'disponibilite' => [
                'type' => Type::string(),
                'description' => 'Disponibilité',
            ],
            'id_annonce' => [
                'type' => Type::int(),
                'description' => 'ID annonce',
            ],
            'id_album_photo' => [
                'type' => Type::int(),
                'description' => 'ID album photo',
            ],
            'profile' => [
                'type' => GraphQL::type('Profile'),  // <-- sans le backslash et avec façade importée
                'description' => 'Profil parent',
            ],
        ];
    }
}
