<div class="w-full max-w-md">
    <div class="bg-crm-card border border-crm-border rounded-xl p-10 text-center animate-fade-in">
        {{-- Logo --}}
        <div class="mb-5">
            <svg viewBox="0 0 100 100" class="w-16 h-16 mx-auto">
                <rect width="100" height="100" fill="transparent"/>
                <path d="M20 90V10h35c20 0 32 12 32 28s-12 28-32 28H38v24H20zm18-40h15c10 0 16-6 16-14s-6-14-16-14H38v28z" fill="#111" stroke="#111" stroke-width="3"/>
            </svg>
        </div>
        <h1 class="text-2xl font-extrabold tracking-widest mb-1">PRIME CRM</h1>
        <p class="text-crm-t3 text-sm mb-6">Enter your credentials to log in</p>

        <form wire:submit="authenticate" class="text-left space-y-3">
            <div>
                <label class="block text-[10px] text-crm-t3 uppercase tracking-wide font-medium mb-1">Username</label>
                <input type="text" wire:model="username" placeholder="Enter username" autofocus
                       class="w-full bg-crm-card border border-crm-border rounded-md px-3 py-2.5 text-sm text-crm-t1 outline-none focus:border-blue-500 font-sans">
            </div>
            <div>
                <label class="block text-[10px] text-crm-t3 uppercase tracking-wide font-medium mb-1">Password (8 digit)</label>
                <input type="password" wire:model="password" placeholder="Enter password"
                       class="w-full bg-crm-card border border-crm-border rounded-md px-3 py-2.5 text-sm text-crm-t1 outline-none focus:border-blue-500 font-sans">
            </div>

            @if($error)
                <div class="text-red-500 text-xs font-semibold">{{ $error }}</div>
            @endif

            <button type="submit"
                    class="w-full bg-crm-t1 text-white rounded-md py-2.5 text-sm font-medium hover:bg-gray-800 transition mt-2"
                    wire:loading.attr="disabled">
                <span wire:loading.remove>Log In</span>
                <span wire:loading>Logging in...</span>
            </button>
        </form>
    </div>
</div>
