CREATE DATABASE IF NOT EXISTS predictive_dialer
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE predictive_dialer;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(191) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','manager','viewer') NOT NULL DEFAULT 'manager',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS vendors (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    trunk_name VARCHAR(150) NOT NULL UNIQUE,
    sip_username VARCHAR(150) NULL,
    sip_password VARCHAR(255) NULL,
    sip_domain VARCHAR(190) NULL,
    from_user VARCHAR(150) NULL,
    from_domain VARCHAR(190) NULL,
    transport VARCHAR(80) NOT NULL DEFAULT 'transport-udp',
    codecs VARCHAR(190) NOT NULL DEFAULT 'alaw,ulaw',
    context VARCHAR(150) NULL,
    dial_prefix VARCHAR(30) NULL,
    max_concurrent_calls INT NOT NULL DEFAULT 10,
    cps_limit DECIMAL(8,2) NOT NULL DEFAULT 1.00,
    priority INT NOT NULL DEFAULT 100,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    config_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_vendor_active_priority (is_active, priority)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ps_auths (
    id VARCHAR(80) PRIMARY KEY,
    auth_type VARCHAR(40) NULL,
    nonce_lifetime INT NULL,
    md5_cred VARCHAR(255) NULL,
    password VARCHAR(255) NULL,
    realm VARCHAR(255) NULL,
    username VARCHAR(255) NULL,
    refresh_token VARCHAR(255) NULL,
    oauth_clientid VARCHAR(255) NULL,
    oauth_secret VARCHAR(255) NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ps_aors (
    id VARCHAR(80) PRIMARY KEY,
    contact VARCHAR(255) NULL,
    default_expiration INT NULL,
    mailboxes VARCHAR(80) NULL,
    max_contacts INT NULL,
    minimum_expiration INT NULL,
    remove_existing VARCHAR(20) NULL,
    qualify_frequency INT NULL,
    authenticate_qualify VARCHAR(20) NULL,
    maximum_expiration INT NULL,
    outbound_proxy VARCHAR(255) NULL,
    support_path VARCHAR(20) NULL,
    qualify_timeout DECIMAL(10,3) NULL,
    voicemail_extension VARCHAR(40) NULL,
    remove_unavailable VARCHAR(20) NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ps_endpoints (
    id VARCHAR(80) PRIMARY KEY,
    transport VARCHAR(80) NULL,
    aors VARCHAR(200) NULL,
    auth VARCHAR(200) NULL,
    context VARCHAR(80) NULL,
    disallow VARCHAR(200) NULL,
    allow VARCHAR(200) NULL,
    direct_media VARCHAR(20) NULL,
    connected_line_method VARCHAR(40) NULL,
    direct_media_method VARCHAR(40) NULL,
    direct_media_glare_mitigation VARCHAR(40) NULL,
    disable_direct_media_on_nat VARCHAR(20) NULL,
    dtmf_mode VARCHAR(40) NULL,
    external_media_address VARCHAR(80) NULL,
    force_rport VARCHAR(20) NULL,
    ice_support VARCHAR(20) NULL,
    identify_by VARCHAR(80) NULL,
    mailboxes VARCHAR(80) NULL,
    moh_suggest VARCHAR(80) NULL,
    outbound_auth VARCHAR(200) NULL,
    outbound_proxy VARCHAR(255) NULL,
    rewrite_contact VARCHAR(20) NULL,
    rtp_ipv6 VARCHAR(20) NULL,
    rtp_symmetric VARCHAR(20) NULL,
    send_diversion VARCHAR(20) NULL,
    send_pai VARCHAR(20) NULL,
    send_rpid VARCHAR(20) NULL,
    timers_min_se INT NULL,
    timers VARCHAR(20) NULL,
    timers_sess_expires INT NULL,
    callerid VARCHAR(80) NULL,
    callerid_privacy VARCHAR(40) NULL,
    callerid_tag VARCHAR(80) NULL,
    trust_id_inbound VARCHAR(20) NULL,
    trust_id_outbound VARCHAR(20) NULL,
    use_ptime VARCHAR(20) NULL,
    use_avpf VARCHAR(20) NULL,
    media_encryption VARCHAR(40) NULL,
    inband_progress VARCHAR(20) NULL,
    call_group VARCHAR(40) NULL,
    pickup_group VARCHAR(40) NULL,
    named_call_group VARCHAR(80) NULL,
    named_pickup_group VARCHAR(80) NULL,
    device_state_busy_at INT NULL,
    fax_detect VARCHAR(20) NULL,
    t38_udptl VARCHAR(20) NULL,
    t38_udptl_ec VARCHAR(40) NULL,
    t38_udptl_maxdatagram INT NULL,
    t38_udptl_nat VARCHAR(20) NULL,
    t38_udptl_ipv6 VARCHAR(20) NULL,
    tone_zone VARCHAR(40) NULL,
    language VARCHAR(40) NULL,
    one_touch_recording VARCHAR(20) NULL,
    record_on_feature VARCHAR(80) NULL,
    record_off_feature VARCHAR(80) NULL,
    rtp_engine VARCHAR(40) NULL,
    allow_transfer VARCHAR(20) NULL,
    user_eq_phone VARCHAR(20) NULL,
    sdp_owner VARCHAR(40) NULL,
    sdp_session VARCHAR(40) NULL,
    tos_audio VARCHAR(20) NULL,
    tos_video VARCHAR(20) NULL,
    cos_audio INT NULL,
    cos_video INT NULL,
    sub_min_expiry INT NULL,
    from_domain VARCHAR(190) NULL,
    from_user VARCHAR(150) NULL,
    mwi_from_user VARCHAR(150) NULL,
    dtls_verify VARCHAR(40) NULL,
    dtls_rekey VARCHAR(40) NULL,
    dtls_cert_file VARCHAR(255) NULL,
    dtls_private_key VARCHAR(255) NULL,
    dtls_cipher VARCHAR(255) NULL,
    dtls_ca_file VARCHAR(255) NULL,
    dtls_ca_path VARCHAR(255) NULL,
    dtls_setup VARCHAR(40) NULL,
    srtp_tag_32 VARCHAR(20) NULL,
    media_address VARCHAR(80) NULL,
    redirect_method VARCHAR(40) NULL,
    set_var TEXT NULL,
    message_context VARCHAR(80) NULL,
    accountcode VARCHAR(80) NULL
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS ps_endpoint_id_ips (
    id VARCHAR(80) PRIMARY KEY,
    endpoint VARCHAR(80) NULL,
    `match` VARCHAR(255) NULL,
    srv_lookups VARCHAR(20) NULL,
    match_header VARCHAR(255) NULL
) ENGINE=InnoDB;



CREATE TABLE IF NOT EXISTS audio_prompts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    asterisk_filename VARCHAR(255) NULL,
    file_type ENUM('wav','mp3') NOT NULL,
    duration_seconds DECIMAL(10,2) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    uploaded_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS campaigns (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(180) NOT NULL,
    status ENUM('draft','scheduled','running','paused','stopping','stopped','completed','failed') NOT NULL DEFAULT 'draft',
    scheduled_at DATETIME NULL,
    started_at DATETIME NULL,
    paused_at DATETIME NULL,
    stopped_at DATETIME NULL,
    completed_at DATETIME NULL,
    vendor_id BIGINT UNSIGNED NULL,
    audio_prompt_id BIGINT UNSIGNED NULL,
    timezone VARCHAR(64) NOT NULL DEFAULT 'Asia/Dhaka',
    max_concurrent_calls INT NOT NULL DEFAULT 10,
    target_answer_rate DECIMAL(5,2) NOT NULL DEFAULT 30.00,
    retry_limit INT NOT NULL DEFAULT 1,
    retry_delay_minutes INT NOT NULL DEFAULT 60,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id),
    FOREIGN KEY (audio_prompt_id) REFERENCES audio_prompts(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_campaign_status_schedule (status, scheduled_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS lead_imports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id BIGINT UNSIGNED NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    total_rows INT NOT NULL DEFAULT 0,
    valid_rows INT NOT NULL DEFAULT 0,
    invalid_rows INT NOT NULL DEFAULT 0,
    status ENUM('uploaded','validating','validated','importing','completed','failed') NOT NULL DEFAULT 'uploaded',
    error_message TEXT NULL,
    uploaded_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id),
    FOREIGN KEY (uploaded_by) REFERENCES users(id),
    INDEX idx_import_campaign (campaign_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS lead_import_errors (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    import_id BIGINT UNSIGNED NOT NULL,
    row_num INT NOT NULL,
    field_name VARCHAR(100) NULL,
    raw_value TEXT NULL,
    error_message VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (import_id) REFERENCES lead_imports(id) ON DELETE CASCADE,
    INDEX idx_import_errors_import (import_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS leads (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id BIGINT UNSIGNED NOT NULL,
    import_id BIGINT UNSIGNED NULL,
    phone_number VARCHAR(32) NOT NULL,
    normalized_phone VARCHAR(32) NOT NULL,
    first_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NULL,
    status ENUM('pending','queued','dialing','answered','completed','failed','no_answer','busy') NOT NULL DEFAULT 'pending',
    attempts INT NOT NULL DEFAULT 0,
    next_attempt_at DATETIME NULL,
    last_dialed_at DATETIME NULL,
    completed_at DATETIME NULL,
    last_disposition VARCHAR(80) NULL,
    custom_data JSON NULL,
    locked_by VARCHAR(100) NULL,
    locked_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id),
    FOREIGN KEY (import_id) REFERENCES lead_imports(id),
    UNIQUE KEY uq_campaign_phone (campaign_id, normalized_phone),
    INDEX idx_lead_campaign_status_next (campaign_id, status, next_attempt_at),
    INDEX idx_lead_lock (locked_by, locked_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS calls (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id BIGINT UNSIGNED NOT NULL,
    lead_id BIGINT UNSIGNED NOT NULL,
    vendor_id BIGINT UNSIGNED NULL,
    asterisk_uniqueid VARCHAR(80) NULL,
    linkedid VARCHAR(80) NULL,
    channel VARCHAR(180) NULL,
    destination VARCHAR(32) NOT NULL,
    status ENUM('initiated','ringing','answered','playing_prompt','collecting_dtmf','completed','failed','busy','no_answer','cancelled') NOT NULL DEFAULT 'initiated',
    dialed_at DATETIME NOT NULL,
    answered_at DATETIME NULL,
    ended_at DATETIME NULL,
    billsec INT NOT NULL DEFAULT 0,
    duration_sec INT NOT NULL DEFAULT 0,
    hangup_cause VARCHAR(80) NULL,
    disposition VARCHAR(80) NULL,
    failure_reason VARCHAR(255) NULL,
    originate_action_id VARCHAR(100) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id),
    FOREIGN KEY (lead_id) REFERENCES leads(id),
    FOREIGN KEY (vendor_id) REFERENCES vendors(id),
    UNIQUE KEY uq_originate_action (originate_action_id),
    INDEX idx_call_uniqueid (asterisk_uniqueid),
    INDEX idx_call_campaign_status (campaign_id, status),
    INDEX idx_call_lead (lead_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS call_dtmf (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    call_id BIGINT UNSIGNED NOT NULL,
    lead_id BIGINT UNSIGNED NOT NULL,
    digit CHAR(1) NOT NULL,
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sequence_no INT NOT NULL DEFAULT 1,
    FOREIGN KEY (call_id) REFERENCES calls(id),
    FOREIGN KEY (lead_id) REFERENCES leads(id),
    INDEX idx_dtmf_call (call_id),
    INDEX idx_dtmf_digit (digit),
    CONSTRAINT chk_dtmf_digit CHECK (digit IN ('0','1','2','3','4','5','6','7','8','9'))
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS campaign_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(80) NOT NULL,
    payload JSON NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_campaign_events_campaign_time (campaign_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS engine_heartbeats (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    engine_id VARCHAR(100) NOT NULL UNIQUE,
    hostname VARCHAR(150) NOT NULL,
    pid INT NOT NULL,
    status ENUM('starting','running','degraded','stopping','stopped') NOT NULL,
    last_seen_at DATETIME NOT NULL,
    metadata JSON NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS system_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    level ENUM('debug','info','warning','error','critical') NOT NULL,
    source VARCHAR(80) NOT NULL,
    message TEXT NOT NULL,
    context JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_logs_level_time (level, created_at),
    INDEX idx_logs_source_time (source, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS asterisk_cdr (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    calldate DATETIME NULL,
    clid VARCHAR(80) NULL,
    src VARCHAR(80) NULL,
    dst VARCHAR(80) NULL,
    dcontext VARCHAR(80) NULL,
    channel VARCHAR(120) NULL,
    dstchannel VARCHAR(120) NULL,
    lastapp VARCHAR(80) NULL,
    lastdata VARCHAR(255) NULL,
    duration INT NULL,
    billsec INT NULL,
    disposition VARCHAR(45) NULL,
    amaflags INT NULL,
    accountcode VARCHAR(40) NULL,
    uniqueid VARCHAR(80) NULL,
    linkedid VARCHAR(80) NULL,
    peeraccount VARCHAR(80) NULL,
    sequence INT NULL,
    INDEX idx_cdr_calldate (calldate),
    INDEX idx_cdr_uniqueid (uniqueid),
    INDEX idx_cdr_linkedid (linkedid)
) ENGINE=InnoDB;
