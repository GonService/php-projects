<?php

declare(strict_types=1);

namespace ProductsApi\Support;

use PDO;
use ProductsApi\Domain\CategoryRepository;
use ProductsApi\Domain\CategoryValidator;
use ProductsApi\Domain\ProductRepository;

/** Fictional catalog of a small Brazilian hardware and electrical supplies store. */
final class DemoSeeder
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function run(string $now): int
    {
        $categories = new CategoryRepository($this->pdo);
        $products = new ProductRepository($this->pdo);
        $catalog = [
            ['Ferramentas elétricas', 'Drills, saws and sanders for professionals.', [
                ['FUR-650W', 'Furadeira de impacto 650 W', 28990, 14],
                ['SER-CIR-1400', 'Serra circular 1400 W 7 1/4"', 54900, 6],
                ['LIX-ORB-220', 'Lixadeira orbital 220 W', 32950, 0],
            ]],
            ['Material elétrico', 'Cables, breakers and outlets.', [
                ['CAB-2.5-100', 'Cabo flexível 2,5 mm² rolo 100 m', 21990, 40],
                ['DISJ-20A', 'Disjuntor DIN 20 A curva C', 1890, 220],
                ['TOM-10A-BR', 'Tomada 2P+T 10 A branca', 1290, 310],
            ]],
            ['Iluminação', 'LED lamps and fixtures.', [
                ['LED-9W-6500', 'Lâmpada LED 9 W 6500 K', 990, 500],
                ['REF-LED-50W', 'Refletor LED 50 W IP66', 8990, 32],
            ]],
            ['Hidráulica', 'Pipes, fittings and valves.', [
                ['TUB-PVC-25', 'Tubo PVC soldável 25 mm 6 m', 2490, 75],
                ['REG-GAV-34', 'Registro de gaveta 3/4"', 4790, 18],
            ]],
        ];

        $count = 0;
        foreach ($catalog as [$name, $description, $items]) {
            $slug = CategoryValidator::slugify($name);
            if ($categories->slugTaken($slug, null)) {
                continue;
            }
            $categoryId = $categories->create(['name' => $name, 'slug' => $slug, 'description' => $description], $now);
            foreach ($items as [$sku, $productName, $price, $stock]) {
                $products->create([
                    'sku' => $sku, 'name' => $productName, 'description' => '', 'price_cents' => $price,
                    'stock' => $stock, 'active' => $sku !== 'LIX-ORB-220', 'category_id' => $categoryId,
                ], $now);
                ++$count;
            }
        }

        return $count;
    }
}
