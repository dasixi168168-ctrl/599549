<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <meta name="theme-color" content="#f3f4f6">
    <meta name="theme-color" media="(prefers-color-scheme: light)" content="#f3f4f6">
    <meta name="theme-color" media="(prefers-color-scheme: dark)" content="#f3f4f6">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title><?php echo e(isset($pageTitle) ? $pageTitle : browser_title_setting(app()->config('app', 'site_name', '六合彩票论坛首页'))); ?></title>
    <meta name="description" content="<?php echo e(isset($pageDescription) ? $pageDescription : '澳门与香港六合彩论坛网站'); ?>">
    <?php
    $frontStyleUrl = '/public/styles/style.css?v=20260706-front-float-contract-clean-01';
    $frontHomeCriticalStyleUrl = '/public/styles/home-critical.css?v=20260706-front-float-contract-clean-01';
    $frontForecastCriticalStyleUrl = '/public/styles/forecast-critical.css?v=20260706-float-contract-converge-01';
    $frontServiceStyleUrl = '/public/styles/front-service.css?v=20260706-pc-member-input-nav-01';
    $frontFloatingStyleUrl = '/public/styles/front-floating.css?v=20260706-pc-member-input-nav-02';
    $fontAwesomeCssUrl = asset('vendor/fontawesome/css/all.min.css?v=20260621-front-fa-sync-01');
    $homeLegacyScriptUrl = asset('home-legacy.js?v=20260705-ad-link-native-contract-06');
    $frontAppScriptUrl = asset('app.js?v=20260705-post-modal-fresh-frame-03');
    $needsFrontServiceStyle = !empty($needsFrontServiceStyle);
    $frontContent = isset($content) ? (string) $content : '';
    $layoutBodyClass = isset($bodyClass) ? (string) $bodyClass : '';
    $hasLegacyHomeData = strpos($frontContent, 'id="legacy-home-data"') !== false;
    $needsHomeLegacyScript = isset($needsHomeLegacyScript)
        ? (bool) $needsHomeLegacyScript
        : $hasLegacyHomeData;
    $needsFontAwesome = true;
    $needsFontAwesomeBrands = $needsFontAwesome && preg_match('/\b(?:fa-brands|fab)\b/', $frontContent);
    $isStandalonePanel = preg_match('/(?:^|\s)standalone-panel(?:\s|$)/u', $layoutBodyClass);
    $hasFrontHomeHero = preg_match('/<section\b(?=[^>]*\bid="section-home")(?=[^>]*\bclass="[^"]*\bhero-banner\b)[^>]*>/u', $frontContent);
    $isFrontHomeContent = $hasLegacyHomeData && $hasFrontHomeHero && !$isStandalonePanel;
    $isFrontForecastContent = preg_match('/(?:^|\s)forecast-panel-page(?:\s|$)/u', $layoutBodyClass);
    $shouldPreloadHomeLegacyScript = $needsHomeLegacyScript && !$isFrontForecastContent;
    $enableFrontPagePrefetch = $isFrontHomeContent || $isFrontForecastContent;
    $enableFrontUiSystem = false;
    $usesFrontServiceChrome = false;
    $appendFrontUiClasses = static function ($html, $classPattern, $classes) {
        return (string) preg_replace_callback(
            '/class="([^"]*\b(?:' . $classPattern . ')\b[^"]*)"/u',
            static function ($matches) use ($classes) {
                $classList = preg_split('/\s+/', trim((string) $matches[1]));
                $classList = is_array($classList) ? $classList : array();

                foreach (preg_split('/\s+/', trim((string) $classes)) as $className) {
                    if ($className !== '' && !in_array($className, $classList, true)) {
                        $classList[] = $className;
                    }
                }

                return 'class="' . e(implode(' ', $classList)) . '"';
            },
            (string) $html
        );
    };
    $frontUiContent = $frontContent;
    if ($enableFrontUiSystem && $isFrontHomeContent) {
        $frontUiContent = $appendFrontUiClasses($frontUiContent, 'top-bar', 'ui-front-section ui-front-card');
        $frontUiContent = $appendFrontUiClasses($frontUiContent, 'hero-banner', 'ui-front-section ui-front-card');
        $frontUiContent = $appendFrontUiClasses($frontUiContent, 'hero-live-box', 'ui-front-section ui-front-card');
        $frontUiContent = $appendFrontUiClasses($frontUiContent, 'marquee', 'ui-front-section ui-front-card');
        $frontUiContent = $appendFrontUiClasses($frontUiContent, 'calendar-panel', 'ui-front-section ui-front-card');
        $frontUiContent = $appendFrontUiClasses($frontUiContent, 'bottom-nav-target|max-w-7xl', 'ui-front-section');
        $frontUiContent = $appendFrontUiClasses($frontUiContent, 'data-frame|expert-item-card|zodiac-reference-card', 'ui-front-card');
        $frontUiContent = $appendFrontUiClasses($frontUiContent, 'section-title|zodiac-ref-title', 'ui-front-section-title');
        $frontUiContent = $appendFrontUiClasses($frontUiContent, 'space-y-3|zodiac-ref-list|zodiac-attr-list', 'ui-front-list');
        $frontUiContent = $appendFrontUiClasses($frontUiContent, 'expert-item-card|zodiac-ref-line|zodiac-attr-line|bottom-nav-link', 'ui-front-list-item');
        $frontUiContent = $appendFrontUiClasses($frontUiContent, 'top-action-btn|bottom-nav-link', 'ui-front-btn');
        $frontUiContent = $appendFrontUiClasses($frontUiContent, 'issue-prefix|zodiac-code|hero-live-period', 'ui-front-tag');
        $frontUiContent = $appendFrontUiClasses($frontUiContent, 'bottom-float-nav', 'ui-front-section ui-front-card');
    }
    ?>
    <?php if ($usesFrontServiceChrome): ?>
        <meta name="color-scheme" content="light">
        <meta name="supported-color-schemes" content="light">
        <meta name="theme-color" content="#f3f4f6">
        <meta name="theme-color" media="(prefers-color-scheme: light)" content="#f3f4f6">
        <meta name="theme-color" media="(prefers-color-scheme: dark)" content="#f3f4f6">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <style id="front-service-browser-chrome-critical">
            html,
            body.front-service-browser-chrome,
            body.customer-service-body {
                background: #f3f4f6 !important;
                color-scheme: light;
            }

            body.front-service-browser-chrome {
                --front-service-chrome-toolbar: #f3f4f6;
                --front-service-chrome-page: #f3f4f6;
                --front-service-chrome-top-bleed: 72px;
                --front-service-chrome-top-gap: 0px;
            }

            @media (max-width: 720px), (hover: none) and (pointer: coarse) {
                body.front-service-browser-chrome:not(.customer-service-embed-body)::before,
                body.front-service-browser-chrome:not(.customer-service-embed-body)::after {
                    content: "";
                    position: fixed;
                    right: 0;
                    left: 0;
                    background: #f3f4f6;
                    pointer-events: none;
                }

                body.front-service-browser-chrome:not(.customer-service-embed-body)::before {
                    top: calc((var(--front-service-chrome-top-bleed, 72px) + env(safe-area-inset-top)) * -1);
                    height: calc(var(--front-service-chrome-top-bleed, 72px) + env(safe-area-inset-top));
                    z-index: 9998;
                }

                body.front-service-browser-chrome:not(.customer-service-embed-body)::after {
                    bottom: 0;
                    height: calc(var(--front-bottom-nav-bottom, 2px) + env(safe-area-inset-bottom));
                    z-index: 9996;
                }

                body.front-service-browser-chrome:not(.customer-service-embed-body):not(.front-input-focus-active):not(.customer-service-compose-keyboard-active):not(:has(.service-thread-composer:focus-within)) .top-bar {
                    top: var(--front-service-chrome-top-gap, 0px) !important;
                }

                body.front-service-browser-chrome:not(.customer-service-embed-body):not(.front-input-focus-active):not(.customer-service-compose-keyboard-active):not(:has(.service-thread-composer:focus-within)) .top-bar-spacer {
                    height: calc(var(--front-mobile-top-spacer, var(--front-top-spacer, 67px)) + var(--front-service-chrome-top-gap, 0px)) !important;
                    flex-basis: calc(var(--front-mobile-top-spacer, var(--front-top-spacer, 67px)) + var(--front-service-chrome-top-gap, 0px)) !important;
                }
            }
        </style>
    <?php endif; ?>
    <style>
        html.front-scroll-restoring,
        html.front-scroll-restoring body {
            scroll-behavior: auto !important;
        }

        html.front-scroll-restoring body {
            opacity: 0;
            pointer-events: none;
        }
    </style>
    <script>
        (function () {
            var key = 'front_refresh_scroll_restore';
            var storage = null;
            var payload = null;
            var raw = '';
            var isReload = false;
            var html = document.documentElement;

            function currentScrollY() {
                return Math.max(0, parseInt(
                    window.pageYOffset
                        || (document.documentElement ? document.documentElement.scrollTop : 0)
                        || (document.body ? document.body.scrollTop : 0)
                        || 0,
                    10
                ) || 0);
            }

            function removeRestoringClass() {
                if (!html) {
                    return;
                }

                if (html.classList) {
                    html.classList.remove('front-scroll-restoring');
                    return;
                }

                html.className = String(html.className || '').replace(/(?:^|\s)front-scroll-restoring(?:\s|$)/g, ' ').replace(/\s+/g, ' ').trim();
            }

            function saveScroll() {
                if (!storage) {
                    return;
                }

                try {
                    storage.setItem(key, JSON.stringify({
                        href: window.location.href,
                        scrollY: currentScrollY(),
                        time: (new Date()).getTime()
                    }));
                } catch (error) {}
            }

            try {
                storage = window.sessionStorage || null;
            } catch (error) {
                storage = null;
            }

            try {
                if (window.performance && typeof window.performance.getEntriesByType === 'function') {
                    var entries = window.performance.getEntriesByType('navigation');
                    isReload = !!(entries && entries.length && entries[0].type === 'reload');
                } else if (window.performance && window.performance.navigation) {
                    isReload = window.performance.navigation.type === 1;
                }
            } catch (error) {
                isReload = false;
            }

            if (isReload) {
                try {
                    if ('scrollRestoration' in window.history) {
                        window.history.scrollRestoration = 'manual';
                    }
                } catch (error) {}

                try {
                    raw = storage ? storage.getItem(key) : '';
                    payload = raw ? JSON.parse(raw) : null;
                } catch (error) {
                    payload = null;
                }

                if (payload && payload.href === window.location.href && (new Date()).getTime() - Number(payload.time || 0) <= 300000) {
                    window.__frontRefreshScrollRestore = payload;
                    if ((parseInt(payload.scrollY, 10) || 0) > 0 && html) {
                        if (html.classList) {
                            html.classList.add('front-scroll-restoring');
                        } else if (String(html.className || '').indexOf('front-scroll-restoring') === -1) {
                            html.className = String(html.className || '') + ' front-scroll-restoring';
                        }
                        window.setTimeout(removeRestoringClass, 1800);
                    }
                }
            }

            window.addEventListener('pagehide', saveScroll, true);
            window.addEventListener('beforeunload', saveScroll, true);
        })();
    </script>
    <script>
        (function () {
            function parseJson(text) {
                try {
                    return text ? JSON.parse(text) : {};
                } catch (error) {
                    return {
                        success: false,
                        message: '服务器返回异常，请稍后重试。'
                    };
                }
            }

            function showAuthError(form, message) {
                var errorBox = form ? form.querySelector('[data-front-auth-error]') : null;
                message = message || '操作失败，请稍后重试。';

                if (errorBox) {
                    errorBox.textContent = message;
                    errorBox.hidden = false;
                    return;
                }

                window.alert(message);
            }

            function markFrontAuthChanged() {
                var storage;
                var now = Date.now ? Date.now() : (new Date()).getTime();

                try {
                    storage = window.localStorage || null;
                } catch (error) {
                    storage = null;
                }

                if (!storage) {
                    return;
                }

                try {
                    storage.setItem('front_auth_changed_at', String(now));
                } catch (error) {}
            }

            document.addEventListener('submit', function (event) {
                var form = event.target;
                var actionInput;
                var action;
                var button;
                var originalButtonText;
                var endpoint;
                var formData;
                var leavingPage = false;

                if (!form || !form.matches || !form.matches('form.front-auth-form[data-ajax-form]')) {
                    return;
                }

                actionInput = form.querySelector('input[name="action"]');
                action = actionInput ? String(actionInput.value || '') : '';
                if (action.indexOf('auth.') !== 0) {
                    return;
                }

                event.preventDefault();
                event.stopImmediatePropagation();

                if (form.getAttribute('data-auth-submit-state') === 'submitting') {
                    return;
                }

                form.setAttribute('data-auth-submit-state', 'submitting');
                endpoint = form.getAttribute('action') || './api.php';
                formData = new FormData(form);
                button = form.querySelector('[type="submit"]');

                if (button) {
                    originalButtonText = button.getAttribute('data-front-critical-text') || button.textContent || '';
                    button.setAttribute('data-front-critical-text', originalButtonText);
                    button.disabled = true;
                }

                fetch(endpoint, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }).then(function (response) {
                    return response.text().then(function (text) {
                        var payload = parseJson(text);
                        if (!response.ok && payload.success !== true) {
                            payload.success = false;
                            payload.message = payload.message || '请求失败，请稍后重试。';
                        }

                        return payload;
                    });
                }).then(function (payload) {
                    var redirect;

                    if (!payload || payload.success !== true) {
                        throw new Error(payload && payload.message ? payload.message : '操作失败，请稍后重试。');
                    }

                    markFrontAuthChanged();
                    form.setAttribute('data-auth-submit-state', 'complete');
                    redirect = payload.redirect || window.location.href;
                    leavingPage = true;

                    if (form.getAttribute('data-reload-current') === '1' && !payload.redirect) {
                        window.location.reload();
                        return;
                    }

                    window.location.href = redirect;
                }).catch(function (error) {
                    if (
                        form.getAttribute('data-auth-submit-state') === 'complete'
                        && error
                        && String(error.message || '').indexOf('表单令牌已失效') !== -1
                    ) {
                        return;
                    }

                    form.removeAttribute('data-auth-submit-state');
                    showAuthError(form, error && error.message ? error.message : '操作失败，请稍后重试。');
                }).finally(function () {
                    if (!leavingPage && button) {
                        button.disabled = false;
                    }
                });
            }, true);
        })();
    </script>
    <?php if ($shouldPreloadHomeLegacyScript): ?>
        <link
            rel="preload"
            href="<?php echo $homeLegacyScriptUrl; ?>"
            as="script"
            fetchpriority="low"
        >
    <?php endif; ?>
    <?php if ($needsFontAwesome): ?>
        <link
            rel="preload"
            href="/public/assets/vendor/fontawesome/webfonts/fa-solid-900.woff2"
            as="font"
            type="font/woff2"
            crossorigin
            fetchpriority="low"
        >
    <?php endif; ?>
    <?php if ($needsFontAwesomeBrands): ?>
        <link
            rel="preload"
            href="/public/assets/vendor/fontawesome/webfonts/fa-brands-400.woff2"
            as="font"
            type="font/woff2"
            crossorigin
            fetchpriority="low"
        >
    <?php endif; ?>
    <?php if ($needsFontAwesome): ?>
        <style id="front-fontawesome-critical">
            @font-face {
                font-family: "Font Awesome 6 Free";
                font-style: normal;
                font-weight: 900;
                font-display: swap;
                src: url("/public/assets/vendor/fontawesome/webfonts/fa-solid-900.woff2") format("woff2");
            }
            <?php if ($needsFontAwesomeBrands): ?>
            @font-face {
                font-family: "Font Awesome 6 Brands";
                font-style: normal;
                font-weight: 400;
                font-display: swap;
                src: url("/public/assets/vendor/fontawesome/webfonts/fa-brands-400.woff2") format("woff2");
            }
            <?php endif; ?>
        </style>
        <link rel="stylesheet" href="<?php echo $fontAwesomeCssUrl; ?>">
    <?php endif; ?>
    <?php if ($isFrontHomeContent): ?>
        <link rel="stylesheet" href="<?php echo $frontHomeCriticalStyleUrl; ?>">
        <link rel="preload" href="<?php echo $frontStyleUrl; ?>" as="style" fetchpriority="low" onload="this.onload=null;this.rel='stylesheet'">
        <noscript><link rel="stylesheet" href="<?php echo $frontStyleUrl; ?>"></noscript>
    <?php elseif ($isFrontForecastContent): ?>
        <link rel="stylesheet" href="<?php echo $frontForecastCriticalStyleUrl; ?>">
        <link rel="preload" href="<?php echo $frontStyleUrl; ?>" as="style" fetchpriority="low" onload="this.onload=null;this.rel='stylesheet'">
        <noscript><link rel="stylesheet" href="<?php echo $frontStyleUrl; ?>"></noscript>
    <?php else: ?>
        <link rel="stylesheet" href="<?php echo $frontStyleUrl; ?>">
    <?php endif; ?>
    <?php if ($needsFrontServiceStyle): ?>
        <link rel="stylesheet" href="<?php echo $frontServiceStyleUrl; ?>">
    <?php endif; ?>
    <?php if ($enableFrontUiSystem): ?>
        <link rel="stylesheet" href="/public/assets/ui-system.css?v=20260628-css-repair-01">
        <link rel="stylesheet" href="/public/assets/ui-override.css?v=20260628-css-governance-01">
    <?php endif; ?>
    <link rel="stylesheet" href="<?php echo $frontFloatingStyleUrl; ?>">
</head>
<body class="<?php echo e(trim('bg-slate-100 text-slate-900 ' . ($usesFrontServiceChrome ? 'front-service-browser-chrome ' : '') . ($enableFrontUiSystem ? 'ui-front-page ui-front-bottom-safe ' : '') . (isset($bodyClass) ? (string) $bodyClass : ''))); ?>" data-region="<?php echo e(isset($region) ? $region : 'macau'); ?>"<?php echo $enableFrontPagePrefetch ? ' data-page-prefetch-enabled="1"' : ''; ?>>
<div class="page-frame<?php echo $enableFrontUiSystem ? ' ui-front-container' : ''; ?>">
    <?php echo $frontUiContent; ?>
</div>
<div class="front-public-browser-edge front-public-browser-edge-top" aria-hidden="true"></div>
<div class="front-public-browser-edge front-public-browser-edge-bottom" aria-hidden="true"></div>
<?php if ($isFrontForecastContent): ?>
    <script>
        (function () {
            var sources = <?php
                echo json_encode(
                    array_values(array_filter(array(
                        $frontAppScriptUrl,
                        $needsHomeLegacyScript ? $homeLegacyScriptUrl : '',
                    ))),
                    JSON_UNESCAPED_SLASHES
                );
            ?>;
            var loaded = false;
            var loading = false;
            var callbacks = [];

            function flushCallbacks() {
                var queue = callbacks.slice();
                callbacks = [];
                queue.forEach(function (callback) {
                    try {
                        callback();
                    } catch (error) {}
                });
            }

            function loadForecastScripts(callback) {
                var index = 0;

                if (typeof callback === 'function') {
                    callbacks.push(callback);
                }
                if (loaded) {
                    flushCallbacks();
                    return;
                }
                if (loading || !sources.length) {
                    return;
                }

                loading = true;
                function next() {
                    var script;
                    var src;

                    if (index >= sources.length) {
                        loaded = true;
                        loading = false;
                        flushCallbacks();
                        return;
                    }

                    src = sources[index];
                    index += 1;
                    if (!src) {
                        next();
                        return;
                    }

                    script = document.createElement('script');
                    script.src = src;
                    script.async = false;
                    script.defer = true;
                    script.setAttribute('fetchpriority', 'low');
                    if ('fetchPriority' in script) {
                        script.fetchPriority = 'low';
                    }
                    script.onload = next;
                    script.onerror = next;
                    document.body.appendChild(script);
                }

                next();
            }

            function isForecastInteractiveTarget(target) {
                return !!(target && target.closest && target.closest([
                    '.forecast-filter-select',
                    '.forecast-generate-btn',
                    '[data-forecast-progress-form]',
                    '[data-front-forecast-pricing]',
                    '[data-ajax-form]'
                ].join(',')));
            }

            function scheduleAutoLoad() {
                var run = function () {
                    window.setTimeout(function () {
                        if ('requestIdleCallback' in window) {
                            window.requestIdleCallback(function () {
                                loadForecastScripts();
                            }, { timeout: 900 });
                            return;
                        }
                        loadForecastScripts();
                    }, 120);
                };

                if (document.readyState === 'complete') {
                    run();
                    return;
                }

                window.addEventListener('load', run, { once: true });
            }

            document.addEventListener('pointerdown', function (event) {
                if (isForecastInteractiveTarget(event.target)) {
                    loadForecastScripts();
                }
            }, true);

            document.addEventListener('focusin', function (event) {
                if (isForecastInteractiveTarget(event.target)) {
                    loadForecastScripts();
                }
            }, true);

            document.addEventListener('change', function (event) {
                if (isForecastInteractiveTarget(event.target)) {
                    loadForecastScripts();
                }
            }, true);

            document.addEventListener('submit', function (event) {
                var form = event.target;
                var submitter = event.submitter || document.activeElement;

                if (!form || !form.matches || !form.matches('[data-forecast-progress-form][data-ajax-form]') || loaded) {
                    return;
                }

                event.preventDefault();
                event.stopImmediatePropagation();
                loadForecastScripts(function () {
                    if (!form.isConnected) {
                        return;
                    }
                    if (typeof form.requestSubmit === 'function') {
                        if (submitter && submitter.form === form) {
                            form.requestSubmit(submitter);
                        } else {
                            form.requestSubmit();
                        }
                        return;
                    }
                    form.submit();
                });
            }, true);

            window.__loadFrontForecastScripts = loadForecastScripts;
            scheduleAutoLoad();
        })();
    </script>
<?php else: ?>
    <script src="<?php echo $frontAppScriptUrl; ?>" defer></script>
    <?php if ($needsHomeLegacyScript): ?>
        <script src="<?php echo $homeLegacyScriptUrl; ?>" defer></script>
    <?php endif; ?>
<?php endif; ?>
</body>
</html>
