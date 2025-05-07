<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RouteResource\Pages;
use App\Filament\Resources\RouteResource\RelationManagers;
use App\Models\Company;
use App\Models\Route;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class RouteResource extends Resource
{
    protected static ?string $model = Route::class;

    protected static ?string $navigationGroup = 'Administración';
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $label = 'Ruta';
    protected static ?string $pluralLabel = 'Rutas';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = Auth::user();
        //dd($user->role->name !== 'Developer', $user->role->name);
        if ($user->role->name !== 'Desarrollador') {
            //dd($query);
            $query->where('company_id', $user->company_id);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()->schema([
                    Fieldset::make('Datos Ruta')->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre Ruta')
                            ->required()
                            ->maxLength(50),
                        Forms\Components\TextInput::make('description')
                            ->label('Descripcion Ruta')
                            ->maxLength(100),
                        Forms\Components\Toggle::make('active')
                            ->label('Activa')
                            ->default(true)
                            ->required(),
                    ])
                        ->columns([
                            'sm' => 1,
                            'md' => 2,
                            'lg' => 2,
                            'xl' => 3,
                            '2xl' => 3,
                        ]),
                    Fieldset::make('Pertenece a')
                        ->schema([
                            Forms\Components\Select::make('company_id')
                                ->label('Compañía')
                                ->preload()
                                ->options(
                                    function () {
                                        $user = Auth::user();
                                        if ($user->role->name === 'Desarrollador') {
                                            // Si el usuario es Desarrollador, muestra todas las compañías
                                            return Company::all()->pluck('name', 'id');
                                        } else {
                                            // Si el usuario tiene una institución, muestra las compañías de su institución
                                            return Company::where('id', $user->company_id)
                                                ->pluck('name', 'id');
                                        }
                                    }
                                )
                                ->afterStateUpdated(
                                    function (callable $set) {
                                        $set('collector_id', null);
                                    }
                                )
                                ->reactive()
                                ->required(),
                            Forms\Components\Select::make('collector_id')
                                ->label('Cobrador')
                                ->preload()
                                ->options(
                                    function (callable $get) {

                                        return User::where('company_id', $get('company_id'))
                                            ->where('role_id', 3)
                                            ->pluck('name', 'id');
                                    }
                                )
                                ->reactive(),
                        ])
                ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre Ruta')
                    ->description(
                        fn(Route $record): string =>
                        $record->description
                    )
                    ->searchable(),
                Tables\Columns\TextColumn::make('company.name')
                    ->label('Compañía')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customers_count')
                    ->label('Clientes Activos')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('collector.name')
                    ->label('Cobrador')
                    ->sortable(),
                Tables\Columns\IconColumn::make('active')
                    ->label('Activa')
                    ->sortable()
                    ->boolean(),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->label('Fecha de Eliminación')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha de Creación')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Fecha de Actualización')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make()
                ])
                    ->tooltip('Acciones')
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoutes::route('/'),
            'create' => Pages\CreateRoute::route('/create'),
            'edit' => Pages\EditRoute::route('/{record}/edit'),
        ];
    }
}
