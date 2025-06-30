<?php

namespace App\GraphQL\Types;
use Rebing\GraphQL\Support\Facades\GraphQL;


use GraphQL\Type\Definition\Type;
use Rebing\GraphQL\Support\Type as GraphQLType;
use App\Models\Profile;

class ProfileType extends GraphQLType
{
    protected $attributes = [
        'name' => 'Profile',  // ça doit correspondre à la clé dans config/graphql.php
        'description' => 'Profil utilisateur',
        'model' => Profile::class,
    ];

    public function fields(): array
    {
        return [
            'id' => [
                'type' => Type::nonNull(Type::int()),
                'description' => 'ID du profil',
            ],
            'bio' => [
                'type' => Type::string(),
                'description' => 'Biographie',
            ],
            'cover_image' => [
                'type' => Type::string(),
                'description' => 'Image de couverture',
            ],
            'immatricule' => [
                'type' => Type::string(),
                'description' => 'Immatricule',
            ],
            'type' => [
                'type' => Type::string(),
                'description' => 'Type de profil',
            ],
            'profileGuide' => [
                'type' => GraphQL::type('ProfileGuide'),  
                'description' => 'Profil Guide',
            ],
            'profileCentre' => [
                'type' => GraphQL::type('ProfileCentre'),
                'description' => 'Profil Centre',
            ],
            'profileGroupe' => [
                'type' => GraphQL::type('ProfileGroupe'),
                'description' => 'Profil Groupe',
            ],
            'profileFournisseur' => [
                'type' => GraphQL::type('ProfileFournisseur'),
                'description' => 'Profil Fournisseur',
            ],
        ];
    }
}
