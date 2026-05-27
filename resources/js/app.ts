import { Alpine, Livewire } from '../../vendor/livewire/livewire/dist/livewire.esm';

import { registerCytoscape } from './alpine/cytoscape';
import { registerSqlEditor } from './alpine/sql-editor';
import { registerTooltip } from './alpine/tooltip';
import { registerNavigationProgress } from './navigation-progress';

registerTooltip(Alpine);
registerSqlEditor(Alpine);
registerCytoscape(Alpine);
registerNavigationProgress();

Livewire.start();
