<div class="w-full flex items-center justify-center gap-8 px-4">
    {{-- Left Logo --}}
    <div class="hidden lg:flex items-center justify-center flex-shrink-0">
        <img src="{{ asset('images/prime-logo.png') }}" alt="Prime" class="w-40 h-40 object-contain opacity-90">
    </div>

    {{-- Login Card --}}
    <div class="w-full max-w-md">
        <div class="bg-crm-card border border-crm-border rounded-xl p-10 text-center animate-fade-in">
            {{-- Logo --}}
            <div class="mb-5">
                <img src="{{ asset('images/prime-logo.png') }}" alt="Prime" class="w-16 h-16 mx-auto object-contain" onerror="this.style.display='none'">
            </div>
            <h1 class="text-2xl font-extrabold tracking-widest mb-1">PRIME CRM</h1>
            <p class="text-crm-t3 text-sm mb-6">Enter your credentials to log in</p>

            <form wire:submit="authenticate" class="text-left space-y-3">
                <div>
                    <label for="fld-username" class="block text-[10px] text-crm-t3 uppercase tracking-wide font-medium mb-1">Username</label>
                    <input id="fld-username" type="text" wire:model="username" placeholder="Enter username" autofocus
                           class="w-full bg-crm-card border border-crm-border rounded-md px-3 py-2.5 text-sm text-crm-t1 outline-none focus:border-blue-500 font-sans">
                </div>
                <div>
                    <label for="fld-password" class="block text-[10px] text-crm-t3 uppercase tracking-wide font-medium mb-1">Password (8 digit)</label>
                    <input id="fld-password" type="password" wire:model="password" placeholder="Enter password"
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

    {{-- Right Logo --}}
    <div class="hidden lg:flex items-center justify-center flex-shrink-0">
        <img src="{{ asset('images/prime-logo.png') }}" alt="Prime" class="w-40 h-40 object-contain opacity-90">
    </div>
</div>
