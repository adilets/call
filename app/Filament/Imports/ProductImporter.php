<?php

namespace App\Filament\Imports;

use App\Models\Product;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProductImporter extends Importer
{
    protected static ?string $model = Product::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')->rules(['required', 'string', 'max:255']),
            ImportColumn::make('slug')->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('sku')->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('barcode')->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('description')->rules(['nullable', 'string']),
            ImportColumn::make('qty')->rules(['nullable', 'integer', 'min:0']),
            ImportColumn::make('security_stock')->rules(['nullable', 'integer', 'min:0']),
            ImportColumn::make('featured')->rules(['nullable', 'boolean']),
            ImportColumn::make('is_visible')->rules(['nullable', 'boolean']),
            ImportColumn::make('old_price')->rules(['nullable', 'numeric', 'min:0']),
            ImportColumn::make('price')->rules(['nullable', 'numeric', 'min:0']),
            ImportColumn::make('cost')->rules(['nullable', 'numeric', 'min:0']),
            ImportColumn::make('type')->rules(['nullable', 'in:deliverable,downloadable']),
            ImportColumn::make('backorder')->rules(['nullable', 'boolean']),
            ImportColumn::make('requires_shipping')->rules(['nullable', 'boolean']),
            ImportColumn::make('published_at')->rules(['nullable', 'date']),
            ImportColumn::make('seo_title')->rules(['nullable', 'string', 'max:60']),
            ImportColumn::make('seo_description')->rules(['nullable', 'string', 'max:160']),
            ImportColumn::make('weight_value')->rules(['nullable', 'numeric', 'min:0']),
            ImportColumn::make('weight_unit')->rules(['nullable', 'string', 'max:10']),
            ImportColumn::make('height_value')->rules(['nullable', 'numeric', 'min:0']),
            ImportColumn::make('height_unit')->rules(['nullable', 'string', 'max:10']),
            ImportColumn::make('width_value')->rules(['nullable', 'numeric', 'min:0']),
            ImportColumn::make('width_unit')->rules(['nullable', 'string', 'max:10']),
            ImportColumn::make('depth_value')->rules(['nullable', 'numeric', 'min:0']),
            ImportColumn::make('depth_unit')->rules(['nullable', 'string', 'max:10']),
            ImportColumn::make('volume_value')->rules(['nullable', 'numeric', 'min:0']),
            ImportColumn::make('volume_unit')->rules(['nullable', 'string', 'max:10']),
            ImportColumn::make('image_urls')->rules(['nullable', 'string']),
        ];
    }

    public function resolveRecord(): ?Product
    {
        $data = $this->data;

        // Привязка клиента
        $data['client_id'] = auth()->user()->client_id;

        // Генерация slug, если не передан
        if (empty($data['slug']) && !empty($data['name'])) {
            $slug = Str::slug($data['name']);
            $originalSlug = $slug;
            $counter = 1;

            while (Product::where('slug', $slug)->where('client_id', $data['client_id'])->exists()) {
                $slug = $originalSlug . '-' . $counter++;
            }

            $data['slug'] = $slug;
        }

        // Нормализация decimal значений
        foreach ([
                     'old_price', 'price', 'cost',
                     'weight_value', 'height_value', 'width_value',
                     'depth_value', 'volume_value'
                 ] as $field) {
            if (isset($data[$field])) {
                $data[$field] = (float) str_replace([' ', ','], ['', '.'], $data[$field]);
            }
        }

        $product = Product::updateOrCreate(
            [
                'sku' => $data['sku'],
                'client_id' => $data['client_id'],
            ],
            $data
        );

        if (!empty($data['image_urls'])) {
            $urls = array_map('trim', explode(',', $data['image_urls']));

            foreach ($urls as $url) {
                try {
                    $product
                        ->addMediaFromUrl($url)
                        ->toMediaCollection('product-images');
                } catch (\Throwable $e) {
                    // Можно логировать ошибку или отправить уведомление
                    logger()->warning("Image import failed: {$url} — {$e->getMessage()}");
                }
            }
        }

        return $product;

        // return Product::firstOrNew([
        //     // Update existing records, matching them by `$this->data['column_name']`
        //     'email' => $this->data['email'],
        // ]);

//        return new Product();
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $success = $import->successful_rows_count;
        $fail = $import->failed_rows_count;

        $message = "✅ Imported: {$success} products.";

        if ($fail > 0) {
            $url = $import->getFailedRowsDownloadUrl();
            $message .= " ⚠️ {$fail} failed rows. ";
            $message .= "<a href=\"{$url}\" target=\"_blank\" class=\"underline text-danger-600\">Download failed rows</a>";
        }

        return $message;
    }

    public static function getCompletedNotificationTitle(Import $import): string
    {
        return '📦 Product Import Completed';
    }
}
