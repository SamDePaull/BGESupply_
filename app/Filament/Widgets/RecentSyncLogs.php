<?php

namespace App\Filament\Widgets;

use App\Models\SyncLog;
use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class RecentSyncLogs extends BaseWidget
{
    protected static ?string $heading = 'Recent Sync Logs';
    /** Matikan polling */
    protected static ?string $pollingInterval = null;
    protected int|string|array $columnSpan = 'full';
    protected function getTableQuery(): Builder
    {
        return SyncLog::query()->latest('id');
    }
    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('created_at')->dateTime('d M Y H:i')->label('Time')->sortable(),
            Tables\Columns\TextColumn::make('job')->label('Job')->searchable(),
            Tables\Columns\TextColumn::make('action')->label('Action')->badge(),
            Tables\Columns\TextColumn::make('http_status')->label('HTTP')->badge()
                ->color(fn($state) => ($state && $state >= 200 && $state < 300) ?
                    'success' : 'danger'),
            Tables\Columns\TextColumn::make('status')->label('Status')->badge()->colors([
                'success' => 'ok',
                'danger' => 'failed',
                'warning' => 'pending'
            ]),
            Tables\Columns\TextColumn::make('message')->label('Message')->limit(60)
                ->tooltip(fn($record) => $record->message),
        ];
    }
}
