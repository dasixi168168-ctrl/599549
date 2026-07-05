<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/front_security.php';
front_security_apply(array(
    'rate_limit' => false,
));

require dirname(__DIR__) . '/bootstrap/app.php';

ensure_installed_or_redirect();
run_housekeeping();

$requestInput = function ($key, $default = null) {
    if (isset($_POST[$key])) {
        return $_POST[$key];
    }
    if (isset($_GET[$key])) {
        return $_GET[$key];
    }

    return $default;
};

$region = (string) $requestInput('region', 'macau');
if (!in_array($region, array('macau', 'hongkong'), true)) {
    $region = 'macau';
}

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

track_page($region === 'hongkong' ? 'front_service_hongkong' : 'front_service_macau');

$user = current_user();
if (!$user) {
    $user = app()->auth()->restoreUserFromFrontMemberCookie();
}
$customerServiceAgent = null;
$customerServiceMode = 'guest';
$customerServiceStatus = isset($_GET['status']) ? trim((string) $_GET['status']) : 'all';
if (!in_array($customerServiceStatus, array('all', 'unread', 'waiting', 'open', 'closed'), true)) {
    $customerServiceStatus = 'all';
}
$customerServiceSessionId = isset($_GET['session_id']) ? (int) $_GET['session_id'] : 0;
$customerServiceAgentLoginRequested = (string) $requestInput('agent', '0') === '1';
if ($customerServiceAgentLoginRequested) {
    \App\Core\Session::put('customer_service_agent_entry', '1');
}
$customerServiceAgentEntryRemembered = (string) \App\Core\Session::get('customer_service_agent_entry', '0') === '1';
$customerServiceAgentPanel = (string) $requestInput('panel', 'console');
if (!in_array($customerServiceAgentPanel, array('console', 'manage'), true)) {
    $customerServiceAgentPanel = 'console';
}
$customerServicePaymentActiveType = (string) $requestInput('payment_type', 'alipay');
if (!in_array($customerServicePaymentActiveType, array('alipay', 'wechat', 'usdt'), true)) {
    $customerServicePaymentActiveType = 'alipay';
}
$customerServiceEmbed = (string) $requestInput('embed', '0') === '1'
    || (string) $requestInput('modal', '0') === '1';
$customerServiceEmptyPayload = array(
    'session' => null,
    'messages' => array(),
    'emojis' => array(),
    'sessions' => array(),
    'overview' => array(),
);
$customerServicePayload = $customerServiceEmptyPayload;

try {
    $customerServiceAgent = app()->support()->currentAgent();
} catch (\Throwable $exception) {
    $customerServiceAgent = null;
}

if (is_post() && (string) $requestInput('_service_action', '') === 'customer_service.payment_upload') {
    $paymentType = $customerServicePaymentActiveType;
    $paymentUploadField = 'payment_qr_' . $paymentType;
    $paymentHasQrUpload = false;
    if (isset($_FILES[$paymentUploadField]) && is_array($_FILES[$paymentUploadField])) {
        $paymentUploadErrors = isset($_FILES[$paymentUploadField]['error'])
            ? $_FILES[$paymentUploadField]['error']
            : UPLOAD_ERR_NO_FILE;
        $paymentUploadErrorStack = is_array($paymentUploadErrors)
            ? $paymentUploadErrors
            : array($paymentUploadErrors);
        foreach ($paymentUploadErrorStack as $paymentUploadError) {
            if ((int) $paymentUploadError !== UPLOAD_ERR_NO_FILE) {
                $paymentHasQrUpload = true;
                break;
            }
        }
    }

    $paymentReturnUrl = public_url('service.php') . '?' . http_build_query(array(
        'region' => $region,
        'agent' => '1',
        'panel' => 'manage',
        'payment_type' => $paymentType,
        'payment_refresh' => (string) time(),
    ));

    try {
        $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
        if ($contentLength > 0 && empty($_POST) && empty($_FILES)) {
            throw new RuntimeException('上传图片过大，请压缩到 5MB 以内后再上传。');
        }
        if (!\App\Core\Csrf::validate((string) input('_token', ''), 'api')) {
            throw new RuntimeException('表单令牌已失效，请刷新页面后重试。');
        }
        if (!$customerServiceAgent) {
            throw new RuntimeException('请先登录客服接待账号。');
        }

        app()->support()->saveAgentPaymentSettings($customerServiceAgent, $_POST, $_FILES);
        $paymentSuccessMessage = '二维码已上传保存。';
        if (isset($_POST['payment_qr_delete'])) {
            $paymentSuccessMessage = '二维码已删除。';
        } elseif ($paymentType === 'usdt' && array_key_exists('usdt_address', $_POST) && !$paymentHasQrUpload) {
            $paymentSuccessMessage = 'USDT 收款地址已保存。';
        } elseif ($paymentType === 'usdt' && array_key_exists('usdt_address', $_POST)) {
            $paymentSuccessMessage = 'USDT 二维码和收款地址已保存。';
        }
        \App\Core\Session::put('customer_service_payment_flash', array(
            'type' => 'success',
            'message' => $paymentSuccessMessage,
        ));
    } catch (\Throwable $exception) {
        \App\Core\Session::put('customer_service_payment_flash', array(
            'type' => 'error',
            'message' => $exception instanceof RuntimeException
                ? $exception->getMessage()
                : '二维码上传失败，请稍后重试。',
        ));
    }

    redirect($paymentReturnUrl);
}

if ($customerServiceAgent) {
    $customerServiceMode = 'agent';
    try {
        $customerServicePayload = app()->support()->agentPayload($customerServiceSessionId, $customerServiceStatus, $customerServiceAgent, false);
    } catch (\Throwable $exception) {
        $customerServicePayload = $customerServiceEmptyPayload;
    }
} elseif ($user) {
    $customerServiceMode = 'member';
    try {
        $customerServicePayload = app()->support()->memberPayload((int) $user['id']);
    } catch (\Throwable $exception) {
        $customerServicePayload = $customerServiceEmptyPayload;
    }
} elseif ($customerServiceAgentLoginRequested) {
    redirect(public_url('admin.php'));
} else {
    if ($customerServiceAgentEntryRemembered) {
        \App\Core\Session::forget('customer_service_agent_entry');
    }
    try {
        $customerServicePayload['service_profile'] = app()->support()->publicServiceProfile();
        $customerServicePayload['typing_status'] = app()->support()->publicTypingStatus();
    } catch (\Throwable $exception) {
        $customerServicePayload = $customerServiceEmptyPayload;
    }
}

$pageRegionName = $region === 'hongkong' ? '香港' : '澳门';
$customerServicePaymentFlash = \App\Core\Session::get('customer_service_payment_flash');
\App\Core\Session::forget('customer_service_payment_flash');
if (!is_array($customerServicePaymentFlash)) {
    $customerServicePaymentFlash = null;
}

view('front/service', array(
    'pageTitle' => browser_title_setting('888888论坛') . ' - ' . browser_region_title_setting($region, $pageRegionName . '论坛') . '在线客服',
    'pageDescription' => $pageRegionName . '论坛在线客服页面',
    'needsFrontServiceStyle' => true,
    'bodyClass' => 'standalone-panel front-unified-panel-page customer-service-body customer-service-panel-page'
        . ($customerServiceMode === 'agent'
            ? ' customer-service-agent-body'
            : '')
        . ($customerServiceMode === 'member' ? ' customer-service-member-body' : '')
        . ($customerServiceMode === 'guest' ? ' customer-service-guest-body' : '')
        . ($customerServiceMode === 'agent_login' ? ' customer-service-agent-login-body' : '')
        . ($customerServiceEmbed ? ' customer-service-embed-body' : ''),
    'region' => $region,
    'user' => $user,
    'customerServiceAgent' => $customerServiceAgent,
    'customerServiceMode' => $customerServiceMode,
    'customerServiceEmbed' => $customerServiceEmbed,
    'customerServiceAgentPanel' => $customerServiceAgentPanel,
    'customerServiceStatus' => $customerServiceStatus,
    'customerServicePayload' => $customerServicePayload,
    'customerServicePaymentFlash' => $customerServicePaymentFlash,
    'customerServicePaymentActiveType' => $customerServicePaymentActiveType,
), 'layouts/home_legacy');
