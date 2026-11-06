/**
 * Device Vault & Persistent Fingerprint Engine
 * منصة الأستاذ محمد يوسف التعليمية
 * Manages persistent client-side device identification across localStorage, cookies & forms.
 */
(function() {
    'use strict';

    const COOKIE_NAME = 'my_device_uuid';
    const NAME_COOKIE = 'my_device_name';
    const STORAGE_KEY = 'my_device_uuid';
    const NAME_STORAGE = 'my_device_name';
    const TEN_YEARS_SEC = 10 * 365 * 24 * 60 * 60;

    function getCookie(name) {
        try {
            const match = document.cookie.match(new RegExp('(^|;\\s*)' + name + '=([^;]+)'));
            return match ? decodeURIComponent(match[2]) : null;
        } catch(e) {
            return null;
        }
    }

    function setCookie(name, value, seconds) {
        try {
            const expires = new Date(Date.now() + (seconds * 1000)).toUTCString();
            const isSecure = window.location.protocol === 'https:';
            document.cookie = name + '=' + encodeURIComponent(value) +
                '; expires=' + expires +
                '; path=/' +
                '; SameSite=Lax' +
                (isSecure ? '; Secure' : '');
        } catch(e) {}
    }

    function generateSecureUUID() {
        if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
            return crypto.randomUUID().replace(/-/g, '');
        }
        if (typeof crypto !== 'undefined' && typeof crypto.getRandomValues === 'function') {
            const bytes = new Uint8Array(16);
            crypto.getRandomValues(bytes);
            return Array.from(bytes).map(b => b.toString(16).padStart(2, '0')).join('');
        }
        return 'dev_' + Math.random().toString(36).substring(2, 15) + Date.now().toString(36);
    }

    // Friendly Device Name Detection
    function detectDeviceFriendlyName() {
        const ua = navigator.userAgent || '';

        // Android Phone Models
        if (/Android/i.test(ua)) {
            // Check for popular brand markers in UA
            if (/SM-[A-Z0-9]+/i.test(ua) || /Samsung/i.test(ua)) {
                const match = ua.match(/SM-([A-Z0-9]+)/i);
                return match ? 'Samsung ' + match[1] : 'سامسونج جالاكسي';
            }
            if (/Redmi/i.test(ua)) {
                const match = ua.match(/Redmi[^;\)]+/i);
                return match ? match[0] : 'شاومي ريدمي';
            }
            if (/POCO/i.test(ua)) {
                const match = ua.match(/POCO[^;\)]+/i);
                return match ? match[0] : 'شاومي بوكو';
            }
            if (/Xiaomi/i.test(ua) || /Mi\s/i.test(ua)) {
                const match = ua.match(/Mi\s[^;\)]+/i);
                return match ? match[0] : 'شاومي Xiaomi';
            }
            if (/Oppo|CPH\d+/i.test(ua)) {
                return 'أوبو Oppo';
            }
            if (/Realme|RMX\d+/i.test(ua)) {
                return 'ريلمي Realme';
            }
            if (/Vivo|V\d{4}/i.test(ua)) {
                return 'فيفو Vivo';
            }
            if (/Huawei|HONOR|HMA-|VOG-/i.test(ua)) {
                return 'هواوي / هونر';
            }
            if (/Infinix|X\d{3}/i.test(ua)) {
                return 'انفنكس Infinix';
            }
            if (/Tecno/i.test(ua)) {
                return 'تكنو Tecno';
            }
            if (/Pixel/i.test(ua)) {
                const match = ua.match(/Pixel\s?[A-Za-z0-9]*/i);
                return match ? 'Google ' + match[0] : 'جوجل بيكسل';
            }

            // General Android model extraction
            const generalMatch = ua.match(/Android\s+[\d\.]+;\s*([^;\)]+?)(?:\s+Build|\))/i);
            if (generalMatch && generalMatch[1] && generalMatch[1].length < 30) {
                return 'هاتف (' + generalMatch[1].trim() + ')';
            }
            return 'هاتف أندرويد';
        }

        // Apple Devices
        if (/iPhone/i.test(ua)) {
            return 'آيفون Apple';
        }
        if (/iPad/i.test(ua)) {
            return 'آيباد Apple';
        }

        // Desktop OS
        if (/Windows NT 10.0/i.test(ua)) {
            return 'كمبيوتر (Windows 10/11)';
        }
        if (/Windows/i.test(ua)) {
            return 'كمبيوتر (Windows)';
        }
        if (/Macintosh|Mac OS X/i.test(ua)) {
            return 'جهاز ماك (macOS)';
        }
        if (/CrOS/i.test(ua)) {
            return 'كروم بوك (ChromeOS)';
        }
        if (/Linux/i.test(ua)) {
            return 'كمبيوتر (Linux)';
        }

        return 'جهاز تصفح';
    }

    // Initialize or Cross-heal UUID
    let uuid = null;
    try {
        uuid = localStorage.getItem(STORAGE_KEY);
    } catch(e) {}

    if (!uuid) {
        uuid = getCookie(COOKIE_NAME);
    }

    if (!uuid) {
        try {
            uuid = sessionStorage.getItem(STORAGE_KEY);
        } catch(e) {}
    }

    // If still no valid UUID, create a permanent new one
    if (!uuid || !/^[a-zA-Z0-9_\-]{16,128}$/.test(uuid)) {
        uuid = generateSecureUUID();
    }

    // Detect Friendly Name
    const friendlyName = detectDeviceFriendlyName();

    // Cross-save across all client storages
    try {
        localStorage.setItem(STORAGE_KEY, uuid);
        localStorage.setItem(NAME_STORAGE, friendlyName);
    } catch(e) {}

    try {
        sessionStorage.setItem(STORAGE_KEY, uuid);
        sessionStorage.setItem(NAME_STORAGE, friendlyName);
    } catch(e) {}

    setCookie(COOKIE_NAME, uuid, TEN_YEARS_SEC);
    setCookie(NAME_COOKIE, friendlyName, TEN_YEARS_SEC);

    // Expose Global Helper
    window.getDeviceVault = function() {
        return {
            uuid: uuid,
            name: friendlyName,
            screen: (window.screen.width || 0) + 'x' + (window.screen.height || 0),
            platform: navigator.platform || 'unknown'
        };
    };

    // Auto-inject hidden inputs into forms on page load and submission
    function injectFormInputs() {
        document.querySelectorAll('form').forEach(function(form) {
            // Check device_uuid
            let uuidInput = form.querySelector('input[name="device_uuid"]');
            if (!uuidInput) {
                uuidInput = document.createElement('input');
                uuidInput.type = 'hidden';
                uuidInput.name = 'device_uuid';
                form.appendChild(uuidInput);
            }
            uuidInput.value = uuid;

            // Check device_name
            let nameInput = form.querySelector('input[name="device_name"]');
            if (!nameInput) {
                nameInput = document.createElement('input');
                nameInput.type = 'hidden';
                nameInput.name = 'device_name';
                form.appendChild(nameInput);
            }
            nameInput.value = friendlyName;
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', injectFormInputs);
    } else {
        injectFormInputs();
    }

    // Hook fetch requests to always include device identity headers and JSON payload fields
    if (typeof window.fetch === 'function') {
        const originalFetch = window.fetch;
        window.fetch = function(resource, init) {
            init = init || {};
            init.headers = init.headers || {};

            // Add headers
            if (init.headers instanceof Headers) {
                if (!init.headers.has('X-Device-UUID')) init.headers.set('X-Device-UUID', uuid);
                if (!init.headers.has('X-Device-Name')) init.headers.set('X-Device-Name', encodeURIComponent(friendlyName));
            } else if (Array.isArray(init.headers)) {
                init.headers.push(['X-Device-UUID', uuid]);
                init.headers.push(['X-Device-Name', encodeURIComponent(friendlyName)]);
            } else {
                init.headers['X-Device-UUID'] = uuid;
                init.headers['X-Device-Name'] = encodeURIComponent(friendlyName);
            }

            // If JSON body, inject device properties if not already present
            if (init.body && typeof init.body === 'string' && init.body.trim().startsWith('{')) {
                try {
                    const parsed = JSON.parse(init.body);
                    if (typeof parsed === 'object' && parsed !== null) {
                        if (!parsed.device_uuid) parsed.device_uuid = uuid;
                        if (!parsed.device_name) parsed.device_name = friendlyName;
                        init.body = JSON.stringify(parsed);
                    }
                } catch(e) {}
            }

            return originalFetch.apply(this, [resource, init]);
        };
    }
})();
