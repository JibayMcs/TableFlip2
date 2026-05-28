<?php

declare(strict_types=1);

namespace App\Livewire\Admin\TableOperations;

use App\Models\TableOperation;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'op', except: '')]
    public string $opFilter = '';

    #[Url(as: 'kind', except: '')]
    public string $kindFilter = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedOpFilter(): void
    {
        $this->resetPage();
    }

    public function updatedKindFilter(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $operations = TableOperation::query()
            ->when($this->search !== '', fn ($q) => $q->where(function ($q): void {
                $q->where('table_name', 'like', "%{$this->search}%")
                    ->orWhere('database_name', 'like', "%{$this->search}%")
                    ->orWhere('user_identifier', 'like', "%{$this->search}%")
                    ->orWhere('sql_text', 'like', "%{$this->search}%");
            }))
            ->when($this->opFilter !== '', fn ($q) => $q->where('operation', $this->opFilter))
            ->when($this->kindFilter !== '', fn ($q) => $q->where('user_kind', $this->kindFilter))
            ->orderByDesc('performed_at')
            ->paginate(25);

        return view('livewire.admin.table-operations.index', [
            'operations' => $operations,
            'opChoices' => ['insert', 'update', 'delete', 'truncate', 'drop'],
            'kindChoices' => ['web', 'direct_db'],
        ]);
    }
}
