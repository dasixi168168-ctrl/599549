<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/front_security.php';
$isModalRequest = (string) ($_GET['modal'] ?? '') === '1';
front_security_apply($isModalRequest ? array(
    'rate_limit' => false,
    'cache_control' => 'no-store, no-cache, must-revalidate, max-age=0',
    'legacy_no_cache_headers' => true,
) : array());

require dirname(__DIR__) . '/bootstrap/app.php';

ensure_installed_or_redirect();

$adminHistoryEmbed = $isModalRequest && (string) ($_GET['admin_history'] ?? '') === '1';
if ($adminHistoryEmbed) {
    app()->auth()->requireAdminPortal('posts.view', public_url('admin.php'));
}

$postId = (int) input('id', 0);
$isModal = $isModalRequest;
if (!$isModal) {
    run_housekeeping();
}

$post = app()->posts()->findPost($postId);

if (!$post) {
    abort(404, '帖子不存在或已删除');
}

$region = $post['region'] === 'hongkong' ? 'hongkong' : 'macau';

if ($isModal) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

if (!$isModal) {
    redirect(($region === 'hongkong' ? public_url('record.php') : public_url('index.php')) . '?open_post=' . $postId);
}

$viewer = current_user();
if ($viewer) {
    app()->users()->ensureMembershipSchema();
}
if (!$adminHistoryEmbed) {
    app()->posts()->registerRealView($postId, $viewer ?: array());
}
$displayViewCount = app()->posts()->currentDisplayedViewCount($postId);
$displayTitle = app()->posts()->displayTitle($post);
$displayTitleHtml = app()->posts()->displayTitleHtml($post);
$displayContent = app()->posts()->visibleContent($post, $viewer);
$hasFullAccess = !$viewer ? false : (
    (int) $viewer['id'] === (int) $post['author_id']
    || app()->posts()->hasPurchased($postId, $viewer['id'])
);
$salePostOpenedForPublic = (int) $post['price'] > 0 && !$hasFullAccess && trim($displayContent) === trim((string) ($post['full_content'] ?? ''));
$purchaseNeeded = (int) $post['price'] > 0 && !$hasFullAccess && !$salePostOpenedForPublic;
$customerServicePostEditAgent = null;
try {
    $customerServicePostEditAgent = app()->support()->currentAgent();
} catch (Throwable $exception) {
    $customerServicePostEditAgent = null;
}
$canCustomerServiceEditPost = is_array($customerServicePostEditAgent);
$customerServiceAgentViewer = (
    (string) input('agent', '0') === '1'
    || (string) \App\Core\Session::get('customer_service_agent_entry', '0') === '1'
) && $canCustomerServiceEditPost;
$customerServiceAgentFreeAccess = $customerServiceAgentViewer
    && isset($_SESSION['customer_service_agent_free_post_access'][$postId]);
if ($customerServiceAgentFreeAccess) {
    $displayContent = (string) ($post['full_content'] ?? '');
    $hasFullAccess = true;
    $salePostOpenedForPublic = (int) $post['price'] > 0;
    $purchaseNeeded = false;
}
$customerServiceEditPayload = $canCustomerServiceEditPost
    ? app()->posts()->customerServiceEditPayload($post, app()->posts()->displayIssuePrefixText($post))
    : array();
$authorBio = trim((string) ($post['author_bio'] ?? ''));
$authorActivityText = '';
$authorFirstPostAt = trim((string) ($post['author_first_post_at'] ?? ''));
$viewerId = $viewer ? (int) $viewer['id'] : 0;
$postLikeCount = app()->posts()->currentDisplayedLikeCount($postId);
$postLikedByViewer = app()->posts()->userLikedPost($postId, $viewer ?: array());
$replies = app()->posts()->listReplies($postId, $viewer ?: array());
$replyTotal = 0;
$replyCounter = static function (array $items) use (&$replyCounter, &$replyTotal) {
    foreach ($items as $item) {
        $replyTotal++;
        if (!empty($item['children']) && is_array($item['children'])) {
            $replyCounter($item['children']);
        }
    }
};
$replyCounter($replies);
$canUsePostInteraction = $viewer ? app()->users()->canUsePostInteraction($viewer) : false;
$commentLimit = $viewer ? app()->users()->commentLimitFor($viewer) : 0;
$commentTodayCount = $viewer ? app()->users()->todayCommentCount($viewerId) : 0;
$canComment = $viewer && $commentLimit !== 0 && ($commentLimit < 0 || $commentTodayCount < $commentLimit);
$commentPermissionText = '请先登录后再评论。';

if ($viewer) {
    if (!$canUsePostInteraction) {
        $commentPermissionText = '当前账号不是会员角色，不能评论。';
    } elseif ($commentLimit === 0) {
        $commentPermissionText = '当前会员等级暂未开放评论权限。';
    } elseif ($commentLimit > 0 && $commentTodayCount >= $commentLimit) {
        $commentPermissionText = '今日评论次数已达上限。';
    } else {
        $commentPermissionText = $commentLimit < 0
            ? '当前会员等级评论次数不限。'
            : '今日还可评论 ' . max(0, $commentLimit - $commentTodayCount) . ' 条。';
    }
}

if ($authorFirstPostAt !== '') {
    try {
        $firstPostDate = new DateTimeImmutable($authorFirstPostAt);
        $now = new DateTimeImmutable('now');

        if ($firstPostDate > $now) {
            $firstPostDate = $now;
        }

        $diff = $firstPostDate->diff($now);
        $durationParts = array();

        if ((int) $diff->y > 0) {
            $durationParts[] = (string) ((int) $diff->y) . '年';
        }

        if ((int) $diff->m > 0) {
            $durationParts[] = (string) ((int) $diff->m) . '个月';
        }

        if (empty($durationParts) && (int) $diff->d > 0) {
            $durationParts[] = (string) ((int) $diff->d) . '天';
        }

        if (!empty($durationParts)) {
            $authorActivityText = '已在本版持续发帖 ' . implode('', array_slice($durationParts, 0, 2));
        } else {
            $authorActivityText = '刚刚开始在本版发帖';
        }
    } catch (Exception $exception) {
        $authorActivityText = '';
    }
}

view('front/post_detail', array(
    'pageTitle' => $displayTitle . ' - ' . browser_title_setting('888888论坛'),
    'pageDescription' => $post['excerpt'],
    'bodyClass' => 'standalone-panel'
        . ($isModal ? ' standalone-modal-post' : '')
        . ($adminHistoryEmbed ? ' admin-history-embed' : ''),
    'region' => $region,
    'post' => $post,
    'displayTitle' => $displayTitle,
    'displayTitleHtml' => is_array($displayTitleHtml) ? (string) ($displayTitleHtml['html'] ?? '') : '',
    'viewer' => $viewer,
    'displayContent' => $displayContent,
    'salePostOpenedForPublic' => $salePostOpenedForPublic,
    'displayViewCount' => $displayViewCount,
    'postLikeCount' => $postLikeCount,
    'postLikedByViewer' => $postLikedByViewer,
    'authorBio' => $authorBio,
    'authorActivityText' => $authorActivityText,
    'postViewApiToken' => $adminHistoryEmbed ? '' : csrf_token('api'),
    'adminHistoryEmbed' => $adminHistoryEmbed,
    'purchaseNeeded' => $purchaseNeeded,
    'canCustomerServiceEditPost' => $canCustomerServiceEditPost,
    'customerServiceAgentViewer' => $customerServiceAgentViewer,
    'customerServiceEditContent' => (string) ($customerServiceEditPayload['content'] ?? ''),
    'customerServiceEditIssueText' => (string) ($customerServiceEditPayload['issue_text'] ?? ''),
    'replies' => $replies,
    'replyTotal' => $replyTotal,
    'canUsePostInteraction' => $canUsePostInteraction,
    'canComment' => $canComment,
    'commentLimit' => $commentLimit,
    'commentTodayCount' => $commentTodayCount,
    'commentPermissionText' => $commentPermissionText,
    'commentThreadApiUrl' => public_url('api.php'),
    'commentThreadLoginUrl' => public_url('member.php') . '?region=' . urlencode($region) . '&mode=login',
    'backUrl' => $region === 'hongkong' ? public_url('record.php') : public_url('index.php'),
    'postTopHtml' => site_setting('appearance.post_top_html', ''),
    'postBottomHtml' => site_setting('appearance.post_bottom_html', ''),
), 'layouts/home_legacy');
