<?php

namespace Flamarkt\TestData\Console;

use Faker\Factory;
use Faker\Generator;
use Flamarkt\Categories\Category;
use Flamarkt\Core\Order\Order;
use Flamarkt\Core\Order\OrderLine;
use Flamarkt\Core\Product\Product;
use Flamarkt\Library\FileRepository;
use Flamarkt\Taxonomies\Taxonomy;
use Flamarkt\Taxonomies\Term;
use Flamarkt\TestData\Event\AfterSeed;
use Flamarkt\TestData\Event\BeforeSeed;
use Flamarkt\TestData\Event\ModelSeed;
use Flamarkt\TestData\Event\BeforeReset;
use Flarum\Extension\ExtensionManager;
use Flarum\User\User;
use Illuminate\Console\Command;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use Laminas\Diactoros\UploadedFile;

class SeedCommand extends Command
{
    protected $signature = 'flamarkt:seed {--reset} {--user-count=100} {--category-count=50} {--taxonomy-count=10} {--product-count=50} {--order-count=100}' .
    ' {--min-product-categories=0} {--max-product-categories=3} {--min-product-taxonomies=0} {--max-product-taxonomies=10} {--min-product-terms=1} {--max-product-terms=3}';

    public $events;
    public $db;

    public function __construct(Dispatcher $events, Connection $db)
    {
        parent::__construct();

        $this->events = $events;
        $this->db = $db;
    }

    public function handle()
    {
        /**
         * @var ExtensionManager $manager
         */
        $manager = resolve(ExtensionManager::class);

        if ($this->option('reset')) {
            $this->db->getSchemaBuilder()->disableForeignKeyConstraints();

            $this->events->dispatch(new BeforeReset($this));

            if ($manager->isEnabled('flamarkt-taxonomies')) {
                $this->db->table('flamarkt_category_product')->truncate();
                $this->db->table('flamarkt_categories')->truncate();
            }

            if ($manager->isEnabled('flamarkt-taxonomies')) {
                $this->db->table('flamarkt_product_taxonomy_term')->truncate();
                $this->db->table('flamarkt_taxonomy_term_user')->truncate();
                $this->db->table('flamarkt_discussion_taxonomy_term')->truncate();
                $this->db->table('flamarkt_taxonomy_terms')->truncate();
                $this->db->table('flamarkt_taxonomies')->truncate();
            }

            if ($manager->isEnabled('flamarkt-library')) {
                $this->db->table('flamarkt_file_product')->truncate();
                $this->db->table('flamarkt_files')->truncate();
            }

            $this->db->table('flamarkt_order_lines')->truncate();
            $this->db->table('flamarkt_orders')->truncate();
            $this->db->table('flamarkt_products')->truncate();

            $this->db->table('group_user')->truncate();
            $this->db->table('users')->truncate();

            $this->db->getSchemaBuilder()->enableForeignKeyConstraints();

            $this->info('Tables truncated.');
        }

        $faker = Factory::create();

        $this->events->dispatch(new BeforeSeed($this, $faker));

        for ($i = 0; $i < $this->option('user-count'); $i++) {
            $user = new User();
            $user->username = $faker->uuid;
            $user->email = $faker->unique()->email;
            $user->is_email_confirmed = $faker->boolean(90);

            if ($manager->isEnabled('flamarkt-identity')) {
                $user->firstname = $faker->firstName;
                $user->lastname = $faker->lastName;
                $user->birthday = $faker->optional()->date();

                if ($faker->boolean(80)) {
                    $user->address_street = $faker->streetName;
                    $user->address_number = $faker->buildingNumber;
                    $user->address_city = $faker->city;
                    $user->address_zip = $faker->postcode;
                    $user->address_state = $faker->optional()->state;
                    $user->address_country = $faker->country;
                }
            }

            if ($manager->isEnabled('flamarkt-balance')) {
                $user->flamarkt_balance = $faker->optional()->numberBetween(1, 5000) ?? 0;
            }

            $this->events->dispatch(new ModelSeed($this, $faker, $user));

            $user->save();
        }

        $this->info('Users seeded.');

        $categories = [];

        if ($manager->isEnabled('flamarkt-categories')) {
            for ($i = 0; $i < $this->option('category-count'); $i++) {
                $category = new Category();
                $category->title = $faker->words($faker->numberBetween(1, 5), true);

                $this->events->dispatch(new ModelSeed($this, $faker, $category));

                $category->save();

                $categories[] = $category->id;
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

                $this->events->dispatch(new ModelSeed($this, $faker, $taxonomy));

                $taxonomy->save();

                $terms = [];

                for ($j = 0; $j < $faker->numberBetween(2, 30); $j++) {
                    $term = new Term();
                    $term->slug = $faker->uuid;
                    $term->name = $faker->words($faker->numberBetween(1, 3), true);

                    $this->events->dispatch(new ModelSeed($this, $faker, $term));

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
            $product->hidden_at = $faker->optional(5)->dateTimeBetween($product->created_at);

            if ($manager->isEnabled('flamarkt-product-slugs')) {
                $product->slug = $faker->unique()->slug;
            }

            if ($manager->isEnabled('flamarkt-library')) {
                if ($faker->boolean(90)) {
                    $file = resolve(FileRepository::class)->store(new UploadedFile($faker->image(), 0, 0, 'image.png'), User::query()->firstOrFail());

                    $product->thumbnail()->associate($file);
                }
            }

            $this->events->dispatch(new ModelSeed($this, $faker, $product));

            $product->save();

            if ($manager->isEnabled('flamarkt-taxonomies')) {
                foreach ($this->randomElements($faker, 'product-taxonomies', $taxonomies) as $data) {
                    $terms = $this->randomElements($faker, 'product-terms', $data[1]);

                    $product->taxonomyTerms()->attach($terms);
                }
            }

            if ($manager->isEnabled('flamarkt-categories')) {
                $categoriesForProduct = $this->randomElements($faker, 'product-categories', $categories);

                $product->categories()->attach($categoriesForProduct);
            }
        }

        $this->info('Products seeded.');

        $finalQuantitiesEnabled = $manager->isEnabled('flamarkt-final-quantities');

        for ($i = 0; $i < $this->option('order-count'); $i++) {
            $order = new Order();
            $order->user()->associate($this->randomUser());
            $order->price_total = $faker->numberBetween(1, 100) * 100;
            $order->created_at = $faker->dateTimeThisDecade;

            $this->events->dispatch(new ModelSeed($this, $faker, $order));

            $order->save();

            $number = 0;

            for ($j = 0; $j < $faker->numberBetween(1, 20); $j++) {
                $product = $this->randomProduct();

                $line = new OrderLine();
                $line->number = ++$number;
                $line->group = null;
                $line->type = 'product';
                $line->product()->associate($product);
                $line->price_unit = $product->price;
                $line->quantity = $faker->numberBetween($finalQuantitiesEnabled ? 0 : 1, 10);
                $line->updateTotal();

                if ($finalQuantitiesEnabled) {
                    $originalDiff = $faker->optional(30)->numberBetween(-3, 3) ?? 0;

                    $line->original_quantity = max(0, $line->quantity + $originalDiff);

                    $line->is_final = $faker->boolean(70);
                }

                $this->events->dispatch(new ModelSeed($this, $faker, $line));

                $order->lines()->save($line);
            }

            $order->updateMeta()->save();
        }

        $this->info('Orders seeded.');

        $this->events->dispatch(new AfterSeed($this, $faker));
    }

    public function randomElements(Generator $faker, string $optionName, array $elements): array
    {
        // Number between 0 and $max, but can be customized with command options
        $max = min($this->option('max-' . $optionName), count($elements) - 1);
        $min = max($this->option('min-' . $optionName), $max);

        return $faker->randomElements($elements, $faker->numberBetween($min, $max));
    }

    public function randomUser(): User
    {
        return User::query()->inRandomOrder()->firstOrFail();
    }

    public function randomProduct(): Product
    {
        return Product::query()->inRandomOrder()->firstOrFail();
    }
}
