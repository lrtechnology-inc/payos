<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\RelationManagers;
use App\Models\Company;
use App\Models\Customer;
use Filament\Forms;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationGroup = 'Negocio';
    protected static ?string $navigationIcon = 'heroicon-o-identification';
    protected static ?string $label = 'Cliente';
    protected static ?string $pluralLabel = 'Clientes';

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
                Section::make()
                    ->schema([
                        Fieldset::make('Datos del cliente')
                            ->schema([
                                Forms\Components\TextInput::make('first_name')
                                    ->label('Nombre(s)')
                                    ->required(),
                                Forms\Components\TextInput::make('last_name')
                                    ->label('Apellido(s)')
                                    ->required(),
                                Forms\Components\Select::make('dni_type')
                                    ->label('Tipo Documento')
                                    ->options([
                                        'CC' => 'Cédula de Ciudadanía',
                                        'CE' => 'Cédula de Extranjería',
                                        'PAS' => 'Pasaporte'
                                    ])
                                    ->preload()
                                    ->required(),
                                Forms\Components\TextInput::make('dni')
                                    ->label('Documento')
                                    ->numeric()
                                    ->required(),
                            ])->columns([
                                'sm' => 2,
                                'md' => 2,
                                'lg' => 2,
                                'xl' => 3,
                                '2xl' => 4,
                            ]),
                        Fieldset::make('Contacto y Ubicación')
                            ->schema([
                                Forms\Components\TextInput::make('address')
                                    ->label('Dirección')
                                    ->columnSpan(2)
                                    ->required(),
                                Forms\Components\TextInput::make('city')
                                    ->label('Ciudad')
                                    ->required(),
                                Forms\Components\TextInput::make('state')
                                    ->label('Estado/Departamento')
                                    ->required(),
                                Forms\Components\TextInput::make('country')
                                    ->label('País')
                                    ->required(),
                                Forms\Components\TextInput::make('email')
                                    ->label('Correo Electrónico')
                                    ->email()
                                    ->required(),
                                Forms\Components\TextInput::make('phone')
                                    ->label('Teléfono')
                                    ->tel()
                                    ->required(),
                            ])->columns([
                                'sm' => 2,
                                'md' => 2,
                                'lg' => 2,
                                'xl' => 3,
                                '2xl' => 4,
                            ]),
                        Fieldset::make('Complementos')
                            ->schema([
                                Forms\Components\Select::make('company_id')
                                    ->label('Compañía')
                                    ->options(function () {
                                        $user = Auth::user();
                                        if ($user->role->name === 'Desarrollador') {
                                            // Si el usuario es Desarrollador, muestra todas las compañías
                                            return Company::all()->pluck('name', 'id');
                                        } else {
                                            // Si el usuario tiene una institución, muestra las compañías de su institución
                                            return Company::where('id', $user->company_id)
                                                ->pluck('name', 'id');
                                        }
                                    })
                                    ->preload()
                                    ->searchable()
                                    ->required(),
                                Forms\Components\Toggle::make('active')
                                    ->label('Activo')
                                    ->helperText('El cliente estará activo en el sistema')
                                    ->required()
                                    ->default(true),
                                Forms\Components\Toggle::make('multiple_routes')
                                    ->label('Múltiples Rutas')
                                    ->helperText('Permitir que el cliente tenga más de una ruta asignada')
                                    ->visible(function () {
                                        $user = Auth::user();
                                        $canView = false;
                                        switch ($user->role->name) {

                                            case 'Desarrollador':
                                                $canView = true;
                                                break;

                                            case 'Prestamista':
                                                $canView = true;
                                                break;

                                            default:
                                                $canView = false;
                                                break;
                                        }

                                        return $canView;
                                    }),
                            ])->columns(3)
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('first_name')
                    ->label('Nombre(s)')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_name')
                    ->label('Apellido(s)')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('dni_type')
                    ->label('Tipo Documento')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('dni')
                    ->label('Documento')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Correo Electrónico')
                    ->searchable(),
                Tables\Columns\IconColumn::make('active')
                    ->label('Activo')
                    ->sortable()
                    ->boolean(),
                Tables\Columns\IconColumn::make('multiple_routes')
                    ->label('Múltiples Rutas')
                    ->sortable()
                    ->boolean(),
                Tables\Columns\TextColumn::make('address')
                    ->label('Dirección')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('city')
                    ->label('Ciudad')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('state')
                    ->label('Estado/Departamento')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('country')
                    ->label('País')
                    ->searchable()
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
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
