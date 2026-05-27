import { Alpine, Livewire } from '../../vendor/livewire/livewire/dist/livewire.esm';

import { registerSqlEditor } from './alpine/sql-editor';
import { registerTooltip } from './alpine/tooltip';

registerTooltip(Alpine);
registerSqlEditor(Alpine);

Livewire.start();
