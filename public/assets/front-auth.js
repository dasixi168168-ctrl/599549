(function () {
    function eyeIconSvg(isVisible) {
        if (isVisible) {
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18"></path><path d="M10.58 10.58a2 2 0 0 0 2.84 2.84"></path><path d="M9.88 5.09A10.94 10.94 0 0 1 12 4c5 0 9.27 3.11 11 7.5a11.8 11.8 0 0 1-2.16 3.35"></path><path d="M6.61 6.61C4.62 7.85 3.14 9.53 2 11.5 3.73 15.89 8 19 13 19a10.7 10.7 0 0 0 4.32-.88"></path></svg>';
        }

        return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
    }

    function syncPasswordToggleButton(button) {
        var isVisible;

        if (!button) {
            return;
        }

        isVisible = button.getAttribute('aria-pressed') === 'true';
        button.innerHTML = eyeIconSvg(isVisible);
        button.setAttribute('aria-label', isVisible ? '隐藏密码' : '显示密码');
        button.setAttribute('title', isVisible ? '隐藏密码' : '显示密码');
    }

    function createField(labelText, inputHtml, extraClass) {
        var wrapper = document.createElement('div');
        var label = document.createElement('label');

        if (extraClass) {
            wrapper.className = extraClass;
        }

        label.className = 'mb-2 block text-sm font-bold text-slate-700';
        label.textContent = labelText;
        wrapper.appendChild(label);
        wrapper.insertAdjacentHTML('beforeend', inputHtml);

        return wrapper;
    }

    function removeDuplicateLoginChip() {
        Array.prototype.forEach.call(
            document.querySelectorAll('.front-auth-card .front-chip-row a[href*="mode=login"].bg-blue-600.text-white'),
            function (button) {
                if (button.parentNode) {
                    button.parentNode.removeChild(button);
                }
            }
        );
    }

    function normalizeRegisterForm(form) {
        var recoveryInputs = form.querySelectorAll('input[name="recovery_answer"]');

        while (recoveryInputs.length > 1) {
            var staleInput = recoveryInputs[0];
            var staleField = staleInput.closest('div');

            if (staleField && staleField.parentNode) {
                staleField.parentNode.removeChild(staleField);
            } else if (staleInput.parentNode) {
                staleInput.parentNode.removeChild(staleInput);
            }

            recoveryInputs = form.querySelectorAll('input[name="recovery_answer"]');
        }

        if (!recoveryInputs.length) {
            var actions = form.querySelector('.front-form-actions');
            if (actions) {
                actions.insertAdjacentElement(
                    'beforebegin',
                    createField(
                        '找回验证信息',
                        '<input class="auth-input" type="text" name="recovery_answer" autocomplete="off" placeholder="请设置一条找回密码验证信息">',
                        ''
                    )
                );
            }
        }
    }

    function normalizeResetForm(form) {
        var actionInput = form.querySelector('input[name="action"]');
        var noteField = form.querySelector('textarea[name="note"]');
        var submitButton = form.querySelector('.front-form-actions button[type="submit"]');
        var actions = form.querySelector('.front-form-actions');

        if (actionInput) {
            actionInput.value = 'password_reset.verify_reset';
        }

        if (noteField) {
            var noteWrapper = noteField.closest('div');
            if (noteWrapper) {
                noteWrapper.parentNode.replaceChild(
                    createField(
                        '找回验证信息',
                        '<input class="auth-input" type="text" name="recovery_answer" autocomplete="off" placeholder="请输入注册时设置的找回验证信息">',
                        ''
                    ),
                    noteWrapper
                );
            }
        } else if (!form.querySelector('input[name="recovery_answer"]') && actions) {
            actions.insertAdjacentElement(
                'beforebegin',
                createField(
                    '找回验证信息',
                    '<input class="auth-input" type="text" name="recovery_answer" autocomplete="off" placeholder="请输入注册时设置的找回验证信息">',
                    ''
                )
            );
        }

        if (actions && !form.querySelector('input[name="password"]')) {
            actions.insertAdjacentElement(
                'beforebegin',
                createField(
                    '确认新密码',
                    '<input class="auth-input" type="password" name="confirm_password" autocomplete="new-password" placeholder="请再次输入新密码">',
                    ''
                )
            );
            actions.insertAdjacentElement(
                'beforebegin',
                createField(
                    '设置新密码',
                    '<input class="auth-input" type="password" name="password" autocomplete="new-password" placeholder="请输入新的登录密码">',
                    'front-auth-field'
                )
            );
        }

        if (submitButton) {
            submitButton.textContent = '验证并重置密码';
        }
    }

    function normalizeFrontAuthUi() {
        var params = new URLSearchParams(window.location.search || '');
        var mode = params.get('mode') || 'login';
        var form = document.querySelector('.front-auth-form');
        var buttons = [];

        removeDuplicateLoginChip();

        if (!form) {
            return;
        }

        if (mode === 'register') {
            normalizeRegisterForm(form);
        }

        if (mode === 'reset') {
            normalizeResetForm(form);
        }

        if (window.AppUI && typeof window.AppUI.initPasswordToggles === 'function') {
            window.AppUI.initPasswordToggles(form);
        }

        buttons = form.querySelectorAll('.auth-input-toggle, .password-input-toggle');
        Array.prototype.forEach.call(buttons, function (button) {
            syncPasswordToggleButton(button);
        });
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('.front-auth-form .auth-input-toggle, .front-auth-form .password-input-toggle');

        if (!button) {
            return;
        }

        window.setTimeout(function () {
            syncPasswordToggleButton(button);
        }, 0);
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', normalizeFrontAuthUi);
    } else {
        normalizeFrontAuthUi();
    }

    window.setTimeout(normalizeFrontAuthUi, 80);
})();
