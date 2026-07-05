<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Security;
use App\Core\Session;
use RuntimeException;

class SupportService extends Service
{
    const RECENT_SEND_SESSION_KEY = 'customer_service_recent_sends';
    const RECENT_SEND_TTL_SECONDS = 5;
    const WELCOME_SEND_SESSION_KEY = 'customer_service_welcome_sends';
    const FORUM_GUIDE_SETTING_PREFIX = 'member_forum_guide.';

    protected $schemaReady = false;
    protected $columnExistsCache = array();
    protected $indexExistsCache = array();
    protected $agentsRequestCache = null;

    public function publicServiceProfile()
    {
        $this->ensureTables();

        return $this->memberServiceProfile(null);
    }

    public function publicTypingStatus()
    {
        $this->ensureTables();

        return $this->typingStatus(null, 'member');
    }

    public function publicStatusPayload()
    {
        $this->ensureTables();

        return array(
            'service_profile' => $this->memberServiceProfile(null),
            'typing_status' => $this->typingStatus(null, 'member'),
        );
    }

    public function forumGuideRules()
    {
        $defaults = $this->forumGuideRuleDefaults();
        $rules = array();

        foreach ($defaults as $key => $default) {
            $value = (string) $this->app->settings()->get(self::FORUM_GUIDE_SETTING_PREFIX . $key, $default);
            $rules[$key] = trim($value) !== '' ? $value : $default;
        }

        return $rules;
    }

    public function saveForumGuideRules(array $payload)
    {
        $defaults = $this->forumGuideRuleDefaults();
        $items = array();

        foreach ($defaults as $key => $default) {
            $field = 'forum_guide_' . $key;
            if (!array_key_exists($field, $payload)) {
                continue;
            }

            $value = $this->normalizeForumGuideRuleText((string) $payload[$field]);
            $items[self::FORUM_GUIDE_SETTING_PREFIX . $key] = $value !== '' ? $value : $default;
        }

        if ($items) {
            $this->app->settings()->setMany('member_forum_guide', $items);
        }
    }

    public function memberPayload($userId)
    {
        $this->ensureTables();

        $session = $this->getOrCreateMemberSession((int) $userId);
        $session = $this->refreshExpiredSessionBlock($session);
        $session = $this->sendMemberWelcomeIfNeeded((int) $userId, $session);
        $this->markMemberRead((int) $session['id']);
        $session = $this->sessionById((int) $session['id']);
        $this->touchMemberPresence($session);

        return array(
            'session' => $this->formatSession($session),
            'messages' => $this->formatMessages($this->messages((int) $session['id'], 'member'), 'member'),
            'emojis' => $this->emojiList(),
            'typing_status' => $this->typingStatus($session, 'member'),
            'service_profile' => $this->memberServiceProfile($session),
        );
    }

    public function sendMemberMessage($userId, array $payload, $file = null)
    {
        $this->ensureTables();

        $session = $this->getOrCreateMemberSession((int) $userId);
        $session = $this->refreshExpiredSessionBlock($session);
        if ($this->sessionBlockActive($session)) {
            throw new RuntimeException($this->blockSendDeniedMessage($session));
        }

        $session = $this->sendMemberWelcomeIfNeeded((int) $userId, $session);
        $message = $this->normalizeMessagePayload($payload, $file, 'member');
        $now = $this->now();
        $this->touchMemberPresence($session);
        if ($this->shouldSkipDuplicateSend((int) $session['id'], 'member', $message)) {
            return $this->memberPayload((int) $userId);
        }

        $this->insertMessage((int) $session['id'], 'member', (int) $userId, null, $message, $now);
        $autoReply = $this->autoReplyForSession($session);
        if ($autoReply) {
            $this->insertMessage((int) $session['id'], 'agent', null, (int) ($autoReply['agent_id'] ?? 0), $autoReply['message'], $now);
        }

        $lastMessage = $autoReply ? $autoReply['message'] : $message;
        $nextStatus = $autoReply || (int) ($session['assigned_agent_id'] ?? 0) > 0 ? 'open' : 'waiting';
        $sessionSql = 'UPDATE customer_service_sessions
             SET status = :status,
                 unread_for_member = :unread_for_member,
                 unread_for_admin = unread_for_admin + 1,
                 member_typing_at = NULL,
                 last_message_type = :last_message_type,
                 last_message_preview = :last_message_preview,
                 last_message_at = :last_message_at,
                 agent_hidden_at = NULL,
                 closed_at = NULL,
                 updated_at = :updated_at
             WHERE id = :id';
        $sessionParams = array(
            'status' => $nextStatus,
            'unread_for_member' => $autoReply ? 1 : 0,
            'last_message_type' => $lastMessage['type'],
            'last_message_preview' => $this->preview($lastMessage),
            'last_message_at' => $now,
            'updated_at' => $now,
            'id' => (int) $session['id'],
        );
        if ($autoReply) {
            $sessionSql = 'UPDATE customer_service_sessions
             SET assigned_agent_id = CASE WHEN assigned_agent_id IS NULL THEN :assigned_agent_id ELSE assigned_agent_id END,
                 status = :status,
                 unread_for_member = :unread_for_member,
                 unread_for_admin = unread_for_admin + 1,
                 member_typing_at = NULL,
                 last_message_type = :last_message_type,
                 last_message_preview = :last_message_preview,
                 last_message_at = :last_message_at,
                 agent_hidden_at = NULL,
                 closed_at = NULL,
                 updated_at = :updated_at
             WHERE id = :id';
            $sessionParams['assigned_agent_id'] = (int) ($autoReply['agent_id'] ?? 0);
        }

        $this->db()->execute(
            $sessionSql,
            $sessionParams
        );

        $this->setSessionLiveStatus((int) $session['id'], 'member', '');

        return $this->memberPayload((int) $userId);
    }

    public function clearMemberRecords($userId)
    {
        $this->ensureTables();

        $session = $this->memberSession((int) $userId);
        if (!$session) {
            return array(
                'session' => null,
                'messages' => array(),
                'emojis' => $this->emojiList(),
                'typing_status' => $this->typingStatus(null, 'member'),
                'service_profile' => $this->memberServiceProfile(null),
            );
        }

        $this->db()->execute(
            'UPDATE customer_service_messages
             SET user_deleted_at = :deleted_at
             WHERE session_id = :session_id AND user_deleted_at IS NULL',
            array(
                'deleted_at' => $this->now(),
                'session_id' => (int) $session['id'],
            )
        );

        $this->markMemberRead((int) $session['id']);

        return $this->memberPayload((int) $userId);
    }

    public function managementPayload($status = 'all', array $operator = array(), $sessionId = 0, $includeSupervision = true)
    {
        $this->ensureTables();

        $status = $this->normalizeStatusFilter($status);
        $sessionId = (int) $sessionId;
        $includeSupervision = (bool) $includeSupervision;
        $sessions = $includeSupervision ? $this->listSessions($status) : array();

        if ($sessionId <= 0 && !empty($sessions)) {
            $sessionId = (int) ($sessions[0]['id'] ?? 0);
        }

        $session = null;
        if ($includeSupervision && $sessionId > 0) {
            $session = $this->sessionRowFromRows($sessionId, $sessions);
            if (!$session) {
                $session = $this->sessionById($sessionId);
                if ($session && !$this->sessionInRows((int) $session['id'], $sessions)) {
                    $session = !empty($sessions) ? $sessions[0] : null;
                }
            }
        }

        $messages = $session ? $this->formatMessages($this->messages((int) $session['id'], 'admin'), 'admin') : array();
        $agents = $this->agents();
        $overview = $this->overview();

        return array(
            'status' => $status,
            'active_id' => $session ? (int) $session['id'] : 0,
            'session' => $this->formatSession($session),
            'overview' => $overview,
            'agents' => $agents,
            'sessions' => $this->formatSessions($sessions),
            'messages' => $messages,
            'can_manage' => (int) ($operator['is_super'] ?? 0) === 1,
            'front_agent_url' => \public_url('admin.php'),
        );
    }

    public function adminPayload($sessionId, $status, array $admin)
    {
        return $this->managementPayload($status, $admin, (int) $sessionId);
    }

    public function sendAdminMessage($sessionId, array $admin, array $payload, $file = null)
    {
        throw new RuntimeException('后台在线客服页只负责账号、权限和链路管理，请在前台客服接待页处理对话。');
    }

    public function clearAdminRecords($sessionId, array $admin)
    {
        throw new RuntimeException('后台在线客服页不处理对话记录，请在前台客服接待页操作。');
    }

    public function closeSession($sessionId, array $admin)
    {
        throw new RuntimeException('后台在线客服页不关闭会话，请在前台客服接待页操作。');
    }

    public function setAdminTyping($sessionId, array $admin, $typing)
    {
        throw new RuntimeException('后台在线客服页不参与对话输入状态。');
    }

    public function currentAgent()
    {
        $this->ensureTables();

        $agentId = (int) Session::get('customer_service_agent_id', 0);
        if ($agentId <= 0) {
            return null;
        }

        $agent = $this->agentById($agentId);
        if (!$agent || (string) ($agent['status'] ?? '') !== 'online') {
            $this->logoutAgent();
            return null;
        }

        return $agent;
    }

    public function currentServingAgent()
    {
        $agent = $this->currentAgent();
        if (!$agent || !$this->agentIsServing($agent)) {
            return null;
        }

        return $agent;
    }

    public function loginAgent($username, $password)
    {
        $this->ensureTables();

        $username = trim((string) $username);
        $password = (string) $password;
        if ($username === '' || $password === '') {
            throw new RuntimeException('请输入客服账号和密码。');
        }

        $agent = $this->agentByUsername($username);
        if (!$agent || (string) ($agent['status'] ?? '') !== 'online' || !password_verify($password, (string) ($agent['password_hash'] ?? ''))) {
            throw new RuntimeException('客服账号或密码错误，或账号已停用。');
        }

        Session::regenerate();
        Session::put('customer_service_agent_id', (int) $agent['id']);
        Session::put('customer_service_agent_entry', '1');
        Session::put('customer_service_agent_logged_in_at', date('Y-m-d H:i:s'));
        Session::forget('customer_service_agent_serving');

        $this->db()->execute(
            'UPDATE customer_service_accounts
             SET last_login_at = :last_login_at,
                 last_login_ip = :last_login_ip,
                 updated_at = :updated_at
             WHERE id = :id',
            array(
                'last_login_at' => $this->now(),
                'last_login_ip' => Security::ipAddress(),
                'updated_at' => $this->now(),
                'id' => (int) $agent['id'],
            )
        );

        $this->clearAgentsRequestCache();
        $agent = $this->agentById((int) $agent['id']);
        if ($agent) {
            $this->touchAgentPresence($agent);
        }

        return $agent;
    }

    public function logoutAgent($clearEntry = false)
    {
        $agentId = (int) Session::get('customer_service_agent_id', 0);
        if ($agentId > 0) {
            $this->clearAgentPresence($agentId);
        }

        Session::forget('customer_service_agent_id');
        Session::forget('customer_service_agent_logged_in_at');
        Session::forget('customer_service_agent_serving');
        if ($clearEntry) {
            Session::forget('customer_service_agent_entry');
        }
        Session::regenerate();
    }

    public function setAgentServing(array $agent, $serving)
    {
        $this->ensureTables();

        $agentId = (int) ($agent['id'] ?? 0);
        if ($agentId <= 0) {
            throw new RuntimeException('客服账号不存在。');
        }

        if ($serving) {
            Session::put('customer_service_agent_serving', '1');
            $this->touchAgentPresence($agent);
        } else {
            Session::put('customer_service_agent_serving', '0');
            $this->touchAgentPresence($agent, false);
        }

        return $this->agentServingState($agent);
    }

    public function agentPayload($sessionId, $status, array $agent, $markRead = false, $preserveSession = false)
    {
        $this->ensureTables();
        $agentServing = $this->agentIsServing($agent);
        if ($agentServing) {
            $this->touchAgentPresence($agent);
        }

        $status = $this->normalizeStatusFilter($status);
        $sessionId = (int) $sessionId;
        $preserveSession = (bool) $preserveSession;
        $sessions = $this->listSessions($status, $agent);

        if ($sessionId <= 0 && !$preserveSession && !empty($sessions)) {
            $sessionId = (int) $sessions[0]['id'];
        }

        $session = $sessionId > 0 ? $this->sessionById($sessionId) : null;
        if ($session && !$this->agentCanSeeSession($session, $agent)) {
            $session = !$preserveSession && !empty($sessions) ? $this->sessionById((int) $sessions[0]['id']) : null;
        }

        $messages = array();
        if ($session) {
            if ($markRead) {
                $this->markAgentRead((int) $session['id']);
                $session = $this->sessionById((int) $session['id']);
            }
            $messages = $this->formatMessages($this->messages((int) $session['id'], 'agent'), 'agent');
            if ($markRead) {
                $sessions = $this->listSessions($status, $agent);
            }
        }

        return array(
            'active_id' => $session ? (int) $session['id'] : 0,
            'session' => $this->formatSession($session),
            'sessions' => $this->formatSessions($sessions),
            'messages' => $messages,
            'emojis' => $this->emojiList(),
            'agent' => $this->formatAgent($agent, false),
            'payment_settings' => $this->paymentSettings(),
            'agent_online' => $agentServing,
            'agent_online_label' => $agentServing ? '在线中···' : '休息中···',
            'agent_online_type' => $agentServing ? 'online' : 'offline',
            'can_reply' => $session && $agentServing ? $this->canAgentWork($session, $agent, 'reply') : false,
            'can_clear' => $session ? $this->canAgentWork($session, $agent, 'clear') : false,
            'can_close' => $session ? $this->canAgentWork($session, $agent, 'close') : false,
            'typing_status' => $this->typingStatus($session, 'agent'),
            'overview' => $this->overview($agent),
        );
    }

    public function agentSessionPayload($sessionId, array $agent, $markRead = false, $status = 'all', array $clientState = array())
    {
        $this->ensureTables();
        $agentServing = $this->agentIsServing($agent);
        if ($agentServing) {
            $this->touchAgentPresence($agent);
        }

        $status = $this->normalizeStatusFilter($status);
        $slimPollEnabled = !empty($clientState['slim_poll']);
        $sessionCacheTtl = $slimPollEnabled && !$markRead ? 2 : 0;
        $session = (int) $sessionId > 0 ? $this->sessionById((int) $sessionId, $sessionCacheTtl) : null;
        if ($session && !$this->agentCanSeeSession($session, $agent)) {
            $session = null;
        }

        $sessionHadUnread = $session && (int) ($session['unread_for_admin'] ?? 0) > 0;
        $messages = array();
        $latestMessageId = 0;
        $sessionsStamp = '';
        if ($session) {
            if ($markRead) {
                $this->markAgentRead((int) $session['id']);
                if ($sessionHadUnread) {
                    $session = $this->sessionById((int) $session['id']);
                } else {
                    $session['unread_for_admin'] = 0;
                }
            }
            if ($slimPollEnabled) {
                $sessionsStamp = $this->agentSessionsStamp($status, $agent);
                $latestMessageId = $this->latestVisibleMessageId((int) $session['id'], 'agent', $sessionCacheTtl);
                $knownSessionStamp = (string) ($clientState['known_session_stamp'] ?? '');
                if (
                    $knownSessionStamp !== ''
                    && !$sessionHadUnread
                    && (int) ($clientState['known_message_id'] ?? 0) >= $latestMessageId
                    && $knownSessionStamp === $sessionsStamp
                ) {
                    return array(
                        'active_id' => (int) $session['id'],
                        'session' => $this->formatSession($session),
                        'poll_unchanged' => true,
                        'latest_message_id' => $latestMessageId,
                        'sessions_stamp' => $sessionsStamp,
                        'agent_online' => $agentServing,
                        'agent_online_label' => $agentServing ? '在线中···' : '休息中···',
                        'agent_online_type' => $agentServing ? 'online' : 'offline',
                        'can_reply' => $agentServing ? $this->canAgentWork($session, $agent, 'reply') : false,
                        'can_clear' => $this->canAgentWork($session, $agent, 'clear'),
                        'can_close' => $this->canAgentWork($session, $agent, 'close'),
                        'typing_status' => $this->typingStatus($session, 'agent'),
                    );
                }
            }
            if ($sessionCacheTtl > 0) {
                $freshSession = $this->sessionById((int) $session['id']);
                if ($freshSession && $this->agentCanSeeSession($freshSession, $agent)) {
                    $session = $freshSession;
                }
            }
            $messages = $this->formatMessages($this->messages((int) $session['id'], 'agent'), 'agent');
        }
        if ($slimPollEnabled && $sessionsStamp === '') {
            $sessionsStamp = $this->agentSessionsStamp($status, $agent);
        }
        $sessions = $this->listSessions($status, $agent);

        $response = array(
            'active_id' => $session ? (int) $session['id'] : 0,
            'session' => $this->formatSession($session),
            'sessions' => $this->formatSessions($sessions),
            'messages' => $messages,
            'emojis' => $this->emojiList(),
            'agent_online' => $agentServing,
            'agent_online_label' => $agentServing ? '在线中···' : '休息中···',
            'agent_online_type' => $agentServing ? 'online' : 'offline',
            'can_reply' => $session && $agentServing ? $this->canAgentWork($session, $agent, 'reply') : false,
            'can_clear' => $session ? $this->canAgentWork($session, $agent, 'clear') : false,
            'can_close' => $session ? $this->canAgentWork($session, $agent, 'close') : false,
            'typing_status' => $this->typingStatus($session, 'agent'),
        );

        if ($slimPollEnabled) {
            $response['latest_message_id'] = $latestMessageId;
            $response['sessions_stamp'] = $sessionsStamp;
        }

        return $response;
    }

    public function sendAgentMessage($sessionId, array $agent, array $payload, $file = null)
    {
        $this->ensureTables();

        $session = $this->sessionById((int) $sessionId);
        if (!$session) {
            throw new RuntimeException('客服会话不存在。');
        }

        $session = $this->refreshExpiredSessionBlock($session);
        if ((string) ($session['status'] ?? '') === 'closed' && !$this->sessionBlockActive($session)) {
            throw new RuntimeException('该会话已关闭，请等待会员再次发起咨询。');
        }

        $this->assertAgentCanWork($session, $agent, 'reply');
        if (!$this->agentIsServing($agent)) {
            throw new RuntimeException('当前客服账号不在线，请重新登录后再回复会员。');
        }

        $agentId = (int) ($agent['id'] ?? 0);
        $message = $this->normalizeMessagePayload($payload, $file, 'agent');
        $now = $this->now();
        $this->touchAgentPresence($agent);
        if ($this->shouldSkipDuplicateSend((int) $session['id'], 'agent', $message)) {
            $this->setSessionLiveStatus((int) $session['id'], 'agent', '');

            return true;
        }

        $this->insertMessage((int) $session['id'], 'agent', null, $agentId, $message, $now);

        $this->db()->execute(
            'UPDATE customer_service_sessions
             SET assigned_agent_id = :assigned_agent_id,
                 status = :status,
                 unread_for_member = unread_for_member + 1,
                 unread_for_admin = 0,
                 agent_typing_at = NULL,
                 last_message_type = :last_message_type,
                 last_message_preview = :last_message_preview,
                 last_message_at = :last_message_at,
                 updated_at = :updated_at
             WHERE id = :id',
            array(
                'assigned_agent_id' => $agentId,
                'status' => 'open',
                'last_message_type' => $message['type'],
                'last_message_preview' => $this->preview($message),
                'last_message_at' => $now,
                'updated_at' => $now,
                'id' => (int) $session['id'],
            )
        );

        $this->setSessionLiveStatus((int) $session['id'], 'agent', '');

        return true;
    }

    public function addSessionMemberScore($sessionId, array $agent, $amount)
    {
        $this->ensureTables();

        $amount = (int) $amount;
        if ($amount === 0) {
            throw new RuntimeException('调整积分不能为 0。');
        }

        if (abs($amount) > 100000000) {
            throw new RuntimeException('单次调整积分不能超过 100000000。');
        }

        $session = $this->sessionById((int) $sessionId);
        if (!$session) {
            throw new RuntimeException('客服会话不存在。');
        }

        if (!$this->agentCanSeeSession($session, $agent)) {
            throw new RuntimeException('当前客服账号不能操作该会话。');
        }

        $this->assertAgentCanWork($session, $agent, 'reply');
        if (!$this->agentIsServing($agent)) {
            throw new RuntimeException('当前客服账号不在线，请重新登录后再为会员充值积分。');
        }

        $userId = (int) ($session['user_id'] ?? 0);
        if ($userId <= 0) {
            throw new RuntimeException('当前会话会员不存在。');
        }

        $this->touchAgentPresence($agent);
        $savedUser = $this->app->users()->addScore($userId, $amount, array('id' => null));
        $this->app->logs()->system(
            'customer_service',
            '客服调整会员积分：' . $this->agentName($agent) . ' 为 ' . (string) ($savedUser['username'] ?? '') . ' 调整 ' . $amount . ' 积分',
            'info',
            array(
                'agent_id' => (int) ($agent['id'] ?? 0),
                'session_id' => (int) ($session['id'] ?? 0),
                'user_id' => $userId,
                'amount' => $amount,
                'score' => (int) ($savedUser['score'] ?? 0),
            )
        );

        $now = $this->now();
        $noticeContent = $amount > 0
            ? '您的账户充值成功，充值积分 ' . $amount . '，当前剩余积分 ' . (int) ($savedUser['score'] ?? 0) . '。'
            : '您的账户扣减成功，扣减积分 ' . abs($amount) . '，当前剩余积分 ' . (int) ($savedUser['score'] ?? 0) . '。';
        $noticeMessage = $this->textMessage($noticeContent);
        $this->insertMessage((int) $session['id'], 'system', null, null, $noticeMessage, $now);
        $this->db()->execute(
            "UPDATE customer_service_sessions
             SET assigned_agent_id = CASE WHEN assigned_agent_id IS NULL THEN :assigned_agent_id ELSE assigned_agent_id END,
                 status = CASE WHEN status = 'closed' THEN 'open' ELSE status END,
                 unread_for_member = unread_for_member + 1,
                 unread_for_admin = 0,
                 last_message_type = :last_message_type,
                 last_message_preview = :last_message_preview,
                 last_message_at = :last_message_at,
                 closed_at = NULL,
                 agent_hidden_at = NULL,
                 updated_at = :updated_at
             WHERE id = :id",
            array(
                'assigned_agent_id' => (int) ($agent['id'] ?? 0),
                'last_message_type' => $noticeMessage['type'],
                'last_message_preview' => $this->preview($noticeMessage),
                'last_message_at' => $now,
                'updated_at' => $now,
                'id' => (int) $session['id'],
            )
        );

        return array(
            'user_id' => $userId,
            'username' => (string) ($savedUser['username'] ?? ''),
            'amount' => $amount,
            'score' => (int) ($savedUser['score'] ?? 0),
        );
    }

    public function clearAgentRecords($sessionId, array $agent)
    {
        $this->ensureTables();

        $session = $this->sessionById((int) $sessionId);
        if (!$session) {
            throw new RuntimeException('客服会话不存在。');
        }

        $this->assertAgentCanWork($session, $agent, 'clear');

        $this->db()->execute(
            'UPDATE customer_service_messages
             SET agent_deleted_at = :deleted_at
             WHERE session_id = :session_id AND agent_deleted_at IS NULL',
            array(
                'deleted_at' => $this->now(),
                'session_id' => (int) $session['id'],
            )
        );

        $this->markAgentRead((int) $session['id']);

        return true;
    }

    public function deleteSessionFromAgentQueue($sessionId, array $agent)
    {
        $this->ensureTables();

        $session = $this->sessionById((int) $sessionId);
        if (!$session) {
            throw new RuntimeException('客服会话不存在。');
        }

        $this->assertAgentCanWork($session, $agent, 'clear');

        $this->db()->execute(
            'UPDATE customer_service_sessions
             SET unread_for_admin = 0,
                 member_typing_at = NULL,
                 agent_typing_at = NULL,
                 agent_hidden_at = :agent_hidden_at,
                 updated_at = :updated_at
             WHERE id = :id',
            array(
                'agent_hidden_at' => $this->now(),
                'updated_at' => $this->now(),
                'id' => (int) $session['id'],
            )
        );

        $this->setSessionLiveStatus((int) $session['id'], 'member', '');
        $this->setSessionLiveStatus((int) $session['id'], 'agent', '');

        return true;
    }

    public function blockSessionByAgent($sessionId, array $agent, $blockLimit = 'permanent')
    {
        $this->ensureTables();

        $session = $this->sessionById((int) $sessionId);
        if (!$session) {
            throw new RuntimeException('客服会话不存在。');
        }

        if (!$this->agentHasPermission($agent, 'close')) {
            throw new RuntimeException('当前客服账号没有屏蔽会话权限。');
        }

        $blockedUntil = $this->normalizeBlockUntil((string) $blockLimit);
        $now = $this->now();
        $agentId = (int) ($agent['id'] ?? 0);
        $noticeMessage = $this->textMessage($this->blockNoticeMessage($blockedUntil));
        $this->insertMessage((int) $session['id'], 'system', null, null, $noticeMessage, $now);

        $this->db()->execute(
            'UPDATE customer_service_sessions
             SET status = :status,
                 unread_for_member = unread_for_member + 1,
                 unread_for_admin = 0,
                 member_typing_at = NULL,
                 agent_typing_at = NULL,
                 last_message_type = :last_message_type,
                 last_message_preview = :last_message_preview,
                 last_message_at = :last_message_at,
                 agent_hidden_at = NULL,
                 blocked_at = :blocked_at,
                 blocked_until = :blocked_until,
                 blocked_by_agent_id = :blocked_by_agent_id,
                 closed_at = :closed_at,
                 updated_at = :updated_at
             WHERE id = :id',
            array(
                'status' => 'closed',
                'last_message_type' => $noticeMessage['type'],
                'last_message_preview' => $this->preview($noticeMessage),
                'last_message_at' => $now,
                'blocked_at' => $now,
                'blocked_until' => $blockedUntil,
                'blocked_by_agent_id' => $agentId,
                'closed_at' => $now,
                'updated_at' => $now,
                'id' => (int) $session['id'],
            )
        );

        $this->setSessionLiveStatus((int) $session['id'], 'member', '');
        $this->setSessionLiveStatus((int) $session['id'], 'agent', '');

        return true;
    }

    public function unblockSessionByAgent($sessionId, array $agent)
    {
        $this->ensureTables();

        $session = $this->sessionById((int) $sessionId);
        if (!$session) {
            throw new RuntimeException('客服会话不存在。');
        }

        if (!$this->agentHasPermission($agent, 'close')) {
            throw new RuntimeException('当前客服账号没有解除屏蔽权限。');
        }

        $this->releaseSessionBlock((int) $session['id']);

        return true;
    }

    public function closeSessionByAgent($sessionId, array $agent)
    {
        $this->ensureTables();

        $session = $this->sessionById((int) $sessionId);
        if (!$session) {
            throw new RuntimeException('客服会话不存在。');
        }

        $this->assertAgentCanWork($session, $agent, 'close');

        $this->db()->execute(
            'UPDATE customer_service_sessions
             SET status = :status,
                 unread_for_admin = 0,
                 member_typing_at = NULL,
                 agent_typing_at = NULL,
                 closed_at = :closed_at,
                 updated_at = :updated_at
             WHERE id = :id',
            array(
                'status' => 'closed',
                'closed_at' => $this->now(),
                'updated_at' => $this->now(),
                'id' => (int) $session['id'],
            )
        );

        $this->setSessionLiveStatus((int) $session['id'], 'member', '');
        $this->setSessionLiveStatus((int) $session['id'], 'agent', '');

        return true;
    }

    public function setMemberTyping($userId, $typing, $statusType = 'typing')
    {
        $this->ensureTables();

        $session = $this->memberSession((int) $userId);
        if (!$session) {
            return false;
        }
        $this->touchMemberPresence($session);

        $this->db()->execute(
            'UPDATE customer_service_sessions
             SET member_typing_at = :typing_at
             WHERE id = :id',
            array(
                'typing_at' => $typing ? $this->now() : null,
                'id' => (int) $session['id'],
            )
        );

        $this->setSessionLiveStatus((int) $session['id'], 'member', $typing ? $statusType : '');

        return true;
    }

    public function setAgentTyping($sessionId, array $agent, $typing, $statusType = 'typing')
    {
        $this->ensureTables();

        $session = $this->sessionById((int) $sessionId);
        if (!$session || (string) ($session['status'] ?? '') === 'closed') {
            return false;
        }

        $this->assertAgentCanWork($session, $agent, 'reply');
        if (!$this->agentIsServing($agent)) {
            return false;
        }
        $this->touchAgentPresence($agent);

        $this->db()->execute(
            'UPDATE customer_service_sessions
             SET agent_typing_at = :typing_at
             WHERE id = :id',
            array(
                'typing_at' => $typing ? $this->now() : null,
                'id' => (int) $session['id'],
            )
        );

        $this->setSessionLiveStatus((int) $session['id'], 'agent', $typing ? $statusType : '');

        return true;
    }

    public function memberUnreadCount($userId)
    {
        $payload = $this->memberUnreadPayload((int) $userId);

        return (int) ($payload['unread_count'] ?? 0);
    }

    public function memberUnreadPayload($userId)
    {
        $this->ensureTables();

        // 高频导航轮询使用短缓存，降低页面切换时的重复查询压力。
        $cacheKey = 'customer_service_member_unread_' . (int) $userId;
        $cachedPayload = $this->app->cache()->get($cacheKey, null, 2);
        if (is_array($cachedPayload)) {
            return $cachedPayload;
        }

        $row = $this->db()->fetch(
            "SELECT customer_service_sessions.unread_for_member,
                    COALESCE(MAX(customer_service_messages.id), 0) AS latest_message_id
             FROM customer_service_sessions
             LEFT JOIN customer_service_messages
                    ON customer_service_messages.session_id = customer_service_sessions.id
                   AND customer_service_messages.sender_type = 'agent'
                   AND customer_service_messages.user_deleted_at IS NULL
             WHERE customer_service_sessions.user_id = :user_id
             GROUP BY customer_service_sessions.id, customer_service_sessions.unread_for_member
             LIMIT 1",
            array('user_id' => (int) $userId)
        );

        if (!$row) {
            $payload = array(
                'unread_count' => 0,
                'latest_message_id' => 0,
            );
            $this->app->cache()->put($cacheKey, $payload);

            return $payload;
        }

        $payload = array(
            'unread_count' => max(0, (int) ($row['unread_for_member'] ?? 0)),
            'latest_message_id' => max(0, (int) ($row['latest_message_id'] ?? 0)),
        );
        $this->app->cache()->put($cacheKey, $payload);

        return $payload;
    }

    public function adminUnreadPayload()
    {
        $this->ensureTables();

        // 后台入口未读数短缓存，兼顾响应速度与消息实时性。
        $cacheKey = 'customer_service_admin_unread';
        $cachedPayload = $this->app->cache()->get($cacheKey, null, 2);
        if (is_array($cachedPayload)) {
            return $cachedPayload;
        }

        $row = $this->db()->fetch(
            "SELECT
                (SELECT COALESCE(SUM(unread_for_admin), 0) FROM customer_service_sessions) AS unread_count,
                (
                    SELECT COALESCE(MAX(customer_service_messages.id), 0)
                    FROM customer_service_messages
                    INNER JOIN customer_service_sessions ON customer_service_sessions.id = customer_service_messages.session_id
                    WHERE customer_service_messages.sender_type = 'member'
                ) AS latest_message_id"
        );

        if (!$row) {
            $payload = array(
                'unread_count' => 0,
                'latest_message_id' => 0,
            );
            $this->app->cache()->put($cacheKey, $payload);

            return $payload;
        }

        $payload = array(
            'unread_count' => max(0, (int) ($row['unread_count'] ?? 0)),
            'latest_message_id' => max(0, (int) ($row['latest_message_id'] ?? 0)),
        );
        $this->app->cache()->put($cacheKey, $payload);

        return $payload;
    }

    public function agentUnreadPayload(array $agent)
    {
        $this->ensureTables();

        $agentId = (int) ($agent['id'] ?? 0);
        if ($agentId <= 0) {
            return array(
                'unread_count' => 0,
                'latest_message_id' => 0,
            );
        }

        $cacheKey = 'customer_service_agent_unread_' . $agentId;
        $cachedPayload = $this->app->cache()->get($cacheKey, null, 2);
        if (is_array($cachedPayload)) {
            return $cachedPayload;
        }

        $where = array('(customer_service_sessions.agent_hidden_at IS NULL OR customer_service_sessions.blocked_at IS NOT NULL)');
        $params = array();
        if (!$this->agentHasPermission($agent, 'take')) {
            $where[] = '(customer_service_sessions.assigned_agent_id IS NULL OR customer_service_sessions.assigned_agent_id = :agent_id)';
            $params['agent_id'] = $agentId;
        }

        $sessionWhere = implode(' AND ', $where);
        $unreadRow = $this->db()->fetch(
            "SELECT COALESCE(SUM(customer_service_sessions.unread_for_admin), 0) AS unread_count
             FROM customer_service_sessions
             WHERE " . $sessionWhere,
            $params
        );
        $latestRow = $this->db()->fetch(
            "SELECT COALESCE(MAX(customer_service_messages.id), 0) AS latest_message_id
             FROM customer_service_messages
             INNER JOIN customer_service_sessions
                     ON customer_service_sessions.id = customer_service_messages.session_id
             WHERE customer_service_messages.sender_type = 'member'
               AND customer_service_messages.agent_deleted_at IS NULL
               AND " . $sessionWhere,
            $params
        );

        $payload = array(
            'unread_count' => max(0, (int) ($unreadRow['unread_count'] ?? 0)),
            'latest_message_id' => max(0, (int) ($latestRow['latest_message_id'] ?? 0)),
        );
        $this->app->cache()->put($cacheKey, $payload);

        return $payload;
    }

    public function adminOverview()
    {
        return $this->overview();
    }

    public function paymentSettings()
    {
        $settings = $this->app->settings();
        $paymentQrLists = $this->paymentQrLists();

        return array(
            'alipay_qr' => isset($paymentQrLists['alipay'][0]) ? $paymentQrLists['alipay'][0] : '',
            'wechat_qr' => isset($paymentQrLists['wechat'][0]) ? $paymentQrLists['wechat'][0] : '',
            'usdt_qr' => isset($paymentQrLists['usdt'][0]) ? $paymentQrLists['usdt'][0] : '',
            'usdt_address' => (string) $settings->get('customer_service_payment.usdt_address', ''),
        );
    }

    public function paymentQrLists()
    {
        $settings = $this->app->settings();

        return array(
            'alipay' => $this->normalizePaymentQrList($settings->get('customer_service_payment.alipay_qr', '')),
            'wechat' => $this->normalizePaymentQrList($settings->get('customer_service_payment.wechat_qr', '')),
            'usdt' => $this->normalizePaymentQrList($settings->get('customer_service_payment.usdt_qr', '')),
        );
    }

    public function saveAgentPaymentSettings(array $agent, array $payload, array $files)
    {
        $this->ensureTables();

        if ((int) ($agent['id'] ?? 0) <= 0) {
            throw new RuntimeException('客服账号不存在。');
        }

        $currentSettings = $this->paymentSettings();
        $usdtAddress = array_key_exists('usdt_address', $payload)
            ? $this->normalizeUsdtAddress((string) ($payload['usdt_address'] ?? ''))
            : (string) ($currentSettings['usdt_address'] ?? '');
        $usdtAddressChanged = array_key_exists('usdt_address', $payload)
            && $usdtAddress !== $this->normalizeUsdtAddress((string) ($currentSettings['usdt_address'] ?? ''));
        $usdtAddressSubmitted = array_key_exists('usdt_address', $payload)
            && (string) ($payload['payment_type'] ?? '') === 'usdt';
        $paymentQrLists = $this->paymentQrLists();
        $items = array(
            'customer_service_payment.alipay_qr' => $this->encodePaymentQrList($paymentQrLists['alipay']),
            'customer_service_payment.wechat_qr' => $this->encodePaymentQrList($paymentQrLists['wechat']),
            'customer_service_payment.usdt_qr' => $this->encodePaymentQrList($paymentQrLists['usdt']),
            'customer_service_payment.usdt_address' => $usdtAddress,
        );

        $deleteTarget = trim((string) ($payload['payment_qr_delete'] ?? ''));
        if ($deleteTarget !== '') {
            $this->deletePaymentQrFromList($paymentQrLists, $deleteTarget);
            foreach ($this->paymentQrTypes() as $type => $label) {
                $items['customer_service_payment.' . $type . '_qr'] = $this->encodePaymentQrList($paymentQrLists[$type]);
            }

            $this->app->settings()->setMany('customer_service_payment', $items);

            return $this->paymentSettings();
        }

        $savedQrCount = 0;
        foreach ($this->paymentQrTypes() as $type => $label) {
            $fieldName = 'payment_qr_' . $type;
            if (!isset($files[$fieldName]) || !is_array($files[$fieldName])) {
                continue;
            }

            foreach ($this->paymentQrUploadFiles($files[$fieldName]) as $paymentQrFile) {
                $errorCode = (int) ($paymentQrFile['error'] ?? UPLOAD_ERR_NO_FILE);
                if ($errorCode === UPLOAD_ERR_NO_FILE) {
                    continue;
                }

                $paymentQrLists[$type][] = $this->savePaymentQrImage(
                    $paymentQrFile,
                    $type,
                    $label
                );
                $savedQrCount++;
            }

            $items['customer_service_payment.' . $type . '_qr'] = $this->encodePaymentQrList($paymentQrLists[$type]);
        }

        if ($savedQrCount <= 0 && !$usdtAddressChanged && !$usdtAddressSubmitted) {
            throw new RuntimeException('请选择支付宝、微信或 USDT 二维码图片后再上传。');
        }

        $this->app->settings()->setMany('customer_service_payment', $items);

        return $this->paymentSettings();
    }

    public function saveAgentSettings(array $agent, array $payload)
    {
        $this->ensureTables();

        $agentId = (int) ($agent['id'] ?? 0);
        if ($agentId <= 0) {
            throw new RuntimeException('客服账号不存在。');
        }

        $displayName = $this->limitText((string) ($payload['display_name'] ?? ''), 80);
        if ($displayName === '') {
            $displayName = (string) ($agent['username'] ?? '客服');
        }

        $serviceHours = $this->normalizeServiceHours((string) ($payload['service_hours'] ?? ''));
        $welcomeText = $this->limitText((string) ($payload['welcome_text'] ?? ''), 255);
        $autoReplyText = $this->limitText((string) ($payload['auto_reply_text'] ?? ''), 1000);
        $activityNotice = $this->limitText((string) ($payload['activity_notice'] ?? ''), 2000);
        $activityNoticeEnabled = array_key_exists('activity_notice_enabled', $payload)
            ? ((string) $payload['activity_notice_enabled'] === '1' ? 1 : 0)
            : ((int) ($agent['activity_notice_enabled'] ?? ($activityNotice !== '' ? 1 : 0)) === 1 ? 1 : 0);
        $now = $this->now();

        $this->rememberAgentNicknameChange($agent, $displayName, $now);

        $this->db()->execute(
            'UPDATE customer_service_accounts
             SET display_name = :display_name,
                 service_hours = :service_hours,
                 welcome_text = :welcome_text,
                 auto_reply_text = :auto_reply_text,
                 activity_notice = :activity_notice,
                 activity_notice_enabled = :activity_notice_enabled,
                 updated_at = :updated_at
             WHERE id = :id',
            array(
                'display_name' => $displayName,
                'service_hours' => $serviceHours,
                'welcome_text' => $welcomeText,
                'auto_reply_text' => $autoReplyText,
                'activity_notice' => $activityNotice,
                'activity_notice_enabled' => $activityNoticeEnabled,
                'updated_at' => $now,
                'id' => $agentId,
            )
        );
        $this->saveForumGuideRules($payload);

        $this->clearAgentsRequestCache();
        return $this->agentById($agentId);
    }

    public function deleteAgentNicknameOption(array $agent, $name)
    {
        $this->ensureTables();

        $agentId = (int) ($agent['id'] ?? 0);
        if ($agentId <= 0) {
            throw new RuntimeException('客服账号不存在。');
        }

        $name = $this->limitText((string) $name, 80);
        if ($name === '') {
            throw new RuntimeException('请选择要删除的昵称。');
        }

        $deleted = $this->readAgentNicknameDeleted($agentId);
        $deleted[$name] = true;
        $this->writeAgentNicknameDeleted($agentId, $deleted);

        return $this->agentById($agentId);
    }

    public function saveAgent(array $payload, array $operator)
    {
        $this->ensureTables();
        $this->assertSuperAdmin($operator);

        $agentId = max(0, (int) ($payload['id'] ?? 0));
        $username = trim((string) ($payload['username'] ?? ''));
        $displayName = trim((string) ($payload['display_name'] ?? ''));
        $password = (string) ($payload['password'] ?? '');

        if (!preg_match('/^[A-Za-z0-9_]{3,32}$/', $username)) {
            throw new RuntimeException('客服账号只能使用 3-32 位字母、数字或下划线。');
        }

        if ($displayName === '') {
            $displayName = $username;
        }

        if ($agentId <= 0 && strlen($password) < 6) {
            throw new RuntimeException('新增客服账号密码至少 6 位。');
        }

        if ($password !== '' && strlen($password) < 6) {
            throw new RuntimeException('客服账号密码至少 6 位。');
        }

        $welcomeText = $this->limitText((string) ($payload['welcome_text'] ?? ''), 255);
        $serviceHours = $this->normalizeServiceHours((string) ($payload['service_hours'] ?? ''));
        $autoReplyText = $this->limitText((string) ($payload['auto_reply_text'] ?? ''), 1000);
        $activityNotice = $this->limitText((string) ($payload['activity_notice'] ?? ''), 2000);
        $activityNoticeEnabled = (string) ($payload['activity_notice_enabled'] ?? ($activityNotice !== '' ? '1' : '0')) === '1' ? 1 : 0;
        $autoReplyEnabled = (string) ($payload['auto_reply_enabled'] ?? '0') === '1' ? 1 : 0;
        if ($autoReplyEnabled === 1 && $autoReplyText === '') {
            throw new RuntimeException('启用自动回复时，请先填写自动回复语。');
        }
        $status = (string) ($payload['status'] ?? 'online') === 'offline' ? 'offline' : 'online';
        $sortOrder = max(0, min(9999, (int) ($payload['sort_order'] ?? 50)));
        $permissions = $this->normalizeAgentPermissions($payload);
        $now = $this->now();

        $duplicate = $this->db()->fetch(
            'SELECT id
             FROM customer_service_accounts
             WHERE username = :username
               AND deleted_at IS NULL
               AND id <> :id
             LIMIT 1',
            array(
                'username' => $username,
                'id' => $agentId,
            )
        );
        if ($duplicate) {
            throw new RuntimeException('客服账号已存在。');
        }

        if ($agentId > 0) {
            $existing = $this->agentById($agentId);
            if (!$existing) {
                throw new RuntimeException('客服账号不存在。');
            }
            if (!array_key_exists('activity_notice', $payload)) {
                $activityNotice = (string) ($existing['activity_notice'] ?? '');
            }
            if (!array_key_exists('activity_notice_enabled', $payload)) {
                $activityNoticeEnabled = (int) ($existing['activity_notice_enabled'] ?? ($activityNotice !== '' ? 1 : 0)) === 1 ? 1 : 0;
            }

            $this->rememberAgentNicknameChange($existing, $displayName, $now);

            $params = array(
                'username' => $username,
                'display_name' => $displayName,
                'welcome_text' => $welcomeText,
                'service_hours' => $serviceHours,
                'auto_reply_text' => $autoReplyText,
                'activity_notice' => $activityNotice,
                'activity_notice_enabled' => $activityNoticeEnabled,
                'auto_reply_enabled' => $autoReplyEnabled,
                'status' => $status,
                'sort_order' => $sortOrder,
                'permissions_json' => json_encode($permissions, JSON_UNESCAPED_UNICODE),
                'updated_at' => $now,
                'id' => $agentId,
            );
            $passwordSql = '';
            if ($password !== '') {
                $passwordSql = ', password_hash = :password_hash';
                $params['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            }

            $this->db()->execute(
                'UPDATE customer_service_accounts
                 SET username = :username,
                     display_name = :display_name,
                     welcome_text = :welcome_text,
                     service_hours = :service_hours,
                     auto_reply_text = :auto_reply_text,
                     activity_notice = :activity_notice,
                     activity_notice_enabled = :activity_notice_enabled,
                     auto_reply_enabled = :auto_reply_enabled,
                     status = :status,
                     sort_order = :sort_order,
                     permissions_json = :permissions_json,
                     updated_at = :updated_at' . $passwordSql . '
                 WHERE id = :id',
                $params
            );

            $this->clearAgentsRequestCache();
            return true;
        }

        $this->db()->insertGetId(
            'INSERT INTO customer_service_accounts (username, password_hash, display_name, welcome_text, service_hours, auto_reply_text, activity_notice, activity_notice_enabled, auto_reply_enabled, status, permissions_json, sort_order, last_login_at, last_login_ip, created_at, updated_at, deleted_at)
             VALUES (:username, :password_hash, :display_name, :welcome_text, :service_hours, :auto_reply_text, :activity_notice, :activity_notice_enabled, :auto_reply_enabled, :status, :permissions_json, :sort_order, :last_login_at, :last_login_ip, :created_at, :updated_at, :deleted_at)',
            array(
                'username' => $username,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'display_name' => $displayName,
                'welcome_text' => $welcomeText,
                'service_hours' => $serviceHours,
                'auto_reply_text' => $autoReplyText,
                'activity_notice' => $activityNotice,
                'activity_notice_enabled' => $activityNoticeEnabled,
                'auto_reply_enabled' => $autoReplyEnabled,
                'status' => $status,
                'permissions_json' => json_encode($permissions, JSON_UNESCAPED_UNICODE),
                'sort_order' => $sortOrder,
                'last_login_at' => null,
                'last_login_ip' => '',
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            )
        );

        $this->clearAgentsRequestCache();
        return true;
    }

    public function deleteAgent($agentId, array $operator)
    {
        $this->ensureTables();
        $this->assertSuperAdmin($operator);

        $agent = $this->agentById((int) $agentId);
        if (!$agent) {
            throw new RuntimeException('客服账号不存在。');
        }

        $this->db()->execute(
            'UPDATE customer_service_accounts
             SET status = :status,
                 deleted_at = :deleted_at,
                 updated_at = :updated_at
             WHERE id = :id',
            array(
                'status' => 'offline',
                'deleted_at' => $this->now(),
                'updated_at' => $this->now(),
                'id' => (int) $agentId,
            )
        );

        $this->clearAgentsRequestCache();
        $this->db()->execute(
            'UPDATE customer_service_sessions
             SET assigned_agent_id = NULL,
                 status = CASE WHEN status = :open_status THEN :waiting_status ELSE status END,
                 updated_at = :updated_at
             WHERE assigned_agent_id = :assigned_agent_id',
            array(
                'open_status' => 'open',
                'waiting_status' => 'waiting',
                'updated_at' => $this->now(),
                'assigned_agent_id' => (int) $agentId,
            )
        );

        return true;
    }

    public function agents()
    {
        $this->ensureTables();

        return array_map(function (array $agent) {
            return $this->formatAgent($agent, true);
        }, $this->agentRowsForRequest());
    }

    public function agentById($agentId)
    {
        $this->ensureTables();
        $agentId = (int) $agentId;
        if ($agentId <= 0) {
            return null;
        }

        if ($this->agentsRequestCache !== null) {
            foreach ($this->agentsRequestCache as $agent) {
                if ((int) ($agent['id'] ?? 0) === $agentId) {
                    return $agent;
                }
            }

            return null;
        }

        return $this->db()->fetch(
            'SELECT *
             FROM customer_service_accounts
             WHERE id = :id AND deleted_at IS NULL
             LIMIT 1',
            array('id' => $agentId)
        );
    }

    public function agentForManagement($agentId)
    {
        $agent = $this->agentById((int) $agentId);

        return $agent ? $this->formatAgent($agent, true) : null;
    }

    protected function agentRowsForRequest()
    {
        if ($this->agentsRequestCache !== null) {
            return $this->agentsRequestCache;
        }

        $this->agentsRequestCache = $this->db()->fetchAll(
            'SELECT *
             FROM customer_service_accounts
             WHERE deleted_at IS NULL
             ORDER BY status DESC, sort_order ASC, id ASC'
        );

        return $this->agentsRequestCache;
    }

    protected function clearAgentsRequestCache()
    {
        $this->agentsRequestCache = null;
    }

    public function adminOptions()
    {
        return array();
    }

    public function memberSession($userId)
    {
        $this->ensureTables();

        return $this->db()->fetch(
            'SELECT customer_service_sessions.*,
                    users.username,
                    users.score AS member_score,
                    customer_service_accounts.display_name AS assigned_agent_name,
                    customer_service_accounts.status AS assigned_agent_status,
                    (SELECT MAX(page_views.created_at) FROM page_views WHERE page_views.user_id = customer_service_sessions.user_id) AS member_last_seen_at
             FROM customer_service_sessions
             INNER JOIN users ON users.id = customer_service_sessions.user_id
             LEFT JOIN customer_service_accounts ON customer_service_accounts.id = customer_service_sessions.assigned_agent_id
             WHERE customer_service_sessions.user_id = :user_id
             LIMIT 1',
            array('user_id' => (int) $userId)
        );
    }

    public function sessionById($sessionId, $cacheTtl = 0)
    {
        $this->ensureTables();

        $sessionId = (int) $sessionId;
        $cacheTtl = (int) $cacheTtl;
        $cacheKey = 'customer_service_session_by_id_' . $sessionId;
        if ($cacheTtl > 0) {
            $cachedSession = $this->app->cache()->get($cacheKey, null, $cacheTtl);
            if (is_array($cachedSession)) {
                return $cachedSession;
            }
        }

        $session = $this->db()->fetch(
            'SELECT customer_service_sessions.*,
                    users.username,
                    users.score AS member_score,
                    customer_service_accounts.display_name AS assigned_agent_name,
                    customer_service_accounts.status AS assigned_agent_status,
                    (SELECT MAX(page_views.created_at) FROM page_views WHERE page_views.user_id = customer_service_sessions.user_id) AS member_last_seen_at
             FROM customer_service_sessions
             INNER JOIN users ON users.id = customer_service_sessions.user_id
             LEFT JOIN customer_service_accounts ON customer_service_accounts.id = customer_service_sessions.assigned_agent_id
             WHERE customer_service_sessions.id = :id
             LIMIT 1',
            array('id' => $sessionId)
        );

        if ($cacheTtl > 0 && is_array($session)) {
            $this->app->cache()->put($cacheKey, $session);
        }

        return $session;
    }

    protected function sessionListConditions($status = 'all', array $agent = null)
    {
        $status = $this->normalizeStatusFilter($status);
        $where = array();
        $params = array();

        if ($status === 'unread') {
            $where[] = 'customer_service_sessions.unread_for_admin > 0';
        } elseif ($status !== 'all') {
            $where[] = 'customer_service_sessions.status = :status';
            $params['status'] = $status;
        }

        if ($agent !== null) {
            $where[] = '(customer_service_sessions.agent_hidden_at IS NULL OR customer_service_sessions.blocked_at IS NOT NULL)';
        }

        if ($agent !== null && !$this->agentHasPermission($agent, 'take')) {
            $where[] = '(customer_service_sessions.assigned_agent_id IS NULL OR customer_service_sessions.assigned_agent_id = :agent_id)';
            $params['agent_id'] = (int) ($agent['id'] ?? 0);
        }

        return array('where' => $where, 'params' => $params);
    }

    public function listSessions($status = 'all', array $agent = null)
    {
        $this->ensureTables();
        $this->releaseExpiredSessionBlocks();

        $conditions = $this->sessionListConditions($status, $agent);
        $where = $conditions['where'];
        $params = $conditions['params'];

        $sql = 'SELECT customer_service_sessions.*,
                       users.username,
                       users.score AS member_score,
                       customer_service_accounts.display_name AS assigned_agent_name,
                       customer_service_accounts.status AS assigned_agent_status,
                       (
                           SELECT customer_service_latest_message.created_at
                           FROM customer_service_messages customer_service_latest_message
                           WHERE customer_service_latest_message.session_id = customer_service_sessions.id
                             AND customer_service_latest_message.agent_deleted_at IS NULL
                           ORDER BY customer_service_latest_message.id DESC
                           LIMIT 1
                       ) AS agent_latest_message_at,
                       (SELECT MAX(page_views.created_at) FROM page_views WHERE page_views.user_id = customer_service_sessions.user_id) AS member_last_seen_at
                FROM customer_service_sessions
                INNER JOIN users ON users.id = customer_service_sessions.user_id
                LEFT JOIN customer_service_accounts ON customer_service_accounts.id = customer_service_sessions.assigned_agent_id';

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY COALESCE(agent_latest_message_at, customer_service_sessions.last_message_at, customer_service_sessions.updated_at) DESC, customer_service_sessions.last_message_at DESC, customer_service_sessions.updated_at DESC, customer_service_sessions.id DESC LIMIT 120';

        return $this->db()->fetchAll($sql, $params);
    }

    protected function agentSessionsStamp($status, array $agent)
    {
        $status = $this->normalizeStatusFilter($status);
        $agentId = (int) ($agent['id'] ?? 0);
        $cacheKey = 'customer_service_agent_sessions_stamp_' . md5($status . '|' . $agentId . '|' . (string) ($agent['permissions_json'] ?? ''));
        $cachedStamp = $this->app->cache()->get($cacheKey, '', 2);
        if (is_string($cachedStamp) && $cachedStamp !== '') {
            return $cachedStamp;
        }

        $conditions = $this->sessionListConditions($status, $agent);
        $where = $conditions['where'];
        $params = $conditions['params'];

        $sql = 'SELECT COUNT(*) AS total_count,
                       COALESCE(SUM(customer_service_sessions.unread_for_admin), 0) AS unread_total,
                       COALESCE(MAX(customer_service_sessions.id), 0) AS max_session_id,
                       COALESCE(MAX(UNIX_TIMESTAMP(customer_service_sessions.updated_at)), 0) AS max_updated_at,
                       COALESCE(MAX(UNIX_TIMESTAMP(customer_service_sessions.last_message_at)), 0) AS max_last_message_at,
                       COALESCE(MAX(UNIX_TIMESTAMP(customer_service_sessions.blocked_at)), 0) AS max_blocked_at,
                       COALESCE(MAX(UNIX_TIMESTAMP(customer_service_sessions.blocked_until)), 0) AS max_blocked_until
                FROM customer_service_sessions';

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $row = $this->db()->fetch($sql, $params) ?: array();
        $source = array(
            $this->normalizeStatusFilter($status),
            (int) ($row['total_count'] ?? 0),
            (int) ($row['unread_total'] ?? 0),
            (int) ($row['max_session_id'] ?? 0),
            (int) ($row['max_updated_at'] ?? 0),
            (int) ($row['max_last_message_at'] ?? 0),
            (int) ($row['max_blocked_at'] ?? 0),
            (int) ($row['max_blocked_until'] ?? 0),
        );

        $stamp = hash('sha256', implode('|', $source));
        $this->app->cache()->put($cacheKey, $stamp);

        return $stamp;
    }

    public function messages($sessionId, $viewer)
    {
        $this->ensureTables();

        $viewer = (string) $viewer;
        $visibilitySql = '';
        if ($viewer === 'member') {
            $visibilitySql = ' AND customer_service_messages.user_deleted_at IS NULL';
        } elseif ($viewer !== 'admin') {
            $visibilitySql = ' AND customer_service_messages.agent_deleted_at IS NULL';
        }

        return $this->db()->fetchAll(
            'SELECT customer_service_messages.*,
                    users.username AS user_name,
                    customer_service_accounts.display_name AS agent_name
             FROM customer_service_messages
             LEFT JOIN users ON users.id = customer_service_messages.sender_user_id
             LEFT JOIN customer_service_accounts ON customer_service_accounts.id = customer_service_messages.sender_agent_id
             WHERE customer_service_messages.session_id = :session_id' . $visibilitySql . '
             ORDER BY customer_service_messages.id ASC
             LIMIT 200',
            array('session_id' => (int) $sessionId)
        );
    }

    public function latestVisibleMessageId($sessionId, $viewer, $cacheTtl = 0)
    {
        $this->ensureTables();

        $sessionId = (int) $sessionId;
        $viewer = (string) $viewer;
        $visibilitySql = '';
        if ($viewer === 'member') {
            $visibilitySql = ' AND user_deleted_at IS NULL';
        } elseif ($viewer !== 'admin') {
            $visibilitySql = ' AND agent_deleted_at IS NULL';
        }

        $cacheTtl = (int) $cacheTtl;
        $cacheKey = 'customer_service_latest_visible_message_' . md5($viewer . '|' . $sessionId . '|' . $visibilitySql);
        if ($cacheTtl > 0) {
            $cachedLatestId = $this->app->cache()->get($cacheKey, null, $cacheTtl);
            if ($cachedLatestId !== null) {
                return max(0, (int) $cachedLatestId);
            }
        }

        $row = $this->db()->fetch(
            'SELECT COALESCE(MAX(id), 0) AS latest_id
             FROM customer_service_messages
             WHERE session_id = :session_id' . $visibilitySql,
            array('session_id' => $sessionId)
        );

        $latestId = (int) ($row['latest_id'] ?? 0);
        if ($cacheTtl > 0) {
            $this->app->cache()->put($cacheKey, $latestId);
        }

        return $latestId;
    }

    public function latestMemberVisibleMessageId($userId)
    {
        $session = $this->memberSession((int) $userId);
        if (!$session) {
            return 0;
        }

        return $this->latestVisibleMessageId((int) $session['id'], 'member');
    }

    public function latestAgentVisibleMessageId($sessionId, array $agent)
    {
        $session = (int) $sessionId > 0 ? $this->sessionById((int) $sessionId) : null;
        if (!$session || !$this->agentCanSeeSession($session, $agent)) {
            return 0;
        }

        return $this->latestVisibleMessageId((int) $session['id'], 'agent');
    }

    public function markMemberRead($sessionId)
    {
        $this->ensureTables();

        $this->db()->execute(
            'UPDATE customer_service_sessions
             SET unread_for_member = 0, updated_at = :updated_at
             WHERE id = :id AND unread_for_member > 0',
            array(
                'updated_at' => $this->now(),
                'id' => (int) $sessionId,
            )
        );
    }

    public function markAgentRead($sessionId)
    {
        $this->ensureTables();

        $this->db()->execute(
            'UPDATE customer_service_sessions
             SET unread_for_admin = 0, updated_at = :updated_at
             WHERE id = :id AND unread_for_admin > 0',
            array(
                'updated_at' => $this->now(),
                'id' => (int) $sessionId,
            )
        );
    }

    public function notifyInviteRegisterReward(array $inviteUser, $registeredUsername, $rewardScore)
    {
        $this->ensureTables();

        $userId = (int) ($inviteUser['id'] ?? 0);
        $rewardScore = max(0, (int) $rewardScore);
        $registeredUsername = trim((string) $registeredUsername);
        if ($userId <= 0 || $rewardScore <= 0 || $registeredUsername === '') {
            return;
        }

        $session = $this->getOrCreateMemberSession($userId);
        $sessionId = (int) ($session['id'] ?? 0);
        if ($sessionId <= 0) {
            return;
        }

        $content = '您的邀请好友「' . $registeredUsername . '」已注册成功，邀请奖励 +' . $rewardScore . ' 积分已到账。';
        if ($this->inviteRewardNotificationExists($sessionId, $content)) {
            return;
        }

        $message = $this->textMessage($content);
        $now = $this->now();

        $this->insertMessage($sessionId, 'system', null, null, $message, $now);
        $this->db()->execute(
            "UPDATE customer_service_sessions
             SET status = CASE WHEN status = 'closed' THEN 'waiting' ELSE status END,
                 unread_for_member = unread_for_member + 1,
                 last_message_type = :last_message_type,
                 last_message_preview = :last_message_preview,
                 last_message_at = :last_message_at,
                 closed_at = NULL,
                 updated_at = :updated_at
             WHERE id = :id",
            array(
                'last_message_type' => $message['type'],
                'last_message_preview' => $this->preview($message),
                'last_message_at' => $now,
                'updated_at' => $now,
                'id' => $sessionId,
            )
        );
    }

    protected function getOrCreateMemberSession($userId)
    {
        $session = $this->memberSession((int) $userId);
        if ($session) {
            return $session;
        }

        $now = $this->now();
        $sessionId = $this->db()->insertGetId(
            'INSERT INTO customer_service_sessions (session_key, user_id, assigned_agent_id, status, unread_for_member, unread_for_admin, last_message_type, last_message_preview, last_message_at, closed_at, created_at, updated_at)
             VALUES (:session_key, :user_id, :assigned_agent_id, :status, :unread_for_member, :unread_for_admin, :last_message_type, :last_message_preview, :last_message_at, :closed_at, :created_at, :updated_at)',
            array(
                'session_key' => 'member-' . (int) $userId,
                'user_id' => (int) $userId,
                'assigned_agent_id' => null,
                'status' => 'waiting',
                'unread_for_member' => 0,
                'unread_for_admin' => 0,
                'last_message_type' => 'text',
                'last_message_preview' => '',
                'last_message_at' => $now,
                'closed_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            )
        );

        return $this->sessionById($sessionId);
    }

    protected function sendMemberWelcomeIfNeeded($userId, array $session)
    {
        $sessionId = (int) ($session['id'] ?? 0);
        if ($sessionId <= 0
            || $this->sessionBlockActive($session)
            || $this->memberWelcomeSentInCurrentLogin((int) $userId, $sessionId)) {
            return $session;
        }

        $agent = null;
        $assignedAgentId = (int) ($session['assigned_agent_id'] ?? 0);
        if ($assignedAgentId > 0) {
            $agent = $this->agentById($assignedAgentId);
        }
        if (!$agent) {
            $agent = $this->preferredServiceAgent(false);
        }

        $profile = $this->serviceProfileFromAgent($agent);
        $content = $this->limitText((string) ($profile['welcome_text'] ?? ''), 255);
        if ($content === '') {
            return $session;
        }

        $agentId = is_array($agent) ? (int) ($agent['id'] ?? 0) : 0;
        $message = $this->textMessage($content);
        $now = $this->now();

        if ($this->memberWelcomeMessageExists($sessionId, $content)) {
            $this->markMemberWelcomeSentForCurrentLogin((int) $userId, $sessionId);

            return $session;
        }

        $this->db()->beginTransaction();
        try {
            $this->lockCustomerServiceSession($sessionId);
            if ($this->memberWelcomeMessageExists($sessionId, $content)) {
                $this->db()->commit();
                $this->markMemberWelcomeSentForCurrentLogin((int) $userId, $sessionId);

                return $this->sessionById($sessionId) ?: $session;
            }

            $this->insertMessage(
                $sessionId,
                'agent',
                null,
                $agentId > 0 ? $agentId : null,
                $message,
                $now
            );

            $sessionSql = 'UPDATE customer_service_sessions
                 SET status = :status,
                     unread_for_member = unread_for_member + 1,
                     last_message_type = :last_message_type,
                     last_message_preview = :last_message_preview,
                     last_message_at = :last_message_at,
                     closed_at = NULL,
                     updated_at = :updated_at
                 WHERE id = :id';
            $sessionParams = array(
                'status' => $agentId > 0 ? 'open' : 'waiting',
                'last_message_type' => $message['type'],
                'last_message_preview' => $this->preview($message),
                'last_message_at' => $now,
                'updated_at' => $now,
                'id' => $sessionId,
            );
            if ($agentId > 0) {
                $sessionSql = 'UPDATE customer_service_sessions
                 SET assigned_agent_id = CASE WHEN assigned_agent_id IS NULL THEN :assigned_agent_id ELSE assigned_agent_id END,
                     status = :status,
                     unread_for_member = unread_for_member + 1,
                     last_message_type = :last_message_type,
                     last_message_preview = :last_message_preview,
                     last_message_at = :last_message_at,
                     closed_at = NULL,
                     updated_at = :updated_at
                 WHERE id = :id';
                $sessionParams['assigned_agent_id'] = $agentId;
            }

            $this->db()->execute($sessionSql, $sessionParams);
            $this->db()->commit();
            $this->markMemberWelcomeSentForCurrentLogin((int) $userId, $sessionId);
        } catch (\Throwable $exception) {
            $this->db()->rollBack();
            throw $exception;
        }

        return $this->sessionById($sessionId);
    }

    protected function lockCustomerServiceSession($sessionId)
    {
        $this->db()->fetch(
            'SELECT id
             FROM customer_service_sessions
             WHERE id = :id
             FOR UPDATE',
            array('id' => (int) $sessionId)
        );
    }

    protected function memberWelcomeMessageExists($sessionId, $content)
    {
        $row = $this->db()->fetch(
            'SELECT id
             FROM customer_service_messages
             WHERE session_id = :session_id
               AND sender_type = :sender_type
               AND message_type = :message_type
               AND content = :content
             ORDER BY id DESC
             LIMIT 1',
            array(
                'session_id' => (int) $sessionId,
                'sender_type' => 'agent',
                'message_type' => 'text',
                'content' => (string) $content,
            )
        );

        return (bool) $row;
    }

    protected function memberWelcomeSentInCurrentLogin($userId, $sessionId)
    {
        $sentKeys = Session::get(self::WELCOME_SEND_SESSION_KEY, array());
        $sentKeys = is_array($sentKeys) ? $sentKeys : array();

        return isset($sentKeys[$this->memberWelcomeSendKey((int) $userId, (int) $sessionId)]);
    }

    protected function markMemberWelcomeSentForCurrentLogin($userId, $sessionId)
    {
        $sentKeys = Session::get(self::WELCOME_SEND_SESSION_KEY, array());
        $sentKeys = is_array($sentKeys) ? $sentKeys : array();
        $sentKeys[$this->memberWelcomeSendKey((int) $userId, (int) $sessionId)] = time();

        Session::put(self::WELCOME_SEND_SESSION_KEY, $sentKeys);
    }

    protected function memberWelcomeSendKey($userId, $sessionId)
    {
        return hash('sha256', implode('|', array(
            (string) (int) $userId,
            (string) (int) $sessionId,
            trim((string) Session::get('logged_in_at', '')),
            function_exists('session_id') ? (string) session_id() : '',
            date('Y-m-d'),
        )));
    }

    protected function normalizeMessagePayload(array $payload, $file, $viewer)
    {
        $type = (string) ($payload['message_type'] ?? 'text');
        if (!in_array($type, array('text', 'emoji', 'image', 'voice'), true)) {
            $type = 'text';
        }

        $content = trim((string) ($payload['content'] ?? ''));
        $message = array(
            'type' => $type,
            'content' => '',
            'attachment_url' => '',
            'attachment_name' => '',
            'attachment_mime' => '',
            'attachment_size' => 0,
            'voice_duration' => 0,
        );

        if ($type === 'image' || $type === 'voice') {
            $attachment = $this->saveMessageAttachment($file, $type);
            $message = array_merge($message, $attachment);
            $message['content'] = $type === 'image' ? '图片' : '语音';
            $message['voice_duration'] = $type === 'voice' ? $this->normalizeDuration($payload['duration'] ?? 0) : 0;

            return $message;
        }

        if ($type === 'emoji') {
            $message['content'] = $this->limitText($content, 40);
            if ($message['content'] === '') {
                throw new RuntimeException('请选择要发送的表情。');
            }

            return $message;
        }

        $message['content'] = $this->limitText($content, 1000);
        if ($message['content'] === '') {
            throw new RuntimeException($viewer === 'agent' ? '请输入客服回复内容。' : '请输入消息内容。');
        }

        return $message;
    }

    protected function insertMessage($sessionId, $senderType, $senderUserId, $senderAgentId, array $message, $createdAt)
    {
        $this->db()->execute(
            'INSERT INTO customer_service_messages (session_id, sender_type, sender_user_id, sender_agent_id, message_type, content, attachment_url, attachment_name, attachment_mime, attachment_size, voice_duration, user_deleted_at, agent_deleted_at, created_at)
             VALUES (:session_id, :sender_type, :sender_user_id, :sender_agent_id, :message_type, :content, :attachment_url, :attachment_name, :attachment_mime, :attachment_size, :voice_duration, :user_deleted_at, :agent_deleted_at, :created_at)',
            array(
                'session_id' => (int) $sessionId,
                'sender_type' => (string) $senderType,
                'sender_user_id' => $senderUserId,
                'sender_agent_id' => $senderAgentId,
                'message_type' => (string) $message['type'],
                'content' => (string) $message['content'],
                'attachment_url' => (string) $message['attachment_url'],
                'attachment_name' => (string) $message['attachment_name'],
                'attachment_mime' => (string) $message['attachment_mime'],
                'attachment_size' => (int) $message['attachment_size'],
                'voice_duration' => (int) $message['voice_duration'],
                'user_deleted_at' => null,
                'agent_deleted_at' => null,
                'created_at' => (string) $createdAt,
            )
        );
    }

    protected function shouldSkipDuplicateSend($sessionId, $senderType, array $message)
    {
        $sessionId = (int) $sessionId;
        $senderType = (string) $senderType;
        $fingerprint = hash('sha256', implode('|', array(
            (string) $sessionId,
            $senderType,
            (string) ($message['type'] ?? ''),
            (string) ($message['content'] ?? ''),
            (string) ($message['attachment_url'] ?? ''),
            (string) ($message['attachment_name'] ?? ''),
            (string) ($message['voice_duration'] ?? 0),
        )));
        $now = time();
        $recentSends = Session::get(self::RECENT_SEND_SESSION_KEY, array());
        $recentSends = is_array($recentSends) ? $recentSends : array();
        $activeSends = array();

        foreach ($recentSends as $key => $row) {
            if (!is_array($row)) {
                continue;
            }

            $sentAt = isset($row['sent_at']) ? (int) $row['sent_at'] : 0;
            if ($sentAt > 0 && ($now - $sentAt) <= self::RECENT_SEND_TTL_SECONDS) {
                $activeSends[(string) $key] = array('sent_at' => $sentAt);
            }
        }

        if (isset($activeSends[$fingerprint])) {
            Session::put(self::RECENT_SEND_SESSION_KEY, $activeSends);

            return true;
        }

        $activeSends[$fingerprint] = array('sent_at' => $now);
        Session::put(self::RECENT_SEND_SESSION_KEY, $activeSends);

        return false;
    }

    protected function saveMessageAttachment($file, $messageType)
    {
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException($messageType === 'image' ? '请选择要发送的图片。' : '请录制要发送的语音。');
        }

        $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode !== UPLOAD_ERR_OK) {
            throw new RuntimeException($this->uploadErrorMessage($errorCode));
        }

        $originalName = trim((string) ($file['name'] ?? ''));
        $tmpName = (string) ($file['tmp_name'] ?? '');
        $fileSize = (int) ($file['size'] ?? 0);

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('上传文件无效，请重新选择。');
        }

        $mimeType = $this->detectMimeType($tmpName);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension === '') {
            $extension = $this->extensionByMime($mimeType, $messageType);
        }

        if ($messageType === 'image') {
            $this->assertImageAttachment($tmpName, $mimeType, $extension, $fileSize);
        } else {
            $this->assertVoiceAttachment($mimeType, $extension, $fileSize);
        }

        $subDirectory = date('Ym');
        $relativeDirectory = 'uploads/customer_service/' . $subDirectory;
        $absoluteDirectory = $this->app->basePath('public/' . $relativeDirectory);
        if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0777, true) && !is_dir($absoluteDirectory)) {
            throw new RuntimeException('客服上传目录创建失败，请检查目录权限。');
        }

        $storageName = date('YmdHis') . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
        $absolutePath = $absoluteDirectory . DIRECTORY_SEPARATOR . $storageName;
        if (!move_uploaded_file($tmpName, $absolutePath)) {
            throw new RuntimeException('文件保存失败，请重试。');
        }

        return array(
            'attachment_url' => \public_url($relativeDirectory . '/' . $storageName),
            'attachment_name' => $originalName !== '' ? $this->limitText($originalName, 140) : $storageName,
            'attachment_mime' => $mimeType,
            'attachment_size' => $fileSize,
        );
    }

    protected function paymentQrTypes()
    {
        return array(
            'alipay' => '支付宝',
            'wechat' => '微信',
            'usdt' => 'USDT',
        );
    }

    protected function normalizeUsdtAddress($value)
    {
        $value = preg_replace('/\s+/u', '', trim((string) $value));

        return $this->limitText($value, 255);
    }

    protected function normalizePaymentQrList($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return array();
        }

        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return $this->cleanPaymentQrList($decoded);
        }

        return $this->cleanPaymentQrList(array($value));
    }

    protected function cleanPaymentQrList(array $items)
    {
        $urls = array();

        foreach ($items as $item) {
            if (is_array($item)) {
                $item = isset($item['url']) ? $item['url'] : '';
            }

            $url = trim((string) $item);
            if ($url === '') {
                continue;
            }

            $urls[] = $this->limitText($url, 500);
        }

        return array_values(array_unique($urls));
    }

    protected function encodePaymentQrList(array $items)
    {
        $items = $this->cleanPaymentQrList($items);
        if (!$items) {
            return '';
        }

        $encoded = json_encode($items, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return is_string($encoded) ? $encoded : '';
    }

    protected function paymentQrUploadFiles(array $file)
    {
        if (!isset($file['name']) || !is_array($file['name'])) {
            return array($file);
        }

        $files = array();
        $total = count($file['name']);
        for ($index = 0; $index < $total; $index++) {
            $files[] = array(
                'name' => isset($file['name'][$index]) ? $file['name'][$index] : '',
                'type' => isset($file['type'][$index]) ? $file['type'][$index] : '',
                'tmp_name' => isset($file['tmp_name'][$index]) ? $file['tmp_name'][$index] : '',
                'error' => isset($file['error'][$index]) ? $file['error'][$index] : UPLOAD_ERR_NO_FILE,
                'size' => isset($file['size'][$index]) ? $file['size'][$index] : 0,
            );
        }

        return $files;
    }

    protected function deletePaymentQrFromList(array &$paymentQrLists, $deleteTarget)
    {
        $parts = explode(':', (string) $deleteTarget, 2);
        $type = isset($parts[0]) ? (string) $parts[0] : '';
        $indexText = isset($parts[1]) ? (string) $parts[1] : '';

        if (!array_key_exists($type, $this->paymentQrTypes()) || !ctype_digit($indexText)) {
            throw new RuntimeException('删除二维码参数无效。');
        }

        $index = (int) $indexText;
        if (!isset($paymentQrLists[$type]) || !array_key_exists($index, $paymentQrLists[$type])) {
            throw new RuntimeException('该二维码不存在或已删除。');
        }

        $deletedUrl = (string) $paymentQrLists[$type][$index];
        $this->deletePaymentQrStorageFile($deletedUrl);

        unset($paymentQrLists[$type][$index]);
        $paymentQrLists[$type] = array_values($paymentQrLists[$type]);
    }

    protected function deletePaymentQrStorageFile($url)
    {
        $url = trim((string) $url);
        if ($url === '') {
            return;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || trim($path) === '') {
            return;
        }

        $path = ltrim(str_replace('\\', '/', rawurldecode($path)), '/');
        $publicBase = ltrim(rtrim(str_replace('\\', '/', \public_base_path()), '/'), '/');
        if ($publicBase !== '' && strpos($path, $publicBase . '/') === 0) {
            $path = substr($path, strlen($publicBase) + 1);
        }

        if (strpos($path, 'uploads/customer_service_payment/') !== 0) {
            return;
        }

        $absolutePath = $this->app->basePath('public/' . $path);
        if (!is_file($absolutePath)) {
            return;
        }

        $realBase = realpath($this->app->basePath('public/uploads/customer_service_payment'));
        $realFile = realpath($absolutePath);
        if (!is_string($realBase) || !is_string($realFile)) {
            return;
        }

        $realBase = rtrim(str_replace('\\', '/', $realBase), '/') . '/';
        $realFile = str_replace('\\', '/', $realFile);
        if (strpos($realFile, $realBase) !== 0) {
            return;
        }

        if (!@unlink($realFile)) {
            throw new RuntimeException('二维码图片文件删除失败，请检查上传目录权限。');
        }
    }

    protected function savePaymentQrImage(array $file, $type, $label)
    {
        $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode !== UPLOAD_ERR_OK) {
            throw new RuntimeException($this->uploadErrorMessage($errorCode));
        }

        $originalName = trim((string) ($file['name'] ?? ''));
        $tmpName = (string) ($file['tmp_name'] ?? '');
        $fileSize = (int) ($file['size'] ?? 0);

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException($label . '二维码上传文件无效，请重新选择。');
        }

        $mimeType = $this->detectMimeType($tmpName);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension === '') {
            $extension = $this->extensionByMime($mimeType, 'image');
        }

        $this->assertImageAttachment($tmpName, $mimeType, $extension, $fileSize);

        $subDirectory = date('Ym');
        $relativeDirectory = 'uploads/customer_service_payment/' . (string) $type . '/' . $subDirectory;
        $absoluteDirectory = $this->app->basePath('public/' . $relativeDirectory);
        if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0777, true) && !is_dir($absoluteDirectory)) {
            throw new RuntimeException('二维码上传目录创建失败，请检查目录权限。');
        }

        $storageName = date('YmdHis') . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
        $absolutePath = $absoluteDirectory . DIRECTORY_SEPARATOR . $storageName;
        if (!move_uploaded_file($tmpName, $absolutePath)) {
            throw new RuntimeException($label . '二维码保存失败，请重试。');
        }

        return \public_url($relativeDirectory . '/' . $storageName);
    }

    protected function assertImageAttachment($tmpName, $mimeType, $extension, $fileSize)
    {
        $allowedExtensions = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp');
        $allowedMimeTypes = array('image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/x-ms-bmp');

        if ($fileSize <= 0 || $fileSize > 5 * 1024 * 1024) {
            throw new RuntimeException('图片大小不能超过 5MB。');
        }

        $imageInfo = @getimagesize($tmpName);
        if (!is_array($imageInfo) || empty($imageInfo['mime'])) {
            throw new RuntimeException('上传文件不是有效图片。');
        }

        $imageMime = (string) ($imageInfo['mime'] ?? '');
        if (!in_array($mimeType, $allowedMimeTypes, true) && in_array($imageMime, $allowedMimeTypes, true)) {
            $mimeType = $imageMime;
        }

        if (!in_array($extension, $allowedExtensions, true) && in_array($mimeType, $allowedMimeTypes, true)) {
            $extension = $this->extensionByMime($mimeType, 'image');
        }

        if (!in_array($extension, $allowedExtensions, true) || !in_array($mimeType, $allowedMimeTypes, true)) {
            throw new RuntimeException('仅支持 jpg、jpeg、png、gif、webp、bmp 图片。');
        }
    }

    protected function assertVoiceAttachment($mimeType, $extension, $fileSize)
    {
        $allowedExtensions = array('webm', 'mp3', 'm4a', 'mp4', 'wav', 'ogg');
        $allowedMimeTypes = array('audio/webm', 'audio/mpeg', 'audio/mp4', 'audio/wav', 'audio/x-wav', 'audio/ogg', 'video/webm');

        if ($fileSize <= 0 || $fileSize > 8 * 1024 * 1024) {
            throw new RuntimeException('语音大小不能超过 8MB。');
        }

        if (!in_array($extension, $allowedExtensions, true) || !in_array($mimeType, $allowedMimeTypes, true)) {
            throw new RuntimeException('仅支持 webm、mp3、m4a、mp4、wav、ogg 语音。');
        }
    }

    protected function detectMimeType($path)
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mimeType = finfo_file($finfo, $path);
                finfo_close($finfo);
                if (is_string($mimeType) && $mimeType !== '') {
                    return $mimeType;
                }
            }
        }

        return 'application/octet-stream';
    }

    protected function extensionByMime($mimeType, $messageType)
    {
        $map = array(
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/bmp' => 'bmp',
            'image/x-ms-bmp' => 'bmp',
            'audio/webm' => 'webm',
            'video/webm' => 'webm',
            'audio/mpeg' => 'mp3',
            'audio/mp4' => 'm4a',
            'audio/wav' => 'wav',
            'audio/x-wav' => 'wav',
            'audio/ogg' => 'ogg',
        );

        if (isset($map[$mimeType])) {
            return $map[$mimeType];
        }

        return $messageType === 'image' ? 'jpg' : 'webm';
    }

    protected function normalizeDuration($value)
    {
        return max(0, min(600, (int) $value));
    }

    protected function normalizeStatusFilter($status)
    {
        $status = (string) $status;

        return in_array($status, array('all', 'waiting', 'open', 'unread', 'closed'), true) ? $status : 'all';
    }

    protected function blockNoticeMessage($blockedUntil)
    {
        $blockedUntilText = $this->blockUntilText($blockedUntil);
        if ($blockedUntilText === '永久') {
            return '您已被系统永久屏蔽，暂时无法继续发送在线客服消息。';
        }

        return '您已被系统屏蔽，解除时间:' . $blockedUntilText;
    }

    protected function normalizeBlockNoticeContent($content)
    {
        $content = trim((string) $content);
        if ($content === '您已被客服永久屏蔽，暂时无法继续发送在线客服消息。') {
            return '您已被系统永久屏蔽，暂时无法继续发送在线客服消息。';
        }

        if (preg_match('/^您已被客服屏蔽，屏蔽解除时间[：:]\\s*([0-9]{4}-[0-9]{2}-[0-9]{2}\\s+[0-9]{2}:[0-9]{2})(?::[0-9]{2})?[。.]?$/u', $content, $matches) === 1) {
            return '您已被系统屏蔽，解除时间:' . (string) $matches[1];
        }

        if (preg_match('/^您已被系统屏蔽，解除时间[：:]\\s*([0-9]{4}-[0-9]{2}-[0-9]{2}\\s+[0-9]{2}:[0-9]{2})(?::[0-9]{2})?[。.]?$/u', $content, $matches) === 1) {
            return '您已被系统屏蔽，解除时间:' . (string) $matches[1];
        }

        return $content;
    }

    protected function isBlockNoticeContent($content)
    {
        $content = trim((string) $content);

        return $this->normalizeBlockNoticeContent($content) !== $content
            || strpos($content, '您已被系统屏蔽，解除时间:') === 0
            || $content === '您已被系统永久屏蔽，暂时无法继续发送在线客服消息。';
    }

    protected function blockSendDeniedMessage($session)
    {
        $blockedUntil = is_array($session) ? (string) ($session['blocked_until'] ?? '') : '';
        $blockedUntilText = $this->blockUntilText($blockedUntil);
        if ($blockedUntilText === '永久') {
            return '当前会话已被永久屏蔽，暂时无法发送消息。';
        }

        return '当前会话已被屏蔽，屏蔽解除时间：' . $blockedUntilText . '。';
    }

    protected function blockUntilText($blockedUntil)
    {
        $blockedUntil = trim((string) $blockedUntil);
        if ($blockedUntil === '') {
            return '永久';
        }

        $timestamp = strtotime($blockedUntil);
        if ($timestamp === false) {
            return $blockedUntil;
        }

        return date('Y-m-d H:i', $timestamp);
    }

    protected function normalizeBlockUntil($blockLimit)
    {
        $blockLimit = strtolower(trim((string) $blockLimit));
        $secondsMap = array(
            '1h' => 3600,
            '24h' => 86400,
            '7d' => 604800,
            '30d' => 2592000,
        );

        if (!isset($secondsMap[$blockLimit])) {
            return null;
        }

        return date('Y-m-d H:i:s', time() + $secondsMap[$blockLimit]);
    }

    protected function sessionBlockActive($session)
    {
        if (!$session || !is_array($session) || empty($session['blocked_at'])) {
            return false;
        }

        $blockedUntil = trim((string) ($session['blocked_until'] ?? ''));
        if ($blockedUntil === '') {
            return true;
        }

        $timestamp = strtotime($blockedUntil);

        return $timestamp === false || $timestamp > time();
    }

    protected function refreshExpiredSessionBlock($session)
    {
        if (!$session || !is_array($session) || empty($session['blocked_at'])) {
            return $session;
        }

        $blockedUntil = trim((string) ($session['blocked_until'] ?? ''));
        $timestamp = $blockedUntil !== '' ? strtotime($blockedUntil) : false;
        if ($timestamp === false || $timestamp > time()) {
            return $session;
        }

        $this->releaseSessionBlock((int) ($session['id'] ?? 0));

        return $this->sessionById((int) ($session['id'] ?? 0));
    }

    protected function releaseSessionBlock($sessionId)
    {
        $sessionId = (int) $sessionId;
        if ($sessionId <= 0) {
            return;
        }

        $this->db()->execute(
            'UPDATE customer_service_sessions
             SET status = CASE WHEN status = :closed_status THEN :waiting_status ELSE status END,
                 blocked_at = NULL,
                 blocked_until = NULL,
                 blocked_by_agent_id = NULL,
                 agent_hidden_at = NULL,
                 updated_at = :updated_at
             WHERE id = :id',
            array(
                'closed_status' => 'closed',
                'waiting_status' => 'waiting',
                'updated_at' => $this->now(),
                'id' => $sessionId,
            )
        );
    }

    protected function releaseExpiredSessionBlocks()
    {
        $cacheKey = 'customer_service_release_expired_blocks_at';
        if ((int) $this->app->cache()->get($cacheKey, 0, 10) > 0) {
            return;
        }

        $this->db()->execute(
            'UPDATE customer_service_sessions
             SET status = CASE WHEN status = :closed_status THEN :waiting_status ELSE status END,
                 blocked_at = NULL,
                 blocked_until = NULL,
                 blocked_by_agent_id = NULL,
                 agent_hidden_at = NULL,
                 updated_at = :updated_at
             WHERE blocked_at IS NOT NULL
               AND blocked_until IS NOT NULL
               AND blocked_until <= :now_at',
            array(
                'closed_status' => 'closed',
                'waiting_status' => 'waiting',
                'updated_at' => $this->now(),
                'now_at' => $this->now(),
            )
        );
        $this->app->cache()->put($cacheKey, time());
    }

    protected function assertSuperAdmin(array $operator)
    {
        if ((int) ($operator['is_super'] ?? 0) !== 1) {
            throw new RuntimeException('只有超级管理员可以管理在线客服账号。');
        }
    }

    protected function canAgentWork(array $session, array $agent, $permission)
    {
        try {
            $this->assertAgentCanWork($session, $agent, $permission);
            return true;
        } catch (RuntimeException $exception) {
            return false;
        }
    }

    protected function assertAgentCanWork(array $session, array $agent, $permission)
    {
        if (!$this->agentHasPermission($agent, $permission)) {
            throw new RuntimeException('当前客服账号没有该操作权限。');
        }

        if ((string) ($session['status'] ?? '') === 'closed' && $permission !== 'clear' && !($permission === 'reply' && $this->sessionBlockActive($session))) {
            throw new RuntimeException('该会话已关闭。');
        }

        $assignedAgentId = (int) ($session['assigned_agent_id'] ?? 0);
        $agentId = (int) ($agent['id'] ?? 0);
        if ($assignedAgentId <= 0 || $assignedAgentId === $agentId) {
            return;
        }

        if ($this->agentHasPermission($agent, 'take')) {
            return;
        }

        throw new RuntimeException('该会话已由其他客服接待。');
    }

    protected function agentCanSeeSession(array $session, array $agent)
    {
        if (!empty($session['agent_hidden_at']) && empty($session['blocked_at'])) {
            return false;
        }

        $assignedAgentId = (int) ($session['assigned_agent_id'] ?? 0);
        $agentId = (int) ($agent['id'] ?? 0);

        return $assignedAgentId <= 0 || $assignedAgentId === $agentId || $this->agentHasPermission($agent, 'take');
    }

    protected function agentHasPermission(array $agent, $permission)
    {
        $permissions = $this->agentPermissions($agent);

        return !empty($permissions[(string) $permission]);
    }

    protected function agentPermissions(array $agent)
    {
        $json = (string) ($agent['permissions_json'] ?? '');
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return $this->defaultAgentPermissions();
        }

        $permissions = array();
        foreach ($this->permissionOptions() as $code => $label) {
            $permissions[$code] = !empty($decoded[$code]);
        }

        return $permissions;
    }

    protected function normalizeAgentPermissions(array $payload)
    {
        $selected = isset($payload['permissions']) && is_array($payload['permissions'])
            ? $payload['permissions']
            : array();
        $permissions = array();

        foreach ($this->permissionOptions() as $code => $label) {
            $permissions[$code] = in_array($code, $selected, true)
                || (string) ($payload['permission_' . $code] ?? '') === '1';
        }

        return $permissions;
    }

    protected function defaultAgentPermissions()
    {
        return array(
            'reply' => true,
            'close' => true,
            'clear' => true,
            'take' => true,
        );
    }

    protected function permissionOptions()
    {
        return array(
            'reply' => '回复消息',
            'take' => '接待新会话',
            'close' => '关闭会话',
            'clear' => '删除本账号记录',
        );
    }

    protected function overview(array $agent = null)
    {
        $where = array();
        $params = array();

        if ($agent !== null && !$this->agentHasPermission($agent, 'take')) {
            $where[] = '(assigned_agent_id IS NULL OR assigned_agent_id = :agent_id)';
            $params['agent_id'] = (int) ($agent['id'] ?? 0);
        }

        $sql = "SELECT
                    COUNT(*) AS total_sessions,
                    COALESCE(SUM(CASE WHEN unread_for_admin > 0 THEN 1 ELSE 0 END), 0) AS unread_sessions,
                    COALESCE(SUM(unread_for_admin), 0) AS unread_messages,
                    COALESCE(SUM(CASE WHEN status = 'waiting' THEN 1 ELSE 0 END), 0) AS waiting_sessions,
                    COALESCE(SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END), 0) AS open_sessions,
                    COALESCE(SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END), 0) AS closed_sessions
                FROM customer_service_sessions";

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sessionRow = $this->db()->fetch($sql, $params);
        $agentRow = $this->overviewAgentCounts();

        return array(
            'total_sessions' => max(0, (int) ($sessionRow['total_sessions'] ?? 0)),
            'unread_sessions' => max(0, (int) ($sessionRow['unread_sessions'] ?? 0)),
            'unread_messages' => max(0, (int) ($sessionRow['unread_messages'] ?? 0)),
            'waiting_sessions' => max(0, (int) ($sessionRow['waiting_sessions'] ?? 0)),
            'open_sessions' => max(0, (int) ($sessionRow['open_sessions'] ?? 0)),
            'closed_sessions' => max(0, (int) ($sessionRow['closed_sessions'] ?? 0)),
            'total_agents' => max(0, (int) ($agentRow['total_agents'] ?? 0)),
            'online_agents' => max(0, (int) ($agentRow['online_agents'] ?? 0)),
        );
    }

    protected function overviewAgentCounts()
    {
        if ($this->agentsRequestCache !== null) {
            $totalAgents = 0;
            $onlineAgents = 0;
            foreach ($this->agentsRequestCache as $agent) {
                $totalAgents++;
                if ((string) ($agent['status'] ?? '') === 'online') {
                    $onlineAgents++;
                }
            }

            return array(
                'total_agents' => $totalAgents,
                'online_agents' => $onlineAgents,
            );
        }

        return $this->db()->fetch(
            "SELECT
                COUNT(*) AS total_agents,
                COALESCE(SUM(CASE WHEN status = 'online' THEN 1 ELSE 0 END), 0) AS online_agents
             FROM customer_service_accounts
             WHERE deleted_at IS NULL"
        );
    }

    protected function formatSessions(array $rows)
    {
        $items = array();
        foreach ($rows as $row) {
            $items[] = $this->formatSession($row);
        }

        return $items;
    }

    protected function sessionInRows($sessionId, array $rows)
    {
        return $this->sessionRowFromRows($sessionId, $rows) !== null;
    }

    protected function sessionRowFromRows($sessionId, array $rows)
    {
        $sessionId = (int) $sessionId;
        if ($sessionId <= 0) {
            return null;
        }

        foreach ($rows as $row) {
            if ((int) ($row['id'] ?? 0) === $sessionId) {
                return $row;
            }
        }

        return null;
    }

    protected function formatSession($row)
    {
        if (!$row) {
            return null;
        }

        $status = (string) ($row['status'] ?? 'waiting');
        $memberOnline = $this->memberOnlineFromRow($row);
        $blocked = $this->sessionBlockActive($row);
        $blockedUntil = trim((string) ($row['blocked_until'] ?? ''));

        return array(
            'id' => (int) ($row['id'] ?? 0),
            'user_id' => (int) ($row['user_id'] ?? 0),
            'username' => (string) ($row['username'] ?? ''),
            'score' => (int) ($row['member_score'] ?? 0),
            'member_online' => $memberOnline,
            'member_online_label' => $memberOnline ? '在线' : '离线',
            'member_online_type' => $memberOnline ? 'online' : 'offline',
            'assigned_agent_id' => (int) ($row['assigned_agent_id'] ?? 0),
            'assigned_agent_name' => $this->agentName($row),
            'status' => $status,
            'status_label' => $blocked ? '已屏蔽' : $this->statusLabel($status),
            'member_typing_at' => (string) ($row['member_typing_at'] ?? ''),
            'agent_typing_at' => (string) ($row['agent_typing_at'] ?? ''),
            'unread_for_member' => (int) ($row['unread_for_member'] ?? 0),
            'unread_for_admin' => (int) ($row['unread_for_admin'] ?? 0),
            'last_message_type' => (string) ($row['last_message_type'] ?? 'text'),
            'last_message_preview' => (string) ($row['last_message_preview'] ?? ''),
            'agent_hidden_at' => isset($row['agent_hidden_at']) ? \format_datetime($row['agent_hidden_at']) : '',
            'blocked_at' => isset($row['blocked_at']) ? \format_datetime($row['blocked_at']) : '',
            'blocked_until' => $blockedUntil !== '' ? \format_datetime($blockedUntil) : '',
            'block_label' => $blocked ? ($blockedUntil !== '' ? '限时屏蔽' : '永久屏蔽') : '',
            'blocked' => $blocked,
            'last_message_at' => isset($row['agent_latest_message_at']) && $row['agent_latest_message_at']
                ? \format_datetime($row['agent_latest_message_at'])
                : (isset($row['last_message_at']) ? \format_datetime($row['last_message_at']) : ''),
            'closed_at' => isset($row['closed_at']) ? \format_datetime($row['closed_at']) : '',
            'created_at' => isset($row['created_at']) ? \format_datetime($row['created_at']) : '',
            'updated_at' => isset($row['updated_at']) ? \format_datetime($row['updated_at']) : '',
        );
    }

    protected function formatMessages(array $rows, $viewer = 'member')
    {
        $messages = array();
        $inviteRewardNotifications = array();
        foreach ($rows as $row) {
            $createdAt = (string) ($row['created_at'] ?? '');
            $timestamp = $createdAt !== '' ? strtotime($createdAt) : false;
            $senderType = (string) ($row['sender_type'] ?? 'member');
            $content = (string) ($row['content'] ?? '');
            if (in_array($senderType, array('agent', 'system'), true)) {
                $content = $this->normalizeBlockNoticeContent($content);
            }
            $content = $this->normalizeScoreNoticeContentForViewer($content, (string) $viewer);
            if ($senderType !== 'member' && $this->isBlockNoticeContent($content)) {
                $senderType = 'system';
            }
            if ($senderType === 'system' && $this->isInviteRewardNotificationContent($content)) {
                if (isset($inviteRewardNotifications[$content])) {
                    continue;
                }

                $inviteRewardNotifications[$content] = true;
            }
            $messages[] = array(
                'id' => (int) ($row['id'] ?? 0),
                'sender_type' => $senderType,
                'sender_name' => $this->messageSenderName($row),
                'message_type' => (string) ($row['message_type'] ?? 'text'),
                'content' => $content,
                'attachment_url' => (string) ($row['attachment_url'] ?? ''),
                'attachment_name' => (string) ($row['attachment_name'] ?? ''),
                'attachment_mime' => (string) ($row['attachment_mime'] ?? ''),
                'attachment_size' => (int) ($row['attachment_size'] ?? 0),
                'voice_duration' => (int) ($row['voice_duration'] ?? 0),
                'created_at' => $timestamp ? date('Y-m-d H:i', $timestamp) : '',
                'created_date' => $timestamp ? date('Y-m-d', $timestamp) : '',
                'created_time' => $timestamp ? date('H:i', $timestamp) : '',
            );
        }

        return $messages;
    }

    protected function normalizeScoreNoticeContentForViewer($content, $viewer)
    {
        $content = (string) $content;
        if (!in_array((string) $viewer, array('agent', 'admin'), true)) {
            return $content;
        }

        if (preg_match('/^您的账户充值成功，充值积分\s*(\d+)，当前剩余积分\s*(\d+)。$/u', $content, $matches)) {
            return '已对该用户充值了 ' . (int) $matches[1] . ' 积分，当前剩余积分 ' . (int) $matches[2] . '。';
        }

        if (preg_match('/^您的账户扣减成功，扣减积分\s*(\d+)，当前剩余积分\s*(\d+)。$/u', $content, $matches)) {
            return '已对该用户扣减了 ' . (int) $matches[1] . ' 积分，当前剩余积分 ' . (int) $matches[2] . '。';
        }

        return $content;
    }

    protected function inviteRewardNotificationExists($sessionId, $content)
    {
        $row = $this->db()->fetch(
            'SELECT id
             FROM customer_service_messages
             WHERE session_id = :session_id
               AND sender_type = :sender_type
               AND message_type = :message_type
               AND content = :content
             ORDER BY id DESC
             LIMIT 1',
            array(
                'session_id' => (int) $sessionId,
                'sender_type' => 'system',
                'message_type' => 'text',
                'content' => (string) $content,
            )
        );

        return (bool) $row;
    }

    protected function isInviteRewardNotificationContent($content)
    {
        return preg_match('/^您的邀请好友\s*(?:「[^」]+」|【[^】]+】|\[[^\]]+\]|.+?)\s*已注册成功，邀请奖励\s*\+[0-9]+\s*积分已到账。$/u', (string) $content) === 1;
    }

    protected function formatAgent(array $agent, $publicSafe)
    {
        $permissions = $this->agentPermissions($agent);
        $permissionLabels = array();
        foreach ($this->permissionOptions() as $code => $label) {
            if (!empty($permissions[$code])) {
                $permissionLabels[] = $label;
            }
        }

        $item = array(
            'id' => (int) ($agent['id'] ?? 0),
            'username' => (string) ($agent['username'] ?? ''),
            'display_name' => (string) ($agent['display_name'] ?? ''),
            'nickname_options' => $this->agentNicknameOptions($agent),
            'welcome_text' => (string) ($agent['welcome_text'] ?? ''),
            'service_hours' => $this->normalizeServiceHours((string) ($agent['service_hours'] ?? '')),
            'auto_reply_text' => (string) ($agent['auto_reply_text'] ?? ''),
            'activity_notice' => (string) ($agent['activity_notice'] ?? ''),
            'activity_notice_enabled' => (int) ($agent['activity_notice_enabled'] ?? (!empty($agent['activity_notice']) ? 1 : 0)) === 1,
            'auto_reply_enabled' => (int) ($agent['auto_reply_enabled'] ?? 0) === 1,
            'status' => (string) ($agent['status'] ?? 'online'),
            'status_label' => (string) ($agent['status'] ?? 'online') === 'online' ? '启用' : '停用',
            'permissions' => $permissions,
            'permission_labels' => $permissionLabels,
            'permission_text' => $permissionLabels ? implode('、', $permissionLabels) : '仅查看',
            'sort_order' => (int) ($agent['sort_order'] ?? 50),
            'last_login_at' => isset($agent['last_login_at']) ? \format_datetime($agent['last_login_at']) : '-',
            'last_login_ip' => (string) ($agent['last_login_ip'] ?? ''),
            'created_at' => isset($agent['created_at']) ? \format_datetime($agent['created_at']) : '-',
            'updated_at' => isset($agent['updated_at']) ? \format_datetime($agent['updated_at']) : '-',
        );

        if (!$publicSafe) {
            return $item;
        }

        return $item;
    }

    protected function memberServiceProfile($session)
    {
        $agent = null;
        if ($session && is_array($session) && (int) ($session['assigned_agent_id'] ?? 0) > 0) {
            $agent = $this->agentById((int) $session['assigned_agent_id']);
        }

        if (!$agent) {
            $agent = $this->preferredServiceAgent(false);
        }

        return $this->serviceProfileFromAgent($agent);
    }

    protected function serviceProfileFromAgent($agent)
    {
        $agent = is_array($agent) ? $agent : array();
        $displayName = trim((string) ($agent['display_name'] ?? ''));
        $welcomeText = trim((string) ($agent['welcome_text'] ?? ''));
        $activityNotice = trim((string) ($agent['activity_notice'] ?? ''));
        $activityNoticeEnabled = (int) ($agent['activity_notice_enabled'] ?? ($activityNotice !== '' ? 1 : 0)) === 1;

        return array(
            'display_name' => $displayName !== '' ? $displayName : '在线客服',
            'service_hours' => $this->normalizeServiceHours((string) ($agent['service_hours'] ?? '')),
            'welcome_text' => $welcomeText !== '' ? $welcomeText : $this->defaultWelcomeText(),
            'activity_notice' => $activityNoticeEnabled ? $activityNotice : '',
            'activity_notice_enabled' => $activityNoticeEnabled,
            'auto_reply_enabled' => (int) ($agent['auto_reply_enabled'] ?? 0) === 1,
        );
    }

    protected function preferredServiceAgent($autoReplyOnly)
    {
        $sql = 'SELECT *
             FROM customer_service_accounts
             WHERE deleted_at IS NULL AND status = :status';
        $sql .= ' ORDER BY sort_order ASC, id ASC';

        $agents = $this->db()->fetchAll($sql, array('status' => 'online'));
        $liveAgentIds = $this->liveAgentIds();
        if ($autoReplyOnly) {
            foreach ($agents as $agent) {
                $agentId = (int) ($agent['id'] ?? 0);
                if ((int) ($agent['auto_reply_enabled'] ?? 0) !== 1
                    || trim((string) ($agent['auto_reply_text'] ?? '')) === ''
                    || isset($liveAgentIds[$agentId])) {
                    continue;
                }

                return $agent;
            }

            return null;
        }

        foreach ($agents as $agent) {
            if (isset($liveAgentIds[(int) ($agent['id'] ?? 0)])) {
                return $agent;
            }
        }

        foreach ($agents as $agent) {
            return $agent;
        }

        return null;
    }

    protected function autoReplyForSession(array $session)
    {
        $sessionId = (int) ($session['id'] ?? 0);
        if ($sessionId <= 0) {
            return null;
        }

        $agent = null;
        $assignedAgentId = (int) ($session['assigned_agent_id'] ?? 0);
        if ($assignedAgentId > 0) {
            $agent = $this->agentById($assignedAgentId);
        } else {
            $agent = $this->preferredServiceAgent(true);
        }

        if (!$agent || (string) ($agent['status'] ?? '') !== 'online' || $this->agentIsServing($agent)) {
            return null;
        }

        if ((int) ($agent['auto_reply_enabled'] ?? 0) !== 1) {
            return null;
        }

        $content = $this->limitText((string) ($agent['auto_reply_text'] ?? ''), 1000);
        if ($content === '') {
            return null;
        }

        $agentId = (int) ($agent['id'] ?? 0);
        if ($agentId <= 0 || $this->recentAutoReplyExists($sessionId, $agentId, $content)) {
            return null;
        }

        return array(
            'agent_id' => $agentId,
            'message' => $this->textMessage($content),
        );
    }

    protected function recentAutoReplyExists($sessionId, $agentId, $content)
    {
        $row = $this->db()->fetch(
            'SELECT id
             FROM customer_service_messages
             WHERE session_id = :session_id
               AND sender_type = :sender_type
               AND sender_agent_id = :sender_agent_id
               AND message_type = :message_type
               AND content = :content
               AND created_at >= :created_after
             ORDER BY id DESC
             LIMIT 1',
            array(
                'session_id' => (int) $sessionId,
                'sender_type' => 'agent',
                'sender_agent_id' => (int) $agentId,
                'message_type' => 'text',
                'content' => (string) $content,
                'created_after' => date('Y-m-d H:i:s', time() - 120),
            )
        );

        return (bool) $row;
    }

    protected function textMessage($content)
    {
        return array(
            'type' => 'text',
            'content' => (string) $content,
            'attachment_url' => '',
            'attachment_name' => '',
            'attachment_mime' => '',
            'attachment_size' => 0,
            'voice_duration' => 0,
        );
    }

    protected function agentNicknameHistoryKey($agentId)
    {
        return 'customer_service_agent.nickname_history.' . (string) ((int) $agentId);
    }

    protected function agentNicknameDeletedKey($agentId)
    {
        return 'customer_service_agent.nickname_deleted.' . (string) ((int) $agentId);
    }

    protected function agentDisplayNameForHistory(array $agent)
    {
        $name = trim((string) ($agent['display_name'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($agent['username'] ?? ''));
        }

        return $name !== '' ? $this->limitText($name, 80) : '客服';
    }

    protected function readAgentNicknameHistory($agentId)
    {
        $agentId = (int) $agentId;
        if ($agentId <= 0) {
            return array();
        }

        $raw = (string) $this->app->settings()->get($this->agentNicknameHistoryKey($agentId), '');
        $items = $raw !== '' ? json_decode($raw, true) : array();
        if (!is_array($items)) {
            $items = array();
        }

        $history = array();
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $name = $this->limitText((string) ($item['name'] ?? ''), 80);
            $effectiveAt = trim((string) ($item['effective_at'] ?? ''));
            if ($name === '' || $effectiveAt === '' || strtotime($effectiveAt) === false) {
                continue;
            }

            $history[] = array(
                'name' => $name,
                'effective_at' => $effectiveAt,
            );
        }

        usort($history, function ($left, $right) {
            $leftTime = strtotime((string) ($left['effective_at'] ?? '')) ?: 0;
            $rightTime = strtotime((string) ($right['effective_at'] ?? '')) ?: 0;
            if ($leftTime === $rightTime) {
                return 0;
            }

            return $leftTime < $rightTime ? -1 : 1;
        });

        return $history;
    }

    protected function writeAgentNicknameHistory($agentId, array $history)
    {
        $agentId = (int) $agentId;
        if ($agentId <= 0) {
            return;
        }

        $normalized = array();
        foreach ($history as $item) {
            if (!is_array($item)) {
                continue;
            }

            $name = $this->limitText((string) ($item['name'] ?? ''), 80);
            $effectiveAt = trim((string) ($item['effective_at'] ?? ''));
            if ($name === '' || $effectiveAt === '' || strtotime($effectiveAt) === false) {
                continue;
            }

            $normalized[] = array(
                'name' => $name,
                'effective_at' => $effectiveAt,
            );
        }

        $this->app->settings()->setMany(
            'customer_service_agent_nickname',
            array(
                $this->agentNicknameHistoryKey($agentId) => json_encode($normalized, JSON_UNESCAPED_UNICODE),
            )
        );
    }

    protected function readAgentNicknameDeleted($agentId)
    {
        $agentId = (int) $agentId;
        if ($agentId <= 0) {
            return array();
        }

        $raw = (string) $this->app->settings()->get($this->agentNicknameDeletedKey($agentId), '');
        $items = $raw !== '' ? json_decode($raw, true) : array();
        if (!is_array($items)) {
            $items = array();
        }

        $deleted = array();
        foreach ($items as $item) {
            $name = $this->limitText((string) $item, 80);
            if ($name !== '') {
                $deleted[$name] = true;
            }
        }

        return $deleted;
    }

    protected function writeAgentNicknameDeleted($agentId, array $deleted)
    {
        $agentId = (int) $agentId;
        if ($agentId <= 0) {
            return;
        }

        $names = array();
        foreach ($deleted as $key => $value) {
            $name = is_string($key) && $value === true ? $key : (string) $value;
            $name = $this->limitText($name, 80);
            if ($name !== '' && !isset($names[$name])) {
                $names[$name] = $name;
            }
        }

        $this->app->settings()->setMany(
            'customer_service_agent_nickname',
            array(
                $this->agentNicknameDeletedKey($agentId) => json_encode(array_values($names), JSON_UNESCAPED_UNICODE),
            )
        );
    }

    protected function restoreAgentNicknameOption($agentId, $name)
    {
        $name = $this->limitText((string) $name, 80);
        if ($name === '') {
            return;
        }

        $deleted = $this->readAgentNicknameDeleted($agentId);
        if (!isset($deleted[$name])) {
            return;
        }

        unset($deleted[$name]);
        $this->writeAgentNicknameDeleted($agentId, $deleted);
    }

    protected function rememberAgentNicknameChange(array $agent, $nextName, $effectiveAt)
    {
        $agentId = (int) ($agent['id'] ?? 0);
        if ($agentId <= 0) {
            return;
        }

        $currentName = $this->agentDisplayNameForHistory($agent);
        $nextName = $this->limitText((string) $nextName, 80);
        if ($nextName === '') {
            $nextName = $currentName;
        }

        $history = $this->readAgentNicknameHistory($agentId);
        if (!$history) {
            $history[] = array(
                'name' => $currentName,
                'effective_at' => '1970-01-01 00:00:00',
            );
        }

        $last = end($history);
        $lastName = is_array($last) ? (string) ($last['name'] ?? '') : '';
        if ($nextName !== '' && $nextName !== $lastName) {
            $history[] = array(
                'name' => $nextName,
                'effective_at' => (string) $effectiveAt,
            );
        }

        $this->writeAgentNicknameHistory($agentId, $history);
        if ($nextName !== '' && $nextName !== $currentName) {
            $this->restoreAgentNicknameOption($agentId, $nextName);
        }
    }

    protected function agentNicknameAtMessageTime($agentId, $createdAt, $fallbackName)
    {
        $agentId = (int) $agentId;
        $fallbackName = $this->limitText((string) $fallbackName, 80);
        if ($agentId <= 0) {
            return $fallbackName;
        }

        $history = $this->readAgentNicknameHistory($agentId);
        if (!$history) {
            return $fallbackName;
        }

        $messageTime = strtotime((string) $createdAt);
        if ($messageTime === false) {
            $messageTime = time();
        }

        $matchedName = '';
        foreach ($history as $item) {
            $effectiveTime = strtotime((string) ($item['effective_at'] ?? ''));
            if ($effectiveTime === false) {
                continue;
            }

            if ($effectiveTime <= $messageTime) {
                $matchedName = (string) ($item['name'] ?? '');
            }
        }

        return $matchedName !== '' ? $matchedName : $fallbackName;
    }

    protected function agentNicknameOptions(array $agent)
    {
        $agentId = (int) ($agent['id'] ?? 0);
        $options = array();
        $seen = array();
        $deleted = $this->readAgentNicknameDeleted($agentId);
        $addOption = function ($name) use (&$options, &$seen, $deleted) {
            $name = $this->limitText((string) $name, 80);
            if ($name === '' || isset($seen[$name]) || isset($deleted[$name])) {
                return;
            }

            $seen[$name] = true;
            $options[] = $name;
        };

        $addOption($this->agentDisplayNameForHistory($agent));
        $addOption((string) ($agent['username'] ?? ''));

        $history = array_reverse($this->readAgentNicknameHistory($agentId));
        foreach ($history as $item) {
            $addOption((string) ($item['name'] ?? ''));
        }

        return $options;
    }

    protected function messageSenderName(array $row)
    {
        $senderType = (string) ($row['sender_type'] ?? '');
        if ($senderType === 'system'
            || ($senderType === 'agent' && $this->isBlockNoticeContent((string) ($row['content'] ?? '')))) {
            return '系统';
        }

        if ($senderType === 'agent') {
            $name = trim((string) ($row['agent_name'] ?? ''));
            $historyName = $this->agentNicknameAtMessageTime(
                (int) ($row['sender_agent_id'] ?? 0),
                (string) ($row['created_at'] ?? ''),
                $name
            );
            if ($historyName !== '') {
                return $historyName;
            }

            return $name !== '' ? $name : '客服';
        }

        $name = trim((string) ($row['user_name'] ?? ''));

        return $name !== '' ? $name : '会员';
    }

    protected function agentName(array $row)
    {
        $name = trim((string) ($row['assigned_agent_name'] ?? ''));

        return $name !== '' ? $name : '未接待';
    }

    protected function statusLabel($status)
    {
        switch ((string) $status) {
            case 'blocked':
                return '已屏蔽';
            case 'open':
                return '会话中';
            case 'closed':
                return '已关闭';
            case 'waiting':
            default:
                return '待接待';
        }
    }

    protected function typingStatus($session, $viewer)
    {
        $viewer = (string) $viewer === 'member' ? 'member' : 'agent';

        if (!$session || !is_array($session)) {
            $isOnline = $this->hasOnlineAgents();
            return array(
                'is_typing' => false,
                'status_type' => 'serving',
                'text' => $isOnline ? '客服在线，可直接发送消息。' : '客服暂未在线，请留言等待回复。',
                'avatar_label' => $isOnline ? '在线中···' : '休息中···',
                'avatar_status_type' => $isOnline ? 'online' : 'offline',
            );
        }

        $peerSide = $viewer === 'agent' ? 'member' : 'agent';
        $typingAt = $viewer === 'agent'
            ? (string) ($session['member_typing_at'] ?? '')
            : (string) ($session['agent_typing_at'] ?? '');
        $liveStatusType = $this->activeSessionLiveStatus((int) ($session['id'] ?? 0), $peerSide, $typingAt);
        $isTyping = $liveStatusType !== '';
        $isOnline = $this->sessionAgentOnline($session);

        return array(
            'is_typing' => $isTyping,
            'status_type' => $isTyping ? $liveStatusType : 'serving',
            'text' => $isTyping ? $this->liveStatusText($liveStatusType, $viewer) : ($isOnline ? '客服在线，可直接发送消息。' : '客服暂未在线，请留言等待回复。'),
            'avatar_label' => $isTyping ? $this->liveStatusAvatarLabel($liveStatusType) : ($isOnline ? '在线中···' : '休息中···'),
            'avatar_status_type' => $isTyping ? $liveStatusType : ($isOnline ? 'online' : 'offline'),
        );
    }

    protected function sessionAgentOnline(array $session)
    {
        $assignedAgentId = (int) ($session['assigned_agent_id'] ?? 0);
        if ($assignedAgentId > 0) {
            return $this->isAgentLive($assignedAgentId);
        }

        return $this->hasOnlineAgents();
    }

    protected function memberOnline($userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return false;
        }

        $row = $this->db()->fetch(
            'SELECT MAX(created_at) AS last_seen_at
             FROM page_views
             WHERE user_id = :user_id',
            array('user_id' => $userId)
        );

        $lastSeenAt = (string) ($row['last_seen_at'] ?? '');
        if ($lastSeenAt === '') {
            return false;
        }

        $timestamp = strtotime($lastSeenAt);
        if ($timestamp === false) {
            return false;
        }

        return $timestamp >= time() - 300;
    }

    protected function memberOnlineFromRow(array $row)
    {
        $presenceStatus = $this->memberPresenceStatus((int) ($row['id'] ?? 0));
        if ($presenceStatus !== null) {
            return $presenceStatus;
        }

        if (array_key_exists('member_last_seen_at', $row)) {
            $lastSeenAt = (string) ($row['member_last_seen_at'] ?? '');
            $timestamp = strtotime($lastSeenAt);
            if ($timestamp !== false && $timestamp >= time() - 300) {
                return true;
            }
        }

        if (array_key_exists('member_last_seen_at', $row)) {
            return false;
        }

        return $this->memberOnline((int) ($row['user_id'] ?? 0));
    }

    protected function hasOnlineAgents()
    {
        return $this->hasLiveAgents();
    }

    protected function agentInServiceHours(array $agent)
    {
        if ((string) ($agent['status'] ?? 'online') !== 'online') {
            return false;
        }

        return $this->serviceHoursContainNow((string) ($agent['service_hours'] ?? ''));
    }

    protected function agentIsServing(array $agent)
    {
        $agentId = (int) ($agent['id'] ?? 0);
        if ($agentId <= 0) {
            return false;
        }

        if ((string) ($agent['status'] ?? 'online') !== 'online') {
            return false;
        }

        if ((int) Session::get('customer_service_agent_id', 0) === $agentId) {
            $sessionServing = Session::get('customer_service_agent_serving', null);
            if ($sessionServing !== null) {
                return (string) $sessionServing === '1';
            }
        }

        return $this->isAgentLive($agentId);
    }

    protected function agentServingState(array $agent)
    {
        $isServing = $this->agentIsServing($agent);

        return array(
            'agent_online' => $isServing,
            'agent_online_label' => $isServing ? '在线中···' : '休息中···',
            'agent_online_type' => $isServing ? 'online' : 'offline',
        );
    }

    protected function isTypingActive($typingAt)
    {
        $typingAt = trim((string) $typingAt);
        if ($typingAt === '') {
            return false;
        }

        $timestamp = strtotime($typingAt);
        if ($timestamp === false) {
            return false;
        }

        return $timestamp >= time() - 8;
    }

    protected function normalizeLiveStatusType($statusType)
    {
        $statusType = strtolower(trim((string) $statusType));

        return in_array($statusType, array('typing', 'image', 'voice'), true) ? $statusType : 'typing';
    }

    protected function liveStatusText($statusType, $viewer)
    {
        $prefix = (string) $viewer === 'agent' ? '会员' : '客服';

        switch ($this->normalizeLiveStatusType($statusType)) {
            case 'image':
                return $prefix . '正在发送图片...';
            case 'voice':
                return $prefix . '正在发送语音...';
            case 'typing':
            default:
                return $prefix . '正在输入...';
        }
    }

    protected function liveStatusAvatarLabel($statusType)
    {
        switch ($this->normalizeLiveStatusType($statusType)) {
            case 'image':
                return '发图中';
            case 'voice':
                return '语音中';
            case 'typing':
            default:
                return '输入中';
        }
    }

    protected function liveStatusPath()
    {
        return $this->app->basePath('storage/cache/customer_service_live_status.json');
    }

    protected function readLiveStatuses()
    {
        $path = $this->liveStatusPath();
        if (!is_file($path)) {
            return array();
        }

        $json = file_get_contents($path);
        if ($json === false || trim($json) === '') {
            return array();
        }

        $data = json_decode($json, true);

        return is_array($data) ? $data : array();
    }

    protected function writeLiveStatuses(array $statuses)
    {
        $path = $this->liveStatusPath();
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($path, json_encode($statuses, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    protected function pruneLiveStatuses(array $statuses)
    {
        $activeAfter = time() - 10;

        foreach ($statuses as $sessionId => $sides) {
            if (!is_array($sides)) {
                unset($statuses[$sessionId]);
                continue;
            }

            foreach ($sides as $side => $status) {
                $updatedAt = is_array($status) ? (int) ($status['updated_at'] ?? 0) : 0;
                if ($updatedAt < $activeAfter) {
                    unset($statuses[$sessionId][$side]);
                }
            }

            if (empty($statuses[$sessionId])) {
                unset($statuses[$sessionId]);
            }
        }

        return $statuses;
    }

    protected function setSessionLiveStatus($sessionId, $side, $statusType)
    {
        $sessionId = (int) $sessionId;
        $side = (string) $side === 'agent' ? 'agent' : 'member';
        if ($sessionId <= 0) {
            return;
        }

        $statuses = $this->pruneLiveStatuses($this->readLiveStatuses());
        $sessionKey = (string) $sessionId;
        $statusType = trim((string) $statusType);

        if ($statusType === '') {
            if (isset($statuses[$sessionKey][$side])) {
                unset($statuses[$sessionKey][$side]);
            }
            if (isset($statuses[$sessionKey]) && empty($statuses[$sessionKey])) {
                unset($statuses[$sessionKey]);
            }
        } else {
            if (!isset($statuses[$sessionKey]) || !is_array($statuses[$sessionKey])) {
                $statuses[$sessionKey] = array();
            }
            $statuses[$sessionKey][$side] = array(
                'status_type' => $this->normalizeLiveStatusType($statusType),
                'updated_at' => time(),
            );
        }

        $this->writeLiveStatuses($statuses);
    }

    protected function activeSessionLiveStatus($sessionId, $side, $fallbackTypingAt = '')
    {
        $sessionId = (int) $sessionId;
        $side = (string) $side === 'agent' ? 'agent' : 'member';
        $statuses = $this->readLiveStatuses();
        $sessionKey = (string) $sessionId;
        $status = isset($statuses[$sessionKey][$side]) && is_array($statuses[$sessionKey][$side])
            ? $statuses[$sessionKey][$side]
            : array();
        $updatedAt = (int) ($status['updated_at'] ?? 0);

        if ($updatedAt >= time() - 8) {
            return $this->normalizeLiveStatusType((string) ($status['status_type'] ?? 'typing'));
        }

        return $this->isTypingActive($fallbackTypingAt) ? 'typing' : '';
    }

    protected function presencePath()
    {
        return $this->app->basePath('storage/cache/customer_service_presence.json');
    }

    protected function readPresence()
    {
        $path = $this->presencePath();
        if (!is_file($path)) {
            return array(
                'members' => array(),
                'agents' => array(),
            );
        }

        $json = file_get_contents($path);
        $data = $json !== false && trim($json) !== '' ? json_decode($json, true) : array();
        if (!is_array($data)) {
            $data = array();
        }

        return array(
            'members' => isset($data['members']) && is_array($data['members']) ? $data['members'] : array(),
            'agents' => isset($data['agents']) && is_array($data['agents']) ? $data['agents'] : array(),
        );
    }

    protected function writePresence(array $presence)
    {
        $path = $this->presencePath();
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($path, json_encode($presence, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    protected function prunePresence(array $presence)
    {
        $activeAfter = time() - 86400;

        foreach (array('members', 'agents') as $group) {
            if (!isset($presence[$group]) || !is_array($presence[$group])) {
                $presence[$group] = array();
                continue;
            }

            foreach ($presence[$group] as $id => $item) {
                $updatedAt = is_array($item) ? (int) ($item['updated_at'] ?? 0) : 0;
                if ($updatedAt < $activeAfter) {
                    unset($presence[$group][$id]);
                }
            }
        }

        return $presence;
    }

    protected function touchMemberPresence($session)
    {
        if (!$session || !is_array($session)) {
            return;
        }

        $sessionId = (int) ($session['id'] ?? 0);
        if ($sessionId <= 0) {
            return;
        }

        $touchCacheKey = 'customer_service_member_presence_touch_' . $sessionId;
        if ((int) $this->app->cache()->get($touchCacheKey, 0, 5) > 0) {
            return;
        }

        $presence = $this->prunePresence($this->readPresence());
        $presence['members'][(string) $sessionId] = array(
            'user_id' => (int) ($session['user_id'] ?? 0),
            'updated_at' => time(),
        );
        $this->writePresence($presence);
        $this->app->cache()->put($touchCacheKey, time());
    }

    protected function touchAgentPresence(array $agent, $serving = true)
    {
        $agentId = (int) ($agent['id'] ?? 0);
        if ($agentId <= 0) {
            return;
        }

        $touchCacheKey = 'customer_service_agent_presence_touch_' . $agentId . '_' . ($serving ? '1' : '0');
        if ((int) $this->app->cache()->get($touchCacheKey, 0, 5) > 0) {
            return;
        }

        $presence = $this->prunePresence($this->readPresence());
        $presence['agents'][(string) $agentId] = array(
            'serving' => $serving ? 1 : 0,
            'updated_at' => time(),
        );
        $this->writePresence($presence);
        $this->app->cache()->put($touchCacheKey, time());
    }

    protected function clearAgentPresence($agentId)
    {
        $agentId = (int) $agentId;
        if ($agentId <= 0) {
            return;
        }

        $presence = $this->readPresence();
        unset($presence['agents'][(string) $agentId]);
        $this->writePresence($presence);
    }

    protected function memberPresenceStatus($sessionId)
    {
        $sessionId = (int) $sessionId;
        if ($sessionId <= 0) {
            return null;
        }

        $presence = $this->readPresence();
        if (!isset($presence['members'][(string) $sessionId]) || !is_array($presence['members'][(string) $sessionId])) {
            return null;
        }

        return (int) ($presence['members'][(string) $sessionId]['updated_at'] ?? 0) >= time() - 12;
    }

    protected function isAgentLive($agentId)
    {
        $agentId = (int) $agentId;
        if ($agentId <= 0) {
            return false;
        }

        $presence = $this->readPresence();
        $hasPresence = isset($presence['agents'][(string) $agentId])
            && is_array($presence['agents'][(string) $agentId])
            && (int) ($presence['agents'][(string) $agentId]['updated_at'] ?? 0) >= time() - 86400;

        if ($hasPresence) {
            return (string) ($presence['agents'][(string) $agentId]['serving'] ?? '0') === '1';
        }

        return false;
    }

    protected function hasLiveAgents()
    {
        $presence = $this->readPresence();
        foreach ($presence['agents'] as $agentId => $agent) {
            if (!is_array($agent) || (int) ($agent['updated_at'] ?? 0) < time() - 86400) {
                continue;
            }
            if ((string) ($agent['serving'] ?? '0') === '1') {
                return true;
            }
        }

        return false;
    }

    protected function liveAgentIds()
    {
        $ids = array();
        $presence = $this->readPresence();
        foreach ($presence['agents'] as $agentId => $agent) {
            if (!is_array($agent) || (int) ($agent['updated_at'] ?? 0) < time() - 86400) {
                continue;
            }
            if ((string) ($agent['serving'] ?? '0') === '1') {
                $ids[(int) $agentId] = true;
            }
        }

        return $ids;
    }

    protected function preview(array $message)
    {
        if ($message['type'] === 'image') {
            return '[图片]';
        }

        if ($message['type'] === 'voice') {
            return '[语音]';
        }

        if ($message['type'] === 'emoji') {
            return '[表情] ' . (string) $message['content'];
        }

        return $this->limitText(preg_replace('/\s+/u', ' ', trim((string) $message['content'])), 120);
    }

    protected function limitText($value, $length)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($value, 'UTF-8') > $length ? mb_substr($value, 0, $length, 'UTF-8') : $value;
        }

        return strlen($value) > $length ? substr($value, 0, $length) : $value;
    }

    protected function forumGuideRuleDefaults()
    {
        return array(
            'invite_rule' => '邀请好友说明：会员可邀请好友注册参与站内交流，禁止诱导、刷号或虚假承诺。',
            'recharge_rule' => '充值方式说明：支持支付宝、微信、USDT等方式，具体到账以客服核对为准。',
            'purchase_rule' => "AI预测说明：预测内容仅作站内参考。\n购买帖子说明：已购帖子可在购买记录查看。",
            'conduct_rule' => "发表帖子说明：内容需真实、清晰、符合栏目主题。\n禁止不文明评论说明：禁止辱骂、刷屏、恶意引战。\n惩罚说明：违规内容将删除，严重者限制账号功能。",
        );
    }

    protected function normalizeForumGuideRuleText($value)
    {
        $value = str_replace(array("\r\n", "\r"), "\n", trim((string) $value));
        $value = preg_replace('/[ \t]+/u', ' ', $value);
        $value = preg_replace("/\n{3,}/u", "\n\n", $value);

        return $this->limitText($value, 800);
    }

    protected function normalizeServiceHours($value)
    {
        $value = preg_replace('/\s+/u', ' ', trim((string) $value));
        $value = $this->limitText($value, 80);

        return $value !== '' ? $value : '09:00-23:00';
    }

    protected function serviceHoursContainNow($value)
    {
        $hours = $this->normalizeServiceHours((string) $value);
        $matches = array();

        if (!preg_match('/(\d{1,2})\s*:\s*(\d{1,2})\s*(?:-|~|—|–|至|到)\s*(\d{1,2})\s*:\s*(\d{1,2})/u', $hours, $matches)) {
            return false;
        }

        $startHour = max(0, min(23, (int) $matches[1]));
        $startMinute = max(0, min(59, (int) $matches[2]));
        $endHour = max(0, min(23, (int) $matches[3]));
        $endMinute = max(0, min(59, (int) $matches[4]));
        $start = $startHour * 60 + $startMinute;
        $end = $endHour * 60 + $endMinute;
        $now = (int) date('G') * 60 + (int) date('i');

        if ($start === $end) {
            return true;
        }

        if ($start < $end) {
            return $now >= $start && $now <= $end;
        }

        return $now >= $start || $now <= $end;
    }

    protected function defaultWelcomeText()
    {
        return '您好，这里是在线客服，请直接留言，客服看到后会尽快回复。';
    }

    protected function emojiList()
    {
        return array(
            '😊', '😄', '😁', '😂', '🤣',
            '😉', '😍', '😘', '🥰', '😋',
            '😜', '😎', '🤔', '🙄', '😳',
            '😅', '😌', '😴', '🤐', '🤭',
            '😭', '😢', '😤', '😡', '😱',
            '👍', '👎', '👏', '🙏', '💪',
            '👌', '✌️', '🤝', '👊', '👋',
            '❤️', '💔', '💕', '💯', '✨',
            '🌟', '🎉', '🎊', '🔥', '✅',
            '❌', '💬', '☕', '🍵', '🍻',
            '🎁', '💰', '🏆', '🚀', '🌹',
        );
    }

    protected function uploadErrorMessage($errorCode)
    {
        switch ((int) $errorCode) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return '上传文件超过服务器限制。';
            case UPLOAD_ERR_PARTIAL:
                return '上传文件不完整，请重新上传。';
            case UPLOAD_ERR_NO_FILE:
                return '请选择要上传的文件。';
            case UPLOAD_ERR_NO_TMP_DIR:
                return '服务器缺少临时目录，无法上传。';
            case UPLOAD_ERR_CANT_WRITE:
                return '服务器无法写入上传文件。';
            case UPLOAD_ERR_EXTENSION:
                return '上传被服务器扩展中止。';
            default:
                return '上传失败，请稍后重试。';
        }
    }

    protected function agentByUsername($username)
    {
        return $this->db()->fetch(
            'SELECT *
             FROM customer_service_accounts
             WHERE username = :username AND deleted_at IS NULL
             ORDER BY id DESC
             LIMIT 1',
            array('username' => (string) $username)
        );
    }

    protected function ensureTables()
    {
        if ($this->schemaReady) {
            return;
        }

        $schemaReadyCacheKey = $this->schemaReadyCacheKey();
        if ((string) $this->app->cache()->get($schemaReadyCacheKey, '', 3600) === '1') {
            $this->schemaReady = true;
            return;
        }

        $this->db()->execute(
            "CREATE TABLE IF NOT EXISTS customer_service_accounts (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                display_name VARCHAR(80) NOT NULL DEFAULT '',
                welcome_text VARCHAR(255) NOT NULL DEFAULT '',
                service_hours VARCHAR(80) NOT NULL DEFAULT '09:00-23:00',
                auto_reply_text MEDIUMTEXT NULL,
                activity_notice MEDIUMTEXT NULL,
                activity_notice_enabled TINYINT(1) NOT NULL DEFAULT 1,
                auto_reply_enabled TINYINT(1) NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT 'online',
                permissions_json TEXT NOT NULL,
                sort_order INT NOT NULL DEFAULT 50,
                last_login_at DATETIME DEFAULT NULL,
                last_login_ip VARCHAR(45) NOT NULL DEFAULT '',
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                deleted_at DATETIME DEFAULT NULL,
                INDEX idx_customer_service_accounts_username (username),
                INDEX idx_customer_service_accounts_status (status, sort_order),
                INDEX idx_customer_service_accounts_deleted_at (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $this->ensureAccountColumns();

        $this->db()->execute(
            "CREATE TABLE IF NOT EXISTS customer_service_sessions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                session_key VARCHAR(80) NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                assigned_agent_id BIGINT UNSIGNED DEFAULT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'waiting',
                member_typing_at DATETIME DEFAULT NULL,
                agent_typing_at DATETIME DEFAULT NULL,
                unread_for_member INT NOT NULL DEFAULT 0,
                unread_for_admin INT NOT NULL DEFAULT 0,
                last_message_type VARCHAR(20) NOT NULL DEFAULT 'text',
                last_message_preview VARCHAR(255) NOT NULL DEFAULT '',
                last_message_at DATETIME DEFAULT NULL,
                closed_at DATETIME DEFAULT NULL,
                agent_hidden_at DATETIME DEFAULT NULL,
                blocked_at DATETIME DEFAULT NULL,
                blocked_until DATETIME DEFAULT NULL,
                blocked_by_agent_id BIGINT UNSIGNED DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uniq_customer_service_sessions_key (session_key),
                UNIQUE KEY uniq_customer_service_sessions_user (user_id),
                INDEX idx_customer_service_sessions_status (status, last_message_at),
                INDEX idx_customer_service_sessions_agent (assigned_agent_id, status),
                INDEX idx_customer_service_sessions_agent_queue (agent_hidden_at, blocked_at, status, last_message_at),
                CONSTRAINT fk_customer_service_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_customer_service_sessions_agent FOREIGN KEY (assigned_agent_id) REFERENCES customer_service_accounts(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $this->ensureSessionColumns();

        $this->db()->execute(
            "CREATE TABLE IF NOT EXISTS customer_service_messages (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                session_id BIGINT UNSIGNED NOT NULL,
                sender_type VARCHAR(20) NOT NULL,
                sender_user_id INT UNSIGNED DEFAULT NULL,
                sender_agent_id BIGINT UNSIGNED DEFAULT NULL,
                message_type VARCHAR(20) NOT NULL DEFAULT 'text',
                content MEDIUMTEXT NOT NULL,
                attachment_url VARCHAR(255) NOT NULL DEFAULT '',
                attachment_name VARCHAR(150) NOT NULL DEFAULT '',
                attachment_mime VARCHAR(100) NOT NULL DEFAULT '',
                attachment_size INT UNSIGNED NOT NULL DEFAULT 0,
                voice_duration INT UNSIGNED NOT NULL DEFAULT 0,
                user_deleted_at DATETIME DEFAULT NULL,
                agent_deleted_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_customer_service_messages_session (session_id, id),
                INDEX idx_customer_service_messages_user_visible (session_id, user_deleted_at),
                INDEX idx_customer_service_messages_agent_visible (session_id, agent_deleted_at),
                INDEX idx_customer_service_messages_session_sender_visible
                    (session_id, sender_type, user_deleted_at, id),
                INDEX idx_customer_service_messages_sender_id (sender_type, id),
                CONSTRAINT fk_customer_service_messages_session FOREIGN KEY (session_id) REFERENCES customer_service_sessions(id) ON DELETE CASCADE,
                CONSTRAINT fk_customer_service_messages_user FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE SET NULL,
                CONSTRAINT fk_customer_service_messages_agent FOREIGN KEY (sender_agent_id) REFERENCES customer_service_accounts(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $this->ensureMessageColumns();
        $this->schemaReady = true;
        $this->app->cache()->put($schemaReadyCacheKey, '1');
    }

    protected function schemaReadyCacheKey()
    {
        $databaseConfig = $this->app->databaseConfig();
        $databaseName = is_array($databaseConfig) ? (string) ($databaseConfig['database'] ?? '') : '';

        return 'customer_service_schema_ready_' . md5($databaseName . '|20260611-performance-01');
    }

    protected function ensureSessionColumns()
    {
        if (!$this->columnExists('customer_service_sessions', 'assigned_agent_id')) {
            $this->db()->pdo()->exec('ALTER TABLE customer_service_sessions ADD COLUMN assigned_agent_id BIGINT UNSIGNED DEFAULT NULL AFTER user_id');
        }

        if (!$this->columnExists('customer_service_sessions', 'member_typing_at')) {
            $this->db()->pdo()->exec('ALTER TABLE customer_service_sessions ADD COLUMN member_typing_at DATETIME DEFAULT NULL AFTER status');
        }

        if (!$this->columnExists('customer_service_sessions', 'agent_typing_at')) {
            $this->db()->pdo()->exec('ALTER TABLE customer_service_sessions ADD COLUMN agent_typing_at DATETIME DEFAULT NULL AFTER member_typing_at');
        }

        if (!$this->columnExists('customer_service_sessions', 'agent_hidden_at')) {
            $this->db()->pdo()->exec('ALTER TABLE customer_service_sessions ADD COLUMN agent_hidden_at DATETIME DEFAULT NULL AFTER closed_at');
        }

        if (!$this->columnExists('customer_service_sessions', 'blocked_at')) {
            $this->db()->pdo()->exec('ALTER TABLE customer_service_sessions ADD COLUMN blocked_at DATETIME DEFAULT NULL AFTER agent_hidden_at');
        }

        if (!$this->columnExists('customer_service_sessions', 'blocked_until')) {
            $this->db()->pdo()->exec('ALTER TABLE customer_service_sessions ADD COLUMN blocked_until DATETIME DEFAULT NULL AFTER blocked_at');
        }

        if (!$this->columnExists('customer_service_sessions', 'blocked_by_agent_id')) {
            $this->db()->pdo()->exec('ALTER TABLE customer_service_sessions ADD COLUMN blocked_by_agent_id BIGINT UNSIGNED DEFAULT NULL AFTER blocked_until');
        }

        if (!$this->indexExists('customer_service_sessions', 'idx_customer_service_sessions_agent')) {
            $this->db()->pdo()->exec('ALTER TABLE customer_service_sessions ADD INDEX idx_customer_service_sessions_agent (assigned_agent_id, status)');
        }

        if (!$this->indexExists('customer_service_sessions', 'idx_customer_service_sessions_agent_queue')) {
            $this->db()->pdo()->exec('ALTER TABLE customer_service_sessions ADD INDEX idx_customer_service_sessions_agent_queue (agent_hidden_at, blocked_at, status, last_message_at)');
        }
    }

    protected function ensureAccountColumns()
    {
        if (!$this->columnExists('customer_service_accounts', 'password_hash')) {
            $this->db()->pdo()->exec("ALTER TABLE customer_service_accounts ADD COLUMN password_hash VARCHAR(255) NOT NULL DEFAULT '' AFTER username");
        }

        if (!$this->columnExists('customer_service_accounts', 'display_name')) {
            $this->db()->pdo()->exec("ALTER TABLE customer_service_accounts ADD COLUMN display_name VARCHAR(80) NOT NULL DEFAULT '' AFTER password_hash");
        }

        if (!$this->columnExists('customer_service_accounts', 'welcome_text')) {
            $this->db()->pdo()->exec("ALTER TABLE customer_service_accounts ADD COLUMN welcome_text VARCHAR(255) NOT NULL DEFAULT '' AFTER display_name");
        }

        if (!$this->columnExists('customer_service_accounts', 'service_hours')) {
            $this->db()->pdo()->exec("ALTER TABLE customer_service_accounts ADD COLUMN service_hours VARCHAR(80) NOT NULL DEFAULT '09:00-23:00' AFTER welcome_text");
        }

        if (!$this->columnExists('customer_service_accounts', 'auto_reply_text')) {
            $this->db()->pdo()->exec('ALTER TABLE customer_service_accounts ADD COLUMN auto_reply_text MEDIUMTEXT NULL AFTER service_hours');
        }

        if (!$this->columnExists('customer_service_accounts', 'activity_notice')) {
            $this->db()->pdo()->exec('ALTER TABLE customer_service_accounts ADD COLUMN activity_notice MEDIUMTEXT NULL AFTER auto_reply_text');
        }

        if (!$this->columnExists('customer_service_accounts', 'activity_notice_enabled')) {
            $this->db()->pdo()->exec('ALTER TABLE customer_service_accounts ADD COLUMN activity_notice_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER activity_notice');
        }

        if (!$this->columnExists('customer_service_accounts', 'auto_reply_enabled')) {
            $this->db()->pdo()->exec('ALTER TABLE customer_service_accounts ADD COLUMN auto_reply_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER activity_notice_enabled');
        }

        if (!$this->columnExists('customer_service_accounts', 'status')) {
            $this->db()->pdo()->exec("ALTER TABLE customer_service_accounts ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'online' AFTER welcome_text");
        }

        if (!$this->columnExists('customer_service_accounts', 'permissions_json')) {
            $this->db()->pdo()->exec('ALTER TABLE customer_service_accounts ADD COLUMN permissions_json TEXT NULL AFTER status');
            $this->db()->execute(
                'UPDATE customer_service_accounts SET permissions_json = :permissions_json WHERE permissions_json IS NULL',
                array('permissions_json' => json_encode($this->defaultAgentPermissions(), JSON_UNESCAPED_UNICODE))
            );
        }

        if (!$this->columnExists('customer_service_accounts', 'sort_order')) {
            $this->db()->pdo()->exec('ALTER TABLE customer_service_accounts ADD COLUMN sort_order INT NOT NULL DEFAULT 50 AFTER permissions_json');
        }

        if (!$this->columnExists('customer_service_accounts', 'last_login_at')) {
            $this->db()->pdo()->exec('ALTER TABLE customer_service_accounts ADD COLUMN last_login_at DATETIME DEFAULT NULL AFTER sort_order');
        }

        if (!$this->columnExists('customer_service_accounts', 'last_login_ip')) {
            $this->db()->pdo()->exec("ALTER TABLE customer_service_accounts ADD COLUMN last_login_ip VARCHAR(45) NOT NULL DEFAULT '' AFTER last_login_at");
        }

        if (!$this->columnExists('customer_service_accounts', 'deleted_at')) {
            $this->db()->pdo()->exec('ALTER TABLE customer_service_accounts ADD COLUMN deleted_at DATETIME DEFAULT NULL AFTER updated_at');
        }

        if (!$this->indexExists('customer_service_accounts', 'idx_customer_service_accounts_username')) {
            $this->db()->pdo()->exec('ALTER TABLE customer_service_accounts ADD INDEX idx_customer_service_accounts_username (username)');
        }
    }

    protected function ensureMessageColumns()
    {
        if (!$this->columnExists('customer_service_messages', 'sender_agent_id')) {
            $this->db()->pdo()->exec('ALTER TABLE customer_service_messages ADD COLUMN sender_agent_id BIGINT UNSIGNED DEFAULT NULL AFTER sender_user_id');
        }

        if (!$this->columnExists('customer_service_messages', 'agent_deleted_at')) {
            $this->db()->pdo()->exec('ALTER TABLE customer_service_messages ADD COLUMN agent_deleted_at DATETIME DEFAULT NULL AFTER user_deleted_at');
        }

        if (!$this->indexExists('customer_service_messages', 'idx_customer_service_messages_agent_visible')) {
            $this->db()->pdo()->exec('ALTER TABLE customer_service_messages ADD INDEX idx_customer_service_messages_agent_visible (session_id, agent_deleted_at)');
        }

        if (!$this->indexExists('customer_service_messages', 'idx_customer_service_messages_session_sender_visible')) {
            $this->db()->pdo()->exec(
                'ALTER TABLE customer_service_messages ADD INDEX '
                . 'idx_customer_service_messages_session_sender_visible '
                . '(session_id, sender_type, user_deleted_at, id)'
            );
        }

        if (!$this->indexExists('customer_service_messages', 'idx_customer_service_messages_sender_id')) {
            $this->db()->pdo()->exec(
                'ALTER TABLE customer_service_messages ADD INDEX '
                . 'idx_customer_service_messages_sender_id (sender_type, id)'
            );
        }
    }

    protected function columnExists($tableName, $columnName)
    {
        $cacheKey = (string) $tableName . '.' . (string) $columnName;
        if (!empty($this->columnExistsCache[$cacheKey])) {
            return true;
        }

        $row = $this->db()->fetch(
            'SELECT COUNT(*) AS total_count
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name',
            array(
                'table_name' => (string) $tableName,
                'column_name' => (string) $columnName,
            )
        );

        $exists = $row && (int) ($row['total_count'] ?? 0) > 0;
        if ($exists) {
            $this->columnExistsCache[$cacheKey] = true;
        }

        return $exists;
    }

    protected function indexExists($tableName, $indexName)
    {
        $cacheKey = (string) $tableName . '.' . (string) $indexName;
        if (!empty($this->indexExistsCache[$cacheKey])) {
            return true;
        }

        $row = $this->db()->fetch(
            'SELECT COUNT(*) AS total_count
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND INDEX_NAME = :index_name',
            array(
                'table_name' => (string) $tableName,
                'index_name' => (string) $indexName,
            )
        );

        $exists = $row && (int) ($row['total_count'] ?? 0) > 0;
        if ($exists) {
            $this->indexExistsCache[$cacheKey] = true;
        }

        return $exists;
    }

}
