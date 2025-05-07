<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\Company;
use App\Models\Role;
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
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationGroup = 'Administración';
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $label = 'Usuario';
    protected static ?string $pluralLabel = 'Usuarios';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = Auth::user();
        //dd($user->role->name !== 'Developer', $user->role->name);
        if ($user->role->name !== 'Desarrollador') {
            //dd($query);
            $query->where('company_id', $user->company_id)->where('id', '!=', $user->id);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()->schema([
                    Fieldset::make('Datos Usuario')
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->label('Nombre Usuario')
                                ->required()
                                ->maxLength(50),
                            Forms\Components\TextInput::make('email')
                                ->label('Correo Electrónico')
                                ->email()
                                ->required()
                                ->maxLength(50),
                            Forms\Components\TextInput::make('password')
                                ->label('Contraseña')
                                ->password()
                                ->dehydrateStateUsing(fn($state) => !empty($state) ? Hash::make($state) : null)
                                ->dehydrated(fn($state) => filled($state)) // Solo se enviará si se llena
                                ->required(fn(string $operation) => $operation === 'create')
                                ->revealable(),
                            Forms\Components\Select::make('company_id')
                                ->label('Compañía')
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
                                ->required(),
                            Forms\Components\Select::make('role_id')
                                ->label('Perfil')
                                ->options(
                                    function () {
                                        $user = Auth::user();
                                        if ($user->role->name === 'Desarrollador') {
                                            // Si el usuario es Desarrollador, muestra todos los usuarios con role_id 6
                                            return Role::all()->pluck('name', 'id');
                                        } else {
                                            // Si el usuario tiene una institución, muestra los usuarios con role_id 6 de su institución
                                            return Role::where('id', '>', $user->role_id)
                                                ->pluck('name', 'id');
                                        }
                                    }
                                )
                                ->required(),
                        ])
                        ->columns([
                            'sm' => 2,
                            'md' => 1,
                            'lg' => 1,
                            'xl' => 2,
                            '2xl' => 4,
                        ]),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre Usuario')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Correo')
                    ->searchable(),
                Tables\Columns\TextColumn::make('role.name')
                    ->label('Rol')
                    ->sortable(),
                Tables\Columns\TextColumn::make('company.name')
                    ->label('Compañía')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
