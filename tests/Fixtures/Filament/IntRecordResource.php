<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Tests\Fixtures\Filament;

use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Victorio\PublicLinks\Filament\RelationManagers\PublicLinksRelationManager;
use Victorio\PublicLinks\Tests\Fixtures\Filament\Pages\EditIntRecord;
use Victorio\PublicLinks\Tests\Fixtures\Filament\Pages\ListIntRecords;
use Victorio\PublicLinks\Tests\Fixtures\IntRecord;

final class IntRecordResource extends Resource
{
    protected static ?string $model = IntRecord::class;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('name')]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')]);
    }

    public static function getRelations(): array
    {
        return [PublicLinksRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIntRecords::route('/'),
            'edit' => EditIntRecord::route('/{record}/edit'),
        ];
    }
}
