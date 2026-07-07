(function () {
    var noticeQueue = [];
    var activeNotice = null;
    var customerServiceNoticeAudioReady = false;
    var customerServiceNoticeAudioBound = false;
    var customerServiceNoticeAudioContext = null;
    var customerServiceNoticeAudioPending = false;
    var customerServiceNoticeAudioLastPlayedAt = 0;
    var customerServiceNoticeAudioRetryTimer = 0;
    var customerServiceNoticeAudioRetryAttempts = 0;
    var customerServiceNoticeAudioLastVibrateAt = 0;
    var customerServiceNoticePermissionRequested = false;
    var serviceAgentScoreViewportBound = false;
    var formModalViewportBound = false;
    var frontPostBuyScrollKey = 'front_post_buy_scroll_restore';
    var frontRefreshScrollKey = 'front_refresh_scroll_restore';
    var frontRefreshScrollRestorePayload = window.__frontRefreshScrollRestore || null;
    var memberPurchasePostFrameScrollRestore = null;
    var frontPostAuthSyncPending = false;
    var frontAuthPageLoadedAt = 0;

    function debounce(callback, wait) {
        var timer = 0;

        return function () {
            var context = this;
            var args = arguments;

            window.clearTimeout(timer);
            timer = window.setTimeout(function () {
                callback.apply(context, args);
            }, wait || 120);
        };
    }

    function throttle(callback, wait) {
        var locked = false;

        return function () {
            var context = this;
            var args = arguments;

            if (locked) {
                return;
            }

            locked = true;
            callback.apply(context, args);
            window.setTimeout(function () {
                locked = false;
            }, wait || 160);
        };
    }

    function installFrontFloodGuard() {
        if (window.__frontFloodGuardReady) {
            return;
        }
        window.__frontFloodGuardReady = true;
        var clickMap = typeof WeakMap === "function" ? new WeakMap() : null;
        var pendingFetches = {};

        function floodNow() {
            return Date.now ? Date.now() : (new Date()).getTime();
        }

        function floodClosest(node, selector) {
            return node && node.closest ? node.closest(selector) : null;
        }

        function floodBypass(node) {
            return !!floodClosest(node, "[data-flood-guard=off], [data-front-flood-bypass]");
        }

        function floodElementLastAt(node) {
            if (!node) {
                return 0;
            }
            if (clickMap) {
                return parseInt(clickMap.get(node) || "0", 10) || 0;
            }
            return parseInt(node.__frontFloodGuardAt || "0", 10) || 0;
        }

        function floodSetElementLastAt(node, value) {
            if (!node) {
                return;
            }
            if (clickMap) {
                clickMap.set(node, value);
                return;
            }
            node.__frontFloodGuardAt = value;
        }

        function floodBodyKey(body) {
            var keys;
            var values;
            var index;

            if (!body) {
                return "";
            }
            if (typeof body === "string") {
                return body.length > 300 ? body.slice(0, 300) + "|" + body.length : body;
            }
            if (window.URLSearchParams && body instanceof URLSearchParams) {
                return body.toString();
            }
            if (window.FormData && body instanceof FormData) {
                keys = ["action", "post_id", "id", "region", "mode"];
                if (String(body.get("action") || "").indexOf("customer_service.") === 0) {
                    keys = keys.concat(["session_id", "status", "read", "light", "online", "message_type", "content"]);
                }
                values = [];
                for (index = 0; index < keys.length; index++) {
                    values.push(keys[index] + "=" + String(body.get(keys[index]) || ""));
                }
                return values.join("&");
            }
            return Object.prototype.toString.call(body);
        }

        function floodFetchKey(input, init) {
            var method = "GET";
            var url = "";
            var body = init && init.body ? init.body : null;

            if (input && typeof input === "object") {
                method = input.method || method;
                url = input.url || url;
            }
            if (init && init.method) {
                method = init.method;
            }
            method = String(method || "GET").toUpperCase();
            if (method === "GET" || method === "HEAD") {
                return "";
            }
            if (!url) {
                url = String(input || "");
            }

            return method + "|" + url + "|" + floodBodyKey(body);
        }

        document.addEventListener("click", function (event) {
            var target;
            var lastAt;
            var currentAt;
            var waitTime;

            if (event.defaultPrevented) {
                return;
            }
            target = floodClosest(event.target, "button, input[type=button], input[type=submit], a[href], [data-submit-form], [data-confirm-link], [data-front-post-login-open], [data-member-purchase-card]");
            if (!target || floodBypass(target) || target.disabled || target.getAttribute("aria-disabled") === "true") {
                return;
            }

            waitTime = target.hasAttribute("data-confirm-link") ? 900 : 650;
            currentAt = floodNow();
            lastAt = floodElementLastAt(target);
            if (currentAt - lastAt < waitTime) {
                event.preventDefault();
                event.stopImmediatePropagation();
                return;
            }

            floodSetElementLastAt(target, currentAt);
        }, true);

        document.addEventListener("submit", function (event) {
            var form = event.target;
            var currentAt;
            var lastAt;
            var method;
            var waitTime;

            if (!form || floodBypass(form) || (form.matches && form.matches("form[data-confirm]"))) {
                return;
            }

            method = String(form.getAttribute("method") || "get").toUpperCase();
            waitTime = method === "GET" ? 450 : 1600;
            currentAt = floodNow();
            lastAt = parseInt(form.getAttribute("data-front-flood-at") || "0", 10) || 0;
            if (currentAt - lastAt < waitTime) {
                event.preventDefault();
                event.stopImmediatePropagation();
                return;
            }

            form.setAttribute("data-front-flood-at", String(currentAt));
        }, true);

        if (typeof window.fetch === "function" && !window.fetch.__frontFloodGuard) {
            var originalFetch = window.fetch;
            window.fetch = function (input, init) {
                var key = floodFetchKey(input, init);
                var currentAt = floodNow();
                var existing;
                var requestPromise;
                var networkPromise;

                if (!key) {
                    return originalFetch.apply(this, arguments);
                }

                existing = pendingFetches[key];
                if (existing && currentAt - existing.time < 1600) {
                    return existing.promise.then(function (response) {
                        if (existing.responseClone && typeof existing.responseClone.clone === "function") {
                            return existing.responseClone.clone();
                        }
                        return response && typeof response.clone === "function" ? response.clone() : response;
                    });
                }

                networkPromise = originalFetch.apply(this, arguments);
                pendingFetches[key] = {
                    time: currentAt,
                    promise: null,
                    responseClone: null
                };
                requestPromise = networkPromise.then(function (response) {
                    if (pendingFetches[key] && response && typeof response.clone === "function") {
                        try {
                            pendingFetches[key].responseClone = response.clone();
                        } catch (cloneError) {}
                    }
                    return response;
                });
                pendingFetches[key].promise = requestPromise;
                requestPromise.then(function () {
                    delete pendingFetches[key];
                }, function () {
                    delete pendingFetches[key];
                });

                return requestPromise;
            };
            window.fetch.__frontFloodGuard = true;
        }

        window.FrontFloodGuard = {
            ready: true,
            reset: function (node) {
                if (node && node.removeAttribute) {
                    node.removeAttribute("data-front-flood-at");
                }
                floodSetElementLastAt(node, 0);
            }
        };
    }

    function replaceChildrenFast(target, fragment) {
        if (!target) {
            return;
        }

        if (typeof target.replaceChildren === 'function') {
            target.replaceChildren(fragment);
            return;
        }

        target.innerHTML = '';
        target.appendChild(fragment);
    }

    function getSessionStorage() {
        try {
            return window.sessionStorage || null;
        } catch (error) {
            return null;
        }
    }

    function getLocalStorage() {
        try {
            return window.localStorage || null;
        } catch (error) {
            return null;
        }
    }

    function markFrontAuthChanged() {
        var storage = getLocalStorage();
        var now = Date.now ? Date.now() : (new Date()).getTime();

        if (!storage) {
            return;
        }

        try {
            storage.setItem('front_auth_changed_at', String(now));
        } catch (error) {}
    }

    function frontAuthChangedAt() {
        var storage = getLocalStorage();
        var changedAt = 0;
        var cookieMatch;

        if (storage) {
            try {
                changedAt = Math.max(changedAt, parseInt(storage.getItem('front_auth_changed_at') || '0', 10) || 0);
            } catch (error) {}
        }

        try {
            cookieMatch = document.cookie.match(/(?:^|;\s*)front_auth_changed_at=([^;]+)/);
            if (cookieMatch) {
                changedAt = Math.max(changedAt, parseInt(decodeURIComponent(cookieMatch[1]) || '0', 10) || 0);
            }
        } catch (error) {}

        return changedAt;
    }

    frontAuthPageLoadedAt = frontAuthChangedAt();

    function reloadStaleCustomerServiceAuthPage() {
        var body = document.body;
        var changedAt;
        var reloadKey;
        var sessionStorage;
        var reloadStamp;
        var url;

        if (!body || !body.classList || !body.classList.contains('customer-service-body')) {
            return;
        }

        changedAt = frontAuthChangedAt();
        if (changedAt <= frontAuthPageLoadedAt) {
            return;
        }

        reloadKey = 'front_service_auth_refresh_' + String(changedAt);
        sessionStorage = getSessionStorage();
        try {
            reloadStamp = sessionStorage ? sessionStorage.getItem(reloadKey) : '';
        } catch (error) {
            reloadStamp = '';
        }

        if (reloadStamp === '1') {
            return;
        }

        try {
            if (sessionStorage) {
                sessionStorage.setItem(reloadKey, '1');
            }
        } catch (error) {}

        try {
            url = new URL(window.location.href);
            url.searchParams.set('_auth_refresh', String(changedAt));
            window.location.replace(url.href);
        } catch (error) {
            window.location.reload();
        }
    }

    function isFrontRefreshScrollEnabled() {
        return !!(
            document.body
            && !(document.body.classList && document.body.classList.contains('admin-body'))
            && (document.body.getAttribute('data-region') || document.querySelector('.bottom-float-nav') || document.querySelector('.page-frame'))
        );
    }

    function currentFrontScrollY() {
        return Math.max(0, parseInt(
            window.pageYOffset
                || (document.documentElement ? document.documentElement.scrollTop : 0)
                || (document.body ? document.body.scrollTop : 0)
                || 0,
            10
        ) || 0);
    }

    function frontRefreshMaxScrollY() {
        var doc = document.documentElement;
        var body = document.body;
        var height = Math.max(
            doc ? doc.scrollHeight : 0,
            body ? body.scrollHeight : 0,
            doc ? doc.offsetHeight : 0,
            body ? body.offsetHeight : 0
        );

        return Math.max(0, height - (window.innerHeight || (doc ? doc.clientHeight : 0) || 0));
    }

    function isFrontRefreshNavigation() {
        var entries;

        try {
            if (window.performance && typeof window.performance.getEntriesByType === 'function') {
                entries = window.performance.getEntriesByType('navigation');
                if (entries && entries.length) {
                    return entries[0].type === 'reload';
                }
            }

            if (window.performance && window.performance.navigation) {
                return window.performance.navigation.type === 1;
            }
        } catch (error) {}

        return false;
    }

    function removeFrontRefreshRestoringClass() {
        var html = document.documentElement;

        if (!html) {
            return;
        }

        if (html.classList) {
            html.classList.remove('front-scroll-restoring');
            return;
        }

        html.className = String(html.className || '').replace(/(?:^|\s)front-scroll-restoring(?:\s|$)/g, ' ').replace(/\s+/g, ' ').trim();
    }

    function readFrontRefreshScrollPayload(storage) {
        var raw = '';
        var payload = frontRefreshScrollRestorePayload;

        if (payload) {
            return payload;
        }

        if (!storage) {
            return null;
        }

        try {
            raw = storage.getItem(frontRefreshScrollKey);
        } catch (error) {
            raw = '';
        }

        if (!raw) {
            return null;
        }

        try {
            return JSON.parse(raw);
        } catch (error) {
            return null;
        }
    }

    function saveFrontRefreshScroll() {
        var storage = getSessionStorage();
        var payload;

        if (!storage || !isFrontRefreshScrollEnabled()) {
            return;
        }

        payload = {
            href: window.location.href,
            scrollY: currentFrontScrollY(),
            time: (new Date()).getTime()
        };

        try {
            storage.setItem(frontRefreshScrollKey, JSON.stringify(payload));
        } catch (error) {}
    }

    function bindFrontRefreshScrollSave() {
        if (window.__frontRefreshScrollSaveBound) {
            return;
        }

        window.__frontRefreshScrollSaveBound = true;
        window.addEventListener('pagehide', saveFrontRefreshScroll, true);
        window.addEventListener('beforeunload', saveFrontRefreshScroll, true);
    }

    function restoreFrontRefreshScroll() {
        var storage = getSessionStorage();
        var payload = readFrontRefreshScrollPayload(storage);
        var scrollY = 0;
        var startedAt = (new Date()).getTime();
        var restored = false;
        var restore;
        var schedule;
        var finish;

        if (!isFrontRefreshScrollEnabled()) {
            removeFrontRefreshRestoringClass();
            return;
        }

        if (!payload && !isFrontRefreshNavigation()) {
            removeFrontRefreshRestoringClass();
            return;
        }

        if (!payload || payload.href !== window.location.href || (new Date()).getTime() - Number(payload.time || 0) > 300000) {
            removeFrontRefreshRestoringClass();
            return;
        }

        try {
            if (storage) {
                storage.removeItem(frontRefreshScrollKey);
            }
        } catch (error) {}

        scrollY = Math.max(0, parseInt(payload.scrollY, 10) || 0);
        if (scrollY <= 0) {
            window.scrollTo(0, 0);
            removeFrontRefreshRestoringClass();
            return;
        }

        try {
            if ('scrollRestoration' in window.history) {
                window.history.scrollRestoration = 'manual';
            }
        } catch (error) {}

        finish = function () {
            restored = true;
            removeFrontRefreshRestoringClass();
        };

        restore = function (forceReveal) {
            var maxY;
            var targetY;
            var elapsed;

            if (restored) {
                return;
            }

            maxY = frontRefreshMaxScrollY();
            targetY = Math.min(scrollY, maxY);
            window.scrollTo(0, targetY);
            elapsed = (new Date()).getTime() - startedAt;

            if (forceReveal || maxY >= scrollY - 4 || elapsed > 1400) {
                finish();
            }
        };

        schedule = function (delay, forceReveal) {
            window.setTimeout(function () {
                restore(!!forceReveal);
            }, delay);
        };

        if (typeof window.requestAnimationFrame === 'function') {
            window.requestAnimationFrame(function () {
                restore(false);
            });
        } else {
            restore(false);
        }

        schedule(80, false);
        schedule(180, false);
        schedule(360, false);
        schedule(700, false);
        schedule(1100, false);
        schedule(1500, true);

        if (document.readyState !== 'complete') {
            window.addEventListener('load', function () {
                restore(true);
            });
        }
    }

    function isPostBuyAjaxForm(form) {
        var actionInput;

        if (!form || typeof form.querySelector !== 'function') {
            return false;
        }

        actionInput = form.querySelector('input[name="action"]');
        return !!actionInput && String(actionInput.value || '') === 'post.buy';
    }

    function normalizeFrontPostBuyScrollHref(href) {
        var url;

        href = String(href || '');
        if (href === '') {
            return '';
        }

        try {
            url = new URL(href, window.location.href);
            url.searchParams.delete('_fresh');
            return url.href;
        } catch (error) {
            return href.replace(/([?&])_fresh=[^&]*(&|$)/, function (match, prefix, suffix) {
                return suffix ? prefix : '';
            }).replace(/[?&]$/, '');
        }
    }

    function frontPostBuyAnchorForForm(form) {
        var anchor;
        var index = -1;
        var anchors;

        if (!form || !form.closest) {
            return null;
        }

        anchor = form.closest('[data-forecast-row], .front-forecast-card, .front-post-main-content, .front-post-detail-stack');
        if (!anchor) {
            anchor = form.closest('.front-post-buy-actions');
        }
        if (!anchor) {
            return null;
        }

        anchors = document.querySelectorAll('[data-forecast-row], .front-forecast-card, .front-post-main-content, .front-post-detail-stack, .front-post-buy-actions');
        Array.prototype.some.call(anchors, function (item, itemIndex) {
            if (item === anchor) {
                index = itemIndex;
                return true;
            }
            return false;
        });

        return {
            index: index,
            top: anchor.getBoundingClientRect ? Math.round(anchor.getBoundingClientRect().top) : 0
        };
    }

    function frontPostBuyAnchorByPayload(payload) {
        var anchors;
        var index;

        if (!payload) {
            return null;
        }

        index = parseInt(payload.anchorIndex, 10);
        if (isNaN(index) || index < 0) {
            return null;
        }

        anchors = document.querySelectorAll('[data-forecast-row], .front-forecast-card, .front-post-main-content, .front-post-detail-stack, .front-post-buy-actions');
        return anchors[index] || null;
    }

    function saveFrontPostBuyScroll(form) {
        var storage = getSessionStorage();
        var scrollY;
        var scrollElement;
        var anchorPayload;
        var payload;

        if (!storage || !isPostBuyAjaxForm(form)) {
            return;
        }

        scrollElement = document.scrollingElement || document.documentElement || document.body;
        scrollY = window.pageYOffset
            || (scrollElement ? scrollElement.scrollTop : 0)
            || (document.documentElement ? document.documentElement.scrollTop : 0)
            || (document.body ? document.body.scrollTop : 0)
            || 0;
        anchorPayload = frontPostBuyAnchorForForm(form);
        payload = {
            href: normalizeFrontPostBuyScrollHref(window.location.href),
            scrollY: Math.max(0, parseInt(scrollY, 10) || 0),
            anchorIndex: anchorPayload ? anchorPayload.index : -1,
            anchorTop: anchorPayload ? anchorPayload.top : 0,
            time: (new Date()).getTime()
        };

        try {
            storage.setItem(frontPostBuyScrollKey, JSON.stringify(payload));
        } catch (error) {}

        if (window.parent && window.parent !== window && typeof window.parent.postMessage === 'function') {
            try {
                window.parent.postMessage({
                    type: 'front-post-buy-scroll-save',
                    payload: payload
                }, window.location.origin);
            } catch (messageError) {}
        }
    }

    function restoreFrontPostBuyScroll() {
        var storage = getSessionStorage();
        var raw;
        var payload;
        var scrollY;
        var anchorTop;
        var restore;
        var scheduleRestore;

        if (!storage) {
            return;
        }

        try {
            raw = storage.getItem(frontPostBuyScrollKey);
        } catch (error) {
            raw = '';
        }

        if (!raw) {
            return;
        }

        try {
            payload = JSON.parse(raw);
            storage.removeItem(frontPostBuyScrollKey);
        } catch (error) {
            try {
                storage.removeItem(frontPostBuyScrollKey);
            } catch (removeError) {}
            return;
        }

        if (!payload || normalizeFrontPostBuyScrollHref(payload.href) !== normalizeFrontPostBuyScrollHref(window.location.href) || (new Date()).getTime() - Number(payload.time || 0) > 15000) {
            return;
        }

        scrollY = Math.max(0, parseInt(payload.scrollY, 10) || 0);
        anchorTop = Math.max(-2000, Math.min(2000, parseInt(payload.anchorTop, 10) || 0));
        restore = function () {
            var anchor = frontPostBuyAnchorByPayload(payload);
            var anchorDelta = 0;

            if (anchor && anchor.getBoundingClientRect) {
                anchorDelta = Math.round(anchor.getBoundingClientRect().top) - anchorTop;
                window.scrollTo(0, Math.max(0, currentFrontScrollY() + anchorDelta));
                return;
            }

            window.scrollTo(0, scrollY);
        };
        scheduleRestore = function () {
            if (typeof window.requestAnimationFrame === 'function') {
                window.requestAnimationFrame(restore);
            } else {
                restore();
            }
            window.setTimeout(restore, 120);
            window.setTimeout(restore, 420);
            window.setTimeout(restore, 900);
            if (document.readyState !== 'complete') {
                window.addEventListener('load', restore);
            }
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', scheduleRestore);
            return;
        }

        scheduleRestore();
    }

    bindFrontRefreshScrollSave();
    restoreFrontRefreshScroll();
    restoreFrontPostBuyScroll();
    installFrontFloodGuard();

    function setAnimatedHidden(element, isOpen, bodyClass) {
        if (!element) {
            return;
        }

        if (element._appUiHiddenTimer) {
            window.clearTimeout(element._appUiHiddenTimer);
            element._appUiHiddenTimer = 0;
        }

        if (document.body && bodyClass) {
            document.body.classList.toggle(bodyClass, !!isOpen);
        }

        if (isOpen) {
            element.hidden = false;
            window.requestAnimationFrame(function () {
                element.classList.add('is-visible');
            });
            return;
        }

        element.classList.remove('is-visible');
        element._appUiHiddenTimer = window.setTimeout(function () {
            element.hidden = true;
            element._appUiHiddenTimer = 0;
        }, 180);
    }


    function isEmbeddedRechargeServiceUrl(rawUrl) {
        try {
            var parsedUrl = new URL(rawUrl, window.location.href);
            return parsedUrl.searchParams.get('embed') === '1' || parsedUrl.searchParams.get('modal') === '1';
        } catch (error) {
            return String(rawUrl || '').indexOf('embed=1') !== -1 || String(rawUrl || '').indexOf('modal=1') !== -1;
        }
    }

    function ensureMemberRechargeServiceModal() {
        var modal = document.querySelector('[data-member-recharge-service-modal]');
        if (modal) {
            return modal;
        }

        modal = document.createElement('div');
        modal.className = 'front-post-service-modal front-standard-modal';
        modal.hidden = true;
        modal.setAttribute('data-member-recharge-service-modal', '');
        modal.setAttribute('role', 'presentation');
        modal.innerHTML = ''
            + '<button type="button" class="front-post-service-backdrop front-standard-modal-backdrop" data-member-recharge-service-close aria-label="关闭客服会话"></button>'
            + '<section class="front-post-service-dialog front-standard-modal-dialog" role="dialog" aria-modal="true" aria-label="在线客服会话">'
            + '<header class="front-post-service-head front-standard-modal-head">'
            + '<strong class="front-post-service-title">'
            + '<span class="front-post-service-icon"><i class="fa-solid fa-headset" aria-hidden="true"></i><span class="front-post-service-state" data-member-recharge-service-state hidden>在线</span></span>'
            + '<span class="front-post-service-title-copy"><span data-member-recharge-service-title>在线客服</span><small data-member-recharge-service-hours>接待时间：09:00-23:00</small></span>'
            + '</strong>'
            + '<span class="front-post-service-actions">'
            + '<button type="button" class="front-post-service-clear" data-member-recharge-service-clear aria-label="删除聊天记录" title="删除聊天记录" hidden><i class="fa-solid fa-trash-can" aria-hidden="true"></i></button>'
            + '<button type="button" class="front-post-service-close" data-member-recharge-service-close aria-label="关闭客服会话"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>'
            + '</span>'
            + '</header>'
            + '<iframe class="front-post-service-frame" title="在线客服会话" loading="eager" data-member-recharge-service-frame></iframe>'
            + '</section>';
        document.body.appendChild(modal);

        return modal;
    }

    function bindMemberPurchasePostModalFrame(modal, frame) {
        if (!modal || !frame || frame.getAttribute('data-member-purchase-frame-ready') === '1') {
            return;
        }

        frame.addEventListener('load', function () {
            if (!modal || modal.querySelector('.expert-post-modal-frame') !== frame) {
                return;
            }
            scheduleMemberPurchasePostModalFrameSync();
            restoreMemberPurchasePostFrameScroll(frame);
        });
        frame.setAttribute('data-member-purchase-frame-ready', '1');
    }

    function replaceMemberPurchasePostModalFrame(modal) {
        var body = modal ? modal.querySelector('.expert-post-modal-body') : null;
        var oldFrame = body ? body.querySelector('.expert-post-modal-frame') : null;
        var frame;

        if (!body) {
            return oldFrame;
        }

        frame = document.createElement('iframe');
        frame.className = 'expert-post-modal-frame';
        frame.title = '帖子阅读窗口';
        frame.loading = 'eager';
        frame.referrerPolicy = 'same-origin';
        bindMemberPurchasePostModalFrame(modal, frame);

        if (oldFrame && oldFrame.parentNode === body) {
            body.replaceChild(frame, oldFrame);
        } else {
            body.appendChild(frame);
        }

        return frame;
    }


    function ensureMemberPurchasePostModal() {
        var modal = document.getElementById('expert-post-modal');
        var frame;
        var title;
        var meta;
        var author;

        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'expert-post-modal';
            modal.className = 'expert-post-modal front-standard-modal';
            modal.hidden = true;
            modal.innerHTML = ''
                + '<div class="expert-post-modal-backdrop front-standard-modal-backdrop" data-expert-post-close="1"></div>'
                + '<div class="expert-post-modal-dialog front-standard-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="expert-post-modal-title">'
                + '<div class="expert-post-modal-header front-standard-modal-head">'
                + '<div class="expert-post-modal-heading">'
                + '<div class="expert-post-modal-identity">'
                + '<span class="expert-post-modal-avatar" aria-hidden="true"><i class="fa-solid fa-circle-user"></i><span class="expert-post-modal-avatar-level">超级vip</span></span>'
                + '<div class="expert-post-modal-author" hidden></div>'
                + '</div>'
                + '<div class="expert-post-modal-copy">'
                + '<div id="expert-post-modal-title" class="expert-post-modal-title">帖子阅读</div>'
                + '<div class="expert-post-modal-meta" hidden></div>'
                + '</div>'
                + '</div>'
                + '<button type="button" class="expert-post-modal-close" data-expert-post-close="1" aria-label="关闭">×</button>'
                + '</div>'
                + '<div class="expert-post-modal-body front-standard-modal-body">'
                + '<iframe class="expert-post-modal-frame" title="帖子阅读窗口" loading="eager" referrerpolicy="same-origin"></iframe>'
                + '</div>'
                + '</div>';
            document.body.appendChild(modal);
        }

        if (modal.getAttribute('data-member-purchase-post-ready') !== '1') {
            modal.addEventListener('click', function (event) {
                var likeButton = event.target && event.target.closest ? event.target.closest('[data-expert-post-modal-like]') : null;

                if (likeButton && modal.contains(likeButton)) {
                    event.preventDefault();
                    toggleMemberPurchasePostModalLike(likeButton);
                    return;
                }

                if (event.target && event.target.closest && event.target.closest('[data-member-purchase-post-close="1"], [data-expert-post-close="1"]')) {
                    closeMemberPurchasePostModal();
                }
            });
            modal.setAttribute('data-member-purchase-post-ready', '1');
        }

        frame = modal.querySelector('.expert-post-modal-frame');
        title = modal.querySelector('.expert-post-modal-title');
        meta = modal.querySelector('.expert-post-modal-meta');
        author = modal.querySelector('.expert-post-modal-author');
        bindMemberPurchasePostModalFrame(modal, frame);

        return {
            modal: modal,
            frame: frame,
            title: title,
            meta: meta,
            author: author
        };
    }

    function memberPurchaseTextFromCharCodes(codes) {
        return String.fromCharCode.apply(String, codes || []);
    }

    function memberPurchasePostMetaLabel(type) {
        if (type === 'author') {
            return memberPurchaseTextFromCharCodes([20316, 32773, 65306]);
        }

        if (type === 'shelf') {
            return memberPurchaseTextFromCharCodes([19978, 26550, 65306]);
        }

        if (type === 'published') {
            return memberPurchaseTextFromCharCodes([21457, 24067, 26102, 38388, 65306]);
        }

        if (type === 'views') {
            return memberPurchaseTextFromCharCodes([27983, 35272, 65306]);
        }

        return '';
    }

    function memberPurchasePostViewIcon() {
        return String.fromCodePoint(128065, 8205, 128488);
    }

    function stripMemberPurchaseMetaLabel(text, label) {
        var normalizedText = String(text || '').trim();
        var normalizedLabel = String(label || '').trim();

        if (!normalizedText) {
            return '';
        }

        if (normalizedLabel && normalizedText.indexOf(normalizedLabel) === 0) {
            return normalizedText.slice(normalizedLabel.length).trim();
        }

        return normalizedText;
    }

    function memberPurchaseMetaValueAfterLabel(items, label) {
        var normalizedLabel = String(label || '').trim();
        var matched = '';

        if (!normalizedLabel) {
            return '';
        }

        (items || []).some(function (text) {
            text = String(text || '').trim();
            if (text.indexOf(normalizedLabel) !== 0) {
                return false;
            }

            matched = text.slice(normalizedLabel.length).trim();
            return true;
        });

        return matched;
    }

    function normalizeMemberPurchasePostCount(value) {
        var count = parseInt(String(value || '').replace(/[^0-9]/g, ''), 10);

        return isNaN(count) ? '0' : String(Math.max(0, count));
    }

    function buildMemberPurchasePostModalMeta(metaItems, likeState) {
        var normalizedItems = (metaItems || []).map(function (text) {
            return String(text || '').replace(/\s+/g, ' ').trim();
        }).filter(function (text) {
            return text !== '';
        });
        var authorValue = stripMemberPurchaseMetaLabel(normalizedItems[0], memberPurchasePostMetaLabel('author')) || '--';
        var shelfTime = memberPurchaseMetaValueAfterLabel(normalizedItems, memberPurchasePostMetaLabel('shelf'))
            || memberPurchaseMetaValueAfterLabel(normalizedItems, memberPurchasePostMetaLabel('published'));
        var viewValue = stripMemberPurchaseMetaLabel(normalizedItems.length ? normalizedItems[normalizedItems.length - 1] : '', memberPurchasePostMetaLabel('views')) || '--';
        var likeCount = likeState && likeState.count !== '' ? likeState.count : '0';

        return [
            {
                label: '',
                value: authorValue
            },
            {
                label: memberPurchasePostViewIcon() + memberPurchasePostMetaLabel('views'),
                value: viewValue
            },
            {
                label: memberPurchasePostMetaLabel('shelf'),
                value: shelfTime || '--'
            },
            {
                type: 'like',
                label: '',
                value: likeCount,
                liked: !!(likeState && likeState.liked),
                postId: likeState ? likeState.postId : ''
            }
        ];
    }

    function setMemberPurchasePostModalMeta(items) {
        var state = ensureMemberPurchasePostModal();

        if (!state.meta) {
            return;
        }

        state.meta.innerHTML = '';
        if (state.author) {
            state.author.innerHTML = '';
            state.author.hidden = true;
        }

        if (!items || !items.length) {
            if (state.author) {
                var authorNode = document.createElement('span');
                var authorValueNode = document.createElement('span');
                authorNode.className = 'expert-post-modal-meta-item is-placeholder';
                authorValueNode.className = 'expert-post-modal-meta-value';
                authorValueNode.textContent = '--';
                authorNode.appendChild(authorValueNode);
                state.author.appendChild(authorNode);
                state.author.hidden = false;
            }

            var viewNode = document.createElement('span');
            var viewLabelNode = document.createElement('span');
            var viewValueNode = document.createElement('span');
            var likeNode = document.createElement('span');
            var likeIconNode = document.createElement('i');
            var likeValueNode = document.createElement('span');

            viewNode.className = 'expert-post-modal-meta-item is-placeholder';
            viewLabelNode.className = 'expert-post-modal-meta-label';
            viewLabelNode.textContent = '浏览：';
            viewValueNode.className = 'expert-post-modal-meta-value';
            viewValueNode.textContent = '--';
            viewNode.appendChild(viewLabelNode);
            viewNode.appendChild(viewValueNode);

            likeNode.className = 'expert-post-modal-meta-item expert-post-modal-meta-like is-placeholder';
            likeIconNode.className = 'fa-solid fa-thumbs-up expert-post-modal-like-icon';
            likeIconNode.setAttribute('aria-hidden', 'true');
            likeValueNode.className = 'expert-post-modal-meta-value';
            likeValueNode.textContent = '0';
            likeNode.appendChild(likeIconNode);
            likeNode.appendChild(document.createTextNode(memberPurchaseTextFromCharCodes([65306])));
            likeNode.appendChild(likeValueNode);

            state.meta.appendChild(viewNode);
            state.meta.appendChild(likeNode);
            state.meta.hidden = false;
            return;
        }

        items.forEach(function (item, index) {
            var isLikeItem = item && typeof item === 'object' && item.type === 'like';
            var isAuthorItem = index === 0 && !isLikeItem && state.author;
            var node = document.createElement(isLikeItem ? 'button' : 'span');
            var iconNode;
            var labelNode;
            var valueNode;

            node.className = 'expert-post-modal-meta-item';

            if (isLikeItem) {
                node.className += ' expert-post-modal-meta-like';
                node.setAttribute('type', 'button');
                node.setAttribute('data-expert-post-modal-like', '1');
                node.setAttribute('aria-pressed', item.liked ? 'true' : 'false');
                if (item.liked) {
                    node.className += ' is-liked';
                }
                if (item.postId) {
                    node.setAttribute('data-post-id', String(item.postId));
                }
                iconNode = document.createElement('i');
                iconNode.className = 'fa-solid fa-thumbs-up expert-post-modal-like-icon';
                iconNode.setAttribute('aria-hidden', 'true');
                node.appendChild(iconNode);
            }

            labelNode = document.createElement('span');
            labelNode.className = 'expert-post-modal-meta-label';
            labelNode.textContent = String(item && item.label ? item.label : '').trim();
            valueNode = document.createElement('span');
            valueNode.className = 'expert-post-modal-meta-value';
            valueNode.textContent = String(item && item.value ? item.value : '').trim();

            if (!labelNode.textContent && !valueNode.textContent) {
                return;
            }

            if (labelNode.textContent) {
                node.appendChild(labelNode);
            }
            if (valueNode.textContent) {
                if (isLikeItem) {
                    node.appendChild(document.createTextNode(memberPurchaseTextFromCharCodes([65306])));
                }
                node.appendChild(valueNode);
            }

            if (isAuthorItem) {
                state.author.appendChild(node);
            } else {
                state.meta.appendChild(node);
            }
        });

        if (state.author && state.author.childNodes.length) {
            state.author.hidden = false;
        }

        state.meta.hidden = !state.meta.childNodes.length;
    }

    function toggleMemberPurchasePostModalLike(button) {
        var state = ensureMemberPurchasePostModal();
        var frameDocument;
        var frameLikeButton;

        if (!state.frame || !button || button.classList.contains('is-loading')) {
            return;
        }

        try {
            frameDocument = state.frame.contentDocument || (state.frame.contentWindow ? state.frame.contentWindow.document : null);
        } catch (error) {
            return;
        }

        if (!frameDocument) {
            return;
        }

        frameLikeButton = frameDocument.querySelector('[data-post-like]');
        if (!frameLikeButton) {
            return;
        }

        button.classList.add('is-loading');
        frameLikeButton.click();
        window.setTimeout(function () {
            syncMemberPurchasePostModalFrame();
            button.classList.remove('is-loading');
        }, 500);
        window.setTimeout(syncMemberPurchasePostModalFrame, 1200);
    }

    function restoreMemberPurchasePostFrameScroll(frame) {
        var payload = memberPurchasePostFrameScrollRestore;
        var currentHref = '';
        var scrollY = 0;
        var anchorTop = 0;
        var restore;

        if (!frame || !payload || (new Date()).getTime() - Number(payload.time || 0) > 15000) {
            return;
        }

        try {
            currentHref = frame.contentWindow && frame.contentWindow.location ? String(frame.contentWindow.location.href || '') : '';
        } catch (error) {
            return;
        }

        if (currentHref === '' || normalizeFrontPostBuyScrollHref(currentHref) !== normalizeFrontPostBuyScrollHref(payload.href)) {
            return;
        }

        memberPurchasePostFrameScrollRestore = null;
        scrollY = Math.max(0, parseInt(payload.scrollY, 10) || 0);
        anchorTop = Math.max(-2000, Math.min(2000, parseInt(payload.anchorTop, 10) || 0));
        restore = function () {
            var frameDocument;
            var frameAnchor = null;
            var frameAnchors;
            var anchorIndex;
            var anchorDelta;
            var frameScrollY;

            try {
                if (frame.contentWindow && typeof frame.contentWindow.scrollTo === 'function') {
                    anchorIndex = parseInt(payload.anchorIndex, 10);
                    if (!isNaN(anchorIndex) && anchorIndex >= 0) {
                        frameDocument = frame.contentDocument || frame.contentWindow.document;
                        frameAnchors = frameDocument ? frameDocument.querySelectorAll('[data-forecast-row], .front-forecast-card, .front-post-main-content, .front-post-detail-stack, .front-post-buy-actions') : null;
                        frameAnchor = frameAnchors ? frameAnchors[anchorIndex] : null;
                    }
                    if (frameAnchor && frameAnchor.getBoundingClientRect) {
                        frameScrollY = Math.max(0, parseInt(
                            frame.contentWindow.pageYOffset
                                || (frameDocument && frameDocument.scrollingElement ? frameDocument.scrollingElement.scrollTop : 0)
                                || (frameDocument && frameDocument.documentElement ? frameDocument.documentElement.scrollTop : 0)
                                || (frameDocument && frameDocument.body ? frameDocument.body.scrollTop : 0)
                                || 0,
                            10
                        ) || 0);
                        anchorDelta = Math.round(frameAnchor.getBoundingClientRect().top) - anchorTop;
                        frame.contentWindow.scrollTo(0, Math.max(0, frameScrollY + anchorDelta));
                        return;
                    }
                    frame.contentWindow.scrollTo(0, scrollY);
                }
            } catch (error) {}
        };

        if (typeof window.requestAnimationFrame === 'function') {
            window.requestAnimationFrame(restore);
        } else {
            restore();
        }
        window.setTimeout(restore, 120);
        window.setTimeout(restore, 420);
        window.setTimeout(restore, 900);
    }

    function syncMemberPurchasePostModalFrame() {
        var state = ensureMemberPurchasePostModal();
        var frameDocument;
        var expectedPostId = '';
        var framePostNode;
        var framePostId = '';
        var titleNode;
        var metaItems = [];
        var likeButton;
        var likeCountNode;
        var likeState = null;

        if (!state.frame) {
            return;
        }

        try {
            frameDocument = state.frame.contentDocument || (state.frame.contentWindow ? state.frame.contentWindow.document : null);
        } catch (error) {
            return;
        }

        if (!frameDocument) {
            return;
        }

        expectedPostId = state.modal ? String(state.modal.getAttribute('data-expert-post-id') || '').trim() : '';
        framePostNode = frameDocument.querySelector('[data-comment-thread][data-post-id], [data-post-like][data-post-id]');
        framePostId = framePostNode ? String(framePostNode.getAttribute('data-post-id') || '').trim() : '';
        if (expectedPostId && framePostId && framePostId !== expectedPostId) {
            return;
        }

        if (state.modal && frameDocument.querySelector('.front-post-main-content, .front-forecast-card, .front-post-detail-stack, .front-post-buy-actions')) {
            state.modal.classList.remove('is-loading');
        }

        titleNode = frameDocument.querySelector('.front-post-modal-sync-source h1, .front-panel-card h1');
        metaItems = Array.prototype.map.call(frameDocument.querySelectorAll('.front-inline-meta > span'), function (node) {
            return String(node.textContent || '').replace(/\s+/g, ' ').trim();
        }).filter(function (text) {
            return text !== '';
        });
        likeButton = frameDocument.querySelector('[data-post-like]');
        if (likeButton) {
            likeCountNode = likeButton.querySelector('[data-post-like-count]');
            likeState = {
                postId: String(likeButton.getAttribute('data-post-id') || '').trim(),
                count: normalizeMemberPurchasePostCount(likeCountNode ? likeCountNode.textContent : ''),
                liked: likeButton.classList.contains('is-liked') || likeButton.getAttribute('aria-pressed') === 'true'
            };
        }

        if (state.title && titleNode) {
            state.title.textContent = String(titleNode.textContent || '').replace(/\s+/g, ' ').trim() || state.title.textContent;
        }

        setMemberPurchasePostModalMeta(buildMemberPurchasePostModalMeta(metaItems, likeState));
    }

    function scheduleMemberPurchasePostModalFrameSync() {
        [0, 80, 240, 600, 1200, 2200].forEach(function (delay) {
            window.setTimeout(syncMemberPurchasePostModalFrame, delay);
        });
    }

    window.addEventListener('message', function (event) {
        var data = event.data || {};
        var modal;
        var frame;

        if (event.origin && event.origin !== window.location.origin) {
            return;
        }

        if (data && data.type === 'front-post-auth-success') {
            modal = document.getElementById('expert-post-modal');
            frame = modal ? modal.querySelector('.expert-post-modal-frame') : null;
            if (frame && event.source && event.source !== frame.contentWindow) {
                return;
            }
            frontPostAuthSyncPending = true;
            syncFrontPostAuthNav();
            return;
        }

        if (!data || data.type !== 'front-post-modal-meta-ready') {
            return;
        }

        modal = document.getElementById('expert-post-modal');
        if (!modal) {
            return;
        }
        frame = modal ? modal.querySelector('.expert-post-modal-frame') : null;
        if (frame && event.source && event.source !== frame.contentWindow) {
            return;
        }
        if (modal && modal.getAttribute('data-expert-post-id') && String(data.postId || '') !== String(modal.getAttribute('data-expert-post-id') || '')) {
            return;
        }

        modal.classList.remove('is-loading');
        scheduleMemberPurchasePostModalFrameSync();
    });

    function closeMemberPurchasePostModal() {
        var state = ensureMemberPurchasePostModal();

        if (!state.modal) {
            return;
        }

        state.modal.classList.remove('is-visible', 'is-loading');
        if (document.body) {
            document.body.classList.remove('expert-post-modal-open');
        }
        window.setTimeout(function () {
            if (!document.body || !document.body.classList.contains('expert-post-modal-open')) {
                state.modal.hidden = true;
                state.modal.removeAttribute('data-expert-post-frame-url');
                state.modal.removeAttribute('data-expert-post-id');
                replaceMemberPurchasePostModalFrame(state.modal);
                if (frontPostAuthSyncPending) {
                    window.location.reload();
                }
            }
        }, 180);
    }

    function syncFrontPostAuthNav() {
        var loginLink = document.querySelector('.bottom-float-nav .bottom-nav-login');
        var label;

        if (!loginLink) {
            return;
        }

        label = loginLink.querySelector('span:last-child');
        if (label && String(label.textContent || '').trim() === '登录') {
            label.textContent = '我的';
        }
    }

    var memberPurchasePostModalFreshCounter = 0;

    function nextMemberPurchasePostModalFreshToken() {
        memberPurchasePostModalFreshCounter += 1;
        return String(Date.now ? Date.now() : (new Date()).getTime()) + '-' + String(memberPurchasePostModalFreshCounter);
    }

    function openMemberPurchasePostModal(rawUrl, titleText) {
        var state = ensureMemberPurchasePostModal();
        var frameUrl = String(rawUrl || '').trim();
        var currentFrameUrl = '';
        var nextPostId = '';
        var freshToken = nextMemberPurchasePostModalFreshToken();

        if (!state.modal || !state.frame || !frameUrl) {
            return false;
        }

        try {
            frameUrl = new URL(frameUrl, window.location.href);
            frameUrl.searchParams.set('modal', '1');
            frameUrl.searchParams.set('_fresh', freshToken);
            nextPostId = String(frameUrl.searchParams.get('id') || '').trim();
            frameUrl = frameUrl.href;
        } catch (error) {
            frameUrl += (frameUrl.indexOf('?') === -1 ? '?' : '&') + 'modal=1&_fresh=' + encodeURIComponent(freshToken);
        }

        if (state.title) {
            state.title.textContent = '帖子阅读';
        }
        setMemberPurchasePostModalMeta([]);

        state.modal.hidden = false;
        state.modal.setAttribute('data-expert-post-frame-url', frameUrl);
        if (nextPostId) {
            state.modal.setAttribute('data-expert-post-id', nextPostId);
        } else {
            state.modal.removeAttribute('data-expert-post-id');
        }
        state.modal.classList.add('is-loading');
        currentFrameUrl = String(state.frame.getAttribute('src') || '').trim();
        if (currentFrameUrl !== frameUrl) {
            state.frame = replaceMemberPurchasePostModalFrame(state.modal);
            currentFrameUrl = '';
        } else {
            syncMemberPurchasePostModalFrame();
        }
        scheduleMemberPurchasePostModalFrameSync();
        window.requestAnimationFrame(function () {
            state.modal.classList.add('is-visible');
            if (currentFrameUrl !== frameUrl) {
                state.frame.setAttribute('src', frameUrl);
            }
        });
        if (document.body) {
            document.body.classList.add('expert-post-modal-open');
        }

        return true;
    }

    function applyMemberRechargeServiceStatus(modal, payload) {
        var titleTarget;
        var hoursTarget;
        var stateTarget;
        var stateText;
        var stateType;

        if (!modal || !payload) {
            return;
        }

        titleTarget = modal.querySelector('[data-member-recharge-service-title]');
        hoursTarget = modal.querySelector('[data-member-recharge-service-hours]');
        stateTarget = modal.querySelector('[data-member-recharge-service-state]');

        if (titleTarget && payload.title) {
            titleTarget.textContent = String(payload.title || '').trim() || '在线客服';
        }

        if (hoursTarget && payload.hours) {
            hoursTarget.textContent = String(payload.hours || '').replace(/\s+/g, ' ').trim() || '接待时间：09:00-23:00';
        }

        if (!stateTarget) {
            return;
        }

        stateText = String(payload.avatar_label || payload.state_text || '').trim();
        stateType = String(payload.avatar_status_type || payload.status_type || '').trim() || 'online';
        if (!stateText) {
            return;
        }

        stateTarget.textContent = stateText;
        stateTarget.setAttribute('data-status-type', stateType);
        stateTarget.hidden = false;
    }

    function syncMemberRechargeServiceHeader(modal) {
        if (!modal) {
            return;
        }

        var frame = modal.querySelector('[data-member-recharge-service-frame]');
        if (!frame || !frame.contentDocument) {
            return;
        }

        var frameDocument = frame.contentDocument;
        var innerTitle = frameDocument.querySelector('.customer-service-title-text');
        var innerHours = frameDocument.querySelector('.customer-service-title-hours');
        var innerState = frameDocument.querySelector('.customer-service-title-state');
        var innerClear = frameDocument.querySelector('[data-customer-service-clear]');
        var clearTarget = modal.querySelector('[data-member-recharge-service-clear]');

        applyMemberRechargeServiceStatus(modal, {
            title: innerTitle ? String(innerTitle.textContent || '').trim() : '',
            hours: innerHours ? String(innerHours.textContent || '').replace(/\s+/g, ' ').trim() : '',
            avatar_label: innerState ? String(innerState.textContent || '').trim() : '',
            avatar_status_type: innerState ? String(innerState.getAttribute('data-status-type') || '').trim() : ''
        });

        if (clearTarget) {
            clearTarget.hidden = !innerClear;
        }
    }

    function handleMemberRechargeServiceStatusMessage(event) {
        var data = event && event.data;
        var modal;
        var frame;
        var expectedOrigin = window.location.origin || (window.location.protocol + '//' + window.location.host);

        if (!data || typeof data !== 'object' || data.type !== 'customer-service-status-sync') {
            return;
        }

        if (event.origin && event.origin !== expectedOrigin) {
            return;
        }

        modal = document.querySelector('[data-member-recharge-service-modal]');
        frame = modal ? modal.querySelector('[data-member-recharge-service-frame]') : null;
        if (!modal || modal.hidden || (frame && frame.contentWindow && event.source !== frame.contentWindow)) {
            return;
        }

        applyMemberRechargeServiceStatus(modal, data);
    }

    window.addEventListener('message', handleMemberRechargeServiceStatusMessage);

    function triggerMemberRechargeServiceClear() {
        var modal = document.querySelector('[data-member-recharge-service-modal]');
        var frame = modal ? modal.querySelector('[data-member-recharge-service-frame]') : null;
        var frameDocument = frame && frame.contentDocument ? frame.contentDocument : null;
        var clearButton = frameDocument ? frameDocument.querySelector('[data-customer-service-clear]') : null;

        if (clearButton) {
            clearButton.click();
        }
    }

    function openMemberRechargeServiceModal(rawUrl) {
        var modal = ensureMemberRechargeServiceModal();
        var frame = modal.querySelector('[data-member-recharge-service-frame]');
        if (frame) {
            frame.onload = function () {
                syncMemberRechargeServiceHeader(modal);
            };
            frame.setAttribute('src', rawUrl);
        }
        setAnimatedHidden(modal, true, 'front-post-service-modal-open');
    }

    function closeMemberRechargeServiceModal() {
        var modal = document.querySelector('[data-member-recharge-service-modal]');
        if (!modal) {
            return;
        }

        setAnimatedHidden(modal, false, 'front-post-service-modal-open');
        window.setTimeout(function () {
            var frame = modal.querySelector('[data-member-recharge-service-frame]');
            if (frame && modal.hidden) {
                frame.removeAttribute('src');
            }
        }, 190);
    }

    function updateServiceAgentScoreViewport() {
        var root = document.documentElement;
        var viewport = window.visualViewport || null;
        var height = viewport && viewport.height ? viewport.height : window.innerHeight;
        var offsetTop = viewport && typeof viewport.offsetTop === 'number' ? viewport.offsetTop : 0;

        if (!root || !height) {
            return;
        }

        root.style.setProperty('--service-agent-score-viewport-height', Math.max(240, Math.floor(height)) + 'px');
        root.style.setProperty('--service-agent-score-viewport-top', Math.max(0, Math.floor(offsetTop)) + 'px');
    }

    function bindServiceAgentScoreViewport() {
        updateServiceAgentScoreViewport();

        if (serviceAgentScoreViewportBound) {
            return;
        }

        serviceAgentScoreViewportBound = true;
        window.addEventListener('resize', updateServiceAgentScoreViewport, { passive: true });
        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', updateServiceAgentScoreViewport, { passive: true });
            window.visualViewport.addEventListener('scroll', updateServiceAgentScoreViewport, { passive: true });
        }
    }

    function updateFormModalViewport() {
        var root = document.documentElement;
        var viewport = window.visualViewport || null;
        var height = viewport && viewport.height ? viewport.height : window.innerHeight;
        var offsetTop = viewport && typeof viewport.offsetTop === 'number' ? viewport.offsetTop : 0;

        if (!root || !height) {
            return;
        }

        root.style.setProperty('--form-modal-viewport-height', Math.max(240, Math.floor(height)) + 'px');
        root.style.setProperty('--form-modal-viewport-top', Math.max(0, Math.floor(offsetTop)) + 'px');
    }

    function bindFormModalViewport() {
        updateFormModalViewport();

        if (formModalViewportBound) {
            return;
        }

        formModalViewportBound = true;
        window.addEventListener('resize', updateFormModalViewport, { passive: true });
        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', updateFormModalViewport, { passive: true });
            window.visualViewport.addEventListener('scroll', updateFormModalViewport, { passive: true });
        }
    }

    function setFormModalKeyboardActive(modal, active) {
        if (!modal) {
            return;
        }

        if (active) {
            bindFormModalViewport();
        }

        modal.classList.toggle('is-keyboard-active', !!active);
    }

    function isFormModalInput(target) {
        if (!target || !target.matches) {
            return false;
        }

        return target.matches('input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]), textarea, [contenteditable="true"]');
    }

    function setFrontInputFocusActive(active) {
        if (!document.body || !document.body.classList) {
            return;
        }

        if (active) {
            bindFormModalViewport();
        }

        document.body.classList.toggle('front-input-focus-active', !!active);
    }

    function formModalScrollContainers(target) {
        var modal;
        var selectors;
        var containers = [];
        var index;
        var node;

        if (!target || !target.closest) {
            return containers;
        }

        modal = target.closest('[data-service-agent-settings-modal], [data-service-agent-score-modal], [data-member-recharge-modal], [data-member-edit-modal]');
        if (!modal) {
            return containers;
        }

        selectors = [
            '.service-agent-settings-form',
            '.service-agent-score-form',
            '.member-recharge-body',
            '.member-recharge-card',
            '.member-recharge-dialog',
            '.member-edit-card',
            '.member-edit-body'
        ];

        for (index = 0; index < selectors.length; index += 1) {
            node = target.closest(selectors[index]);
            if (node && modal.contains(node) && containers.indexOf(node) === -1) {
                containers.push(node);
            }
        }

        containers.push(modal);
        return containers;
    }

    function scrollModalContainerToTarget(container, target) {
        var targetRect;
        var containerRect;
        var topGap = 12;
        var bottomGap = 72;
        var delta = 0;

        if (!container || !target || typeof container.getBoundingClientRect !== 'function') {
            return;
        }

        if (container.scrollHeight <= container.clientHeight + 1) {
            return;
        }

        targetRect = target.getBoundingClientRect();
        containerRect = container.getBoundingClientRect();

        if (targetRect.bottom > containerRect.bottom - bottomGap) {
            delta = targetRect.bottom - containerRect.bottom + bottomGap;
        } else if (targetRect.top < containerRect.top + topGap) {
            delta = targetRect.top - containerRect.top - topGap;
        }

        if (delta !== 0) {
            container.scrollTop += delta;
        }
    }

    function scrollFormModalInputIntoView(target) {
        var containers;

        if (!target || typeof target.scrollIntoView !== 'function') {
            return;
        }

        window.setTimeout(function () {
            updateFormModalViewport();
            updateServiceAgentScoreViewport();
            containers = formModalScrollContainers(target);
            Array.prototype.forEach.call(containers, function (container) {
                scrollModalContainerToTarget(container, target);
            });

            try {
                target.scrollIntoView({ block: 'nearest', inline: 'nearest' });
            } catch (error) {
                target.scrollIntoView();
            }
        }, 120);
    }

    function isUnsafePrefetchUrl(url, link) {
        var bottomNavPrefetchEnabled = !!(
            link
            && link.closest
            && link.closest('.bottom-float-nav')
            && link.getAttribute('data-nav-prefetch') === '1'
        );
        var historyBottomNavPrefetch = !!(
            document.body
            && document.body.classList
            && document.body.classList.contains('history-panel-page')
            && link
            && link.closest
            && link.closest('.bottom-float-nav')
        );

        if (!url || !link) {
            return true;
        }

        if (historyBottomNavPrefetch) {
            return true;
        }

        if (link.hasAttribute('data-no-prefetch')
            || link.matches('[data-confirm-link]')
            || (link.closest
                && link.closest('.bottom-float-nav')
                && link.getAttribute('data-member-prefetch') !== '1'
                && !bottomNavPrefetchEnabled)) {
            return true;
        }

        if (/\/member\.php$/i.test(url.pathname)
            && link.getAttribute('data-member-prefetch') !== '1') {
            return true;
        }

        if (/\/service\.php$/i.test(url.pathname)) {
            return true;
        }

        if (url.search && /[?&](logout|delete|remove|clear)=/i.test(url.search)) {
            return true;
        }

        return false;
    }

    function navigableSameOriginUrl(link) {
        var url;

        if (!link || !link.href || link.target || link.hasAttribute('download')) {
            return '';
        }

        try {
            url = new URL(link.href, window.location.href);
        } catch (error) {
            return '';
        }

        if (url.origin !== window.location.origin || url.protocol.indexOf('http') !== 0) {
            return '';
        }

        if (isUnsafePrefetchUrl(url, link)) {
            return '';
        }

        if (url.pathname === window.location.pathname
            && url.search === window.location.search) {
            return '';
        }

        return url.href;
    }

    function prefetchDocument(url) {
        var node;
        var existingLinks;
        var index;

        if (!url) {
            return;
        }

        existingLinks = document.querySelectorAll('link[rel="prefetch"]');
        for (index = 0; index < existingLinks.length; index += 1) {
            if (existingLinks[index].getAttribute('href') === url) {
                return;
            }
        }

        node = document.createElement('link');
        node.rel = 'prefetch';
        node.href = url;
        node.as = 'document';
        document.head.appendChild(node);
    }

    function runWhenIdle(callback, timeout) {
        if (window.requestIdleCallback) {
            window.requestIdleCallback(callback, { timeout: timeout || 1600 });
            return;
        }

        window.setTimeout(callback, timeout || 1600);
    }

    function initPagePrefetch(root) {
        var body = document.body;
        var isAdminBody = !!(body && body.classList && body.classList.contains('admin-body'));
        var prefetchSoon;
        var prefetchLink;
        var prefetchBottomNavLinks;

        if (!body
            || body.getAttribute('data-page-prefetch-ready') === '1') {
            return;
        }

        body.setAttribute('data-page-prefetch-ready', '1');

        if (body.getAttribute('data-page-prefetch-enabled') !== '1') {
            return;
        }

        prefetchLink = function (link) {
            var url = navigableSameOriginUrl(link);

            if (isAdminBody && !(link.classList && link.classList.contains('admin-nav-link'))) {
                return;
            }

            if (!url || link.getAttribute('data-page-prefetched') === '1') {
                return;
            }

            link.setAttribute('data-page-prefetched', '1');
            runWhenIdle(function () {
                prefetchDocument(url);
            }, 120);
        };

        prefetchBottomNavLinks = function () {
            var links;

            if (document.hidden) {
                return;
            }

            links = document.querySelectorAll('.bottom-float-nav a[data-nav-prefetch="1"]');
            Array.prototype.forEach.call(links, function (link) {
                if (!root || root.contains(link) || root === document) {
                    prefetchLink(link);
                }
            });
        };

        prefetchSoon = debounce(prefetchLink, 80);

        document.addEventListener('mouseover', function (event) {
            var link = event.target.closest ? event.target.closest('a[href]') : null;

            if (link && (!root || root.contains(link) || root === document)) {
                prefetchSoon(link);
            }
        }, { passive: true });

        document.addEventListener('focusin', function (event) {
            var link = event.target.closest ? event.target.closest('a[href]') : null;

            if (link && (!root || root.contains(link) || root === document)) {
                prefetchSoon(link);
            }
        });

        document.addEventListener('touchstart', function (event) {
            var link = event.target.closest ? event.target.closest('a[href]') : null;

            if (link && (!root || root.contains(link) || root === document)) {
                prefetchLink(link);
            }
        }, { passive: true });

        runWhenIdle(prefetchBottomNavLinks, 900);
    }

    function eyeIconSvg(isVisible) {
        if (isVisible) {
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18"></path><path d="M10.58 10.58a2 2 0 0 0 2.84 2.84"></path><path d="M9.88 5.09A10.94 10.94 0 0 1 12 4c5 0 9.27 3.11 11 7.5a11.8 11.8 0 0 1-2.16 3.35"></path><path d="M6.61 6.61C4.62 7.85 3.14 9.53 2 11.5 3.73 15.89 8 19 13 19a10.7 10.7 0 0 0 4.32-.88"></path></svg>';
        }

        return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
    }

    function isAdminAutoReloadDisabled() {
        return !!(document.body && document.body.classList && document.body.classList.contains('admin-body'));
    }

    function noticeMeta(type) {
        switch (type) {
            case 'success':
                return {
                    title: '操作成功',
                    className: 'is-success',
                    icon: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm4.3 7.7-5.1 6.1a1 1 0 0 1-1.47.08l-2.6-2.34a1 1 0 1 1 1.34-1.48l1.82 1.63 4.48-5.36a1 1 0 1 1 1.53 1.36Z" fill="currentColor"/></svg>'
                };
            case 'error':
                return {
                    title: '操作失败',
                    className: 'is-error',
                    icon: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm3.36 13.95a1 1 0 0 1-1.41 1.41L12 13.41l-1.95 1.95a1 1 0 0 1-1.41-1.41L10.59 12 8.64 10.05a1 1 0 1 1 1.41-1.41L12 10.59l1.95-1.95a1 1 0 0 1 1.41 1.41L13.41 12Z" fill="currentColor"/></svg>'
                };
            default:
                return {
                    title: '温馨提醒',
                    className: 'is-info',
                    icon: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm0 15a1.25 1.25 0 1 1 1.25-1.25A1.25 1.25 0 0 1 12 17Zm1.2-5.4a1 1 0 0 1-2 0V7.8a1 1 0 0 1 2 0Z" fill="currentColor"/></svg>'
                };
        }
    }

    function ensureNoticeModal() {
        var existing = document.getElementById('app-notice-modal');
        var message;
        var title;
        var icon;
        var action;

        if (existing) {
            return existing;
        }

        existing = document.createElement('div');
        existing.id = 'app-notice-modal';
        existing.className = 'app-notice-modal front-standard-modal admin-modal';
        existing.setAttribute('hidden', 'hidden');
        existing.innerHTML = '' +
            '<div class="app-notice-backdrop front-standard-modal-backdrop admin-modal-backdrop" data-app-notice-close></div>' +
            '<div class="app-notice-dialog front-standard-modal-dialog admin-modal-card admin-modal-card--sm" role="alertdialog" aria-modal="true" aria-labelledby="app-notice-title">' +
                '<div class="app-notice-head admin-modal-head admin-modal-head--center">' +
                    '<div class="app-notice-icon" data-app-notice-icon></div>' +
                    '<div class="admin-modal-heading">' +
                        '<div class="admin-modal-title-row">' +
                            '<div class="app-notice-title admin-modal-title" id="app-notice-title" data-app-notice-title></div>' +
                        '</div>' +
                        '<div class="app-notice-message admin-modal-subtitle" data-app-notice-message></div>' +
                    '</div>' +
                '</div>' +
                '<div class="app-notice-actions admin-modal-actions">' +
                    '<button type="button" class="app-notice-button" data-app-notice-close>知道了</button>' +
                '</div>' +
            '</div>';
        document.body.appendChild(existing);

        message = existing.querySelector('[data-app-notice-message]');
        title = existing.querySelector('[data-app-notice-title]');
        icon = existing.querySelector('[data-app-notice-icon]');
        action = existing.querySelector('.app-notice-button');

        existing._noticeRefs = {
            message: message,
            title: title,
            icon: icon,
            action: action
        };

        existing.addEventListener('click', function (event) {
            if (event.target.closest('[data-app-notice-close]')) {
                closeNoticeModal();
            }
        });

        return existing;
    }

    function pumpNoticeQueue() {
        var modal;
        var meta;
        var notice;

        if (activeNotice || !noticeQueue.length) {
            return;
        }

        modal = ensureNoticeModal();
        notice = noticeQueue.shift();
        meta = noticeMeta(notice.type);
        activeNotice = notice;

        modal.classList.remove('is-success', 'is-error', 'is-info');
        modal.classList.add('is-visible', meta.className);
        modal.removeAttribute('hidden');
        modal._noticeRefs.title.textContent = notice.title || meta.title;
        modal._noticeRefs.message.textContent = notice.message;
        modal._noticeRefs.icon.innerHTML = meta.icon;
        modal._noticeRefs.action.focus();
    }

    function closeNoticeModal() {
        var modal = document.getElementById('app-notice-modal');
        var redirectUrl = activeNotice && activeNotice.redirect ? activeNotice.redirect : '';

        if (!modal) {
            activeNotice = null;
            return;
        }

        modal.classList.remove('is-visible', 'is-success', 'is-error', 'is-info');
        modal.setAttribute('hidden', 'hidden');
        activeNotice = null;
        if (redirectUrl) {
            window.location.href = redirectUrl;
            return;
        }
        window.setTimeout(pumpNoticeQueue, 0);
    }

    function toast(message, type, title, redirectUrl) {
        if (!message) {
            return;
        }

        noticeQueue.push({
            message: String(message),
            type: type || 'info',
            title: title ? String(title) : '',
            redirect: redirectUrl ? String(redirectUrl) : ''
        });
        pumpNoticeQueue();
    }

    function ensureAppConfirmModal() {
        var existing = document.getElementById('app-confirm-modal');

        if (existing) {
            return existing;
        }

        existing = document.createElement('div');
        existing.id = 'app-confirm-modal';
        existing.className = 'app-confirm-modal front-standard-modal admin-modal';
        existing.setAttribute('hidden', 'hidden');
        existing.innerHTML = '' +
            '<div class="app-confirm-backdrop front-standard-modal-backdrop admin-modal-backdrop" data-app-confirm-cancel></div>' +
            '<section class="app-confirm-dialog front-standard-modal-dialog admin-modal-card admin-modal-card--sm" role="dialog" aria-modal="true" aria-labelledby="app-confirm-title">' +
                '<div class="app-confirm-head admin-modal-head admin-modal-head--center">' +
                    '<div class="app-confirm-icon" data-app-confirm-icon aria-hidden="true"></div>' +
                    '<div class="admin-modal-heading">' +
                        '<div class="admin-modal-title-row">' +
                            '<div class="app-confirm-title admin-modal-title" id="app-confirm-title" data-app-confirm-title></div>' +
                        '</div>' +
                        '<div class="app-confirm-message admin-modal-subtitle" data-app-confirm-message></div>' +
                    '</div>' +
                '</div>' +
                '<div class="app-confirm-actions admin-modal-actions">' +
                    '<button type="button" class="app-confirm-cancel" data-app-confirm-cancel>取消</button>' +
                    '<button type="button" class="app-confirm-submit" data-app-confirm-submit>确定</button>' +
                '</div>' +
            '</section>';
        document.body.appendChild(existing);

        existing._confirmRefs = {
            icon: existing.querySelector('[data-app-confirm-icon]'),
            title: existing.querySelector('[data-app-confirm-title]'),
            message: existing.querySelector('[data-app-confirm-message]'),
            cancel: existing.querySelector('[data-app-confirm-cancel].app-confirm-cancel'),
            submit: existing.querySelector('[data-app-confirm-submit]')
        };

        existing.addEventListener('click', function (event) {
            if (event.target.closest('[data-app-confirm-submit]')) {
                closeAppConfirmModal(true);
                return;
            }

            if (event.target.closest('[data-app-confirm-cancel]')) {
                closeAppConfirmModal(false);
            }
        });

        return existing;
    }

    function closeAppConfirmModal(confirmed) {
        var modal = document.getElementById('app-confirm-modal');
        var resolve;

        if (!modal) {
            return;
        }

        resolve = modal._confirmResolve;
        modal._confirmResolve = null;
        modal.classList.remove('is-visible');
        modal.setAttribute('hidden', 'hidden');
        if (typeof resolve === 'function') {
            resolve(!!confirmed);
        }
    }

    function appConfirm(message, title, confirmText, cancelText) {
        var modal = ensureAppConfirmModal();

        if (typeof modal._confirmResolve === 'function') {
            modal._confirmResolve(false);
        }

        if (modal._confirmRefs.icon) {
            modal._confirmRefs.icon.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm0 5a1 1 0 0 1 1 1v4.2a1 1 0 1 1-2 0V8a1 1 0 0 1 1-1Zm0 10.25a1.25 1.25 0 1 1 1.25-1.25A1.25 1.25 0 0 1 12 17.25Z" fill="currentColor"/></svg>';
        }
        modal._confirmRefs.title.textContent = title || '确认操作';
        modal._confirmRefs.message.textContent = message || '确认执行此操作吗？';
        modal._confirmRefs.submit.textContent = confirmText || '确定';
        modal._confirmRefs.cancel.textContent = cancelText || '取消';
        modal.removeAttribute('hidden');
        window.requestAnimationFrame(function () {
            modal.classList.add('is-visible');
            modal._confirmRefs.cancel.focus();
        });

        return new Promise(function (resolve) {
            modal._confirmResolve = resolve;
        });
    }

    function ensureAppPromptModal() {
        var existing = document.getElementById('app-prompt-modal');

        if (existing) {
            return existing;
        }

        existing = document.createElement('div');
        existing.id = 'app-prompt-modal';
        existing.className = 'app-confirm-modal app-prompt-modal front-standard-modal admin-modal';
        existing.setAttribute('hidden', 'hidden');
        existing.innerHTML = '' +
            '<div class="app-confirm-backdrop front-standard-modal-backdrop admin-modal-backdrop" data-app-prompt-cancel></div>' +
            '<section class="app-confirm-dialog app-prompt-dialog front-standard-modal-dialog admin-modal-card admin-modal-card--sm" role="dialog" aria-modal="true" aria-labelledby="app-prompt-title">' +
                '<div class="app-confirm-head app-prompt-head admin-modal-head admin-modal-head--center">' +
                    '<div class="app-confirm-icon" data-app-prompt-icon aria-hidden="true"></div>' +
                    '<div class="admin-modal-heading">' +
                        '<div class="admin-modal-title-row">' +
                            '<div class="app-confirm-title admin-modal-title" id="app-prompt-title" data-app-prompt-title></div>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="app-prompt-body admin-modal-body">' +
                    '<label class="app-prompt-field">' +
                        '<span class="app-confirm-message app-prompt-label admin-modal-subtitle" data-app-prompt-label></span>' +
                        '<input class="app-prompt-input" type="text" data-app-prompt-input autocomplete="off">' +
                    '</label>' +
                '</div>' +
                '<div class="app-confirm-actions admin-modal-actions">' +
                    '<button type="button" class="app-confirm-cancel" data-app-prompt-cancel>取消</button>' +
                    '<button type="button" class="app-confirm-submit" data-app-prompt-submit>确定</button>' +
                '</div>' +
            '</section>';
        document.body.appendChild(existing);

        existing._promptRefs = {
            icon: existing.querySelector('[data-app-prompt-icon]'),
            title: existing.querySelector('[data-app-prompt-title]'),
            label: existing.querySelector('[data-app-prompt-label]'),
            input: existing.querySelector('[data-app-prompt-input]'),
            cancel: existing.querySelector('[data-app-prompt-cancel].app-confirm-cancel'),
            submit: existing.querySelector('[data-app-prompt-submit]')
        };

        existing.addEventListener('click', function (event) {
            if (event.target.closest('[data-app-prompt-submit]')) {
                closeAppPromptModal(true);
                return;
            }

            if (event.target.closest('[data-app-prompt-cancel]')) {
                closeAppPromptModal(false);
            }
        });

        existing.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                closeAppPromptModal(true);
                return;
            }

            if (event.key === 'Escape' || event.key === 'Esc') {
                event.preventDefault();
                closeAppPromptModal(false);
            }
        });

        return existing;
    }

    function closeAppPromptModal(confirmed) {
        var modal = document.getElementById('app-prompt-modal');
        var resolve;
        var value = null;

        if (!modal) {
            return;
        }

        resolve = modal._promptResolve;
        modal._promptResolve = null;
        if (confirmed && modal._promptRefs && modal._promptRefs.input) {
            value = modal._promptRefs.input.value;
        }
        modal.classList.remove('is-visible');
        modal.setAttribute('hidden', 'hidden');
        if (typeof resolve === 'function') {
            resolve(confirmed ? value : null);
        }
    }

    function appPrompt(message, title, confirmText, cancelText, defaultValue) {
        var modal = ensureAppPromptModal();

        if (typeof modal._promptResolve === 'function') {
            modal._promptResolve(null);
        }

        if (modal._promptRefs.icon) {
            modal._promptRefs.icon.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm0 13.25a1.25 1.25 0 1 1-1.25 1.25A1.25 1.25 0 0 1 12 15.25Zm0-9a4.25 4.25 0 0 1 1.65 8.16.85.85 0 0 0-.55.8v.04a1.1 1.1 0 0 1-2.2 0v-.04a3.05 3.05 0 0 1 1.93-2.84A2.05 2.05 0 1 0 9.95 10a1.1 1.1 0 0 1-2.2 0A4.25 4.25 0 0 1 12 6.25Z" fill="currentColor"/></svg>';
        }
        modal._promptRefs.title.textContent = title || '填写信息';
        modal._promptRefs.label.textContent = message || '请输入内容';
        modal._promptRefs.submit.textContent = confirmText || '确定';
        modal._promptRefs.cancel.textContent = cancelText || '取消';
        modal._promptRefs.input.value = typeof defaultValue === 'undefined' || defaultValue === null ? '' : String(defaultValue);
        modal.removeAttribute('hidden');
        window.requestAnimationFrame(function () {
            modal.classList.add('is-visible');
            modal._promptRefs.input.focus();
            modal._promptRefs.input.select();
        });

        return new Promise(function (resolve) {
            modal._promptResolve = resolve;
        });
    }

    function submitConfirmedForm(form, submitter) {

        form.setAttribute('data-confirm-confirmed', '1');
        if (typeof form.requestSubmit === 'function') {
            try {
                form.requestSubmit(submitter || undefined);
                return;
            } catch (error) {
            }
        }

        form.submit();
    }

    function setFormError(form, message) {
        var target = form.querySelector('[data-form-error]');
        if (target) {
            target.textContent = message || '';
            target.classList.toggle('hidden', !message);
        }
    }

    function toJson(response) {
        return response.text().then(function (text) {
            try {
                return JSON.parse(text);
            } catch (error) {
                throw new Error(text || '返回内容不是有效 JSON。');
            }
        });
    }

    function payloadError(payload, fallbackMessage) {
        var error = new Error((payload && payload.message) || fallbackMessage || '操作失败。');

        if (payload && payload.redirect) {
            error.redirect = payload.redirect;
        }
        if (payload && payload.recharge_modal) {
            error.rechargeModal = String(payload.recharge_modal || '');
        }
        if (payload && payload.recharge_delay) {
            error.rechargeDelay = parseInt(payload.recharge_delay, 10);
        }

        return error;
    }

    function redirectFromPayloadError(error) {
        if (error && error.rechargeModal) {
            toast(error.message, 'error');
            window.setTimeout(function () {
                var modal = document.getElementById(error.rechargeModal);

                if (!modal) {
                    return;
                }

                closeNoticeModal();
                closeMemberRechargeModals(modal);
                setMemberRechargeModal(modal, true);
            }, error.rechargeDelay && error.rechargeDelay > 0 ? error.rechargeDelay * 1000 : 0);
            return true;
        }

        if (error && error.redirect) {
            window.location.href = error.redirect;
            return true;
        }

        return false;
    }

    function initJsonCharts(root) {
        var scope = root || document;
        var nodes;

        if (typeof Chart === 'undefined' || !scope.querySelectorAll) {
            return;
        }

        nodes = scope.querySelectorAll('script[type="application/json"][data-chart-target]');
        Array.prototype.forEach.call(nodes, function (node) {
            var targetId;
            var canvas;
            var config;

            if (node.getAttribute('data-chart-initialized') === '1') {
                return;
            }

            targetId = String(node.getAttribute('data-chart-target') || '').trim();
            if (!targetId) {
                return;
            }

            canvas = document.getElementById(targetId);
            if (!canvas) {
                return;
            }

            try {
                config = JSON.parse(node.textContent || '{}');
            } catch (error) {
                return;
            }

            if (!config || typeof config !== 'object' || !config.type || !config.data) {
                return;
            }

            new Chart(canvas, config);
            node.setAttribute('data-chart-initialized', '1');
        });
    }

    function syncPasswordToggleButton(button, isVisible) {
        if (!button) {
            return;
        }

        button.innerHTML = eyeIconSvg(!!isVisible);
        button.classList.toggle('is-active', !!isVisible);
        button.setAttribute('aria-pressed', isVisible ? 'true' : 'false');
        button.setAttribute('aria-label', isVisible ? '隐藏密码' : '显示密码');
        button.setAttribute('title', isVisible ? '隐藏密码' : '显示密码');
    }

    function initPasswordToggles(root) {
        var scope = root || document;
        var inputs = scope.querySelectorAll('input[type="password"], input[type="text"][data-password-visible="1"]');

        Array.prototype.forEach.call(inputs, function (input, index) {
            var wrapper;
            var group;
            var button;
            var inputId;

            if (!input || String(input.type || '').toLowerCase() === 'hidden') {
                return;
            }

            wrapper = input.parentNode;
            if (!wrapper) {
                return;
            }

            if (wrapper.classList && (wrapper.classList.contains('password-input-group') || wrapper.classList.contains('auth-input-group'))) {
                group = wrapper;
            } else {
                group = document.createElement('div');
                group.className = input.classList && input.classList.contains('auth-input')
                    ? 'password-input-group auth-input-group'
                    : 'password-input-group';
                wrapper.insertBefore(group, input);
                group.appendChild(input);
            }

            inputId = String(input.id || '').trim();
            if (!inputId) {
                inputId = 'password-toggle-' + index + '-' + Date.now();
                input.id = inputId;
            }

            button = group.querySelector('.password-input-toggle, .auth-input-toggle');
            if (!button) {
                button = document.createElement('button');
                button.type = 'button';
                button.className = 'password-input-toggle auth-input-toggle';
                group.appendChild(button);
            }
            button.setAttribute('data-password-toggle', '#' + inputId);
            button.textContent = '显示';
            input.setAttribute('data-password-toggle-ready', '1');
            input.setAttribute('data-password-visible', input.getAttribute('type') === 'text' ? '1' : '0');
            syncPasswordToggleButton(button, input.getAttribute('type') === 'text');
        });
    }

    function customerServiceText(value) {
        return value === null || value === undefined ? '' : String(value);
    }

    function customerServicePayloadSessionId(data) {
        if (!data) {
            return '';
        }
        if (data.active_id !== undefined) {
            return customerServiceText(data.active_id);
        }
        if (data.session && data.session.id !== undefined) {
            return customerServiceText(data.session.id);
        }
        return '';
    }

    function customerServicePost(chat, action, extras, attachment, attachmentName) {
        var formData = new FormData();
        var endpoint = chat.getAttribute('data-api-url') || './api.php';
        var token = chat.getAttribute('data-token') || '';

        formData.append('action', action);
        formData.append('_token', token);

        Object.keys(extras || {}).forEach(function (key) {
            formData.append(key, extras[key]);
        });

        if (attachment) {
            formData.append('attachment', attachment, attachmentName || attachment.name || 'attachment');
        }

        return fetch(endpoint, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        }).then(function (response) {
            return toJson(response);
        }).then(function (payload) {
            if (!payload.success) {
                if (payload.redirect) {
                    window.location.href = payload.redirect;
                }
                throw payloadError(payload, '操作失败。');
            }

            return payload;
        });
    }

    function customerServiceIsBlockNoticeContent(content) {
        var text = customerServiceText(content).trim();

        return text.indexOf('您已被系统屏蔽，解除时间:') === 0
            || text.indexOf('您已被系统屏蔽，解除时间：') === 0
            || text === '您已被系统永久屏蔽，暂时无法继续发送在线客服消息。'
            || text === '您已被客服永久屏蔽，暂时无法继续发送在线客服消息。';
    }

    function customerServiceIsSystemMessage(message) {
        var senderType = customerServiceText(message && message.sender_type);
        var senderName = customerServiceText(message && message.sender_name);

        return senderType === 'system'
            || (senderType !== 'member' && customerServiceIsBlockNoticeContent(message && message.content))
            || (senderName === '系统' && customerServiceIsBlockNoticeContent(message && message.content));
    }

    function customerServiceAgentBlockNoticeDisplay(content) {
        var text = customerServiceText(content);
        var inviteRewardMatch;

        if (text.indexOf('您已被系统屏蔽，解除时间:') === 0) {
            return text.replace('您已被系统屏蔽，解除时间:', '对方已被屏蔽，解除时间:');
        }

        if (text.indexOf('您已被系统屏蔽，解除时间：') === 0) {
            return text.replace('您已被系统屏蔽，解除时间：', '对方已被屏蔽，解除时间:');
        }

        inviteRewardMatch = text.match(/^您的邀请好友\s*(?:「([^」]+)」|【([^】]+)】|\[([^\]]+)\]|(.+?))\s*已注册成功，邀请奖励\s*\+([0-9]+)\s*积分已到账。$/);
        if (inviteRewardMatch) {
            return '该会员邀请的好友「' + customerServiceText(inviteRewardMatch[1] || inviteRewardMatch[2] || inviteRewardMatch[3] || inviteRewardMatch[4]).trim() + '」已注册成功，已向该会员发放邀请奖励 +' + inviteRewardMatch[5] + ' 积分。';
        }

        return text;
    }

    function customerServiceDisplaySystemContent(message, role) {
        var content = customerServiceText(message && message.content);

        if (role === 'agent') {
            return customerServiceAgentBlockNoticeDisplay(content);
        }

        return content;
    }

    function customerServiceMessageElement(message, role) {
        var senderType = customerServiceText(message.sender_type);
        var type = customerServiceText(message.message_type) || 'text';
        var isSelf = (role === 'admin' || role === 'agent') ? senderType === 'agent' : senderType === 'member';
        var row = document.createElement('div');
        var wrap = document.createElement('div');
        var meta = document.createElement('div');
        var name = document.createElement('span');
        var time = document.createElement('span');
        var bubble = document.createElement('div');
        var senderName;
        var previewButton;
        var image;
        var voice;
        var audio;

        if (customerServiceIsSystemMessage(message)) {
            row.className = 'service-thread-system';
            row.setAttribute('data-customer-service-message-id', customerServiceText(message.id));
            bubble.className = 'service-thread-system-pill';
            bubble.textContent = customerServiceDisplaySystemContent(message, role);
            row.appendChild(bubble);

            return row;
        }

        row.className = 'service-thread-message ' + (isSelf ? 'is-self' : 'is-peer');
        row.setAttribute('data-customer-service-message-id', customerServiceText(message.id));
        wrap.className = 'service-thread-message-wrap';
        meta.className = 'service-thread-meta';
        senderName = customerServiceText(message.sender_name);
        if (!senderName && senderType === 'system') {
            senderName = '系统';
        }
        name.textContent = senderName || (isSelf ? '我' : ((role === 'admin' || role === 'agent') ? '会员' : '客服'));
        time.textContent = customerServiceText(message.created_time);
        bubble.className = 'service-thread-bubble is-' + type;

        if (type === 'image') {
            previewButton = document.createElement('button');
            image = document.createElement('img');
            previewButton.type = 'button';
            previewButton.className = 'service-thread-image-open';
            previewButton.setAttribute('data-customer-service-image-preview-open', customerServiceText(message.attachment_url));
            previewButton.setAttribute('data-customer-service-image-preview-title', '聊天图片');
            previewButton.setAttribute('aria-label', '预览聊天图片');
            image.src = customerServiceText(message.attachment_url);
            image.alt = '聊天图片';
            image.loading = 'lazy';
            image.decoding = 'async';
            image.width = 240;
            image.height = 180;
            image.setAttribute('fetchpriority', 'low');
            previewButton.appendChild(image);
            bubble.appendChild(previewButton);
        } else if (type === 'voice') {
            voice = document.createElement('div');
            audio = document.createElement('audio');
            voice.className = 'service-thread-voice';
            voice.innerHTML = '<i class="fa-solid fa-volume-high"></i><span>' + Math.max(1, parseInt(message.voice_duration || 0, 10)) + ' 秒语音</span>';
            audio.controls = true;
            audio.setAttribute('controlsList', 'nodownload noplaybackrate');
            audio.setAttribute('disablepictureinpicture', '');
            audio.preload = 'none';
            audio.src = customerServiceText(message.attachment_url);
            bubble.appendChild(voice);
            bubble.appendChild(audio);
        } else {
            bubble.textContent = customerServiceText(message.content);
            bubble.style.whiteSpace = 'pre-line';
        }

        meta.appendChild(name);
        meta.appendChild(time);
        wrap.appendChild(meta);
        wrap.appendChild(bubble);
        row.appendChild(wrap);

        return row;
    }

    function customerServiceChecksum(value) {
        var text = customerServiceText(value);
        var hash = 0;
        var index;

        for (index = 0; index < text.length; index += 1) {
            hash = ((hash << 5) - hash + text.charCodeAt(index)) | 0;
        }

        return String(hash >>> 0);
    }

    function customerServiceMessagesState(messages) {
        var source = messages || [];
        var parts = [String(source.length)];

        source.forEach(function (message) {
            parts.push([
                customerServiceText(message.id),
                customerServiceText(message.sender_type),
                customerServiceText(message.message_type),
                customerServiceText(message.created_date),
                customerServiceText(message.created_time),
                customerServiceChecksum(message.content),
                customerServiceChecksum(message.attachment_url),
                customerServiceText(message.voice_duration)
            ].join(':'));
        });

        return parts.join('|');
    }

    function customerServiceLogNearBottom(log) {
        if (!log) {
            return true;
        }

        return log.scrollHeight - log.scrollTop - log.clientHeight <= 80;
    }

    function customerServiceScrollToBottom(log) {
        if (log) {
            log.scrollTop = log.scrollHeight;
        }
    }

    function customerServiceScrollToBottomSoon(log) {
        customerServiceScrollToBottom(log);

        if (window.requestAnimationFrame) {
            window.requestAnimationFrame(function () {
                customerServiceScrollToBottom(log);
            });
        }

        [120, 320, 700, 1200].forEach(function (delay) {
            window.setTimeout(function () {
                customerServiceScrollToBottom(log);
            }, delay);
        });
    }

    function appendCustomerServiceMessagesInPlace(chat, log, messages, role) {
        var rendered = log ? log.querySelectorAll('.service-thread-message[data-customer-service-message-id], .customer-service-message[data-customer-service-message-id]') : [];
        var dateNodes;
        var lastDate = '';
        var index;
        var date;
        var divider;

        if (!log || !messages || !messages.length || !rendered.length || rendered.length > messages.length) {
            return false;
        }

        for (index = 0; index < rendered.length; index += 1) {
            if (customerServiceText(rendered[index].getAttribute('data-customer-service-message-id')) !== customerServiceText(messages[index].id)) {
                return false;
            }
        }

        if (rendered.length === messages.length) {
            return false;
        }

        dateNodes = log.querySelectorAll('.service-thread-date, .customer-service-date');
        if (dateNodes.length) {
            lastDate = customerServiceText(dateNodes[dateNodes.length - 1].textContent);
        }

        log.removeAttribute('hidden');
        for (index = rendered.length; index < messages.length; index += 1) {
            date = customerServiceText(messages[index].created_date);
            if (date && date !== lastDate) {
                divider = document.createElement('div');
                divider.className = 'service-thread-date';
                divider.textContent = date;
                log.appendChild(divider);
                lastDate = date;
            }
            log.appendChild(customerServiceMessageElement(messages[index], role));
        }

        return true;
    }

    function renderCustomerServiceMessages(chat, messages, options) {
        var log = chat.querySelector('[data-customer-service-log]');
        var role = chat.getAttribute('data-customer-service-role') || 'member';
        var emptyText = chat.getAttribute('data-empty-text') || '暂无消息。';
        var forceScroll = !!(options && options.forceScroll);
        var preserveScroll = !!(options && options.preserveScroll);
        var forceAppendScroll = !!(options && options.forceAppendScroll);
        var nextState = customerServiceMessagesState(messages);
        var previousState;
        var shouldStickBottom;
        var previousScrollTop;
        var previousMessageCount = 0;
        var nextMessageCount = messages && messages.length ? messages.length : 0;
        var hasNewMessageRows = false;
        var lastDate = '';
        var empty;
        var pendingRows = [];
        var fragment = document.createDocumentFragment();

        if (!log) {
            return;
        }

        previousState = log.getAttribute('data-customer-service-message-state') || '';
        previousMessageCount = parseInt(log.getAttribute('data-customer-service-message-count') || '0', 10);
        if (!isFinite(previousMessageCount) || previousMessageCount < 0) {
            previousMessageCount = 0;
        }
        hasNewMessageRows = nextMessageCount > previousMessageCount;
        if (!forceScroll && previousState === nextState) {
            return;
        }

        shouldStickBottom = !preserveScroll && (forceScroll || customerServiceLogNearBottom(log));
        if (forceAppendScroll && hasNewMessageRows) {
            shouldStickBottom = true;
        }
        previousScrollTop = log.scrollTop;

        if (!forceScroll) {
            Array.prototype.forEach.call(log.querySelectorAll('[data-customer-service-pending-message="1"]'), function (row) {
                pendingRows.push(row);
            });
        }

        if (preserveScroll && appendCustomerServiceMessagesInPlace(chat, log, messages || [], role)) {
            log.setAttribute('data-customer-service-message-state', nextState);
            log.setAttribute('data-customer-service-message-count', String(nextMessageCount));
            if (forceAppendScroll && hasNewMessageRows) {
                customerServiceScrollToBottomSoon(log);
            } else {
                log.scrollTop = previousScrollTop;
            }
            return;
        }

        log.removeAttribute('hidden');
        log.setAttribute('data-customer-service-message-state', nextState);
        log.setAttribute('data-customer-service-message-count', String(nextMessageCount));

        if (!messages || !messages.length) {
            if (pendingRows.length) {
                pendingRows.forEach(function (row) {
                    fragment.appendChild(row);
                });
                replaceChildrenFast(log, fragment);
                if (shouldStickBottom) {
                    customerServiceScrollToBottomSoon(log);
                } else {
                    log.scrollTop = previousScrollTop;
                }
                return;
            }
            empty = document.createElement('div');
            empty.className = role === 'admin' ? 'admin-empty' : 'service-thread-empty';
            empty.setAttribute('data-customer-service-empty', '1');
            empty.textContent = emptyText;
            fragment.appendChild(empty);
            replaceChildrenFast(log, fragment);
            if (shouldStickBottom) {
                customerServiceScrollToBottomSoon(log);
            } else {
                log.scrollTop = previousScrollTop;
            }
            return;
        }

        messages.forEach(function (message) {
            var date = customerServiceText(message.created_date);
            var divider;

            if (date && date !== lastDate) {
                divider = document.createElement('div');
                divider.className = 'service-thread-date';
                divider.textContent = date;
                fragment.appendChild(divider);
                lastDate = date;
            }

            fragment.appendChild(customerServiceMessageElement(message, role));
        });
        pendingRows.forEach(function (row) {
            fragment.appendChild(row);
        });

        replaceChildrenFast(log, fragment);

        if (shouldStickBottom) {
            customerServiceScrollToBottomSoon(log);
        } else {
            log.scrollTop = previousScrollTop;
        }
    }

    function latestCustomerServiceIncomingMessageId(chat, messages) {
        var role = chat.getAttribute('data-customer-service-role') || 'member';
        var incomingSender = (role === 'admin' || role === 'agent') ? 'member' : 'agent';
        var latestId = 0;

        (messages || []).forEach(function (message) {
            var senderType = customerServiceText(message.sender_type);
            var messageId = parseInt(message.id || 0, 10);

            if (!customerServiceIsSystemMessage(message) && senderType === incomingSender && messageId > latestId) {
                latestId = messageId;
            }
        });

        return latestId;
    }

    function countCustomerServiceIncomingMessagesAfter(chat, messages, afterId) {
        var role = chat.getAttribute('data-customer-service-role') || 'member';
        var incomingSender = (role === 'admin' || role === 'agent') ? 'member' : 'agent';
        var baseline = parseInt(afterId || 0, 10);
        var count = 0;

        (messages || []).forEach(function (message) {
            var senderType = customerServiceText(message.sender_type);
            var messageId = parseInt(message.id || 0, 10);

            if (!customerServiceIsSystemMessage(message) && senderType === incomingSender && messageId > baseline) {
                count += 1;
            }
        });

        return count;
    }

    function latestRenderedIncomingMessageId(chat) {
        var latestId = 0;
        var log = chat.querySelector('[data-customer-service-log]');

        if (!log) {
            return 0;
        }

        Array.prototype.forEach.call(log.querySelectorAll('.service-thread-message.is-peer[data-customer-service-message-id], .customer-service-message.is-peer[data-customer-service-message-id]'), function (message) {
            var messageId = parseInt(message.getAttribute('data-customer-service-message-id') || '0', 10);

            if (messageId > latestId) {
                latestId = messageId;
            }
        });

        return latestId;
    }

    function latestRenderedCustomerServiceMessageId(chat) {
        var latestId = 0;
        var log = chat.querySelector('[data-customer-service-log]');

        if (!log) {
            return 0;
        }

        Array.prototype.forEach.call(log.querySelectorAll('[data-customer-service-message-id]'), function (message) {
            var messageId = parseInt(message.getAttribute('data-customer-service-message-id') || '0', 10);

            if (messageId > latestId) {
                latestId = messageId;
            }
        });

        return latestId;
    }

    function serviceAgentHasActiveSession(chat) {
        return chat.getAttribute('data-has-session') === '1' && parseInt(chat.getAttribute('data-session-id') || '0', 10) > 0;
    }

    function customerServiceBadgeText(count) {
        var value = Math.max(0, parseInt(count || 0, 10));

        return value > 99 ? '99+' : String(value);
    }

    function setServiceAgentSwitchBadge(chat, selector, count) {
        var badge = chat.querySelector(selector);
        var value = Math.max(0, parseInt(count || 0, 10));
        var label = customerServiceBadgeText(value);

        if (!badge) {
            return;
        }

        badge.textContent = label;
        badge.hidden = value <= 0;
        badge.setAttribute('aria-label', '最新消息' + label + '条');
    }

    function serviceAgentChatUnreadCount(chat) {
        return Math.max(0, parseInt(chat.getAttribute('data-service-agent-chat-unread-count') || '0', 10));
    }

    function serviceAgentQueueUnreadCount(chat) {
        return Math.max(0, parseInt(chat.getAttribute('data-service-agent-queue-unread-count') || '0', 10));
    }

    function serviceAgentQueueUnreadCountFromBadge(chat) {
        var badge = chat.querySelector('[data-service-agent-queue-unread]');
        var value;

        if (!badge || badge.hidden) {
            return 0;
        }

        value = parseInt((badge.textContent || '').replace(/[^\d]/g, ''), 10);

        return isFinite(value) ? Math.max(0, value) : 0;
    }

    function countServiceAgentQueueUnread(sessions) {
        var count = 0;

        (sessions || []).forEach(function (session) {
            count += Math.max(0, parseInt(session.unread_for_admin || 0, 10));
        });

        return count;
    }

    function setServiceAgentChatUnread(chat, count) {
        var value = Math.max(0, parseInt(count || 0, 10));

        chat.setAttribute('data-service-agent-chat-unread-count', String(value));
        setServiceAgentSwitchBadge(chat, '[data-service-agent-chat-unread]', value);
    }

    function updateServiceAgentQueueUnread(chat, sessions) {
        var count = countServiceAgentQueueUnread(sessions);

        chat.setAttribute('data-service-agent-queue-unread-count', String(count));
        setServiceAgentSwitchBadge(chat, '[data-service-agent-queue-unread]', count);

        return count;
    }

    function clearServiceAgentSessionUnread(chat, sessionId) {
        var id = customerServiceText(sessionId);
        var card;
        var badge;
        var previousUnread;
        var queueUnread;

        if (!chat || !id || id === '0') {
            return;
        }

        card = chat.querySelector('[data-service-agent-session-card][data-customer-service-session-id="' + id + '"]');
        if (!card) {
            return;
        }

        badge = card.querySelector('.service-agent-session-unread');
        if (!badge || badge.hidden) {
            return;
        }

        previousUnread = parseInt((badge.textContent || '').replace(/[^\d]/g, ''), 10);
        if (!isFinite(previousUnread) || previousUnread <= 0) {
            previousUnread = 0;
        }

        badge.textContent = '0';
        badge.hidden = true;
        badge.setAttribute('aria-label', '未读信息0条');

        if (previousUnread > 0) {
            queueUnread = Math.max(0, serviceAgentQueueUnreadCount(chat) - previousUnread);
            chat.setAttribute('data-service-agent-queue-unread-count', String(queueUnread));
            setServiceAgentSwitchBadge(chat, '[data-service-agent-queue-unread]', queueUnread);
        }
    }

    function serviceAgentCanMarkRead(chat) {
        var log = chat.querySelector('[data-customer-service-log]');

        return (chat.getAttribute('data-customer-service-role') || '') === 'agent'
            && serviceAgentChatPanelIsReadable(chat)
            && serviceAgentHasActiveSession(chat)
            && customerServiceLogNearBottom(log);
    }

    function persistServiceAgentView(view) {
        var nextUrl;

        if (!window.history || !window.history.replaceState) {
            return;
        }

        try {
            nextUrl = new URL(window.location.href);
            nextUrl.searchParams.set('agent_view', view === 'chat' ? 'chat' : 'queue');
            window.history.replaceState(null, '', nextUrl.toString());
        } catch (error) {
        }
    }

    function serviceAgentUsesDesktopSplit() {
        return !!(window.matchMedia && window.matchMedia('(min-width: 721px)').matches);
    }

    function serviceAgentChatPanelIsReadable(chat) {
        return serviceAgentUsesDesktopSplit() || (chat.getAttribute('data-agent-active-view') || '') === 'chat';
    }

    function setServiceAgentView(chat, view, persist) {
        var role = chat.getAttribute('data-customer-service-role') || 'member';
        var hasSession = serviceAgentHasActiveSession(chat);
        var previousView = chat.getAttribute('data-agent-active-view') || 'queue';
        var targetView = view === 'chat' && hasSession ? 'chat' : 'queue';
        var switchedToChat = targetView === 'chat' && previousView !== 'chat';
        var desktopSplit = serviceAgentUsesDesktopSplit();
        var log;

        if (role !== 'agent') {
            return;
        }

        chat.setAttribute('data-agent-active-view', targetView);
        chat.setAttribute('data-service-agent-layout', desktopSplit ? 'split' : 'single');
        chat.classList.toggle('is-queue-view', targetView === 'queue');
        chat.classList.toggle('is-chat-view', targetView === 'chat');
        if (persist) {
            persistServiceAgentView(targetView);
        }

        Array.prototype.forEach.call(chat.querySelectorAll('[data-service-agent-view-target]'), function (button) {
            var buttonView = button.getAttribute('data-service-agent-view-target') || 'queue';
            var isActive = buttonView === targetView;
            var isTab = button.getAttribute('role') === 'tab';

            button.classList.toggle('is-active', isTab && isActive);
            if (isTab) {
                button.setAttribute('aria-selected', isActive ? 'true' : 'false');
            }

            if (buttonView === 'chat') {
                button.disabled = !hasSession;
            }
        });

        Array.prototype.forEach.call(chat.querySelectorAll('[data-service-agent-panel]'), function (panel) {
            var panelHidden = !desktopSplit && (panel.getAttribute('data-service-agent-panel') || 'queue') !== targetView;

            panel.hidden = panelHidden;
            panel.setAttribute('aria-hidden', panelHidden ? 'true' : 'false');
        });

        if (targetView === 'chat') {
            log = chat.querySelector('[data-customer-service-log]');
            if (switchedToChat) {
                setServiceAgentChatUnread(chat, 0);
            }
            if (log && switchedToChat) {
                customerServiceScrollToBottomSoon(log);
            }
        }
    }

    function bindServiceAgentDesktopSplitWatcher(chat) {
        var media;
        var updateLayout;

        if (!chat || !window.matchMedia || chat.getAttribute('data-service-agent-layout-watch-ready') === '1') {
            return;
        }

        media = window.matchMedia('(min-width: 721px)');
        updateLayout = function () {
            setServiceAgentView(chat, chat.getAttribute('data-agent-active-view') || 'queue');
        };

        chat.setAttribute('data-service-agent-layout-watch-ready', '1');
        if (media.addEventListener) {
            media.addEventListener('change', updateLayout);
        } else if (media.addListener) {
            media.addListener(updateLayout);
        }
    }

    function customerServiceSessionsState(sessions, activeId, role) {
        var parts = [customerServiceText(activeId), customerServiceText(role), String((sessions || []).length)];

        (sessions || []).forEach(function (session) {
            parts.push([
                customerServiceText(session.id),
                customerServiceText(session.username),
                customerServiceText(session.member_online_type),
                customerServiceText(session.status),
                customerServiceText(session.status_label),
                customerServiceText(session.unread_for_admin),
                customerServiceText(session.last_message_type),
                customerServiceChecksum(session.last_message_preview),
                customerServiceText(session.last_message_at),
                customerServiceText(session.assigned_agent_id),
                customerServiceText(session.blocked ? '1' : '0')
            ].join(':'));
        });

        return parts.join('|');
    }

    function updateServiceAgentSessionCardInPlace(card, session, activeId, status, baseUrl, separator) {
        var id = parseInt(session.id || 0, 10);
        var idText = customerServiceText(id);
        var username = customerServiceText(session.username) || '会员';
        var unread = Math.max(0, parseInt(session.unread_for_admin || 0, 10));
        var unreadLabel = unread > 99 ? '99+' : String(unread);
        var isActive = id === parseInt(activeId || 0, 10);
        var sessionHref = baseUrl + separator + 'status=' + encodeURIComponent(status) + '&session_id=' + encodeURIComponent(id) + '&agent_view=chat';
        var title = card.querySelector('.service-agent-session-meta strong');
        var presence = card.querySelector('.customer-service-session-presence');
        var preview = card.querySelector('.service-agent-session-preview');
        var time = card.querySelector('.service-agent-session-time');
        var unreadBadge = card.querySelector('.service-agent-session-unread');
        var selectInput = card.querySelector('[data-service-agent-session-select]');
        var deleteButton = card.querySelector('[data-service-agent-session-delete]');

        card.classList.toggle('is-active', isActive);
        card.setAttribute('data-session-href', sessionHref);
        card.setAttribute('data-customer-service-session-id', idText);

        if (title) {
            title.textContent = username;
        }
        if (presence) {
            presence.textContent = customerServiceText(session.member_online_label) || '离线';
            presence.setAttribute('data-status-type', customerServiceText(session.member_online_type) || 'offline');
        }
        if (preview) {
            preview.textContent = customerServiceAgentBlockNoticeDisplay(session.last_message_preview) || '暂无消息';
        }
        if (time) {
            time.textContent = customerServiceText(session.last_message_at);
        }
        if (unreadBadge) {
            unreadBadge.textContent = unreadLabel;
            unreadBadge.hidden = unread <= 0;
            unreadBadge.setAttribute('aria-label', '未读信息' + unreadLabel + '条');
        }
        if (selectInput) {
            selectInput.value = idText;
            selectInput.setAttribute('aria-label', '选择' + username);
        }
        if (deleteButton) {
            deleteButton.setAttribute('data-session-id', idText);
            deleteButton.setAttribute('aria-label', '删除' + username + '会话');
        }
    }

    function updateServiceAgentSessionsInPlace(list, sessions, activeId, status, baseUrl, separator) {
        var cards = list ? list.querySelectorAll('[data-service-agent-session-card][data-customer-service-session-id]') : [];
        var byId = {};
        var canUpdate = true;

        if (!list || !sessions || !sessions.length || cards.length !== sessions.length) {
            return false;
        }

        sessions.forEach(function (session) {
            var id = customerServiceText(session.id);

            if (!id || byId[id]) {
                canUpdate = false;
                return;
            }
            byId[id] = session;
        });

        if (!canUpdate) {
            return false;
        }

        Array.prototype.forEach.call(cards, function (card, index) {
            var id = customerServiceText(card.getAttribute('data-customer-service-session-id'));
            var expectedSession = sessions[index];
            var expectedId = expectedSession ? customerServiceText(expectedSession.id) : '';

            if (!id || !byId[id] || id !== expectedId) {
                canUpdate = false;
            }
        });

        if (!canUpdate) {
            return false;
        }

        Array.prototype.forEach.call(cards, function (card) {
            var id = customerServiceText(card.getAttribute('data-customer-service-session-id'));

            updateServiceAgentSessionCardInPlace(card, byId[id], activeId, status, baseUrl, separator);
        });

        return true;
    }

    function renderCustomerServiceSessions(chat, sessions, activeId) {
        var list = chat.querySelector('[data-customer-service-session-list]');
        var queueCount = chat.querySelector('[data-service-agent-queue-count]');
        var status = chat.getAttribute('data-status') || 'all';
        var role = chat.getAttribute('data-customer-service-role') || 'member';
        var baseUrl = chat.getAttribute('data-session-base-url') || (window.location.pathname || 'admin.php');
        var separator = baseUrl.indexOf('?') === -1 ? '?' : '&';
        var fragment = document.createDocumentFragment();
        var selectedIds = role === 'agent' ? selectedServiceAgentSessionIds(chat) : [];
        var selectedIdMap = {};
        var nextState;
        var previousState;
        var previousScrollTop;

        Array.prototype.forEach.call(selectedIds, function (id) {
            selectedIdMap[id] = true;
        });

        if (!list) {
            return;
        }

        nextState = customerServiceSessionsState(sessions, activeId, role);
        previousState = list.getAttribute('data-customer-service-session-state') || '';
        if (previousState === nextState) {
            return;
        }
        previousScrollTop = list.scrollTop;
        list.setAttribute('data-customer-service-session-state', nextState);

        if (queueCount) {
            queueCount.textContent = String((sessions || []).length) + '人';
        }

        if (role === 'agent' && updateServiceAgentSessionsInPlace(list, sessions, activeId, status, baseUrl, separator)) {
            syncServiceAgentQueueSelection(chat);
            return;
        }

        if (!sessions || !sessions.length) {
            var empty = document.createElement('div');
            empty.className = 'admin-empty support-empty';
            if (role === 'agent') {
                empty.className = 'customer-service-empty';
            }
            empty.textContent = role === 'agent' ? '当前没有需要接待的会话。' : '当前没有客服会话。';
            fragment.appendChild(empty);
            replaceChildrenFast(list, fragment);
            list.scrollTop = previousScrollTop;
            if (role === 'agent') {
                syncServiceAgentQueueSelection(chat);
            }
            return;
        }

        sessions.forEach(function (session) {
            var id = parseInt(session.id || 0, 10);
            var unread = parseInt(session.unread_for_admin || 0, 10);
            var link = document.createElement('a');
            var selectLabel = document.createElement('label');
            var selectInput = document.createElement('input');
            var selectBox = document.createElement('span');
            var main = document.createElement('span');
            var topRow = document.createElement('span');
            var meta = document.createElement('span');
            var title = document.createElement('strong');
            var presence = document.createElement('span');
            var preview = document.createElement('span');
            var previewRow = document.createElement('span');
            var deleteButton = document.createElement('button');
            var deleteIcon = document.createElement('i');
            var side = document.createElement('span');
            var badge = document.createElement('span');
            var unreadBadge = document.createElement('em');
            var time = document.createElement('small');
            var isBlocked = !!session.blocked;
            var sessionHref;
            var sessionStatus = role === 'agent' ? (isBlocked ? 'blocked' : 'waiting') : (customerServiceText(session.status) || 'waiting');
            var unreadLabel = unread > 99 ? '99+' : String(unread);

            link.className = 'customer-service-session-item support-ticket-card' + (id === parseInt(activeId || 0, 10) ? ' is-active' : '');
            if (role === 'agent') {
                link = document.createElement('div');
                sessionHref = baseUrl + separator + 'status=' + encodeURIComponent(status) + '&session_id=' + encodeURIComponent(id) + '&agent_view=chat';
                link.className = 'customer-service-session-item service-agent-session' + (id === parseInt(activeId || 0, 10) ? ' is-active' : '');
                link.setAttribute('role', 'link');
                link.setAttribute('tabindex', '0');
                link.setAttribute('data-service-agent-session-card', '1');
                link.setAttribute('data-session-href', sessionHref);
                selectLabel.className = 'service-agent-session-check';
                selectLabel.setAttribute('title', '选择会话');
                selectInput.type = 'checkbox';
                selectInput.value = customerServiceText(id);
                selectInput.setAttribute('data-service-agent-session-select', '1');
                selectInput.setAttribute('aria-label', '选择' + (customerServiceText(session.username) || '会员'));
                selectInput.checked = !!selectedIdMap[customerServiceText(id)];
                selectLabel.appendChild(selectInput);
                selectLabel.appendChild(selectBox);
            } else {
                link.href = baseUrl + '?page=support&status=' + encodeURIComponent(status) + '&session_id=' + encodeURIComponent(id);
            }
            link.setAttribute('data-customer-service-session-id', customerServiceText(id));
            main.className = 'customer-service-session-main support-ticket-main';
            if (role === 'agent') {
                main.className = 'customer-service-session-main service-agent-session-main';
            }
            title.textContent = customerServiceText(session.username) || '会员';
            presence.className = 'customer-service-session-presence';
            presence.setAttribute('data-status-type', customerServiceText(session.member_online_type) || 'offline');
            presence.textContent = customerServiceText(session.member_online_label) || '离线';
            preview.textContent = role === 'agent'
                ? (customerServiceAgentBlockNoticeDisplay(session.last_message_preview) || '暂无消息')
                : (customerServiceText(session.last_message_preview) || '暂无消息');
            side.className = 'customer-service-session-side support-ticket-side';
            if (role === 'agent') {
                topRow.className = 'service-agent-session-top';
                meta.className = 'service-agent-session-meta';
                preview.className = 'service-agent-session-preview';
                side.className = 'customer-service-session-side service-agent-session-side';
                time.className = 'service-agent-session-time';
                unreadBadge.className = 'service-agent-session-unread';
                unreadBadge.textContent = unreadLabel;
                unreadBadge.setAttribute('aria-label', '未读信息' + unreadLabel + '条');
                unreadBadge.hidden = unread <= 0;
                previewRow.className = 'service-agent-session-preview-row';
                deleteButton.type = 'button';
                deleteButton.className = 'service-agent-session-delete';
                deleteButton.setAttribute('data-service-agent-session-delete', '1');
                deleteButton.setAttribute('data-session-id', customerServiceText(id));
                deleteButton.setAttribute('aria-label', '删除' + (customerServiceText(session.username) || '会员') + '会话');
                deleteButton.setAttribute('title', '删除会话');
                deleteIcon.className = 'fa-solid fa-trash-can';
                deleteIcon.setAttribute('aria-hidden', 'true');
                deleteButton.appendChild(deleteIcon);
            }
            time.textContent = customerServiceText(session.last_message_at);

            if (role === 'agent') {
                meta.appendChild(selectLabel);
                meta.appendChild(title);
                meta.appendChild(presence);
                side.appendChild(time);
                side.appendChild(unreadBadge);
                topRow.appendChild(meta);
                topRow.appendChild(side);
                main.appendChild(topRow);
                previewRow.appendChild(preview);
                previewRow.appendChild(deleteButton);
                main.appendChild(previewRow);
            } else {
                main.appendChild(title);
                main.appendChild(presence);
                main.appendChild(preview);
                badge.className = 'support-status-badge admin-badge ' + (isBlocked ? 'is-blocked' : (unread > 0 ? 'is-hot is-danger' : 'is-' + sessionStatus + (sessionStatus === 'closed' ? ' is-info' : ' is-success')));
                badge.textContent = isBlocked ? '已屏蔽' : (unread > 0 ? String(unread) : (customerServiceText(session.status_label) || '待接待'));
                side.appendChild(badge);
                side.appendChild(time);
            }
            link.appendChild(main);
            if (role !== 'agent') {
                link.appendChild(side);
            }
            fragment.appendChild(link);
        });

        replaceChildrenFast(list, fragment);
        list.scrollTop = previousScrollTop;
        if (role === 'agent') {
            syncServiceAgentQueueSelection(chat);
        }
    }

    function customerServiceStatusPayload(type) {
        if (type === 'image') {
            return {
                is_typing: true,
                status_type: 'image',
                text: '输入：发送图片中...',
                avatar_label: '发图中',
                avatar_status_type: 'image'
            };
        }

        if (type === 'voice') {
            return {
                is_typing: true,
                status_type: 'voice',
                text: '输入：发送语音中...',
                avatar_label: '语音中',
                avatar_status_type: 'voice'
            };
        }

        if (type === 'typing') {
            return {
                is_typing: true,
                status_type: 'typing',
                text: '输入：正在输入中...',
                avatar_label: '输入中',
                avatar_status_type: 'typing'
            };
        }

        return {
            is_typing: false,
            status_type: 'serving',
            text: ''
        };
    }

    function postCustomerServiceStatusToParent(chat, avatarText, avatarStatusType, statusType, text) {
        var titleNode;
        var hoursNode;
        var targetOrigin;

        if (!chat || !window.parent || window.parent === window) {
            return;
        }

        titleNode = document.querySelector('.customer-service-title-text');
        hoursNode = document.querySelector('.customer-service-title-hours');
        targetOrigin = window.location.origin || (window.location.protocol + '//' + window.location.host);

        try {
            window.parent.postMessage({
                type: 'customer-service-status-sync',
                avatar_label: String(avatarText || '').trim(),
                avatar_status_type: String(avatarStatusType || '').trim(),
                status_type: String(statusType || '').trim(),
                state_text: String(text || '').trim(),
                title: titleNode ? String(titleNode.textContent || '').trim() : '',
                hours: hoursNode ? String(hoursNode.textContent || '').replace(/\s+/g, ' ').trim() : ''
            }, targetOrigin);
        } catch (error) {
        }
    }

    function renderCustomerServiceStatus(chat, status) {
        var targets = chat.querySelectorAll('[data-customer-service-status]');
        var avatars = chat.querySelectorAll('[data-customer-service-avatar-status]');
        var localStatus = chat.getAttribute('data-customer-service-local-status') || '';
        var statusType;
        var text;
        var avatarText;
        var avatarStatusType;
        var nextState;

        if (localStatus) {
            status = customerServiceStatusPayload(localStatus);
        } else if (!status) {
            status = customerServiceStatusPayload('serving');
        }

        statusType = status.status_type || (status.is_typing ? 'typing' : 'serving');

        if (!targets.length && !avatars.length) {
            return;
        }

        text = status && status.text ? customerServiceText(status.text) : '';
        avatarText = status && status.avatar_label ? customerServiceText(status.avatar_label) : '';
        avatarStatusType = status && status.avatar_status_type ? status.avatar_status_type : statusType;
        nextState = [localStatus, statusType, text, avatarText, avatarStatusType].join('|');
        if (chat.getAttribute('data-customer-service-status-render-state') === nextState) {
            return;
        }
        chat.setAttribute('data-customer-service-status-render-state', nextState);

        Array.prototype.forEach.call(targets, function (target) {
            target.hidden = !text;
            target.textContent = text;
            target.setAttribute('data-status-type', statusType);
            target.classList.toggle('is-typing', !!(text && status && status.is_typing));
        });

        if (!avatarText) {
            return;
        }

        Array.prototype.forEach.call(avatars, function (avatar) {
            avatar.textContent = avatarText;
            avatar.setAttribute('data-status-type', avatarStatusType);
        });

        postCustomerServiceStatusToParent(chat, avatarText, avatarStatusType, statusType, text);
    }

    function renderServiceAgentPresence(chat, data) {
        var role = chat.getAttribute('data-customer-service-role') || 'member';
        var label = chat.querySelector('[data-service-agent-presence-label]');
        var button = chat.querySelector('[data-service-agent-presence-toggle]');
        var servingField = chat.querySelector('[data-service-agent-settings-form] [name="serving"][type="checkbox"]');
        var online;
        var statusType;
        var statusLabel;
        var nextState;

        if (role !== 'agent' || !data) {
            return;
        }

        online = !!data.agent_online;
        statusType = customerServiceText(data.agent_online_type) || (online ? 'online' : 'offline');
        statusLabel = customerServiceText(data.agent_online_label) || (online ? '在线中···' : '休息中···');
        nextState = [online ? '1' : '0', statusType, statusLabel, serviceAgentSettingsModalOpen(chat) ? '1' : '0'].join('|');
        if (chat.getAttribute('data-service-agent-presence-render-state') === nextState) {
            return;
        }
        chat.setAttribute('data-service-agent-presence-render-state', nextState);
        chat.setAttribute('data-agent-online', online ? '1' : '0');

        if (label) {
            label.textContent = statusLabel;
            label.setAttribute('data-status-type', statusType);
        }

        if (button) {
            button.textContent = online ? '在线中···' : '休息中···';
            button.setAttribute('data-next-online', online ? '0' : '1');
            button.setAttribute('aria-pressed', online ? 'true' : 'false');
            button.classList.toggle('is-online', online);
            button.classList.toggle('is-offline', !online);
        }

        if (servingField && !serviceAgentSettingsModalOpen(chat)) {
            servingField.checked = online;
        }
    }

    function serviceAgentSettingsModalOpen(chat) {
        var modal = chat.querySelector('[data-service-agent-settings-modal]');

        return !!(modal && !modal.hidden);
    }

    function setServiceAgentSettingsModal(chat, open) {
        var modal = chat.querySelector('[data-service-agent-settings-modal]');

        if (!modal) {
            return;
        }

        if (!open) {
            setFormModalKeyboardActive(modal, false);
        }
        setAnimatedHidden(modal, open, 'service-agent-settings-open');
        if (open) {
            window.setTimeout(function () {
                var firstField = modal.querySelector('input[name="display_name"], textarea, button');
                if (firstField) {
                    firstField.focus();
                }
            }, 30);
        }
    }

    function setServiceAgentSettingsKeyboardActive(chat, active) {
        var modal = chat.querySelector('[data-service-agent-settings-modal]');

        setFormModalKeyboardActive(modal, active);
    }

    function serviceAgentScoreModalOpen(chat) {
        var modal = chat.querySelector('[data-service-agent-score-modal]');

        return !!(modal && !modal.hidden);
    }

    function setServiceAgentScoreKeyboardActive(chat, active) {
        var modal = chat.querySelector('[data-service-agent-score-modal]');

        if (!modal) {
            return;
        }

        modal.classList.toggle('is-keyboard-active', !!active);
        updateServiceAgentScoreViewport();
    }

    function setServiceAgentScoreModal(chat, open) {
        var modal = chat.querySelector('[data-service-agent-score-modal]');

        if (!modal) {
            return;
        }

        if (open) {
            bindServiceAgentScoreViewport();
        } else {
            modal.classList.remove('is-keyboard-active');
        }
        setAnimatedHidden(modal, open, 'service-agent-score-modal-open');
        if (open) {
            window.setTimeout(function () {
                var amountField = modal.querySelector('[data-service-agent-score-amount]');

                updateServiceAgentScoreViewport();
                if (amountField) {
                    amountField.focus();
                    amountField.select();
                    scrollFormModalInputIntoView(amountField);
                }
            }, 30);
        }
    }

    function updateServiceAgentScoreValue(chat, score) {
        var modal = chat.querySelector('[data-service-agent-score-modal]');
        var target;
        var normalized = customerServiceText(score).replace(/[^\d]/g, '');

        if (normalized === '') {
            return;
        }

        chat.setAttribute('data-service-agent-score-current', normalized);
        if (modal) {
            target = modal.querySelector('[data-service-agent-score-current]');
            if (target) {
                target.textContent = normalized;
            }
        }
    }

    function serviceAgentScoreFromMessage(message) {
        var match = customerServiceText(message).match(/当前积分\s*([0-9]+)/);

        return match ? match[1] : '';
    }

    function serviceAgentScoreFromPayload(data) {
        if (data && data.score_result && data.score_result.score !== undefined) {
            return customerServiceText(data.score_result.score).replace(/[^\d]/g, '');
        }

        if (data && data.session && data.session.score !== undefined) {
            return customerServiceText(data.session.score).replace(/[^\d]/g, '');
        }

        return '';
    }

    function isAppleTouchDevice() {
        var platform = window.navigator ? String(window.navigator.platform || '') : '';
        var userAgent = window.navigator ? String(window.navigator.userAgent || '') : '';
        var maxTouchPoints = window.navigator ? (window.navigator.maxTouchPoints || 0) : 0;

        return /iPad|iPhone|iPod/i.test(userAgent)
            || (platform === 'MacIntel' && maxTouchPoints > 1);
    }

    function normalizeServiceAgentScoreAmount(value) {
        return customerServiceText(value)
            .trim()
            .replace(/[\u2212\u2013\u2014\uFF0D]/g, '-')
            .replace(/\s+/g, '');
    }

    function prepareServiceAgentScoreAmountField(field) {
        if (!field || field.getAttribute('data-service-agent-score-input-ready') === '1') {
            return;
        }

        field.setAttribute('data-service-agent-score-input-ready', '1');
        if (!isAppleTouchDevice()) {
            return;
        }

        field.setAttribute('type', 'text');
        field.setAttribute('inputmode', 'text');
        field.setAttribute('autocomplete', 'off');
        field.setAttribute('autocorrect', 'off');
        field.setAttribute('spellcheck', 'false');
    }

    function renderServiceAgentScoreModal(chat, session) {
        var role = chat.getAttribute('data-customer-service-role') || 'member';
        var modal = chat.querySelector('[data-service-agent-score-modal]');
        var scoreButton = chat.querySelector('[data-service-agent-score-open]');
        var account;
        var avatarTarget;
        var accountTarget;
        var scoreTarget;

        if (role !== 'agent') {
            return;
        }

        if (session) {
            account = customerServiceText(session.username) || '会员';
            chat.setAttribute('data-service-agent-score-account', account);
            if (session.score !== undefined) {
                updateServiceAgentScoreValue(chat, session.score);
            }
        } else {
            account = '未选择';
            chat.setAttribute('data-service-agent-score-account', '');
        }

        if (scoreButton) {
            scoreButton.hidden = !session;
            scoreButton.disabled = !session;
        }

        if (!modal || serviceAgentScoreModalOpen(chat)) {
            return;
        }

        accountTarget = modal.querySelector('[data-service-agent-score-account]');
        avatarTarget = modal.querySelector('[data-service-agent-score-avatar]');
        scoreTarget = modal.querySelector('[data-service-agent-score-current]');
        if (avatarTarget) {
            renderCustomerServiceAvatarIcon(avatarTarget, account ? account + '头像' : '会员头像');
        }
        if (accountTarget) {
            accountTarget.textContent = account;
        }
        if (scoreTarget && chat.getAttribute('data-service-agent-score-current') !== null) {
            scoreTarget.textContent = chat.getAttribute('data-service-agent-score-current') || '0';
        }
    }

    function renderServiceAgentNicknameOptions(form, agent, displayName) {
        var field;
        var listId;
        var datalist;
        var picker;
        var menu;
        var toggle;
        var names = [];
        var seen = {};
        var datalistFragment;
        var menuFragment;
        var activeName;
        var menuWasOpen;

        if (!form) {
            return;
        }

        field = form.querySelector('[data-service-agent-nickname-input], [name="display_name"]');
        listId = field ? customerServiceText(field.getAttribute('list')) : '';
        datalist = listId ? document.getElementById(listId) : form.querySelector('datalist');
        picker = field && field.closest ? field.closest('[data-service-agent-nickname-picker]') : null;
        menu = picker ? picker.querySelector('[data-service-agent-nickname-menu]') : null;
        toggle = picker ? picker.querySelector('[data-service-agent-nickname-toggle]') : null;
        if (!field || (!datalist && !menu)) {
            return;
        }

        function addName(name) {
            name = customerServiceText(name);
            if (!name || seen[name]) {
                return;
            }

            seen[name] = true;
            names.push(name);
        }

        addName(displayName);
        addName(agent && agent.username);
        if (agent && agent.nickname_options && agent.nickname_options.length) {
            Array.prototype.forEach.call(agent.nickname_options, addName);
        }

        if (datalist) {
            datalistFragment = document.createDocumentFragment();
            Array.prototype.forEach.call(names, function (name) {
                var option = document.createElement('option');

                option.value = name;
                datalistFragment.appendChild(option);
            });
            replaceChildrenFast(datalist, datalistFragment);
        }

        if (menu) {
            menuWasOpen = !menu.hidden;
            activeName = customerServiceText(field.value) || customerServiceText(displayName);
            menuFragment = document.createDocumentFragment();
            Array.prototype.forEach.call(names, function (name) {
                var row = document.createElement('div');
                var option = document.createElement('button');
                var deleteButton = document.createElement('button');
                var isActive = name === activeName;

                row.className = isActive ? 'service-agent-nickname-row is-active' : 'service-agent-nickname-row';
                row.setAttribute('role', 'option');
                row.setAttribute('data-service-agent-nickname-row', name);
                row.setAttribute('aria-selected', isActive ? 'true' : 'false');

                option.type = 'button';
                option.textContent = name;
                option.setAttribute('data-service-agent-nickname-option', name);

                deleteButton.type = 'button';
                deleteButton.textContent = 'x';
                deleteButton.setAttribute('data-service-agent-nickname-delete', name);
                deleteButton.setAttribute('aria-label', '删除昵称 ' + name);

                row.appendChild(option);
                row.appendChild(deleteButton);
                menuFragment.appendChild(row);
            });
            replaceChildrenFast(menu, menuFragment);
            menu.hidden = !menuWasOpen || names.length === 0;
            if (picker) {
                picker.classList.toggle('is-open', !menu.hidden);
            }
        }

        if (toggle) {
            toggle.disabled = names.length === 0;
            toggle.setAttribute('aria-expanded', menu && !menu.hidden ? 'true' : 'false');
        }
    }

    function syncServiceAgentNicknameMenuActive(picker) {
        var field;
        var value;

        if (!picker) {
            return;
        }

        field = picker.querySelector('[data-service-agent-nickname-input], [name="display_name"]');
        value = field ? customerServiceText(field.value) : '';
        Array.prototype.forEach.call(picker.querySelectorAll('[data-service-agent-nickname-row]'), function (row) {
            var selected = customerServiceText(row.getAttribute('data-service-agent-nickname-row')) === value;

            row.classList.toggle('is-active', selected);
            row.setAttribute('aria-selected', selected ? 'true' : 'false');
        });
    }

    function setServiceAgentNicknameMenu(picker, isOpen) {
        var menu;
        var toggle;

        if (!picker) {
            return;
        }

        menu = picker.querySelector('[data-service-agent-nickname-menu]');
        toggle = picker.querySelector('[data-service-agent-nickname-toggle]');
        if (!menu) {
            return;
        }

        if (isOpen && !menu.querySelector('[data-service-agent-nickname-option]')) {
            isOpen = false;
        }

        picker.classList.toggle('is-open', !!isOpen);
        menu.hidden = !isOpen;
        if (toggle) {
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        }

        if (isOpen) {
            syncServiceAgentNicknameMenuActive(picker);
        }
    }

    function closeServiceAgentNicknameMenus(exceptPicker) {
        Array.prototype.forEach.call(document.querySelectorAll('[data-service-agent-nickname-picker]'), function (picker) {
            if (picker !== exceptPicker) {
                setServiceAgentNicknameMenu(picker, false);
            }
        });
    }

    function chooseServiceAgentNicknameOption(option) {
        var picker;
        var field;
        var value;

        if (!option || !option.closest) {
            return;
        }

        picker = option.closest('[data-service-agent-nickname-picker]');
        field = picker ? picker.querySelector('[data-service-agent-nickname-input], [name="display_name"]') : null;
        value = customerServiceText(option.getAttribute('data-service-agent-nickname-option'));
        if (!field || !value) {
            return;
        }

        field.value = value;
        syncServiceAgentNicknameMenuActive(picker);
        setServiceAgentNicknameMenu(picker, false);
    }

    function deleteServiceAgentNicknameOption(button) {
        var picker;
        var chat;
        var value;
        var action;

        if (!button || !button.closest) {
            return;
        }

        picker = button.closest('[data-service-agent-nickname-picker]');
        chat = picker ? picker.closest('[data-customer-service]') : null;
        value = customerServiceText(button.getAttribute('data-service-agent-nickname-delete'));
        action = chat ? customerServiceText(chat.getAttribute('data-nickname-delete-action')) : '';
        if (!chat || !value || !action) {
            return;
        }

        button.disabled = true;
        customerServicePost(chat, action, {
            nickname: value,
            session_id: customerServiceText(chat.getAttribute('data-session-id')) || '0',
            status: customerServiceText(chat.getAttribute('data-status')) || 'all'
        }).then(function (payload) {
            toast(payload.message || '昵称候选已删除。', 'success');
            applyCustomerServicePayload(chat, payload.data || {});
        }).catch(function (error) {
            toast(error.message, 'error');
        }).finally(function () {
            button.disabled = false;
        });
    }

    function renderServiceAgentSettings(chat, agent) {
        var role = chat.getAttribute('data-customer-service-role') || 'member';
        var title = chat.querySelector('[data-service-agent-title]');
        var form = chat.querySelector('[data-service-agent-settings-form]');
        var displayName;
        var field;
        var nextState;

        if (role !== 'agent' || !agent) {
            return;
        }

        displayName = customerServiceText(agent.display_name) || customerServiceText(agent.username) || '客服';
        nextState = [
            displayName,
            customerServiceText(agent.service_hours),
            customerServiceChecksum(agent.welcome_text),
            customerServiceChecksum(agent.auto_reply_text),
            customerServiceChecksum(agent.activity_notice),
            customerServiceText(agent.activity_notice_enabled),
            agent.nickname_options && agent.nickname_options.length ? agent.nickname_options.join(',') : ''
        ].join('|');
        if (chat.getAttribute('data-service-agent-settings-render-state') === nextState) {
            return;
        }
        chat.setAttribute('data-service-agent-settings-render-state', nextState);

        if (title) {
            title.textContent = displayName;
        }

        if (!form) {
            return;
        }

        if (!serviceAgentSettingsModalOpen(chat)) {
            field = form.querySelector('[data-service-agent-nickname-input], [name="display_name"]');
            if (field) {
                field.value = displayName;
            }
            field = form.querySelector('[name="service_hours"]');
            if (field) {
                field.value = customerServiceText(agent.service_hours) || '09:00-23:00';
            }
            field = form.querySelector('[name="welcome_text"]');
            if (field) {
                field.value = customerServiceText(agent.welcome_text);
            }
            field = form.querySelector('[name="auto_reply_text"]');
            if (field) {
                field.value = customerServiceText(agent.auto_reply_text);
            }
            field = form.querySelector('[name="activity_notice"]');
            if (field) {
                field.value = customerServiceText(agent.activity_notice);
            }
            field = form.querySelector('[name="activity_notice_enabled"][type="checkbox"]');
            if (field) {
                field.checked = agent.activity_notice_enabled === true
                    || agent.activity_notice_enabled === 1
                    || customerServiceText(agent.activity_notice_enabled) === '1';
            }
        }

        renderServiceAgentNicknameOptions(form, agent, displayName);
    }

    function renderCustomerServiceProfile(chat, profile) {
        var role = chat.getAttribute('data-customer-service-role') || 'member';
        var notice = chat.querySelector('[data-customer-service-activity-notice]');
        var content = notice
            ? (notice.querySelector('[data-customer-service-activity-notice-text]') || notice.querySelector('span'))
            : null;
        var enabled = true;
        var text;
        var nextState;

        if (role !== 'member' || !notice || !profile) {
            return;
        }

        if (profile.activity_notice_enabled !== undefined) {
            enabled = profile.activity_notice_enabled === true
                || profile.activity_notice_enabled === 1
                || customerServiceText(profile.activity_notice_enabled) === '1';
        }
        text = customerServiceText(profile.activity_notice).trim();
        nextState = [enabled ? '1' : '0', customerServiceChecksum(text)].join('|');
        if (chat.getAttribute('data-customer-service-profile-render-state') === nextState) {
            return;
        }
        chat.setAttribute('data-customer-service-profile-render-state', nextState);
        notice.hidden = !enabled || text === '';
        if (content) {
            content.textContent = text;
        }
    }

    function renderServiceAgentQueueActions(chat, data) {
        var role = chat.getAttribute('data-customer-service-role') || 'member';
        var deleteButton = chat.querySelector('[data-service-agent-delete-current]');
        var hasSession = !!(data && data.session && data.session.id);

        if (role !== 'agent' || !deleteButton) {
            return;
        }

        deleteButton.disabled = !hasSession;
    }

    function selectedServiceAgentSessionIds(chat) {
        var ids = [];

        Array.prototype.forEach.call(chat.querySelectorAll('[data-service-agent-session-select]:checked'), function (input) {
            var id = parseInt(input.value || input.getAttribute('value') || '0', 10);

            if (id > 0 && ids.indexOf(String(id)) === -1) {
                ids.push(String(id));
            }
        });

        return ids;
    }

    function syncServiceAgentQueueSelection(chat) {
        var checkboxes = chat.querySelectorAll('[data-service-agent-session-select]');
        var selectedIds = selectedServiceAgentSessionIds(chat);
        var selectAll = chat.querySelector('[data-service-agent-select-all]');
        var deleteButton = chat.querySelector('[data-service-agent-batch-delete]');
        var total = checkboxes.length;
        var checked = selectedIds.length;

        if (selectAll) {
            selectAll.checked = total > 0 && checked === total;
            selectAll.indeterminate = checked > 0 && checked < total;
            selectAll.disabled = total === 0;
        }

        if (deleteButton) {
            deleteButton.disabled = false;
            deleteButton.classList.toggle('is-disabled', checked === 0);
            deleteButton.setAttribute('aria-disabled', checked === 0 ? 'true' : 'false');
            deleteButton.setAttribute('aria-label', checked > 0 ? '删除已选' + checked + '个会话' : '删除已选会话');
            deleteButton.setAttribute('title', checked > 0 ? '删除已选' + checked + '个会话' : '删除已选会话');
        }
    }

    function deleteServiceAgentQueueSessions(chat, sessionIds, trigger) {
        var deleteAction = chat.getAttribute('data-delete-action') || '';
        var ids = [];
        var status = chat.getAttribute('data-status') || 'all';
        var lastPayload = null;
        var chain = Promise.resolve();
        var buttons;
        var confirmMessage;
        var currentActiveSessionId = chat.getAttribute('data-session-id') || '0';
        var deletesActiveSession = false;

        Array.prototype.forEach.call(sessionIds || [], function (sessionId) {
            var id = parseInt(sessionId || 0, 10);

            if (id > 0 && ids.indexOf(String(id)) === -1) {
                ids.push(String(id));
            }
        });

        if (!ids.length) {
            toast('请先选择要删除的会话。', 'error');
            syncServiceAgentQueueSelection(chat);
            return;
        }

        if (!deleteAction) {
            toast('删除接口不可用，请刷新页面后重试。', 'error');
            return;
        }

        deletesActiveSession = currentActiveSessionId !== '0' && ids.indexOf(currentActiveSessionId) !== -1;
        confirmMessage = ids.length > 1 ? '确认删除已选会话吗？会员再次留言后会重新出现在列表中。' : '确认删除该会员会话吗？会员再次留言后会重新出现在列表中。';
        appConfirm(confirmMessage, '删除会话', '确定', '取消').then(function (confirmed) {
            if (!confirmed) {
                return;
            }

            buttons = chat.querySelectorAll('[data-service-agent-session-delete], [data-service-agent-batch-delete]');
            Array.prototype.forEach.call(buttons, function (button) {
                button.disabled = true;
            });
            if (trigger) {
                trigger.disabled = true;
            }

            ids.forEach(function (sessionId) {
                chain = chain.then(function () {
                    return customerServicePost(chat, deleteAction, {
                        session_id: sessionId,
                        status: status
                    }).then(function (payload) {
                        lastPayload = payload;
                    });
                });
            });

            chain.then(function () {
                toast(ids.length > 1 ? '已删除已选会话。' : ((lastPayload && lastPayload.message) || '会员已从会话队列删除。'), 'success');
                applyCustomerServicePayload(chat, lastPayload && lastPayload.data ? lastPayload.data : {}, {
                    forceScroll: true,
                    preserveActiveSession: !deletesActiveSession && (chat.getAttribute('data-customer-service-role') || '') === 'agent'
                });
            }).catch(function (error) {
                toast(error.message, 'error');
            }).finally(function () {
                Array.prototype.forEach.call(buttons, function (button) {
                    button.disabled = false;
                });
                syncServiceAgentQueueSelection(chat);
            });
        });
    }

    function serviceAgentBlockLimitLabel(limit) {
        var labels = {
            permanent: '永久屏蔽',
            '1h': '屏蔽1小时',
            '24h': '屏蔽24小时',
            '7d': '屏蔽7天',
            '30d': '屏蔽30天'
        };

        return labels[customerServiceText(limit)] || '限时屏蔽';
    }

    function serviceAgentDateTimeStamp(value) {
        var text = customerServiceText(value);
        var match;

        if (!text) {
            return 0;
        }

        match = /^(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2})(?::(\d{2}))?/.exec(text);
        if (!match) {
            return 0;
        }

        return new Date(
            parseInt(match[1], 10),
            parseInt(match[2], 10) - 1,
            parseInt(match[3], 10),
            parseInt(match[4], 10),
            parseInt(match[5], 10),
            parseInt(match[6] || '0', 10)
        ).getTime();
    }

    function serviceAgentBlockSessionLabel(select, session, isBlocked) {
        var storedLabel;
        var blockedAt;
        var blockedUntil;
        var duration;
        var durationOptions = [
            { seconds: 3600, label: '屏蔽1小时' },
            { seconds: 86400, label: '屏蔽24小时' },
            { seconds: 604800, label: '屏蔽7天' },
            { seconds: 2592000, label: '屏蔽30天' }
        ];
        var index;

        if (!isBlocked) {
            return '屏蔽会话';
        }

        storedLabel = customerServiceText(select.getAttribute('data-block-label'));
        if (storedLabel) {
            return storedLabel;
        }

        if (!customerServiceText(session && session.blocked_until)) {
            return '永久屏蔽';
        }

        blockedAt = serviceAgentDateTimeStamp(session && session.blocked_at);
        blockedUntil = serviceAgentDateTimeStamp(session && session.blocked_until);
        if (blockedAt > 0 && blockedUntil > blockedAt) {
            duration = Math.round((blockedUntil - blockedAt) / 1000);
            for (index = 0; index < durationOptions.length; index += 1) {
                if (Math.abs(duration - durationOptions[index].seconds) <= 300) {
                    return durationOptions[index].label;
                }
            }
        }

        return '限时屏蔽';
    }

    function renderServiceAgentChatBlockControls(chat, session) {
        var role = chat.getAttribute('data-customer-service-role') || 'member';
        var controls = chat.querySelector('[data-service-agent-chat-block-controls]');
        var select;
        var placeholder;
        var options;
        var sessionId;
        var isBlocked;
        var previousSessionId;

        if (role !== 'agent' || !controls) {
            return;
        }

        select = controls.querySelector('[data-service-agent-block-limit]');
        sessionId = session && session.id ? customerServiceText(session.id) : '0';
        isBlocked = !!(session && session.blocked);
        controls.hidden = sessionId === '0';

        if (select) {
            previousSessionId = select.getAttribute('data-session-id') || '0';
            select.setAttribute('data-session-id', sessionId);
            select.setAttribute('data-blocked', isBlocked ? '1' : '0');
            select.disabled = sessionId === '0';
            if (previousSessionId !== sessionId || !isBlocked) {
                select.removeAttribute('data-block-label');
            }
            if (previousSessionId !== sessionId || select.value !== '') {
                select.value = '';
            }

            placeholder = select.querySelector('[data-service-agent-block-placeholder]');
            if (placeholder) {
                placeholder.textContent = serviceAgentBlockSessionLabel(select, session, isBlocked);
                placeholder.disabled = true;
            }

            options = select.querySelectorAll('[data-service-agent-block-mode]');
            Array.prototype.forEach.call(options, function (option) {
                var mode = option.getAttribute('data-service-agent-block-mode') || 'block';
                var disabled = sessionId === '0' || (isBlocked ? mode !== 'unblock' : mode === 'unblock');

                option.disabled = disabled;
                option.hidden = disabled;
            });
        }
    }

    function renderCustomerServiceAvatarIcon(target, label) {
        if (!target) {
            return;
        }

        target.setAttribute('aria-label', label || '会员头像');
        if (!target.querySelector('.front-icon-circle-user')) {
            target.innerHTML = '<i class="front-fa-icon front-icon-circle-user" aria-hidden="true"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm0 4.2a3.2 3.2 0 1 1 0 6.4 3.2 3.2 0 0 1 0-6.4Zm0 13.8a7.95 7.95 0 0 1-5.6-2.28c.8-2.2 2.86-3.52 5.6-3.52s4.8 1.32 5.6 3.52A7.95 7.95 0 0 1 12 20Z"/></svg></i>';
        }
    }

    function renderCustomerServiceActiveSession(chat, data) {
        var role = chat.getAttribute('data-customer-service-role') || 'member';
        var session = data && data.session ? data.session : null;
        var name = session ? (customerServiceText(session.username) || '会员') : '未选择会话';
        var status = session ? customerServiceText(session.status) : '';
        var nameTarget = chat.querySelector('[data-customer-service-active-name]');
        var avatarTarget = chat.querySelector('[data-customer-service-active-avatar]');
        var onlineTarget = chat.querySelector('[data-customer-service-active-online]');
        var statusTarget = chat.querySelector('[data-customer-service-active-status]');
        var agentTarget = chat.querySelector('[data-customer-service-active-agent]');
        var form = chat.querySelector('[data-customer-service-form]');
        var locked = chat.querySelector('[data-service-agent-locked]');
        var activeLabel = chat.querySelector('[data-service-agent-active-label]');
        var canReply;
        var isAgentOnline;

        if (role !== 'admin' && role !== 'agent') {
            return;
        }

        chat.setAttribute('data-has-session', session ? '1' : '0');

        if (nameTarget) {
            nameTarget.textContent = name;
        }

        if (avatarTarget) {
            renderCustomerServiceAvatarIcon(avatarTarget, name ? name + '头像' : '会员头像');
        }

        if (onlineTarget) {
            onlineTarget.textContent = session ? (customerServiceText(session.member_online_label) || '离线') : '离线';
            onlineTarget.setAttribute('data-status-type', session ? (customerServiceText(session.member_online_type) || 'offline') : 'offline');
        }

        if (statusTarget && role !== 'agent') {
            statusTarget.textContent = session ? (customerServiceText(session.status_label) || '待接待') : '等待选择';
        }

        if (agentTarget) {
            agentTarget.textContent = '接待：' + (session ? (customerServiceText(session.assigned_agent_name) || '未接待') : '未接待');
        }

        if (activeLabel) {
            activeLabel.textContent = session ? name : '未选择';
        }

        if (data && data.can_reply !== undefined) {
            canReply = !!(session && (status !== 'closed' || session.blocked) && data.can_reply);
            if (form) {
                form.hidden = !canReply;
            }
            if (locked && role === 'agent') {
                isAgentOnline = !!data.agent_online;
                locked.hidden = canReply;
                if (!canReply) {
                    locked.textContent = session
                        ? (isAgentOnline ? '当前账号无回复权限，或该会话不可操作。' : '当前休息状态...')
                        : '请先在会话列表选择会员。';
                }
            }
        }

        if (role === 'agent') {
            renderServiceAgentScoreModal(chat, session);
            renderServiceAgentChatBlockControls(chat, session);
            setServiceAgentView(chat, chat.getAttribute('data-agent-active-view') || 'queue');
        }
    }

    function setCustomerServiceLocalStatus(chat, type) {
        if (type) {
            chat.setAttribute('data-customer-service-local-status', type);
        } else {
            chat.removeAttribute('data-customer-service-local-status');
        }

        renderCustomerServiceStatus(chat, type ? customerServiceStatusPayload(type) : null);
    }

    function renderCustomerServiceUnread(count) {
        var badges = document.querySelectorAll('[data-customer-service-unread-badge]');
        var unread = Math.max(0, parseInt(count || 0, 10));
        var label = unread > 99 ? '99+' : String(unread);

        Array.prototype.forEach.call(badges, function (badge) {
            badge.textContent = label;
            badge.setAttribute('aria-label', '未阅读信息' + label + '条');
            badge.hidden = unread <= 0;
        });
    }

    function shouldBlurCustomerServiceInputAfterSend() {
        return !!(window.matchMedia && window.matchMedia('(hover: none) and (pointer: coarse), (max-width: 720px)').matches);
    }

    function resizeCustomerServiceTextarea(field) {
        var nextHeight;

        if (!field || field.tagName.toLowerCase() !== 'textarea') {
            return;
        }

        field.style.height = 'auto';
        nextHeight = field.scrollHeight;
        field.style.height = nextHeight + 'px';
        field.style.overflowY = 'hidden';
    }

    function requestCustomerServiceSystemNoticePermission() {
        var result;

        if (!('Notification' in window) || customerServiceNoticePermissionRequested) {
            return;
        }

        if (Notification.permission !== 'default') {
            return;
        }

        customerServiceNoticePermissionRequested = true;
        try {
            result = Notification.requestPermission();
            if (result && result.catch) {
                result.catch(function () {
                });
            }
        } catch (error) {
            try {
                Notification.requestPermission(function () {
                });
            } catch (innerError) {
            }
        }
    }

    function showCustomerServiceSystemNotice(title, body, tag) {
        var notice;

        if (!document.hidden || !('Notification' in window) || Notification.permission !== 'granted') {
            return;
        }

        try {
            notice = new Notification(title, {
                body: body,
                tag: tag,
                renotify: true,
                silent: false
            });
            notice.onclick = function () {
                window.focus();
                notice.close();
            };
            window.setTimeout(function () {
                notice.close();
            }, 6000);
        } catch (error) {
        }
    }

    function vibrateCustomerServiceNotice() {
        var nowTime;

        if (!navigator.vibrate) {
            return;
        }

        nowTime = Date.now ? Date.now() : new Date().getTime();
        if (nowTime - customerServiceNoticeAudioLastVibrateAt < 900) {
            return;
        }
        customerServiceNoticeAudioLastVibrateAt = nowTime;

        try {
            navigator.vibrate([90, 40, 90]);
        } catch (error) {
        }
    }

    function scheduleCustomerServiceNoticeAudioRetry() {
        if (customerServiceNoticeAudioReady || customerServiceNoticeAudioRetryTimer || customerServiceNoticeAudioRetryAttempts >= 3) {
            return;
        }

        customerServiceNoticeAudioRetryAttempts += 1;
        customerServiceNoticeAudioRetryTimer = window.setTimeout(function () {
            customerServiceNoticeAudioRetryTimer = 0;
            if (customerServiceNoticeAudioReady || !customerServiceNoticeAudioPending || document.hidden) {
                return;
            }
            unlockCustomerServiceNoticeAudio();
        }, 900);
    }

    function unlockCustomerServiceNoticeAudio() {
        var AudioContextClass = window.AudioContext || window.webkitAudioContext;
        var gain;
        var oscillator;
        var now;
        var pendingBeforeUnlock = customerServiceNoticeAudioPending;
        var finishUnlock = function () {
            customerServiceNoticeAudioReady = true;
            customerServiceNoticeAudioBound = false;
            if (customerServiceNoticeAudioRetryTimer) {
                window.clearTimeout(customerServiceNoticeAudioRetryTimer);
                customerServiceNoticeAudioRetryTimer = 0;
            }
            customerServiceNoticeAudioRetryAttempts = 0;
            document.removeEventListener('pointerdown', unlockCustomerServiceNoticeAudio, true);
            document.removeEventListener('pointerup', unlockCustomerServiceNoticeAudio, true);
            document.removeEventListener('touchstart', unlockCustomerServiceNoticeAudio, true);
            document.removeEventListener('touchend', unlockCustomerServiceNoticeAudio, true);
            document.removeEventListener('mousedown', unlockCustomerServiceNoticeAudio, true);
            document.removeEventListener('mouseup', unlockCustomerServiceNoticeAudio, true);
            document.removeEventListener('click', unlockCustomerServiceNoticeAudio, true);
            document.removeEventListener('keydown', unlockCustomerServiceNoticeAudio, true);
            document.removeEventListener('visibilitychange', scheduleCustomerServiceNoticeAudioRetry, true);
            window.removeEventListener('focus', scheduleCustomerServiceNoticeAudioRetry, true);
            window.removeEventListener('pageshow', scheduleCustomerServiceNoticeAudioRetry, true);
            requestCustomerServiceSystemNoticePermission();
            if (customerServiceNoticeAudioPending) {
                customerServiceNoticeAudioPending = false;
                playCustomerServiceNoticeSound();
            }
        };

        requestCustomerServiceSystemNoticePermission();
        if (!customerServiceNoticeAudioContext && AudioContextClass) {
            try {
                customerServiceNoticeAudioContext = new AudioContextClass();
                if (customerServiceNoticeAudioContext.resume) {
                    customerServiceNoticeAudioContext.resume().then(finishUnlock).catch(function () {
                        customerServiceNoticeAudioReady = false;
                        customerServiceNoticeAudioPending = pendingBeforeUnlock;
                        customerServiceNoticeAudioBound = false;
                        bindCustomerServiceNoticeAudioUnlock();
                        if (pendingBeforeUnlock) {
                            scheduleCustomerServiceNoticeAudioRetry();
                        }
                    });
                }
                gain = customerServiceNoticeAudioContext.createGain();
                oscillator = customerServiceNoticeAudioContext.createOscillator();
                now = customerServiceNoticeAudioContext.currentTime;
                gain.gain.setValueAtTime(0.0001, now);
                oscillator.connect(gain);
                gain.connect(customerServiceNoticeAudioContext.destination);
                oscillator.start(now);
                oscillator.stop(now + 0.03);
            } catch (error) {
                customerServiceNoticeAudioContext = null;
                finishUnlock();
            }
        } else {
            finishUnlock();
        }
    }

    function bindCustomerServiceNoticeAudioUnlock() {
        if (customerServiceNoticeAudioReady || customerServiceNoticeAudioBound) {
            return;
        }

        customerServiceNoticeAudioBound = true;
        document.addEventListener('pointerdown', unlockCustomerServiceNoticeAudio, true);
        document.addEventListener('pointerup', unlockCustomerServiceNoticeAudio, true);
        document.addEventListener('touchstart', unlockCustomerServiceNoticeAudio, true);
        document.addEventListener('touchend', unlockCustomerServiceNoticeAudio, true);
        document.addEventListener('mousedown', unlockCustomerServiceNoticeAudio, true);
        document.addEventListener('mouseup', unlockCustomerServiceNoticeAudio, true);
        document.addEventListener('click', unlockCustomerServiceNoticeAudio, true);
        document.addEventListener('keydown', unlockCustomerServiceNoticeAudio, true);
        document.addEventListener('visibilitychange', scheduleCustomerServiceNoticeAudioRetry, true);
        window.addEventListener('focus', scheduleCustomerServiceNoticeAudioRetry, true);
        window.addEventListener('pageshow', scheduleCustomerServiceNoticeAudioRetry, true);
    }

    function playCustomerServiceNoticeSound() {
        var AudioContextClass;
        var context;
        var nowTime = Date.now ? Date.now() : new Date().getTime();

        if (!customerServiceNoticeAudioReady) {
            customerServiceNoticeAudioPending = true;
            bindCustomerServiceNoticeAudioUnlock();
            vibrateCustomerServiceNotice();
            scheduleCustomerServiceNoticeAudioRetry();
            return;
        }

        if (nowTime - customerServiceNoticeAudioLastPlayedAt < 450) {
            return;
        }
        customerServiceNoticeAudioLastPlayedAt = nowTime;

        AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (!AudioContextClass && !customerServiceNoticeAudioContext) {
            vibrateCustomerServiceNotice();
            return;
        }

        function playTone(targetContext) {
            var now;

            if (!targetContext) {
                return;
            }

            now = targetContext.currentTime;

            function playNote(frequency, delay, duration, volume) {
                var gain = targetContext.createGain();
                var tone = targetContext.createOscillator();
                var bell = targetContext.createOscillator();
                var startAt = now + delay;
                var endAt = startAt + duration;

                tone.type = 'sine';
                bell.type = 'sine';
                tone.frequency.setValueAtTime(frequency, startAt);
                bell.frequency.setValueAtTime(frequency * 2.01, startAt);
                gain.gain.setValueAtTime(0.0001, startAt);
                gain.gain.exponentialRampToValueAtTime(volume, startAt + 0.008);
                gain.gain.exponentialRampToValueAtTime(volume * 0.34, startAt + duration * 0.58);
                gain.gain.exponentialRampToValueAtTime(0.0001, endAt);
                tone.connect(gain);
                bell.connect(gain);
                gain.connect(targetContext.destination);
                tone.start(startAt);
                bell.start(startAt);
                tone.stop(endAt + 0.02);
                bell.stop(endAt + 0.02);
                bell.onended = function () {
                    tone.disconnect();
                    bell.disconnect();
                    gain.disconnect();
                };
            }

            playNote(880, 0, 0.18, 0.105);
            playNote(1319, 0.14, 0.18, 0.095);
            playNote(1760, 0.28, 0.24, 0.085);
        }

        try {
            if (!customerServiceNoticeAudioContext) {
                customerServiceNoticeAudioContext = new AudioContextClass();
            }
            context = customerServiceNoticeAudioContext;
            if (context.state === 'suspended' && context.resume) {
                context.resume().then(function () {
                    playTone(context);
                }).catch(function () {
                    customerServiceNoticeAudioReady = false;
                    customerServiceNoticeAudioPending = true;
                    bindCustomerServiceNoticeAudioUnlock();
                    vibrateCustomerServiceNotice();
                    scheduleCustomerServiceNoticeAudioRetry();
                });
                return;
            }
            playTone(context);
        } catch (error) {
            customerServiceNoticeAudioReady = false;
            customerServiceNoticeAudioPending = true;
            bindCustomerServiceNoticeAudioUnlock();
            vibrateCustomerServiceNotice();
            scheduleCustomerServiceNoticeAudioRetry();
        }
    }

    function initCustomerServiceUnreadPoll(root) {
        var scope = root || document;
        var navs = scope.querySelectorAll('[data-customer-service-unread-poll]');
        var isAgentConsolePage = !!document.querySelector('[data-customer-service][data-customer-service-role="agent"]');

        Array.prototype.forEach.call(navs, function (nav) {
            var action = nav.getAttribute('data-poll-action') || '';
            var enabled = nav.getAttribute('data-enabled') === '1';
            var lastMessageId = parseInt(nav.getAttribute('data-last-message-id') || '0', 10);
            var pollBusy = false;
            var isAgentNotice = action.indexOf('.agent.') !== -1 || action.indexOf('.admin.') !== -1;
            var isAdminNavNotice = !!(document.body && document.body.classList && document.body.classList.contains('admin-body'));
            var noticeBody = isAgentNotice ? '会员有新消息，请及时处理。' : '您有新的客服消息，请及时查看。';
            var pollInterval = isAgentNotice ? 20000 : 30000;
            var initialPollDelay = isAdminNavNotice ? 5000 : 1000;
            var minPollSpacing = isAgentNotice ? 10000 : 15000;
            var lastPollStartedAt = 0;
            var initializedAt = Date.now ? Date.now() : new Date().getTime();
            var adminBaselineReady = !isAdminNavNotice || lastMessageId > 0;
            var failureCount = 0;
            var retryAfter = 0;
            var timer;

            if (nav.getAttribute('data-customer-service-unread-ready') === '1') {
                return;
            }

            nav.setAttribute('data-customer-service-unread-ready', '1');

            if (isAgentConsolePage && nav.closest && nav.closest('.bottom-float-nav')) {
                return;
            }

            if (!enabled || !action) {
                return;
            }

            bindCustomerServiceNoticeAudioUnlock();

            function pollUnread(force) {
                var now = Date.now ? Date.now() : new Date().getTime();

                force = !!force;

                if (pollBusy || now < retryAfter || (!force && lastPollStartedAt > 0 && now - lastPollStartedAt < minPollSpacing)) {
                    return;
                }

                lastPollStartedAt = now;
                pollBusy = true;
                customerServicePost(nav, action, {}).then(function (payload) {
                    var data = payload.data || {};
                    var unread = parseInt(data.unread_count || 0, 10);
                    var latestMessageId = parseInt(data.latest_message_id || 0, 10);

                    renderCustomerServiceUnread(unread);

                    if (!adminBaselineReady) {
                        if (latestMessageId > lastMessageId) {
                            lastMessageId = latestMessageId;
                            nav.setAttribute('data-last-message-id', String(lastMessageId));
                        }
                        adminBaselineReady = true;
                        return;
                    }

                    if (unread > 0 && latestMessageId > lastMessageId) {
                        playCustomerServiceNoticeSound();
                        showCustomerServiceSystemNotice(
                            '在线客服新消息',
                            noticeBody,
                            'customer-service-unread-' + latestMessageId
                        );
                    }

                    if (latestMessageId > lastMessageId) {
                        lastMessageId = latestMessageId;
                        nav.setAttribute('data-last-message-id', String(lastMessageId));
                    }
                    failureCount = 0;
                    retryAfter = 0;
                }).catch(function () {
                    failureCount += 1;
                    retryAfter = now + (failureCount > 1 ? 30000 : 15000);
                }).finally(function () {
                    pollBusy = false;
                });
            }

            function shouldDelayAdminInitialPoll() {
                var now = Date.now ? Date.now() : new Date().getTime();

                return isAdminNavNotice && now - initializedAt < initialPollDelay;
            }

            timer = window.setInterval(function () {
                if (document.hidden) {
                    return;
                }
                pollUnread();
            }, pollInterval);
            document.addEventListener('visibilitychange', function () {
                if (shouldDelayAdminInitialPoll()) {
                    return;
                }
                pollUnread();
            });
            window.addEventListener('focus', function () {
                if (shouldDelayAdminInitialPoll()) {
                    return;
                }
                pollUnread();
            });
            window.addEventListener('pagehide', function (event) {
                if (!event.persisted) {
                    window.clearInterval(timer);
                }
            });
            window.addEventListener('pageshow', function () {
                if (shouldDelayAdminInitialPoll()) {
                    return;
                }
                pollUnread();
            });
            window.setTimeout(function () {
                pollUnread(true);
            }, initialPollDelay);
        });
    }

    function applyCustomerServicePayload(chat, data, options) {
        var role = chat.getAttribute('data-customer-service-role') || 'member';
        var previousSessionId = chat.getAttribute('data-session-id') || '0';
        var renderOptions = options || {};
        var currentSessionId;
        var baselineReady;
        var lastIncomingMessageId;
        var latestIncomingMessageId;
        var newIncomingCount;
        var log;
        var chatIsReadingLatest;
        var chatUnread;
        var previousQueueUnread;
        var nextQueueUnread;
        var shouldPlayIncomingSound;
        var scoreText;
        var agentSilentPoll;
        var preserveActiveSession;
        var payloadActiveId;
        var payloadMatchesActiveSession = true;
        var activeMessages;
        var sessionListActiveId;
        var forceLatestScroll;

        if (!data) {
            return;
        }
        if (data.sessions_stamp !== undefined) {
            chat.setAttribute('data-customer-service-sessions-stamp', customerServiceText(data.sessions_stamp));
        }

        preserveActiveSession = role === 'agent' && !!renderOptions.preserveActiveSession && previousSessionId !== '0';
        payloadActiveId = data.active_id !== undefined
            ? customerServiceText(data.active_id)
            : (data.session && data.session.id !== undefined ? customerServiceText(data.session.id) : '');
        if (preserveActiveSession && payloadActiveId !== previousSessionId) {
            payloadMatchesActiveSession = false;
            chat.setAttribute('data-session-id', previousSessionId);
        } else {
            if (data.active_id !== undefined) {
                chat.setAttribute('data-session-id', customerServiceText(data.active_id));
            } else if (data.session && data.session.id !== undefined) {
                chat.setAttribute('data-session-id', customerServiceText(data.session.id));
            }
        }

        currentSessionId = chat.getAttribute('data-session-id') || '0';
        if (role === 'agent' && data.poll_unchanged) {
            renderServiceAgentPresence(chat, data);
            if (payloadMatchesActiveSession) {
                renderServiceAgentQueueActions(chat, data);
                renderCustomerServiceActiveSession(chat, data);
                renderCustomerServiceStatus(chat, data.typing_status || null);
            }
            return;
        }

        activeMessages = payloadMatchesActiveSession ? (data.messages || []) : [];
        latestIncomingMessageId = latestCustomerServiceIncomingMessageId(chat, activeMessages);
        lastIncomingMessageId = parseInt(chat.getAttribute('data-last-incoming-message-id') || '0', 10);
        newIncomingCount = countCustomerServiceIncomingMessagesAfter(chat, activeMessages, lastIncomingMessageId);
        forceLatestScroll = payloadMatchesActiveSession && newIncomingCount > 0;
        baselineReady = chat.getAttribute('data-incoming-message-baseline-ready') === '1';
        log = chat.querySelector('[data-customer-service-log]');
        agentSilentPoll = role === 'agent' && !renderOptions.forceScroll && currentSessionId === previousSessionId;
        chatIsReadingLatest = role === 'agent' && serviceAgentChatPanelIsReadable(chat) && (customerServiceLogNearBottom(log) || forceLatestScroll);
        shouldPlayIncomingSound = currentSessionId === previousSessionId
            && baselineReady
            && latestIncomingMessageId > lastIncomingMessageId;

        if (role === 'agent' && data.sessions) {
            previousQueueUnread = serviceAgentQueueUnreadCount(chat);
            nextQueueUnread = countServiceAgentQueueUnread(data.sessions);
            if (baselineReady && nextQueueUnread > previousQueueUnread) {
                shouldPlayIncomingSound = true;
            }
        }

        if (shouldPlayIncomingSound) {
            playCustomerServiceNoticeSound();
            showCustomerServiceSystemNotice(
                '在线客服新消息',
                role === 'agent' ? '会员有新消息，请及时处理。' : '客服有新回复，请及时查看。',
                'customer-service-chat-' + currentSessionId + '-' + latestIncomingMessageId
            );
        }

        if (role === 'agent') {
            chatUnread = currentSessionId === previousSessionId ? serviceAgentChatUnreadCount(chat) : 0;
            if (currentSessionId === previousSessionId && baselineReady && newIncomingCount > 0 && !chatIsReadingLatest) {
                chatUnread += newIncomingCount;
            }
            if (chatIsReadingLatest || currentSessionId !== previousSessionId) {
                chatUnread = 0;
            }
            setServiceAgentChatUnread(chat, chatUnread);
            if (payloadMatchesActiveSession && chatIsReadingLatest) {
                clearServiceAgentSessionUnread(chat, currentSessionId);
            }
        }

        if (latestIncomingMessageId > 0 || currentSessionId !== previousSessionId || !baselineReady) {
            chat.setAttribute('data-last-incoming-message-id', String(latestIncomingMessageId));
            chat.setAttribute('data-incoming-message-baseline-ready', '1');
        }

        if (role === 'agent' && !payloadMatchesActiveSession) {
            chat.setAttribute('data-has-session', '1');
            setServiceAgentView(chat, chat.getAttribute('data-agent-active-view') || 'chat');
        }

        if (role === 'agent' && payloadMatchesActiveSession) {
            scoreText = serviceAgentScoreFromPayload(data);
            if (scoreText !== '') {
                updateServiceAgentScoreValue(chat, scoreText);
            }
        }

        if (payloadMatchesActiveSession) {
            renderCustomerServiceMessages(chat, data.messages || [], {
                forceScroll: !!renderOptions.forceScroll || currentSessionId !== previousSessionId || forceLatestScroll,
                forceAppendScroll: payloadMatchesActiveSession && (
                    !!renderOptions.forceScroll
                    || forceLatestScroll
                    || role === 'member'
                    || (role === 'agent' && serviceAgentChatPanelIsReadable(chat))
                ),
                preserveScroll: agentSilentPoll && !forceLatestScroll
            });
        }
        renderServiceAgentPresence(chat, data);
        renderServiceAgentSettings(chat, data.agent || null);
        renderCustomerServiceProfile(chat, data.service_profile || null);
        if (payloadMatchesActiveSession) {
            renderServiceAgentQueueActions(chat, data);
            renderCustomerServiceActiveSession(chat, data);
            renderCustomerServiceStatus(chat, data.typing_status || null);
        }
        if (role === 'member' && data.session && data.session.unread_for_member !== undefined) {
            renderCustomerServiceUnread(data.session.unread_for_member);
        }

        if (data.sessions) {
            sessionListActiveId = payloadMatchesActiveSession ? (data.active_id || chat.getAttribute('data-session-id')) : chat.getAttribute('data-session-id');
            renderCustomerServiceSessions(chat, data.sessions, sessionListActiveId);
            if (role === 'agent') {
                updateServiceAgentQueueUnread(chat, data.sessions);
            }
        }
    }

    function setCustomerServiceComposeKeyboardActive(chat, active) {
        var keyboardActive = !!active;
        var root = document.documentElement;
        var rebuiltThread = !!(chat && chat.querySelector && chat.querySelector('.service-thread-composer'));
        var appleThreadKeyboardActive = keyboardActive && rebuiltThread && isAppleTouchDevice();
        var agentThreadKeyboardActive = keyboardActive
            && rebuiltThread
            && !!(chat && chat.getAttribute && chat.getAttribute('data-customer-service-role') === 'agent');

        if (!chat || !document.body || !document.body.classList) {
            return;
        }

        if (root && root.classList) {
            root.classList.toggle('customer-service-compose-keyboard-active-root', keyboardActive && !rebuiltThread);
            root.classList.toggle('customer-service-ios-keyboard-active-root', appleThreadKeyboardActive);
        }
        chat.classList.toggle('is-compose-keyboard-active', keyboardActive);
        document.body.classList.toggle('customer-service-compose-keyboard-active', keyboardActive);
        document.body.classList.toggle('customer-service-ios-keyboard-active', appleThreadKeyboardActive);
        document.body.classList.toggle('customer-service-agent-compose-active', agentThreadKeyboardActive);

        if (keyboardActive) {
            updateCustomerServiceViewport();
            lockCustomerServiceKeyboardScroll();
        } else {
            clearCustomerServiceViewport();
        }
    }

    function updateCustomerServiceViewport() {
        var root = document.documentElement;
        var viewport = window.visualViewport || null;
        var height = viewport && viewport.height ? viewport.height : window.innerHeight;
        var offsetTop = viewport && typeof viewport.offsetTop === 'number' ? viewport.offsetTop : 0;
        var layoutHeight = window.innerHeight || height;
        var keyboardInset = 0;

        if (!root || !height) {
            return;
        }

        if (window.innerHeight) {
            keyboardInset = Math.max(0, Math.floor(window.innerHeight - height - offsetTop));
        }

        root.style.setProperty('--customer-service-viewport-height', Math.max(320, Math.floor(height)) + 'px');
        root.style.setProperty('--customer-service-layout-height', Math.max(320, Math.floor(layoutHeight)) + 'px');
        root.style.setProperty('--customer-service-viewport-top', Math.max(0, Math.floor(offsetTop)) + 'px');
        root.style.setProperty('--customer-service-keyboard-inset', keyboardInset + 'px');
    }

    function clearCustomerServiceViewport() {
        var root = document.documentElement;

        if (!root || !root.style) {
            return;
        }

        root.style.removeProperty('--customer-service-viewport-height');
        root.style.removeProperty('--customer-service-layout-height');
        root.style.removeProperty('--customer-service-viewport-top');
        root.style.removeProperty('--customer-service-keyboard-inset');
    }

    function resetCustomerServiceWindowScroll() {
        var activeElement = document.activeElement;
        var scroller;

        if (
            !document.body
            || !document.body.classList
            || !document.body.classList.contains('customer-service-compose-keyboard-active')
        ) {
            return;
        }

        if (
            activeElement
            && activeElement.closest
            && activeElement.closest('.service-thread-composer')
            && !isAppleTouchDevice()
        ) {
            return;
        }

        scroller = document.scrollingElement || document.documentElement || document.body;
        if (scroller) {
            scroller.scrollTop = 0;
            scroller.scrollLeft = 0;
        }
        if (typeof window.scrollTo === 'function') {
            try {
                window.scrollTo({ top: 0, left: 0, behavior: 'auto' });
            } catch (error) {
                window.scrollTo(0, 0);
            }
        }
    }

    function lockCustomerServiceKeyboardScroll() {
        resetCustomerServiceWindowScroll();
        if (window.requestAnimationFrame) {
            window.requestAnimationFrame(resetCustomerServiceWindowScroll);
        }
        window.setTimeout(resetCustomerServiceWindowScroll, 80);
        window.setTimeout(resetCustomerServiceWindowScroll, 220);
        window.setTimeout(resetCustomerServiceWindowScroll, 420);
    }

    function customerServiceKeyboardDebugEnabled() {
        return /(?:^|[?&])debug_keyboard=1(?:&|$)/.test(window.location.search || '');
    }

    function customerServiceDebugRect(node) {
        var rect;

        if (!node || !node.getBoundingClientRect) {
            return '-';
        }

        rect = node.getBoundingClientRect();
        return [
            Math.round(rect.top),
            Math.round(rect.bottom),
            Math.round(rect.height)
        ].join('/');
    }

    function updateCustomerServiceKeyboardDebug(chat) {
        var panel;
        var viewport = window.visualViewport || null;
        var activeElement = document.activeElement;
        var lines;

        if (!customerServiceKeyboardDebugEnabled() || !chat || !document.body) {
            return;
        }

        panel = document.querySelector('[data-customer-service-keyboard-debug]');
        if (!panel) {
            panel = document.createElement('pre');
            panel.setAttribute('data-customer-service-keyboard-debug', '1');
            panel.style.position = 'fixed';
            panel.style.left = '6px';
            panel.style.top = '6px';
            panel.style.zIndex = '2147483647';
            panel.style.maxWidth = 'calc(100vw - 12px)';
            panel.style.margin = '0';
            panel.style.padding = '6px 8px';
            panel.style.borderRadius = '8px';
            panel.style.background = 'rgba(15, 23, 42, 0.86)';
            panel.style.color = '#fff';
            panel.style.font = '11px/1.35 monospace';
            panel.style.whiteSpace = 'pre-wrap';
            panel.style.pointerEvents = 'none';
            document.body.appendChild(panel);
        }

        lines = [
            'ih=' + Math.round(window.innerHeight || 0),
            'vvh=' + Math.round(viewport && viewport.height ? viewport.height : 0),
            'vvo=' + Math.round(viewport && typeof viewport.offsetTop === 'number' ? viewport.offsetTop : 0),
            'doc=' + customerServiceDebugRect(document.documentElement),
            'body=' + customerServiceDebugRect(document.body),
            'frame=' + customerServiceDebugRect(document.querySelector('.page-frame')),
            'page=' + customerServiceDebugRect(chat),
            'uframe=' + customerServiceDebugRect(chat.querySelector('.front-unified-frame')),
            'thread=' + customerServiceDebugRect(chat.querySelector('[data-service-thread]')),
            'log=' + customerServiceDebugRect(chat.querySelector('[data-customer-service-log]')),
            'compose=' + customerServiceDebugRect(chat.querySelector('[data-customer-service-form]')),
            'active=' + (activeElement && activeElement.matches && activeElement.matches('[data-customer-service-input]') ? 'input' : (activeElement ? activeElement.tagName : '-')),
            'class=' + document.body.className
        ];

        panel.textContent = lines.join('\n');
    }

    function syncCustomerServiceComposeKeyboardActive(chat) {
        var activeElement = document.activeElement;
        var composeFocused = !!(chat && chat.getAttribute('data-compose-focused') === '1');
        var inputFocused = !!(
            chat
            && activeElement
            && activeElement.matches
            && activeElement.matches('[data-customer-service-input]')
            && chat.contains(activeElement)
        );

        inputFocused = inputFocused || composeFocused;
        setCustomerServiceComposeKeyboardActive(chat, inputFocused);
        updateCustomerServiceKeyboardDebug(chat);
    }

    function initCustomerServiceChats(root) {
        var scope = root || document;
        var chats = scope.querySelectorAll('[data-customer-service]');

        Array.prototype.forEach.call(chats, function (chat) {
            var form;
            var input;
            var imageInput;
            var imageTrigger;
            var emojiToggle;
            var emojiPanel;
            var voiceButton;
            var agentScoreButton;
            var agentScoreForm;
            var agentScoreCloseButtons;
            var pendingPreview;
            var clearButton;
            var agentForm;
            var agentViewButtons;
            var agentPresenceButton;
            var agentSettingsOpenButtons;
            var agentSettingsCloseButtons;
            var agentSettingsForm;
            var agentQueueDeleteButton;
            var agentQueueBatchDeleteButton;
            var sessionList;
            var pollAction;
            var sendAction;
            var typingAction;
            var streamUrl;
            var role;
            var enabled;
            var timer;
            var pollBootTimer = 0;
            var pollBusy = false;
            var pollInterval = 10000;
            var fastPollInterval = 3000;
            var initialPollDelay = 120;
            var minPollSpacing = 3000;
            var lastPollStartedAt = 0;
            var lightPollSlimCount = 0;
            var log;
            var typingIdleTimer = null;
            var lastTypingState = false;
            var lastTypingType = '';
            var lastTypingSentAt = 0;
            var pendingMessage = null;
            var sendBusy = false;
            var mediaRecorder = null;
            var mediaChunks = [];
            var mediaStream = null;
            var voiceStartedAt = 0;
            var voicePressing = false;
            var messageMutationSerial = 0;
            var pollFailureCount = 0;
            var pollRetryAfter = 0;
            var presenceRequestSerial = 0;
            var streamSource = null;
            var streamKey = '';
            var streamActive = false;
            var streamReconnectTimer = 0;
            var streamPollQueued = false;

            if (chat.getAttribute('data-customer-service-ready') === '1') {
                return;
            }

            chat.setAttribute('data-customer-service-ready', '1');
            form = chat.querySelector('[data-customer-service-form]');
            input = chat.querySelector('[data-customer-service-input]');
            imageInput = chat.querySelector('[data-customer-service-image]');
            imageTrigger = chat.querySelector('[data-customer-service-image-trigger]');
            emojiToggle = chat.querySelector('[data-customer-service-emoji-toggle]');
            emojiPanel = chat.querySelector('[data-customer-service-emoji-panel]');
            voiceButton = chat.querySelector('[data-customer-service-voice]');
            agentScoreButton = chat.querySelector('[data-service-agent-score-open]');
            agentScoreForm = chat.querySelector('[data-service-agent-score-form]');
            agentScoreCloseButtons = chat.querySelectorAll('[data-service-agent-score-close]');
            pendingPreview = chat.querySelector('[data-customer-service-pending]');
            clearButton = chat.querySelector('[data-customer-service-clear]');
            agentForm = chat.querySelector('[data-customer-service-agent-form]');
            agentViewButtons = chat.querySelectorAll('[data-service-agent-view-target]');
            agentPresenceButton = chat.querySelector('[data-service-agent-presence-toggle]');
            agentSettingsOpenButtons = chat.querySelectorAll('[data-service-agent-settings-open]');
            agentSettingsCloseButtons = chat.querySelectorAll('[data-service-agent-settings-close]');
            agentSettingsForm = chat.querySelector('[data-service-agent-settings-form]');
            agentQueueDeleteButton = chat.querySelector('[data-service-agent-delete-current]');
            agentQueueBatchDeleteButton = chat.querySelector('[data-service-agent-batch-delete]');
            sessionList = chat.querySelector('[data-customer-service-session-list]');
            pollAction = chat.getAttribute('data-poll-action') || '';
            sendAction = chat.getAttribute('data-send-action') || '';
            typingAction = chat.getAttribute('data-typing-action') || '';
            streamUrl = chat.getAttribute('data-stream-url') || '';
            role = chat.getAttribute('data-customer-service-role') || 'member';
            enabled = chat.getAttribute('data-enabled') !== '0';
            pollInterval = role === 'agent' ? 12000 : 15000;
            fastPollInterval = role === 'agent' ? 5000 : 5000;
            initialPollDelay = role === 'agent' ? 400 : 500;
            minPollSpacing = role === 'agent' ? 4200 : 4200;
            bindCustomerServiceNoticeAudioUnlock();
            if (role === 'agent') {
                chat.setAttribute('data-service-agent-queue-unread-count', String(serviceAgentQueueUnreadCountFromBadge(chat)));
            }
            chat.setAttribute('data-last-incoming-message-id', String(latestRenderedIncomingMessageId(chat)));
            chat.setAttribute('data-incoming-message-baseline-ready', currentSessionId() !== '0' ? '1' : '0');
            log = chat.querySelector('[data-customer-service-log]');
            if (log) {
                customerServiceScrollToBottomSoon(log);
                if (role === 'agent') {
                    log.addEventListener('scroll', function () {
                        if (serviceAgentChatPanelIsReadable(chat) && customerServiceLogNearBottom(log)) {
                            setServiceAgentChatUnread(chat, 0);
                        }
                    }, { passive: true });
                }
            }
            if (voiceButton) {
                voiceButton.setAttribute('data-voice-default-html', voiceButton.innerHTML);
            }
            if (input) {
                var activateCustomerServiceCompose = function () {
                    chat.setAttribute('data-compose-focused', '1');
                    setCustomerServiceComposeKeyboardActive(chat, true);
                    updateCustomerServiceKeyboardDebug(chat);
                };
                var prepareCustomerServiceComposeTap = function () {
                    updateCustomerServiceViewport();
                    updateCustomerServiceKeyboardDebug(chat);
                };
                input.addEventListener('pointerdown', prepareCustomerServiceComposeTap, { passive: true });
                input.addEventListener('touchstart', prepareCustomerServiceComposeTap, { passive: true });
                input.addEventListener('focusin', function () {
                    activateCustomerServiceCompose();
                    [80, 180, 320, 560].forEach(function (delay) {
                        window.setTimeout(function () {
                            syncCustomerServiceComposeKeyboardActive(chat);
                            lockCustomerServiceKeyboardScroll();
                        }, delay);
                    });
                });
                input.addEventListener('input', function () {
                    lockCustomerServiceKeyboardScroll();
                });
                input.addEventListener('click', function () {
                    lockCustomerServiceKeyboardScroll();
                });
                input.addEventListener('keyup', function () {
                    lockCustomerServiceKeyboardScroll();
                });
                input.addEventListener('focusout', function () {
                    window.setTimeout(function () {
                        var activeElement = document.activeElement;

                        if (
                            activeElement
                            && activeElement.matches
                            && activeElement.matches('[data-customer-service-input]')
                            && chat.contains(activeElement)
                        ) {
                            return;
                        }

                        chat.removeAttribute('data-compose-focused');
                        setCustomerServiceComposeKeyboardActive(chat, false);
                        updateCustomerServiceKeyboardDebug(chat);
                    }, 80);
                });
                if (window.visualViewport && !chat.getAttribute('data-customer-service-keyboard-viewport-ready')) {
                    chat.setAttribute('data-customer-service-keyboard-viewport-ready', '1');
                    window.visualViewport.addEventListener('resize', function () {
                        syncCustomerServiceComposeKeyboardActive(chat);
                        lockCustomerServiceKeyboardScroll();
                        window.setTimeout(function () {
                            updateCustomerServiceKeyboardDebug(chat);
                        }, 80);
                    }, { passive: true });
                    window.visualViewport.addEventListener('scroll', function () {
                        syncCustomerServiceComposeKeyboardActive(chat);
                        lockCustomerServiceKeyboardScroll();
                        window.setTimeout(function () {
                            updateCustomerServiceKeyboardDebug(chat);
                        }, 80);
                    }, { passive: true });
                }
                if (!chat.getAttribute('data-customer-service-keyboard-selection-ready')) {
                    chat.setAttribute('data-customer-service-keyboard-selection-ready', '1');
                    document.addEventListener('selectionchange', function () {
                        if (document.activeElement === input) {
                            lockCustomerServiceKeyboardScroll();
                        }
                    });
                }
            }
            if (role === 'agent') {
                if (agentScoreButton) {
                    agentScoreButton.addEventListener('click', function (event) {
                        var scoreAction = chat.getAttribute('data-score-action') || '';
                        var sessionId = currentSessionId();

                        event.preventDefault();
                        if (!scoreAction || agentScoreButton.disabled || sessionId === '0') {
                            return;
                        }

                        renderServiceAgentScoreModal(chat, {
                            username: chat.getAttribute('data-service-agent-score-account') || '会员'
                        });
                        setServiceAgentScoreModal(chat, true);
                    });
                }
                Array.prototype.forEach.call(agentScoreCloseButtons, function (button) {
                    button.addEventListener('click', function (event) {
                        event.preventDefault();
                        setServiceAgentScoreModal(chat, false);
                    });
                });
                if (agentScoreForm) {
                    prepareServiceAgentScoreAmountField(agentScoreForm.querySelector('[data-service-agent-score-amount]'));
                    agentScoreForm.addEventListener('focusin', function (event) {
                        setServiceAgentScoreKeyboardActive(chat, true);
                        scrollFormModalInputIntoView(event.target);
                    });
                    agentScoreForm.addEventListener('focusout', function () {
                        window.setTimeout(function () {
                            if (!agentScoreForm.contains(document.activeElement)) {
                                setServiceAgentScoreKeyboardActive(chat, false);
                            }
                        }, 80);
                    });
                    agentScoreForm.addEventListener('submit', function (event) {
                        var scoreAction = chat.getAttribute('data-score-action') || '';
                        var sessionId = currentSessionId();
                        var amountInput = agentScoreForm.querySelector('[data-service-agent-score-amount]');
                        var submitButton = agentScoreForm.querySelector('[data-service-agent-score-submit]');
                        var amountText = normalizeServiceAgentScoreAmount(amountInput ? amountInput.value : '');
                        var amount;
                        var scoreText;

                        event.preventDefault();
                        if (!scoreAction || sessionId === '0') {
                            toast('请先选择需要充值的会员。', 'error');
                            return;
                        }

                        if (!/^-?[1-9][0-9]{0,8}$/.test(amountText)) {
                            toast('请输入非 0 整数积分。', 'error');
                            return;
                        }

                        amount = parseInt(amountText, 10);
                        if (Math.abs(amount) > 100000000) {
                            toast('单次调整积分不能超过 100000000。', 'error');
                            return;
                        }

                        if (submitButton) {
                            submitButton.disabled = true;
                        }
                        if (agentScoreButton) {
                            agentScoreButton.disabled = true;
                        }

                        customerServicePost(chat, scoreAction, {
                            session_id: sessionId,
                            status: currentStatus(),
                            score_amount: String(amount)
                        }).then(function (payload) {
                            toast(payload.message || '会员积分已充值。', 'success');
                            scoreText = serviceAgentScoreFromPayload(payload.data || {});
                            if (scoreText === '') {
                                scoreText = serviceAgentScoreFromMessage(payload.message || '');
                            }
                            if (scoreText !== '') {
                                updateServiceAgentScoreValue(chat, scoreText);
                            }
                            if (amountInput) {
                                amountInput.value = '';
                            }
                            applyCustomerServicePayload(chat, payload.data || {}, {
                                preserveActiveSession: role === 'agent'
                            });
                            setServiceAgentScoreModal(chat, false);
                        }).catch(function (error) {
                            toast(error.message, 'error');
                        }).finally(function () {
                            if (submitButton) {
                                submitButton.disabled = false;
                            }
                            if (agentScoreButton) {
                                agentScoreButton.disabled = currentSessionId() === '0';
                            }
                        });
                    });
                }
                if (agentSettingsForm) {
                    agentSettingsForm.addEventListener('focusin', function (event) {
                        if (!isFormModalInput(event.target)) {
                            return;
                        }

                        setServiceAgentSettingsKeyboardActive(chat, true);
                        scrollFormModalInputIntoView(event.target);
                    });
                    agentSettingsForm.addEventListener('focusout', function () {
                        window.setTimeout(function () {
                            if (!agentSettingsForm.contains(document.activeElement)) {
                                setServiceAgentSettingsKeyboardActive(chat, false);
                            }
                        }, 80);
                    });
                }
                setServiceAgentView(chat, chat.getAttribute('data-agent-active-view') || 'queue');
                bindServiceAgentDesktopSplitWatcher(chat);
                Array.prototype.forEach.call(agentViewButtons, function (button) {
                    button.addEventListener('click', function (event) {
                        event.preventDefault();
                        setServiceAgentView(chat, button.getAttribute('data-service-agent-view-target') || 'queue', true);
                        syncCustomerServiceStream();
                    });
                });
                if (agentPresenceButton) {
                    agentPresenceButton.addEventListener('click', function (event) {
                        var nextOnline = agentPresenceButton.getAttribute('data-next-online') === '1';
                        var presenceAction = chat.getAttribute('data-presence-action') || '';

                        event.preventDefault();
                        if (!presenceAction) {
                            return;
                        }

                        presenceRequestSerial += 1;
                        var requestSerial = presenceRequestSerial;
                        renderServiceAgentPresence(chat, {
                            agent_online: nextOnline,
                            agent_online_label: nextOnline ? '在线中···' : '休息中···',
                            agent_online_type: nextOnline ? 'online' : 'offline'
                        });
                        customerServicePost(chat, presenceAction, {
                            online: nextOnline ? '1' : '0',
                            session_id: currentSessionId(),
                            status: currentStatus()
                        }).then(function (response) {
                            if (requestSerial !== presenceRequestSerial) {
                                return;
                            }
                            toast(response.message || (nextOnline ? '已在线接待。' : '已离线。'), 'success');
                            renderServiceAgentPresence(chat, response.data || {
                                agent_online: nextOnline,
                                agent_online_label: nextOnline ? '在线中···' : '休息中···',
                                agent_online_type: nextOnline ? 'online' : 'offline'
                            });
                        }).catch(function (error) {
                            if (requestSerial !== presenceRequestSerial) {
                                return;
                            }
                            renderServiceAgentPresence(chat, {
                                agent_online: !nextOnline,
                                agent_online_label: !nextOnline ? '在线中···' : '休息中···',
                                agent_online_type: !nextOnline ? 'online' : 'offline'
                            });
                            toast(error.message, 'error');
                        });
                    });
                }
                Array.prototype.forEach.call(agentSettingsOpenButtons, function (button) {
                    button.addEventListener('click', function (event) {
                        event.preventDefault();
                        setServiceAgentSettingsModal(chat, true);
                    });
                });
                Array.prototype.forEach.call(agentSettingsCloseButtons, function (button) {
                    button.addEventListener('click', function (event) {
                        event.preventDefault();
                        setServiceAgentSettingsModal(chat, false);
                    });
                });
                if (agentSettingsForm) {
                    agentSettingsForm.addEventListener('submit', function (event) {
                        var button = agentSettingsForm.querySelector('[type="submit"]');
                        var formData = new FormData(agentSettingsForm);
                        var endpoint = agentSettingsForm.getAttribute('action') || chat.getAttribute('data-api-url') || './api.php';
                        var settingsAction = chat.getAttribute('data-settings-action') || 'customer_service.agent.settings';
                        var activityNoticeEnabled = agentSettingsForm.querySelector('[name="activity_notice_enabled"][type="checkbox"]');

                        event.preventDefault();
                        formData.set('action', settingsAction);
                        formData.set('session_id', currentSessionId());
                        formData.set('status', currentStatus());
                        formData.set('activity_notice_enabled', activityNoticeEnabled && activityNoticeEnabled.checked ? '1' : '0');

                        if (button) {
                            button.disabled = true;
                        }

                        fetch(endpoint, {
                            method: 'POST',
                            body: formData,
                            credentials: 'same-origin'
                        }).then(function (response) {
                            return toJson(response);
                        }).then(function (payload) {
                            if (!payload.success) {
                                throw payloadError(payload, '设置保存失败。');
                            }
                            toast(payload.message || '客服接待设置已保存。', 'success');
                            applyCustomerServicePayload(chat, payload.data || {}, {
                                preserveActiveSession: role === 'agent'
                            });
                            setServiceAgentSettingsModal(chat, false);
                        }).catch(function (error) {
                            if (redirectFromPayloadError(error)) {
                                return;
                            }
                            toast(error.message, 'error');
                        }).finally(function () {
                            if (button) {
                                button.disabled = false;
                            }
                        });
                    });
                }
                if (agentQueueDeleteButton) {
                    agentQueueDeleteButton.addEventListener('click', function (event) {
                        var deleteAction = chat.getAttribute('data-delete-action') || '';
                        var sessionId = currentSessionId();

                        event.preventDefault();
                        if (!deleteAction || agentQueueDeleteButton.disabled || sessionId === '0') {
                            return;
                        }

                        deleteServiceAgentQueueSessions(chat, [sessionId], agentQueueDeleteButton);
                    });
                }
                if (agentQueueBatchDeleteButton) {
                    agentQueueBatchDeleteButton.addEventListener('click', function (event) {
                        var selectedIds;

                        event.preventDefault();
                        selectedIds = selectedServiceAgentSessionIds(chat);
                        if (!selectedIds.length) {
                            syncServiceAgentQueueSelection(chat);
                            toast('请先选择要删除的会话。', 'error');
                            return;
                        }

                        deleteServiceAgentQueueSessions(chat, selectedIds, agentQueueBatchDeleteButton);
                    });
                }
                if (role === 'agent') {
                    chat.addEventListener('change', function (event) {
                        var queueSelectAll = event.target.closest('[data-service-agent-select-all]');
                        var queueSelect = event.target.closest('[data-service-agent-session-select]');
                        var blockSelect = event.target.closest('[data-service-agent-block-limit]');
                        var blockAction = chat.getAttribute('data-block-action') || '';
                        var unblockAction = chat.getAttribute('data-unblock-action') || '';
                        var sessionId;
                        var blocked;
                        var blockLimit;
                        var unblocking;
                        var action;

                        if (queueSelectAll && chat.contains(queueSelectAll)) {
                            Array.prototype.forEach.call(chat.querySelectorAll('[data-service-agent-session-select]'), function (input) {
                                input.checked = queueSelectAll.checked;
                            });
                            syncServiceAgentQueueSelection(chat);
                            return;
                        }

                        if (queueSelect && chat.contains(queueSelect)) {
                            syncServiceAgentQueueSelection(chat);
                            return;
                        }

                        if (!blockSelect || !chat.contains(blockSelect)) {
                            return;
                        }

                        sessionId = blockSelect.getAttribute('data-session-id') || currentSessionId();
                        blocked = blockSelect.getAttribute('data-blocked') === '1';
                        blockLimit = blockSelect.value || '';
                        unblocking = blockLimit === 'unblock';
                        action = unblocking ? unblockAction : blockAction;

                        if (!blockLimit || blockSelect.disabled || sessionId === '0' || !action || (blocked && !unblocking) || (!blocked && unblocking)) {
                            blockSelect.value = '';
                            return;
                        }

                        if (unblocking) {
                            blockSelect.removeAttribute('data-block-label');
                        } else {
                            blockSelect.setAttribute('data-block-label', serviceAgentBlockLimitLabel(blockLimit));
                        }

                        blockSelect.disabled = true;
                        customerServicePost(chat, action, {
                            session_id: sessionId,
                            status: currentStatus(),
                            block_limit: unblocking ? '' : blockLimit
                        }).then(function (payload) {
                            toast(payload.message || (unblocking ? '该会员会话已解除屏蔽。' : '该会员会话已屏蔽。'), 'success');
                            applyCustomerServicePayload(chat, payload.data || {}, {
                                forceScroll: true,
                                preserveActiveSession: role === 'agent'
                            });
                        }).catch(function (error) {
                            toast(error.message, 'error');
                            if (!unblocking) {
                                blockSelect.removeAttribute('data-block-label');
                            }
                            blockSelect.value = '';
                        }).finally(function () {
                            blockSelect.disabled = false;
                        });
                    });
                }
                if (sessionList) {
                    sessionList.addEventListener('click', function (event) {
                        var deleteButton = event.target.closest('[data-service-agent-session-delete]');
                        var interactive = event.target.closest('[data-service-agent-session-select], [data-service-agent-session-delete], .service-agent-session-check');
                        var sessionCard = event.target.closest('[data-service-agent-session-card]');
                        var sessionHref;

                        if (deleteButton && sessionList.contains(deleteButton)) {
                            event.preventDefault();
                            event.stopPropagation();
                            deleteServiceAgentQueueSessions(chat, [deleteButton.getAttribute('data-session-id') || '0'], deleteButton);
                            return;
                        }

                        if (interactive) {
                            event.stopPropagation();
                            return;
                        }

                        if (!sessionCard) {
                            return;
                        }

                        sessionHref = sessionCard.getAttribute('data-session-href') || '';
                        if (sessionHref) {
                            window.location.href = sessionHref;
                        }
                    });
                    sessionList.addEventListener('keydown', function (event) {
                        var interactive = event.target.closest('[data-service-agent-session-select], [data-service-agent-session-delete], .service-agent-session-check');
                        var sessionCard = event.target.closest('[data-service-agent-session-card]');
                        var sessionHref;

                        if (interactive) {
                            return;
                        }

                        if (!sessionCard || (event.key !== 'Enter' && event.key !== ' ')) {
                            return;
                        }

                        event.preventDefault();
                        sessionHref = sessionCard.getAttribute('data-session-href') || '';
                        if (sessionHref) {
                            window.location.href = sessionHref;
                        }
                    });
                    syncServiceAgentQueueSelection(chat);
                }
            }

            function currentSessionId() {
                return chat.getAttribute('data-session-id') || '0';
            }

            function currentStatus() {
                return chat.getAttribute('data-status') || 'all';
            }

            function customerServiceStreamAllowed() {
                // Mobile WebViews on this deployment buffer SSE, so keep chat refresh on fast light polling.
                return false;

                if (!enabled || !streamUrl || !pollAction || document.hidden || typeof window.EventSource === 'undefined') {
                    return false;
                }

                if (currentSessionId() === '0') {
                    return false;
                }

                if (role === 'agent') {
                    return serviceAgentChatPanelIsReadable(chat);
                }

                return role === 'member';
            }

            function customerServiceStreamKey() {
                return [
                    role,
                    currentSessionId(),
                    currentStatus(),
                    chat.getAttribute('data-agent-active-view') || ''
                ].join(':');
            }

            function customerServiceStreamHref() {
                var params = [
                    'role=' + encodeURIComponent(role),
                    'session_id=' + encodeURIComponent(currentSessionId()),
                    'status=' + encodeURIComponent(currentStatus()),
                    'last_id=' + encodeURIComponent(String(latestRenderedCustomerServiceMessageId(chat))),
                    '_token=' + encodeURIComponent(chat.getAttribute('data-token') || '')
                ];

                return streamUrl + (streamUrl.indexOf('?') === -1 ? '?' : '&') + params.join('&');
            }

            function stopCustomerServiceStream() {
                if (streamReconnectTimer) {
                    window.clearTimeout(streamReconnectTimer);
                    streamReconnectTimer = 0;
                }
                if (streamSource) {
                    streamSource.close();
                    streamSource = null;
                }
                streamActive = false;
                streamKey = '';
            }

            function scheduleCustomerServiceStreamPoll(force) {
                if (streamPollQueued) {
                    return;
                }

                streamPollQueued = true;
                window.setTimeout(function () {
                    streamPollQueued = false;
                    if (force) {
                        poll(true);
                        return;
                    }
                    poll();
                }, 40);
            }

            function startCustomerServiceStream() {
                var nextKey;
                var source;

                if (!customerServiceStreamAllowed()) {
                    stopCustomerServiceStream();
                    return;
                }

                nextKey = customerServiceStreamKey();
                if (streamSource && streamKey === nextKey) {
                    return;
                }

                stopCustomerServiceStream();
                streamKey = nextKey;
                source = new window.EventSource(customerServiceStreamHref());
                streamSource = source;
                source.addEventListener('open', function () {
                    if (streamSource !== source) {
                        return;
                    }
                    streamActive = true;
                });
                source.addEventListener('customer-service-message', function () {
                    if (streamSource !== source) {
                        return;
                    }
                    streamActive = true;
                    scheduleCustomerServiceStreamPoll(true);
                });
                source.addEventListener('customer-service-close', function () {
                    if (streamSource !== source) {
                        return;
                    }
                    stopCustomerServiceStream();
                });
                source.addEventListener('error', function () {
                    if (streamSource !== source) {
                        return;
                    }
                    if (streamSource) {
                        streamSource.close();
                        streamSource = null;
                    }
                    streamActive = false;
                    streamKey = '';
                    if (!streamReconnectTimer && customerServiceStreamAllowed()) {
                        streamReconnectTimer = window.setTimeout(function () {
                            streamReconnectTimer = 0;
                            startCustomerServiceStream();
                        }, 2500);
                    }
                });
            }

            function syncCustomerServiceStream() {
                if (customerServiceStreamAllowed()) {
                    startCustomerServiceStream();
                    return;
                }

                stopCustomerServiceStream();
            }

            function setTypingState(active, force, statusType) {
                var now = Date.now();

                active = !!active;
                statusType = active ? (customerServiceText(statusType) || 'typing') : '';
                if (!enabled || !typingAction) {
                    return;
                }

                if (!force && lastTypingState === active && lastTypingType === statusType && (!active || now - lastTypingSentAt < 2500)) {
                    return;
                }

                lastTypingState = active;
                lastTypingType = statusType;
                lastTypingSentAt = now;

                customerServicePost(chat, typingAction, {
                    role: role,
                    session_id: currentSessionId(),
                    status: currentStatus(),
                    status_type: statusType || 'typing',
                    typing: active ? '1' : '0'
                }).catch(function () {
                });
            }

            function resetLocalTypingState() {
                if (typingIdleTimer) {
                    window.clearTimeout(typingIdleTimer);
                    typingIdleTimer = null;
                }
                lastTypingState = false;
                lastTypingType = '';
                lastTypingSentAt = Date.now();
            }

            function syncCustomerServiceInputHeight() {
                resizeCustomerServiceTextarea(input);
            }

            function updateTypingFromInput() {
                var active = input ? customerServiceText(input.value).trim() !== '' : false;

                syncCustomerServiceInputHeight();
                setTypingState(active, false, 'typing');

                if (typingIdleTimer) {
                    window.clearTimeout(typingIdleTimer);
                    typingIdleTimer = null;
                }

                if (active) {
                    typingIdleTimer = window.setTimeout(function () {
                        setTypingState(false, true);
                    }, 3500);
                }
            }

            function renderPendingMessage() {
                var image;
                var text;
                var clear;

                if (!pendingPreview) {
                    return;
                }

                pendingPreview.innerHTML = '';
                pendingPreview.hidden = !pendingMessage;

                if (!pendingMessage) {
                    return;
                }

                if (pendingMessage.messageType === 'image' && pendingMessage.previewUrl) {
                    image = document.createElement('img');
                    image.src = pendingMessage.previewUrl;
                    image.decoding = 'async';
                    image.width = 96;
                    image.height = 72;
                    image.alt = '待发送图片';
                    pendingPreview.appendChild(image);
                }

                text = document.createElement('span');
                text.textContent = pendingMessage.messageType === 'image' ? (pendingMessage.attachmentName || '待发送图片') : '待发送内容';
                clear = document.createElement('button');
                clear.type = 'button';
                clear.setAttribute('aria-label', '移除待发送内容');
                clear.textContent = '×';
                clear.addEventListener('click', function () {
                    clearPendingMessage();
                    setCustomerServiceLocalStatus(chat, '');
                    setTypingState(false, true);
                    if (imageInput) {
                        imageInput.value = '';
                    }
                });

                pendingPreview.appendChild(text);
                pendingPreview.appendChild(clear);
            }

            function clearPendingMessage() {
                if (pendingMessage && pendingMessage.previewUrl && window.URL && window.URL.revokeObjectURL) {
                    window.URL.revokeObjectURL(pendingMessage.previewUrl);
                }

                pendingMessage = null;
                renderPendingMessage();
            }

            function setPendingImage(file) {
                clearPendingMessage();
                pendingMessage = {
                    messageType: 'image',
                    content: '图片',
                    attachment: file,
                    attachmentName: file.name || 'image',
                    duration: 0,
                    previewUrl: window.URL && window.URL.createObjectURL ? window.URL.createObjectURL(file) : ''
                };
                renderPendingMessage();
                setCustomerServiceLocalStatus(chat, 'image');
                setTypingState(true, true, 'image');
                if (input) {
                    input.focus();
                }
            }

            function pastedImageName(file) {
                var mimeType = file && file.type ? String(file.type).toLowerCase() : '';
                var fileName = file && file.name ? String(file.name) : '';
                var nameExtension = fileName.indexOf('.') !== -1 ? fileName.split('.').pop().toLowerCase() : '';
                var allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
                var extension = 'png';

                if (fileName && allowedExtensions.indexOf(nameExtension) !== -1) {
                    return fileName;
                }

                if (mimeType === 'image/jpeg') {
                    extension = 'jpg';
                } else if (mimeType === 'image/gif') {
                    extension = 'gif';
                } else if (mimeType === 'image/webp') {
                    extension = 'webp';
                } else if (mimeType === 'image/bmp' || mimeType === 'image/x-ms-bmp') {
                    extension = 'bmp';
                }

                return 'paste-image-' + Date.now() + '.' + extension;
            }

            function clipboardImageFile(event) {
                var clipboardData = event.clipboardData || event.dataTransfer || window.clipboardData;
                var items;
                var files;
                var index;
                var item;
                var file;

                if (!clipboardData) {
                    return null;
                }

                items = clipboardData.items || [];
                for (index = 0; index < items.length; index += 1) {
                    item = items[index];
                    if (item && item.kind === 'file' && String(item.type || '').indexOf('image/') === 0) {
                        file = item.getAsFile ? item.getAsFile() : null;
                        if (file) {
                            return file;
                        }
                    }
                }

                files = clipboardData.files || [];
                for (index = 0; index < files.length; index += 1) {
                    file = files[index];
                    if (file && String(file.type || '').indexOf('image/') === 0) {
                        return file;
                    }
                }

                return null;
            }

            function handleImagePaste(event) {
                var file = clipboardImageFile(event);
                var mimeType = file && file.type ? String(file.type).toLowerCase() : '';
                var allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/x-ms-bmp'];
                var attachmentName;

                if (!file) {
                    return;
                }

                event.preventDefault();
                if (allowedTypes.indexOf(mimeType) === -1) {
                    toast('仅支持粘贴 jpg、jpeg、png、gif、webp、bmp 图片。', 'error');
                    return;
                }

                if (file.size <= 0 || file.size > 5 * 1024 * 1024) {
                    toast('图片大小不能超过 5MB。', 'error');
                    return;
                }

                attachmentName = pastedImageName(file);
                if (pendingPreview) {
                    clearPendingMessage();
                    pendingMessage = {
                        messageType: 'image',
                        content: '图片',
                        attachment: file,
                        attachmentName: attachmentName,
                        duration: 0,
                        previewUrl: window.URL && window.URL.createObjectURL ? window.URL.createObjectURL(file) : ''
                    };
                    renderPendingMessage();
                    setCustomerServiceLocalStatus(chat, 'image');
                    setTypingState(true, true, 'image');
                    if (input) {
                        input.focus();
                    }
                    return;
                }

                sendMessage('image', '图片', file, attachmentName, 0);
            }

            function appendInputText(value) {
                var text = customerServiceText(value);

                if (!input || !text) {
                    return;
                }

                input.value = input.value + text;
                input.focus();
                updateTypingFromInput();
            }

            function closestCustomerServiceTool(target, selector) {
                var control = target && target.closest ? target.closest(selector) : null;

                return control && chat.contains(control) ? control : null;
            }

            function customerServiceToolForm(control) {
                var toolForm = control && control.closest ? control.closest('[data-customer-service-form]') : null;

                return toolForm && chat.contains(toolForm) ? toolForm : form;
            }

            function customerServiceImageInput(control) {
                var toolForm = customerServiceToolForm(control);

                if (toolForm) {
                    return toolForm.querySelector('[data-customer-service-image]');
                }

                return imageInput || chat.querySelector('[data-customer-service-image]');
            }

            function customerServiceEmojiPanel(control) {
                var toolForm = customerServiceToolForm(control);

                if (toolForm) {
                    return toolForm.querySelector('[data-customer-service-emoji-panel]');
                }

                return emojiPanel || chat.querySelector('[data-customer-service-emoji-panel]');
            }

            function syncCustomerServiceEmojiToggle(panel, expanded) {
                var toolForm = panel && panel.closest ? panel.closest('[data-customer-service-form]') : null;
                var toggles = (toolForm && chat.contains(toolForm) ? toolForm : chat).querySelectorAll('[data-customer-service-emoji-toggle]');

                Array.prototype.forEach.call(toggles, function (button) {
                    button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                });
            }

            function triggerCustomerServiceImagePicker(control) {
                var targetInput = customerServiceImageInput(control);

                if (targetInput && typeof targetInput.click === 'function') {
                    imageInput = targetInput;
                    targetInput.click();
                }
            }

            function toggleCustomerServiceEmojiPanel(control) {
                var panel = customerServiceEmojiPanel(control);
                var expanded;

                if (!panel) {
                    return;
                }

                expanded = !!panel.hidden;
                panel.hidden = !expanded;
                syncCustomerServiceEmojiToggle(panel, expanded);
            }

            function pickCustomerServiceEmoji(control) {
                var panel = closestCustomerServiceTool(control, '[data-customer-service-emoji-panel]') || customerServiceEmojiPanel(control);

                if (panel) {
                    panel.hidden = true;
                    syncCustomerServiceEmojiToggle(panel, false);
                }

                appendInputText(control.getAttribute('data-customer-service-emoji') || '');
            }

            function handleCustomerServiceImageChange(control) {
                var file = control && control.files && control.files[0] ? control.files[0] : null;

                if (!file) {
                    return;
                }

                imageInput = control;
                if (pendingPreview) {
                    setPendingImage(file);
                } else {
                    sendMessage('image', '图片', file, file.name || 'image', 0);
                }
            }

            function handleCustomerServiceToolClick(event) {
                var imageButton = closestCustomerServiceTool(event.target, '[data-customer-service-image-trigger]');
                var emojiButton;
                var emojiItem;

                if (imageButton) {
                    event.preventDefault();
                    triggerCustomerServiceImagePicker(imageButton);
                    return;
                }

                emojiButton = closestCustomerServiceTool(event.target, '[data-customer-service-emoji-toggle]');
                if (emojiButton) {
                    event.preventDefault();
                    toggleCustomerServiceEmojiPanel(emojiButton);
                    return;
                }

                emojiItem = closestCustomerServiceTool(event.target, '[data-customer-service-emoji]');
                if (emojiItem) {
                    event.preventDefault();
                    pickCustomerServiceEmoji(emojiItem);
                }
            }

            function handleCustomerServiceToolChange(event) {
                var changedImageInput = closestCustomerServiceTool(event.target, '[data-customer-service-image]');

                if (changedImageInput) {
                    handleCustomerServiceImageChange(changedImageInput);
                }
            }

            function poll(force) {
                var now = Date.now ? Date.now() : new Date().getTime();
                var pollPayload;
                var hiddenPollBlocked;
                var pollMutationSerial;

                force = !!force;
                hiddenPollBlocked = document.hidden && role !== 'agent';
                if (
                    !enabled
                    || !pollAction
                    || pollBusy
                    || (!force && sendBusy)
                    || hiddenPollBlocked
                    || now < pollRetryAfter
                    || (!force && lastPollStartedAt > 0 && now - lastPollStartedAt < minPollSpacing)
                ) {
                    return;
                }

                lastPollStartedAt = now;
                pollPayload = {
                    session_id: currentSessionId(),
                    status: currentStatus(),
                    read: serviceAgentCanMarkRead(chat) ? '1' : '0'
                };
                if (role === 'agent' && serviceAgentChatPanelIsReadable(chat) && pollPayload.session_id !== '0') {
                    pollPayload.light = '1';
                    pollPayload.slim = '1';
                    if (lightPollSlimCount < 6 && (chat.getAttribute('data-customer-service-sessions-stamp') || '') !== '') {
                        pollPayload.known_message_id = String(latestRenderedCustomerServiceMessageId(chat));
                        pollPayload.known_session_stamp = chat.getAttribute('data-customer-service-sessions-stamp') || '';
                    }
                }

                pollBusy = true;
                pollMutationSerial = messageMutationSerial;
                customerServicePost(chat, pollAction, pollPayload).then(function (payload) {
                    if (pollMutationSerial !== messageMutationSerial) {
                        return;
                    }
                    if (payload.data && payload.data.poll_unchanged) {
                        lightPollSlimCount += 1;
                    } else {
                        lightPollSlimCount = 0;
                    }
                    applyCustomerServicePayload(chat, payload.data, {
                        preserveActiveSession: role === 'agent'
                    });
                    syncCustomerServiceStream();
                    pollFailureCount = 0;
                    pollRetryAfter = 0;
                }).catch(function () {
                    pollFailureCount += 1;
                    pollRetryAfter = now + (pollFailureCount > 1 ? 30000 : 15000);
                }).finally(function () {
                    pollBusy = false;
                });
            }

            function fastPollActive() {
                if (currentSessionId() === '0') {
                    return false;
                }

                if (role === 'agent') {
                    return serviceAgentChatPanelIsReadable(chat);
                }

                return role === 'member';
            }

            function currentPollInterval() {
                return fastPollActive() ? fastPollInterval : pollInterval;
            }

            function stopPollTimer() {
                if (timer) {
                    window.clearTimeout(timer);
                    timer = 0;
                }
                if (pollBootTimer) {
                    window.clearTimeout(pollBootTimer);
                    pollBootTimer = 0;
                }
            }

            function startPollTimer() {
                if (timer) {
                    return;
                }

                timer = window.setTimeout(function tickCustomerServicePoll() {
                    timer = 0;
                    poll();
                    startPollTimer();
                }, currentPollInterval());
            }

            function sendMessage(messageType, content, attachment, attachmentName, duration) {
                var button = form ? form.querySelector('[type="submit"]') : null;
                var restoreText = '';
                var sendSucceeded = false;
                var sentSessionId;
                var payload = {
                    session_id: currentSessionId(),
                    status: currentStatus(),
                    message_type: messageType,
                    content: content || '',
                    duration: duration || 0
                };

                sentSessionId = customerServiceText(payload.session_id || '0');

                if (!sendAction) {
                    return;
                }

                if (sendBusy) {
                    return;
                }

                sendBusy = true;
                messageMutationSerial += 1;

                if (messageType === 'image' || messageType === 'voice') {
                    setCustomerServiceLocalStatus(chat, messageType);
                    setTypingState(true, true, messageType);
                }

                if (button) {
                    button.disabled = true;
                }
                if (input && messageType === 'text') {
                    restoreText = input.value;
                    input.value = '';
                    syncCustomerServiceInputHeight();
                    resetLocalTypingState();
                }

                customerServicePost(chat, sendAction, payload, attachment, attachmentName).then(function (response) {
                    sendSucceeded = true;
                    if (response.message && messageType !== 'text') {
                        toast(response.message, 'success');
                    }
                    if (input && messageType === 'text') {
                        if (shouldBlurCustomerServiceInputAfterSend()) {
                            input.blur();
                        } else {
                            input.focus();
                        }
                    }
                    if (messageType === 'image' || messageType === 'voice') {
                        clearPendingMessage();
                    }
                    resetLocalTypingState();
                    setCustomerServiceLocalStatus(chat, '');
                    applyCustomerServicePayload(chat, response.data, {
                        forceScroll: true,
                        preserveActiveSession: role === 'agent' && customerServicePayloadSessionId(response.data) !== sentSessionId
                    });
                    syncCustomerServiceStream();
                }).catch(function (error) {
                    if (input && messageType === 'text' && !customerServiceText(input.value).trim() && restoreText) {
                        input.value = restoreText;
                        syncCustomerServiceInputHeight();
                    }
                    setTypingState(false, true);
                    setCustomerServiceLocalStatus(chat, '');
                    toast(error.message, 'error');
                }).finally(function () {
                    sendBusy = false;
                    if (button) {
                        button.disabled = false;
                    }
                    if (imageInput) {
                        imageInput.value = '';
                    }
                    if (sendSucceeded && enabled && pollAction && role !== 'agent') {
                        window.setTimeout(poll, 80);
                    }
                    if (sendSucceeded && enabled && pollAction && role === 'agent') {
                        window.setTimeout(function () {
                            poll(true);
                        }, 80);
                    }
                    if (enabled && pollAction && role === 'agent') {
                        startPollTimer();
                    }
                });
            }

            function stopVoiceTracks() {
                if (!mediaStream) {
                    return;
                }

                Array.prototype.forEach.call(mediaStream.getTracks(), function (track) {
                    track.stop();
                });
                mediaStream = null;
            }

            function setVoiceRecording(active) {
                if (!voiceButton) {
                    return;
                }

                voiceButton.classList.toggle('is-recording', !!active);
                voiceButton.setAttribute('aria-pressed', active ? 'true' : 'false');
                voiceButton.innerHTML = active ? '松开' : (voiceButton.getAttribute('data-voice-default-html') || '语音');
                if (voiceButton.tagName.toLowerCase() === 'button') {
                    voiceButton.title = active ? '松开发送语音' : '按住录音';
                }
            }

            function startVoiceRecording() {
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || typeof MediaRecorder === 'undefined') {
                    voicePressing = false;
                    toast('当前浏览器不支持录音。', 'error');
                    return;
                }

                navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
                    mediaStream = stream;
                    mediaChunks = [];
                    mediaRecorder = new MediaRecorder(stream);
                    voiceStartedAt = Date.now();
                    mediaRecorder.addEventListener('dataavailable', function (event) {
                        if (event.data && event.data.size > 0) {
                            mediaChunks.push(event.data);
                        }
                    });
                    mediaRecorder.addEventListener('stop', function () {
                        var mimeType = mediaRecorder && mediaRecorder.mimeType ? mediaRecorder.mimeType : 'audio/webm';
                        var blob = new Blob(mediaChunks, { type: mimeType });
                        var duration = Math.max(1, Math.round((Date.now() - voiceStartedAt) / 1000));

                        setVoiceRecording(false);
                        stopVoiceTracks();

                        if (blob.size <= 0) {
                            setCustomerServiceLocalStatus(chat, '');
                            setTypingState(false, true);
                            toast('录音失败，请重试。', 'error');
                            return;
                        }

                        sendMessage('voice', '语音', blob, 'voice.webm', duration);
                    });
                    mediaRecorder.start();
                    setVoiceRecording(true);
                    setCustomerServiceLocalStatus(chat, 'voice');
                    setTypingState(true, true, 'voice');
                    if (!voicePressing && mediaRecorder && mediaRecorder.state === 'recording') {
                        mediaRecorder.stop();
                    }
                }).catch(function () {
                    voicePressing = false;
                    setVoiceRecording(false);
                    setCustomerServiceLocalStatus(chat, '');
                    setTypingState(false, true);
                    stopVoiceTracks();
                    toast('无法获取麦克风权限。', 'error');
                });
            }

            if (form) {
                form.addEventListener('submit', function (event) {
                    var content = input ? customerServiceText(input.value).trim() : '';

                    event.preventDefault();

                    if (pendingMessage) {
                        sendMessage(
                            pendingMessage.messageType,
                            pendingMessage.content,
                            pendingMessage.attachment,
                            pendingMessage.attachmentName,
                            pendingMessage.duration
                        );
                        return;
                    }

                    if (!content) {
                        toast('请输入消息内容。', 'error');
                        return;
                    }

                    sendMessage('text', content, null, '', 0);
                });
            }

            if (input) {
                syncCustomerServiceInputHeight();
                input.addEventListener('input', updateTypingFromInput);
                input.addEventListener('paste', handleImagePaste);
                input.addEventListener('focus', updateTypingFromInput);
                input.addEventListener('blur', function () {
                    if (typingIdleTimer) {
                        window.clearTimeout(typingIdleTimer);
                        typingIdleTimer = null;
                    }
                    setTypingState(false, true);
                });
            }

            if (emojiToggle && emojiPanel) {
                emojiToggle.setAttribute('aria-expanded', emojiPanel.hidden ? 'false' : 'true');
            }

            chat.addEventListener('click', handleCustomerServiceToolClick);
            chat.addEventListener('change', handleCustomerServiceToolChange);

            if (voiceButton) {
                voiceButton.addEventListener('pointerdown', function (event) {
                    event.preventDefault();

                    if (mediaRecorder && mediaRecorder.state === 'recording') {
                        return;
                    }

                    voicePressing = true;
                    if (voiceButton.setPointerCapture && event.pointerId !== undefined) {
                        try {
                            voiceButton.setPointerCapture(event.pointerId);
                        } catch (captureError) {
                        }
                    }
                    startVoiceRecording();
                });

                voiceButton.addEventListener('pointerup', function (event) {
                    event.preventDefault();
                    voicePressing = false;
                    if (mediaRecorder && mediaRecorder.state === 'recording') {
                        mediaRecorder.stop();
                    }
                });

                voiceButton.addEventListener('pointercancel', function (event) {
                    event.preventDefault();
                    voicePressing = false;
                    if (mediaRecorder && mediaRecorder.state === 'recording') {
                        mediaRecorder.stop();
                    }
                });

                voiceButton.addEventListener('contextmenu', function (event) {
                    event.preventDefault();
                });
            }

            if (clearButton) {
                clearButton.addEventListener('click', function (event) {
                    var clearAction = chat.getAttribute('data-clear-action') || '';
                    var clearConfirm = role === 'agent'
                        ? '确认删除当前客服账号可见的聊天记录吗？后台监督记录不会删除。'
                        : '确认删除当前聊天记录吗？';

                    event.preventDefault();

                    if (!clearAction) {
                        return;
                    }

                    appConfirm(clearConfirm, '清空聊天记录', '确定', '取消').then(function (confirmed) {
                        if (!confirmed) {
                            return;
                        }

                        customerServicePost(chat, clearAction, {
                            session_id: currentSessionId(),
                            status: currentStatus()
                        }).then(function (payload) {
                            if (payload.message) {
                                toast(payload.message, 'success');
                            }
                            applyCustomerServicePayload(chat, payload.data, {
                                forceScroll: true,
                                preserveActiveSession: role === 'agent'
                            });
                        }).catch(function (error) {
                            toast(error.message, 'error');
                        });
                    });
                });
            }

            if (agentForm) {
                agentForm.addEventListener('submit', function (event) {
                    var button = agentForm.querySelector('[type="submit"]');
                    var formData = new FormData(agentForm);
                    var successRedirect = agentForm.getAttribute('data-success-redirect') || '';

                    event.preventDefault();

                    if (button) {
                        button.disabled = true;
                    }

                    fetch(agentForm.getAttribute('action') || './api.php', {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    }).then(function (response) {
                        return toJson(response);
                    }).then(function (payload) {
                        if (!payload.success) {
                            throw new Error(payload.message || '操作失败。');
                        }
                        toast(payload.message || '客服接入人员已保存。', 'success');
                        window.setTimeout(function () {
                            if (payload.redirect || successRedirect) {
                                window.location.href = payload.redirect || successRedirect;
                                return;
                            }
                            window.location.reload();
                        }, 450);
                    }).catch(function (error) {
                        toast(error.message, 'error');
                    }).finally(function () {
                        if (button) {
                            button.disabled = false;
                        }
                    });
                });
            }

            if (enabled && pollAction) {
                startPollTimer();
                syncCustomerServiceStream();
                document.addEventListener('visibilitychange', function () {
                    if (document.hidden) {
                        stopPollTimer();
                        stopCustomerServiceStream();
                        return;
                    }
                    startPollTimer();
                    syncCustomerServiceStream();
                    poll();
                });
                window.addEventListener('pagehide', function () {
                    stopPollTimer();
                    stopCustomerServiceStream();
                });
                window.addEventListener('pageshow', function () {
                    startPollTimer();
                    if (document.hidden) {
                        return;
                    }
                    syncCustomerServiceStream();
                    poll();
                });
                pollBootTimer = window.setTimeout(function () {
                    pollBootTimer = 0;
                    poll();
                    syncCustomerServiceStream();
                }, initialPollDelay);
            }
        });
    }

    function initAdminNavigationDrawer(root) {
        var scope = root || document;
        var body = document.body;
        var toggle = document.querySelector('[data-admin-nav-drawer-toggle]');
        var drawer = document.querySelector('[data-admin-nav-drawer]');
        var closeButton = document.querySelector('[data-admin-nav-drawer-close]');
        var backdrop = document.querySelector('[data-admin-nav-drawer-backdrop]');
        var mobileQuery = window.matchMedia ? window.matchMedia('(max-width: 860px)') : null;

        if (!body || !toggle || !drawer || scope.getAttribute && scope.getAttribute('data-admin-nav-drawer-ready') === '1') {
            return;
        }

        if (body.getAttribute('data-admin-nav-drawer-ready') === '1') {
            return;
        }

        body.setAttribute('data-admin-nav-drawer-ready', '1');

        function isMobileDrawer() {
            return mobileQuery ? mobileQuery.matches : window.innerWidth <= 860;
        }

        function closeDrawer() {
            body.classList.remove('admin-nav-drawer-open');
            toggle.setAttribute('aria-expanded', 'false');
            if (backdrop) {
                backdrop.hidden = true;
            }
        }

        function openDrawer() {
            if (!isMobileDrawer()) {
                return;
            }

            body.classList.add('admin-nav-drawer-open');
            toggle.setAttribute('aria-expanded', 'true');
            if (backdrop) {
                backdrop.hidden = false;
            }
        }

        toggle.addEventListener('click', throttle(function (event) {
            event.preventDefault();

            if (body.classList.contains('admin-nav-drawer-open')) {
                closeDrawer();
                return;
            }

            openDrawer();
        }, 180));

        if (closeButton) {
            closeButton.addEventListener('click', function (event) {
                event.preventDefault();
                closeDrawer();
            });
        }

        if (backdrop) {
            backdrop.addEventListener('click', closeDrawer);
        }

        drawer.addEventListener('click', function (event) {
            if (event.target.closest('a')) {
                closeDrawer();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeDrawer();
            }
        });

        window.addEventListener('resize', debounce(function () {
            if (!isMobileDrawer()) {
                closeDrawer();
            }
        }, 160));
    }

    function initAdminAccountModal(root) {
        var scope = root || document;
        var body = document.body;
        var modal = document.querySelector('[data-admin-account-modal]');
        var openButtons = document.querySelectorAll('[data-admin-account-modal-open]');
        var closeButtons;
        var tabs;
        var editButtons;
        var panelOpenButtons;
        var closeTimer = 0;
        var lastFocus = null;
        var modalOpen = false;

        if (!body || !modal || !openButtons.length) {
            return;
        }

        if (body.getAttribute('data-admin-account-modal-ready') === '1') {
            return;
        }

        body.setAttribute('data-admin-account-modal-ready', '1');
        closeButtons = modal.querySelectorAll('[data-admin-account-modal-close]');
        tabs = modal.querySelectorAll('.admin-account-tabs [data-admin-account-tab]');
        editButtons = modal.querySelectorAll('[data-admin-account-edit]');
        panelOpenButtons = modal.querySelectorAll('[data-admin-account-panel-open]');

        function setExpanded(expanded) {
            Array.prototype.forEach.call(openButtons, function (button) {
                button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            });
        }

        function focusFirstField() {
            var target = modal.querySelector('.admin-account-panel:not([hidden]) input:not([type="hidden"]), .admin-account-panel:not([hidden]) select, .admin-account-panel:not([hidden]) textarea, .admin-account-modal-close');
            if (target && target.focus) {
                target.focus();
            }
        }

        function openModal() {
            if (closeTimer) {
                window.clearTimeout(closeTimer);
                closeTimer = 0;
            }

            lastFocus = document.activeElement;
            modalOpen = true;
            modal.hidden = false;
            setExpanded(true);
            if (modalOpen) {
                modal.classList.add('is-visible');
                focusFirstField();
            }
        }

        function closeModal() {
            modalOpen = false;
            modal.classList.remove('is-visible');
            setExpanded(false);
            closeTimer = window.setTimeout(function () {
                modal.hidden = true;
                closeTimer = 0;
                if (lastFocus && lastFocus.focus) {
                    lastFocus.focus();
                }
            }, 180);
        }

        function activatePanel(name) {
            var tabName = name === 'edit' ? 'manage' : name;

            Array.prototype.forEach.call(tabs, function (tab) {
                var active = tab.getAttribute('data-admin-account-tab') === tabName;
                tab.classList.toggle('is-active', active);
                tab.setAttribute('aria-selected', active ? 'true' : 'false');
            });

            Array.prototype.forEach.call(modal.querySelectorAll('[data-admin-account-panel]'), function (panel) {
                var active = panel.getAttribute('data-admin-account-panel') === name;
                panel.hidden = !active;
                panel.classList.toggle('is-active', active);
            });

            focusFirstField();
        }

        function field(form, name) {
            return form ? form.querySelector('[name="' + name + '"]') : null;
        }

        function setFieldValue(form, name, value) {
            var target = field(form, name);
            if (target) {
                target.value = value == null ? '' : String(value);
            }
        }

        function editAdminFromButton(button) {
            var form = modal.querySelector('[data-admin-account-edit-form]');
            var raw = button.getAttribute('data-admin-account-json') || '{}';
            var payload;

            if (!form) {
                return;
            }

            try {
                payload = JSON.parse(raw);
            } catch (error) {
                payload = {};
            }

            setFormError(form, '');
            setFieldValue(form, 'id', payload.id || 0);
            setFieldValue(form, 'username', payload.username || '');
            setFieldValue(form, 'password', '');
            setFieldValue(form, 'real_name', payload.real_name || '');
            setFieldValue(form, 'nickname', payload.nickname || '');
            setFieldValue(form, 'mobile', payload.mobile || '');
            setFieldValue(form, 'email', payload.email || '');
            setFieldValue(form, 'role_id', payload.role_id || 0);
            setFieldValue(form, 'status', payload.status == null ? 1 : payload.status);
            setFieldValue(form, 'remark', payload.remark || '');
            activatePanel('edit');
        }

        Array.prototype.forEach.call(openButtons, function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                openModal();
            });
        });

        Array.prototype.forEach.call(closeButtons, function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                closeModal();
            });
        });

        Array.prototype.forEach.call(tabs, function (tab) {
            tab.addEventListener('click', function (event) {
                event.preventDefault();
                activatePanel(tab.getAttribute('data-admin-account-tab') || 'current');
            });
        });

        Array.prototype.forEach.call(panelOpenButtons, function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                activatePanel(button.getAttribute('data-admin-account-panel-open') || 'manage');
            });
        });

        Array.prototype.forEach.call(editButtons, function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                editAdminFromButton(button);
            });
        });

        modal.addEventListener('click', function (event) {
            if (event.target && event.target.matches('[data-admin-account-modal-close]')) {
                closeModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !modal.hidden) {
                closeModal();
            }
        });
    }

    function initAdminSettingsComposer(root) {
        var scope = root || document;
        var forms = scope.querySelectorAll('[data-admin-settings-composer]');

        Array.prototype.forEach.call(forms, function (form) {
            var previews;
            var syncPreviewSoon;

            if (form.getAttribute('data-admin-settings-composer-ready') === '1') {
                return;
            }

            form.setAttribute('data-admin-settings-composer-ready', '1');
            previews = {
                siteName: form.querySelector('[data-settings-preview="siteName"]'),
                frontTitle: form.querySelector('[data-settings-preview="frontTitle"]'),
                macauTitle: form.querySelector('[data-settings-preview="macauTitle"]'),
                hongkongTitle: form.querySelector('[data-settings-preview="hongkongTitle"]'),
                adminTitle: form.querySelector('[data-settings-preview="adminTitle"]'),
                adminName: form.querySelector('[data-settings-preview="adminName"]')
            };

            function fieldValue(name, fallback) {
                var field = form.querySelector('[name="' + name + '"]');
                var value = field ? String(field.value || '').trim() : '';

                return value || fallback;
            }

            function setPreview(key, value) {
                if (previews[key]) {
                    previews[key].textContent = value;
                }
            }

            function syncPreview() {
                var siteName = fieldValue('site_name', '站点名称');
                var siteTitle = fieldValue('site_title', siteName);
                var macauTitle = fieldValue('browser_region_title_macau', '澳门区域标题');
                var hongkongTitle = fieldValue('browser_region_title_hongkong', '香港区域标题');
                var adminBrowserTitle = fieldValue('admin_browser_title', '后台浏览器标题');
                var adminName = fieldValue('admin_management_name', '后台管理名称');

                setPreview('siteName', siteName);
                setPreview('frontTitle', siteTitle);
                setPreview('macauTitle', siteTitle + ' - ' + macauTitle);
                setPreview('hongkongTitle', siteTitle + ' - ' + hongkongTitle);
                setPreview('adminTitle', '系统设置 - ' + adminBrowserTitle);
                setPreview('adminName', adminName);
            }

            syncPreviewSoon = debounce(syncPreview, 80);
            form.addEventListener('input', syncPreviewSoon);
            form.addEventListener('change', syncPreviewSoon);
            form.addEventListener('reset', function () {
                window.setTimeout(syncPreview, 0);
            });
            syncPreview();
        });
    }

    function initAdminNavigationFeedback(root) {
        var scope = root || document;
        var body = document.body;
        var links;

        if (!body
            || !body.classList
            || !body.classList.contains('admin-body')
            || body.getAttribute('data-admin-nav-feedback-ready') === '1') {
            return;
        }

        body.setAttribute('data-admin-nav-feedback-ready', '1');
        links = scope.querySelectorAll ? scope.querySelectorAll('.admin-nav-link[href]') : [];

        Array.prototype.forEach.call(links, function (link) {
            link.addEventListener('click', function (event) {
                if (event.defaultPrevented
                    || link.target
                    || link.hasAttribute('download')
                    || link.classList.contains('is-active')) {
                    return;
                }

                Array.prototype.forEach.call(links, function (item) {
                    item.classList.remove('is-active');
                    item.removeAttribute('aria-current');
                    item.removeAttribute('aria-busy');
                });

                link.classList.add('is-active');
                link.setAttribute('aria-current', 'page');
                link.setAttribute('aria-busy', 'true');
            });
        });
    }

    function initAdminForecastPricing(root) {
        var scope = root || document;
        var forms = scope.querySelectorAll('[data-forecast-pricing-form]');

        function formatPoints(value) {
            var number = Math.max(0, Number(value) || 0);
            var fixed = Math.round(number * 100) / 100;

            if (Math.abs(fixed - Math.round(fixed)) < 0.001) {
                return String(Math.round(fixed));
            }

            return fixed.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
        }

        Array.prototype.forEach.call(forms, function (form) {
            var preview = form.querySelector('[data-forecast-pricing-preview]');
            var syncPreviewSoon;

            if (form.getAttribute('data-forecast-pricing-ready') === '1') {
                return;
            }

            form.setAttribute('data-forecast-pricing-ready', '1');

            function discountFor(count) {
                var input = form.querySelector('[data-forecast-discount="' + count + '"]');
                var value = input ? Math.max(1, Math.min(100, Number(input.value) || 100)) : 100;

                return count <= 1 ? 100 : value;
            }

            function appendText(parent, className, text) {
                var span = document.createElement('span');
                span.className = className;
                span.textContent = text;
                parent.appendChild(span);
                return span;
            }

            function syncPreview() {
                var panels = form.querySelectorAll('.forecast-option-panel');
                var items = [];
                var discount;
                var total = 0;
                var strong;

                if (!preview) {
                    return;
                }

                Array.prototype.forEach.call(panels, function (panel) {
                    var rows = panel.querySelectorAll('.forecast-option-row');
                    var picked = null;

                    Array.prototype.forEach.call(rows, function (row) {
                        var enabled = row.querySelector('[data-forecast-option-enabled]');
                        var label = row.querySelector('[data-forecast-option-label]');
                        var price = row.querySelector('[data-forecast-price-input]');

                        if (!picked && enabled && enabled.checked) {
                            picked = {
                                label: label ? String(label.value || '').trim() : '选项',
                                price: price ? Math.max(0, Number(price.value) || 0) : 0
                            };
                        }
                    });

                    if (picked) {
                        items.push(picked);
                    }
                });

                preview.innerHTML = '';
                if (!items.length) {
                    appendText(preview, '', '选择类型后显示价格');
                    return;
                }

                discount = discountFor(items.length);
                Array.prototype.forEach.call(items, function (item, index) {
                    var itemNode;
                    var discountedPrice = Math.round(item.price * discount) / 100;
                    total += discountedPrice;

                    if (index > 0) {
                        appendText(preview, 'forecast-preview-plus', '+');
                    }

                    itemNode = appendText(preview, 'forecast-preview-item', item.label || '选项');
                    strong = document.createElement('b');
                    strong.textContent = formatPoints(discountedPrice) + '积分';
                    itemNode.appendChild(strong);
                });

                appendText(preview, 'forecast-preview-equal', '=');
                strong = document.createElement('strong');
                strong.textContent = formatPoints(total) + '积分';
                preview.appendChild(strong);
                if (items.length > 1) {
                    appendText(preview, 'forecast-preview-discount', discount + '%');
                }
            }

            syncPreviewSoon = debounce(syncPreview, 80);
            form.addEventListener('input', syncPreviewSoon);
            form.addEventListener('change', syncPreviewSoon);
            form.addEventListener('reset', function () {
                window.setTimeout(syncPreview, 0);
            });
            syncPreview();
        });
    }

    function initFrontForecastPricing(root) {
        var scope = root || document;
        var blocks = scope.querySelectorAll('[data-front-forecast-pricing]');

        function formatPoints(value) {
            var number = Math.max(0, Number(value) || 0);
            var fixed = Math.round(number * 100) / 100;

            if (Math.abs(fixed - Math.round(fixed)) < 0.001) {
                return String(Math.round(fixed));
            }

            return fixed.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
        }

        function foldLabel(percent) {
            var fold = Math.max(1, Math.min(100, Number(percent) || 100)) / 10;

            return formatPoints(fold) + '折';
        }

        Array.prototype.forEach.call(blocks, function (block) {
            var shell = block.closest('.forecast-card-primary') || document;
            var selects = shell.querySelectorAll('[data-forecast-price-select]');
            var strip = block.querySelector('[data-forecast-price-strip]');
            var totalNode = block.querySelector('[data-forecast-price-total]');
            var discountNode = block.querySelector('[data-forecast-price-discount]');
            var discounts = {};
            var agentFree = block.getAttribute('data-forecast-agent-free') === '1';
            var syncPricingSoon;

            if (block.getAttribute('data-front-forecast-pricing-ready') === '1') {
                return;
            }

            block.setAttribute('data-front-forecast-pricing-ready', '1');

            try {
                discounts = JSON.parse(block.getAttribute('data-forecast-discounts') || '{}') || {};
            } catch (error) {
                discounts = {};
            }

            function discountFor(count) {
                var value = Math.max(1, Math.min(100, Number(discounts[String(count)]) || 100));

                return count <= 1 ? 100 : value;
            }

            function syncPricing() {
                var items = [];
                var discount;
                var total = 0;

                Array.prototype.forEach.call(selects, function (select) {
                    var option = select.options[select.selectedIndex];
                    var price;

                    if (!option || !select.value) {
                        return;
                    }

                    price = Math.max(0, Number(option.getAttribute('data-forecast-price')) || 0);
                    items.push({
                        price: price
                    });
                });

                if (!strip || !totalNode || !discountNode) {
                    return;
                }

                if (!items.length) {
                    strip.setAttribute('hidden', 'hidden');
                    return;
                }

                discount = discountFor(items.length);
                Array.prototype.forEach.call(items, function (item) {
                    total += Math.round(item.price * discount) / 100;
                });

                strip.removeAttribute('hidden');
                if (agentFree) {
                    totalNode.textContent = '免积分';
                    discountNode.setAttribute('hidden', 'hidden');
                    return;
                }

                totalNode.textContent = formatPoints(total) + '积分';

                if (items.length > 1) {
                    discountNode.textContent = foldLabel(discount);
                    discountNode.removeAttribute('hidden');
                } else {
                    discountNode.setAttribute('hidden', 'hidden');
                }
            }

            syncPricingSoon = debounce(syncPricing, 60);
            shell.addEventListener('change', function (event) {
                if (event.target.closest('[data-forecast-price-select]')) {
                    syncPricingSoon();
                }
            });

            syncPricing();
        });
    }

    function runCustomerServiceWhenIdle(callback, delay) {
        window.setTimeout(function () {
            if (typeof window.requestIdleCallback === 'function') {
                window.requestIdleCallback(callback, { timeout: 1200 });
                return;
            }

            callback();
        }, delay || 0);
    }

    function scheduleCustomerServiceUi(root) {
        var scope = root || document;
        var hasChat = !!scope.querySelector('[data-customer-service]');
        var hasUnreadPoll = !!scope.querySelector('[data-customer-service-unread-poll]');
        var isAdmin = !!(document.body && document.body.classList && document.body.classList.contains('admin-body'));
        var delay = isAdmin ? 700 : 900;
        var runUnreadPoll;

        if (!hasChat && !hasUnreadPoll) {
            return;
        }

        if (hasChat) {
            initCustomerServiceChats(root);
            initCustomerServiceUnreadPoll(root);
            return;
        }

        runUnreadPoll = function () {
            initCustomerServiceUnreadPoll(root);
        };

        if (document.readyState === 'complete') {
            runCustomerServiceWhenIdle(runUnreadPoll, delay);
            return;
        }

        window.addEventListener('load', function () {
            runCustomerServiceWhenIdle(runUnreadPoll, delay);
        }, { once: true });
    }

    var uploadImageDefaultMaxSize = 5 * 1024 * 1024;

    function uploadImageCanCompress(file) {
        var type = String(file && file.type ? file.type : '').toLowerCase();
        var name = String(file && file.name ? file.name : '').toLowerCase();

        if (type === 'image/gif' || /\.gif$/.test(name)) {
            return false;
        }

        return type === 'image/jpeg'
            || type === 'image/jpg'
            || type === 'image/png'
            || type === 'image/webp'
            || type === 'image/bmp'
            || type === 'image/x-ms-bmp'
            || type === '';
    }

    function uploadImageLoad(file) {
        return new Promise(function (resolve, reject) {
            var image = new Image();
            var url = '';
            var reader = null;

            image.onload = function () {
                if (url && window.URL && window.URL.revokeObjectURL) {
                    window.URL.revokeObjectURL(url);
                }
                resolve(image);
            };
            image.onerror = function () {
                if (url && window.URL && window.URL.revokeObjectURL) {
                    window.URL.revokeObjectURL(url);
                }
                reject(new Error('图片读取失败，请重新选择后再上传。'));
            };

            if (window.URL && window.URL.createObjectURL) {
                url = window.URL.createObjectURL(file);
                image.src = url;
                return;
            }

            if (typeof FileReader !== 'function') {
                reject(new Error('当前浏览器不支持自动压缩，请压缩后再上传。'));
                return;
            }

            reader = new FileReader();
            reader.onload = function () {
                image.src = String(reader.result || '');
            };
            reader.onerror = function () {
                reject(new Error('图片读取失败，请重新选择后再上传。'));
            };
            reader.readAsDataURL(file);
        });
    }

    function uploadImageCanvasBlob(canvas, mimeType, quality) {
        return new Promise(function (resolve, reject) {
            var dataUrl;
            var parts;
            var binary;
            var bytes;
            var index;

            if (canvas.toBlob) {
                canvas.toBlob(function (blob) {
                    if (blob) {
                        resolve(blob);
                        return;
                    }
                    reject(new Error('图片压缩失败，请重新选择后再上传。'));
                }, mimeType, quality);
                return;
            }

            try {
                dataUrl = canvas.toDataURL(mimeType, quality);
                parts = dataUrl.split(',');
                binary = window.atob(parts[1] || '');
                bytes = new Uint8Array(binary.length);
                for (index = 0; index < binary.length; index += 1) {
                    bytes[index] = binary.charCodeAt(index);
                }
                resolve(new Blob([bytes], { type: mimeType }));
            } catch (error) {
                reject(new Error('图片压缩失败，请重新选择后再上传。'));
            }
        });
    }

    function uploadImageCompressedName(name, suffix) {
        name = String(name || 'upload-image').replace(/\.[^.]*$/, '');
        if (!name) {
            name = 'upload-image';
        }

        return name + (suffix || '-compressed') + '.jpg';
    }

    function uploadImageBlobToFile(blob, originalFile, suffix) {
        var fileName = uploadImageCompressedName(originalFile && originalFile.name, suffix);

        if (typeof File === 'function') {
            return new File([blob], fileName, {
                type: 'image/jpeg',
                lastModified: Date.now()
            });
        }

        blob.name = fileName;
        blob.lastModified = Date.now();
        return blob;
    }

    function compressImageForUpload(file, options) {
        var settings = options || {};
        var maxWidth = Math.max(320, parseInt(settings.maxWidth || '1920', 10) || 1920);
        var maxHeight = Math.max(320, parseInt(settings.maxHeight || '1920', 10) || 1920);
        var quality = Math.max(0.55, Math.min(0.92, Number(settings.quality || 0.82)));
        var directSize = Math.max(0, parseInt(settings.directSize || String(900 * 1024), 10) || 0);
        var targetSize = Math.max(0, parseInt(settings.targetSize || String(uploadImageDefaultMaxSize), 10) || uploadImageDefaultMaxSize);
        var outputSuffix = settings.outputSuffix || '-compressed';
        var fillWhite = settings.fillWhite !== false;

        if (!file || !uploadImageCanCompress(file) || !document.createElement('canvas').getContext) {
            return Promise.resolve(file);
        }

        return uploadImageLoad(file).then(function (image) {
            var sourceWidth = image.naturalWidth || image.width || 0;
            var sourceHeight = image.naturalHeight || image.height || 0;
            var ratio;
            var targetWidth;
            var targetHeight;
            var canvas;
            var context;

            if (!sourceWidth || !sourceHeight) {
                return file;
            }

            ratio = Math.min(1, maxWidth / sourceWidth, maxHeight / sourceHeight);
            if (ratio >= 1 && (!file.size || file.size <= directSize)) {
                return file;
            }

            targetWidth = Math.max(1, Math.round(sourceWidth * ratio));
            targetHeight = Math.max(1, Math.round(sourceHeight * ratio));
            canvas = document.createElement('canvas');
            canvas.width = targetWidth;
            canvas.height = targetHeight;
            context = canvas.getContext('2d');
            if (!context) {
                return file;
            }

            if (fillWhite) {
                context.fillStyle = '#ffffff';
                context.fillRect(0, 0, targetWidth, targetHeight);
            }
            context.drawImage(image, 0, 0, targetWidth, targetHeight);

            return uploadImageCanvasBlob(canvas, 'image/jpeg', quality).then(function (blob) {
                if (!blob || blob.size <= 0 || (file.size && blob.size >= file.size)) {
                    return file;
                }
                if (targetSize > 0 && blob.size > targetSize && ratio > 0.24) {
                    return compressImageForUpload(file, {
                        maxWidth: Math.max(320, Math.floor(targetWidth * 0.82)),
                        maxHeight: Math.max(320, Math.floor(targetHeight * 0.82)),
                        quality: Math.max(0.62, quality - 0.08),
                        directSize: 0,
                        targetSize: targetSize,
                        outputSuffix: outputSuffix,
                        fillWhite: fillWhite
                    });
                }

                return uploadImageBlobToFile(blob, file, outputSuffix);
            });
        }).catch(function () {
            return file;
        });
    }

    function replaceInputFile(input, file, maxSize) {
        var transfer = null;

        try {
            if (typeof DataTransfer === 'function') {
                transfer = new DataTransfer();
            } else if (typeof ClipboardEvent === 'function') {
                transfer = new ClipboardEvent('').clipboardData;
            }
        } catch (error) {
            transfer = null;
        }

        if (!transfer || !transfer.items || !transfer.items.add) {
            return false;
        }

        try {
            transfer.items.add(file);
            input.files = transfer.files;
        } catch (error) {
            return false;
        }

        return !!(input.files && input.files.length && (!maxSize || input.files[0].size <= maxSize));
    }

    function initAdminUploadCompressForms(root) {
        var scope = root || document;
        var forms = scope.querySelectorAll('[data-admin-upload-compress]');

        Array.prototype.forEach.call(forms, function (form) {
            if (form.getAttribute('data-admin-upload-compress-ready') === '1') {
                return;
            }
            form.setAttribute('data-admin-upload-compress-ready', '1');

            form.addEventListener('submit', function (event) {
                var input;
                var file;
                var submitter;
                var maxSize = uploadImageDefaultMaxSize;

                if (form.getAttribute('data-admin-upload-compressed-submit') === '1') {
                    form.removeAttribute('data-admin-upload-compressed-submit');
                    return;
                }

                input = form.querySelector('input[type="file"][name="upload_file"]');
                file = input && input.files && input.files.length ? input.files[0] : null;
                if (!file || !uploadImageCanCompress(file)) {
                    return;
                }
                if (file.size && file.size <= 900 * 1024) {
                    return;
                }

                event.preventDefault();
                if (form.getAttribute('data-admin-upload-compressing') === '1') {
                    return;
                }

                submitter = event.submitter || document.activeElement;
                form.setAttribute('data-admin-upload-compressing', '1');
                if (submitter && submitter.form === form) {
                    submitter.setAttribute('aria-busy', 'true');
                }
                toast('图片较大，正在自动压缩后上传。', 'info');

                compressImageForUpload(file, {
                    maxWidth: 1920,
                    maxHeight: 1920,
                    quality: 0.82,
                    directSize: 900 * 1024,
                    targetSize: maxSize,
                    outputSuffix: '-upload'
                }).then(function (preparedFile) {
                    if (preparedFile && preparedFile !== file) {
                        replaceInputFile(input, preparedFile, maxSize);
                    }

                    form.removeAttribute('data-admin-upload-compressing');
                    if (submitter && submitter.form === form) {
                        submitter.removeAttribute('aria-busy');
                    }
                    form.setAttribute('data-admin-upload-compressed-submit', '1');
                    if (typeof form.requestSubmit === 'function') {
                        if (submitter && submitter.form === form) {
                            form.requestSubmit(submitter);
                        } else {
                            form.requestSubmit();
                        }
                    } else {
                        form.submit();
                    }
                }).catch(function () {
                    form.removeAttribute('data-admin-upload-compressing');
                    if (submitter && submitter.form === form) {
                        submitter.removeAttribute('aria-busy');
                    }
                    if (file.size <= maxSize) {
                        form.setAttribute('data-admin-upload-compressed-submit', '1');
                        form.submit();
                        return;
                    }
                    toast('图片压缩失败，请压缩到 5MB 以内后再上传。', 'error');
                });
            });
        });
    }
    var serviceAgentPaymentMaxUploadSize = 5 * 1024 * 1024;
    var serviceAgentPaymentCompressTriggerSize = Math.floor(4.6 * 1024 * 1024);
    var serviceAgentPaymentTargetUploadSize = Math.floor(3.8 * 1024 * 1024);

    function serviceAgentPaymentUploadInput(form) {
        var inputs;
        var index;
        var input;

        if (!form || !form.querySelectorAll) {
            return null;
        }

        inputs = form.querySelectorAll('input[type="file"]');
        for (index = 0; index < inputs.length; index += 1) {
            input = inputs[index];
            if (String(input.name || '').indexOf('payment_qr_') === 0) {
                return input;
            }
        }

        return null;
    }

    function serviceAgentPaymentActiveSubmitter(form, event) {
        var active = document.activeElement;

        if (event && event.submitter && event.submitter.form === form) {
            return event.submitter;
        }

        if (form && form._serviceAgentPaymentSubmitter && form._serviceAgentPaymentSubmitter.form === form) {
            return form._serviceAgentPaymentSubmitter;
        }

        if (active && active.form === form) {
            return active;
        }

        return null;
    }

    function serviceAgentPaymentIsDeleteSubmitter(submitter) {
        return !!(submitter && String(submitter.name || '') === 'payment_qr_delete');
    }

    function serviceAgentPaymentCanCompress(file) {
        var type = String(file && file.type ? file.type : '').toLowerCase();

        return type === 'image/jpeg'
            || type === 'image/jpg'
            || type === 'image/png'
            || type === 'image/webp'
            || type === 'image/bmp'
            || type === 'image/x-ms-bmp'
            || type === '';
    }

    function serviceAgentPaymentLoadImage(file) {
        return new Promise(function (resolve, reject) {
            var image;
            var url;
            var reader;

            image = new Image();
            image.onload = function () {
                if (url && window.URL && window.URL.revokeObjectURL) {
                    window.URL.revokeObjectURL(url);
                }
                resolve(image);
            };
            image.onerror = function () {
                if (url && window.URL && window.URL.revokeObjectURL) {
                    window.URL.revokeObjectURL(url);
                }
                reject(new Error('图片读取失败，请重新选择后再上传。'));
            };

            if (window.URL && window.URL.createObjectURL) {
                url = window.URL.createObjectURL(file);
                image.src = url;
                return;
            }

            if (typeof FileReader !== 'function') {
                reject(new Error('当前浏览器不支持自动压缩，请压缩到 5MB 以内后再上传。'));
                return;
            }

            reader = new FileReader();
            reader.onload = function () {
                image.src = String(reader.result || '');
            };
            reader.onerror = function () {
                reject(new Error('图片读取失败，请重新选择后再上传。'));
            };
            reader.readAsDataURL(file);
        });
    }

    function serviceAgentPaymentCanvasBlob(canvas, mimeType, quality) {
        return new Promise(function (resolve, reject) {
            var dataUrl;
            var parts;
            var binary;
            var index;
            var bytes;

            if (canvas.toBlob) {
                canvas.toBlob(function (blob) {
                    if (blob) {
                        resolve(blob);
                        return;
                    }
                    reject(new Error('图片压缩失败，请重新选择后再上传。'));
                }, mimeType, quality);
                return;
            }

            try {
                dataUrl = canvas.toDataURL(mimeType, quality);
                parts = dataUrl.split(',');
                binary = window.atob(parts[1] || '');
                bytes = new Uint8Array(binary.length);
                for (index = 0; index < binary.length; index += 1) {
                    bytes[index] = binary.charCodeAt(index);
                }
                resolve(new Blob([bytes], { type: mimeType }));
            } catch (error) {
                reject(new Error('图片压缩失败，请重新选择后再上传。'));
            }
        });
    }

    function serviceAgentPaymentCompressedName(name) {
        name = String(name || 'payment-qr').replace(/\.[^.]*$/, '');
        if (!name) {
            name = 'payment-qr';
        }

        return name + '-compressed.jpg';
    }

    function serviceAgentPaymentBlobToFile(blob, originalFile) {
        var fileName = serviceAgentPaymentCompressedName(originalFile && originalFile.name);

        if (typeof File === 'function') {
            return new File([blob], fileName, {
                type: 'image/jpeg',
                lastModified: Date.now()
            });
        }

        blob.name = fileName;
        blob.lastModified = Date.now();
        return blob;
    }

    function serviceAgentPaymentCompressImage(file) {
        return serviceAgentPaymentLoadImage(file).then(function (image) {
            var sourceWidth = image.naturalWidth || image.width || 0;
            var sourceHeight = image.naturalHeight || image.height || 0;
            var maxSide = 1400;
            var scale;
            var quality = 0.88;
            var attempt = 0;
            var canvas = document.createElement('canvas');
            var context = canvas.getContext ? canvas.getContext('2d') : null;

            if (!sourceWidth || !sourceHeight || !context) {
                throw new Error('图片压缩失败，请重新选择后再上传。');
            }

            scale = Math.min(1, maxSide / Math.max(sourceWidth, sourceHeight));
            scale = Math.min(scale, Math.sqrt(serviceAgentPaymentTargetUploadSize / Math.max(file.size || 1, 1)));
            scale = Math.max(0.16, scale);

            function renderNext() {
                var width = Math.max(1, Math.round(sourceWidth * scale));
                var height = Math.max(1, Math.round(sourceHeight * scale));

                canvas.width = width;
                canvas.height = height;
                context.clearRect(0, 0, width, height);
                context.fillStyle = '#ffffff';
                context.fillRect(0, 0, width, height);
                context.drawImage(image, 0, 0, width, height);

                return serviceAgentPaymentCanvasBlob(canvas, 'image/jpeg', quality).then(function (blob) {
                    attempt += 1;
                    if (blob.size > 0 && blob.size <= serviceAgentPaymentTargetUploadSize) {
                        return serviceAgentPaymentBlobToFile(blob, file);
                    }
                    if (attempt >= 9) {
                        if (blob.size > 0 && blob.size <= serviceAgentPaymentMaxUploadSize) {
                            return serviceAgentPaymentBlobToFile(blob, file);
                        }
                        throw new Error('图片仍然超过 5MB，请换一张更清晰但更小的二维码图片。');
                    }

                    if (quality > 0.62) {
                        quality -= 0.08;
                    } else {
                        scale = Math.max(0.16, scale * 0.82);
                    }

                    return renderNext();
                });
            }

            return renderNext();
        });
    }

    function serviceAgentPaymentReplaceInputFile(input, file) {
        var transfer = null;

        try {
            if (typeof DataTransfer === 'function') {
                transfer = new DataTransfer();
            } else if (typeof ClipboardEvent === 'function') {
                transfer = new ClipboardEvent('').clipboardData;
            }
        } catch (error) {
            transfer = null;
        }

        if (!transfer || !transfer.items || !transfer.items.add) {
            return false;
        }

        try {
            transfer.items.add(file);
            input.files = transfer.files;
        } catch (error) {
            return false;
        }

        return !!(input.files && input.files.length && input.files[0].size <= serviceAgentPaymentMaxUploadSize);
    }

    function submitServiceAgentPaymentBypassCompression(form, submitter) {
        var hiddenSubmitter;

        form.setAttribute('data-service-agent-payment-compressed-submit', '1');
        if (typeof form.requestSubmit === 'function') {
            if (submitter && submitter.form === form) {
                form.requestSubmit(submitter);
            } else {
                form.requestSubmit();
            }
            return;
        }

        if (submitter && submitter.name) {
            hiddenSubmitter = document.createElement('input');
            hiddenSubmitter.type = 'hidden';
            hiddenSubmitter.name = submitter.name;
            hiddenSubmitter.value = submitter.value;
            form.appendChild(hiddenSubmitter);
        }
        form.submit();
    }

    function initServiceAgentPaymentUploadForms(root) {
        var scope = root || document;
        var forms = scope.querySelectorAll('[data-service-agent-payment-upload-form]');

        Array.prototype.forEach.call(forms, function (form) {
            if (form.getAttribute('data-service-agent-payment-upload-ready') === '1') {
                return;
            }
            form.setAttribute('data-service-agent-payment-upload-ready', '1');

            form.addEventListener('click', function (event) {
                var submitter = event.target && event.target.closest ? event.target.closest('button, input[type="submit"]') : null;
                if (submitter && submitter.form === form) {
                    form._serviceAgentPaymentSubmitter = submitter;
                }
            }, true);

            form.addEventListener('submit', function (event) {
                var input;
                var file;
                var submitter;

                if (form.getAttribute('data-service-agent-payment-compressed-submit') === '1') {
                    form.removeAttribute('data-service-agent-payment-compressed-submit');
                    return;
                }

                submitter = serviceAgentPaymentActiveSubmitter(form, event);
                if (serviceAgentPaymentIsDeleteSubmitter(submitter)) {
                    return;
                }

                input = serviceAgentPaymentUploadInput(form);
                file = input && input.files && input.files.length ? input.files[0] : null;
                if (!file || file.size <= serviceAgentPaymentCompressTriggerSize) {
                    return;
                }

                if (!serviceAgentPaymentCanCompress(file)) {
                    if (file.size <= serviceAgentPaymentMaxUploadSize) {
                        return;
                    }

                    event.preventDefault();
                    toast('该图片格式过大且不支持自动压缩，请压缩到 5MB 以内后再上传。', 'error');
                    return;
                }

                event.preventDefault();
                if (form.getAttribute('data-service-agent-payment-compressing') === '1') {
                    return;
                }

                form.setAttribute('data-service-agent-payment-compressing', '1');
                if (submitter) {
                    submitter.setAttribute('aria-busy', 'true');
                }
                toast('图片较大，正在自动压缩后上传。', 'info');

                serviceAgentPaymentCompressImage(file).then(function (compressedFile) {
                    if (!serviceAgentPaymentReplaceInputFile(input, compressedFile) && file.size > serviceAgentPaymentMaxUploadSize) {
                        throw new Error('当前浏览器不支持自动压缩提交，请压缩到 5MB 以内后再上传。');
                    }

                    form.removeAttribute('data-service-agent-payment-compressing');
                    if (submitter) {
                        submitter.removeAttribute('aria-busy');
                    }

                    submitServiceAgentPaymentBypassCompression(form, submitter);
                }).catch(function (error) {
                    form.removeAttribute('data-service-agent-payment-compressing');
                    if (submitter) {
                        submitter.removeAttribute('aria-busy');
                    }

                    if (file && file.size <= serviceAgentPaymentMaxUploadSize) {
                        submitServiceAgentPaymentBypassCompression(form, submitter);
                        return;
                    }

                    toast(error && error.message ? error.message : '图片压缩失败，请压缩到 5MB 以内后再上传。', 'error');
                });
            });
        });
    }

    function submitServiceAgentPaymentDelete(deleteButton) {
        var sourceForm = deleteButton ? deleteButton.form : null;
        var cleanForm;
        var fieldNames = ['_service_action', '_token', 'region', 'agent', 'panel', 'payment_type'];

        if (!sourceForm) {
            return;
        }

        cleanForm = document.createElement('form');
        cleanForm.method = sourceForm.method || 'post';
        cleanForm.action = sourceForm.action || window.location.href;
        cleanForm.style.display = 'none';

        fieldNames.forEach(function (name) {
            var source = sourceForm.querySelector('input[type="hidden"][name="' + name + '"]');
            var field;

            if (!source) {
                return;
            }

            field = document.createElement('input');
            field.type = 'hidden';
            field.name = name;
            field.value = source.value;
            cleanForm.appendChild(field);
        });

        if (deleteButton.name) {
            var deleteField = document.createElement('input');
            deleteField.type = 'hidden';
            deleteField.name = deleteButton.name;
            deleteField.value = deleteButton.value;
            cleanForm.appendChild(deleteField);
        }

        document.body.appendChild(cleanForm);
        cleanForm.submit();
    }

    function bootCommonUi(root) {
        var isAdminBody = !!(document.body
            && document.body.classList
            && document.body.classList.contains('admin-body'));

        initJsonCharts(root);
        initPasswordToggles(root);
        initInstallProgressForms(root);

        if (isAdminBody) {
            initAdminNavigationDrawer(root);
            initAdminAccountModal(root);
            initAdminNavigationFeedback(root);
            initAdminSettingsComposer(root);
            initAdminForecastPricing(root);
            scheduleCustomerServiceUi(root);
            initAdminUploadCompressForms(root);
            initNoticeSeeds(root);
            return;
        }

        initFrontForecastPricing(root);
        reloadStaleCustomerServiceAuthPage();
        scheduleCustomerServiceUi(root);
        initMemberPredictionDeleteForms(root);
        initServiceAgentPaymentUploadForms(root);
        initNoticeSeeds(root);
        initPagePrefetch(root);
        initMobileFrontGestures(root);
    }

    function isMobileFrontGestureDevice() {
        if (window.matchMedia && window.matchMedia('(hover: none) and (pointer: coarse)').matches) {
            return true;
        }

        return ('ontouchstart' in window) || (navigator.maxTouchPoints && navigator.maxTouchPoints > 0);
    }

    function frontGestureHasNavSurface() {
        return !!(
            document.querySelector('.bottom-float-nav')
            || (document.body && document.body.classList && document.body.classList.contains('standalone-modal-post'))
        );
    }

    function isFrontGestureInteractiveTarget(target) {
        if (!target || !target.closest) {
            return true;
        }

        return !!target.closest([
            'a[href]',
            'button',
            'input',
            'select',
            'textarea',
            'label',
            '[role="button"]',
            '[contenteditable="true"]',
            '.tox',
            '.tox-tinymce',
            '.front-post-modal-composer-card',
            '.service-thread-input',
            '.service-thread-composer',
            '.customer-service-input',
            '.customer-service-compose',
            '[data-member-recharge-modal]',
            '[data-member-edit-modal]',
            '[data-front-post-login-modal]',
            '[data-front-post-customer-service-edit-modal]'
        ].join(','));
    }

    function isFrontGestureHorizontalScroller(target) {
        var node = target;
        var depth = 0;
        var style;
        var overflowX;

        while (node && node !== document.body && depth < 7) {
            if (node.nodeType === 1 && node.scrollWidth > node.clientWidth + 8) {
                style = window.getComputedStyle ? window.getComputedStyle(node) : null;
                overflowX = style ? String(style.overflowX || '') : '';
                if (overflowX === 'auto' || overflowX === 'scroll') {
                    return true;
                }
            }

            node = node.parentNode;
            depth += 1;
        }

        return false;
    }

    function isFrontGestureBlockingModalOpen() {
        var selectors = [
            '#app-notice-modal:not([hidden])',
            '#app-confirm-modal:not([hidden])',
            '#app-prompt-modal:not([hidden])',
            '[data-member-recharge-modal]:not([hidden])',
            '[data-member-edit-modal]:not([hidden])',
            '[data-member-recharge-service-modal]:not([hidden])',
            '[data-front-post-login-modal]:not([hidden])',
            '[data-front-post-customer-service-edit-modal]:not([hidden])',
            '[data-service-agent-payment-preview-modal]:not([hidden])',
            '[data-customer-service-image-preview-modal]:not([hidden])',
            '[data-forecast-progress]:not([hidden])',
            '#forecast-guest-modal:not([hidden])'
        ];
        var index;

        if (document.body
            && document.body.classList
            && document.body.classList.contains('expert-post-modal-open')) {
            return false;
        }

        for (index = 0; index < selectors.length; index += 1) {
            if (document.querySelector(selectors[index])) {
                return true;
            }
        }

        return false;
    }

    function frontGestureSameOriginReferrer() {
        var referrer = String(document.referrer || '').trim();
        var url;

        if (!referrer) {
            return false;
        }

        try {
            url = new URL(referrer, window.location.href);
            return url.origin === window.location.origin;
        } catch (error) {
            return false;
        }
    }

    function frontGestureGoBack(fallbackUrl) {
        if (window.history && window.history.length > 1 && frontGestureSameOriginReferrer()) {
            window.history.back();
            return true;
        }

        if (fallbackUrl) {
            window.location.href = fallbackUrl;
            return true;
        }

        return false;
    }

    function closeFrontGestureModalLayer(direction) {
        var body = document.body;
        var fallbackLink;

        if (direction !== 'right' || !body || !body.classList) {
            return false;
        }

        if (body.classList.contains('expert-post-modal-open')) {
            closeMemberPurchasePostModal();
            return true;
        }

        if (body.classList.contains('standalone-modal-post')) {
            if (window.parent && window.parent !== window && typeof window.parent.postMessage === 'function') {
                try {
                    window.parent.postMessage({
                        type: 'front-post-modal-close-request'
                    }, window.location.origin);
                    return true;
                } catch (error) {}
            }

            fallbackLink = document.querySelector('.front-detail-actions a[href]');
            return frontGestureGoBack(fallbackLink ? fallbackLink.href : '');
        }

        return false;
    }

    function frontGestureBottomNavLinks() {
        var nav = document.querySelector('.bottom-float-nav');
        var links;

        if (!nav || !nav.querySelectorAll) {
            return [];
        }

        links = Array.prototype.slice.call(nav.querySelectorAll('a.bottom-nav-link[href]'));
        return links.filter(function (link) {
            return !!(link.href && !link.hasAttribute('disabled') && link.getAttribute('aria-disabled') !== 'true');
        });
    }

    function frontGestureActiveNavIndex(links) {
        var currentPath = window.location.pathname;
        var currentSearch = window.location.search;
        var matchedIndex = -1;
        var index;
        var url;

        for (index = 0; index < links.length; index += 1) {
            if (links[index].classList && links[index].classList.contains('is-active')) {
                return index;
            }
        }

        for (index = 0; index < links.length; index += 1) {
            try {
                url = new URL(links[index].href, window.location.href);
            } catch (error) {
                continue;
            }

            if (url.pathname === currentPath && url.search === currentSearch) {
                return index;
            }

            if (matchedIndex < 0 && url.pathname === currentPath) {
                matchedIndex = index;
            }
        }

        return matchedIndex >= 0 ? matchedIndex : 0;
    }

    function navigateFrontGestureBottomNav(direction) {
        var links = frontGestureBottomNavLinks();
        var activeIndex;
        var nextIndex;
        var currentHref;
        var nextHref;

        if (!links.length) {
            return false;
        }

        activeIndex = frontGestureActiveNavIndex(links);
        nextIndex = activeIndex + (direction === 'left' ? 1 : -1);
        if (nextIndex < 0) {
            return direction === 'right' ? frontGestureGoBack('') : false;
        }
        if (nextIndex >= links.length) {
            return false;
        }

        currentHref = String(window.location.href || '').replace(/#.*$/, '');
        nextHref = String(links[nextIndex].href || '').replace(/#.*$/, '');
        if (!nextHref || nextHref === currentHref) {
            return false;
        }

        window.location.href = links[nextIndex].href;
        return true;
    }

    function handleFrontGestureNavigation(direction) {
        var body = document.body;

        if (closeFrontGestureModalLayer(direction)) {
            return true;
        }

        if (body && body.classList && (
            body.classList.contains('expert-post-modal-open')
            || body.classList.contains('standalone-modal-post')
        )) {
            return false;
        }

        if (isFrontGestureBlockingModalOpen()) {
            return false;
        }

        return navigateFrontGestureBottomNav(direction);
    }

    function initMobileFrontGestures(root) {
        var body = document.body;
        var startX = 0;
        var startY = 0;
        var lastX = 0;
        var lastY = 0;
        var startTime = 0;
        var tracking = false;
        var lastTriggeredAt = 0;

        if (!body
            || body.getAttribute('data-mobile-front-gestures-ready') === '1'
            || !isMobileFrontGestureDevice()
            || !frontGestureHasNavSurface()) {
            return;
        }

        body.setAttribute('data-mobile-front-gestures-ready', '1');

        document.addEventListener('touchstart', function (event) {
            var touch;
            var selection;

            if (!event.touches || event.touches.length !== 1) {
                tracking = false;
                return;
            }

            if (!frontGestureHasNavSurface() || isFrontGestureBlockingModalOpen()) {
                tracking = false;
                return;
            }

            if (isFrontGestureInteractiveTarget(event.target) || isFrontGestureHorizontalScroller(event.target)) {
                tracking = false;
                return;
            }

            try {
                selection = window.getSelection ? window.getSelection() : null;
                if (selection && !selection.isCollapsed) {
                    tracking = false;
                    return;
                }
            } catch (error) {}

            touch = event.touches[0];
            startX = touch.clientX;
            startY = touch.clientY;
            lastX = startX;
            lastY = startY;
            startTime = (new Date()).getTime();
            tracking = true;
        }, { passive: true });

        document.addEventListener('touchmove', function (event) {
            var touch;
            var deltaX;
            var deltaY;
            var absX;
            var absY;

            if (!tracking || !event.touches || event.touches.length !== 1) {
                return;
            }

            touch = event.touches[0];
            lastX = touch.clientX;
            lastY = touch.clientY;
            deltaX = lastX - startX;
            deltaY = lastY - startY;
            absX = Math.abs(deltaX);
            absY = Math.abs(deltaY);

            if (absY > 42 && absY > absX * 1.1) {
                tracking = false;
                return;
            }

            if (absX > 28 && absX > absY * 1.35 && event.cancelable) {
                event.preventDefault();
            }
        }, { passive: false });

        document.addEventListener('touchend', function (event) {
            var deltaX;
            var deltaY;
            var absX;
            var absY;
            var elapsed;
            var minDistance;
            var direction;
            var now;

            if (!tracking) {
                return;
            }

            tracking = false;
            deltaX = lastX - startX;
            deltaY = lastY - startY;
            absX = Math.abs(deltaX);
            absY = Math.abs(deltaY);
            elapsed = (new Date()).getTime() - startTime;
            minDistance = Math.max(64, Math.min(112, Math.floor(window.innerWidth * 0.16)));

            if (absX < minDistance || absX < absY * 1.45 || elapsed > 700) {
                return;
            }

            now = (new Date()).getTime();
            if (now - lastTriggeredAt < 720) {
                return;
            }

            direction = deltaX > 0 ? 'right' : 'left';
            if (handleFrontGestureNavigation(direction)) {
                lastTriggeredAt = now;
                if (event.cancelable) {
                    event.preventDefault();
                }
            }
        }, { passive: false });

        document.addEventListener('touchcancel', function () {
            tracking = false;
        }, { passive: true });
    }

    function initInstallProgressForms(root) {
        var scope = root || document;
        var forms = scope.querySelectorAll('[data-install-progress-form]');

        Array.prototype.forEach.call(forms, function (form) {
            var panel = form.querySelector('[data-install-progress]');
            var bar = form.querySelector('[data-install-progress-bar]');
            var text = form.querySelector('[data-install-progress-text]');
            var percent = form.querySelector('[data-install-progress-percent]');
            var button = form.querySelector('[type="submit"]');
            var buttonText = button ? button.querySelector('span') : null;
            var initialButtonText = buttonText ? buttonText.textContent : '';
            var timer = null;
            var progress = 0;
            var steps = [
                '正在验证数据库连接',
                '正在写入系统配置',
                '正在创建数据表结构',
                '正在初始化管理员账号',
                '正在完成安装跳转'
            ];

            if (form.getAttribute('data-install-progress-ready') === '1') {
                return;
            }
            form.setAttribute('data-install-progress-ready', '1');

            function render(value, message) {
                progress = Math.min(96, Math.max(progress, parseInt(value || 0, 10)));
                if (bar) {
                    bar.style.width = progress + '%';
                }
                if (percent) {
                    percent.textContent = progress + '%';
                }
                if (text && message) {
                    text.textContent = message;
                }
            }

            function stopProgress() {
                window.clearInterval(timer);
                timer = null;
                progress = 0;
                form.removeAttribute('data-install-submitting');
                form.classList.remove('is-installing');
                if (panel) {
                    panel.hidden = true;
                }
                if (button) {
                    button.disabled = false;
                    button.removeAttribute('aria-busy');
                }
                if (buttonText) {
                    buttonText.textContent = initialButtonText;
                }
                render(0, '准备开始安装');
            }

            form.addEventListener('submit', function (event) {
                if (form.getAttribute('data-install-submitting') === '1') {
                    event.preventDefault();
                    return;
                }

                form.setAttribute('data-install-submitting', '1');
                form.classList.add('is-installing');
                if (panel) {
                    panel.hidden = false;
                }
                if (button) {
                    button.disabled = true;
                    button.setAttribute('aria-busy', 'true');
                }
                if (buttonText) {
                    buttonText.textContent = '安装中...';
                }

                render(8, steps[0]);
                timer = window.setInterval(function () {
                    var stepIndex;

                    if (progress < 46) {
                        progress += 8;
                    } else if (progress < 78) {
                        progress += 5;
                    } else {
                        progress += 2;
                    }

                    stepIndex = Math.min(steps.length - 1, Math.floor(progress / 24));
                    render(progress, steps[stepIndex]);
                }, 620);
            });

            window.addEventListener('pageshow', function (event) {
                if (event.persisted && form.getAttribute('data-install-submitting') === '1') {
                    stopProgress();
                }
            });
        });
    }

    function initMemberPredictionDeleteForms(root) {
        var scope = root || document;
        var forms = scope.querySelectorAll('[data-member-prediction-delete-form]');

        Array.prototype.forEach.call(forms, function (form) {
            var submitButton = form.querySelector('[data-member-prediction-delete-submit]');
            var checkAll = form.querySelector('[data-member-prediction-check-all]');
            var checkboxes = Array.prototype.slice.call(form.querySelectorAll('.member-ai-log-check-input'));
            var baseText;
            var refreshState;

            if (form.getAttribute('data-member-prediction-delete-ready') === '1') {
                return;
            }
            form.setAttribute('data-member-prediction-delete-ready', '1');

            baseText = submitButton ? submitButton.textContent : '删除记录';
            refreshState = function () {
                var selectedCount = checkboxes.filter(function (checkbox) {
                    return checkbox.checked;
                }).length;

                if (submitButton) {
                    submitButton.disabled = selectedCount <= 0;
                    submitButton.textContent = selectedCount > 0 ? '删除' + selectedCount + '条' : baseText;
                }

                if (checkAll) {
                    checkAll.checked = checkboxes.length > 0 && selectedCount === checkboxes.length;
                    checkAll.indeterminate = selectedCount > 0 && selectedCount < checkboxes.length;
                }
            };

            form.addEventListener('change', function (event) {
                if (event.target.matches('.member-ai-log-check-input') || event.target === checkAll) {
                    window.setTimeout(refreshState, 0);
                }
            });

            form.addEventListener('submit', function (event) {
                var selectedCount = checkboxes.filter(function (checkbox) {
                    return checkbox.checked;
                }).length;

                if (selectedCount <= 0) {
                    event.preventDefault();
                    event.stopPropagation();
                    toast('请先选择要删除的预测记录。', 'error');
                }
            });

            refreshState();
        });
    }

    function initNoticeSeeds(root) {
        var scope = root || document;
        var seeds = scope.querySelectorAll('[data-app-notice-seed]');

        Array.prototype.forEach.call(seeds, function (seed) {
            var message = String(seed.getAttribute('data-app-notice-message') || '').trim();
            var type = String(seed.getAttribute('data-app-notice-type') || 'info').trim();

            if (message) {
                toast(message, type);
            }

            seed.parentNode && seed.parentNode.removeChild(seed);
        });
    }

    window.addEventListener('message', function (event) {
        var data = event && event.data ? event.data : null;
        var payload;

        if (!data) {
            return;
        }
        if (event.origin && event.origin !== window.location.origin) {
            return;
        }

        if (data.type === 'front-post-modal-close-request') {
            closeMemberPurchasePostModal();
            return;
        }

        if (data.type !== 'front-post-buy-scroll-save') {
            return;
        }

        payload = data.payload || null;
        if (!payload || String(payload.href || '') === '') {
            return;
        }

        memberPurchasePostFrameScrollRestore = {
            href: normalizeFrontPostBuyScrollHref(payload.href),
            scrollY: Math.max(0, parseInt(payload.scrollY, 10) || 0),
            anchorIndex: parseInt(payload.anchorIndex, 10) || -1,
            anchorTop: parseInt(payload.anchorTop, 10) || 0,
            time: Number(payload.time || 0) || (new Date()).getTime()
        };
    });

    document.addEventListener('submit', function (event) {
        var form = event.target;
        var confirmText;
        var submitter;

        if (!form.matches('form[data-confirm]:not([data-ajax-form])')) {
            return;
        }

        if (form.getAttribute('data-confirm-confirmed') === '1') {
            form.removeAttribute('data-confirm-confirmed');
            return;
        }

        confirmText = form.getAttribute('data-confirm') || '';
        if (!confirmText) {
            return;
        }

        event.preventDefault();
        submitter = event.submitter || document.activeElement;
        appConfirm(confirmText, '确认操作', '确定', '取消').then(function (confirmed) {
            if (confirmed) {
                submitConfirmedForm(form, submitter && submitter.form === form ? submitter : null);
            }
        });
    });

    function padForecastProgressNumber(number) {
        number = Math.max(1, Math.min(49, parseInt(number, 10) || 1));

        return number < 10 ? '0' + number : String(number);
    }

    function setForecastProgressText(target, text) {
        if (target) {
            target.textContent = text;
        }
    }

    function refreshForecastProgressCodes(state) {
        if (!state || !state.codes) {
            return;
        }

        Array.prototype.forEach.call(state.codes, function (code) {
            code.textContent = padForecastProgressNumber(1 + Math.floor(Math.random() * 49));
        });
    }

    function stopForecastProgress(state) {
        if (!state || state.stopped) {
            return;
        }

        state.stopped = true;
        window.clearInterval(state.timer);
        window.clearTimeout(state.hideTimer);
        if (state.form) {
            state.form.classList.remove('is-forecast-progressing');
            state.form.removeAttribute('aria-busy');
        }
        if (state.card) {
            state.card.classList.remove('is-forecast-progressing');
        }
        if (state.panel) {
            state.panel.classList.remove('is-success', 'is-error');
            state.panel.setAttribute('aria-hidden', 'true');
            state.panel.hidden = true;
        }
        if (state.actionWrap) {
            state.actionWrap.hidden = true;
        }
        if (state.action) {
            state.action.textContent = '查看结果';
        }
    }

    function updateForecastProgressPhase(state, forcePhase) {
        var phase;

        if (!state || state.stopped) {
            return;
        }

        if (typeof forcePhase === 'number') {
            state.phase = Math.max(0, Math.min(state.phases.length - 1, forcePhase));
        }
        phase = state.phases[state.phase] || state.phases[0];
        setForecastProgressText(state.title, phase.title);
        setForecastProgressText(state.text, phase.text);
        if (state.bar) {
            state.bar.style.width = Math.max(6, Math.min(100, state.percent)) + '%';
        }
        refreshForecastProgressCodes(state);
    }

    function startForecastProgress(form) {
        var card;
        var panel;
        var state;

        if (!form || !form.matches('[data-forecast-progress-form]')) {
            return null;
        }

        if (form._forecastProgressState) {
            stopForecastProgress(form._forecastProgressState);
        }

        card = form.closest('.forecast-card-primary') || form.closest('.forecast-page') || document.body;
        panel = card ? card.querySelector('[data-forecast-progress]') : null;
        if (!panel) {
            return null;
        }

        state = {
            form: form,
            card: card,
            panel: panel,
            title: panel.querySelector('[data-forecast-progress-title]'),
            text: panel.querySelector('[data-forecast-progress-text]'),
            bar: panel.querySelector('[data-forecast-progress-bar]'),
            codes: panel.querySelectorAll('[data-forecast-progress-code]'),
            actionWrap: panel.querySelector('[data-forecast-progress-actions]'),
            action: panel.querySelector('[data-forecast-progress-close]'),
            timer: null,
            hideTimer: null,
            stopped: false,
            phase: 0,
            percent: 8,
            redirect: '',
            rechargeModal: '',
            phases: [
                {title: '\u7384\u95e8\u5f00\u76d8\u4e2d', text: '\u6b63\u5728\u5524\u8d77\u8fd1\u671f\u5f00\u5956\u6c14\u6570'},
                {title: '\u661f\u76d8\u6821\u9a8c\u4e2d', text: '\u6b63\u5728\u6821\u9a8c\u5355\u53cc\u6ce2\u8272\u4e94\u884c'},
                {title: '\u751f\u8096\u53f7\u7801\u5165\u5c40', text: '\u6b63\u5728\u63a8\u6f14\u751f\u8096\u4e0e\u53f7\u7801\u6620\u5c04'},
                {title: '\u70ed\u5ea6\u8109\u7edc\u5f52\u4f4d', text: '\u6b63\u5728\u538b\u7f29\u91cd\u590d\u5e76\u91cd\u6392\u7ec4\u5408'},
                {title: '\u7384\u673a\u5373\u5c06\u6210\u5c40', text: '\u6b63\u5728\u5c01\u5b58\u672c\u671f\u9884\u6d4b\u7ed3\u679c'}
            ]
        };

        form._forecastProgressState = state;
        if (state.action && state.action.getAttribute('data-forecast-progress-bound') !== '1') {
            state.action.setAttribute('data-forecast-progress-bound', '1');
            state.action.addEventListener('click', function () {
                var currentState = form._forecastProgressState;
                var redirectUrl = currentState && currentState.redirect ? currentState.redirect : '';
                var rechargeModalId = currentState && currentState.rechargeModal ? currentState.rechargeModal : '';
                var rechargeModal;

                stopForecastProgress(currentState);
                if (rechargeModalId) {
                    rechargeModal = document.getElementById(rechargeModalId);
                    if (rechargeModal) {
                        closeNoticeModal();
                        closeMemberRechargeModals(rechargeModal);
                        setMemberRechargeModal(rechargeModal, true);
                    }
                    return;
                }
                if (redirectUrl) {
                    window.location.href = redirectUrl;
                }
            });
        }
        panel.classList.remove('is-success', 'is-error');
        panel.hidden = false;
        panel.setAttribute('aria-hidden', 'false');
        if (state.actionWrap) {
            state.actionWrap.hidden = true;
        }
        if (state.action) {
            state.action.textContent = '查看结果';
        }
        form.classList.add('is-forecast-progressing');
        form.setAttribute('aria-busy', 'true');
        if (card) {
            card.classList.add('is-forecast-progressing');
        }
        updateForecastProgressPhase(state, 0);
        state.timer = window.setInterval(function () {
            state.phase = Math.min(state.phases.length - 1, state.phase + 1);
            state.percent = Math.min(92, state.percent + 11 + Math.floor(Math.random() * 9));
            updateForecastProgressPhase(state);
        }, 620);

        return state;
    }

    function finishForecastProgress(state, success, payload) {
        var title = '';
        var message = '';

        if (!state || state.stopped) {
            return;
        }

        window.clearInterval(state.timer);
        window.clearTimeout(state.hideTimer);
        payload = payload || {};
        title = payload.notice_title || payload.title || '';
        message = payload.message || '';
        state.redirect = payload.redirect ? String(payload.redirect) : '';
        state.rechargeModal = payload.rechargeModal ? String(payload.rechargeModal) : '';
        state.percent = success ? 100 : 18;
        if (state.panel) {
            state.panel.classList.toggle('is-success', !!success);
            state.panel.classList.toggle('is-error', !success);
        }
        setForecastProgressText(state.title, title || (success ? '\u7384\u673a\u5df2\u6210\u5c40' : '\u63a8\u6f14\u672a\u6210\u5c40'));
        setForecastProgressText(state.text, message || (success ? '\u6b63\u5728\u5c55\u5f00\u672c\u671f\u9884\u6d4b\u7ed3\u679c' : '\u8bf7\u6838\u5bf9\u9009\u62e9\u4e0e\u79ef\u5206\u540e\u518d\u8bd5'));
        if (state.bar) {
            state.bar.style.width = state.percent + '%';
        }
        refreshForecastProgressCodes(state);
        if (state.actionWrap) {
            state.actionWrap.hidden = false;
        }
        if (state.action) {
            if (state.rechargeModal) {
                state.action.textContent = '去充值';
            } else if (state.redirect) {
                state.action.textContent = success ? '查看结果' : '继续';
            } else {
                state.action.textContent = '知道了';
            }
            state.action.focus();
        }
    }

    function ensureForecastGuestModal() {
        var modal = document.getElementById('forecast-guest-modal');

        if (modal) {
            return modal;
        }

        modal = document.createElement('div');
        modal.id = 'forecast-guest-modal';
        modal.className = 'forecast-guest-modal front-standard-modal';
        modal.setAttribute('hidden', 'hidden');
        modal.innerHTML = '' +
            '<div class="forecast-guest-backdrop front-standard-modal-backdrop" data-forecast-guest-close></div>' +
            '<section class="forecast-guest-dialog front-standard-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="forecast-guest-title">' +
                '<div class="forecast-guest-mark"><i class="fa-solid fa-user-plus"></i></div>' +
                '<div class="forecast-guest-copy">' +
                    '<h2 id="forecast-guest-title">\u6ce8\u518c\u540e\u53c2\u4e0eAI\u9884\u6d4b</h2>' +
                    '<p>\u5f53\u524d\u4e3a\u6e38\u5ba2\u8bbf\u95ee\uff0c\u8bf7\u5148\u6ce8\u518c\u4f1a\u5458\u540e\u518d\u53c2\u4e0e\u9884\u6d4b\uff0c\u5df2\u9009\u62e9\u7684\u9884\u6d4b\u7c7b\u578b\u4f1a\u4fdd\u7559\u5728\u9875\u9762\u4e0a\u3002</p>' +
                '</div>' +
                '<div class="forecast-guest-actions">' +
                    '<button type="button" class="forecast-guest-register" data-forecast-guest-register>\u7acb\u5373\u6ce8\u518c</button>' +
                    '<button type="button" class="forecast-guest-login" data-forecast-guest-login>\u4f1a\u5458\u767b\u5f55</button>' +
                    '<button type="button" class="forecast-guest-cancel" data-forecast-guest-close>\u5148\u770b\u770b</button>' +
                '</div>' +
            '</section>';
        document.body.appendChild(modal);

        modal.addEventListener('click', function (event) {
            var target = event.target;
            var registerUrl = modal.getAttribute('data-register-url') || '';
            var loginUrl = modal.getAttribute('data-login-url') || '';

            if (!target || !target.closest) {
                return;
            }

            if (target.closest('[data-forecast-guest-register]')) {
                if (registerUrl) {
                    window.location.href = registerUrl;
                }
                return;
            }

            if (target.closest('[data-forecast-guest-login]')) {
                if (loginUrl) {
                    window.location.href = loginUrl;
                }
                return;
            }

            if (target.closest('[data-forecast-guest-close]')) {
                closeForecastGuestModal();
            }
        });

        return modal;
    }

    function closeForecastGuestModal() {
        var modal = document.getElementById('forecast-guest-modal');

        if (!modal) {
            return;
        }

        modal.classList.remove('is-visible');
        modal.setAttribute('hidden', 'hidden');
    }

    function openForecastGuestModal(form) {
        var modal = ensureForecastGuestModal();
        var registerUrl = form.getAttribute('data-forecast-register-url') || '';
        var loginUrl = form.getAttribute('data-forecast-login-url') || '';
        var registerButton;
        var loginButton;

        modal.setAttribute('data-register-url', registerUrl);
        modal.setAttribute('data-login-url', loginUrl);
        modal.removeAttribute('hidden');
        window.requestAnimationFrame(function () {
            modal.classList.add('is-visible');
            registerButton = modal.querySelector('[data-forecast-guest-register]');
            loginButton = modal.querySelector('[data-forecast-guest-login]');
            if (registerButton) {
                registerButton.focus();
            } else if (loginButton) {
                loginButton.focus();
            }
        });
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;
        var formData;
        var endpoint;
        var button;
        var confirmText;
        var submitter;
        var immediateRedirect;
        var reloadCurrent;
        var leavingPage = false;
        var forecastProgress;
        var submitAjaxForm;
        var isAuthForm;

        if (!form.matches('[data-ajax-form]')) {
            return;
        }

        event.preventDefault();
        isAuthForm = !!form.querySelector('input[name="action"][value^="auth."]');
        if (isAuthForm && form.getAttribute('data-auth-submit-state') === 'submitting') {
            return;
        }
        if (form.matches('[data-forecast-progress-form]') && form.getAttribute('data-forecast-guest-form') === '1') {
            openForecastGuestModal(form);
            return;
        }
        setFormError(form, '');
        formData = new FormData(form);
        submitter = event.submitter || document.activeElement;
        if (submitter && submitter.name && submitter.form === form) {
            formData.set(submitter.name, submitter.value || '');
        }
        endpoint = form.getAttribute('action') || './api.php';
        button = submitter && submitter.type === 'submit' ? submitter : form.querySelector('[type="submit"]');
        confirmText = form.getAttribute('data-confirm');
        immediateRedirect = form.getAttribute('data-immediate-redirect') === '1';
        reloadCurrent = form.getAttribute('data-reload-current') === '1';

        submitAjaxForm = function () {
            if (isAuthForm) {
                form.setAttribute('data-auth-submit-state', 'submitting');
            }
            if (button) {
                button.disabled = true;
            }
            forecastProgress = startForecastProgress(form);

            fetch(endpoint, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).then(function (response) {
                return toJson(response);
            }).then(function (payload) {
                if (!payload.success) {
                    throw payloadError(payload, '操作失败。');
                }

                if (isAuthForm) {
                    markFrontAuthChanged();
                }

                finishForecastProgress(forecastProgress, true, payload);

                if (payload.message && !immediateRedirect && !forecastProgress) {
                    toast(
                        payload.message,
                        'success',
                        payload.notice_title || '',
                        payload.notice_redirect_after_close && payload.redirect ? payload.redirect : ''
                    );
                }

                if (reloadCurrent) {
                    leavingPage = true;
                    window.setTimeout(function () {
                        window.location.reload();
                    }, 450);
                    return;
                }

                if (payload.redirect) {
                    if (payload.notice_redirect_after_close && payload.message) {
                        return;
                    }

                    if (isAuthForm) {
                        form.setAttribute('data-auth-submit-state', 'complete');
                    }
                    if (immediateRedirect) {
                        leavingPage = true;
                        window.location.href = payload.redirect;
                        return;
                    }

                    leavingPage = true;
                    window.setTimeout(function () {
                        window.location.href = payload.redirect;
                    }, 450);
                    return;
                }

                if (payload.reload) {
                    if (isAdminAutoReloadDisabled()) {
                        return;
                    }

                    saveFrontPostBuyScroll(form);
                    leavingPage = true;
                    window.setTimeout(function () {
                        window.location.reload();
                    }, 450);
                    return;
                }

                if (payload.htmlTarget && payload.html) {
                    var target = document.querySelector(payload.htmlTarget);
                    if (target) {
                        target.innerHTML = payload.html;
                        bootCommonUi(target);
                    }
                }

                if (payload.resetForm) {
                    form.reset();
                }
            }).catch(function (error) {
                if (
                    isAuthForm
                    && form.getAttribute('data-auth-submit-state') === 'complete'
                    && error
                    && String(error.message || '').indexOf('表单令牌已失效') !== -1
                ) {
                    return;
                }
                finishForecastProgress(forecastProgress, false, {
                    message: error.message,
                    title: error.rechargeModal ? '操作失败' : '',
                    rechargeModal: error.rechargeModal || '',
                    redirect: error.redirect || ''
                });
                if (forecastProgress) {
                    return;
                }
                if (redirectFromPayloadError(error)) {
                    return;
                }
                setFormError(form, error.message);
                toast(error.message, 'error');
            }).finally(function () {
                if (isAuthForm && !leavingPage && form.getAttribute('data-auth-submit-state') !== 'complete') {
                    form.removeAttribute('data-auth-submit-state');
                }
                if (button && !leavingPage) {
                    button.disabled = false;
                }
            });
        };

        if (confirmText) {
            appConfirm(confirmText, '确认操作', '确定', '取消').then(function (confirmed) {
                if (confirmed) {
                    submitAjaxForm();
                }
            });
            return;
        }

        submitAjaxForm();
    });

    function resetMemberRechargeDrawer(modal) {
        var drawer;

        if (!modal) {
            return;
        }

        drawer = modal.querySelector('[data-member-recharge-drawer]');
        if (drawer) {
            drawer.hidden = true;
        }

        Array.prototype.forEach.call(modal.querySelectorAll('[data-member-recharge-method]'), function (button) {
            button.classList.remove('is-active');
            button.setAttribute('aria-expanded', 'false');
        });

        Array.prototype.forEach.call(modal.querySelectorAll('[data-member-recharge-panel]'), function (panel) {
            panel.hidden = true;
        });
    }

    function setMemberRechargeMethod(modal, method) {
        var drawer;
        var hasPanel = false;

        if (!modal || !method) {
            return;
        }

        drawer = modal.querySelector('[data-member-recharge-drawer]');

        Array.prototype.forEach.call(modal.querySelectorAll('[data-member-recharge-panel]'), function (panel) {
            var isActive = panel.getAttribute('data-member-recharge-panel') === method;
            panel.hidden = !isActive;
            hasPanel = hasPanel || isActive;
        });

        Array.prototype.forEach.call(modal.querySelectorAll('[data-member-recharge-method]'), function (button) {
            var isActive = button.getAttribute('data-member-recharge-method') === method;
            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-expanded', isActive ? 'true' : 'false');
        });

        if (drawer) {
            drawer.hidden = !hasPanel;
        }
    }

    function setMemberRechargeQrStatus(image, isMissing) {
        var panel;

        if (!image || !image.closest) {
            return;
        }

        panel = image.closest('[data-member-recharge-panel]');
        if (panel) {
            panel.classList.toggle('is-qr-missing', !!isMissing);
        }
    }

    var memberRechargeBlankQrSrc = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';

    function memberRechargeQrUrl(baseSrc) {
        var nextUrl;
        var refreshToken;

        baseSrc = String(baseSrc || '').trim();
        if (!baseSrc) {
            return memberRechargeBlankQrSrc;
        }

        refreshToken = String(Date.now());
        try {
            nextUrl = new URL(baseSrc, window.location.href);
            nextUrl.searchParams.set('qr_v', refreshToken);

            return nextUrl.href;
        } catch (error) {
            return baseSrc + (baseSrc.indexOf('?') === -1 ? '?' : '&') + 'qr_v=' + encodeURIComponent(refreshToken);
        }
    }

    function setMemberRechargeQrImage(image, baseSrc) {
        baseSrc = String(baseSrc || '').trim();
        if (!image) {
            return;
        }

        image.setAttribute('data-member-recharge-qr-base', baseSrc);
        if (!baseSrc) {
            image.setAttribute('data-member-recharge-qr-missing', '1');
            image.removeAttribute('src');
            setMemberRechargeQrStatus(image, true);
            return;
        }

        image.setAttribute('data-member-recharge-qr-missing', '0');
        image.setAttribute('src', memberRechargeQrUrl(baseSrc));
        setMemberRechargeQrStatus(image, false);
    }

    function memberRechargeQrList(image) {
        var rawList;
        var parsedList;
        var list = [];

        if (!image) {
            return list;
        }

        rawList = String(image.getAttribute('data-member-recharge-qr-list') || '').trim();
        if (rawList) {
            try {
                parsedList = JSON.parse(rawList);
            } catch (error) {
                parsedList = [];
            }

            if (Array.isArray(parsedList)) {
                parsedList.forEach(function (item) {
                    item = String(item || '').trim();
                    if (item) {
                        list.push(item);
                    }
                });
            }
        }

        return list;
    }

    function clearMemberRechargeQrImages(modal) {
        if (!modal || !modal.querySelectorAll) {
            return;
        }

        Array.prototype.forEach.call(modal.querySelectorAll('[data-member-recharge-qr]'), function (image) {
            image.setAttribute('data-member-recharge-qr-list', '[]');
            image.setAttribute('data-member-recharge-qr-index', '0');
            setMemberRechargeQrImage(image, '');
        });
    }

    function updateMemberRechargeUsdtAddress(modal, data) {
        var addressBox;
        var code;
        var copyButton;
        var emptyText;
        var usdtAddress;

        if (!modal || !modal.querySelector) {
            return;
        }

        addressBox = modal.querySelector('.member-recharge-usdt-address');
        if (!addressBox) {
            return;
        }

        code = addressBox.querySelector('code');
        copyButton = addressBox.querySelector('[data-member-recharge-copy]');
        emptyText = String(addressBox.getAttribute('data-member-recharge-usdt-empty') || '').trim();
        usdtAddress = data && data.usdt_address ? String(data.usdt_address).trim() : '';

        addressBox.classList.toggle('is-empty', !usdtAddress);
        if (code) {
            code.textContent = usdtAddress || emptyText;
        }
        if (!copyButton) {
            return;
        }

        copyButton.setAttribute('data-member-recharge-copy', usdtAddress);
        if (usdtAddress) {
            copyButton.disabled = false;
            copyButton.removeAttribute('aria-disabled');
            return;
        }

        copyButton.disabled = true;
        copyButton.setAttribute('aria-disabled', 'true');
    }

    function applyMemberRechargePaymentSettings(modal, data) {
        var qrs;
        var version;

        if (!modal || !modal.querySelector) {
            return;
        }

        qrs = data && data.qrs && typeof data.qrs === 'object' ? data.qrs : {};
        version = data && data.version ? String(data.version) : String(Date.now());
        modal.setAttribute('data-member-recharge-payment-version', version);

        ['alipay', 'wechat', 'usdt'].forEach(function (type) {
            var panel = modal.querySelector('[data-member-recharge-panel="' + type + '"]');
            var image = panel ? panel.querySelector('[data-member-recharge-qr]') : null;
            var rawList = Array.isArray(qrs[type]) ? qrs[type] : [];
            var list = [];

            rawList.forEach(function (item) {
                item = String(item || '').trim();
                if (item && list.indexOf(item) === -1) {
                    list.push(item);
                }
            });

            if (!image) {
                return;
            }

            image.setAttribute('data-member-recharge-qr-list', JSON.stringify(list));
            image.setAttribute('data-member-recharge-qr-index', '0');
            setMemberRechargeQrImage(image, list.length ? list[0] : '');
        });

        updateMemberRechargeUsdtAddress(modal, data || {});
    }

    function fetchMemberRechargePaymentSettings(modal, clearFirst) {
        var endpoint;
        var token;
        var formData;

        if (!modal) {
            return Promise.resolve(null);
        }

        if (clearFirst) {
            clearMemberRechargeQrImages(modal);
        }

        endpoint = String(modal.getAttribute('data-api-url') || './api.php').trim();
        token = String(modal.getAttribute('data-token') || '').trim();
        if (!endpoint || !token) {
            return Promise.reject(new Error('充值二维码刷新参数缺失，请刷新页面后重试。'));
        }

        formData = new FormData();
        formData.append('action', 'customer_service.member.payment_settings');
        formData.append('_token', token);

        return fetch(endpoint, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            cache: 'no-store'
        }).then(function (response) {
            return toJson(response);
        }).then(function (payload) {
            if (!payload.success) {
                throw new Error(payload.message || '充值二维码刷新失败，请稍后重试。');
            }

            applyMemberRechargePaymentSettings(modal, payload.data || {});

            return payload;
        });
    }

    function handleMemberRechargePaymentError(error) {
        toast(error && error.message ? error.message : '充值二维码刷新失败，请稍后重试。', 'error');
    }

    function rotateMemberRechargeQr(trigger) {
        var panel;
        var image;
        var qrList;
        var currentIndex;
        var baseSrc;

        if (!trigger || !trigger.closest) {
            return;
        }

        panel = trigger.closest('[data-member-recharge-panel]');
        image = panel ? panel.querySelector('[data-member-recharge-qr]') : null;
        if (!image) {
            return;
        }

        qrList = memberRechargeQrList(image);
        if (!qrList.length) {
            setMemberRechargeQrImage(image, '');
            return;
        }

        currentIndex = parseInt(image.getAttribute('data-member-recharge-qr-index') || '0', 10);
        if (isNaN(currentIndex)) {
            currentIndex = 0;
        }
        currentIndex = (currentIndex + 1) % qrList.length;
        baseSrc = qrList[currentIndex];
        if (!baseSrc) {
            return;
        }

        image.setAttribute('data-member-recharge-qr-index', String(currentIndex));
        setMemberRechargeQrImage(image, baseSrc);
    }

    function refreshMemberRechargeQr(trigger) {
        var modal;

        if (!trigger || !trigger.closest) {
            return;
        }

        modal = trigger.closest('[data-member-recharge-modal]');
        fetchMemberRechargePaymentSettings(modal, true).then(function () {
            rotateMemberRechargeQr(trigger);
        }).catch(handleMemberRechargePaymentError);
    }

    function copyMemberRechargeText(trigger) {
        var text;
        var textarea;
        var copied = false;

        if (!trigger) {
            return;
        }

        text = String(trigger.getAttribute('data-member-recharge-copy') || '').trim();
        if (!text) {
            return;
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                toast('地址已复制', 'success');
            }, function () {
                toast('复制失败，请手动复制', 'error');
            });
            return;
        }

        textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', 'readonly');
        textarea.style.position = 'fixed';
        textarea.style.left = '-9999px';
        textarea.style.top = '0';
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();

        try {
            copied = document.execCommand('copy');
        } catch (error) {
            copied = false;
        }

        document.body.removeChild(textarea);
        toast(copied ? '地址已复制' : '复制失败，请手动复制', copied ? 'success' : 'error');
    }

    function setMemberRechargeModal(modal, isOpen) {
        var input;
        var hasPaymentRefreshParams;

        if (!modal) {
            return;
        }

        hasPaymentRefreshParams = String(modal.getAttribute('data-api-url') || '').trim() !== ''
            && String(modal.getAttribute('data-token') || '').trim() !== '';

        if (isOpen && hasPaymentRefreshParams) {
            fetchMemberRechargePaymentSettings(modal, true).catch(handleMemberRechargePaymentError);
        }

        setAnimatedHidden(modal, isOpen, 'member-recharge-modal-open');

        if (!isOpen) {
            setFormModalKeyboardActive(modal, false);
            resetMemberRechargeDrawer(modal);
            return;
        }

        if (isOpen) {
            if (hasPaymentRefreshParams) {
                setMemberRechargeMethod(modal, 'alipay');
            }
            input = modal.querySelector('[data-member-recharge-input]');
            window.setTimeout(function () {
                if (input) {
                    input.focus();
                    input.select();
                }
            }, 0);
        }
    }

    function closeMemberRechargeModals(exceptModal) {
        Array.prototype.forEach.call(document.querySelectorAll('[data-member-recharge-modal]'), function (modal) {
            if (modal !== exceptModal) {
                setMemberRechargeModal(modal, false);
            }
        });
    }

    function setMemberEditModal(modal, isOpen) {
        var input;

        if (!modal) {
            return;
        }

        setAnimatedHidden(modal, isOpen, 'member-edit-modal-open');

        if (!isOpen) {
            setFormModalKeyboardActive(modal, false);
            return;
        }

        if (isOpen) {
            input = modal.querySelector('input, select, textarea, button');
            window.setTimeout(function () {
                if (input) {
                    input.focus();
                }
            }, 0);
        }
    }

    function closeMemberEditModals(exceptModal) {
        Array.prototype.forEach.call(document.querySelectorAll('[data-member-edit-modal]'), function (modal) {
            if (modal !== exceptModal) {
                setMemberEditModal(modal, false);
            }
        });
    }

    function openServiceAgentPaymentPreview(trigger) {
        var modal = document.querySelector('[data-service-agent-payment-preview-modal]');
        var image;
        var title;
        var closeButton;
        var imageUrl;
        var imageTitle;

        if (!modal || !trigger) {
            return;
        }

        imageUrl = String(trigger.getAttribute('data-service-agent-payment-preview-open') || '').trim();
        imageTitle = String(trigger.getAttribute('data-service-agent-payment-preview-title') || '').trim();
        if (!imageUrl) {
            return;
        }

        image = modal.querySelector('[data-service-agent-payment-preview-image]');
        title = modal.querySelector('[data-service-agent-payment-preview-title]');
        closeButton = modal.querySelector('[data-service-agent-payment-preview-close]');

        if (image) {
            image.setAttribute('src', imageUrl);
            image.setAttribute('alt', imageTitle || '二维码预览');
        }
        if (title) {
            title.textContent = imageTitle || '二维码预览';
        }

        modal.hidden = false;
        modal.classList.add('is-visible');
        document.body.classList.add('service-agent-payment-preview-modal-open');

        if (closeButton) {
            window.setTimeout(function () {
                closeButton.focus();
            }, 0);
        }
    }

    function closeServiceAgentPaymentPreview() {
        var modal = document.querySelector('[data-service-agent-payment-preview-modal]');
        var image;

        if (!modal) {
            return;
        }

        image = modal.querySelector('[data-service-agent-payment-preview-image]');
        modal.classList.remove('is-visible');
        modal.hidden = true;
        document.body.classList.remove('service-agent-payment-preview-modal-open');

        if (image) {
            image.setAttribute('src', '');
            image.setAttribute('alt', '');
        }
    }

    function customerServiceImagePreviewModal() {
        var modal = document.querySelector('[data-customer-service-image-preview-modal]');
        var backdrop;
        var dialog;
        var closeButton;
        var image;
        var title;

        if (modal) {
            return modal;
        }

        modal = document.createElement('div');
        modal.className = 'customer-service-image-preview-modal';
        modal.setAttribute('data-customer-service-image-preview-modal', '1');
        modal.hidden = true;

        backdrop = document.createElement('button');
        backdrop.type = 'button';
        backdrop.className = 'customer-service-image-preview-backdrop';
        backdrop.setAttribute('data-customer-service-image-preview-close', '1');
        backdrop.setAttribute('aria-label', '关闭图片预览');

        dialog = document.createElement('section');
        dialog.className = 'customer-service-image-preview-dialog';
        dialog.setAttribute('role', 'dialog');
        dialog.setAttribute('aria-modal', 'true');
        dialog.setAttribute('aria-label', '聊天图片预览');

        closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'customer-service-image-preview-close';
        closeButton.setAttribute('data-customer-service-image-preview-close', '1');
        closeButton.setAttribute('aria-label', '关闭图片预览');
        closeButton.innerHTML = '<i class="fa-solid fa-xmark"></i>';

        image = document.createElement('img');
        image.setAttribute('data-customer-service-image-preview-image', '1');
        image.decoding = 'async';
        image.loading = 'eager';
        image.setAttribute('fetchpriority', 'low');
        image.alt = '';

        title = document.createElement('strong');
        title.setAttribute('data-customer-service-image-preview-title', '1');

        dialog.appendChild(closeButton);
        dialog.appendChild(image);
        dialog.appendChild(title);
        modal.appendChild(backdrop);
        modal.appendChild(dialog);
        document.body.appendChild(modal);

        return modal;
    }

    function openCustomerServiceImagePreview(trigger) {
        var modal;
        var image;
        var title;
        var closeButton;
        var imageUrl;
        var imageTitle;

        if (!trigger) {
            return;
        }

        imageUrl = String(trigger.getAttribute('data-customer-service-image-preview-open') || '').trim();
        imageTitle = String(trigger.getAttribute('data-customer-service-image-preview-title') || '').trim();
        if (!imageUrl) {
            return;
        }

        modal = customerServiceImagePreviewModal();
        image = modal.querySelector('[data-customer-service-image-preview-image]');
        title = modal.querySelector('[data-customer-service-image-preview-title]');
        closeButton = modal.querySelector('[data-customer-service-image-preview-close]');

        if (image) {
            image.setAttribute('src', imageUrl);
            image.setAttribute('alt', imageTitle || '聊天图片预览');
        }
        if (title) {
            title.textContent = imageTitle || '聊天图片预览';
        }

        modal.hidden = false;
        modal.classList.add('is-visible');
        document.body.classList.add('customer-service-image-preview-modal-open');

        if (closeButton) {
            window.setTimeout(function () {
                closeButton.focus();
            }, 0);
        }
    }

    function closeCustomerServiceImagePreview() {
        var modal = document.querySelector('[data-customer-service-image-preview-modal]');
        var image;

        if (!modal) {
            return;
        }

        image = modal.querySelector('[data-customer-service-image-preview-image]');
        modal.classList.remove('is-visible');
        modal.hidden = true;
        document.body.classList.remove('customer-service-image-preview-modal-open');

        if (image) {
            image.setAttribute('src', '');
            image.setAttribute('alt', '');
        }
    }

    document.addEventListener('focusin', function (event) {
        var target = event.target;
        var rechargeModal;
        var editModal;

        if (isFormModalInput(target)) {
            setFrontInputFocusActive(true);
        }

        if (!isFormModalInput(target) || !target.closest) {
            return;
        }

        rechargeModal = target.closest('[data-member-recharge-modal]');
        if (rechargeModal) {
            setFormModalKeyboardActive(rechargeModal, true);
            scrollFormModalInputIntoView(target);
            return;
        }

        editModal = target.closest('[data-member-edit-modal]');
        if (editModal) {
            setFormModalKeyboardActive(editModal, true);
            scrollFormModalInputIntoView(target);
        }
    });

    document.addEventListener('focusout', function (event) {
        var target = event.target;
        var rechargeModal = target && target.closest ? target.closest('[data-member-recharge-modal]') : null;
        var editModal = target && target.closest ? target.closest('[data-member-edit-modal]') : null;

        if (isFormModalInput(target)) {
            window.setTimeout(function () {
                setFrontInputFocusActive(isFormModalInput(document.activeElement));
            }, 80);
        }

        if (!rechargeModal && !editModal) {
            return;
        }

        window.setTimeout(function () {
            if (rechargeModal && !rechargeModal.contains(document.activeElement)) {
                setFormModalKeyboardActive(rechargeModal, false);
            }
            if (editModal && !editModal.contains(document.activeElement)) {
                setFormModalKeyboardActive(editModal, false);
            }
        }, 80);
    });

    document.addEventListener('input', function (event) {
        var target = event.target;
        var picker;

        if (!target || !target.matches || !target.matches('[data-service-agent-nickname-input]')) {
            return;
        }

        picker = target.closest('[data-service-agent-nickname-picker]');
        syncServiceAgentNicknameMenuActive(picker);
    });

    document.addEventListener('click', function (event) {
        var nicknameToggle = event.target.closest('[data-service-agent-nickname-toggle]');
        if (nicknameToggle) {
            var nicknamePicker = nicknameToggle.closest('[data-service-agent-nickname-picker]');
            var nicknameMenu = nicknamePicker ? nicknamePicker.querySelector('[data-service-agent-nickname-menu]') : null;
            var nicknameOpen = !!(nicknameMenu && !nicknameMenu.hidden);

            closeServiceAgentNicknameMenus(nicknamePicker);
            setServiceAgentNicknameMenu(nicknamePicker, !nicknameOpen);
            event.preventDefault();
            return;
        }

        var nicknameDelete = event.target.closest('[data-service-agent-nickname-delete]');
        if (nicknameDelete) {
            deleteServiceAgentNicknameOption(nicknameDelete);
            event.preventDefault();
            return;
        }

        var nicknameOption = event.target.closest('[data-service-agent-nickname-option]');
        if (nicknameOption) {
            chooseServiceAgentNicknameOption(nicknameOption);
            event.preventDefault();
            return;
        }

        if (!event.target.closest('[data-service-agent-nickname-picker]')) {
            closeServiceAgentNicknameMenus(null);
        }

        var serviceAgentPaymentPreviewOpen = event.target.closest('[data-service-agent-payment-preview-open]');
        if (serviceAgentPaymentPreviewOpen) {
            openServiceAgentPaymentPreview(serviceAgentPaymentPreviewOpen);
            event.preventDefault();
            return;
        }

        var serviceAgentPaymentPreviewClose = event.target.closest('[data-service-agent-payment-preview-close]');
        if (serviceAgentPaymentPreviewClose) {
            closeServiceAgentPaymentPreview();
            event.preventDefault();
            return;
        }

        var customerServiceImagePreviewOpen = event.target.closest('[data-customer-service-image-preview-open]');
        if (customerServiceImagePreviewOpen) {
            openCustomerServiceImagePreview(customerServiceImagePreviewOpen);
            event.preventDefault();
            return;
        }

        var customerServiceImagePreviewClose = event.target.closest('[data-customer-service-image-preview-close]');
        if (customerServiceImagePreviewClose) {
            closeCustomerServiceImagePreview();
            event.preventDefault();
            return;
        }

        var rechargeServiceClear = event.target.closest('[data-member-recharge-service-clear]');
        if (rechargeServiceClear) {
            triggerMemberRechargeServiceClear();
            event.preventDefault();
            return;
        }

        var rechargeServiceClose = event.target.closest('[data-member-recharge-service-close]');
        if (rechargeServiceClose) {
            closeMemberRechargeServiceModal();
            event.preventDefault();
            return;
        }

        var rechargeService = event.target.closest('[data-member-recharge-service]');
        if (rechargeService) {
            var rechargeServiceUrl = String(rechargeService.getAttribute('href') || '').trim();
            if (rechargeServiceUrl) {
                event.preventDefault();
                if (document.body && document.body.classList.contains('standalone-modal-post') && isEmbeddedRechargeServiceUrl(rechargeServiceUrl)) {
                    openMemberRechargeServiceModal(rechargeServiceUrl);
                } else {
                    window.location.href = rechargeServiceUrl;
                }
            }
            return;
        }

        var submitTrigger = event.target.closest('[data-submit-form]');
        if (submitTrigger) {
            var formSelector = String(submitTrigger.getAttribute('data-submit-form') || '').trim();
            var targetForm = formSelector ? document.querySelector(formSelector) : null;

            if (targetForm) {
                if (typeof targetForm.requestSubmit === 'function') {
                    targetForm.requestSubmit();
                } else {
                    targetForm.submit();
                }
            }

            event.preventDefault();
            return;
        }

        var alertTrigger = event.target.closest('[data-alert-message]');
        if (alertTrigger) {
            toast(alertTrigger.getAttribute('data-alert-message') || '', 'info');
            event.preventDefault();
            return;
        }

        var rechargeTrigger = event.target.closest('[data-member-recharge-open]');
        if (rechargeTrigger) {
            var rechargeModal = document.getElementById(String(rechargeTrigger.getAttribute('data-member-recharge-open') || '').trim());
            closeMemberRechargeModals(rechargeModal);
            setMemberRechargeModal(rechargeModal, true);
            event.preventDefault();
            return;
        }

        var rechargeMethod = event.target.closest('[data-member-recharge-method]');
        if (rechargeMethod) {
            var rechargeMethodModal = rechargeMethod.closest('[data-member-recharge-modal]');
            fetchMemberRechargePaymentSettings(rechargeMethodModal, true).catch(handleMemberRechargePaymentError);
            setMemberRechargeMethod(
                rechargeMethodModal,
                String(rechargeMethod.getAttribute('data-member-recharge-method') || '').trim()
            );
            event.preventDefault();
            return;
        }

        var rechargeQrRefresh = event.target.closest('[data-member-recharge-qr-refresh]');
        if (rechargeQrRefresh) {
            refreshMemberRechargeQr(rechargeQrRefresh);
            event.preventDefault();
            return;
        }

        var rechargeCopy = event.target.closest('[data-member-recharge-copy]');
        if (rechargeCopy) {
            copyMemberRechargeText(rechargeCopy);
            event.preventDefault();
            return;
        }

        var rechargeClose = event.target.closest('[data-member-recharge-close]');
        if (rechargeClose) {
            setMemberRechargeModal(rechargeClose.closest('[data-member-recharge-modal]'), false);
            event.preventDefault();
            return;
        }

        var editTrigger = event.target.closest('[data-member-edit-open]');
        if (editTrigger) {
            var editModal = document.getElementById(String(editTrigger.getAttribute('data-member-edit-open') || '').trim());
            closeMemberEditModals(editModal);
            setMemberEditModal(editModal, true);
            event.preventDefault();
            return;
        }

        var editClose = event.target.closest('[data-member-edit-close]');
        if (editClose) {
            setMemberEditModal(editClose.closest('[data-member-edit-modal]'), false);
            event.preventDefault();
            return;
        }

        var passwordTrigger = event.target.closest('[data-password-toggle]');
        if (passwordTrigger) {
            var passwordSelector = String(passwordTrigger.getAttribute('data-password-toggle') || '').trim();
            var passwordInput = passwordSelector ? document.querySelector(passwordSelector) : null;
            var isVisible;

            if (passwordInput) {
                isVisible = passwordInput.getAttribute('type') === 'text';
                passwordInput.setAttribute('type', isVisible ? 'password' : 'text');
                passwordTrigger.textContent = isVisible ? '显示' : '隐藏';
                passwordInput.setAttribute('data-password-visible', isVisible ? '0' : '1');
                syncPasswordToggleButton(passwordTrigger, !isVisible);
            }

            event.preventDefault();
            return;
        }

        var memberPurchaseCard = event.target.closest('[data-member-purchase-card]');
        if (memberPurchaseCard) {
            var purchaseStatus = String(memberPurchaseCard.getAttribute('data-purchase-status') || '').trim();
            var purchaseUrl = String(memberPurchaseCard.getAttribute('data-purchase-post-url') || memberPurchaseCard.getAttribute('href') || '').trim();
            var purchaseTitle = String(memberPurchaseCard.getAttribute('data-purchase-title') || memberPurchaseCard.textContent || '帖子阅读').replace(/\s+/g, ' ').trim();

            event.preventDefault();
            if (purchaseStatus === 'deleted' || memberPurchaseCard.querySelector('.member-purchase-state.is-offline')) {
                toast('该购买记录对应的帖子已下架，暂时无法查看。', 'info', '已下架');
                return;
            }

            if (!openMemberPurchasePostModal(purchaseUrl, purchaseTitle)) {
                toast('内容打开失败，请稍后重试。', 'error', '操作失败');
            }
            return;
        }

        var servicePaymentDelete = event.target.closest('[data-service-agent-payment-delete-confirm]');
        if (servicePaymentDelete) {
            var servicePaymentDeleteForm = servicePaymentDelete.form;
            var servicePaymentDeleteMessage = servicePaymentDelete.getAttribute('data-service-agent-payment-delete-confirm') || '确认删除这张收款二维码吗？';

            if (!servicePaymentDeleteForm) {
                return;
            }

            event.preventDefault();
            appConfirm(servicePaymentDeleteMessage, '删除二维码', '删除', '取消').then(function (confirmed) {
                if (!confirmed) {
                    return;
                }

                submitServiceAgentPaymentDelete(servicePaymentDelete);
            });
            return;
        }

        var trigger = event.target.closest('[data-confirm-link]');
        if (!trigger) {
            return;
        }

        var message = trigger.getAttribute('data-confirm-link');
        var href = trigger.getAttribute('href') || '';

        event.preventDefault();
        appConfirm(message || '确认执行此操作吗？', '确认操作', '确定', '取消').then(function (confirmed) {
            if (!confirmed) {
                return;
            }

            if (href) {
                window.location.href = href;
            }
        });
    });

    document.addEventListener('load', function (event) {
        var target = event.target;

        if (target && target.matches && target.matches('[data-member-recharge-qr]')) {
            if (target.getAttribute('data-member-recharge-qr-missing') === '1') {
                return;
            }
            setMemberRechargeQrStatus(target, false);
        }
    }, true);

    document.addEventListener('error', function (event) {
        var target = event.target;

        if (target && target.matches && target.matches('[data-member-recharge-qr]')) {
            setMemberRechargeQrStatus(target, true);
        }
    }, true);

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            var rechargeServiceModal = document.querySelector('[data-member-recharge-service-modal]:not([hidden])');
            if (rechargeServiceModal) {
                closeMemberRechargeServiceModal();
                event.preventDefault();
                return;
            }

            closeServiceAgentNicknameMenus(null);
            closeServiceAgentPaymentPreview();
            closeCustomerServiceImagePreview();
            closeMemberRechargeModals(null);
            closeMemberEditModals(null);
        }
    });

    document.addEventListener('change', function (event) {
        var trigger = event.target.closest('[data-check-all]');
        var selector;

        if (!trigger) {
            return;
        }

        selector = String(trigger.getAttribute('data-check-all') || '').trim();
        if (!selector) {
            return;
        }

        Array.prototype.forEach.call(document.querySelectorAll(selector), function (item) {
            item.checked = !!trigger.checked;
        });
    });

    document.addEventListener('reset', function (event) {
        var form = event.target;

        if (!form || !form.querySelectorAll) {
            return;
        }

        window.setTimeout(function () {
            Array.prototype.forEach.call(form.querySelectorAll('[data-password-toggle]'), function (button) {
                var selector = String(button.getAttribute('data-password-toggle') || '').trim();
                var input = selector ? document.querySelector(selector) : null;

                if (!input) {
                    return;
                }

                input.setAttribute('type', 'password');
                input.setAttribute('data-password-visible', '0');
                syncPasswordToggleButton(button, false);
            });
        }, 0);
    });

    window.AppUI = {
        toast: toast,
        confirm: appConfirm,
        prompt: appPrompt,
        initJsonCharts: initJsonCharts,
        initPasswordToggles: initPasswordToggles,
        compressImageForUpload: compressImageForUpload
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            bootCommonUi(document);
        });
    } else {
        bootCommonUi(document);
    }

    window.addEventListener('pageshow', reloadStaleCustomerServiceAuthPage, true);
    window.addEventListener('focus', reloadStaleCustomerServiceAuthPage, true);
    window.addEventListener('storage', function (event) {
        if (event && event.key === 'front_auth_changed_at') {
            reloadStaleCustomerServiceAuthPage();
        }
    }, true);
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            reloadStaleCustomerServiceAuthPage();
        }
    }, true);
})();
