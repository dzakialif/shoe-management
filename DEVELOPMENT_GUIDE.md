# 🚀 Development Guide: Shoe Management

Panduan bertahap membangun aplikasi **Shoe Management** (Brand, Category, Shoe) menggunakan **Laravel 13** dengan pattern **Model → Service → Controller** (diadaptasi dari project `virtue-erm`).

> **Cara pakai**: Kerjakan fase satu per satu secara berurutan. Setiap fase punya checklist verifikasi. Jangan skip verifikasi — fondasi yang benar mencegah error di fase berikutnya. Kalau stuck, tanyakan pada mentor/agent.

---

## 📋 Ringkasan Project

| Aspek | Keputusan |
|---|---|
| Framework | Laravel 13.25 (PHP ^8.3) |
| Database | SQLite (local) / MySQL (production) |
| Tabel | `brands`, `categories`, `shoes` + `users` (default) |
| Primary Key | **ULID** (string, seperti virtue-erm) |
| CRUD | **Penuh** untuk Brand, Category, Shoe |
| Service Layer | `BaseService` (abstract) + 1 service per modul |
| API Auth | **Ditunda** — fokus CRUD dulu, auth menyusul |
| Activity Log | `spatie/laravel-activitylog` (per model) |
| RBAC | `spatie/laravel-permission` — **tidak dipasang sekarang** |

### Relasi Antar Tabel

```
brands ──1──────*── shoes ──*──────1── categories
```

- `Brand hasMany Shoe`
- `Category hasMany Shoe`
- `Shoe belongsTo Brand`
- `Shoe belongsTo Category`

### Struktur Direktori Target

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Api/
│   │   │   ├── BrandController.php
│   │   │   ├── CategoryController.php
│   │   │   └── ShoeController.php
│   │   ├── BrandController.php      (web)
│   │   ├── CategoryController.php   (web)
│   │   └── ShoeController.php       (web)
│   ├── Requests/
│   │   ├── ApiRequest.php            (base class — extends FormRequest)
│   │   ├── StoreBrandRequest.php
│   │   ├── UpdateBrandRequest.php
│   │   ├── StoreCategoryRequest.php
│   │   ├── UpdateCategoryRequest.php
│   │   ├── StoreShoeRequest.php
│   │   └── UpdateShoeRequest.php
│   └── Resources/
│       ├── BrandResource.php
│       ├── CategoryResource.php
│       └── ShoeResource.php
├── Models/
│   ├── User.php      (sudah ada + HasApiTokens)
│   ├── Brand.php
│   ├── Category.php
│   └── Shoe.php
└── Services/
    ├── BaseService.php
    ├── BrandService.php
    ├── CategoryService.php
    └── ShoeService.php

routes/
├── api.php      (baru — perlu dibuat manual)
└── web.php

database/
├── migrations/  (brands, categories, shoes + sanctum/activitylog)
└── seeders/     (BrandSeeder, CategorySeeder, ShoeSeeder)
```

---

## FASE 1 — Foundation Setup

### Step 1.1 — Install Sanctum & Spatie Activitylog

Jalankan dari root project `shoe-management/`:

```bash
composer require laravel/sanctum
composer require spatie/laravel-activitylog
```

**Kenapa?**
- **Sanctum** → menyediakan autentikasi token untuk `routes/api.php` (dipakai saat implementasi auth nanti).
- **Activitylog** → memberikan trait `LogsActivity` yang otomatis mencatat aktivitas model ke tabel `activity_log`.

### Step 1.2 — Publish package config & migrations

```bash
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider"
```

**Cek hasil**:
- Muncul `config/sanctum.php` dan `config/activitylog.php`
- Migration baru: `xxxx_create_personal_access_tokens_table.php` dan `xxxx_create_activity_log_table.php`

Verifikasi: `ls database/migrations/` — harus bertambah 2 file.

### Step 1.3 — Buat file `routes/api.php`

> **PENTING**: File `routes/api.php` **tidak ada** di Laravel 13 skeleton (beda dengan Laravel 12). Harus dibuat manual.

Buat file baru `routes/api.php`:

```php
<?php

use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return response()->json(['message' => 'pong']);
});
```

Ini hanya probe — routes asli diisi pada Fase 5.

### Step 1.4 — Daftarkan `api.php` di `bootstrap/app.php`

Edit `bootstrap/app.php`:

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',           // ← TAMBAHKAN
        apiPrefix: 'api',                             // ← TAMBAHKAN (opsional)
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();                   // ← TAMBAHKAN
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
```

**Penjelasan**:
- `api:` → memberitahu Laravel file `routes/api.php` harus di-load.
- `apiPrefix: 'api'` → semua route di `api.php` otomatis ber-prefix `/api`.
- `statefulApi()` → mengaktifkan cookie-based session auth untuk SPA (bagian dari Sanctum). Aman dipasang sejak sekarang.
- Blok `shouldRenderJsonWhen` sudah ada di default — memastikan request `api/*` mengembalikan JSON error, bukan HTML.

### Step 1.5 — Test koneksi

```bash
php artisan route:list
```

Harus muncul:

```
GET|HEAD api/ping ...
```

Jalankan server lalu test:

```bash
php artisan serve
# terminal lain:
curl http://localhost:8000/api/ping
```

**Expected**: `{"message":"pong"}`

### Step 1.6 — Tambahkan `HasApiTokens` ke User model

Edit `app/Models/User.php`:

```php
<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;              // ← TAMBAHKAN

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;   // ← TAMBAHKAN

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
```

**Penjelasan**: Trait `HasApiTokens` memberikan method `$user->createToken('name')` — dipakai saat implementasi login API nanti.

### Step 1.7 — Jalankan migration

```bash
php artisan migrate
```

**Expected**: 5 tabel ter-create:
- `users`, `cache`, `jobs` (default Laravel)
- `personal_access_tokens` (Sanctum)
- `activity_log` (Spatie)

---

## ✅ Checklist Fase 1

- [ ] `config/sanctum.php` & `config/activitylog.php` ada
- [ ] Migration `personal_access_tokens` & `activity_log` ada
- [ ] File `routes/api.php` ter-register di `bootstrap/app.php`
- [ ] `/api/ping` merespons `{"message":"pong"}`
- [ ] User model punya `HasApiTokens`
- [ ] `php artisan migrate` sukses (5 tabel)

---

## FASE 2 — Schema, Models & Seeds

### Step 2.1 — Generate Model + Migration

```bash
php artisan make:model Brand -m
php artisan make:model Category -m
php artisan make:model Shoe -m
```

**Flag**: `-m` → buatkan migration.

> **Catatan**: Tidak perlu factory. Data awal dibuat langsung lewat **seeder** (Step 2.9).

**Cek hasil**:
- `app/Models/` → Brand.php, Category.php, Shoe.php
- `database/migrations/` → 3 file migration baru

### Step 2.2 — Migration `brands`

`database/migrations/xxxx_create_brands_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 100)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
```

### Step 2.3 — Migration `categories`

`database/migrations/xxxx_create_categories_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
```

### Step 2.4 — Migration `shoes`

`database/migrations/xxxx_create_shoes_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shoes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('category_id')
                ->constrained(table: 'categories', column: 'id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignUlid('brand_id')
                ->constrained('brands', 'id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('name', 200);
            $table->string('size', 50);
            $table->unsignedBigInteger('price');
            $table->unsignedInteger('stock')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shoes');
    }
};
```

**Konsep ULID + FK (wajib paham)**:
- `$table->ulid('id')->primary()` → primary key bertipe **ULID** (string 26 karakter, misal `01HZ5F3G4S...`), bukan auto-increment integer. Sama seperti virtue-erm.
- `foreignUlid('brand_id')` → kolom string `brand_id` bertipe ULID
- `->constrained()` → otomatis buat **foreign key** ke kolom `id` tabel `brands`
- `->cascadeOnDelete()` → hapus brand = semua shoe brand itu ikut terhapus (alternatif: `->restrictOnDelete()` untuk cegah hapus brand yang masih punya shoes)
- `decimal('price', 10, 2)` → desimal max 10 digit, 2 di belakang koma

**Kenapa ULID?** (alasan virtue-erm memilih ini):
- ID tidak bisa ditebak/diiterasi dari luar (aman untuk eksposure API, tidak bocor jumlah data).
- Tetap sortable secara kronologis (mirip auto-increment), beda dengan UUID yang acak.
- String → aman di URL, tidak bisa di-manipulasi.

### Step 2.5 — Jalankan migration

```bash
php artisan migrate
php artisan migrate:status   # semua status harus "Ran"
```

### Step 2.6 — Model Brand

`app/Models/Brand.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Brand extends Model
{
    use HasUlids, LogsActivity;

    protected $fillable = [
        'name',
        'description',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'description'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('brand')
            ->setDescriptionForEvent(fn (string $eventName) => "Brand has been {$eventName}");
    }

    public function shoes(): HasMany
    {
        return $this->hasMany(Shoe::class);
    }
}
```

### Step 2.7 — Model Category

`app/Models/Category.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Category extends Model
{
    use HasUlids, LogsActivity;

    protected $fillable = [
        'name',
        'description',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('category')
            ->setDescriptionForEvent(fn (string $eventName) => "Category has been {$eventName}");
    }

    public function shoes(): HasMany
    {
        return $this->hasMany(Shoe::class);
    }
}
```

### Step 2.8 — Model Shoe

`app/Models/Shoe.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Shoe extends Model
{
    use HasUlids, LogsActivity;

    protected $fillable = [
        'brand_id',
        'category_id',
        'name',
        'size',
        'price',
        'stock',
        'description',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'category_id',
                'brand_id',
                'name',
                'size',
                'price',
                'stock',
                'description',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('shoe')
            ->setDescriptionForEvent(fn (string $eventName) => "Shoe has been {$eventName}");
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
```

**Penjelasan `getActivitylogOptions()`** (pattern dari virtue-erm, disederhanakan):
- `->logOnly([...])` → field yang dicatat saat berubah
- `->logOnlyDirty()` → hanya catat perubahan nyata
- `->dontSubmitEmptyLogs()` → skip log kosong (tidak ada field berubah)

### Step 2.9 — Buat Seeder per modul

```bash
php artisan make:seeder BrandSeeder
php artisan make:seeder CategorySeeder
php artisan make:seeder ShoeSeeder
```

### Step 2.10 — Isi Seeder (data langsung, tanpa factory)

`database/seeders/BrandSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $brands = [
            ['name' => 'Nike', 'description' => 'Just do it'],
            ['name' => 'Adidas', 'description' => 'Impossible is Nothing'],
            ['name' => 'Puma', 'description' => 'Forever Faster'],
            ['name' => 'Reebok', 'description' => 'Life is not a spectator sport'],
            ['name' => 'New Balance', 'description' => 'Endorsed by no one'],
        ];

        foreach ($brands as $brand) {
            Brand::create($brand);
        }
    }
}
```

`database/seeders/CategorySeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Running', 'description' => 'Sepatu untuk lari'],
            ['name' => 'Casual', 'description' => 'Sepatu santai sehari-hari'],
            ['name' => 'Basket', 'description' => 'Sepatu untuk olahraga basket'],
            ['name' => 'Sneakers', 'description' => 'Sepatu sneakers fashion'],
            ['name' => 'Sandal', 'description' => 'Sepatu terbuka / sandal'],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}
```

`database/seeders/ShoeSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Shoe;
use Illuminate\Database\Seeder;

class ShoeSeeder extends Seeder
{
    public function run(): void
    {
        $brand = Brand::where('name', 'Nike')->first();
        $category = Category::where('name', 'Running')->first();

        if (!$brand || !$category) {
            $this->command->warn('Brand/Category belum di-seed. Jalankan BrandSeeder & CategorySeeder dulu.');
            return;
        }

        $shoes = [
            [
                'brand_id'    => $brand->id,
                'category_id' => $category->id,
                'name'        => 'Air Max 90',
                'size'        => '42',
                'price'       => 1500000,
                'stock'       => 10,
                'description' => 'Sepatu lari premium dengan air cushioning.',
            ],
            [
                'brand_id'    => $brand->id,
                'category_id' => $category->id,
                'name'        => 'Pegasus 40',
                'size'        => '41',
                'price'       => 1900000,
                'stock'       => 5,
                'description' => 'Sepatu lari harian yang responsif.',
            ],
        ];

        foreach ($shoes as $shoe) {
            Shoe::create($shoe);
        }
    }
}
```

### Step 2.11 — Hubungkan Seeder di DatabaseSeeder

`database/seeders/DatabaseSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            BrandSeeder::class,
            CategorySeeder::class,
            ShoeSeeder::class,
        ]);

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
```

### Step 2.12 — Jalankan Seeder

```bash
php artisan db:seed
```

**Expected output**:
- 5 brand, 5 category, 2 shoe ter-create
- Setiap operasi `create` tercatat di tabel `activity_log` (aktivasi `LogsActivity`)

---

## ✅ Checklist Fase 2

- [ ] 3 model: Brand, Category, Shoe (dengan `LogsActivity`)
- [ ] 3 migration + FK di `shoes`
- [ ] `migrate:status` semua "Ran"
- [ ] 3 seeder + `DatabaseSeeder` ter-update
- [ ] `php artisan db:seed` sukses (5 brand, 5 category, 2 shoe)

---

## FASE 3 — Service Layer

### Step 3.1 — BaseService (abstract)

Buat `app/Services/BaseService.php`:

```php
<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;

abstract class BaseService
{
    protected Model $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function all()
    {
        return $this->model->all();
    }

    public function find(int $id): Model
    {
        return $this->model->findOrFail($id);
    }

    public function create(array $data): Model
    {
        return $this->model->create($data);
    }

    public function update(int $id, array $data): Model
    {
        $model = $this->find($id);
        $model->update($data);

        return $model->fresh();
    }

    public function delete(int $id): bool
    {
        return $this->find($id)->delete();
    }
}
```

**Konsep**: `BaseService` adalah kontrak dasar — semua service modul mewarisi operasi CRUD generic dari sini. Ini pola "controller tipis, logic di service" yang dipakai virtue-erm.

### Step 3.2 — BrandService

`app/Services/BrandService.php`:

```php
<?php

namespace App\Services;

use App\Models\Brand;

class BrandService extends BaseService
{
    public function __construct(Brand $brand)
    {
        parent::__construct($brand);
    }

    // Business logic khusus brand di sini, contoh:
    public function findWithShoes(int $id): Brand
    {
        return $this->model->with('shoes')->findOrFail($id);
    }
}
```

### Step 3.3 — CategoryService

`app/Services/CategoryService.php`:

```php
<?php

namespace App\Services;

use App\Models\Category;

class CategoryService extends BaseService
{
    public function __construct(Category $category)
    {
        parent::__construct($category);
    }

    public function findWithShoes(int $id): Category
    {
        return $this->model->with('shoes')->findOrFail($id);
    }
}
```

### Step 3.4 — ShoeService

`app/Services/ShoeService.php`:

```php
<?php

namespace App\Services;

use App\Models\Shoe;

class ShoeService extends BaseService
{
    public function __construct(Shoe $shoe)
    {
        parent::__construct($shoe);
    }

    /**
     * Ambil semua shoes dengan relasi brand & category (prevent N+1).
     */
    public function allWithRelations()
    {
        return $this->model->with(['brand', 'category'])->get();
    }

    public function findWithRelations(int $id): Shoe
    {
        return $this->model->with(['brand', 'category'])->findOrFail($id);
    }
}
```

**Konsep**: `->with(['brand', 'category'])` = **eager loading**. Mencegah query berulang (N+1 problem) saat akses relasi di loop.

---

## ✅ Checklist Fase 3

- [ ] `app/Services/BaseService.php` (abstract)
- [ ] `app/Services/BrandService.php`
- [ ] `app/Services/CategoryService.php`
- [ ] `app/Services/ShoeService.php`

---

## FASE 4 — Controller, Request, Resource

### Step 4.1 — Generate semua file

```bash
# Web controllers
php artisan make:controller BrandController --resource
php artisan make:controller CategoryController --resource
php artisan make:controller ShoeController --resource

# API controllers
php artisan make:controller Api/BrandController --resource
php artisan make:controller Api/CategoryController --resource
php artisan make:controller Api/ShoeController --resource

# Form Requests (validation)
php artisan make:request ApiRequest             # base class (akan di-edit jadi abstract)
php artisan make:request StoreBrandRequest
php artisan make:request UpdateBrandRequest
php artisan make:request StoreCategoryRequest
php artisan make:request UpdateCategoryRequest
php artisan make:request StoreShoeRequest
php artisan make:request UpdateShoeRequest

# API Resources (serialization)
php artisan make:resource BrandResource
php artisan make:resource CategoryResource
php artisan make:resource ShoeResource
```

### Step 4.2 — ApiRequest (Base Class)

Edit `app/Http/Requests/ApiRequest.php` menjadi **abstract** dan tambahkan custom `failedValidation`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

abstract class ApiRequest extends FormRequest
{
    /**
     * Override respons gagal validasi → selalu kembalikan JSON custom.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            new JsonResponse([
                'success' => false,
                'message' => 'The given data was invalid.',
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}
```

**Penjelasan**:
- Semua `StoreXxxRequest` / `UpdateXxxRequest` mewarisi class ini (bukan `FormRequest` langsung).
- `failedValidation()` → saat validasi gagal, Laravel default melempar redirect; versi ini melempar **JSON response** dengan struktur konsisten `{ success, message, errors }` — pola yang sama dengan helper `setResponse()` di virtue-erm.
- Hasilnya, request API selalu menerima response JSON 422, bukan redirect HTML.

### Step 4.3 — Form Request: Store/Update Brand

`app/Http/Requests/StoreBrandRequest.php`:

```php
<?php

namespace App\Http\Requests;

class StoreBrandRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'      => 'Nama brand wajib diisi.',
            'name.string'        => 'Nama brand harus berupa teks.',
            'name.max'           => 'Nama brand maksimal :max karakter.',
            'description.string' => 'Deskripsi brand harus berupa teks.',
        ];
    }
}
```

`app/Http/Requests/UpdateBrandRequest.php`:

```php
<?php

namespace App\Http\Requests;

class UpdateBrandRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'      => 'Nama brand wajib diisi.',
            'name.string'        => 'Nama brand harus berupa teks.',
            'name.max'           => 'Nama brand maksimal :max karakter.',
            'description.string' => 'Deskripsi brand harus berupa teks.',
        ];
    }
}
```

> **Catatan**: Untuk learning, Store & Update pakai rules yang sama. Nanti bisa bedakan dengan `Rule::unique(...)->ignore($this->brand->id)` di UpdateRequest untuk cek nama unik saat edit.

### Step 4.4 — Form Request: Store/Update Category

`app/Http/Requests/StoreCategoryRequest.php`:

```php
<?php

namespace App\Http\Requests;

class StoreCategoryRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'      => 'Nama kategori wajib diisi.',
            'name.string'        => 'Nama kategori harus berupa teks.',
            'name.max'           => 'Nama kategori maksimal :max karakter.',
            'description.string' => 'Deskripsi kategori harus berupa teks.',
        ];
    }
}
```

`app/Http/Requests/UpdateCategoryRequest.php`:

```php
<?php

namespace App\Http\Requests;

class UpdateCategoryRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'      => 'Nama kategori wajib diisi.',
            'name.string'        => 'Nama kategori harus berupa teks.',
            'name.max'           => 'Nama kategori maksimal :max karakter.',
            'description.string' => 'Deskripsi kategori harus berupa teks.',
        ];
    }
}
```

### Step 4.5 — Form Request: Store/Update Shoe

`app/Http/Requests/StoreShoeRequest.php`:

```php
<?php

namespace App\Http\Requests;

class StoreShoeRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'brand_id'    => ['required', 'ulid', 'exists:brands,id'],
            'category_id' => ['required', 'ulid', 'exists:categories,id'],
            'name'        => ['required', 'string', 'max:255'],
            'size'        => ['nullable', 'string', 'max:20'],
            'price'       => ['required', 'numeric', 'min:0'],
            'stock'       => ['required', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'brand_id.required'    => 'Brand wajib dipilih.',
            'brand_id.ulid'        => 'Brand tidak valid.',
            'brand_id.exists'      => 'Brand yang dipilih tidak ditemukan.',
            'category_id.required' => 'Kategori wajib dipilih.',
            'category_id.ulid'     => 'Kategori tidak valid.',
            'category_id.exists'   => 'Kategori yang dipilih tidak ditemukan.',
            'name.required'        => 'Nama sepatu wajib diisi.',
            'name.string'          => 'Nama sepatu harus berupa teks.',
            'name.max'             => 'Nama sepatu maksimal :max karakter.',
            'size.string'          => 'Ukuran sepatu harus berupa teks.',
            'size.max'             => 'Ukuran sepatu maksimal :max karakter.',
            'price.required'       => 'Harga wajib diisi.',
            'price.numeric'        => 'Harga harus berupa angka.',
            'price.min'            => 'Harga tidak boleh kurang dari :min.',
            'stock.required'       => 'Stok wajib diisi.',
            'stock.integer'        => 'Stok harus berupa bilangan bulat.',
            'stock.min'            => 'Stok tidak boleh kurang dari :min.',
            'description.string'   => 'Deskripsi sepatu harus berupa teks.',
        ];
    }
}
```

`app/Http/Requests/UpdateShoeRequest.php`:

```php
<?php

namespace App\Http\Requests;

class UpdateShoeRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'brand_id'    => ['required', 'ulid', 'exists:brands,id'],
            'category_id' => ['required', 'ulid', 'exists:categories,id'],
            'name'        => ['required', 'string', 'max:255'],
            'size'        => ['nullable', 'string', 'max:20'],
            'price'       => ['required', 'numeric', 'min:0'],
            'stock'       => ['required', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'brand_id.required'    => 'Brand wajib dipilih.',
            'brand_id.ulid'        => 'Brand tidak valid.',
            'brand_id.exists'      => 'Brand yang dipilih tidak ditemukan.',
            'category_id.required' => 'Kategori wajib dipilih.',
            'category_id.ulid'     => 'Kategori tidak valid.',
            'category_id.exists'   => 'Kategori yang dipilih tidak ditemukan.',
            'name.required'        => 'Nama sepatu wajib diisi.',
            'name.string'          => 'Nama sepatu harus berupa teks.',
            'name.max'             => 'Nama sepatu maksimal :max karakter.',
            'size.string'          => 'Ukuran sepatu harus berupa teks.',
            'size.max'             => 'Ukuran sepatu maksimal :max karakter.',
            'price.required'       => 'Harga wajib diisi.',
            'price.numeric'        => 'Harga harus berupa angka.',
            'price.min'            => 'Harga tidak boleh kurang dari :min.',
            'stock.required'       => 'Stok wajib diisi.',
            'stock.integer'        => 'Stok harus berupa bilangan bulat.',
            'stock.min'            => 'Stok tidak boleh kurang dari :min.',
            'description.string'   => 'Deskripsi sepatu harus berupa teks.',
        ];
    }
}
```

**Konsep penting**:
- `exists:brands,id` → memastikan `brand_id` benar-benar ada di tabel `brands` (validasi FK di level request)
- `min:0` → cegah harga/stock negatif

### Step 4.6 — API Resources

`app/Http/Resources/BrandResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BrandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'shoes_count' => $this->whenCounted('shoes'),
            'shoes'       => ShoeResource::collection($this->whenLoaded('shoes')),
            'created_at'  => $this->created_at?->toISOString(),
            'updated_at'  => $this->updated_at?->toISOString(),
        ];
    }
}
```

`app/Http/Resources/CategoryResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'shoes_count' => $this->whenCounted('shoes'),
            'shoes'       => ShoeResource::collection($this->whenLoaded('shoes')),
            'created_at'  => $this->created_at?->toISOString(),
            'updated_at'  => $this->updated_at?->toISOString(),
        ];
    }
}
```

`app/Http/Resources/ShoeResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShoeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'size'        => $this->size,
            'price'       => (float) $this->price,
            'stock'       => $this->stock,
            'description' => $this->description,
            'brand'       => new BrandResource($this->whenLoaded('brand')),
            'category'    => new CategoryResource($this->whenLoaded('category')),
            'created_at'  => $this->created_at?->toISOString(),
            'updated_at'  => $this->updated_at?->toISOString(),
        ];
    }
}
```

**Konsep API Resource**: Resource mendefinisikan bentuk JSON output. Kamu **kontrol** apa yang diekspos — tidak mengirim seluruh attribute model mentah (security + konsistensi).

### Step 4.7 — Web Controllers (pattern dasar, tanpa view)

> Web controller di virtue-erm mengembalikan `Inertia::render(...)`. Karena kita belum pakai Inertia, web controller untuk sekarang mengembalikan JSON juga (format sama, beda middleware nanti). Ini supaya kamu familiar dengan alur controller+service tanpa dibebani frontend.

`app/Http/Controllers/BrandController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBrandRequest;
use App\Http\Requests\UpdateBrandRequest;
use App\Http\Resources\BrandResource;
use App\Services\BrandService;

class BrandController extends Controller
{
    protected BrandService $brandService;

    public function __construct(BrandService $brandService)
    {
        $this->brandService = $brandService;
    }

    public function index()
    {
        $brands = $this->brandService->all();

        return BrandResource::collection($brands);
    }

    public function show(int $id)
    {
        $brand = $this->brandService->findWithShoes($id);

        return new BrandResource($brand);
    }

    public function store(StoreBrandRequest $request)
    {
        $brand = $this->brandService->create($request->validated());

        return new BrandResource($brand);
    }

    public function update(UpdateBrandRequest $request, int $id)
    {
        $brand = $this->brandService->update($id, $request->validated());

        return new BrandResource($brand);
    }

    public function destroy(int $id)
    {
        $this->brandService->delete($id);

        return response()->json(['message' => 'Brand deleted successfully.']);
    }
}
```

`app/Http/Controllers/CategoryController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Services\CategoryService;

class CategoryController extends Controller
{
    protected CategoryService $categoryService;

    public function __construct(CategoryService $categoryService)
    {
        $this->categoryService = $categoryService;
    }

    public function index()
    {
        return CategoryResource::collection($this->categoryService->all());
    }

    public function show(int $id)
    {
        return new CategoryResource($this->categoryService->findWithShoes($id));
    }

    public function store(StoreCategoryRequest $request)
    {
        return new CategoryResource($this->categoryService->create($request->validated()));
    }

    public function update(UpdateCategoryRequest $request, int $id)
    {
        return new CategoryResource($this->categoryService->update($id, $request->validated()));
    }

    public function destroy(int $id)
    {
        $this->categoryService->delete($id);

        return response()->json(['message' => 'Category deleted successfully.']);
    }
}
```

`app/Http/Controllers/ShoeController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreShoeRequest;
use App\Http\Requests\UpdateShoeRequest;
use App\Http\Resources\ShoeResource;
use App\Services\ShoeService;

class ShoeController extends Controller
{
    protected ShoeService $shoeService;

    public function __construct(ShoeService $shoeService)
    {
        $this->shoeService = $shoeService;
    }

    public function index()
    {
        return ShoeResource::collection($this->shoeService->allWithRelations());
    }

    public function show(int $id)
    {
        return new ShoeResource($this->shoeService->findWithRelations($id));
    }

    public function store(StoreShoeRequest $request)
    {
        $shoe = $this->shoeService->create($request->validated());

        return new ShoeResource($shoe->load(['brand', 'category']));
    }

    public function update(UpdateShoeRequest $request, int $id)
    {
        $shoe = $this->shoeService->update($id, $request->validated());

        return new ShoeResource($shoe->load(['brand', 'category']));
    }

    public function destroy(int $id)
    {
        $this->shoeService->delete($id);

        return response()->json(['message' => 'Shoe deleted successfully.']);
    }
}
```

### Step 4.8 — API Controllers

> API controller mengembalikan **response() wrapper standar** agar konsisten untuk klien eksternal. Untuk learning, bentuk JSON sama dengan web controller — perbedaan utamanya nanti di middleware auth.

`app/Http/Controllers/Api/BrandController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBrandRequest;
use App\Http\Requests\UpdateBrandRequest;
use App\Http\Resources\BrandResource;
use App\Services\BrandService;
use Illuminate\Http\JsonResponse;

class BrandController extends Controller
{
    protected BrandService $brandService;

    public function __construct(BrandService $brandService)
    {
        $this->brandService = $brandService;
    }

    public function index(): JsonResponse
    {
        $brands = $this->brandService->all();

        return response()->json([
            'success' => true,
            'data'    => BrandResource::collection($brands),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $brand = $this->brandService->findWithShoes($id);

        return response()->json([
            'success' => true,
            'data'    => new BrandResource($brand),
        ]);
    }

    public function store(StoreBrandRequest $request): JsonResponse
    {
        $brand = $this->brandService->create($request->validated());

        return response()->json([
            'success' => true,
            'data'    => new BrandResource($brand),
        ], 201);
    }

    public function update(UpdateBrandRequest $request, int $id): JsonResponse
    {
        $brand = $this->brandService->update($id, $request->validated());

        return response()->json([
            'success' => true,
            'data'    => new BrandResource($brand),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->brandService->delete($id);

        return response()->json([
            'success' => true,
            'message' => 'Brand deleted successfully.',
        ]);
    }
}
```

`app/Http/Controllers/Api/CategoryController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    protected CategoryService $categoryService;

    public function __construct(CategoryService $categoryService)
    {
        $this->categoryService = $categoryService;
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => CategoryResource::collection($this->categoryService->all()),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => new CategoryResource($this->categoryService->findWithShoes($id)),
        ]);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->categoryService->create($request->validated());

        return response()->json([
            'success' => true,
            'data'    => new CategoryResource($category),
        ], 201);
    }

    public function update(UpdateCategoryRequest $request, int $id): JsonResponse
    {
        $category = $this->categoryService->update($id, $request->validated());

        return response()->json([
            'success' => true,
            'data'    => new CategoryResource($category),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->categoryService->delete($id);

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully.',
        ]);
    }
}
```

`app/Http/Controllers/Api/ShoeController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShoeRequest;
use App\Http\Requests\UpdateShoeRequest;
use App\Http\Resources\ShoeResource;
use App\Services\ShoeService;
use Illuminate\Http\JsonResponse;

class ShoeController extends Controller
{
    protected ShoeService $shoeService;

    public function __construct(ShoeService $shoeService)
    {
        $this->shoeService = $shoeService;
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => ShoeResource::collection($this->shoeService->allWithRelations()),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => new ShoeResource($this->shoeService->findWithRelations($id)),
        ]);
    }

    public function store(StoreShoeRequest $request): JsonResponse
    {
        $shoe = $this->shoeService->create($request->validated());

        return response()->json([
            'success' => true,
            'data'    => new ShoeResource($shoe->load(['brand', 'category'])),
        ], 201);
    }

    public function update(UpdateShoeRequest $request, int $id): JsonResponse
    {
        $shoe = $this->shoeService->update($id, $request->validated());

        return response()->json([
            'success' => true,
            'data'    => new ShoeResource($shoe->load(['brand', 'category'])),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->shoeService->delete($id);

        return response()->json([
            'success' => true,
            'message' => 'Shoe deleted successfully.',
        ]);
    }
}
```

---

## ✅ Checklist Fase 4

- [ ] `ApiRequest` (base class) dibuat abstract + custom `failedValidation`
- [ ] 6 Form Request extends `ApiRequest` + punya `messages()` custom
- [ ] 3 API Resource
- [ ] 3 web controller (resource)
- [ ] 3 API controller (Api\ prefix)
- [ ] Semua controller pakai service (bukan langsung model)

---

## FASE 5 — Routes

### Step 5.1 — `routes/api.php`

Isi `routes/api.php`:

```php
<?php

use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ShoeController;
use Illuminate\Support\Facades\Route;

// Probe awal (boleh dihapus setelah route asli jalan)
Route::get('/ping', function () {
    return response()->json(['message' => 'pong']);
});

// NOTE: Middleware auth:sanctum DITAMBAHKAN SAAT IMPLEMENTASI AUTH
Route::prefix('v1')->group(function () {
    Route::apiResource('brands', BrandController::class);
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('shoes', ShoeController::class);
});
```

**Penjelasan**:
- `Route::apiResource('brands', BrandController::class)` → otomatis generate 5 route: index, store, show, update, destroy.
- `prefix('v1')` → versi API, mirip pattern virtue-erm (`api/v1/...`).
- Dikombinasi dengan `apiPrefix: 'api'` di bootstrap, URL menjadi `api/v1/brands`.

### Step 5.2 — `routes/web.php`

Edit `routes/web.php`:

```php
<?php

use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ShoeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// NOTE: Middleware auth DITAMBAHKAN SAAT IMPLEMENTASI AUTH
Route::prefix('shoes-management')->group(function () {
    Route::get('brands', [BrandController::class, 'index'])->name('brands.index');
    Route::get('brands/{id}', [BrandController::class, 'show'])->name('brands.show');
    Route::post('brands', [BrandController::class, 'store'])->name('brands.store');
    Route::patch('brands/{id}', [BrandController::class, 'update'])->name('brands.update');
    Route::delete('brands/{id}', [BrandController::class, 'destroy'])->name('brands.destroy');

    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('categories/{id}', [CategoryController::class, 'show'])->name('categories.show');
    Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::patch('categories/{id}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('categories/{id}', [CategoryController::class, 'destroy'])->name('categories.destroy');

    Route::get('shoes', [ShoeController::class, 'index'])->name('shoes.index');
    Route::get('shoes/{id}', [ShoeController::class, 'show'])->name('shoes.show');
    Route::post('shoes', [ShoeController::class, 'store'])->name('shoes.store');
    Route::patch('shoes/{id}', [ShoeController::class, 'update'])->name('shoes.update');
    Route::delete('shoes/{id}', [ShoeController::class, 'destroy'])->name('shoes.destroy');
});
```

**Penjelasan**: Di web.php saya pakai route manual (bukan `Route::resource`) supaya kamu paham **struktur route per method** — lebih transparan untuk learning daripada magic resource.

### Step 5.3 — Verifikasi route

```bash
php artisan route:list
```

Harus muncul semua route API dan web.

### Step 5.4 — Test CRUD dengan curl

> **Penting**: Karena primary key bertipe **ULID**, semua `id` di URL & body request bukan angka `1`, melainkan string ULID 26 karakter (misal `01HZ5F3G4S...`). Ambil id asli dari database dulu:
>
> ```bash
> php artisan tinker
> Brand::first()->id;       // misal "01HZ5F3G4S..."
> Category::first()->id;
> Shoe::first()->id;
> ```
>
> Ganti nilai-nilai di bawah ini dengan ULID yang kamu dapat.

```bash
# Buat brand
curl -X POST http://localhost:8000/api/v1/brands \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"name":"Nike","description":"Just do it"}'

# Buat category
curl -X POST http://localhost:8000/api/v1/categories \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"name":"Running","description":"Sepatu lari"}'

# Buat shoe (ganti <BRAND_ULID> & <CATEGORY_ULID> dengan id asli)
curl -X POST http://localhost:8000/api/v1/shoes \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"brand_id":"<BRAND_ULID>","category_id":"<CATEGORY_ULID>","name":"Air Max 90","size":"42","price":1500000,"stock":10,"description":"Sepatu lari premium"}'

# List shoes (harus include relasi brand & category)
curl http://localhost:8000/api/v1/shoes

# Show detail (ganti <SHOE_ULID> dengan id asli)
curl http://localhost:8000/api/v1/shoes/<SHOE_ULID>

# Update
curl -X PATCH http://localhost:8000/api/v1/shoes/<SHOE_ULID> \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"brand_id":"<BRAND_ULID>","category_id":"<CATEGORY_ULID>","name":"Air Max 97","size":"42","price":1700000,"stock":8}'

# Delete
curl -X DELETE http://localhost:8000/api/v1/shoes/<SHOE_ULID>
```

**Cek activity log** setelah operasi CRUD di atas:

```bash
php artisan tinker
```

```php
\Spatie\Activitylog\Models\Activity::all();   // harus berisi log create/update/delete
```

---

## ✅ Checklist Fase 5

- [ ] `routes/api.php` punya `api/v1/{brands,categories,shoes}`
- [ ] `routes/web.php` punya `shoes-management/{brands,categories,shoes}`
- [ ] CRUD berhasil via curl (POST, GET, PATCH, DELETE)
- [ ] Activity log terisi setelah operasi CRUD
- [ ] Validasi bekerja (test POST dengan data invalid → error 422)

---

## FASE 6 — Testing (Tanpa Factory)

> Seeder sudah dibuat di **Step 2.9–2.12**. Di fase ini kita hanya menulis **Feature Test**.
> Karena kita **tidak pakai factory**, semua data test dibuat langsung dengan `Model::create(...)`.
> Database test otomatis di-refresh tiap test berkat `RefreshDatabase`.

### Step 6.1 — Feature Test (Shoe)

Buat test untuk CRUD API. `tests/Feature/ShoeTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Shoe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShoeTest extends TestCase
{
    use RefreshDatabase;

    private function makeBrand(): Brand
    {
        return Brand::create(['name' => 'Nike', 'description' => 'Just do it']);
    }

    private function makeCategory(): Category
    {
        return Category::create(['name' => 'Running', 'description' => 'Sepatu lari']);
    }

    private function makeShoe(array $attributes = []): Shoe
    {
        return Shoe::create(array_merge([
            'brand_id'    => $this->makeBrand()->id,
            'category_id' => $this->makeCategory()->id,
            'name'        => 'Sepatu Test',
            'size'        => '42',
            'price'       => 500000,
            'stock'       => 5,
            'description' => 'Deskripsi test',
        ], $attributes));
    }

    public function test_can_list_shoes(): void
    {
        $this->makeShoe();
        $this->makeShoe();

        $response = $this->getJson('/api/v1/shoes');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_can_create_shoe(): void
    {
        $brand = $this->makeBrand();
        $category = $this->makeCategory();

        $response = $this->postJson('/api/v1/shoes', [
            'brand_id'    => $brand->id,
            'category_id' => $category->id,
            'name'        => 'Sepatu Test',
            'size'        => '42',
            'price'       => 500000,
            'stock'       => 5,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'Sepatu Test');

        $this->assertDatabaseHas('shoes', [
            'name'  => 'Sepatu Test',
            'price' => 500000,
        ]);
    }

    public function test_create_shoe_requires_valid_brand(): void
    {
        $response = $this->postJson('/api/v1/shoes', [
            'brand_id'    => 999,
            'category_id' => 1,
            'name'        => 'Shoe',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['brand_id']);
    }

    public function test_can_show_shoe(): void
    {
        $shoe = $this->makeShoe();

        $response = $this->getJson("/api/v1/shoes/{$shoe->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $shoe->id);
    }

    public function test_can_update_shoe(): void
    {
        $shoe = $this->makeShoe();

        $response = $this->patchJson("/api/v1/shoes/{$shoe->id}", [
            'brand_id'    => $shoe->brand_id,
            'category_id' => $shoe->category_id,
            'name'        => 'Nama Baru',
            'price'       => $shoe->price,
            'stock'       => $shoe->stock,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.name', 'Nama Baru');

        $this->assertDatabaseHas('shoes', [
            'id'   => $shoe->id,
            'name' => 'Nama Baru',
        ]);
    }

    public function test_can_delete_shoe(): void
    {
        $shoe = $this->makeShoe();

        $response = $this->deleteJson("/api/v1/shoes/{$shoe->id}");

        $response->assertOk();

        $this->assertDatabaseMissing('shoes', ['id' => $shoe->id]);
    }

    public function test_activity_log_created_on_shoe_creation(): void
    {
        $shoe = $this->makeShoe();

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Shoe::class,
            'subject_id'   => $shoe->id,
            'description'  => 'created',
        ]);
    }
}
```

> **Catatan**: Tidak ada `Shoe::factory()`. Helper `makeShoe()` mengisi data secara eksplisit — kalau test butuh data beda, tinggal lewati array, misal `makeShoe(['price' => 1000])`.

`tests/Feature/BrandTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_brands(): void
    {
        Brand::create(['name' => 'Nike', 'description' => 'Just do it']);
        Brand::create(['name' => 'Adidas', 'description' => 'Impossible is Nothing']);
        Brand::create(['name' => 'Puma', 'description' => 'Forever Faster']);

        $this->getJson('/api/v1/brands')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_brand(): void
    {
        $response = $this->postJson('/api/v1/brands', [
            'name'        => 'Nike',
            'description' => 'Just do it',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'Nike');

        $this->assertDatabaseHas('brands', ['name' => 'Nike']);
    }

    public function test_create_brand_requires_name(): void
    {
        $this->postJson('/api/v1/brands', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }
}
```

### Step 6.2 — Jalankan Test

```bash
composer test
```

**Expected**: Semua test PASS (hijau).

---

## ✅ Checklist Fase 6

- [ ] `tests/Feature/ShoeTest.php` (tanpa factory) PASS
- [ ] `tests/Feature/BrandTest.php` (tanpa factory) PASS
- [ ] `composer test` semua PASS

---

## 🔮 Roadmap Berikutnya (Setelah CRUD Beres)

1. **Implementasi Auth** (`laravel/sanctum` sudah terpasang):
   - `POST /api/v1/auth/login` → generate token
   - `POST /api/v1/auth/logout` → revoke token
   - `GET /api/v1/auth/me` → current user
   - Tambahkan middleware `auth:sanctum` ke group `api/v1`
   - Tambahkan `user_id` / `created_by` di tabel shoes (opsional)

2. **Perbaikan Pola** (setelah paham dasar):
   - `Rule::unique()` untuk cek nama unik brand/category di UpdateRequest
   - `SoftDeletes` di model (hapus lunak, restore, force delete)
   - Pagination di `index()` (mirip `paginate()` di virtue-erm)
   - `BaseService` tambah method `paginate()`, `search()`

3. **Belajar Lanjutan dari virtue-erm**:
   - Global scope (`HasSuperAdminGlobalScope`)
   - `HasVisibleColumnsTrait` (dynamic table columns)
   - Workflow engine multi-step
   - RBAC dengan `spatie/laravel-permission`

---

## ⚠️ Catatan Penting (Junior Developer)

1. **Primary key ULID sudah dipakai dari Fase 2** — tidak ada auto-increment integer. Jangan pernah ganti kembali ke `$table->id()` tanpa alasan kuat (keamanan & konsistensi dengan virtue-erm).
2. **Selalu pakai `->with()` eager loading** saat akses relasi — hindari N+1 query.
3. **Selalu bungkus operasi multi-record** dalam `DB::transaction()`.
4. **Service layer** = controller tipis, logic di service. Jangan pindah seluruh logika ke controller.
5. **API Resource** mengontrol output JSON — jangan expose `$model->toArray()` mentah.
6. **Validasi di FormRequest**, bukan di controller inline.
7. **Activitylog trait** sudah aktif — jangan double-logging manual.