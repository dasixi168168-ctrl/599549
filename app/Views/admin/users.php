<?php
$users = isset($users) && is_array($users) ? $users : array();
$userRoles = isset($userRoles) && is_array($userRoles) ? $userRoles : array();
$userFilters = isset($userFilters) && is_array($userFilters) ? $userFilters : array();
$userPage = isset($userPage) && is_array($userPage) ? $userPage : array(
    'items' => $users,
    'total' => count($users),
    'page_no' => 1,
    'per_page' => count($users) > 0 ? count($users) : 40,
    'page_count' => 1,
);
$passwordResetRequests = isset($passwordResetRequests) && is_array($passwordResetRequests) ? $passwordResetRequests : array();
$consumptionRecords = isset($consumptionRecords) && is_array($consumptionRecords) ? $consumptionRecords : array();
$userCanManage = !empty($userCanManage);
$currentKeyword = (string) ($userFilters['keyword'] ?? '');
$currentRole = (string) ($userFilters['role_key'] ?? '');
$currentStatus = (string) ($userFilters['status'] ?? '');
$currentResetStatus = (string) ($userFilters['reset_status'] ?? '');
$currentUserPanel = isset($userPanel) ? (string) $userPanel : 'members';
if (!in_array($currentUserPanel, array('register_rules', 'members', 'consumption'), true)) {
    $currentUserPanel = 'members';
}
$userPageNo = max(1, (int) ($userPage['page_no'] ?? 1));
$userPageCount = max(1, (int) ($userPage['page_count'] ?? 1));
$userTotalCount = max(0, (int) ($userPage['total'] ?? count($users)));
$memberPaginationQueryBase = array(
    'page' => 'users',
    'user_panel' => 'members',
);
if ($currentKeyword !== '') {
    $memberPaginationQueryBase['keyword'] = $currentKeyword;
}
if ($currentRole !== '') {
    $memberPaginationQueryBase['role_key'] = $currentRole;
}
if ($currentStatus !== '') {
    $memberPaginationQueryBase['status'] = $currentStatus;
}
if ($currentResetStatus !== '') {
    $memberPaginationQueryBase['reset_status'] = $currentResetStatus;
}
$registerBonus = max(0, (int) ($registerBonus ?? 88));
$registerLimitDays = max(0, min(365, (int) ($registerLimitDays ?? 1)));
$inviteRegisterBonus = max(0, (int) ($inviteRegisterBonus ?? 0));

$statusLabel = static function ($status) {
    return (string) $status === 'active' ? '正常' : '禁用';
};

$resetStatusLabel = static function ($status) {
    if ((string) $status === 'processed') {
        return '已处理';
    }

    if ((string) $status === 'pending') {
        return '待处理';
    }

    return '暂无申请';
};

$dateTimeLocalValue = static function ($value) {
    $timestamp = $value ? strtotime((string) $value) : false;

    return $timestamp ? date('Y-m-d\TH:i', $timestamp) : '';
};

$locationLabelUnknown = static function ($value) {
    $value = strtolower(trim((string) $value));

    return $value === '' || in_array($value, array('未知', '未知地区', '未知省份', '未知城市', 'unknown', 'unknown province', 'unknown city', '-'), true);
};

$registerSourceUnknown = static function ($value) use ($locationLabelUnknown) {
    $parts = preg_split('/\s*\/\s*/u', trim((string) $value));
    $province = trim((string) ($parts[0] ?? ''));
    $city = trim((string) ($parts[1] ?? ''));

    return $locationLabelUnknown($province) || $locationLabelUnknown($city);
};

$resolveRegisterCarrierFromIp = static function (array $row) {
    foreach (array('register_ip', 'last_login_ip') as $field) {
        $ip = trim((string) ($row[$field] ?? ''));
        if ($ip === '' || $ip === '-') {
            continue;
        }

        $carrier = trim((string) \App\Core\Security::carrierFromIpAddress($ip));
        if ($carrier !== '' && !in_array($carrier, array('未知运营商', '内网', '本地网络'), true)) {
            return $carrier;
        }
    }

    return '未知运营商';
};

$resolveRegisterSourceFromIp = static function (array $row) use ($locationLabelUnknown) {
    $candidateIps = array();
    foreach (array('register_ip', 'last_login_ip') as $field) {
        $ip = trim((string) ($row[$field] ?? ''));
        if ($ip !== '' && $ip !== '-' && !in_array($ip, $candidateIps, true)) {
            $candidateIps[] = $ip;
        }
    }

    foreach ($candidateIps as $ip) {
        $location = \App\Core\Security::ipLocationFromAddress($ip);
        $province = trim((string) ($location['province'] ?? ''));
        $city = trim((string) ($location['city'] ?? ''));
        if (!$locationLabelUnknown($province) && !$locationLabelUnknown($city)) {
            $carrier = trim((string) \App\Core\Security::carrierFromIpAddress($ip));
            if ($carrier === '') {
                $carrier = '未知运营商';
            }

            return $province . ' / ' . $city . ' / ' . $carrier;
        }
    }

    return '';
};

$memberPanelUrl = static function ($panel) use ($currentKeyword, $currentRole, $currentStatus, $currentResetStatus) {
    $query = array(
        'page' => 'users',
        'user_panel' => (string) $panel,
    );
    if ($currentKeyword !== '') {
        $query['keyword'] = $currentKeyword;
    }
    if ($currentRole !== '') {
        $query['role_key'] = $currentRole;
    }
    if ($currentStatus !== '') {
        $query['status'] = $currentStatus;
    }
    if ($currentResetStatus !== '') {
        $query['reset_status'] = $currentResetStatus;
    }

    return public_url('admin.php') . '?' . http_build_query($query);
};
?>
<section class="member-console ui-admin-page">
    <?php if (!$userCanManage): ?>
        <div class="member-console-alert">当前账号只有查看权限，不能执行会员资料、积分、删除、VIP和密码修改操作。</div>
    <?php endif; ?>

    <nav class="member-console-tabs" aria-label="会员管理切换">
        <a class="<?php echo $currentUserPanel === 'members' ? 'is-active' : ''; ?>" href="<?php echo e($memberPanelUrl('members')); ?>">会员列表</a>
        <a class="<?php echo $currentUserPanel === 'consumption' ? 'is-active' : ''; ?>" href="<?php echo e($memberPanelUrl('consumption')); ?>">积分流水</a>
        <a class="<?php echo $currentUserPanel === 'register_rules' ? 'is-active' : ''; ?>" href="<?php echo e($memberPanelUrl('register_rules')); ?>">注册规则设置</a>
    </nav>

    <section class="member-console-grid">
        <?php if ($currentUserPanel === 'register_rules'): ?>
        <article class="member-console-card ui-admin-card is-register-bonus">
            <div class="member-console-head">
                <span class="member-console-pill is-blue">注册规则设置</span>
                <strong>赠送积分 / IP设备限制</strong>
            </div>
            <form class="member-register-bonus-form" method="post" action="<?php echo e(public_url('admin.php') . '?page=users'); ?>" data-confirm="确认保存注册规则设置吗？">
                <input type="hidden" name="_token" value="<?php echo e(csrf_token('admin.users')); ?>">
                <input type="hidden" name="_admin_form" value="page">
                <input type="hidden" name="_admin_action" value="save_register_rules">
                <input type="hidden" name="user_panel" value="register_rules">
                <input type="hidden" name="keyword" value="<?php echo e($currentKeyword); ?>">
                <input type="hidden" name="role_key" value="<?php echo e($currentRole); ?>">
                <input type="hidden" name="status" value="<?php echo e($currentStatus); ?>">
                <input type="hidden" name="reset_status" value="<?php echo e($currentResetStatus); ?>">
                <label>
                    <span>新注册赠送积分</span>
                    <input type="number" name="register_bonus" value="<?php echo e((string) $registerBonus); ?>" min="0" max="100000000" step="1" inputmode="numeric" required<?php echo $userCanManage ? '' : ' readonly'; ?>>
                </label>
                <label>
                    <span>邀请好友注册奖励</span>
                    <input type="number" name="invite_register_bonus" value="<?php echo e((string) $inviteRegisterBonus); ?>" min="0" max="100000000" step="1" inputmode="numeric" required<?php echo $userCanManage ? '' : ' readonly'; ?>>
                </label>
                <label>
                    <span>IP/设备重复注册限制</span>
                    <input type="number" name="register_limit_days" value="<?php echo e((string) $registerLimitDays); ?>" min="0" max="365" step="1" inputmode="numeric" required<?php echo $userCanManage ? '' : ' readonly'; ?>>
                </label>
                <?php if ($userCanManage): ?>
                    <button class="ui-admin-btn ui-admin-btn-primary" type="submit">保存注册规则</button>
                <?php endif; ?>
            </form>
        </article>
        <?php endif; ?>

        <?php if ($currentUserPanel === 'members'): ?>
        <article class="member-console-card ui-admin-card is-list">
            <div class="member-console-head">
                <span class="member-console-pill is-green">会员列表</span>
                <strong>筛选、查看、调整</strong>
            </div>

            <div class="member-list-toolbar ui-admin-toolbar ui-admin-actions">
                <?php if ($users && $userCanManage): ?>
                    <form id="member-batch-delete-form" class="member-batch-delete-form" method="post" action="<?php echo e(public_url('admin.php') . '?page=users'); ?>" data-confirm="确认删除选中的会员吗？删除后不可恢复。">
                        <input type="hidden" name="_token" value="<?php echo e(csrf_token('admin.users')); ?>">
                        <input type="hidden" name="_admin_form" value="page">
                        <input type="hidden" name="_admin_action" value="batch_delete_users">
                        <input type="hidden" name="user_panel" value="members">
                        <input type="hidden" name="keyword" value="<?php echo e($currentKeyword); ?>">
                        <input type="hidden" name="role_key" value="<?php echo e($currentRole); ?>">
                        <input type="hidden" name="status" value="<?php echo e($currentStatus); ?>">
                        <input type="hidden" name="reset_status" value="<?php echo e($currentResetStatus); ?>">
                        <button class="is-danger ui-admin-btn ui-admin-btn-danger" type="submit">批量删除会员</button>
                    </form>
                <?php endif; ?>

                <form class="member-filter-bar" method="get" action="<?php echo e(public_url('admin.php')); ?>">
                    <input type="hidden" name="page" value="users">
                    <input type="hidden" name="user_panel" value="members">
                    <label>
                        <span>关键词</span>
                        <div class="member-search-field">
                            <input name="keyword" value="<?php echo e($currentKeyword); ?>" placeholder="用户名 / 邮箱 / 简介">
                            <button class="ui-admin-btn ui-admin-btn-primary" type="submit">搜索</button>
                        </div>
                    </label>
                </form>
            </div>

            <?php if ($users): ?>
                <div class="member-table-wrap ui-admin-table-wrap">
                    <table class="member-table ui-admin-table">
                        <thead>
                        <tr>
                            <?php if ($userCanManage): ?>
                                <th class="member-bulk-check-cell">
                                    <label class="member-bulk-check">
                                        <input type="checkbox" data-member-bulk-toggle>
                                        <span>全选</span>
                                    </label>
                                </th>
                            <?php endif; ?>
                            <th class="member-account-column">会员昵称</th>
                            <th>剩余积分</th>
                            <th>状态</th>
                            <th>注册信息</th>
                            <th>操作</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($users as $row): ?>
                            <?php
                            $rowId = (int) ($row['id'] ?? 0);
                            $rowStatus = (string) ($row['status'] ?? 'disabled');
                            $editModalId = 'member-edit-modal-' . $rowId;
                            $rechargeModalId = 'member-recharge-modal-' . $rowId;
                            $rowUsername = (string) ($row['username'] ?? '');
                            $rowAvatarText = $rowUsername !== '' ? mb_substr($rowUsername, 0, 1, 'UTF-8') : '会';
                            $rowVipName = (string) (($row['active_vip_name'] ?? '') ?: '站点VIP');
                            $rowVipLevelCode = (string) (($row['active_vip_level_code'] ?? '') ?: 'vip');
                            $rowVipStartAt = $dateTimeLocalValue($row['active_vip_start_at'] ?? '');
                            $rowVipExpireAt = $dateTimeLocalValue($row['active_vip_expire_at'] ?? '');
                            $rowRecoveryLabel = !empty($row['recovery_answer_hash']) ? '已设置' : '未设置';
                            $rowRegisteredRecoveryAnswer = trim((string) ($row['registered_recovery_answer'] ?? ''));
                            if ($rowRegisteredRecoveryAnswer === '') {
                                $rowRegisteredRecoveryAnswer = !empty($row['recovery_answer_hash']) ? '历史账号未记录原文' : '未设置';
                            }
                            $rowResetNote = (string) (($row['latest_reset_note'] ?? '') ?: '暂无找回申请');
                            $rowResetStatusLabel = $resetStatusLabel((string) ($row['latest_reset_status'] ?? ''));
                            $rowResetTime = format_datetime($row['latest_reset_created_at'] ?? null);
                            $rowRegisterTime = format_datetime($row['created_at'] ?? null);
                            $rowRegisterSource = trim((string) ($row['register_area_label'] ?? ''));
                            if ($rowRegisterSource === '') {
                                $rowRegisterProvince = trim((string) ($row['register_province'] ?? '未知省份'));
                                $rowRegisterCity = trim((string) ($row['register_city'] ?? '未知城市'));
                                $rowRegisterCarrier = trim((string) ($row['register_carrier'] ?? ''));
                                if ($rowRegisterCarrier === '') {
                                    $rowRegisterCarrier = $resolveRegisterCarrierFromIp($row);
                                }
                                $rowRegisterSource = ($rowRegisterProvince !== '' ? $rowRegisterProvince : '未知省份') . ' / ' . ($rowRegisterCity !== '' ? $rowRegisterCity : '未知城市') . ' / ' . $rowRegisterCarrier;
                            }
                            if ($registerSourceUnknown($rowRegisterSource)) {
                                $resolvedRegisterSource = $resolveRegisterSourceFromIp($row);
                                if ($resolvedRegisterSource !== '') {
                                    $rowRegisterSource = $resolvedRegisterSource;
                                }
                            }
                            if (substr_count($rowRegisterSource, '/') < 2) {
                                $rowRegisterCarrier = trim((string) ($row['register_carrier'] ?? ''));
                                if ($rowRegisterCarrier === '') {
                                    $rowRegisterCarrier = $resolveRegisterCarrierFromIp($row);
                                }
                                $rowRegisterSource .= ' / ' . $rowRegisterCarrier;
                            }
                            $rowRegisterSourceParts = preg_split('/\s*\/\s*/u', $rowRegisterSource);
                            $rowRegisterProvinceLabel = trim((string) ($rowRegisterSourceParts[0] ?? '未知省份'));
                            $rowRegisterCityLabel = trim((string) ($rowRegisterSourceParts[1] ?? '未知城市'));
                            $rowRegisterCarrierLabel = trim((string) ($rowRegisterSourceParts[2] ?? '未知运营商'));
                            ?>
                            <tr>
                                <?php if ($userCanManage): ?>
                                    <td class="member-bulk-check-cell">
                                        <label class="member-bulk-check">
                                            <input type="checkbox" name="target_ids[]" value="<?php echo e((string) $rowId); ?>" form="member-batch-delete-form" data-member-bulk-item>
                                        </label>
                                    </td>
                                <?php endif; ?>
                                <td class="member-account-column">
                                    <div class="member-account-cell">
                                        <span>ID <?php echo e((string) $rowId); ?></span>
                                        <div>
                                            <strong><?php echo e($rowUsername); ?></strong>
                                            <small><?php echo e((string) ($row['role_name'] ?? '-')); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="member-score-cell">
                                        <strong><?php echo e(number_format((int) ($row['score'] ?? 0))); ?></strong>
                                        <?php if ($userCanManage): ?>
                                            <button class="ui-admin-btn" type="button" data-member-recharge-open="<?php echo e($rechargeModalId); ?>">调分</button>
                                            <div class="member-recharge-modal admin-modal" id="<?php echo e($rechargeModalId); ?>" data-member-recharge-modal hidden>
                                                <div class="member-recharge-backdrop admin-modal-backdrop" data-member-recharge-close></div>
                                                <div class="member-recharge-card admin-modal-card admin-modal-card--sm" role="dialog" aria-modal="true" aria-labelledby="<?php echo e($rechargeModalId); ?>-title">
                                                    <div class="member-recharge-head admin-modal-head">
                                                        <div class="admin-modal-heading">
                                                            <div class="admin-modal-title-row">
                                                                <h2 class="admin-modal-title" id="<?php echo e($rechargeModalId); ?>-title">调整积分</h2>
                                                            </div>
                                                        </div>
                                                        <div class="admin-modal-head-actions">
                                                            <button class="ui-admin-btn admin-modal-close" type="button" aria-label="关闭调整积分" data-member-recharge-close>
                                                                <i class="fa-solid fa-xmark"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <form class="member-recharge-form admin-modal-body" method="post" action="<?php echo e(public_url('admin.php') . '?page=users'); ?>" data-confirm="确认调整该会员积分吗？">
                                                        <input type="hidden" name="_token" value="<?php echo e(csrf_token('admin.users')); ?>">
                                                        <input type="hidden" name="_admin_form" value="page">
                                                        <input type="hidden" name="_admin_action" value="score_user">
                                                        <input type="hidden" name="target_id" value="<?php echo e((string) $rowId); ?>">
                                                        <input type="hidden" name="user_panel" value="members">
                                                        <input type="hidden" name="keyword" value="<?php echo e($currentKeyword); ?>">
                                                        <input type="hidden" name="role_key" value="<?php echo e($currentRole); ?>">
                                                        <input type="hidden" name="status" value="<?php echo e($currentStatus); ?>">
                                                        <input type="hidden" name="reset_status" value="<?php echo e($currentResetStatus); ?>">
                                                        <div class="member-recharge-summary">
                                                            <div class="member-recharge-account-row">
                                                                <span class="member-recharge-avatar"><?php echo e($rowAvatarText); ?></span>
                                                                <span>账号</span>
                                                                <strong><?php echo e($rowUsername); ?></strong>
                                                            </div>
                                                            <div class="member-recharge-current-row">
                                                                <span>积分</span>
                                                                <strong><?php echo e((string) ((int) ($row['score'] ?? 0))); ?></strong>
                                                            </div>
                                                        </div>
                                                        <label class="member-recharge-field">
                                                            <span>变动积分</span>
                                                            <input
                                                                type="number"
                                                                name="score_amount"
                                                                value="10"
                                                                min="-100000000"
                                                                max="100000000"
                                                                step="1"
                                                                inputmode="numeric"
                                                                placeholder="正数为充值，负数为扣减"
                                                                required
                                                                data-member-recharge-input
                                                            >
                                                        </label>
                                                        <label class="member-recharge-field">
                                                            <span>变动说明</span>
                                                            <input
                                                                name="score_note"
                                                                value=""
                                                                maxlength="80"
                                                                placeholder="例如：后台充值、活动补发、人工扣减"
                                                            >
                                                        </label>
                                                        <div class="member-recharge-actions">
                                                            <button class="ui-admin-btn" type="button" data-member-recharge-close>取消</button>
                                                            <button class="ui-admin-btn ui-admin-btn-primary" type="submit">确认调整</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><em class="<?php echo $rowStatus === 'active' ? 'is-active' : 'is-disabled'; ?>"><?php echo e($statusLabel($rowStatus)); ?></em></td>
                                <td>
                                    <div class="member-login-cell">
                                        <strong><?php echo e($rowRegisterTime); ?></strong>
                                        <small class="member-register-source">
                                            <span class="is-province"><?php echo e($rowRegisterProvinceLabel !== '' ? $rowRegisterProvinceLabel : '未知省份'); ?></span>
                                            <span class="is-separator">/</span>
                                            <span class="is-city"><?php echo e($rowRegisterCityLabel !== '' ? $rowRegisterCityLabel : '未知城市'); ?></span>
                                            <span class="is-separator">/</span>
                                            <span class="is-carrier"><?php echo e($rowRegisterCarrierLabel !== '' ? $rowRegisterCarrierLabel : '未知运营商'); ?></span>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <div class="member-row-actions">
                                        <button class="ui-admin-btn" type="button" data-member-edit-open="<?php echo e($editModalId); ?>">编辑</button>
                                        <?php if ($userCanManage): ?>
                                            <form method="post" action="<?php echo e(public_url('admin.php') . '?page=users'); ?>" data-confirm="确认删除该会员吗？删除后不可恢复。">
                                                <input type="hidden" name="_token" value="<?php echo e(csrf_token('admin.users')); ?>">
                                                <input type="hidden" name="_admin_form" value="page">
                                                <input type="hidden" name="_admin_action" value="delete_user">
                                                <input type="hidden" name="target_id" value="<?php echo e((string) $rowId); ?>">
                                                <input type="hidden" name="user_panel" value="members">
                                                <input type="hidden" name="keyword" value="<?php echo e($currentKeyword); ?>">
                                                <button class="is-danger ui-admin-btn ui-admin-btn-danger" type="submit">删除</button>
                                            </form>
                                        <?php endif; ?>

                                        <div class="member-edit-modal admin-modal" id="<?php echo e($editModalId); ?>" data-member-edit-modal hidden>
                                            <div class="member-edit-backdrop admin-modal-backdrop" data-member-edit-close></div>
                                            <div class="member-edit-card admin-modal-card admin-modal-card--xl" role="dialog" aria-modal="true" aria-labelledby="<?php echo e($editModalId); ?>-title">
                                                <div class="member-edit-head admin-modal-head">
                                                    <div class="admin-modal-heading">
                                                        <div class="admin-modal-title-row">
                                                            <strong class="admin-modal-title" id="<?php echo e($editModalId); ?>-title">编辑会员</strong>
                                                        </div>
                                                        <span class="admin-modal-subtitle">ID <?php echo e((string) $rowId); ?> / <?php echo e((string) ($row['username'] ?? '')); ?></span>
                                                    </div>
                                                    <div class="admin-modal-head-actions">
                                                        <button class="ui-admin-btn admin-modal-close" type="button" aria-label="关闭编辑弹窗" data-member-edit-close>×</button>
                                                    </div>
                                                </div>
                                                <div class="member-edit-body admin-modal-body">
                                                    <?php if ($userCanManage): ?>
                                                        <form class="member-edit-form" method="post" action="<?php echo e(public_url('admin.php') . '?page=users'); ?>">
                                                            <input type="hidden" name="_token" value="<?php echo e(csrf_token('admin.users')); ?>">
                                                            <input type="hidden" name="_admin_form" value="page">
                                                            <input type="hidden" name="_admin_action" value="save_user">
                                                            <input type="hidden" name="return_to_list" value="1">
                                                            <input type="hidden" name="user_panel" value="members">
                                                            <input type="hidden" name="id" value="<?php echo e((string) $rowId); ?>">
                                                            <input type="hidden" name="keyword" value="<?php echo e($currentKeyword); ?>">
                                                            <input type="hidden" name="email" value="<?php echo e((string) ($row['email'] ?? '')); ?>">
                                                            <input type="hidden" name="bio" value="<?php echo e((string) ($row['bio'] ?? '')); ?>">
                                                            <div class="member-edit-section-title">基础编辑</div>
                                                            <label>
                                                                <span>用户名</span>
                                                                <input name="username" value="<?php echo e((string) ($row['username'] ?? '')); ?>" required>
                                                            </label>
                                                            <label>
                                                                <span>会员角色</span>
                                                                <select name="role_key">
                                                                    <?php foreach ($userRoles as $role): ?>
                                                                        <option value="<?php echo e((string) $role['role_key']); ?>" <?php echo (string) ($row['role_key'] ?? 'member') === (string) $role['role_key'] ? 'selected' : ''; ?>>
                                                                            <?php echo e((string) $role['role_name']); ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </label>
                                                            <label>
                                                                <span>剩余积分</span>
                                                                <input type="number" name="score" value="<?php echo e((string) ((int) ($row['score'] ?? 0))); ?>" step="1">
                                                            </label>
                                                            <label>
                                                                <span>累计充值积分</span>
                                                                <strong><?php echo e(number_format((int) ($row['recharge_score_total'] ?? 0))); ?> 积分</strong>
                                                            </label>
                                                            <label>
                                                                <span>会员状态</span>
                                                                <select name="status">
                                                                    <option value="active" <?php echo $rowStatus === 'active' ? 'selected' : ''; ?>>正常</option>
                                                                    <option value="disabled" <?php echo $rowStatus === 'disabled' ? 'selected' : ''; ?>>禁用</option>
                                                                </select>
                                                            </label>
                                                            <label>
                                                                <span>修改密码</span>
                                                                <input class="member-edit-password-input" type="password" name="password" placeholder="留空不修改">
                                                            </label>
                                                            <label class="is-full">
                                                                <span class="member-edit-subtitle">找回验证信息</span>
                                                                <div class="member-edit-info-grid member-edit-recovery-grid">
                                                                    <div><small>验证状态</small><strong><?php echo e($rowRecoveryLabel); ?></strong></div>
                                                                    <div><small>最近申请</small><strong><?php echo e($rowResetStatusLabel); ?></strong></div>
                                                                    <div><small>申请时间</small><strong><?php echo e($rowResetTime); ?></strong></div>
                                                                    <div><small>注册验证信息</small><strong><?php echo e($rowRegisteredRecoveryAnswer); ?></strong></div>
                                                                    <div class="is-wide"><small>申请内容</small><strong><?php echo e($rowResetNote); ?></strong></div>
                                                                </div>
                                                            </label>
                                                            <div class="member-edit-actions ui-admin-actions">
                                                                <button class="ui-admin-btn ui-admin-btn-primary" type="submit">保存</button>
                                                            </div>
                                                        </form>

                                                        <form class="member-edit-form is-vip" method="post" action="<?php echo e(public_url('admin.php') . '?page=users'); ?>">
                                                            <input type="hidden" name="_token" value="<?php echo e(csrf_token('admin.users')); ?>">
                                                            <input type="hidden" name="_admin_form" value="page">
                                                            <input type="hidden" name="_admin_action" value="save_vip">
                                                            <input type="hidden" name="return_to_list" value="1">
                                                            <input type="hidden" name="user_panel" value="members">
                                                            <input type="hidden" name="target_user_id" value="<?php echo e((string) $rowId); ?>">
                                                            <input type="hidden" name="keyword" value="<?php echo e($currentKeyword); ?>">
                                                            <div class="member-edit-section-title">VIP管理设置</div>
                                                            <label>
                                                                <span>VIP名称</span>
                                                                <input name="vip_name" value="<?php echo e($rowVipName); ?>">
                                                            </label>
                                                            <label>
                                                                <span>等级标识</span>
                                                                <input name="vip_level_code" value="<?php echo e($rowVipLevelCode); ?>">
                                                            </label>
                                                            <label>
                                                                <span>开始时间</span>
                                                                <input type="datetime-local" name="vip_start_at" value="<?php echo e($rowVipStartAt); ?>">
                                                            </label>
                                                            <label>
                                                                <span>到期时间</span>
                                                                <input type="datetime-local" name="vip_expire_at" value="<?php echo e($rowVipExpireAt); ?>">
                                                            </label>
                                                            <label class="is-full">
                                                                <span>备注</span>
                                                                <textarea name="vip_remark"></textarea>
                                                            </label>
                                                            <div class="member-edit-actions ui-admin-actions">
                                                                <button class="ui-admin-btn ui-admin-btn-primary" type="submit">保存VIP</button>
                                                            </div>
                                                        </form>
                                                    <?php else: ?>
                                                        <div class="member-console-empty">当前账号不能编辑会员。</div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($userPageCount > 1): ?>
                    <div class="admin-pagination">
                        <div class="admin-pagination-meta">共 <?php echo e((string) $userTotalCount); ?> 个会员，第 <?php echo e((string) $userPageNo); ?> / <?php echo e((string) $userPageCount); ?> 页</div>
                        <div class="admin-pagination-links">
                            <?php if ($userPageNo > 1): ?>
                                <a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?' . http_build_query(array_merge($memberPaginationQueryBase, array('page_no' => $userPageNo - 1)))); ?>">上一页</a>
                            <?php endif; ?>
                            <?php if ($userPageNo < $userPageCount): ?>
                                <a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?' . http_build_query(array_merge($memberPaginationQueryBase, array('page_no' => $userPageNo + 1)))); ?>">下一页</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="member-console-empty">当前没有符合条件的会员。</div>
            <?php endif; ?>
        </article>
        <?php endif; ?>

        <?php if ($currentUserPanel === 'consumption'): ?>
        <article class="member-console-card ui-admin-card is-consumption">
            <div class="member-console-head">
                <span class="member-console-pill is-blue">积分流水</span>
                <strong>会员积分变动</strong>
            </div>
            <div class="admin-tip">积分流水记录后台和接待端对会员积分的充值、扣减；累计充值积分统计当前会员账户上的累计充值字段，不等同于全历史流水合计。</div>

            <?php if ($consumptionRecords): ?>
                <div class="member-consumption-wrap ui-admin-table-wrap">
                    <table class="member-consumption-table ui-admin-table">
                        <thead>
                        <tr>
                            <th>会员昵称</th>
                            <th>积分变动</th>
                            <th>变动后积分</th>
                            <th>状态</th>
                            <th>来源</th>
                            <th>变动时间</th>
                            <th>变动说明</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($consumptionRecords as $record): ?>
                            <?php
                            $recordAmount = (int) ($record['score_amount'] ?? 0);
                            $recordAmountText = ($recordAmount > 0 ? '+' : '') . number_format($recordAmount);
                            $recordScoreAfter = $record['score_after'] ?? null;
                            $recordScoreAfterText = $recordScoreAfter === null ? '--' : number_format((int) $recordScoreAfter);
                            $recordStatus = (string) ($record['status'] ?? '成功');
                            $recordSource = (string) (($record['source_label'] ?? '') ?: '后台调分');
                            $recordSourceClass = strpos($recordSource, '接待端') !== false ? 'is-service' : 'is-admin';
                            ?>
                            <tr>
                                <td>
                                    <div class="member-consumption-account">
                                        <strong><?php echo e((string) ($record['username'] ?? '会员已删除')); ?></strong>
                                        <small>ID <?php echo e((string) ((int) ($record['user_id'] ?? 0))); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <span class="member-consumption-score <?php echo $recordAmount < 0 ? 'is-minus' : 'is-plus'; ?>">
                                        <?php echo e($recordAmountText); ?>
                                    </span>
                                </td>
                                <td><?php echo e($recordScoreAfterText); ?></td>
                                <td><em class="<?php echo $recordStatus === '成功' ? 'is-active' : 'is-disabled'; ?>"><?php echo e($recordStatus); ?></em></td>
                                <td><span class="member-consumption-source <?php echo e($recordSourceClass); ?>"><?php echo e($recordSource); ?></span></td>
                                <td><?php echo e(format_datetime($record['created_at'] ?? null)); ?></td>
                                <td><?php echo e((string) ($record['note'] ?? '-')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="member-console-empty">暂无会员积分流水。</div>
            <?php endif; ?>
        </article>
        <?php endif; ?>
    </section>
</section>
<?php if ($currentUserPanel === 'members' && $userCanManage && $users): ?>
    <script>
    (function () {
        var toggle = document.querySelector('[data-member-bulk-toggle]');
        var items = Array.prototype.slice.call(document.querySelectorAll('[data-member-bulk-item]'));

        if (!toggle || !items.length) {
            return;
        }

        function refreshToggleState() {
            var checkedCount = items.filter(function (item) {
                return item.checked;
            }).length;

            toggle.checked = checkedCount === items.length;
            toggle.indeterminate = checkedCount > 0 && checkedCount < items.length;
        }

        toggle.addEventListener('change', function () {
            items.forEach(function (item) {
                item.checked = toggle.checked;
            });
            refreshToggleState();
        });

        items.forEach(function (item) {
            item.addEventListener('change', refreshToggleState);
        });
    }());
    </script>
<?php endif; ?>
