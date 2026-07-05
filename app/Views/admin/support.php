<?php
$customerServicePayload = isset($customerServicePayload) && is_array($customerServicePayload) ? $customerServicePayload : array();
$customerServiceFilters = isset($customerServiceFilters) && is_array($customerServiceFilters) ? $customerServiceFilters : array();
$customerServiceOverview = isset($customerServicePayload['overview']) && is_array($customerServicePayload['overview']) ? $customerServicePayload['overview'] : array();
$customerServiceAgents = isset($customerServicePayload['agents']) && is_array($customerServicePayload['agents']) ? $customerServicePayload['agents'] : array();
$customerServiceSessions = isset($customerServicePayload['sessions']) && is_array($customerServicePayload['sessions']) ? $customerServicePayload['sessions'] : array();
$customerServiceSession = isset($customerServicePayload['session']) && is_array($customerServicePayload['session']) ? $customerServicePayload['session'] : null;
$customerServiceMessages = isset($customerServicePayload['messages']) && is_array($customerServicePayload['messages']) ? $customerServicePayload['messages'] : array();
$customerServiceStatus = (string) ($customerServiceFilters['status'] ?? ($customerServicePayload['status'] ?? 'all'));
$customerServiceCanManage = !empty($customerServiceCanManage);
$customerServiceEditingAgent = isset($customerServiceEditingAgent) && is_array($customerServiceEditingAgent) ? $customerServiceEditingAgent : null;
$customerServiceAddingAgent = !empty($customerServiceAddingAgent);
$customerServiceAgentFormOpen = $customerServiceEditingAgent || $customerServiceAddingAgent;
$customerServiceAgentForm = $customerServiceEditingAgent ?: array(
    'id' => 0,
    'username' => '',
    'display_name' => '',
    'welcome_text' => '',
    'service_hours' => '09:00-23:00',
    'auto_reply_text' => '',
    'auto_reply_enabled' => 0,
    'status' => 'online',
    'sort_order' => 50,
);
$customerServiceView = (string) ($customerServiceFilters['view'] ?? ($_GET['support_view'] ?? ''));
if (in_array($customerServiceView, array('agent_form', 'agent_list'), true)) {
    $customerServiceView = 'agents';
}
if (!in_array($customerServiceView, array('supervision', 'agents'), true)) {
    $customerServiceView = $customerServiceAgentFormOpen ? 'agents' : 'supervision';
}
$customerServiceSessionId = $customerServiceSession ? (int) ($customerServiceSession['id'] ?? 0) : (int) ($customerServicePayload['active_id'] ?? 0);
$customerServiceLastDate = '';
$customerServiceActiveName = $customerServiceSession ? (string) (($customerServiceSession['username'] ?? '') ?: '会员') : '未选择会话';
$customerServiceActiveInitial = $customerServiceActiveName !== ''
    ? (function_exists('mb_substr') ? mb_substr($customerServiceActiveName, 0, 1, 'UTF-8') : substr($customerServiceActiveName, 0, 1))
    : '会';
$customerServiceActiveStatus = $customerServiceSession ? (string) ($customerServiceSession['status_label'] ?? '待接待') : '等待选择';
$customerServiceActiveOnline = $customerServiceSession ? (string) ($customerServiceSession['member_online_label'] ?? '离线') : '离线';
$customerServiceActiveOnlineType = $customerServiceSession ? (string) ($customerServiceSession['member_online_type'] ?? 'offline') : 'offline';
$customerServiceActiveAgent = $customerServiceSession ? (string) (($customerServiceSession['assigned_agent_name'] ?? '') ?: '未接待') : '未接待';
$customerServicePermissionOptions = array(
    'reply' => '回复消息',
    'take' => '接待新会话',
    'close' => '关闭会话',
    'clear' => '删除本账号记录',
);
$customerServiceStatusLabels = array(
    'all' => array('label' => '全部会话', 'count_key' => 'total_sessions'),
    'unread' => array('label' => '未读消息', 'count_key' => 'unread_sessions'),
    'open' => array('label' => '接待中', 'count_key' => 'open_sessions'),
);
$customerServiceStatusCount = static function (array $overview, array $meta) {
    return max(0, (int) ($overview[(string) ($meta['count_key'] ?? '')] ?? 0));
};
$customerServiceSupervisionCount = $customerServiceView === 'supervision'
    ? count($customerServiceSessions)
    : max(0, (int) ($customerServiceOverview['total_sessions'] ?? 0));
$customerServiceEditPermissions = $customerServiceEditingAgent && isset($customerServiceAgentForm['permissions']) && is_array($customerServiceAgentForm['permissions'])
    ? $customerServiceAgentForm['permissions']
    : array(
        'reply' => true,
        'take' => true,
        'close' => true,
        'clear' => true,
    );
$customerServiceSupportUrl = public_url('admin.php') . '?page=support';
$customerServiceFrontAgentUrl = (string) ($customerServicePayload['front_agent_url'] ?? public_url('admin.php'));
$customerServiceSupervisionUrlExtra = array('status' => $customerServiceStatus);
if ($customerServiceSessionId > 0) {
    $customerServiceSupervisionUrlExtra['session_id'] = $customerServiceSessionId;
}
$customerServiceTabUrl = static function ($view, array $extra = array()) {
    return public_url('admin.php') . '?' . http_build_query(array_merge(array(
        'page' => 'support',
        'support_view' => (string) $view,
    ), $extra));
};
?>
<section
    class="support-workbench support-management"
    data-support-management
    data-customer-service
    data-customer-service-role="admin"
    data-api-url="<?php echo e(public_url('api.php')); ?>"
    data-token="<?php echo e(csrf_token('api')); ?>"
    data-session-id="<?php echo e((string) $customerServiceSessionId); ?>"
    data-has-session="<?php echo $customerServiceSession ? '1' : '0'; ?>"
    data-status="<?php echo e($customerServiceStatus); ?>"
    data-session-base-url="<?php echo e(public_url('admin.php')); ?>"
    data-poll-action="customer_service.admin.poll"
    data-enabled="<?php echo $customerServiceView === 'supervision' ? '1' : '0'; ?>"
    data-empty-text="请选择左侧会话查看客服与会员聊天记录。"
>
    <header class="support-view-switch" aria-label="在线客服页面切换">
        <a
            class="support-view-switch-btn<?php echo $customerServiceView === 'supervision' ? ' is-active' : ''; ?>"
            href="<?php echo e($customerServiceTabUrl('supervision', $customerServiceSupervisionUrlExtra)); ?>"
            <?php echo $customerServiceView === 'supervision' ? 'aria-current="page"' : ''; ?>
        >
            <span>监督记录</span>
            <strong><?php echo e((string) $customerServiceSupervisionCount); ?></strong>
        </a>
        <a
            class="support-view-switch-btn<?php echo $customerServiceView === 'agents' ? ' is-active' : ''; ?>"
            href="<?php echo e($customerServiceTabUrl('agents')); ?>"
            <?php echo $customerServiceView === 'agents' ? 'aria-current="page"' : ''; ?>
        >
            <span>客服账号</span>
            <strong><?php echo e((string) count($customerServiceAgents)); ?></strong>
        </a>
    </header>

    <?php if ($customerServiceView === 'supervision'): ?>
    <div class="support-supervision-grid">
        <section class="support-queue-panel">
            <div class="support-panel-head support-queue-head">
                <div>
                    <h3>监督会话队列</h3>
                </div>
                <strong class="support-session-count"><?php echo e((string) count($customerServiceSessions)); ?> 条会话</strong>
            </div>
            <nav class="support-filter-strip is-compact" aria-label="会话状态筛选">
                <?php foreach ($customerServiceStatusLabels as $statusCode => $statusMeta): ?>
                    <a
                        class="support-filter-pill<?php echo $customerServiceStatus === $statusCode ? ' is-active' : ''; ?>"
                        href="<?php echo e($customerServiceSupportUrl . '&' . http_build_query(array('status' => $statusCode))); ?>"
                    >
                        <span><?php echo e((string) ($statusMeta['label'] ?? '全部')); ?></span>
                        <strong><?php echo e((string) $customerServiceStatusCount($customerServiceOverview, $statusMeta)); ?></strong>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="support-ticket-list" data-customer-service-session-list>
                <?php if ($customerServiceSessions): ?>
                    <?php foreach ($customerServiceSessions as $session): ?>
                        <?php
                        $sessionId = (int) ($session['id'] ?? 0);
                        $isActive = $sessionId === $customerServiceSessionId;
                        $unreadCount = (int) ($session['unread_for_admin'] ?? 0);
                        $sessionStatus = (string) ($session['status'] ?? 'waiting');
                        ?>
                        <a
                            class="customer-service-session-item support-ticket-card<?php echo $isActive ? ' is-active' : ''; ?>"
                            href="<?php echo e($customerServiceSupportUrl . '&' . http_build_query(array('status' => $customerServiceStatus, 'session_id' => $sessionId))); ?>"
                            data-customer-service-session-id="<?php echo e((string) $sessionId); ?>"
                        >
                            <span class="customer-service-session-main support-ticket-main">
                                <strong><?php echo e((string) (($session['username'] ?? '') ?: '会员')); ?></strong>
                                <span class="customer-service-session-presence" data-status-type="<?php echo e((string) ($session['member_online_type'] ?? 'offline')); ?>"><?php echo e((string) ($session['member_online_label'] ?? '离线')); ?></span>
                                <span><?php echo e((string) (($session['last_message_preview'] ?? '') ?: '暂无消息')); ?></span>
                            </span>
                            <span class="customer-service-session-side support-ticket-side">
                                <em class="support-status-badge <?php echo $unreadCount > 0 ? 'is-hot' : 'is-' . e($sessionStatus); ?>"><?php echo $unreadCount > 0 ? e((string) $unreadCount) : e((string) ($session['status_label'] ?? '待接待')); ?></em>
                                <small><?php echo e((string) ($session['last_message_at'] ?? '')); ?></small>
                            </span>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="support-empty">当前状态下暂无会话。</div>
                <?php endif; ?>
            </div>
        </section>

        <section class="support-chat-panel">
            <div class="support-chat-head">
                <div class="support-member-card">
                    <span class="support-member-avatar" data-customer-service-active-avatar aria-label="会员头像"><i class="fa-solid fa-circle-user" aria-hidden="true"></i></span>
                    <div>
                        <h3 data-customer-service-active-name><?php echo e($customerServiceActiveName); ?></h3>
                        <div class="support-member-meta">
                            <span data-customer-service-active-online data-status-type="<?php echo e($customerServiceActiveOnlineType); ?>"><?php echo e($customerServiceActiveOnline); ?></span>
                            <span data-customer-service-active-status><?php echo e($customerServiceActiveStatus); ?></span>
                            <span data-customer-service-active-agent>接待：<?php echo e($customerServiceActiveAgent); ?></span>
                            <span>只读监督</span>
                        </div>
                    </div>
                </div>
                <div class="support-chat-actions">
                    <a class="support-action-btn is-muted" href="<?php echo e($customerServiceFrontAgentUrl); ?>" target="_blank" rel="noopener noreferrer">前台接待</a>
                </div>
            </div>

            <div class="support-message-stream" data-customer-service-log>
                <?php if ($customerServiceSession && $customerServiceMessages): ?>
                    <?php foreach ($customerServiceMessages as $message): ?>
                        <?php
                        $messageDate = (string) ($message['created_date'] ?? '');
                        $messageType = (string) ($message['message_type'] ?? 'text');
                        $isSelf = (string) ($message['sender_type'] ?? '') === 'agent';
                        ?>
                        <?php if ($messageDate !== '' && $messageDate !== $customerServiceLastDate): ?>
                            <div class="customer-service-date"><?php echo e($messageDate); ?></div>
                            <?php $customerServiceLastDate = $messageDate; ?>
                        <?php endif; ?>
                        <div class="customer-service-message <?php echo $isSelf ? 'is-self' : 'is-peer'; ?>" data-customer-service-message-id="<?php echo e((string) ($message['id'] ?? 0)); ?>">
                            <div class="customer-service-bubble-wrap">
                                <div class="customer-service-meta">
                                    <span><?php echo e((string) ($message['sender_name'] ?? ($isSelf ? '客服' : '会员'))); ?></span>
                                    <span><?php echo e((string) ($message['created_time'] ?? '')); ?></span>
                                </div>
                                <div class="customer-service-bubble is-<?php echo e($messageType); ?>">
                                    <?php if ($messageType === 'image'): ?>
                                        <a href="<?php echo e((string) ($message['attachment_url'] ?? '')); ?>" target="_blank" rel="noopener noreferrer">
                                            <img
                                                src="<?php echo e((string) ($message['attachment_url'] ?? '')); ?>"
                                                alt="聊天图片"
                                                width="240"
                                                height="180"
                                                loading="lazy"
                                                decoding="async"
                                            >
                                        </a>
                                    <?php elseif ($messageType === 'voice'): ?>
                                        <div class="customer-service-voice">
                                            <i class="fa-solid fa-volume-high"></i>
                                            <span><?php echo max(1, (int) ($message['voice_duration'] ?? 0)); ?> 秒语音</span>
                                        </div>
                                        <audio controls controlsList="nodownload noplaybackrate" disablepictureinpicture preload="none" src="<?php echo e((string) ($message['attachment_url'] ?? '')); ?>"></audio>
                                    <?php else: ?>
                                        <?php echo nl2br(e((string) ($message['content'] ?? ''))); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php elseif ($customerServiceSession): ?>
                    <div class="support-empty" data-customer-service-empty>当前会话暂无聊天记录。</div>
                <?php else: ?>
                    <div class="support-empty" data-customer-service-empty>请选择左侧会话查看客服与会员聊天记录。</div>
                <?php endif; ?>
            </div>

            <div class="support-locked">后台监督区只读，不提供删除、回复或关闭会话操作。</div>
        </section>
    </div>
    <?php endif; ?>

    <?php if ($customerServiceView === 'agents'): ?>
    <div class="support-management-grid is-agents<?php echo $customerServiceAgentFormOpen ? ' has-agent-modal' : ''; ?>">
        <?php if ($customerServiceAgentFormOpen): ?>
            <div class="support-agent-modal" role="dialog" aria-modal="true" aria-labelledby="support-agent-editor-title">
                <a class="support-agent-modal-backdrop" href="<?php echo e($customerServiceTabUrl('agents')); ?>" aria-label="<?php echo $customerServiceAddingAgent ? '关闭添加客服账号' : '关闭编辑客服账号'; ?>"></a>
                <section class="support-ops-card support-management-card support-agent-editor-card is-modal">
                    <div class="support-panel-head">
                        <div>
                            <h3 id="support-agent-editor-title"><?php echo $customerServiceAddingAgent ? '添加客服账号' : '编辑客服账号'; ?></h3>
                        </div>
                        <a class="support-open-link is-muted" href="<?php echo e($customerServiceTabUrl('agents')); ?>"><?php echo $customerServiceAddingAgent ? '取消添加' : '取消编辑'; ?></a>
                    </div>

                    <?php if ($customerServiceCanManage): ?>
                        <form class="support-agent-form" method="post" action="<?php echo e(public_url('api.php')); ?>" data-customer-service-agent-form data-success-redirect="<?php echo e($customerServiceTabUrl('agents')); ?>">
                            <input type="hidden" name="action" value="customer_service.agent.save">
                            <input type="hidden" name="_token" value="<?php echo e(csrf_token('api')); ?>">
                            <input type="hidden" name="id" value="<?php echo e((string) (int) ($customerServiceAgentForm['id'] ?? 0)); ?>">
                            <label>
                                <span>客服账号</span>
                                <input class="support-field" name="username" maxlength="32" autocomplete="off" required value="<?php echo e((string) ($customerServiceAgentForm['username'] ?? '')); ?>" placeholder="例如：service01">
                            </label>
                            <label>
                                <span><?php echo $customerServiceAddingAgent ? '登录密码' : '新密码'; ?></span>
                                <input class="support-field" type="password" name="password" maxlength="64" autocomplete="new-password" <?php echo $customerServiceAddingAgent ? 'required' : ''; ?> placeholder="<?php echo $customerServiceAddingAgent ? '新增必须填写，至少 6 位' : '不修改请留空'; ?>">
                            </label>
                            <label>
                                <span>显示名称</span>
                                <input class="support-field" name="display_name" maxlength="80" value="<?php echo e((string) ($customerServiceAgentForm['display_name'] ?? '')); ?>" placeholder="例如：值班客服">
                            </label>
                            <label>
                                <span>欢迎语</span>
                                <input class="support-field" name="welcome_text" maxlength="255" value="<?php echo e((string) ($customerServiceAgentForm['welcome_text'] ?? '')); ?>" placeholder="您好，请问需要什么帮助？">
                            </label>
                            <label>
                                <span>接待时间</span>
                                <input class="support-field" name="service_hours" maxlength="80" value="<?php echo e((string) ($customerServiceAgentForm['service_hours'] ?? '09:00-23:00')); ?>" placeholder="例如：09:00-23:00">
                            </label>
                            <label>
                                <span>自动回复语</span>
                                <textarea class="support-field" name="auto_reply_text" rows="3" maxlength="1000" placeholder="会员发送消息后自动回复，留空则不启用"><?php echo e((string) ($customerServiceAgentForm['auto_reply_text'] ?? '')); ?></textarea>
                            </label>
                            <div class="support-agent-row">
                                <label>
                                    <span>账号状态</span>
                                    <select class="support-field" name="status">
                                        <option value="online" <?php echo (string) ($customerServiceAgentForm['status'] ?? 'online') === 'online' ? 'selected' : ''; ?>>启用</option>
                                        <option value="offline" <?php echo (string) ($customerServiceAgentForm['status'] ?? '') === 'offline' ? 'selected' : ''; ?>>停用</option>
                                    </select>
                                </label>
                                <label>
                                    <span>排序</span>
                                    <input class="support-field" type="number" name="sort_order" value="<?php echo e((string) (int) ($customerServiceAgentForm['sort_order'] ?? 50)); ?>" min="0" max="9999">
                                </label>
                            </div>
                            <label class="support-check-card">
                                <input type="hidden" name="auto_reply_enabled" value="0">
                                <input type="checkbox" name="auto_reply_enabled" value="1" <?php echo !empty($customerServiceAgentForm['auto_reply_enabled']) ? 'checked' : ''; ?>>
                                <span>启用自动回复</span>
                            </label>
                            <div class="support-permission-grid" aria-label="客服权限">
                                <?php foreach ($customerServicePermissionOptions as $permissionCode => $permissionLabel): ?>
                                    <label class="support-check-card">
                                        <input type="checkbox" name="permissions[]" value="<?php echo e($permissionCode); ?>" <?php echo !empty($customerServiceEditPermissions[$permissionCode]) ? 'checked' : ''; ?>>
                                        <span><?php echo e($permissionLabel); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <button class="support-save-btn" type="submit"><?php echo $customerServiceAddingAgent ? '添加客服账号' : '保存修改'; ?></button>
                        </form>
                    <?php else: ?>
                        <div class="support-empty">当前后台账号没有客服账号管理权限。</div>
                    <?php endif; ?>
                </section>
            </div>
        <?php endif; ?>

        <section class="support-ops-card support-management-card">
            <div class="support-panel-head support-agent-list-head">
                <div>
                    <h3>客服账号列表</h3>
                </div>
                <div class="support-agent-head-actions">
                    <?php if ($customerServiceCanManage): ?>
                        <a class="support-open-link" href="<?php echo e($customerServiceTabUrl('agents', array('add_agent' => 1))); ?>">添加客服账号</a>
                    <?php endif; ?>
                    <strong class="support-agent-count"><?php echo e((string) count($customerServiceAgents)); ?> 个账号</strong>
                </div>
            </div>
            <div class="support-agent-table">
                <?php if ($customerServiceAgents): ?>
                    <?php foreach ($customerServiceAgents as $agent): ?>
                        <?php $agentStatus = (string) ($agent['status'] ?? 'online'); ?>
                        <div class="support-agent-table-row">
                            <div class="support-agent-table-main">
                                <span class="support-agent-dot is-<?php echo e($agentStatus === 'offline' ? 'offline' : 'online'); ?>"></span>
                                <div>
                                    <strong><?php echo e((string) (($agent['display_name'] ?? '') ?: ($agent['username'] ?? '客服'))); ?></strong>
                                    <span class="support-agent-username">账号：<?php echo e((string) ($agent['username'] ?? '')); ?></span>
                                    <span class="support-agent-permissions">权限：<?php echo e((string) ($agent['permission_text'] ?? '仅查看')); ?></span>
                                </div>
                            </div>
                            <div class="support-agent-table-meta">
                                <em class="support-agent-state is-<?php echo e($agentStatus === 'offline' ? 'offline' : 'online'); ?>"><?php echo $agentStatus === 'online' ? '启用' : '停用'; ?></em>
                                <small class="support-agent-login">上次登录：<?php echo e((string) ($agent['last_login_at'] ?? '-')); ?></small>
                            </div>
                            <?php if ($customerServiceCanManage): ?>
                                <div class="support-agent-table-actions">
                                    <a class="support-mini-btn" href="<?php echo e($customerServiceTabUrl('agents', array('edit_agent' => (int) ($agent['id'] ?? 0)))); ?>">编辑</a>
                                    <form method="post" action="<?php echo e(public_url('api.php')); ?>" data-ajax-form data-confirm="确认删除该客服账号吗？">
                                        <input type="hidden" name="action" value="customer_service.agent.delete">
                                        <input type="hidden" name="_token" value="<?php echo e(csrf_token('api')); ?>">
                                        <input type="hidden" name="id" value="<?php echo e((string) (int) ($agent['id'] ?? 0)); ?>">
                                        <button class="support-mini-btn is-danger" type="submit">删除</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="support-empty">暂无客服账号，请先添加客服账号。</div>
                <?php endif; ?>
            </div>
        </section>
    </div>
    <?php endif; ?>
</section>
