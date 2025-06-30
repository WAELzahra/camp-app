<?php

namespace App\GraphQL\Types;

use Rebing\GraphQL\Support\Type as GraphQLType;
use GraphQL\Type\Definition\Type;
use Rebing\GraphQL\Support\Facades\GraphQL;  // <-- ajout de l'import
use App\Models\ProfileFournisseur;

class ProfileFournisseurType extends GraphQLType
{
    protected $attributes = [
        'name' => 'ProfileFournisseur',
        'description' => 'Profil Fournisseur',
        'model' => ProfileFournisseur::class,
    ];

    public function fields(): array
    {
        return [
            'id' => [
                'type' => Type::nonNull(Type::int()),
                'description' => 'ID ProfileFournisseur',
            ],
            'intervale_prix' => [
                'type' => Type::string(),
                'description' => 'Intervalle de prix',
            ],
            'product_category' => [
                'type' => Type::string(),
                'description' => 'Catégorie produit',
            ],
            'profile' => [
                'type' => GraphQL::type('Profile'), // <-- sans backslash et avec façade importée
                'description' => 'Profil parent',
            ],
        ];
    }
}
