<?php

declare(strict_types=1);

namespace App\Livewire\Admin\QueryHistory;

use App\Models\QueryHistory;
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

    #[Url(as: 'status', except: '')]
    public string $statusFilter = '';

    #[Url(as: 'kind', except: '')]
    public string $kindFilter = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedKindFilter(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $entries = QueryHistory::query()
            ->when($this->search !== '', fn ($q) => $q->where(function ($q): void {
                $q->where('sql_text', 'like', "%{$this->search}%")
                    ->orWhere('database_name', 'like', "%{$this->search}%")
                    ->orWhere('user_identifier', 'like', "%{$this->search}%");
            }))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->kindFilter !== '', fn ($q) => $q->where('user_kind', $this->kindFilter))
            ->orderByDesc('executed_at')
            ->paginate(25);

        return view('livewire.admin.query-history.index', [
            'entries' => $entries,
            'statusChoices' => ['success', 'error'],
            'kindChoices' => ['web', 'direct_db'],
        ]);
    }
}
