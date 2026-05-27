import { Alpine, Livewire } from '../../vendor/livewire/livewire/dist/livewire.esm';

import { registerTooltip } from './alpine/tooltip';

registerTooltip(Alpine);

Livewire.start();
