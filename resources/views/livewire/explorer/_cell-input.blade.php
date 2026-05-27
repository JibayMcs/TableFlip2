@props([
    'column' => null,         // ColumnDefinition|null
    'wireModel',              // wire:model path (e.g. "insertDraft.email" or "editingValue")
    'inlineEdit' => false,    // adds Enter=save / Esc=cancel handlers + autofocus
])

@php
    use App\Domain\Database\ValueObjects\ColumnType;

    $type = $column?->type;
    $required = $column && ! $column->nullable && $column->default === null && ! $column->autoIncrement;

    $base = 'w-full text-xs font-mono border rounded px-1.5 py-1 focus:outline-none focus:border-zinc-900 '
        .($required ? 'border-rose-300' : 'border-zinc-300');

    $inlineAttrs = $inlineEdit
        ? 'x-init="$el.focus(); typeof $el.select === \'function\' && $el.select()"'
        : '';
@endphp

@switch ($type)
    @case (ColumnType::ENUM)
        <select wire:model="{{ $wireModel }}" {!! $inlineAttrs !!}
            @if ($inlineEdit) @change="$wire.saveEdit()" @keydown.escape="$wire.cancelEdit()" @endif
            class="{{ $base }}">
            @if ($column?->nullable)
                <option value="">— null —</option>
            @endif
            @foreach ($column->enumValues ?? [] as $v)
                <option value="{{ $v }}">{{ $v }}</option>
            @endforeach
        </select>
        @break

    @case (ColumnType::BOOLEAN)
        <select wire:model="{{ $wireModel }}" {!! $inlineAttrs !!}
            @if ($inlineEdit) @change="$wire.saveEdit()" @keydown.escape="$wire.cancelEdit()" @endif
            class="{{ $base }}">
            @if ($column?->nullable)
                <option value="">— null —</option>
            @endif
            <option value="1">true</option>
            <option value="0">false</option>
        </select>
        @break

    @case (ColumnType::DATE)
        <input type="date" wire:model="{{ $wireModel }}" {!! $inlineAttrs !!}
            @if ($inlineEdit) @keydown.enter.prevent="$wire.saveEdit()" @keydown.escape="$wire.cancelEdit()" @endif
            class="{{ $base }}" />
        @break

    @case (ColumnType::TIME)
        <input type="time" step="1" wire:model="{{ $wireModel }}" {!! $inlineAttrs !!}
            @if ($inlineEdit) @keydown.enter.prevent="$wire.saveEdit()" @keydown.escape="$wire.cancelEdit()" @endif
            class="{{ $base }}" />
        @break

    @case (ColumnType::DATETIME)
    @case (ColumnType::TIMESTAMP)
        <input type="datetime-local" step="1" wire:model="{{ $wireModel }}" {!! $inlineAttrs !!}
            @if ($inlineEdit) @keydown.enter.prevent="$wire.saveEdit()" @keydown.escape="$wire.cancelEdit()" @endif
            class="{{ $base }}" />
        @break

    @case (ColumnType::INTEGER)
        <input type="number" step="1" wire:model="{{ $wireModel }}" {!! $inlineAttrs !!}
            @if ($inlineEdit) @keydown.enter.prevent="$wire.saveEdit()" @keydown.escape="$wire.cancelEdit()" @endif
            placeholder="{{ $column?->nullable ? 'null' : '' }}"
            class="{{ $base }}" />
        @break

    @case (ColumnType::DECIMAL)
    @case (ColumnType::FLOAT)
        <input type="number" step="any" wire:model="{{ $wireModel }}" {!! $inlineAttrs !!}
            @if ($inlineEdit) @keydown.enter.prevent="$wire.saveEdit()" @keydown.escape="$wire.cancelEdit()" @endif
            placeholder="{{ $column?->nullable ? 'null' : '' }}"
            class="{{ $base }}" />
        @break

    @default
        <input type="text" wire:model="{{ $wireModel }}" {!! $inlineAttrs !!}
            @if ($inlineEdit) @keydown.enter.prevent="$wire.saveEdit()" @keydown.escape="$wire.cancelEdit()" @endif
            @if ($column?->length) maxlength="{{ $column->length }}" @endif
            placeholder="{{ $column?->nullable ? 'null' : '' }}"
            class="{{ $base }}" />
@endswitch
