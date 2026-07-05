CREATE TABLE IF NOT EXISTS roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_key VARCHAR(50) NOT NULL UNIQUE,
    role_name VARCHAR(50) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    permission_key VARCHAR(100) NOT NULL UNIQUE,
    permission_name VARCHAR(100) NOT NULL,
    permission_group VARCHAR(50) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS role_permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uniq_role_permission (role_id, permission_id),
    CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id INT UNSIGNED NOT NULL,
    username VARCHAR(60) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(120) DEFAULT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    bio VARCHAR(255) DEFAULT NULL,
    recovery_answer_hash VARCHAR(255) DEFAULT NULL,
    registered_recovery_answer VARCHAR(255) DEFAULT NULL,
    score INT NOT NULL DEFAULT 0,
    recharge_score_total INT NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    last_login_at DATETIME DEFAULT NULL,
    last_login_ip VARCHAR(64) DEFAULT NULL,
    login_province VARCHAR(50) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS login_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    username VARCHAR(60) NOT NULL,
    role_key VARCHAR(50) DEFAULT NULL,
    login_ip VARCHAR(64) NOT NULL,
    login_province VARCHAR(50) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    login_status VARCHAR(20) NOT NULL DEFAULT 'success',
    created_at DATETIME NOT NULL,
    INDEX idx_login_logs_user (user_id),
    CONSTRAINT fk_login_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS password_reset_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    username VARCHAR(60) NOT NULL,
    note VARCHAR(255) DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL,
    processed_at DATETIME DEFAULT NULL,
    processed_by INT UNSIGNED DEFAULT NULL,
    INDEX idx_reset_status (status),
    CONSTRAINT fk_reset_requests_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_reset_requests_admin FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_ban_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    ban_type VARCHAR(20) NOT NULL DEFAULT 'account',
    reason VARCHAR(255) NOT NULL DEFAULT '',
    start_at DATETIME NOT NULL,
    end_at DATETIME DEFAULT NULL,
    status TINYINT(1) NOT NULL DEFAULT 1,
    banned_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
    unbanned_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
    unbanned_at DATETIME DEFAULT NULL,
    remark VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_user_ban_records_user_status (user_id, status),
    INDEX idx_user_ban_records_end_at (end_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_vips (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    vip_name VARCHAR(50) NOT NULL DEFAULT '',
    level_code VARCHAR(30) NOT NULL DEFAULT '',
    start_at DATETIME NOT NULL,
    expire_at DATETIME NOT NULL,
    source_type VARCHAR(20) NOT NULL DEFAULT 'manual',
    source_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    remark VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_user_vips_user_status (user_id, status),
    INDEX idx_user_vips_expire_at (expire_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS uploads (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_type VARCHAR(30) NOT NULL DEFAULT 'general',
    related_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    file_name VARCHAR(255) NOT NULL DEFAULT '',
    storage_name VARCHAR(255) NOT NULL DEFAULT '',
    file_path VARCHAR(255) NOT NULL DEFAULT '',
    file_ext VARCHAR(20) NOT NULL DEFAULT '',
    mime_type VARCHAR(100) NOT NULL DEFAULT '',
    file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
    image_width INT UNSIGNED NOT NULL DEFAULT 0,
    image_height INT UNSIGNED NOT NULL DEFAULT 0,
    sha1_hash VARCHAR(40) NOT NULL DEFAULT '',
    uploaded_by_type VARCHAR(20) NOT NULL DEFAULT 'admin',
    uploaded_by_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_uploads_business_status (business_type, status),
    INDEX idx_uploads_related (related_id),
    INDEX idx_uploads_sha1 (sha1_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS posts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    region VARCHAR(20) NOT NULL,
    author_id INT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    excerpt VARCHAR(255) DEFAULT NULL,
    preview_content MEDIUMTEXT DEFAULT NULL,
    full_content MEDIUMTEXT NOT NULL,
    price INT NOT NULL DEFAULT 0,
    color_tag VARCHAR(20) DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'published',
    is_top_forever TINYINT(1) NOT NULL DEFAULT 0,
    is_top_admin TINYINT(1) NOT NULL DEFAULT 0,
    is_top_normal TINYINT(1) NOT NULL DEFAULT 0,
    view_count INT NOT NULL DEFAULT 0,
    reply_count INT NOT NULL DEFAULT 0,
    purchase_count INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME DEFAULT NULL,
    INDEX idx_posts_region_created (region, created_at),
    INDEX idx_posts_sort_flags (is_top_forever, is_top_admin, is_top_normal),
    INDEX idx_posts_deleted_at (deleted_at),
    CONSTRAINT fk_posts_author FOREIGN KEY (author_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS replies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id BIGINT UNSIGNED NOT NULL,
    parent_id BIGINT UNSIGNED DEFAULT NULL,
    user_id INT UNSIGNED NOT NULL,
    content TEXT NOT NULL,
    like_count INT NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'published',
    created_at DATETIME NOT NULL,
    INDEX idx_replies_post (post_id, created_at),
    INDEX idx_replies_parent (parent_id, created_at),
    CONSTRAINT fk_replies_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    CONSTRAINT fk_replies_parent FOREIGN KEY (parent_id) REFERENCES replies(id) ON DELETE CASCADE,
    CONSTRAINT fk_replies_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS comment_likes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    comment_id BIGINT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uniq_comment_like (comment_id, user_id),
    INDEX idx_comment_likes_comment_status (comment_id, status),
    INDEX idx_comment_likes_user_status (user_id, status),
    CONSTRAINT fk_comment_likes_comment FOREIGN KEY (comment_id) REFERENCES replies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS purchases (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id BIGINT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    price INT NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uniq_purchase (post_id, user_id),
    CONSTRAINT fk_purchases_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    CONSTRAINT fk_purchases_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS post_manage_meta (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id BIGINT UNSIGNED NOT NULL,
    segment_no INT NOT NULL DEFAULT 1,
    segment_sort INT NOT NULL DEFAULT 0,
    post_kind VARCHAR(20) NOT NULL DEFAULT 'data',
    is_fake TINYINT(1) NOT NULL DEFAULT 0,
    recent_result_log VARCHAR(255) NOT NULL DEFAULT '',
    fake_buyer_count INT NOT NULL DEFAULT 0,
    is_hidden TINYINT(1) NOT NULL DEFAULT 0,
    is_encrypted TINYINT(1) NOT NULL DEFAULT 0,
    auto_update_mode VARCHAR(20) NOT NULL DEFAULT 'none',
    auto_update_content MEDIUMTEXT DEFAULT NULL,
    manual_material MEDIUMTEXT DEFAULT NULL,
    author_nickname VARCHAR(60) NOT NULL DEFAULT '',
    title_font_size VARCHAR(8) NOT NULL DEFAULT '',
    title_font_weight VARCHAR(8) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uniq_post_manage_meta_post (post_id),
    INDEX idx_post_manage_meta_segment (segment_no, segment_sort),
    INDEX idx_post_manage_meta_kind (post_kind),
    INDEX idx_post_manage_meta_hidden (is_hidden),
    INDEX idx_post_manage_meta_fake (is_fake),
    CONSTRAINT fk_post_manage_meta_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lottery_draws (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    region VARCHAR(20) NOT NULL,
    issue_no VARCHAR(50) NOT NULL,
    draw_date DATE NOT NULL,
    numbers_json TEXT NOT NULL,
    special_number INT NOT NULL DEFAULT 0,
    note VARCHAR(255) DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uniq_region_issue (region, issue_no),
    INDEX idx_draws_region_date (region, draw_date),
    CONSTRAINT fk_draws_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ai_predictions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    region VARCHAR(20) NOT NULL,
    generated_for_issue VARCHAR(50) DEFAULT NULL,
    summary TEXT NOT NULL,
    numbers_json TEXT NOT NULL,
    confidence DECIMAL(5,2) NOT NULL DEFAULT 0,
    filters_json TEXT DEFAULT NULL,
    display_payloads_json MEDIUMTEXT DEFAULT NULL,
    line_confidences_json TEXT DEFAULT NULL,
    generated_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_predictions_region_created (region, created_at),
    CONSTRAINT fk_predictions_user FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ai_prediction_participations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    region VARCHAR(20) NOT NULL,
    actor_type VARCHAR(20) NOT NULL,
    actor_key VARCHAR(191) NOT NULL,
    user_id INT UNSIGNED DEFAULT NULL,
    participated_on DATE NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_ai_prediction_participations_actor_day (actor_type, actor_key, participated_on),
    INDEX idx_ai_prediction_participations_region_day (region, participated_on),
    INDEX idx_ai_prediction_participations_user_created (user_id, created_at),
    CONSTRAINT fk_ai_prediction_participations_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_group VARCHAR(50) NOT NULL,
    setting_value MEDIUMTEXT DEFAULT NULL,
    is_public TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admin_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    module_name VARCHAR(50) NOT NULL,
    action_name VARCHAR(50) NOT NULL,
    target_type VARCHAR(50) DEFAULT NULL,
    target_id VARCHAR(50) DEFAULT NULL,
    description VARCHAR(255) NOT NULL,
    ip_address VARCHAR(64) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_admin_logs_module (module_name, created_at),
    CONSTRAINT fk_admin_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS system_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    level_name VARCHAR(20) NOT NULL DEFAULT 'info',
    source_name VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    context_json MEDIUMTEXT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_system_logs_level (level_name, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS page_views (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    route_name VARCHAR(80) NOT NULL,
    path_name VARCHAR(255) NOT NULL,
    user_id INT UNSIGNED DEFAULT NULL,
    ip_address VARCHAR(64) NOT NULL,
    province_name VARCHAR(60) NOT NULL DEFAULT '',
    city_name VARCHAR(60) NOT NULL DEFAULT '',
    user_agent VARCHAR(255) DEFAULT NULL,
    referer VARCHAR(255) DEFAULT NULL,
    viewed_on DATE NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_page_views_route (route_name, viewed_on),
    INDEX idx_page_views_day (viewed_on),
    CONSTRAINT fk_page_views_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS post_unique_views (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id BIGINT UNSIGNED NOT NULL,
    user_id INT UNSIGNED DEFAULT NULL,
    identity_type VARCHAR(20) NOT NULL DEFAULT 'guest',
    identity_key VARCHAR(120) NOT NULL,
    ip_address VARCHAR(64) NOT NULL DEFAULT '',
    user_agent VARCHAR(255) DEFAULT NULL,
    viewed_on DATE NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uniq_post_unique_views_identity (post_id, identity_key),
    INDEX idx_post_unique_views_post (post_id, created_at),
    INDEX idx_post_unique_views_day (viewed_on, post_id),
    INDEX idx_post_unique_views_user (user_id, post_id),
    CONSTRAINT fk_post_unique_views_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    CONSTRAINT fk_post_unique_views_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS post_view_display_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id BIGINT UNSIGNED NOT NULL,
    unique_view_id BIGINT UNSIGNED NOT NULL,
    event_no SMALLINT UNSIGNED NOT NULL,
    release_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uniq_post_view_display_event (unique_view_id, event_no),
    INDEX idx_post_view_display_events_post (post_id, release_at),
    INDEX idx_post_view_display_events_release (release_at),
    CONSTRAINT fk_post_view_display_events_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    CONSTRAINT fk_post_view_display_events_unique_view FOREIGN KEY (unique_view_id) REFERENCES post_unique_views(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    region VARCHAR(20) NOT NULL DEFAULT 'all',
    title VARCHAR(100) NOT NULL DEFAULT '',
    content TEXT DEFAULT NULL,
    notice_type VARCHAR(20) NOT NULL DEFAULT 'scroll',
    link_url VARCHAR(255) NOT NULL DEFAULT '',
    sort_order INT NOT NULL DEFAULT 0,
    status TINYINT(1) NOT NULL DEFAULT 1,
    start_at DATETIME DEFAULT NULL,
    end_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_notices_region_type (region, notice_type),
    INDEX idx_notices_status_sort (status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS home_banners (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    region VARCHAR(20) NOT NULL DEFAULT 'all',
    title VARCHAR(100) NOT NULL DEFAULT '',
    image_url VARCHAR(255) NOT NULL DEFAULT '',
    link_url VARCHAR(255) NOT NULL DEFAULT '',
    open_type VARCHAR(20) NOT NULL DEFAULT '_self',
    sort_order INT NOT NULL DEFAULT 0,
    status TINYINT(1) NOT NULL DEFAULT 1,
    start_at DATETIME DEFAULT NULL,
    end_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_home_banners_region_status (region, status),
    INDEX idx_home_banners_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ad_slots (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slot_code VARCHAR(80) NOT NULL,
    slot_name VARCHAR(100) NOT NULL DEFAULT '',
    region VARCHAR(20) NOT NULL DEFAULT 'all',
    page_key VARCHAR(50) NOT NULL DEFAULT 'home',
    title VARCHAR(255) NOT NULL DEFAULT '',
    image_url VARCHAR(255) NOT NULL DEFAULT '',
    link_url VARCHAR(255) NOT NULL DEFAULT '',
    sort_order INT NOT NULL DEFAULT 0,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uniq_ad_slots_region_code (region, slot_code),
    INDEX idx_ad_slots_page_status (page_key, status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS recommend_positions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    position_code VARCHAR(80) NOT NULL,
    target_type VARCHAR(30) NOT NULL DEFAULT 'custom_html',
    target_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    region VARCHAR(20) NOT NULL DEFAULT 'all',
    title_override TEXT DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status TINYINT(1) NOT NULL DEFAULT 1,
    start_at DATETIME DEFAULT NULL,
    end_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uniq_recommend_positions_region_code (region, position_code),
    INDEX idx_recommend_positions_status_sort (status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS home_nav_entries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    region VARCHAR(20) NOT NULL DEFAULT 'all',
    title VARCHAR(50) NOT NULL DEFAULT '',
    icon VARCHAR(80) NOT NULL DEFAULT '',
    link_url VARCHAR(255) NOT NULL DEFAULT '',
    target VARCHAR(20) NOT NULL DEFAULT '_self',
    sort_order INT NOT NULL DEFAULT 0,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_home_nav_entries_region_status (region, status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS home_module_configs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module_key VARCHAR(80) NOT NULL,
    module_name VARCHAR(100) NOT NULL DEFAULT '',
    region VARCHAR(20) NOT NULL DEFAULT 'all',
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    config_json JSON DEFAULT NULL,
    updated_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uniq_home_module_configs_region_key (region, module_key),
    INDEX idx_home_module_configs_enabled_sort (is_enabled, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS system_exception_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    level VARCHAR(20) NOT NULL DEFAULT 'error',
    module VARCHAR(50) NOT NULL DEFAULT '',
    scene VARCHAR(80) NOT NULL DEFAULT '',
    message VARCHAR(500) NOT NULL DEFAULT '',
    trace_excerpt MEDIUMTEXT DEFAULT NULL,
    request_path VARCHAR(150) NOT NULL DEFAULT '',
    request_data MEDIUMTEXT DEFAULT NULL,
    operator_type VARCHAR(20) NOT NULL DEFAULT '',
    operator_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    ip VARCHAR(45) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    INDEX idx_system_exception_logs_level (level),
    INDEX idx_system_exception_logs_module (module),
    INDEX idx_system_exception_logs_operator (operator_type, operator_id),
    INDEX idx_system_exception_logs_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lottery_issues (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    region VARCHAR(20) NOT NULL DEFAULT 'macau',
    issue_no VARCHAR(50) NOT NULL,
    planned_open_at DATETIME DEFAULT NULL,
    actual_open_at DATETIME DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    is_current TINYINT(1) NOT NULL DEFAULT 0,
    remark VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uniq_lottery_issues_region_issue (region, issue_no),
    INDEX idx_lottery_issues_region_current (region, is_current),
    INDEX idx_lottery_issues_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS forum_sections (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    region VARCHAR(20) NOT NULL DEFAULT 'macau',
    name VARCHAR(50) NOT NULL DEFAULT '',
    code VARCHAR(50) NOT NULL,
    description VARCHAR(255) NOT NULL DEFAULT '',
    icon VARCHAR(255) NOT NULL DEFAULT '',
    sort_order INT NOT NULL DEFAULT 0,
    status TINYINT(1) NOT NULL DEFAULT 1,
    post_rule VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uniq_forum_sections_code (code),
    INDEX idx_forum_sections_region_status (region, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS forum_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    section_id BIGINT UNSIGNED NOT NULL,
    region VARCHAR(20) NOT NULL DEFAULT 'macau',
    name VARCHAR(50) NOT NULL DEFAULT '',
    code VARCHAR(50) NOT NULL,
    description VARCHAR(255) NOT NULL DEFAULT '',
    sort_order INT NOT NULL DEFAULT 0,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uniq_forum_categories_code (code),
    INDEX idx_forum_categories_section_status (section_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    target_type VARCHAR(30) NOT NULL DEFAULT '',
    target_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    auditor_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    audit_remark VARCHAR(255) NOT NULL DEFAULT '',
    audited_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_audit_records_target (target_type, target_id),
    INDEX idx_audit_records_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS post_interactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id BIGINT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    interaction_type VARCHAR(20) NOT NULL DEFAULT 'like',
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uniq_post_interaction (post_id, user_id, interaction_type),
    INDEX idx_post_interactions_post_status (post_id, status),
    INDEX idx_post_interactions_user_status (user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS post_reports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id BIGINT UNSIGNED NOT NULL,
    reporter_id INT UNSIGNED NOT NULL,
    report_type VARCHAR(30) NOT NULL DEFAULT 'other',
    content VARCHAR(255) NOT NULL DEFAULT '',
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    handled_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
    handle_result VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_post_reports_post_status (post_id, status),
    INDEX idx_post_reports_reporter_status (reporter_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admin_roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT '',
    data_scope VARCHAR(30) NOT NULL DEFAULT 'all',
    status TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admin_permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(100) NOT NULL UNIQUE,
    type VARCHAR(20) NOT NULL DEFAULT 'page',
    module VARCHAR(50) NOT NULL DEFAULT '',
    route_path VARCHAR(150) NOT NULL DEFAULT '',
    method VARCHAR(20) NOT NULL DEFAULT '',
    sort_order INT NOT NULL DEFAULT 0,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admin_role_permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uniq_admin_role_permission (role_id, permission_id),
    CONSTRAINT fk_admin_role_permissions_role FOREIGN KEY (role_id) REFERENCES admin_roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_admin_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES admin_permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admin_menus (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    title VARCHAR(50) NOT NULL,
    code VARCHAR(80) NOT NULL UNIQUE,
    icon VARCHAR(30) NOT NULL DEFAULT '',
    route_path VARCHAR(150) NOT NULL DEFAULT '',
    component_key VARCHAR(80) NOT NULL DEFAULT '',
    permission_code VARCHAR(100) NOT NULL DEFAULT '',
    menu_type VARCHAR(20) NOT NULL DEFAULT 'menu',
    is_visible TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admin_role_menus (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id BIGINT UNSIGNED NOT NULL,
    menu_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uniq_admin_role_menu (role_id, menu_id),
    CONSTRAINT fk_admin_role_menus_role FOREIGN KEY (role_id) REFERENCES admin_roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_admin_role_menus_menu FOREIGN KEY (menu_id) REFERENCES admin_menus(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admin_users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id BIGINT UNSIGNED NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    real_name VARCHAR(50) NOT NULL DEFAULT '',
    nickname VARCHAR(50) NOT NULL DEFAULT '',
    mobile VARCHAR(20) NOT NULL DEFAULT '',
    email VARCHAR(100) NOT NULL DEFAULT '',
    avatar VARCHAR(255) NOT NULL DEFAULT '',
    status TINYINT(1) NOT NULL DEFAULT 1,
    is_super TINYINT(1) NOT NULL DEFAULT 0,
    login_fail_count INT UNSIGNED NOT NULL DEFAULT 0,
    last_login_at DATETIME DEFAULT NULL,
    last_login_ip VARCHAR(45) NOT NULL DEFAULT '',
    last_login_area VARCHAR(100) NOT NULL DEFAULT '',
    session_token VARCHAR(100) NOT NULL DEFAULT '',
    remark VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME DEFAULT NULL,
    INDEX idx_admin_users_role_id (role_id),
    INDEX idx_admin_users_status (status),
    INDEX idx_admin_users_deleted_at (deleted_at),
    CONSTRAINT fk_admin_users_role FOREIGN KEY (role_id) REFERENCES admin_roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS customer_service_accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(80) NOT NULL DEFAULT '',
    welcome_text VARCHAR(255) NOT NULL DEFAULT '',
    service_hours VARCHAR(80) NOT NULL DEFAULT '09:00-23:00',
    auto_reply_text MEDIUMTEXT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS customer_service_sessions (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS customer_service_messages (
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
    CONSTRAINT fk_customer_service_messages_session FOREIGN KEY (session_id) REFERENCES customer_service_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_customer_service_messages_user FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_customer_service_messages_agent FOREIGN KEY (sender_agent_id) REFERENCES customer_service_accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admin_login_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id BIGINT UNSIGNED DEFAULT NULL,
    username VARCHAR(50) NOT NULL,
    login_type VARCHAR(20) NOT NULL DEFAULT 'password',
    status TINYINT(1) NOT NULL DEFAULT 0,
    ip VARCHAR(45) NOT NULL DEFAULT '',
    area VARCHAR(100) NOT NULL DEFAULT '',
    user_agent VARCHAR(255) NOT NULL DEFAULT '',
    device VARCHAR(50) NOT NULL DEFAULT '',
    fail_reason VARCHAR(255) NOT NULL DEFAULT '',
    login_at DATETIME NOT NULL,
    INDEX idx_admin_login_logs_admin_id (admin_id),
    INDEX idx_admin_login_logs_status (status),
    INDEX idx_admin_login_logs_login_at (login_at),
    CONSTRAINT fk_admin_login_logs_admin FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admin_operation_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id BIGINT UNSIGNED DEFAULT NULL,
    module VARCHAR(50) NOT NULL DEFAULT '',
    action VARCHAR(50) NOT NULL DEFAULT '',
    target_type VARCHAR(50) NOT NULL DEFAULT '',
    target_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    summary VARCHAR(255) NOT NULL DEFAULT '',
    request_method VARCHAR(10) NOT NULL DEFAULT '',
    request_path VARCHAR(150) NOT NULL DEFAULT '',
    request_data MEDIUMTEXT DEFAULT NULL,
    ip VARCHAR(45) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    INDEX idx_admin_operation_logs_admin_id (admin_id),
    INDEX idx_admin_operation_logs_module (module),
    INDEX idx_admin_operation_logs_created_at (created_at),
    CONSTRAINT fk_admin_operation_logs_admin FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS install_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    install_code VARCHAR(50) NOT NULL UNIQUE,
    app_version VARCHAR(30) NOT NULL DEFAULT '',
    site_name VARCHAR(100) NOT NULL DEFAULT '',
    site_domain VARCHAR(150) NOT NULL DEFAULT '',
    db_host VARCHAR(100) NOT NULL DEFAULT '',
    db_port INT UNSIGNED NOT NULL DEFAULT 3306,
    db_name VARCHAR(100) NOT NULL DEFAULT '',
    db_prefix VARCHAR(30) NOT NULL DEFAULT '',
    installer_ip VARCHAR(45) NOT NULL DEFAULT '',
    status VARCHAR(20) NOT NULL DEFAULT 'success',
    error_message VARCHAR(500) NOT NULL DEFAULT '',
    installed_at DATETIME NOT NULL,
    INDEX idx_install_records_installed_at (installed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS init_data_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    install_code VARCHAR(50) NOT NULL,
    data_group VARCHAR(50) NOT NULL DEFAULT '',
    table_name VARCHAR(80) NOT NULL DEFAULT '',
    record_count INT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'success',
    remark VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    INDEX idx_init_data_records_install_code (install_code),
    INDEX idx_init_data_records_data_group (data_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
