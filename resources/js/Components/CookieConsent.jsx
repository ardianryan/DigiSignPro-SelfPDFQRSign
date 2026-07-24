import { useEffect, useState } from 'react';

const STORAGE_KEY = 'cookie_consent_accepted';

export default function CookieConsent() {
    const [show, setShow] = useState(false);
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        try {
            if (!localStorage.getItem(STORAGE_KEY)) {
                setShow(true);
                // slight delay for transition
                requestAnimationFrame(() => setVisible(true));
            }
        } catch {
            // ignore storage errors
        }
    }, []);

    const accept = () => {
        try {
            localStorage.setItem(STORAGE_KEY, 'true');
        } catch {
            // ignore
        }
        setVisible(false);
        setTimeout(() => setShow(false), 300);
    };

    if (!show) return null;

    return (
        <div
            className={`fixed bottom-6 left-6 right-6 md:left-auto md:right-8 md:max-w-md z-[9999] transition-all duration-500 ${
                visible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-10'
            }`}
        >
            <div className="relative overflow-hidden rounded-2xl p-6 shadow-2xl border border-white/20 backdrop-blur-xl bg-slate-900/80 text-white">
                <div className="absolute -top-24 -right-24 w-48 h-48 bg-blue-500/20 rounded-full blur-3xl"></div>

                <div className="relative z-10">
                    <div className="flex items-start gap-4">
                        <div className="flex-shrink-0 w-12 h-12 rounded-full bg-blue-500/20 flex items-center justify-center text-blue-400">
                            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth="2"
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                                ></path>
                            </svg>
                        </div>
                        <div>
                            <h4 className="text-lg font-bold mb-1">Privasi & Keamanan</h4>
                            <p className="text-sm text-slate-300 leading-relaxed">
                                Kami menggunakan cookie untuk meningkatkan keamanan sesi dan pengalaman pengguna Anda.
                                Dengan melanjutkan, Anda menyetujui kebijakan privasi kami.
                            </p>
                        </div>
                    </div>

                    <div className="mt-6 flex items-center justify-end gap-3">
                        <button
                            type="button"
                            onClick={accept}
                            className="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-xl transition-all hover:shadow-lg hover:shadow-blue-500/30 active:scale-95"
                        >
                            Saya Mengerti
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
