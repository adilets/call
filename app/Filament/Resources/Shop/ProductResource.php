<?php

namespace App\Filament\Resources\Shop;

use App\Filament\Imports\ProductImporter;
use App\Filament\Resources\Shop;
use App\Models\Product;
use Filament\Forms\Components\Hidden;
use Filament\Tables\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\ImportAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\QueryBuilder\Constraints\BooleanConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\DateConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\NumberConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\TextConstraint;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static ?string $navigationLabel = 'Products';
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'Shop';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Hidden::make('client_id')
                    ->default(fn () => auth()->user()->client_id),
                Group::make()
                    ->schema([
                        Section::make()
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (string $operation, $state, Set $set) {
                                        if ($operation !== 'create') {
                                            return;
                                        }

                                        $set('slug', Str::slug($state));
                                    }),

                                TextInput::make('slug')
                                    ->disabled()
                                    ->dehydrated()
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(Product::class, 'slug', ignoreRecord: true),

                                MarkdownEditor::make('description')
                                    ->columnSpan('full'),
                            ])
                            ->columns(2),

                        Section::make('Images')
                            ->schema([
                                SpatieMediaLibraryFileUpload::make('media')
                                    ->collection('product-images')
                                    ->multiple()
                                    ->maxFiles(5)
                                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp']) // ✅
                                    ->maxSize(5120) // ✅ 5MB (в КБ)
                                    ->hiddenLabel(),
                            ])
                            ->collapsible(),

                        Section::make('Pricing')
                            ->schema([
                                TextInput::make('price')
                                    ->numeric()
                                    ->rules(['regex:/^\d{1,6}(\.\d{0,2})?$/'])
                                    ->required(),

//                                TextInput::make('old_price')
//                                    ->label('Compare at price')
//                                    ->numeric()
//                                    ->rules(['regex:/^\d{1,6}(\.\d{0,2})?$/'])
//                                    ->required(),

//                                TextInput::make('cost')
//                                    ->label('Cost per item')
//                                    ->helperText('Customers won\'t see this price.')
//                                    ->numeric()
//                                    ->rules(['regex:/^\d{1,6}(\.\d{0,2})?$/'])
//                                    ->required(),
                            ])
                            ->columns(2),
                        Section::make('Inventory')
                            ->schema([
                                TextInput::make('sku')
                                    ->label('SKU (Stock Keeping Unit)')
                                    ->unique(Product::class, 'sku', ignoreRecord: true)
                                    ->maxLength(255)
                                    ->required(),

//                                TextInput::make('barcode')
//                                    ->label('Barcode (ISBN, UPC, GTIN, etc.)')
//                                    ->unique(Product::class, 'barcode', ignoreRecord: true)
//                                    ->maxLength(255)
//                                    ->required(),

//                                TextInput::make('qty')
//                                    ->label('Quantity')
//                                    ->numeric()
//                                    ->rules(['integer', 'min:0'])
//                                    ->required(),

//                                TextInput::make('security_stock')
//                                    ->helperText('The safety stock is the limit stock for your products which alerts you if the product stock will soon be out of stock.')
//                                    ->numeric()
//                                    ->rules(['integer', 'min:0'])
//                                    ->required(),
                            ])
                            ->columns(2),

                        Section::make('Shipping')
                            ->schema([
                                Checkbox::make('backorder')
                                    ->label('This product can be returned'),

                                Checkbox::make('requires_shipping')
                                    ->label('This product will be shipped'),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpan(['lg' => 2]),

                Group::make()
                    ->schema([
                        Section::make('Status')
                            ->schema([
                                Toggle::make('is_visible')
                                    ->label('Visible')
                                    ->helperText('This product will be hidden from all sales channels.')
                                    ->default(true),

                                DatePicker::make('published_at')
                                    ->label('Availability')
                                    ->default(now())
                                    ->required(),
                            ]),

                        Section::make('Associations')
                            ->schema([
//                                Select::make('shop_brand_id')
//                                    ->relationship('brand', 'name')
//                                    ->searchable()
//                                    ->hiddenOn(ProductsRelationManager::class),

                                Select::make('categories')
                                    ->relationship('categories', 'name')
                                    ->multiple()
                                    ->required(),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('product-image')
                    ->label('Image')
                    ->collection('product-images'),

                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                IconColumn::make('is_visible')
                    ->label('Visibility')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('price')
                    ->label('Price')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('qty')
                    ->label('Quantity')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('security_stock')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),

                TextColumn::make('published_at')
                    ->label('Publish Date')
                    ->date()
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
            ])
            ->filters([
                QueryBuilder::make()
                    ->constraints([
                        TextConstraint::make('name'),
                        TextConstraint::make('slug'),
                        TextConstraint::make('sku')
                            ->label('SKU (Stock Keeping Unit)'),
//                        TextConstraint::make('barcode')
//                            ->label('Barcode (ISBN, UPC, GTIN, etc.)'),
                        TextConstraint::make('description'),
//                        NumberConstraint::make('old_price')
//                            ->label('Compare at price')
//                            ->icon('heroicon-m-currency-dollar'),
                        NumberConstraint::make('price')
                            ->icon('heroicon-m-currency-dollar'),
//                        NumberConstraint::make('cost')
//                            ->label('Cost per item')
//                            ->icon('heroicon-m-currency-dollar'),
                        NumberConstraint::make('qty')
                            ->label('Quantity'),
                        NumberConstraint::make('security_stock'),
                        BooleanConstraint::make('is_visible')
                            ->label('Visibility'),
                        BooleanConstraint::make('featured'),
                        BooleanConstraint::make('backorder'),
                        BooleanConstraint::make('requires_shipping')
                            ->icon('heroicon-m-truck'),
                        DateConstraint::make('published_at'),
                    ])
                    ->constraintPickerColumns(2),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->deferFilters()
            ->actions([
                EditAction::make(),
            ])
            ->headerActions([
                ImportAction::make()
                    ->importer(ProductImporter::class)
                    ->label('Import Products'),
            ])
            ->groupedBulkActions([
                DeleteBulkAction::make()
                    ->action(function () {
                        Notification::make()
                            ->title('Now, now, don\'t be cheeky, leave some records for others to play with!')
                            ->warning()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Shop\ProductResource\Pages\ListProducts::route('/'),
            'create' => Shop\ProductResource\Pages\CreateProduct::route('/create'),
            'edit' => Shop\ProductResource\Pages\EditProduct::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->where('client_id', auth()->user()->client_id);
    }

    public static function canAccess(): bool
    {
        // Если у тебя только клиентские пользователи:
        return auth()->check();

        // Или если только для клиентов с ролью:
        // return auth()->user()?->hasRole('client');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'sku'];
    }

    public static function getNavigationBadge(): ?string
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = static::$model;

        return (string) $modelClass::whereColumn('qty', '<', 'security_stock')->count();
    }
}
