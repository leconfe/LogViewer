<?php

namespace LogViewer\Pages;

use App\Facades\Plugin;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Livewire\Attributes\Locked;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class LogViewerPage extends Page implements HasForms, HasActions
{
    use InteractsWithForms, InteractsWithActions;

    protected static ?string $title = 'Log Viewer';

    protected static string $view = 'LogViewer::log-viewer';

    protected static bool $shouldRegisterNavigation = false;

    #[Locked]
    public array $options = [];

    public ?string $logFile = null;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Select::make('logFile')
                ->label(false)
                ->placeholder('Select for a log file')
                ->lazy()
                ->searchable()
                ->options($this->getFileNames($this->getFinder()))
                ->afterStateUpdated(fn() => $this->refresh())
                ->suffixAction(
                    FormAction::make('copyCostToPrice')
                        ->icon('heroicon-c-arrow-down-tray')
                        ->disabled(!$this->logFile)
                        ->action(fn() => response()->download($this->logFile)),
                ),
        ]);
    }

    public static function canAccess(): bool
    {
        return auth()->user()->can('viewAny', Plugin::class);
    }

    public function refresh(): void
    {
        $this->dispatch('logContentUpdated', content: $this->read());
    }

    protected function getFileNames($files): Collection
    {
        return collect($files)->mapWithKeys(function (SplFileInfo $file) {
            return [$file->getRealPath() => $file->getFilename()];
        });
    }

    protected function getFinder(): Finder
    {
        return once(
            fn() => Finder::create()
                ->ignoreDotFiles(true)
                ->ignoreUnreadableDirs()
                ->files()
                ->sortByModifiedTime()
                ->in([storage_path('logs')])
                ->notName([]),
        );
    }

    public function read(): string
    {
        // check extension is log
        if (!$this->logFile || pathinfo($this->logFile, PATHINFO_EXTENSION) !== 'log') {
            $this->logFile = null;
            return '';
        }

        return mb_convert_encoding(File::get($this->logFile), 'UTF-8', 'UTF-8');
    }

    public function clear(): void
    {
        if (!$this->logFile || pathinfo($this->logFile, PATHINFO_EXTENSION) !== 'log') {
            $this->logFile = null;
            return;
        }

        File::put($this->logFile, '');
        $this->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $plugin = Plugin::getPlugin('LogViewer');

        return [
            'css' => $plugin->asset('index.css'),
            'js' => $plugin->asset('index.js'),
        ];
    }

    public function downloadAction(): Action
    {
        return Action::make('download')
            ->icon('heroicon-c-arrow-down-tray')
            ->disabled(!$this->logFile)
            ->action(fn() => response()->download($this->logFile));
    }

    public function clearAction(): Action
    {
        return Action::make('clear')
            ->icon('heroicon-o-trash')
            ->requiresConfirmation()
            ->disabled(!$this->logFile)
            ->action(fn() => response()->download($this->logFile));
    }
}
