<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
 
    public function run(): void
    {
        $taxonomy = [
            'Hair' => [
                'description' => 'Haircuts, styling, and hair treatments',
                'children' => [
                    'Haircuts & Styling'  => 'Cuts, blowouts, and styling for all hair types',
                    'Braiding & Weaving'  => 'Cornrows, box braids, weaves, and protective styles',
                    'Hair Coloring'       => 'Dyeing, highlights, balayage, and color correction',
                    'Hair Treatments'     => 'Deep conditioning, keratin, relaxing, and repair treatments',
                    'Wigs & Extensions'   => 'Wig fitting, installation, and extension services',
                ],
            ],
            'Nails' => [
                'description' => 'Manicures, pedicures, and nail art',
                'children' => [
                    'Manicure'            => 'Classic and spa manicures',
                    'Pedicure'            => 'Classic and spa pedicures',
                    'Gel & Acrylic Nails' => 'Gel polish, acrylic extensions, and overlays',
                    'Nail Art'            => 'Custom nail art and designs',
                ],
            ],
            'Skincare & Facials' => [
                'description' => 'Facials, skin treatments, and waxing',
                'children' => [
                    'Facials'         => 'Deep cleansing, hydrating, and rejuvenating facials',
                    'Skin Treatments' => 'Acne treatment, chemical peels, and microdermabrasion',
                    'Waxing'          => 'Full body, facial, and bikini waxing',
                ],
            ],
            'Makeup' => [
                'description' => 'Makeup application for any occasion',
                'children' => [
                    'Makeup Application' => 'Everyday, event, and photoshoot makeup',
                    'Bridal Makeup'      => 'Bridal trials and wedding-day makeup',
                ],
            ],
            'Lashes & Brows' => [
                'description' => 'Eyelash and eyebrow services',
                'children' => [
                    'Eyelash Extensions'         => 'Classic, hybrid, and volume lash extensions',
                    'Eyebrow Shaping & Tinting'  => 'Threading, waxing, microblading, and tinting',
                ],
            ],
            'Spa & Massage' => [
                'description' => 'Massage therapy and spa packages',
                'children' => [
                    'Full Body Massage' => 'Swedish, deep tissue, and hot stone massage',
                    'Spa Packages'      => 'Combined multi-service spa experiences',
                ],
            ],
            'Barbershop & Men\'s Grooming' => [
                'description' => 'Grooming services for men',
                'children' => [
                    'Haircuts (Men)'  => 'Fades, trims, and classic cuts',
                    'Beard Grooming'  => 'Beard trims, shaping, and hot towel shaves',
                ],
            ],
            'Beauty Products' => [
                'description' => 'Products customers purchase rather than book',
                'children' => [
                    'Hair Care Products'    => 'Shampoos, conditioners, oils, and styling products',
                    'Skincare Products'     => 'Cleansers, moisturizers, and serums',
                    'Makeup & Cosmetics'    => 'Foundations, lipsticks, and makeup tools',
                    'Nail Care Products'    => 'Polishes, nail tools, and treatments',
                    'Fragrances'            => 'Perfumes and body sprays',
                ],
            ],
        ];

        $sortOrder = 1;

        foreach ($taxonomy as $parentName => $parentData) {
            $parent = Category::updateOrCreate(
                ['slug' => str($parentName)->slug()],
                [
                    'name'        => $parentName,
                    'description' => $parentData['description'],
                    'icon'        => null,
                    'parent_id'   => null,
                    'sort_order'  => $sortOrder++,
                    'is_active'   => true,
                ]
            );

            $childSortOrder = 1;

            foreach ($parentData['children'] as $childName => $childDescription) {
                Category::updateOrCreate(
                    ['slug' => str($parentName . '-' . $childName)->slug()],
                    [
                        'name'        => $childName,
                        'description' => $childDescription,
                        'icon'        => null,
                        'parent_id'   => $parent->id,
                        'sort_order'  => $childSortOrder++,
                        'is_active'   => true,
                    ]
                );
            }
        }
    }
}