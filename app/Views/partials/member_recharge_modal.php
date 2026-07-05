<?php
$memberRechargeUser = isset($memberRechargeUser) && is_array($memberRechargeUser)
    ? $memberRechargeUser
    : (isset($user) && is_array($user) ? $user : null);
if (!$memberRechargeUser) {
    return;
}
$memberRechargeRegion = isset($memberRechargeRegion)
    ? ((string) $memberRechargeRegion === 'hongkong' ? 'hongkong' : 'macau')
    : (isset($region) && (string) $region === 'hongkong' ? 'hongkong' : 'macau');
$memberRechargeUrl = isset($memberRechargeUrl)
    ? (string) $memberRechargeUrl
    : public_url('service.php') . '?region=' . urlencode($memberRechargeRegion);
$memberRechargePaymentSettings = isset($memberRechargePaymentSettings) && is_array($memberRechargePaymentSettings)
    ? $memberRechargePaymentSettings
    : array();
if (empty($memberRechargePaymentSettings)) {
    try {
        $memberRechargePaymentSettings = app()->support()->paymentSettings();
    } catch (\Throwable $exception) {
        $memberRechargePaymentSettings = array();
    }
}
$memberRechargeBlankQr = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';
$memberRechargeQrVersion = isset($memberRechargeQrVersion) ? (string) $memberRechargeQrVersion : (string) time();
$memberRechargeQrSrc = static function ($url) use ($memberRechargeBlankQr, $memberRechargeQrVersion) {
    $url = trim((string) $url);
    if ($url === '') {
        return $memberRechargeBlankQr;
    }

    return $url . (strpos($url, '?') === false ? '?' : '&') . 'qr_v=' . rawurlencode($memberRechargeQrVersion);
};
$memberRechargeStoredQrLists = isset($memberRechargeStoredQrLists) && is_array($memberRechargeStoredQrLists)
    ? $memberRechargeStoredQrLists
    : array();
if (empty($memberRechargeStoredQrLists)) {
    try {
        $memberRechargeStoredQrLists = app()->support()->paymentQrLists();
    } catch (\Throwable $exception) {
        $memberRechargeStoredQrLists = array();
    }
}
$memberRechargeQrLists = isset($memberRechargeQrLists) && is_array($memberRechargeQrLists)
    ? $memberRechargeQrLists
    : array();
$memberRechargeQrListJson = isset($memberRechargeQrListJson) && is_array($memberRechargeQrListJson)
    ? $memberRechargeQrListJson
    : array();
foreach (array('alipay', 'wechat', 'usdt') as $memberRechargeQrType) {
    if (!isset($memberRechargeQrLists[$memberRechargeQrType]) || !is_array($memberRechargeQrLists[$memberRechargeQrType])) {
        $memberRechargeQrList = array();
        if (isset($memberRechargeStoredQrLists[$memberRechargeQrType])
            && is_array($memberRechargeStoredQrLists[$memberRechargeQrType])
        ) {
            foreach ($memberRechargeStoredQrLists[$memberRechargeQrType] as $memberRechargeQrUrl) {
                $memberRechargeQrUrl = trim((string) $memberRechargeQrUrl);
                if ($memberRechargeQrUrl !== '') {
                    $memberRechargeQrList[] = $memberRechargeQrUrl;
                }
            }
        }
        $memberRechargeQrLists[$memberRechargeQrType] = array_values(array_unique($memberRechargeQrList));
    }
    if (!isset($memberRechargeQrListJson[$memberRechargeQrType])) {
        $memberRechargeEncodedQrList = json_encode($memberRechargeQrLists[$memberRechargeQrType], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $memberRechargeQrListJson[$memberRechargeQrType] = is_string($memberRechargeEncodedQrList) ? $memberRechargeEncodedQrList : '[]';
    }
}
$memberRechargeAlipayQr = isset($memberRechargeAlipayQr) ? (string) $memberRechargeAlipayQr : (isset($memberRechargeQrLists['alipay'][0]) ? $memberRechargeQrLists['alipay'][0] : '');
$memberRechargeWechatQr = isset($memberRechargeWechatQr) ? (string) $memberRechargeWechatQr : (isset($memberRechargeQrLists['wechat'][0]) ? $memberRechargeQrLists['wechat'][0] : '');
$memberRechargeUsdtQr = isset($memberRechargeUsdtQr) ? (string) $memberRechargeUsdtQr : (isset($memberRechargeQrLists['usdt'][0]) ? $memberRechargeQrLists['usdt'][0] : '');
$memberRechargeAlipaySrc = isset($memberRechargeAlipaySrc) ? (string) $memberRechargeAlipaySrc : $memberRechargeQrSrc($memberRechargeAlipayQr);
$memberRechargeWechatSrc = isset($memberRechargeWechatSrc) ? (string) $memberRechargeWechatSrc : $memberRechargeQrSrc($memberRechargeWechatQr);
$memberRechargeUsdtSrc = isset($memberRechargeUsdtSrc) ? (string) $memberRechargeUsdtSrc : $memberRechargeQrSrc($memberRechargeUsdtQr);
$memberRechargeUsdtAddress = isset($memberRechargeUsdtAddress)
    ? (string) $memberRechargeUsdtAddress
    : trim((string) (isset($memberRechargePaymentSettings['usdt_address']) ? $memberRechargePaymentSettings['usdt_address'] : ''));
$memberRechargeUsdtAddressText = isset($memberRechargeUsdtAddressText)
    ? (string) $memberRechargeUsdtAddressText
    : ($memberRechargeUsdtAddress !== '' ? $memberRechargeUsdtAddress : '请联系客服获取 USDT 地址');
?>
<div class="member-recharge-modal front-standard-modal" id="member-recharge-modal" data-member-recharge-modal data-api-url="<?php echo e(public_url('api.php')); ?>" data-token="<?php echo e(csrf_token('api')); ?>" hidden>
    <div class="member-recharge-backdrop front-standard-modal-backdrop" data-member-recharge-close></div>
    <section class="member-recharge-dialog front-standard-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="member-recharge-title">
        <div class="member-recharge-head front-standard-modal-head">
            <h2 id="member-recharge-title">选择充值方式</h2>
            <div class="member-recharge-user">
                <strong>账号：<?php echo e((string) $memberRechargeUser['username']); ?></strong>
                <strong>积分：<?php echo e((string) $memberRechargeUser['score']); ?></strong>
            </div>
            <button type="button" class="member-recharge-close" data-member-recharge-close aria-label="关闭充值弹窗">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="member-recharge-body front-standard-modal-body">
            <div class="member-recharge-methods">
                <button
                    type="button"
                    class="member-recharge-method is-alipay"
                    data-member-recharge-method="alipay"
                    aria-controls="member-recharge-panel-alipay"
                    aria-expanded="false"
                >
                    <div class="member-recharge-method-icon"><i class="fa-brands fa-alipay"></i></div>
                    <div class="member-recharge-method-body">
                        <h3>支付宝</h3>
                    </div>
                </button>
                <button
                    type="button"
                    class="member-recharge-method is-wechat"
                    data-member-recharge-method="wechat"
                    aria-controls="member-recharge-panel-wechat"
                    aria-expanded="false"
                >
                    <div class="member-recharge-method-icon"><i class="fa-brands fa-weixin"></i></div>
                    <div class="member-recharge-method-body">
                        <h3>微信</h3>
                    </div>
                </button>
                <button
                    type="button"
                    class="member-recharge-method is-usdt"
                    data-member-recharge-method="usdt"
                    aria-controls="member-recharge-panel-usdt"
                    aria-expanded="false"
                >
                    <div class="member-recharge-method-icon"><i class="fa-solid fa-coins"></i></div>
                    <div class="member-recharge-method-body">
                        <h3>USDT</h3>
                    </div>
                </button>
            </div>
            <div class="member-recharge-qr-drawer" data-member-recharge-drawer hidden>
                <div class="member-recharge-qr-panel<?php echo $memberRechargeAlipayQr === '' ? ' is-qr-missing' : ''; ?>" id="member-recharge-panel-alipay" data-member-recharge-panel="alipay" hidden>
                    <div class="member-recharge-qr-head">
                        <strong>支付宝收款二维码</strong>
                        <span>付款后截图提交转账记录，联系客服确认充值</span>
                        <button type="button" class="member-recharge-qr-refresh" data-member-recharge-qr-refresh aria-label="刷新支付宝收款二维码">
                            <i class="fa-solid fa-rotate-right"></i>
                        </button>
                    </div>
                    <div class="member-recharge-qr-box">
                        <img src="<?php echo e($memberRechargeAlipaySrc); ?>" alt="支付宝充值二维码" loading="lazy" decoding="async" data-member-recharge-qr data-member-recharge-qr-list="<?php echo e($memberRechargeQrListJson['alipay']); ?>" data-member-recharge-qr-index="0" data-member-recharge-qr-base="<?php echo e($memberRechargeAlipayQr); ?>" data-member-recharge-qr-missing="<?php echo $memberRechargeAlipayQr === '' ? '1' : '0'; ?>">
                        <div class="member-recharge-qr-fallback">请联系客服获取支付宝二维码</div>
                    </div>
                </div>
                <div class="member-recharge-qr-panel<?php echo $memberRechargeWechatQr === '' ? ' is-qr-missing' : ''; ?>" id="member-recharge-panel-wechat" data-member-recharge-panel="wechat" hidden>
                    <div class="member-recharge-qr-head">
                        <strong>微信收款二维码</strong>
                        <span>付款后截图提交转账记录，联系客服确认充值</span>
                        <button type="button" class="member-recharge-qr-refresh" data-member-recharge-qr-refresh aria-label="刷新微信收款二维码">
                            <i class="fa-solid fa-rotate-right"></i>
                        </button>
                    </div>
                    <div class="member-recharge-qr-box">
                        <img src="<?php echo e($memberRechargeWechatSrc); ?>" alt="微信充值二维码" loading="lazy" decoding="async" data-member-recharge-qr data-member-recharge-qr-list="<?php echo e($memberRechargeQrListJson['wechat']); ?>" data-member-recharge-qr-index="0" data-member-recharge-qr-base="<?php echo e($memberRechargeWechatQr); ?>" data-member-recharge-qr-missing="<?php echo $memberRechargeWechatQr === '' ? '1' : '0'; ?>">
                        <div class="member-recharge-qr-fallback">请联系客服获取微信二维码</div>
                    </div>
                </div>
                <div class="member-recharge-qr-panel<?php echo $memberRechargeUsdtQr === '' ? ' is-qr-missing' : ''; ?>" id="member-recharge-panel-usdt" data-member-recharge-panel="usdt" hidden>
                    <div class="member-recharge-qr-head">
                        <strong>USDT 收款二维码</strong>
                        <span>付款后截图提交转账记录，联系客服确认充值</span>
                        <button type="button" class="member-recharge-qr-refresh" data-member-recharge-qr-refresh aria-label="刷新 USDT 收款二维码">
                            <i class="fa-solid fa-rotate-right"></i>
                        </button>
                    </div>
                    <div class="member-recharge-usdt-grid">
                        <div class="member-recharge-usdt-qr-column">
                            <div class="member-recharge-qr-box">
                                <img src="<?php echo e($memberRechargeUsdtSrc); ?>" alt="USDT 充值二维码" loading="lazy" decoding="async" data-member-recharge-qr data-member-recharge-qr-list="<?php echo e($memberRechargeQrListJson['usdt']); ?>" data-member-recharge-qr-index="0" data-member-recharge-qr-base="<?php echo e($memberRechargeUsdtQr); ?>" data-member-recharge-qr-missing="<?php echo $memberRechargeUsdtQr === '' ? '1' : '0'; ?>">
                                <div class="member-recharge-qr-fallback">请联系客服获取 USDT 二维码</div>
                            </div>
                        </div>
                        <div class="member-recharge-usdt-address<?php echo $memberRechargeUsdtAddress === '' ? ' is-empty' : ''; ?>" data-member-recharge-usdt-empty="请联系客服获取 USDT 地址">
                            <div class="member-recharge-usdt-title">
                                <span>USDT</span>
                                <span>地址</span>
                            </div>
                            <code><?php echo e($memberRechargeUsdtAddressText); ?></code>
                            <button
                                type="button"
                                class="member-recharge-copy"
                                data-member-recharge-copy="<?php echo e($memberRechargeUsdtAddress); ?>"
                                <?php echo $memberRechargeUsdtAddress === '' ? 'disabled aria-disabled="true"' : ''; ?>
                            >
                                <i class="fa-regular fa-copy"></i>
                                <span>复制地址</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="member-recharge-note">
                <strong>充值说明</strong>
                <span>请勿直接向旧地址转账，每次充值以当前《选择充值方式》页面提供的收款信息为准。付款完成后截图发送提交凭证，等待客服核对无误后充值积分。</span>
            </div>
        </div>
        <a class="member-recharge-service" href="<?php echo e($memberRechargeUrl); ?>" data-member-recharge-service data-no-prefetch>
            <i class="fa-solid fa-headset"></i>
            <span>联系客服确认充值</span>
        </a>
    </section>
</div>
