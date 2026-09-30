<?php

namespace Database\Seeders;

use App\Models\BasketItem;
use App\Models\Buyer;
use App\Models\Category;
use App\Models\Farmer;
use App\Models\FarmerProduct;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo data for trying the app against a local backend: users for every role, products priced in IQD,
 * farmer stock for each quality, a basket and a few orders. Safe to run more than once.
 *
 *   php artisan db:seed --class=DemoDataSeeder
 *
 * Logins (phone / password): buyer 07508456346 / test1234, farmer 07518456346 / test1234,
 * second buyer 07508456347 / test1234, second farmer 07518456347 / test1234, admin admin@gmail.com / admin123.
 */
class DemoDataSeeder extends Seeder
{
    /**
     * title => [category, good, normal, bad (IQD per kg), description]
     */
    private const PRODUCTS = [
        'Tomato'       => ['Vegetable', 1500, 1250, 900,  'Fresh red tomatoes from Erbil farms.'],
        'Cucumber'     => ['Vegetable', 1250, 1000, 750,  'Crisp cucumbers, perfect for salads.'],
        'Potato'       => ['Vegetable', 1000, 800,  600,  'Local potatoes for cooking and frying.'],
        'Onion'        => ['Vegetable', 750,  600,  400,  'Dry yellow onions.'],
        'Eggplant'     => ['Vegetable', 1250, 1000, 750,  'Shiny purple eggplants.'],
        'Green Pepper' => ['Vegetable', 2000, 1750, 1250, 'Sweet green peppers.'],
        'Apple'        => ['Fruit',     2500, 2000, 1500, 'Crisp red apples from the mountains.'],
        'Orange'       => ['Fruit',     2000, 1750, 1250, 'Juicy oranges packed with vitamin C.'],
        'Grapes'       => ['Fruit',     3000, 2500, 1750, 'Sweet seedless grapes.'],
        'Pomegranate'  => ['Fruit',     3500, 3000, 2000, 'Halabja pomegranates.'],
        'Wheat'        => ['Grains',    900,  800,  650,  'Durum wheat, cleaned and dried.'],
        'Rice'         => ['Grains',    2750, 2500, 2000, 'Long grain rice.'],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $this->call([AdminSeeder::class, CategorySeeder::class, TestUsersSeeder::class]);

            $buyer = User::where('phone', '07508456346')->firstOrFail();
            $farmer = User::where('phone', '07518456346')->firstOrFail();
            $secondBuyer = $this->user('07508456347', 'buyer', ['first_name' => 'Sara', 'second_name' => 'Ahmed', 'address' => 'Gulan Street', 'city' => 'Erbil', 'business_name' => 'Sara Market']);
            $secondFarmer = $this->user('07518456347', 'farmer', ['first_name' => 'Karwan', 'second_name' => 'Omar', 'address' => 'Shaqlawa Road', 'city' => 'Erbil']);

            // The test users live in Erbil, next to the warehouse
            $buyer->buyer->update(['address' => '60m Street', 'city' => 'Erbil']);
            $farmer->farmer->update(['address' => 'Koya Road', 'city' => 'Erbil']);

            $products = [];
            $index = 0;
            foreach (self::PRODUCTS as $title => [$category, $good, $normal, $bad, $info]) {
                $products[$title] = Product::updateOrCreate(['title' => $title], [
                    'price_good'   => $good,
                    'price_normal' => $normal,
                    'price_bad'    => $bad,
                    'information'  => $info,
                    'category_id'  => Category::where('name', $category)->value('id'),
                    'status'       => 'active',
                    'quantity'     => 0,
                    'total_quantity' => 0,
                ]);

                // Two farmers, a mix of qualities; every product has some good stock
                $this->stock($farmer, $products[$title], 3, 150 + 25 * $index);
                $this->stock($farmer, $products[$title], 2, 80 + 10 * $index);
                $this->stock($secondFarmer, $products[$title], $index % 2 ? 1 : 3, 60 + 5 * $index);
                $index++;
            }

            // Kept in sync with farmer stock, like the farmer product endpoints do
            foreach ($products as $product) {
                $product->forceFill([
                    'total_quantity' => FarmerProduct::where('product_id', $product->id)->sum('quantity'),
                ])->saveQuietly();
            }

            // A basket ready to check out
            foreach ([['Tomato', 3, 20], ['Apple', 2, 10], ['Rice', 3, 25]] as [$title, $quality, $kg]) {
                BasketItem::updateOrCreate(
                    ['buyer_id' => $buyer->id, 'product_id' => $products[$title]->id, 'quality' => $quality],
                    ['quantity' => $kg],
                );
            }

            // Past orders in several statuses, only created on the first run
            if (! Order::where('buyer_id', $buyer->id)->exists()) {
                $this->order($buyer, [['Potato', 3, 50], ['Onion', 2, 30]], 'completed', now()->subDays(9), [$farmer]);
                $this->order($buyer, [['Cucumber', 3, 15]], 'assigned', now()->subDays(2), [$farmer, $secondFarmer]);
                $this->order($buyer, [['Orange', 3, 12]], 'confirmed', now()->subHours(3));
                $this->order($buyer, [['Grapes', 2, 8]], 'cancelled', now()->subDay());
                $this->order($secondBuyer, [['Wheat', 3, 100]], 'accepted', now()->subDays(4), [$secondFarmer]);
            }
        });
    }

    private function user(string $phone, string $type, array $profile): User
    {
        $user = User::updateOrCreate(['phone' => $phone], [
            'password'  => 'test1234',
            'user_type' => $type,
            'status'    => 'approved',
        ]);

        ($type === 'buyer' ? Buyer::class : Farmer::class)::updateOrCreate(['user_id' => $user->id], $profile);

        return $user;
    }

    private function stock(User $farmer, Product $product, int $quality, float $kg): void
    {
        FarmerProduct::updateOrCreate(
            ['farmer_id' => $farmer->farmer->id, 'product_id' => $product->id, 'rating' => $quality],
            ['quantity' => $kg],
        );
    }

    /**
     * @param  array<int, array{0: string, 1: int, 2: int}>  $items  [title, quality, kg]
     * @param  array<int, User>  $farmers
     */
    private function order(User $buyer, array $items, string $status, $placedAt, array $farmers = []): void
    {
        $lines = collect($items)->map(function ($item) {
            [$title, $quality, $kg] = $item;
            $product = Product::where('title', $title)->firstOrFail();

            return ['product' => $product, 'quality' => $quality, 'quantity' => $kg, 'price' => (float) $product->priceFor($quality)];
        });

        $fee = 2000; // the 0-10 km tier
        $cancelled = $status === 'cancelled';

        $order = new Order([
            'buyer_id'              => $buyer->id,
            'total_amount'          => $lines->sum(fn ($l) => $l['price'] * $l['quantity']) + $fee,
            'status'                => $status,
            'address'               => $buyer->profile()->address,
            'city'                  => $buyer->profile()->city,
            'phone'                 => $buyer->phone,
            'cancel_reason'         => $cancelled ? 'Ordered the wrong quality' : null,
            'delivery_method'       => 'delivery',
            'delivery_fee'          => $fee,
            'latitude'              => 36.2050,
            'longitude'             => 44.0300,
            'distance_km'           => 2.3,
            'cancel_window_seconds' => 20,
            'confirmed_at'          => $cancelled ? null : $placedAt->copy()->addSeconds(21),
            'cancelled_at'          => $cancelled ? $placedAt->copy()->addSeconds(8) : null,
        ]);
        $order->created_at = $placedAt;
        $order->updated_at = $placedAt;
        $order->save();

        foreach ($lines as $line) {
            OrderItem::create([
                'order_id'   => $order->id,
                'product_id' => $line['product']->id,
                'quality'    => $line['quality'],
                'quantity'   => $line['quantity'],
                'price'      => $line['price'],
            ]);
        }

        $pivotStatus = in_array($status, ['accepted', 'completed']) ? 'accepted' : 'pending';
        foreach ($farmers as $farmer) {
            $order->farmers()->attach($farmer->id, ['status' => $pivotStatus]);
        }
    }
}
