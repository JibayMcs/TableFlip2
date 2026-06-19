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

    /**
     * Layout algorithm for the client-side Cytoscape render. NOT a #[Url]
     * property and never read server-side : switching it is pure client
     * work (the directive re-runs cy.layout()), so Alpine owns it and the
     * `?layout=` query param via history.replaceState. Seeded from the
     * request query in mount() for deeplinks.
     */
    public string $layout = 'dagre';

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

        // Seed the layout from the query string so deeplinks keep the choice;
        // after mount the Alpine toggle owns it (no server round-trip).
        $queryLayout = (string) request()->query('layout', '');
        if (in_array($queryLayout, ['dagre', 'fcose', 'cose'], true)) {
            $this->layout = $queryLayout;
        }
    }

    public function generate(CurrentConnection $current, ErdGenerator $generator): void
    {
        $this->error = null;
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

        $this->tableCount = $result['tableCount'];
        $this->relationshipCount = $result['relationshipCount'];
        $this->skippedTables = $result['skippedTables'];

        // Push the (potentially multi-MB) graph to the client as a one-shot
        // browser event instead of a public property. Keeping nodes/edges out
        // of the Livewire snapshot avoids carrying them in the DOM and echoing
        // them on every subsequent round-trip.
        $this->dispatch('erd:generated', nodes: $result['nodes'], edges: $result['edges']);
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
