(function () {
    function toast(message, type) {
        if (window.AppUI && typeof window.AppUI.toast === 'function') {
            window.AppUI.toast(message, type || 'info');
            return;
        }

        if (window.console && message) {
            window.console.warn(message);
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

    function setFormError(form, message) {
        var target = form.querySelector('[data-form-error]');
        if (!target) {
            return;
        }

        target.textContent = message || '';
        target.classList.toggle('hidden', !message);
    }

    function submitForm(form) {
        var button = form.querySelector('[type="submit"]');
        var endpoint = form.getAttribute('action') || '';
        var formData = new FormData(form);

        setFormError(form, '');
        if (button) {
            button.disabled = true;
        }

        fetch(endpoint, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        }).then(toJson).then(function (payload) {
            if (!payload || payload.success !== true) {
                throw new Error(payload && payload.message ? payload.message : '操作失败。');
            }

            toast(payload.message || '回复已发布。', 'success');
            window.setTimeout(function () {
                window.location.reload();
            }, 450);
        }).catch(function (error) {
            setFormError(form, error.message);
            toast(error.message, 'error');
        }).finally(function () {
            if (button) {
                button.disabled = false;
            }
        });
    }

    function toggleLike(thread, button) {
        var apiUrl = thread.getAttribute('data-api-url') || '';
        var token = thread.getAttribute('data-token') || '';
        var commentId = button.getAttribute('data-comment-id') || '';
        var payload = new URLSearchParams();

        if (!apiUrl || !token || !commentId || button.disabled) {
            return;
        }

        payload.append('action', 'comment.like');
        payload.append('_token', token);
        payload.append('comment_id', commentId);
        button.disabled = true;

        fetch(apiUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: payload.toString()
        }).then(toJson).then(function (result) {
            var data;
            var countNode;

            if (!result || result.success !== true) {
                throw new Error(result && result.message ? result.message : '操作失败。');
            }

            data = result.data || {};
            countNode = button.querySelector('[data-comment-like-count]');
            button.classList.toggle('is-active', !!data.liked);
            if (countNode && typeof data.like_count !== 'undefined') {
                countNode.textContent = String(data.like_count);
            }
        }).catch(function (error) {
            toast(error.message, 'error');
        }).finally(function () {
            button.disabled = false;
        });
    }

    function focusReplyForm(thread, button) {
        var form = thread.querySelector('[data-comment-form]');
        var parentInput = form ? form.querySelector('[data-comment-parent-id]') : null;
        var textarea = form ? form.querySelector('textarea[name="content"]') : null;
        var target = form ? form.querySelector('[data-comment-reply-target]') : null;
        var targetText = form ? form.querySelector('[data-comment-reply-target-text]') : null;
        var title = form ? form.querySelector('[data-comment-form-title]') : null;
        var commentId = button.getAttribute('data-comment-id') || '0';
        var author = button.getAttribute('data-comment-author') || '该用户';

        if (!form || !parentInput || !textarea || textarea.disabled) {
            return;
        }

        parentInput.value = commentId;
        if (target && targetText) {
            target.hidden = false;
            targetText.textContent = '正在回复：' + author;
        }

        if (title) {
            title.textContent = '回复 ' + author;
        }

        textarea.focus();
        form.scrollIntoView({
            behavior: 'smooth',
            block: 'nearest'
        });
    }

    function resetReplyTarget(form) {
        var parentInput = form.querySelector('[data-comment-parent-id]');
        var target = form.querySelector('[data-comment-reply-target]');
        var targetText = form.querySelector('[data-comment-reply-target-text]');
        var title = form.querySelector('[data-comment-form-title]');

        if (parentInput) {
            parentInput.value = '0';
        }

        if (target) {
            target.hidden = true;
        }

        if (targetText) {
            targetText.textContent = '';
        }

        if (title) {
            title.textContent = '写下你的回复';
        }
    }

    document.addEventListener('click', function (event) {
        var likeButton = event.target.closest('[data-comment-like]');
        var replyButton = event.target.closest('[data-comment-reply]');
        var cancelButton = event.target.closest('[data-comment-reply-cancel]');
        var thread;
        var form;

        if (likeButton) {
            thread = likeButton.closest('[data-comment-thread]');
            if (thread) {
                toggleLike(thread, likeButton);
            }
            event.preventDefault();
            return;
        }

        if (replyButton) {
            thread = replyButton.closest('[data-comment-thread]');
            if (thread) {
                focusReplyForm(thread, replyButton);
            }
            event.preventDefault();
            return;
        }

        if (cancelButton) {
            form = cancelButton.closest('[data-comment-form]');
            if (form) {
                resetReplyTarget(form);
            }
            event.preventDefault();
        }
    });

    document.addEventListener('submit', function (event) {
        var form = event.target;

        if (!form.matches('[data-comment-form]')) {
            return;
        }

        event.preventDefault();
        submitForm(form);
    });
}());
