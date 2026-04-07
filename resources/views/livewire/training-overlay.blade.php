{{-- Global Training Walkthrough Overlay --}}
{{-- This component is loaded on every authenticated page via the layout --}}
<div>
@if($walkthroughData)
<div
    x-data="trainingEngine(@js($walkthroughData))"
    x-show="active"
    x-cloak
    class="training-overlay-root"
    style="display:none"
>
    {{-- Dim overlay (click to dismiss optionally) --}}
    <div x-show="currentStep?.dim_background !== false" x-transition.opacity
         class="fixed inset-0 z-[99990]" style="display:none">
        {{-- SVG mask to create hole around targeted element --}}
        <svg x-ref="overlayMask" class="absolute inset-0 w-full h-full" style="pointer-events:none">
            <defs>
                <mask id="training-mask">
                    <rect width="100%" height="100%" fill="white"/>
                    <rect x="0" y="0" width="0" height="0" rx="8" fill="black" x-ref="maskHole"/>
                </mask>
            </defs>
            <rect width="100%" height="100%" fill="rgba(0,0,0,0.55)" mask="url(#training-mask)" style="pointer-events:all"/>
        </svg>
    </div>

    {{-- Highlight ring around target element --}}
    <div x-show="highlightRect" x-transition
         class="fixed z-[99991] pointer-events-none rounded-lg"
         :style="`top:${highlightRect?.top - 4}px; left:${highlightRect?.left - 4}px; width:${highlightRect?.width + 8}px; height:${highlightRect?.height + 8}px; box-shadow: 0 0 0 3px rgba(59,130,246,0.6), 0 0 20px rgba(59,130,246,0.3); transition: all 0.3s ease;`"
         style="display:none">
    </div>

    {{-- Tooltip Card --}}
    <div x-ref="tooltipCard" x-show="showTooltip" x-transition
         class="fixed z-[99995] w-80 bg-white rounded-xl shadow-2xl border border-gray-200 overflow-hidden"
         :style="tooltipStyle"
         style="display:none">
        {{-- Header --}}
        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="text-white text-sm" x-text="currentStep?.icon || '📌'"></span>
                    <span class="text-white text-[10px] font-semibold uppercase tracking-wider" x-text="`Step ${stepIndex + 1} of ${totalSteps}`"></span>
                </div>
                <button @click="dismiss()" class="text-white/70 hover:text-white text-sm">&times;</button>
            </div>
            <h4 class="text-white font-bold text-sm mt-1" x-text="currentStep?.title"></h4>
        </div>

        {{-- Progress bar --}}
        <div class="h-1 bg-gray-100">
            <div class="h-full bg-blue-500 transition-all duration-500" :style="`width: ${Math.round((stepIndex + 1) / totalSteps * 100)}%`"></div>
        </div>

        {{-- Content --}}
        <div class="px-4 py-3">
            <p class="text-xs text-gray-600 leading-relaxed" x-text="currentStep?.description"></p>

            {{-- Screenshot --}}
            <template x-if="currentStep?.image_path">
                <div class="mt-2 rounded-lg overflow-hidden border border-gray-200 bg-gray-50">
                    <img :src="`/storage/${currentStep.image_path}`" class="w-full max-h-40 object-contain">
                    <template x-if="currentStep?.image_caption">
                        <div class="px-2 py-1 text-[9px] text-gray-500 bg-white border-t border-gray-200" x-text="currentStep.image_caption"></div>
                    </template>
                </div>
            </template>

            {{-- Tip --}}
            <template x-if="currentStep?.tip_text">
                <div class="mt-2 bg-amber-50 border border-amber-200 rounded-lg px-2.5 py-1.5 flex items-start gap-1.5">
                    <span class="text-amber-500 text-[10px] flex-shrink-0">💡</span>
                    <p class="text-[10px] text-amber-800" x-text="currentStep.tip_text"></p>
                </div>
            </template>

            {{-- Action hint --}}
            <template x-if="currentStep?.step_type === 'action'">
                <div class="mt-2 bg-blue-50 border border-blue-200 rounded-lg px-2.5 py-1.5">
                    <p class="text-[10px] text-blue-700 font-semibold">Click the highlighted element to continue</p>
                </div>
            </template>
        </div>

        {{-- Navigation --}}
        <div class="border-t border-gray-100 px-4 py-2.5 bg-gray-50 flex items-center justify-between">
            <div class="flex items-center gap-1.5">
                <button @click="prevStep()" x-show="stepIndex > 0"
                    class="px-3 py-1.5 text-[10px] font-semibold text-gray-500 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition" style="display:none">
                    &larr; Back
                </button>
            </div>
            <div class="flex items-center gap-1.5">
                <template x-if="allowSkip">
                    <button @click="skipCurrent()" class="px-3 py-1.5 text-[10px] font-semibold text-gray-400 hover:text-gray-600 transition">Skip</button>
                </template>
                <button @click="completeCurrent()"
                    class="px-4 py-1.5 text-[10px] font-bold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition">
                    <span x-text="stepIndex >= totalSteps - 1 ? 'Finish' : (currentStep?.step_type === 'action' ? 'Done' : 'Next →')"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('trainingEngine', (data) => ({
        active: true,
        steps: data?.steps || [],
        stepIndex: data?.current_step_index ?? 0,
        totalSteps: data?.total ?? 0,
        allowSkip: data?.allow_skip ?? true,
        flowId: data?.flow_id,
        highlightRect: null,
        showTooltip: false,
        tooltipStyle: '',
        resizeObserver: null,

        get currentStep() {
            return this.steps[this.stepIndex] || null;
        },

        init() {
            if (!this.steps.length || !data) {
                this.active = false;
                return;
            }
            this.$nextTick(() => this.focusStep());

            // Re-position on resize/scroll
            const reposition = () => { if (this.active) this.focusStep(); };
            window.addEventListener('resize', reposition);
            window.addEventListener('scroll', reposition, true);

            // Watch for Livewire DOM updates
            document.addEventListener('livewire:navigated', () => {
                setTimeout(() => this.focusStep(), 300);
            });
        },

        focusStep() {
            const step = this.currentStep;
            if (!step) { this.active = false; return; }

            const selector = step.target_selector;
            let el = null;

            if (selector) {
                el = document.querySelector(selector);

                // If targeting a nav item inside the hamburger drawer, open the drawer first
                if (!el && selector.startsWith('[data-training="nav-')) {
                    // Try opening the drawer via Alpine
                    const body = document.querySelector('[x-data]');
                    if (body && body.__x) {
                        body.__x.$data.drawerOpen = true;
                    } else {
                        // Fallback: click the hamburger button
                        const hamburger = document.querySelector('header button');
                        if (hamburger) hamburger.click();
                    }
                    // Wait for drawer transition then retry
                    setTimeout(() => {
                        el = document.querySelector(selector);
                        if (el) this._highlightElement(el, step);
                        else this._showCentered(step);
                    }, 350);
                    return;
                }
            }

            if (el && step.highlight_element !== false) {
                this._highlightElement(el, step);
            } else {
                this._showCentered(step);
            }
        },

        _highlightElement(el, step) {
            if (el && step.highlight_element !== false) {
                // Scroll into view
                if (step.auto_scroll !== false) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }

                setTimeout(() => {
                    const rect = el.getBoundingClientRect();
                    this.highlightRect = {
                        top: rect.top + window.scrollY,
                        left: rect.left + window.scrollX,
                        width: rect.width,
                        height: rect.height,
                    };

                    // Update SVG mask hole
                    const hole = this.$refs.maskHole;
                    if (hole) {
                        hole.setAttribute('x', rect.left - 6);
                        hole.setAttribute('y', rect.top - 6);
                        hole.setAttribute('width', rect.width + 12);
                        hole.setAttribute('height', rect.height + 12);
                    }

                    this.positionTooltip(rect, step.tooltip_position || 'bottom');
                    this.showTooltip = true;

                    // For action steps, listen for click on target
                    if (step.step_type === 'action' && step.action_event === 'click') {
                        el.style.position = el.style.position || 'relative';
                        el.style.zIndex = '99993';
                        el.style.pointerEvents = 'auto';
                    }
                }, 350);
            }
        },

        _showCentered(step) {
            this.highlightRect = null;
            const hole = this.$refs.maskHole;
            if (hole) {
                hole.setAttribute('width', 0);
                hole.setAttribute('height', 0);
            }
            this.tooltipStyle = 'top: 50%; left: 50%; transform: translate(-50%, -50%);';
            this.showTooltip = true;
        },

        positionTooltip(rect, position) {
            const gap = 12;
            const cardW = 320;
            const cardH = 280;
            let top, left;

            switch (position) {
                case 'top':
                    top = rect.top + window.scrollY - cardH - gap;
                    left = rect.left + window.scrollX + rect.width / 2 - cardW / 2;
                    break;
                case 'left':
                    top = rect.top + window.scrollY + rect.height / 2 - cardH / 2;
                    left = rect.left + window.scrollX - cardW - gap;
                    break;
                case 'right':
                    top = rect.top + window.scrollY + rect.height / 2 - cardH / 2;
                    left = rect.right + window.scrollX + gap;
                    break;
                default: // bottom
                    top = rect.bottom + window.scrollY + gap;
                    left = rect.left + window.scrollX + rect.width / 2 - cardW / 2;
            }

            // Keep in viewport
            top = Math.max(10, Math.min(top, window.innerHeight + window.scrollY - cardH - 10));
            left = Math.max(10, Math.min(left, window.innerWidth - cardW - 10));

            this.tooltipStyle = `top:${top}px; left:${left}px;`;
        },

        completeCurrent() {
            const step = this.currentStep;
            if (!step) return;
            this.$wire.completeStep(step.id);
            this.steps[this.stepIndex].status = 'completed';
            this.nextStep();
        },

        skipCurrent() {
            const step = this.currentStep;
            if (!step) return;
            this.$wire.skipStep(step.id);
            this.steps[this.stepIndex].status = 'skipped';
            this.nextStep();
        },

        nextStep() {
            this.showTooltip = false;
            this.resetHighlight();

            // Find next not_started step
            for (let i = this.stepIndex + 1; i < this.steps.length; i++) {
                if (this.steps[i].status === 'not_started') {
                    this.stepIndex = i;
                    setTimeout(() => this.focusStep(), 200);
                    return;
                }
            }

            // All done
            this.active = false;
        },

        prevStep() {
            if (this.stepIndex <= 0) return;
            this.showTooltip = false;
            this.resetHighlight();
            this.stepIndex--;
            setTimeout(() => this.focusStep(), 200);
        },

        dismiss() {
            this.active = false;
            this.resetHighlight();
            this.$wire.dismissTraining();
        },

        resetHighlight() {
            this.highlightRect = null;
            const hole = this.$refs.maskHole;
            if (hole) {
                hole.setAttribute('width', 0);
                hole.setAttribute('height', 0);
            }
            // Reset z-index on any elevated elements
            document.querySelectorAll('[style*="z-index: 99993"]').forEach(el => {
                el.style.zIndex = '';
            });
        },
    }));
});
</script>
@endif
</div>
