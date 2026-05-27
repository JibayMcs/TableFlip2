<x-layouts.app :title="config('app.name')">
    <div class="space-y-6">
        <h1 class="text-2xl font-semibold tracking-tight">Welcome to {{ config('app.name') }}</h1>

        <p class="text-zinc-600 max-w-2xl">
            A self-hosted database studio for MySQL, PostgreSQL, SQLite and SQL Server.
            Authentication, connection management and the SQL workspace are coming in the next phases.
        </p>
    </div>
</x-layouts.app>
