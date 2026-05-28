<?php

declare(strict_types=1);

namespace App\Livewire\Visualizer;

use App\Application\Connections\CurrentConnection;
use App\Application\Schema\ErdGenerator;
use App\Application\Schema\SchemaIntrospectionService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

/**
 * Render the ER diagram of a database with Cytoscape (client-side).
 *
 * The component intentionally does not auto-generate on mount: on huge ERP
 * schemas (Sage SS2I has 300+ tables) introspection alone is several
 * seconds. The user picks a DB, an optional compact toggle, and clicks
 * Generate.
 */
#[Layout('components.layouts.app', ['flush' => true])]
class Index extends Component
{
    #[Url(as: 'db', except: null)]
    public ?string $database = null;

    #[Url(as: 'compact', except: false)]
    public bool $compact = false;

    #[Url(as: 'layout', except: 'dagre')]
    public string $layout = 'dagre';

    /** @var list<array<string, mixed>> */
    public array $nodes = [];

    /** @var list<array<string, mixed>> */
    public array $edges = [];

    public ?string $error = null;

    public int $tableCount = 0;

    public int $relationshipCount = 0;

    /** @var list<string> */
    public array $skippedTables = [];

    public function mount(CurrentConnection $current): void
    {
        if ($current->driver() === null) {
            $this->redirect(route('login'), navigate: true);

            return;
        }

        if ($this->database === null) {
            $this->database = $current->defaultDatabase();
        }
    }

    public function generate(CurrentConnection $current, ErdGenerator $generator): void
    {
        $this->error = null;
        $this->nodes = [];
        $this->edges = [];
        $this->tableCount = 0;
        $this->relationshipCount = 0;
        $this->skippedTables = [];

        $driver = $current->driver();
        if ($driver === null) {
            $this->error = 'No active connection.';

            return;
        }
        if ($this->database === null || $this->database === '') {
            $this->error = 'Pick a database first.';

            return;
        }

        try {
            $result = $generator->generate($driver, $this->database, compact: $this->compact);
        } catch (Throwable $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->nodes = $result['nodes'];
        $this->edges = $result['edges'];
        $this->tableCount = $result['tableCount'];
        $this->relationshipCount = $result['relationshipCount'];
        $this->skippedTables = $result['skippedTables'];
    }

    public function render(CurrentConnection $current, SchemaIntrospectionService $schema): View
    {
        $driver = $current->driver();
        $databases = [];
        if ($driver !== null) {
            try {
                $databases = $schema->databases($driver);
            } catch (Throwable) {
                $databases = [];
            }
        }

        return view('livewire.visualizer.index', [
            'currentLabel' => $current->label(),
            'databases' => $databases,
        ]);
    }
}
