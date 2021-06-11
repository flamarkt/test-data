<?php

namespace Flamarkt\TestData\Console;

use Faker\Factory;
use Flamarkt\Categories\Category;
use Flamarkt\Core\Order\Order;
use Flamarkt\Core\Order\OrderLine;
use Flamarkt\Core\Product\Product;
use Flamarkt\Library\FileRepository;
use Flamarkt\Taxonomies\Taxonomy;
use Flamarkt\Taxonomies\Term;
use Flarum\Extension\ExtensionManager;
use Flarum\User\User;
use Illuminate\Console\Command;
use Laminas\Diactoros\UploadedFile;

class SeedCommand extends Command
{
    protected $signature = 'flamarkt:seed {--category-count=50} {--taxonomy-count=10} {--product-count=50} {--order-count=50}';

    public function handle()
    {
        /**
         * @var ExtensionManager $manager
         */
        $manager = resolve(ExtensionManager::class);

        $faker = Factory::create();

        $categories = [];

        if ($manager->isEnabled('flamarkt-taxonomies')) {
            for ($i = 0; $i < $this->option('taxonomy-count'); $i++) {
                $category = new Category();
                $category->title = $faker->words($faker->numberBetween(1, 5), true);
                $category->save();

                $categories[] = $category;
            }

            $this->info('Categories seeded.');
        }

        $taxonomies = [];

        if ($manager->isEnabled('flamarkt-taxonomies')) {
            for ($i = 0; $i < $this->option('taxonomy-count'); $i++) {
                $taxonomy = new Taxonomy();
                $taxonomy->type = 'products';
                $taxonomy->slug = $faker->uuid;
                $taxonomy->name = $faker->words($faker->numberBetween(1, 3), true);
                $taxonomy->save();

                $terms = [];

                for ($j = 0; $j < $faker->numberBetween(2, 30); $j++) {
                    $term = new Term();
                    $term->slug = $faker->uuid;
                    $term->name = $faker->words($faker->numberBetween(1, 3), true);
                    $taxonomy->terms()->save($term);

                    $terms[] = $term->id;
                }

                $taxonomies[] = [
                    $taxonomy->id,
                    $terms,
                ];
            }

            $this->info('Taxonomies seeded.');
        }

        for ($i = 0; $i < $this->option('product-count'); $i++) {
            $product = new Product();
            $product->title = $faker->words($faker->numberBetween(1, 10), true);
            $product->description = $faker->optional(0.8)->paragraphs($faker->numberBetween(1, 20), true);
            $product->price = ($faker->numberBetween(1, 100) * 100) + $faker->randomElement([0, 0, 0, 20, 50, 80, 95]);
            $product->created_at = $faker->dateTimeThisDecade;
            $product->updated_at = $faker->optional()->dateTimeBetween($product->created_at);
            $product->hidden_at = $faker->dateTimeBetween($product->created_at);

            if ($manager->isEnabled('flamarkt-library')) {
                if ($faker->boolean(90)) {
                    $file = resolve(FileRepository::class)->store(new UploadedFile($faker->image(), 0, 0, 'image.png'), User::query()->firstOrFail());

                    $product->thumbnail()->associate($file);
                }
            }

            $product->save();

            if ($manager->isEnabled('flamarkt-taxonomies')) {
                foreach ($faker->randomElements($taxonomies, $faker->numberBetween(0, count($taxonomies) - 1)) as $data) {
                    $terms = $faker->randomElements($data[1], $faker->numberBetween(1, min(3, count($data[1]) - 1)));

                    $product->taxonomyTerms()->attach($terms);
                }
            }
        }

        $this->info('Products seeded.');

        for ($i = 0; $i < $this->option('order-count'); $i++) {
            $order = new Order();
            $order->user()->associate($this->randomUser());
            $order->price_total = $faker->numberBetween(1, 100) * 100;
            $order->created_at = $faker->dateTimeThisDecade;
            $order->save();

            for ($j = 0; $j < $faker->numberBetween(1, 20); $j++) {
                $line = new OrderLine();
                $line->group = 'products';
                $line->type = 'products';
                $line->product()->associate($this->randomProduct());
                $order->lines()->save($line);
            }
        }

        $this->info('Orders seeded.');
    }

    protected function randomUser(): User
    {
        return User::query()->inRandomOrder()->firstOrFail();
    }

    protected function randomProduct(): Product
    {
        return Product::query()->inRandomOrder()->firstOrFail();
    }
}
