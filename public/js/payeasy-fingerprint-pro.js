const settings = (window.PayeasyFingerprint || {});
const publicKey = settings.publicKey;

window.PayeasyFingerprint = window.PayeasyFingerprint || {};

if (!publicKey) {
    window.PayeasyFingerprint.cookiesAvailable = false;
    window.PayeasyFingerprint.isAllowed = false;
    window.PayeasyFingerprint.getResult = async () => ({ visitorId: '', requestId: '' });
    window.PayeasyFingerprint.getVisitorId = async () => '';
    window.PayeasyFingerprint.getRequestId = async () => '';
} else {
    window.PayeasyFingerprint._result = window.PayeasyFingerprint._result || null; // { visitorId, requestId }
    window.PayeasyFingerprint._resultPromise = window.PayeasyFingerprint._resultPromise || null;

    const LS_KEY = 'payeasy_fp_cache_v1';
    const LS_TTL_MS = 60 * 60 * 1000;

    function safeCookieGet(name) {
        try {
            const all = typeof document.cookie === 'string' ? document.cookie : '';
            if (!all) return null;
            const parts = all.split(';');
            for (const p of parts) {
                const s = p.trim();
                if (s.startsWith(name + '=')) {
                    return decodeURIComponent(s.slice(name.length + 1));
                }
            }
            return null;
        } catch (e) {
            return null;
        }
    }

    function safeCookieSet(name, value, maxAgeSeconds) {
        try {
            if (typeof value !== 'string') return;
            const secure = window.location?.protocol === 'https:' ? '; Secure' : '';
            document.cookie =
                name +
                '=' +
                encodeURIComponent(value) +
                '; Max-Age=' +
                String(maxAgeSeconds) +
                '; Path=/; SameSite=Lax' +
                secure;
        } catch (e) {}
    }

    function areCookiesAvailable() {
        try {
            const testKey = '__payeasy_cookie_test__';
            const testVal = String(Date.now());
            // Try set then read back.
            safeCookieSet(testKey, testVal, 60);
            const got = safeCookieGet(testKey);
            // Best-effort cleanup.
            try { document.cookie = testKey + '=; Max-Age=0; Path=/; SameSite=Lax' + (window.location?.protocol === 'https:' ? '; Secure' : ''); } catch (e) {}
            return got === testVal;
        } catch (e) {
            return false;
        }
    }

    const cookiesOk = areCookiesAvailable();
    window.PayeasyFingerprint.cookiesAvailable = cookiesOk;
    window.PayeasyFingerprint.isAllowed = cookiesOk;

    if (!cookiesOk) {
        // If cookies are blocked, we do not call Fingerprint at all.
        window.PayeasyFingerprint.getResult = async () => ({ visitorId: '', requestId: '' });
        window.PayeasyFingerprint.getVisitorId = async () => '';
        window.PayeasyFingerprint.getRequestId = async () => '';
    } else {
        const fpPromise = import(`https://fpjscdn.net/v3/${publicKey}`)
            .then((FingerprintJS) => FingerprintJS.load());

        function loadFromDomainCache() {
            const raw = safeCookieGet(LS_KEY);
            if (!raw) return null;
            try {
                const parsed = JSON.parse(raw);
                const visitorId = typeof parsed?.visitorId === 'string' ? parsed.visitorId : '';
                const requestId = typeof parsed?.requestId === 'string' ? parsed.requestId : '';
                const ts = typeof parsed?.ts === 'number' ? parsed.ts : 0;
                if (!visitorId || !requestId || !ts) return null;
                if (Date.now() - ts > LS_TTL_MS) return null;
                return { visitorId, requestId };
            } catch (e) {
                return null;
            }
        }

        function saveToDomainCache(result) {
            if (!result?.visitorId || !result?.requestId) return;
            const payload = JSON.stringify({ visitorId: result.visitorId, requestId: result.requestId, ts: Date.now() });
            safeCookieSet(LS_KEY, payload, Math.ceil(LS_TTL_MS / 1000));
        }

        function fillInputs(result) {
            try {
                if (result?.visitorId) {
                    document.querySelectorAll('input[name="fp_visitor_id"]').forEach((el) => {
                        try { el.value = result.visitorId; } catch (e) {}
                    });
                }
                if (result?.requestId) {
                    document.querySelectorAll('input[name="fp_request_id"]').forEach((el) => {
                        try { el.value = result.requestId; } catch (e) {}
                    });
                }
            } catch (e) {}
        }

        window.PayeasyFingerprint.getResult = () => {
            if (window.PayeasyFingerprint._result) {
                return Promise.resolve(window.PayeasyFingerprint._result);
            }

            const cached = loadFromDomainCache();
            if (cached) {
                window.PayeasyFingerprint._result = cached;
                window.PayeasyFingerprint.visitorId = cached.visitorId;
                window.PayeasyFingerprint.requestId = cached.requestId;
                return Promise.resolve(cached);
            }

            if (window.PayeasyFingerprint._resultPromise) {
                return window.PayeasyFingerprint._resultPromise;
            }

            window.PayeasyFingerprint._resultPromise = (async () => {
                try {
                    const fp = await fpPromise;
                    const r = await fp.get();
                    const visitorId = r?.visitorId || '';
                    const requestId = r?.requestId || '';
                    const result = { visitorId, requestId };
                    if (visitorId && requestId) {
                        window.PayeasyFingerprint._result = result;
                        window.PayeasyFingerprint.visitorId = visitorId;
                        window.PayeasyFingerprint.requestId = requestId;
                        saveToDomainCache(result);
                    }
                    return result;
                } catch (e) {
                    return { visitorId: '', requestId: '' };
                }
            })();

            return window.PayeasyFingerprint._resultPromise;
        };

        window.PayeasyFingerprint.getVisitorId = async () => (await window.PayeasyFingerprint.getResult()).visitorId || '';
        window.PayeasyFingerprint.getRequestId = async () => (await window.PayeasyFingerprint.getResult()).requestId || '';

        // Run immediately on page load (uses domain cache if available).
        const run = () => {
            window.PayeasyFingerprint.getResult().then((result) => {
                fillInputs(result);
                try {
                    document.dispatchEvent(new CustomEvent('payeasy:fingerprint', { detail: result }));
                } catch (e) {}
            });
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', run);
        } else {
            run();
        }
    }
}

