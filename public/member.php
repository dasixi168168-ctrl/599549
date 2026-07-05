<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/front_security.php';
front_security_apply();

require dirname(__DIR__) . '/bootstrap/app.php';

ensure_installed_or_redirect();
run_housekeeping();

$region = (string) input('region', 'macau');
if (!in_array($region, array('macau', 'hongkong'), true)) {
    $region = 'macau';
}

if (input('logout')) {
    if (is_prefetch_request()) {
        http_response_code(204);
        exit;
    }

    app()->auth()->logout();
    redirect($region === 'hongkong' ? public_url('record.php') : public_url('index.php'));
}

track_page($region === 'hongkong' ? 'front_member_hongkong' : 'front_member_macau');

$mode = (string) input('mode', 'login');
if (!in_array($mode, array('login', 'register', 'reset'), true)) {
    $mode = 'login';
}
$inviteCode = trim((string) input('invite', ''));

$user = current_user();
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
}
$predictionLogs = array();
$predictionResultDraws = array();
$purchaseRecords = array();
$memberRechargePaymentSettings = array();
$memberTab = (string) input('tab', 'ai');
if ($memberTab === 'recharge') {
    $memberTab = 'about';
}
if (!in_array($memberTab, array('ai', 'purchases', 'about', 'profile'), true)) {
    $memberTab = 'ai';
}

// 会员中心仅加载当前标签需要的数据，避免切换时触发无关查询。
if ($user) {
    try {
        $memberRechargePaymentSettings = app()->support()->paymentSettings();
    } catch (\Throwable $exception) {
        $memberRechargePaymentSettings = array();
    }

    if ($memberTab === 'ai') {
        try {
            $predictionLogs = app()->prediction()->memberPredictionLogs($user['id'], 12);
        } catch (\Throwable $exception) {
            $predictionLogs = array();
        }

        if (!empty($predictionLogs)) {
            $predictionIssuesByRegion = array(
                'macau' => array(),
                'hongkong' => array(),
            );

            foreach ($predictionLogs as $predictionLog) {
                $predictionRegion = (string) ($predictionLog['region'] ?? '') === 'hongkong'
                    ? 'hongkong'
                    : 'macau';
                $predictionIssueNo = trim((string) ($predictionLog['generated_for_issue'] ?? ''));
                if ($predictionIssueNo !== '') {
                    $predictionIssuesByRegion[$predictionRegion][$predictionIssueNo] = true;
                }
            }

            foreach ($predictionIssuesByRegion as $predictionRegion => $predictionIssues) {
                if (empty($predictionIssues)) {
                    continue;
                }

                $predictionIssueList = array_values(array_keys($predictionIssues));
                $predictionSqlParams = array('region' => $predictionRegion);
                $predictionSqlPlaceholders = array();
                foreach ($predictionIssueList as $predictionIssueIndex => $predictionIssueNo) {
                    $predictionParamName = 'issue_' . $predictionIssueIndex;
                    $predictionSqlPlaceholders[] = ':' . $predictionParamName;
                    $predictionSqlParams[$predictionParamName] = $predictionIssueNo;
                }
                $predictionFetchDrawRows = static function () use ($predictionSqlParams, $predictionSqlPlaceholders) {
                    return db()->fetchAll(
                        'SELECT region, issue_no, numbers_json, special_number
                         FROM lottery_draws
                         WHERE region = :region
                           AND issue_no IN (' . implode(',', $predictionSqlPlaceholders) . ')',
                        $predictionSqlParams
                    );
                };

                try {
                    $predictionDrawRows = $predictionFetchDrawRows();

                    foreach ($predictionDrawRows as $predictionDrawRow) {
                        $predictionDrawRegion = (string) ($predictionDrawRow['region'] ?? '') === 'hongkong'
                            ? 'hongkong'
                            : 'macau';
                        $predictionDrawIssue = trim((string) ($predictionDrawRow['issue_no'] ?? ''));
                        if ($predictionDrawIssue !== '') {
                            if (!isset($predictionResultDraws[$predictionDrawRegion])) {
                                $predictionResultDraws[$predictionDrawRegion] = array();
                            }
                            $predictionResultDraws[$predictionDrawRegion][$predictionDrawIssue] = $predictionDrawRow;
                        }
                    }
                } catch (\Throwable $exception) {
                }
            }
        }
    }

    if ($memberTab === 'purchases') {
        try {
            $purchaseRecords = db()->fetchAll(
                "SELECT purchases.*, posts.title, posts.region, posts.status, posts.price AS current_price
                 FROM purchases
                 INNER JOIN posts ON posts.id = purchases.post_id
                 WHERE purchases.user_id = :user_id
                 ORDER BY purchases.created_at DESC
                 LIMIT 30",
                array('user_id' => $user['id'])
            );
        } catch (\Throwable $exception) {
            $purchaseRecords = array();
        }
    }
}

$pageRegionName = $region === 'hongkong' ? '香港' : '澳门';
$pageHeading = $user ? '会员中心' : ($mode === 'register' ? '会员注册' : ($mode === 'reset' ? '找回密码' : '会员登录'));

view('front/member_portal', array(
    'pageTitle' => browser_title_setting('888888论坛') . ' - ' . browser_region_title_setting($region, $pageRegionName . '论坛') . $pageHeading,
    'pageDescription' => $pageRegionName . '论坛会员中心',
    'bodyClass' => 'standalone-panel front-unified-panel-page member-panel-page'
        . ($user ? ' member-center-page' : ' member-auth-page'),
    'region' => $region,
    'mode' => $mode,
    'inviteCode' => $inviteCode,
    'user' => $user,
    'predictionLogs' => $predictionLogs,
    'predictionResultDraws' => $predictionResultDraws,
    'purchaseRecords' => $purchaseRecords,
    'memberRechargePaymentSettings' => $memberRechargePaymentSettings,
    'memberTab' => $memberTab,
), 'layouts/home_legacy');
