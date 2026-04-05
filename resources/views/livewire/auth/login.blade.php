<div class="relative w-full min-h-screen flex items-center justify-center overflow-hidden">

    {{-- Left Logo — decorative background --}}
    <div class="hidden lg:flex absolute left-0 top-0 bottom-0 items-center justify-center" style="width:45%;">
        <img src="{{ asset('images/prime-logo.png') }}" alt="" class="w-[500px] h-[500px] object-contain select-none pointer-events-none" style="opacity:0.08;filter:blur(1.5px);">
    </div>

    {{-- Right Logo — decorative background --}}
    <div class="hidden lg:flex absolute right-0 top-0 bottom-0 items-center justify-center" style="width:45%;">
        <img src="{{ asset('images/prime-logo.png') }}" alt="" class="w-[500px] h-[500px] object-contain select-none pointer-events-none" style="opacity:0.08;filter:blur(1.5px);">
    </div>

    {{-- Login Card — main focus --}}
    <div class="relative z-10 w-full max-w-md mx-4">
        <div class="bg-white/95 backdrop-blur-sm border border-gray-200 rounded-2xl shadow-2xl p-10 text-center">
            {{-- Logo --}}
            <div class="mb-6">
                <img src="{{ asset('images/prime-logo.png') }}" alt="Prime" class="w-20 h-20 mx-auto object-contain" onerror="this.style.display='none'">
            </div>
            <h1 class="text-2xl font-extrabold tracking-widest mb-1 text-gray-900">PRIME CRM</h1>
            <p class="text-gray-400 text-sm mb-8">Enter your credentials to log in</p>

            <form wire:submit="authenticate" class="text-left space-y-4">
                <div>
                    <label for="fld-username" class="block text-[10px] text-gray-400 uppercase tracking-wider font-semibold mb-1.5">Username</label>
                    <input id="fld-username" type="text" wire:model="username" placeholder="Enter username" autofocus
                           class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition font-sans">
                </div>
                <div>
                    <label for="fld-password" class="block text-[10px] text-gray-400 uppercase tracking-wider font-semibold mb-1.5">Password</label>
                    <input id="fld-password" type="password" wire:model="password" placeholder="Enter password"
                           class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition font-sans">
                </div>

                @if($error)
                    <div class="text-red-500 text-xs font-semibold bg-red-50 border border-red-200 rounded-lg px-3 py-2">{{ $error }}</div>
                @endif

                <button type="submit"
                        class="w-full bg-gray-900 text-white rounded-xl py-3 text-sm font-semibold hover:bg-gray-800 active:scale-[0.98] transition-all mt-2 shadow-lg shadow-gray-900/20"
                        wire:loading.attr="disabled">
                    <span wire:loading.remove>Log In</span>
                    <span wire:loading>Logging in...</span>
                </button>
            </form>
        </div>

        {{-- Mobile: logo below card --}}
        <div class="lg:hidden flex justify-center mt-8">
            <img src="{{ asset('images/prime-logo.png') }}" alt="Prime" class="w-24 h-24 object-contain" style="opacity:0.15;">
        </div>
    </div>
</div>
