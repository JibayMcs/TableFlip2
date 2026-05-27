<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">Users</h1>
        <button wire:click="openCreate"
            class="rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800">
            New user
        </button>
    </div>

    @if (session('admin_user_status'))
        <div class="rounded-md bg-emerald-50 border border-emerald-200 px-4 py-2 text-sm text-emerald-800">
            {{ session('admin_user_status') }}
        </div>
    @endif

    <div>
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by name or email…"
            class="w-full max-w-sm rounded-md border border-zinc-300 dark:border-zinc-700 px-3 py-2 text-sm" />
    </div>

    <div class="overflow-x-auto bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-lg">
        <table class="w-full text-sm">
            <thead class="text-left text-xs uppercase text-zinc-500 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-800">
                <tr>
                    <th class="px-4 py-2">Name</th>
                    <th class="px-4 py-2">Email</th>
                    <th class="px-4 py-2">Role</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $u)
                    <tr class="border-b border-zinc-100 dark:border-zinc-800 last:border-0" wire:key="user-{{ $u->id }}">
                        <td class="px-4 py-2">{{ $u->name }}</td>
                        <td class="px-4 py-2">{{ $u->email }}</td>
                        <td class="px-4 py-2">{{ $u->roles->pluck('name')->join(', ') ?: '—' }}</td>
                        <td class="px-4 py-2">
                            @if ($u->is_active)
                                <span class="inline-flex items-center gap-1 text-emerald-700 text-xs">
                                    <span class="size-1.5 rounded-full bg-emerald-500"></span> Active
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 text-zinc-500 dark:text-zinc-400 text-xs">
                                    <span class="size-1.5 rounded-full bg-zinc-400"></span> Disabled
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right space-x-3">
                            <button wire:click="toggleActive({{ $u->id }})"
                                class="text-xs text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:text-zinc-100">
                                {{ $u->is_active ? 'Disable' : 'Enable' }}
                            </button>
                            <button wire:click="resetPassword({{ $u->id }})"
                                wire:confirm="Reset password for {{ $u->email }}?"
                                class="text-xs text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:text-zinc-100">
                                Reset password
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div>{{ $users->links() }}</div>

    @if ($showCreate)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-900/40 p-4" wire:click.self="$set('showCreate', false)">
            <div class="bg-white dark:bg-zinc-900 rounded-lg shadow-lg max-w-md w-full p-6 space-y-4">
                <h2 class="text-lg font-semibold">Create user</h2>
                <form wire:submit="createUser" class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Name</label>
                        <input type="text" wire:model="newName" required
                            class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 px-3 py-2 text-sm" />
                        @error('newName') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Email</label>
                        <input type="email" wire:model="newEmail" required
                            class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 px-3 py-2 text-sm" />
                        @error('newEmail') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Role</label>
                        <select wire:model="newRole"
                            class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 px-3 py-2 text-sm">
                            <option value="user">user</option>
                            <option value="admin">admin</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Initial password (leave empty to auto-generate)</label>
                        <input type="text" wire:model="newPassword"
                            class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 px-3 py-2 text-sm font-mono" />
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="$set('showCreate', false)"
                            class="px-3 py-2 text-sm text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:text-zinc-100">Cancel</button>
                        <button type="submit"
                            class="rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800">Create</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
