<?php
/**
 * Cookie Consent Pop-up Component
 * Premium Glassmorphism Design
 */
?>
<div x-data="{ 
    showConsent: false,
    init() {
        // Delay check slightly to ensure localStorage is available and Alpine is ready
        this.showConsent = !localStorage.getItem('cookie_consent_accepted');
    },
    accept() {
        localStorage.setItem('cookie_consent_accepted', 'true');
        this.showConsent = false;
    }
}" 
     x-show="showConsent"
     x-cloak
     x-transition:enter="transition ease-out duration-500"
     x-transition:enter-start="opacity-0 translate-y-10"
     x-transition:enter-end="opacity-100 translate-y-0"
     x-transition:leave="transition ease-in duration-300"
     x-transition:leave-start="opacity-100 translate-y-0"
     x-transition:leave-end="opacity-0 translate-y-10"
     class="fixed bottom-6 left-6 right-6 md:left-auto md:right-8 md:max-w-md z-[9999]">
    
    <div class="relative overflow-hidden rounded-2xl p-6 shadow-2xl border border-white/20 backdrop-blur-xl bg-slate-900/80 text-white">
        <!-- Decorative Gradient -->
        <div class="absolute -top-24 -right-24 w-48 h-48 bg-blue-500/20 rounded-full blur-3xl"></div>
        
        <div class="relative z-10">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0 w-12 h-12 rounded-full bg-blue-500/20 flex items-center justify-center text-blue-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                </div>
                <div>
                    <h4 class="text-lg font-bold mb-1">Privasi & Keamanan</h4>
                    <p class="text-sm text-slate-300 leading-relaxed">
                        Kami menggunakan cookie untuk meningkatkan keamanan sesi dan pengalaman pengguna Anda. Dengan melanjutkan, Anda menyetujui kebijakan privasi kami.
                    </p>
                </div>
            </div>
            
            <div class="mt-6 flex items-center justify-end gap-3">
                <button @click="accept()" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-xl transition-all hover:shadow-lg hover:shadow-blue-500/30 transform active:scale-95">
                    Saya Mengerti
                </button>
            </div>
        </div>
    </div>
</div>

