<?php

namespace App\Command;

use App\Entity\Category;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-demo',
    description: 'Seeds the database with demo categories and products for Patrick\'s Cold Cuts',
)]
class SeedDemoDataCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title("Seeding demo data");

        // Categories
        $categories = [
            'White Meat' => 'Chicken, turkey and other white meats',
            'Red Meat' => 'Beef and pork selections',
            'Seafood' => 'Frozen fish and shellfish',
            'Ice Cream' => 'Premium ice creams and frozen desserts',
            'Dumplings' => 'Assorted dumplings and dim sum',
        ];

        $categoryEntities = [];
        foreach ($categories as $name => $desc) {
            $existing = $this->em->getRepository(Category::class)->findOneBy(['name' => $name]);
            if ($existing) {
                $categoryEntities[$name] = $existing;
                continue;
            }
            $cat = (new Category())
                ->setName($name)
                ->setDescription($desc);
            $this->em->persist($cat);
            $categoryEntities[$name] = $cat;
        }

        // Products
        $products = [
            ['Premium Ice Cream', 'Ice Cream', 8.99, '+9 months'],
            ['Chicken Breast (1kg)', 'White Meat', 120.00, '+6 months'],
            ['Beef Sirloin (1kg)', 'Red Meat', 380.00, '+4 months'],
            ['Frozen Dumplings (30 pcs)', 'Dumplings', 199.00, '+5 months'],
            ['Salmon Fillet (500g)', 'Seafood', 320.00, '+3 months'],
        ];

        $created = 0;
        foreach ($products as [$name, $catName, $price, $expiryStr]) {
            $repo = $this->em->getRepository(Product::class);
            $exists = $repo->findOneBy(['name' => $name]);
            if ($exists) {
                continue;
            }
            $expiry = (new \DateTime())->modify($expiryStr);
            $product = (new Product())
                ->setName($name)
                ->setPrice((float)$price)
                ->setExpiryDate($expiry)
                ->setCategory($categoryEntities[$catName] ?? null);
            $this->em->persist($product);
            $created++;
        }

        $this->em->flush();

        $io->success(sprintf('Seeding complete. %d product(s) created.', $created));
        return Command::SUCCESS;
    }
}
