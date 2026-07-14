<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/front_security.php';
front_security_apply(array(
    'rate_limit' => false,
    'output_buffer' => false,
    'cache_control' => 'no-store, no-cache, must-revalidate, max-age=0',
));

require dirname(__DIR__) . '/bootstrap/app.php';

if (!app()->isInstalled()) {
    json_response(array(
        'success' => false,
        'message' => '系统尚未安装，请先完成在线安装。',
    ), 503);
}

if (!is_post()) {
    json_response(array(
        'success' => false,
        'message' => '仅支持 POST 请求。',
    ), 405);
}

try {
    if (!\App\Core\Csrf::validate((string) input('_token', ''), 'api')) {
        json_response(array(
            'success' => false,
            'message' => '表单令牌已失效，请刷新页面后重试。',
        ), 419);
    }

    $action = (string) input('action', '');
    $user = current_user();
    $admin = current_admin();
    $markFrontAuthChanged = static function () {
        $stamp = (string) ((int) floor(microtime(true) * 1000));
        $secure = function_exists('front_security_is_https') && front_security_is_https();
        if (!headers_sent()) {
            setcookie('front_auth_changed_at', $stamp, array(
                'expires' => time() + 2592000,
                'path' => '/',
                'secure' => $secure,
                'httponly' => false,
                'samesite' => 'Lax',
            ));
        }
    };
    $commitSession = static function () {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    };
    $normalizePostRegion = static function ($region) {
        return (string) $region === 'hongkong' ? 'hongkong' : 'macau';
    };
    $normalizePostView = static function ($view, $default = 'manage') {
        $view = (string) $view;

        return in_array($view, array('manage', 'compose', 'published', 'recycle'), true) ? $view : $default;
    };
    $isAjaxRequest = static function () {
        $requestedWith = isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) : '';
        if ($requestedWith === 'xmlhttprequest') {
            return true;
        }

        $accept = isset($_SERVER['HTTP_ACCEPT']) ? strtolower((string) $_SERVER['HTTP_ACCEPT']) : '';

        return strpos($accept, 'application/json') !== false;
    };
    $insufficientPointsResponse = static function () {
        json_response(array(
            'success' => false,
            'message' => '积分不足，请先充值后再购买。3 秒后弹出选择充值方式。',
            'recharge_modal' => 'member-recharge-modal',
            'recharge_delay' => 3,
        ), 400);
    };
    $customerServiceAgentLoginResponse = static function () {
        json_response(array(
            'success' => false,
            'message' => '请先登录客服账号。',
            'redirect' => public_url('admin.php'),
        ), 401);
    };
    $customerServiceAgentActive = static function () {
        $agentEntryRemembered = (string) \App\Core\Session::get('customer_service_agent_entry', '0') === '1';
        if ((string) input('agent', '0') !== '1' && !$agentEntryRemembered) {
            return false;
        }
        if ((int) \App\Core\Session::get('customer_service_agent_id', 0) <= 0) {
            return false;
        }

        try {
            return app()->support()->currentAgent() !== null;
        } catch (\Throwable $exception) {
            return false;
        }
    };

    switch ($action) {
        case 'draw.latest':
            $region = $normalizePostRegion(input('region', 'macau'));
            json_response(array(
                'success' => true,
                'draw' => app()->prediction()->latestHomepageDraw($region),
                'current_issue' => app()->admins()->managedIssuePrefixSnapshotByRegion($region),
            ));
            break;

        case 'auth.login':
            $loggedInUser = app()->users()->attemptLogin(
                (string) input('username', ''),
                (string) input('password', ''),
                false
            );
            app()->auth()->issueFrontMemberCookie($loggedInUser);
            $markFrontAuthChanged();
            $commitSession();
            json_response(array(
                'success' => true,
                'message' => '登录成功，欢迎回来。',
                'redirect' => public_url('member.php') . '?region=' . urlencode((string) input('region', 'macau')),
            ));
            break;

        case 'auth.admin_login':
            app()->admins()->ensureReady();
            app()->admins()->attemptLogin(
                (string) input('username', ''),
                (string) input('password', '')
            );
            json_response(array(
                'success' => true,
                'message' => '后台登录成功。',
                'redirect' => public_url('admin.php'),
            ));
            break;

        case 'auth.register':
            $registeredUser = app()->users()->register($_POST);
            app()->auth()->issueFrontMemberCookie($registeredUser);
            $markFrontAuthChanged();
            $commitSession();
            json_response(array(
                'success' => true,
                'message' => '注册成功，已自动登录。',
                'redirect' => public_url('member.php') . '?region=' . urlencode((string) input('region', 'macau')),
                'reload' => true,
                'data' => array(
                    'user_id' => (int) ($registeredUser['id'] ?? 0),
                    'username' => (string) ($registeredUser['username'] ?? ''),
                ),
            ));
            break;

        case 'password_reset.verify_reset':
            app()->users()->resetPasswordByRecovery($_POST);
            json_response(array(
                'success' => true,
                'message' => '密码已重置，请使用新密码登录。',
                'redirect' => public_url('member.php') . '?region=' . urlencode((string) input('region', 'macau')) . '&mode=login',
            ));
            break;

        case 'auth.logout':
            app()->auth()->logout();
            app()->auth()->clearFrontMemberCookie();
            $markFrontAuthChanged();
            $commitSession();
            json_response(array(
                'success' => true,
                'message' => '已安全退出当前账号。',
                'reload' => true,
            ));
            break;

        case 'password_reset.reset':
            app()->users()->resetPasswordByRecovery($_POST);
            json_response(array(
                'success' => true,
                'message' => '找回申请已提交，请等待处理。',
                'reload' => true,
            ));
            break;

        case 'profile.update':
            if (!$user) {
                throw new RuntimeException('请先登录。');
            }
            app()->users()->updateProfile($user['id'], $_POST);
            json_response(array(
                'success' => true,
                'message' => '个人资料已更新。',
                'reload' => true,
            ));
            break;

        case 'profile.avatar':
            throw new RuntimeException('头像上传功能已关闭。');

        case 'profile.password':
            if (!$user) {
                throw new RuntimeException('请先登录。');
            }
            app()->users()->changePassword($user['id'], $_POST);
            json_response(array(
                'success' => true,
                'message' => '密码修改成功，请牢记新密码。',
                'reload' => true,
            ));
            break;

        case 'profile.recovery':
            if (!$user) {
                throw new RuntimeException('请先登录。');
            }
            app()->users()->changeRecoveryAnswer($user['id'], $_POST);
            json_response(array(
                'success' => true,
                'message' => '找回验证信息已更新。',
                'reload' => true,
            ));
            break;

        case 'prediction.logs.delete':
            if (!$user) {
                throw new RuntimeException('请先登录。');
            }
            $deletedCount = app()->prediction()->deleteMemberPredictionLogs(
                (int) $user['id'],
                (array) input('prediction_ids', array())
            );
            json_response(array(
                'success' => true,
                'message' => '已删除 ' . $deletedCount . ' 条预测记录。',
                'reload' => true,
            ));
            break;

        case 'post.create':
            if (!$user) {
                throw new RuntimeException('请先登录后再发帖。');
            }
            app()->users()->assertCanCreatePost($user);
            $post = app()->posts()->createPost($user['id'], $_POST);
            if (is_admin()) {
                app()->logs()->admin('posts', 'create', '用户发帖：' . $post['title'], 'post', (string) $post['id'], $user['id']);
            } else {
                app()->logs()->system('post', '会员发帖成功', 'info', array('post_id' => $post['id'], 'user_id' => $user['id']));
            }
            json_response(array(
                'success' => true,
                'message' => '帖子发布成功。',
                'redirect' => public_url('post.php') . '?id=' . $post['id'],
            ));
            break;

        case 'post.reply':
            if (!$user) {
                throw new RuntimeException('请先登录后再回复。');
            }
            app()->users()->assertCanComment($user);
            app()->posts()->addReply((int) input('post_id', 0), $user['id'], (string) input('content', ''), (int) input('parent_id', 0));
            json_response(array(
                'success' => true,
                'message' => '回复已发布。',
                'reload' => true,
            ));
            break;

        case 'post.view_count':
            $postId = (int) input('post_id', 0);
            if ($postId <= 0) {
                throw new RuntimeException('帖子不存在或已删除');
            }

            $post = app()->posts()->findPost($postId);
            if (!$post) {
                throw new RuntimeException('帖子不存在或已删除');
            }

            json_response(array(
                'success' => true,
                'data' => array(
                    'display_view_count' => app()->posts()->currentDisplayedViewCount($postId),
                ),
            ));
            break;

        case 'post.buy':
            $postBuyByCustomerServiceAgent = $customerServiceAgentActive();
            if ($postBuyByCustomerServiceAgent) {
                $postBuyId = (int) input('post_id', 0);
                if ($postBuyId <= 0) {
                    throw new RuntimeException('帖子不存在。');
                }
                if (!isset($_SESSION['customer_service_agent_free_post_access']) || !is_array($_SESSION['customer_service_agent_free_post_access'])) {
                    $_SESSION['customer_service_agent_free_post_access'] = array();
                }
                $_SESSION['customer_service_agent_free_post_access'][$postBuyId] = time();
                json_response(array(
                    'success' => true,
                    'message' => '购买成功，接待客服账号已免积分查看资料。',
                    'reload' => true,
                ));
            }
            if (!$user) {
                throw new RuntimeException('请先登录后再购买。');
            }
            try {
                app()->posts()->buyPost((int) input('post_id', 0), $user);
            } catch (RuntimeException $exception) {
                if ($exception->getMessage() === '积分不足，请先充值后再购买。') {
                    $insufficientPointsResponse();
                }

                throw $exception;
            }
            json_response(array(
                'success' => true,
                'message' => '购买成功，已扣除对应积分。',
                'reload' => true,
            ));
            break;

        case 'post.customer_service.update':
            $customerServiceAgent = app()->support()->currentAgent();
            if (!$customerServiceAgent) {
                $customerServiceAgentLoginResponse();
            }
            $customerServicePostId = (int) input('post_id', 0);
            $customerServiceSaveTarget = trim((string) input('save_target', 'combined'));
            $customerServiceSaveMessage = '帖子资料已更新。';
            $customerServiceReload = true;

            if ($customerServiceSaveTarget === 'content') {
                $customerServicePost = app()->posts()->findPost($customerServicePostId);
                if (!$customerServicePost) {
                    throw new RuntimeException('帖子不存在或不可编辑。');
                }
                $customerServiceContentPayload = $_POST;
                $customerServiceContentPayload['price'] = (string) max(0, (int) ($customerServicePost['price'] ?? 0));
                app()->posts()->updatePostContentByCustomerService(
                    $customerServicePostId,
                    $customerServiceContentPayload,
                    $customerServiceAgent
                );
                $customerServiceSaveMessage = '当前期数资料内容已保存。';
            } elseif ($customerServiceSaveTarget === 'price') {
                app()->posts()->updatePostPriceByCustomerService(
                    $customerServicePostId,
                    input('price', ''),
                    $customerServiceAgent
                );
                $customerServiceSaveMessage = '出售积分已保存。';
                $customerServiceReload = false;
            } elseif ($customerServiceSaveTarget === 'waiting_display') {
                $customerServicePost = app()->posts()->findPost($customerServicePostId);
                if (!$customerServicePost) {
                    throw new RuntimeException('帖子不存在或不可编辑。');
                }
                $customerServiceWaitingDisplayContent = str_replace(
                    array("\r\n", "\r"),
                    "\n",
                    trim((string) input('waiting_display_content', ''))
                );
                if ($customerServiceWaitingDisplayContent === '') {
                    throw new RuntimeException('资料内容更新状态正文不能为空。');
                }
                $customerServiceWaitingDisplayLength = function_exists('mb_strlen')
                    ? mb_strlen($customerServiceWaitingDisplayContent, 'UTF-8')
                    : strlen($customerServiceWaitingDisplayContent);
                if ($customerServiceWaitingDisplayLength > 300) {
                    throw new RuntimeException('资料内容更新状态正文不能超过 300 个字符。');
                }
                $customerServicePostRegion = (string) ($customerServicePost['region'] ?? 'macau') === 'hongkong'
                    ? 'hongkong'
                    : 'macau';
                app()->admins()->saveManagedPostWaitingDisplayContent(
                    $customerServicePostRegion,
                    $customerServiceWaitingDisplayContent
                );
                app()->logs()->system('post', '在线客服保存资料更新状态正文', 'info', array(
                    'post_id' => $customerServicePostId,
                    'region' => $customerServicePostRegion,
                    'agent_id' => (int) ($customerServiceAgent['id'] ?? 0),
                ));
                $customerServiceSaveMessage = '资料内容更新状态正文已保存。';
                $customerServiceReload = false;
            } elseif ($customerServiceSaveTarget === 'combined' || $customerServiceSaveTarget === '') {
                app()->posts()->updatePostContentByCustomerService(
                    $customerServicePostId,
                    $_POST,
                    $customerServiceAgent
                );
            } else {
                throw new RuntimeException('帖子资料保存目标无效。');
            }
            json_response(array(
                'success' => true,
                'message' => $customerServiceSaveMessage,
                'reload' => $customerServiceReload,
            ));
            break;

        case 'post.like':
            $likeResult = app()->posts()->toggleLike((int) input('post_id', 0), $user ?: array());
            json_response(array(
                'success' => true,
                'message' => !empty($likeResult['liked']) ? '点赞成功。' : '已取消点赞。',
                'data' => $likeResult,
            ));
            break;

        case 'post.favorite':
            if (!$user) {
                throw new RuntimeException('请先登录后再收藏。');
            }
            $favoriteResult = app()->posts()->toggleFavorite((int) input('post_id', 0), $user);
            json_response(array(
                'success' => true,
                'message' => !empty($favoriteResult['active']) ? '收藏成功。' : '已取消收藏。',
                'data' => $favoriteResult,
            ));
            break;

        case 'post.follow':
            if (!$user) {
                throw new RuntimeException('请先登录后再关注。');
            }
            $followResult = app()->posts()->toggleFollow((int) input('post_id', 0), $user);
            json_response(array(
                'success' => true,
                'message' => !empty($followResult['active']) ? '关注成功。' : '已取消关注。',
                'data' => $followResult,
            ));
            break;

        case 'comment.like':
            throw new RuntimeException('评论区点赞功能已关闭。');

        case 'customer_service.public.status':
            json_response(array(
                'success' => true,
                'data' => app()->support()->publicStatusPayload(),
            ));
            break;

        case 'customer_service.member.poll':
            if (!$user) {
                throw new RuntimeException('请先登录后再使用在线客服。');
            }
            json_response(array(
                'success' => true,
                'data' => app()->support()->memberPayload($user['id']),
            ));
            break;

        case 'customer_service.member.unread':
            if (!$user) {
                throw new RuntimeException('请先登录后再使用在线客服。');
            }
            json_response(array(
                'success' => true,
                'data' => app()->support()->memberUnreadPayload((int) $user['id']),
            ));
            break;

        case 'customer_service.agent.unread':
            $customerServiceAgent = app()->support()->currentAgent();
            if (!$customerServiceAgent) {
                $customerServiceAgentLoginResponse();
            }
            json_response(array(
                'success' => true,
                'data' => app()->support()->agentUnreadPayload($customerServiceAgent),
            ));
            break;

        case 'customer_service.member.send':
            if (!$user) {
                throw new RuntimeException('请先登录后再使用在线客服。');
            }
            json_response(array(
                'success' => true,
                'message' => '消息已发送。',
                'data' => app()->support()->sendMemberMessage(
                    (int) $user['id'],
                    $_POST,
                    isset($_FILES['attachment']) ? $_FILES['attachment'] : null
                ),
            ));
            break;

        case 'customer_service.member.clear':
            if (!$user) {
                throw new RuntimeException('请先登录后再使用在线客服。');
            }
            json_response(array(
                'success' => true,
                'message' => '聊天记录已删除。',
                'data' => app()->support()->clearMemberRecords((int) $user['id']),
            ));
            break;

        case 'customer_service.member.recall':
            if (!$user) {
                throw new RuntimeException('请先登录后再使用在线客服。');
            }
            throw new RuntimeException('会员端已取消消息撤回。');

        case 'customer_service.member.payment_settings':
            if (!$user) {
                throw new RuntimeException('请先登录后再查看充值二维码。');
            }
            if (!headers_sent()) {
                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                header('Pragma: no-cache');
                header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
            }
            $memberPaymentSettings = app()->support()->paymentSettings();
            json_response(array(
                'success' => true,
                'data' => array(
                    'qrs' => app()->support()->paymentQrLists(),
                    'usdt_address' => isset($memberPaymentSettings['usdt_address']) ? (string) $memberPaymentSettings['usdt_address'] : '',
                    'version' => (string) time(),
                ),
            ));
            break;

        case 'customer_service.agent.login':
            $agent = app()->support()->loginAgent(
                (string) input('username', ''),
                (string) input('password', '')
            );
            json_response(array(
                'success' => true,
                'message' => '客服登录成功。',
                'redirect' => public_url('service.php') . '?agent=1&region=' . urlencode((string) input('region', 'macau')),
                'data' => array(
                    'agent' => $agent ? array(
                        'id' => (int) ($agent['id'] ?? 0),
                        'username' => (string) ($agent['username'] ?? ''),
                        'display_name' => (string) ($agent['display_name'] ?? ''),
                    ) : null,
                ),
            ));
            break;

        case 'customer_service.agent.logout':
            app()->support()->logoutAgent(true);
            if (!$isAjaxRequest()) {
                redirect(public_url('admin.php'));
            }
            json_response(array(
                'success' => true,
                'message' => '已退出客服接待。',
                'redirect' => public_url('admin.php'),
            ));
            break;

        case 'customer_service.agent.presence':
            $customerServiceAgent = app()->support()->currentAgent();
            if (!$customerServiceAgent) {
                $customerServiceAgentLoginResponse();
            }
            $customerServiceAgentPresence = app()->support()->setAgentServing(
                $customerServiceAgent,
                (string) input('online', '0') === '1'
            );
            json_response(array(
                'success' => true,
                'message' => (string) input('online', '0') === '1' ? '已在线接待。' : '已离线。',
                'data' => $customerServiceAgentPresence,
            ));
            break;

        case 'customer_service.agent.settings':
            $customerServiceAgent = app()->support()->currentAgent();
            if (!$customerServiceAgent) {
                $customerServiceAgentLoginResponse();
            }
            $customerServiceAgent = app()->support()->saveAgentSettings($customerServiceAgent, $_POST);
            if (isset($_POST['serving'])) {
                app()->support()->setAgentServing(
                    $customerServiceAgent,
                    (string) input('serving', '0') === '1'
                );
            }
            json_response(array(
                'success' => true,
                'message' => '客服接待设置已保存。',
                'data' => app()->support()->agentPayload(
                    (int) input('session_id', 0),
                    (string) input('status', 'all'),
                    $customerServiceAgent,
                    false
                ),
            ));
            break;

        case 'customer_service.agent.nickname_delete':
            $customerServiceAgent = app()->support()->currentAgent();
            if (!$customerServiceAgent) {
                $customerServiceAgentLoginResponse();
            }
            $customerServiceAgent = app()->support()->deleteAgentNicknameOption(
                $customerServiceAgent,
                (string) input('nickname', '')
            );
            json_response(array(
                'success' => true,
                'message' => '昵称候选已删除。',
                'data' => app()->support()->agentPayload(
                    (int) input('session_id', 0),
                    (string) input('status', 'all'),
                    $customerServiceAgent,
                    false
                ),
            ));
            break;

        case 'customer_service.agent.payment_settings':
            $customerServiceAgent = app()->support()->currentAgent();
            if (!$customerServiceAgent) {
                $customerServiceAgentLoginResponse();
            }
            app()->support()->saveAgentPaymentSettings($customerServiceAgent, $_POST, $_FILES);
            json_response(array(
                'success' => true,
                'message' => isset($_POST['payment_qr_delete']) ? '二维码已删除。' : '在线客服接待后台管理已保存。',
                'reload' => true,
            ));
            break;

        case 'customer_service.agent.poll':
            $customerServiceAgent = app()->support()->currentAgent();
            if (!$customerServiceAgent) {
                $customerServiceAgentLoginResponse();
            }
            $customerServiceAgentSessionId = (int) input('session_id', 0);
            $customerServiceAgentLightPoll = (string) input('light', '0') === '1' && $customerServiceAgentSessionId > 0;
            $customerServiceAgentPollState = array(
                'slim_poll' => (string) input('slim', '0') === '1',
                'known_message_id' => (int) input('known_message_id', 0),
                'known_session_stamp' => (string) input('known_session_stamp', ''),
            );
            json_response(array(
                'success' => true,
                'data' => $customerServiceAgentLightPoll
                    ? app()->support()->agentSessionPayload(
                        $customerServiceAgentSessionId,
                        $customerServiceAgent,
                        (string) input('read', '0') === '1',
                        (string) input('status', 'all'),
                        $customerServiceAgentPollState
                    )
                    : app()->support()->agentPayload(
                        $customerServiceAgentSessionId,
                        (string) input('status', 'all'),
                        $customerServiceAgent,
                        (string) input('read', '0') === '1',
                        true
                    ),
            ));
            break;

        case 'customer_service.agent.send':
            $customerServiceAgent = app()->support()->currentAgent();
            if (!$customerServiceAgent) {
                $customerServiceAgentLoginResponse();
            }
            app()->support()->sendAgentMessage(
                (int) input('session_id', 0),
                $customerServiceAgent,
                $_POST,
                isset($_FILES['attachment']) ? $_FILES['attachment'] : null
            );
            json_response(array(
                'success' => true,
                'message' => '客服消息已发送。',
                'data' => app()->support()->agentSessionPayload(
                    (int) input('session_id', 0),
                    $customerServiceAgent,
                    false,
                    (string) input('status', 'all')
                ),
            ));
            break;

        case 'customer_service.agent.score':
            $customerServiceAgent = app()->support()->currentAgent();
            if (!$customerServiceAgent) {
                $customerServiceAgentLoginResponse();
            }
            $customerServiceScoreResult = app()->support()->addSessionMemberScore(
                (int) input('session_id', 0),
                $customerServiceAgent,
                (int) input('score_amount', 0)
            );
            $customerServiceScoreMessage = '已对该用户'
                . ((int) ($customerServiceScoreResult['amount'] ?? 0) > 0 ? '充值了 ' : '扣减了 ')
                . abs((int) ($customerServiceScoreResult['amount'] ?? 0))
                . ' 积分，当前积分 '
                . (int) ($customerServiceScoreResult['score'] ?? 0)
                . '。';
            $customerServiceScorePayload = app()->support()->agentPayload(
                (int) input('session_id', 0),
                (string) input('status', 'all'),
                $customerServiceAgent,
                true
            );
            $customerServiceScorePayload['score_result'] = $customerServiceScoreResult;
            json_response(array(
                'success' => true,
                'message' => $customerServiceScoreMessage,
                'data' => $customerServiceScorePayload,
            ));
            break;

        case 'customer_service.agent.clear':
            $customerServiceAgent = app()->support()->currentAgent();
            if (!$customerServiceAgent) {
                $customerServiceAgentLoginResponse();
            }
            app()->support()->clearAgentRecords((int) input('session_id', 0), $customerServiceAgent);
            json_response(array(
                'success' => true,
                'message' => '当前客服账号聊天记录已删除，后台监督记录已保留。',
                'data' => app()->support()->agentPayload(
                    (int) input('session_id', 0),
                    (string) input('status', 'all'),
                    $customerServiceAgent,
                    true
                ),
            ));
            break;

        case 'customer_service.agent.recall':
            $customerServiceAgent = app()->support()->currentAgent();
            if (!$customerServiceAgent) {
                $customerServiceAgentLoginResponse();
            }
            app()->support()->recallAgentMessage(
                (int) input('session_id', 0),
                $customerServiceAgent,
                (int) input('message_id', 0)
            );
            json_response(array(
                'success' => true,
                'message' => '消息已撤回。',
                'data' => app()->support()->agentSessionPayload(
                    (int) input('session_id', 0),
                    $customerServiceAgent,
                    false,
                    (string) input('status', 'all')
                ),
            ));
            break;

        case 'customer_service.agent.queue_delete':
            $customerServiceAgent = app()->support()->currentAgent();
            if (!$customerServiceAgent) {
                $customerServiceAgentLoginResponse();
            }
            app()->support()->deleteSessionFromAgentQueue((int) input('session_id', 0), $customerServiceAgent);
            json_response(array(
                'success' => true,
                'message' => '会员已从会话队列删除。',
                'data' => app()->support()->agentPayload(
                    0,
                    (string) input('status', 'all'),
                    $customerServiceAgent,
                    true
                ),
            ));
            break;

        case 'customer_service.agent.block':
            $customerServiceAgent = app()->support()->currentAgent();
            if (!$customerServiceAgent) {
                $customerServiceAgentLoginResponse();
            }
            app()->support()->blockSessionByAgent((int) input('session_id', 0), $customerServiceAgent, (string) input('block_limit', 'permanent'));
            json_response(array(
                'success' => true,
                'message' => '该会员会话已屏蔽。',
                'data' => app()->support()->agentPayload(
                    (int) input('session_id', 0),
                    (string) input('status', 'all'),
                    $customerServiceAgent,
                    true
                ),
            ));
            break;

        case 'customer_service.agent.unblock':
            $customerServiceAgent = app()->support()->currentAgent();
            if (!$customerServiceAgent) {
                $customerServiceAgentLoginResponse();
            }
            app()->support()->unblockSessionByAgent((int) input('session_id', 0), $customerServiceAgent);
            json_response(array(
                'success' => true,
                'message' => '该会员会话已解除屏蔽。',
                'data' => app()->support()->agentPayload(
                    (int) input('session_id', 0),
                    (string) input('status', 'all'),
                    $customerServiceAgent,
                    true
                ),
            ));
            break;

        case 'customer_service.agent.close':
            $customerServiceAgent = app()->support()->currentAgent();
            if (!$customerServiceAgent) {
                $customerServiceAgentLoginResponse();
            }
            app()->support()->closeSessionByAgent((int) input('session_id', 0), $customerServiceAgent);
            json_response(array(
                'success' => true,
                'message' => '客服会话已关闭。',
                'data' => app()->support()->agentPayload(
                    (int) input('session_id', 0),
                    (string) input('status', 'all'),
                    $customerServiceAgent,
                    true
                ),
            ));
            break;

        case 'customer_service.typing':
            $customerServiceTypingRole = in_array((string) input('role', 'member'), array('agent', 'admin'), true) ? 'agent' : 'member';
            $customerServiceIsTyping = (string) input('typing', '0') === '1';
            $customerServiceTypingStatusType = (string) input('status_type', 'typing');
            if ($customerServiceTypingRole === 'agent') {
                $customerServiceAgent = app()->support()->currentAgent();
                if (!$customerServiceAgent) {
                    $customerServiceAgentLoginResponse();
                }
                app()->support()->setAgentTyping(
                    (int) input('session_id', 0),
                    $customerServiceAgent,
                    $customerServiceIsTyping,
                    $customerServiceTypingStatusType
                );
                json_response(array('success' => true));
                break;
            }

            if (!$user) {
                throw new RuntimeException('请先登录后再使用在线客服。');
            }
            app()->support()->setMemberTyping((int) $user['id'], $customerServiceIsTyping, $customerServiceTypingStatusType);
            json_response(array('success' => true));
            break;

        case 'admin.settings.save':
            if (!$admin) {
                throw new RuntimeException('请先登录后台后再执行该操作。');
            }
            app()->auth()->requireAdminPortal('settings.manage', public_url('admin.php'));
            app()->admins()->saveSystemSettings($_POST, $admin);
            json_response(array(
                'success' => true,
                'message' => '系统设置已保存。',
                'reload' => true,
            ));
            break;

        case 'admin.admin.save':
            if (!$admin) {
                throw new RuntimeException('请先登录后台后再执行该操作。');
            }
            app()->auth()->requireAdminPortal('admins.manage', public_url('admin.php'));
            app()->admins()->saveAdmin($_POST, $admin);
            json_response(array(
                'success' => true,
                'message' => '管理员信息已保存。',
                'reload' => true,
            ));
            break;

        case 'admin.admin.toggle':
            if (!$admin) {
                throw new RuntimeException('请先登录后台后再执行该操作。');
            }
            app()->auth()->requireAdminPortal('admins.manage', public_url('admin.php'));
            app()->admins()->toggleAdminStatus((int) input('target_id', 0), $admin);
            json_response(array(
                'success' => true,
                'message' => '管理员状态已更新。',
                'reload' => true,
            ));
            break;

        case 'admin.admin.delete':
            if (!$admin) {
                throw new RuntimeException('请先登录后台后再执行该操作。');
            }
            app()->auth()->requireAdminPortal('admins.manage', public_url('admin.php'));
            app()->admins()->deleteAdmin((int) input('target_id', 0), $admin);
            json_response(array(
                'success' => true,
                'message' => '管理员已删除。',
                'reload' => true,
            ));
            break;

        case 'admin.role.save':
            if (!$admin) {
                throw new RuntimeException('请先登录后台后再执行该操作。');
            }
            app()->auth()->requireAdminPortal('roles.manage', public_url('admin.php'));
            app()->admins()->saveRole($_POST, $admin);
            json_response(array(
                'success' => true,
                'message' => '角色信息已保存。',
                'reload' => true,
            ));
            break;

        case 'admin.post.save':
            if (!$admin) {
                throw new RuntimeException('请先登录后台后再执行该操作。');
            }
            app()->auth()->requireAdminPortal('posts.manage', public_url('admin.php'));
            $savedPost = app()->admins()->saveManagedForumPost($_POST, $admin);
            $postRegion = $normalizePostRegion(input('region', (string) ($savedPost['region'] ?? 'macau')));
            $postView = $normalizePostView((string) input('view', 'compose'), 'compose');
            json_response(array(
                'success' => true,
                'message' => '帖子信息已保存。',
                'redirect' => public_url('admin.php') . '?' . http_build_query(array(
                    'page' => 'posts',
                    'region' => $postRegion,
                    'view' => $postView,
                    'edit' => (int) ($savedPost['id'] ?? 0),
                )),
                'data' => array(
                    'id' => (int) ($savedPost['id'] ?? 0),
                    'title' => (string) ($savedPost['title'] ?? ''),
                    'status' => (string) ($savedPost['status'] ?? ''),
                ),
            ));
            break;

        case 'admin.post.bulk':
            if (!$admin) {
                throw new RuntimeException('请先登录后台后再执行该操作。');
            }
            app()->auth()->requireAdminPortal('posts.manage', public_url('admin.php'));
            $selectedIds = isset($_POST['selected_ids']) && is_array($_POST['selected_ids'])
                ? array_map('intval', $_POST['selected_ids'])
                : array();
            if (empty($selectedIds) && trim((string) input('ids', '')) !== '') {
                $selectedIds = array_map('intval', array_filter(array_map('trim', explode(',', (string) input('ids', ''))), 'strlen'));
            }
            $bulkPostResult = app()->admins()->bulkManagedPosts($selectedIds, (string) input('bulk_action', ''), (string) input('bulk_value', ''), $admin);
            $postRegion = $normalizePostRegion(input('region', 'macau'));
            $postView = $normalizePostView((string) input('view', 'manage'), 'manage');
            json_response(array(
                'success' => true,
                'message' => (string) ($bulkPostResult['message'] ?? '帖子批量操作已完成。'),
                'redirect' => public_url('admin.php') . '?' . http_build_query(array(
                    'page' => 'posts',
                    'region' => $postRegion,
                    'view' => $postView,
                )),
                'data' => array(
                    'selected_ids' => array_values($selectedIds),
                    'result' => $bulkPostResult,
                ),
            ));
            break;

        case 'admin.post_lock.save':
            if (!$admin) {
                throw new RuntimeException('请先登录后台后再执行该操作。');
            }
            app()->auth()->requireAdminPortal('posts.manage', public_url('admin.php'));
            $postLockSettings = app()->admins()->saveManagedPostLockSettings($_POST, $admin);
            $postLockRegion = $normalizePostRegion(input('region', 'macau'));
            json_response(array(
                'success' => true,
                'message' => '锁帖时间设置已保存。',
                'data' => array(
                    'settings' => $postLockSettings,
                    'state' => app()->posts()->postLockState($postLockRegion),
                ),
            ));
            break;

        case 'admin.post_like_increment.save':
            if (!$admin) {
                throw new RuntimeException('请先登录后台后再执行该操作。');
            }
            app()->auth()->requireAdminPortal('posts.manage', public_url('admin.php'));
            $postLikeIncrementSettings = app()->admins()->saveManagedPostLikeIncrementSettings($_POST, $admin);
            json_response(array(
                'success' => true,
                'message' => '帖子点赞显示参数已保存。',
                'data' => array(
                    'settings' => $postLikeIncrementSettings,
                ),
            ));
            break;

        case 'admin.post_view_display.save':
            if (!$admin) {
                throw new RuntimeException('请先登录后台后再执行该操作。');
            }
            app()->auth()->requireAdminPortal('posts.manage', public_url('admin.php'));
            $postViewDisplaySettings = app()->admins()->saveManagedPostViewDisplaySettings($_POST, $admin);
            json_response(array(
                'success' => true,
                'message' => '帖子浏览量显示参数已保存。',
                'data' => array(
                    'settings' => $postViewDisplaySettings,
                ),
            ));
            break;

        case 'admin.post_sale_buyer_increment.save':
            if (!$admin) {
                throw new RuntimeException('请先登录后台后再执行该操作。');
            }
            app()->auth()->requireAdminPortal('posts.manage', public_url('admin.php'));
            $postSaleBuyerIncrementSettings = app()->admins()->saveManagedPostSaleBuyerIncrementSettings($_POST, $admin);
            json_response(array(
                'success' => true,
                'message' => '出售购买递增参数已保存。',
                'data' => array(
                    'settings' => $postSaleBuyerIncrementSettings,
                ),
            ));
            break;

        case 'admin.post.quick':
            if (!$admin) {
                throw new RuntimeException('请先登录后台后再执行该操作。');
            }
            app()->auth()->requireAdminPortal('posts.manage', public_url('admin.php'));
            $processedPost = app()->admins()->processManagedForumPostAction(
                (int) input('target_post_id', 0),
                (string) input('quick_action', ''),
                $_POST,
                $admin
            );
            $postRegion = $normalizePostRegion(input('region', (string) ($processedPost['region'] ?? 'macau')));
            $postView = $normalizePostView((string) input('view', 'manage'), 'manage');
            json_response(array(
                'success' => true,
                'message' => (string) ($processedPost['_message'] ?? '帖子操作已完成。'),
                'redirect' => public_url('admin.php') . '?' . http_build_query(array(
                    'page' => 'posts',
                    'region' => $postRegion,
                    'view' => $postView,
                )),
                'data' => array(
                    'id' => (int) ($processedPost['id'] ?? 0),
                    'status' => (string) ($processedPost['status'] ?? ''),
                ),
            ));
            break;

        case 'admin.post.categories':
            if (!$admin) {
                throw new RuntimeException('请先登录后台后再执行该操作。');
            }
            app()->auth()->requireAdminPortal('posts.view', public_url('admin.php'));
            $postRegion = $normalizePostRegion(input('region', 'macau'));
            $categories = app()->admins()->categoryOptions((int) input('section_id', 0), $postRegion);
            $payloadCategories = array_values(array_map(static function ($category) use ($postRegion) {
                return array(
                    'id' => (int) ($category['id'] ?? 0),
                    'section_id' => (int) ($category['section_id'] ?? 0),
                    'name' => (string) ($category['name'] ?? ''),
                    'region' => (string) ($category['region'] ?? $postRegion),
                );
            }, $categories));
            json_response(array(
                'success' => true,
                'categories' => $payloadCategories,
                'data' => array(
                    'categories' => $payloadCategories,
                ),
            ));
            break;

        case 'customer_service.admin.poll':
            if (!$admin) {
                throw new RuntimeException('请先登录后台后再执行该操作。');
            }
            app()->auth()->requireAdminPortal('customer_service.view', public_url('admin.php'));
            json_response(array(
                'success' => true,
                'data' => app()->support()->adminPayload(
                    (int) input('session_id', 0),
                    (string) input('status', 'all'),
                    $admin
                ),
            ));
            break;

        case 'customer_service.admin.unread':
            if (!$admin) {
                throw new RuntimeException('请先登录后台后再执行该操作。');
            }
            app()->auth()->requireAdminPortal('customer_service.view', public_url('admin.php'));
            json_response(array(
                'success' => true,
                'data' => app()->support()->adminUnreadPayload(),
            ));
            break;

        case 'customer_service.admin.send':
            if (!$admin) {
                throw new RuntimeException('请先登录后台后再执行该操作。');
            }
            throw new RuntimeException('后台在线客服页只负责设置管理，请在前台客服接待页处理对话。');

        case 'customer_service.admin.clear':
            if (!$admin) {
                throw new RuntimeException('请先登录后台后再执行该操作。');
            }
            throw new RuntimeException('后台在线客服页不处理聊天记录，请在前台客服接待页操作。');

        case 'customer_service.admin.close':
            if (!$admin) {
                throw new RuntimeException('请先登录后台后再执行该操作。');
            }
            throw new RuntimeException('后台在线客服页不关闭会话，请在前台客服接待页操作。');

        case 'customer_service.agent.save':
            if (!$admin) {
                throw new RuntimeException('请先登录后台后再执行该操作。');
            }
            app()->auth()->requireAdminPortal('customer_service.manage', public_url('admin.php'));
            app()->support()->saveAgent($_POST, $admin);
            json_response(array(
                'success' => true,
                'message' => '客服账号与权限已保存。',
                'reload' => true,
            ));
            break;

        case 'customer_service.agent.delete':
            if (!$admin) {
                throw new RuntimeException('请先登录后台后再执行该操作。');
            }
            app()->auth()->requireAdminPortal('customer_service.manage', public_url('admin.php'));
            app()->support()->deleteAgent((int) input('id', 0), $admin);
            json_response(array(
                'success' => true,
                'message' => '客服账号已删除。',
                'redirect' => public_url('admin.php') . '?page=support&support_view=agents',
            ));
            break;

        case 'admin.predict.generate':
        case 'admin.draw.save':
        case 'admin.user.save':
        case 'admin.user.score':
        case 'admin.reset.process':
        case 'admin.cache.clear':
            throw new RuntimeException('当前后台第一阶段暂未开放该接口，请使用已上线页面模块。');

        case 'forecast.generate':
            $forecastIsCustomerServiceAgent = $customerServiceAgentActive();
            $forecastFilters = array(
                'zodiac_type' => trim((string) input('zodiac_type', '')),
                'number_type' => trim((string) input('number_type', '')),
                'pingte_type' => trim((string) input('pingte_type', '')),
                'other_type' => trim((string) input('other_type', '')),
            );
            $hasForecastFilter = false;
            foreach ($forecastFilters as $forecastFilterValue) {
                if ($forecastFilterValue !== '') {
                    $hasForecastFilter = true;
                    break;
                }
            }
            if (!$hasForecastFilter) {
                throw new RuntimeException('请先至少选择一项预测类型。');
            }
            if (!$user && !$forecastIsCustomerServiceAgent) {
                throw new RuntimeException('请注册或登录后再参与AI预测。');
            }
            $forecastPricingSummary = app()->admins()->forecastPricingForFilters($forecastFilters);
            $forecastChargePoints = ($user && !$forecastIsCustomerServiceAgent) ? (int) round((float) ($forecastPricingSummary['total'] ?? 0)) : 0;
            if ($user && !$forecastIsCustomerServiceAgent && $forecastChargePoints > 0 && (int) ($user['score'] ?? 0) < $forecastChargePoints) {
                $insufficientPointsResponse();
            }
            $forecastEnabledOptions = app()->admins()->forecastFilterOptions();
            $forecastFilterTypeMap = array(
                'zodiac_type' => 'zodiac',
                'number_type' => 'number',
                'pingte_type' => 'pingte',
                'other_type' => 'other',
            );
            foreach ($forecastFilterTypeMap as $forecastFilterKey => $forecastTypeKey) {
                $forecastFilterValue = (string) ($forecastFilters[$forecastFilterKey] ?? '');
                if ($forecastFilterValue === '') {
                    continue;
                }
                $forecastValueEnabled = false;
                foreach ((array) ($forecastEnabledOptions[$forecastTypeKey]['options'] ?? array()) as $forecastOption) {
                    if ((string) ($forecastOption['value'] ?? '') === $forecastFilterValue) {
                        $forecastValueEnabled = true;
                        break;
                    }
                }
                if (!$forecastValueEnabled) {
                    throw new RuntimeException('请选择已启用的预测选项。');
                }
            }
            $forecastRegion = $normalizePostRegion(input('region', 'macau'));
            $forecastGeneratedBy = ($user && !$forecastIsCustomerServiceAgent) ? (int) $user['id'] : null;
            if (!$forecastIsCustomerServiceAgent) {
                app()->prediction()->assertForecastParticipationAllowed($forecastRegion, $forecastGeneratedBy);
            }
            $forecastTransactionStarted = false;
            try {
                if ($user && !$forecastIsCustomerServiceAgent) {
                    app()->db()->beginTransaction();
                    $forecastTransactionStarted = true;
                }

                $prediction = app()->prediction()->buildForecast($forecastRegion, $forecastGeneratedBy, ($user && !$forecastIsCustomerServiceAgent) ? true : false, $forecastFilters);

                if ($user && !$forecastIsCustomerServiceAgent && $forecastChargePoints > 0) {
                    $forecastLockedUser = app()->db()->fetch('SELECT id, score FROM users WHERE id = :id FOR UPDATE', array(
                        'id' => (int) $user['id'],
                    ));
                    if (!$forecastLockedUser) {
                        throw new RuntimeException('会员账号不存在，请重新登录。');
                    }
                    if ((int) ($forecastLockedUser['score'] ?? 0) < $forecastChargePoints) {
                        $insufficientPointsResponse();
                    }
                }

                if (!$forecastIsCustomerServiceAgent) {
                    app()->prediction()->recordForecastParticipation($forecastRegion, $forecastGeneratedBy);
                }

                if ($user && !$forecastIsCustomerServiceAgent && $forecastChargePoints > 0) {
                    app()->db()->execute('UPDATE users SET score = score - :score, updated_at = :updated_at WHERE id = :id', array(
                        'score' => $forecastChargePoints,
                        'updated_at' => date('Y-m-d H:i:s'),
                        'id' => (int) $user['id'],
                    ));
                }

                if ($forecastTransactionStarted) {
                    app()->db()->commit();
                }
            } catch (\Exception $forecastException) {
                if ($forecastTransactionStarted) {
                    app()->db()->rollBack();
                }
                throw $forecastException;
            }
            $forecastIdioms = array(
                '旗开得胜',
                '财运亨通',
                '鸿运当头',
                '马到功成',
                '稳操胜券',
                '一举夺魁',
                '大吉大利',
                '福星高照',
                '捷报频传',
                '满载而归',
                '一路长虹',
                '福运连连',
                '喜从天降',
                '连连报喜',
                '百发百中',
                '十拿九稳',
                '开门见喜',
                '喜气盈门',
                '招财进宝',
                '财气冲天',
                '福到财到',
                '金运亨通',
                '顺心顺意',
                '财喜双收',
                '大获全胜',
                '胜券在握',
                '出手即赢',
                '好运加身',
                '财势双旺',
                '财路大开',
                '开局即红',
                '红运加持',
                '喜迎丰收',
                '好运在握',
                '胜算倍增',
                '一举拿下',
                '一路向赢',
                '赢面大开',
                '运势上扬',
                '金喜临门',
                '财旺福旺',
                '旺上加旺',
                '抢赢当下',
                '八方来财',
                '富贵盈门',
                '喜讯频来',
                '胜利在望',
                '红利在前',
                '一路稳赢',
                '财星高照',
            );
            $forecastBlessings = array(
                '恭喜好运临门，祝您中奖赢钱。',
                '祝您喜气满满，开奖顺利得胜。',
                '恭喜胜势已到，愿您赢钱中奖。',
                '祝您一路红运，赢得漂亮胜利。',
                '恭喜财气相随，中奖喜讯将至。',
                '祝您好运开局，中奖喜气连连。',
                '恭喜红运上扬，赢钱顺心如意。',
                '愿您财星照耀，胜利一路相伴。',
                '祝您喜报频传，中奖福气到家。',
                '恭喜手气正旺，赢钱满载而归。',
                '愿您好运加持，胜利稳稳入怀。',
                '祝您鸿运当头，中奖喜笑颜开。',
                '恭喜财运升温，赢钱一路漂亮。',
                '愿您福气临门，胜利喜上眉梢。',
                '祝您顺风顺水，中奖好运不断。',
                '恭喜金运相随，赢钱喜气盈门。',
                '愿您心想事成，胜利好运同来。',
                '祝您红光满面，中奖喜讯连连。',
                '恭喜好运翻倍，赢钱更加顺利。',
                '愿您财气高涨，胜利一路高开。',
                '祝您喜气入门，中奖福运常在。',
                '恭喜时来运转，赢钱满心欢喜。',
                '愿您好运成双，胜利捷报频传。',
                '祝您财源滚滚，中奖喜运同行。',
                '恭喜旺气聚拢，赢钱顺势而来。',
                '愿您红运不断，胜利光彩满满。',
                '祝您福星高照，中奖快乐加倍。',
                '恭喜喜气相伴，赢钱一路顺畅。',
                '愿您好运入局，胜利稳步向前。',
                '祝您财运亨通，中奖喜气洋洋。',
                '恭喜福运到位，赢钱喜事临门。',
                '愿您胜意满满，好运中奖同行。',
                '祝您开门见喜，赢钱顺心顺意。',
                '恭喜一路长红，胜利喜报到来。',
                '愿您财气围绕，中奖好运常伴。',
                '祝您喜运升起，赢钱笑口常开。',
                '恭喜好运当前，胜利越来越近。',
                '愿您金光照路，中奖福气满满。',
                '祝您财喜双收，赢钱开心顺遂。',
                '恭喜红运在线，胜利喜气同行。',
                '愿您福到运到，中奖喜事也到。',
                '祝您好运不歇，赢钱一路欢喜。',
                '恭喜财星入局，胜利光芒正盛。',
                '愿您喜气聚财，中奖好运相随。',
                '祝您顺势而上，赢钱喜悦满怀。',
                '恭喜鸿运加码，胜利喜报不断。',
                '愿您财路宽广，中奖好运开花。',
                '祝您好运满格，赢钱喜气高升。',
                '恭喜福气到账，胜利一路发光。',
                '愿您红运连开，中奖喜气盈怀。',
                '祝您财喜临门，赢钱好运双收。',
                '恭喜顺风得势，胜利笑迎好彩。',
                '愿您好运连线，中奖福运同频。',
                '祝您旺气长在，赢钱胜利相随。',
                '恭喜喜运常开，中奖赢钱开心。',
            );
            $forecastIdiom = $forecastIdioms[random_int(0, count($forecastIdioms) - 1)];
            $forecastBlessing = $forecastBlessings[random_int(0, count($forecastBlessings) - 1)];
            $forecastResponseMessage = $forecastBlessing;
            $forecastNoticeTitle = '🎉 恭喜：祝您中奖！';
            if (!$user && !$forecastIsCustomerServiceAgent) {
                $forecastResponseMessage = $forecastBlessing . ' 游客预测结果不保留，请立即截图保存，切换页面后将清空。';
                $forecastNoticeTitle = '游客预测已生成';
            } elseif ($forecastIsCustomerServiceAgent) {
                $forecastResponseMessage = $forecastBlessing . ' 接待客服账号已免积分生成预测。';
                $forecastNoticeTitle = '接待客服预测已生成';
            }
            $_SESSION['forecast_generated_predictions'][$forecastRegion] = array(
                'filters' => $forecastFilters,
                'guest_once' => !$user && !$forecastIsCustomerServiceAgent,
                'prediction' => array(
                    'region' => (string) ($prediction['region'] ?? $forecastRegion),
                    'generated_for_issue' => (string) ($prediction['generated_for_issue'] ?? ''),
                    'summary' => (string) ($prediction['summary'] ?? ''),
                    'numbers' => array_values(array_map('intval', (array) ($prediction['numbers'] ?? array()))),
                    'confidence' => min(97.0, max(89.0, (float) ($prediction['confidence'] ?? 0.0))),
                    'display_payloads' => is_array($prediction['display_payloads'] ?? null) ? $prediction['display_payloads'] : array(),
                    'line_confidences' => is_array($prediction['line_confidences'] ?? null) ? $prediction['line_confidences'] : array(),
                ),
            );
            $_SESSION['forecast_result_notes'][$forecastRegion] = array(
                'filters' => $forecastFilters,
                'guest_once' => !$user && !$forecastIsCustomerServiceAgent,
                'idiom' => $forecastIdiom,
                'blessing' => $forecastBlessing,
            );
            $forecastRedirectQuery = array('region' => $forecastRegion);
            if ($forecastIsCustomerServiceAgent) {
                $forecastRedirectQuery['agent'] = '1';
            }
            foreach ($forecastFilters as $filterKey => $filterValue) {
                if ($filterValue !== '') {
                    $forecastRedirectQuery[$filterKey] = $filterValue;
                }
            }
            if ((string) input('inline', '0') === '1') {
                json_response(array(
                    'success' => true,
                    'message' => $forecastResponseMessage,
                    'notice_title' => $forecastNoticeTitle,
                    'data' => array(
                        'issue' => (string) $prediction['generated_for_issue'],
                        'summary' => (string) $prediction['summary'],
                        'numbers' => array_values(array_map('intval', $prediction['numbers'])),
                        'confidence' => min(97.0, max(89.0, (float) $prediction['confidence'])),
                        'line_confidences' => is_array($prediction['line_confidences'] ?? null) ? $prediction['line_confidences'] : array(),
                        'created_at' => format_datetime(date('Y-m-d H:i:s')),
                        'idiom' => $forecastIdiom,
                        'blessing' => $forecastBlessing,
                    ),
                ));
            }
            json_response(array(
                'success' => true,
                'message' => $forecastResponseMessage,
                'notice_title' => $forecastNoticeTitle,
                'notice_redirect_after_close' => true,
                'redirect' => public_url('forecast.php') . '?' . http_build_query($forecastRedirectQuery),
            ));
            break;

        default:
            throw new RuntimeException('未识别的接口动作：' . $action);
    }
} catch (\Exception $exception) {
    $isExpectedError = $exception instanceof RuntimeException;

    json_response(array(
        'success' => false,
        'message' => $isExpectedError ? $exception->getMessage() : '服务器处理失败，请稍后重试。',
    ), $isExpectedError ? 422 : 500);
}
