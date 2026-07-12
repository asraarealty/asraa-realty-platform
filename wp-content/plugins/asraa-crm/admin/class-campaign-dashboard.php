<?php
/**
 * Campaign Dashboard Page
 *
 * Renders the campaign type selection screen and existing campaign list
 * in the WordPress admin. When a type is chosen the user is shown a
 * creation form that records the campaign before routing to the
 * appropriate bulk-send flow.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Asraa_Campaign_Dashboard {

    /**
     * Render the campaign dashboard page.
     *
     * Three sub-views are supported:
     *  (none)           → campaign type selector + past-campaigns list
     *  action=create    → campaign creation form for the chosen type
     *  action=view&id=N → detail view of an existing campaign
     */
    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'asraa-crm' ) );
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';

        if ( 'create' === $action ) {
            self::render_create_form();
            return;
        }

        if ( 'view' === $action ) {
            self::render_campaign_detail();
            return;
        }

        self::render_dashboard();
    }

    /* ----------------------------------------------------------------
       DASHBOARD (type selector + campaign list)
    ---------------------------------------------------------------- */

    private static function render_dashboard() {
        $nonce = wp_create_nonce( 'asraa_crm_campaign_nonce' );

        $campaign_types = [
            [
                'type'        => 'email',
                'icon'        => 'dashicons-email-alt',
                'color'       => '#2271b1',
                'title'       => 'Email Campaign',
                'description' => 'Send targeted email campaigns to your leads using customisable templates.',
            ],
            [
                'type'        => 'whatsapp',
                'icon'        => 'dashicons-phone',
                'color'       => '#25D366',
                'title'       => 'WhatsApp Campaign',
                'description' => 'Reach leads directly on WhatsApp with personalised message templates.',
            ],
            [
                'type'        => 'sms',
                'icon'        => 'dashicons-smartphone',
                'color'       => '#ffb900',
                'title'       => 'SMS Campaign',
                'description' => 'Send quick SMS messages to your contacts for timely updates.',
            ],
            [
                'type'        => 'automation',
                'icon'        => 'dashicons-randomize',
                'color'       => '#8c5fc5',
                'title'       => 'Automation Campaign',
                'description' => 'Set up automated sequences that trigger based on lead actions or schedules.',
            ],
        ];

        // Fetch past campaigns.
        $repo      = new Asraa_CRM_Bulk_Campaign_Repository();
        $campaigns = $repo->get_all();
        ?>
        <div class="wrap asraa-campaign-wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Campaigns', 'asraa-crm' ); ?></h1>
            <p class="asraa-campaign-subtitle">
                <?php esc_html_e( 'Choose a campaign type to get started.', 'asraa-crm' ); ?>
            </p>

            <div class="asraa-campaign-grid">
                <?php foreach ( $campaign_types as $campaign ) : ?>
                    <div class="asraa-campaign-card" data-type="<?php echo esc_attr( $campaign['type'] ); ?>">
                        <div class="asraa-campaign-card__icon" style="color: <?php echo esc_attr( $campaign['color'] ); ?>; border-color: <?php echo esc_attr( $campaign['color'] ); ?>;">
                            <span class="dashicons <?php echo esc_attr( $campaign['icon'] ); ?>"></span>
                        </div>
                        <h2 class="asraa-campaign-card__title"><?php echo esc_html( $campaign['title'] ); ?></h2>
                        <p class="asraa-campaign-card__desc"><?php echo esc_html( $campaign['description'] ); ?></p>
                        <a
                            href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=asraa-crm-campaigns&action=create&type=' . rawurlencode( $campaign['type'] ) ), 'asraa_crm_campaign_nonce' ) ); ?>"
                            class="button button-primary asraa-campaign-card__btn"
                        >
                            <?php esc_html_e( 'Create Campaign', 'asraa-crm' ); ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ( ! empty( $campaigns ) ) : ?>
            <h2 style="margin-top:30px;"><?php esc_html_e( 'Past Campaigns', 'asraa-crm' ); ?></h2>
            <table class="wp-list-table widefat fixed striped" style="margin-top:10px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'ID', 'asraa-crm' ); ?></th>
                        <th><?php esc_html_e( 'Name', 'asraa-crm' ); ?></th>
                        <th><?php esc_html_e( 'Type', 'asraa-crm' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'asraa-crm' ); ?></th>
                        <th><?php esc_html_e( 'Leads', 'asraa-crm' ); ?></th>
                        <th><?php esc_html_e( 'Sent', 'asraa-crm' ); ?></th>
                        <th><?php esc_html_e( 'Created', 'asraa-crm' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'asraa-crm' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $campaigns as $c ) : ?>
                    <tr>
                        <td><?php echo esc_html( $c['id'] ); ?></td>
                        <td><?php echo esc_html( $c['campaign_name'] ); ?></td>
                        <td><?php echo esc_html( ucfirst( $c['message_type'] ) ); ?></td>
                        <td>
                            <span style="text-transform:capitalize;"><?php echo esc_html( str_replace( '_', ' ', $c['status'] ) ); ?></span>
                        </td>
                        <td><?php echo esc_html( $c['leads_count'] ); ?></td>
                        <td><?php echo esc_html( $c['sent_count'] ); ?></td>
                        <td><?php echo esc_html( asraa_crm_format_date( $c['created_at'] ) ); ?></td>
                        <td>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=asraa-crm-campaigns&action=view&id=' . (int) $c['id'] ) ); ?>"
                               class="button button-small"><?php esc_html_e( 'View', 'asraa-crm' ); ?></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else : ?>
            <p style="margin-top:20px; color:#666;"><?php esc_html_e( 'No campaigns yet. Create your first one above.', 'asraa-crm' ); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /* ----------------------------------------------------------------
       CREATE FORM
    ---------------------------------------------------------------- */

    private static function render_create_form() {
        // Verify nonce before rendering the form.
        if ( empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'asraa_crm_campaign_nonce' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'asraa-crm' ) );
        }

        $type = isset( $_GET['type'] ) ? sanitize_text_field( wp_unslash( $_GET['type'] ) ) : 'email';

        // Only email and whatsapp have dedicated creation forms; sms/automation
        // show an informational placeholder.
        $supported = [ 'email', 'whatsapp' ];

        // Handle form submission (POST).
        if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['asraa_create_campaign'] ) ) {
            check_admin_referer( 'asraa_create_campaign_' . $type );
            self::handle_campaign_create( $type );
            return;
        }

        // Fetch groups for the lead-filter dropdown.
        global $wpdb;
        $groups = $wpdb->get_results(
            "SELECT id, group_name FROM {$wpdb->prefix}asraa_crm_groups ORDER BY group_name ASC",
            ARRAY_A
        );

        $type_labels = [
            'email'      => __( 'Email Campaign', 'asraa-crm' ),
            'whatsapp'   => __( 'WhatsApp Campaign', 'asraa-crm' ),
            'sms'        => __( 'SMS Campaign', 'asraa-crm' ),
            'automation' => __( 'Automation Campaign', 'asraa-crm' ),
        ];
        $type_label = $type_labels[ $type ] ?? ucfirst( $type ) . ' Campaign';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( $type_label ); ?></h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=asraa-crm-campaigns' ) ); ?>" class="button" style="margin-bottom:15px;">
                &larr; <?php esc_html_e( 'Back to Campaigns', 'asraa-crm' ); ?>
            </a>

            <?php if ( ! in_array( $type, $supported, true ) ) : ?>
                <div class="notice notice-info">
                    <p>
                        <?php
                        printf(
                            /* translators: %s: campaign type name */
                            esc_html__( '%s campaigns are coming soon. In the meantime, use the Automation rules page for automated triggers, or the Leads page for bulk outreach.', 'asraa-crm' ),
                            esc_html( $type_label )
                        );
                        ?>
                    </p>
                </div>
            <?php else : ?>

            <div class="postbox" style="max-width:720px; padding:20px;">
                <form method="post">
                    <?php wp_nonce_field( 'asraa_create_campaign_' . $type ); ?>
                    <input type="hidden" name="asraa_create_campaign" value="1">
                    <input type="hidden" name="campaign_type" value="<?php echo esc_attr( $type ); ?>">

                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="campaign_name"><?php esc_html_e( 'Campaign Name', 'asraa-crm' ); ?></label></th>
                            <td>
                                <input type="text" id="campaign_name" name="campaign_name" class="regular-text" required
                                       placeholder="<?php esc_attr_e( 'e.g. April Newsletter', 'asraa-crm' ); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="filter_group"><?php esc_html_e( 'Target Group (optional)', 'asraa-crm' ); ?></label></th>
                            <td>
                                <select id="filter_group" name="filter_group">
                                    <option value=""><?php esc_html_e( '— All active leads —', 'asraa-crm' ); ?></option>
                                    <?php foreach ( $groups as $g ) : ?>
                                        <option value="<?php echo esc_attr( $g['id'] ); ?>"><?php echo esc_html( $g['group_name'] ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php esc_html_e( 'Leave blank to target all active leads.', 'asraa-crm' ); ?></p>
                            </td>
                        </tr>

                        <?php if ( 'email' === $type ) : ?>
                        <tr>
                            <th scope="row"><label for="email_subject"><?php esc_html_e( 'Subject', 'asraa-crm' ); ?></label></th>
                            <td>
                                <input type="text" id="email_subject" name="email_subject" class="large-text" required
                                       placeholder="<?php esc_attr_e( 'e.g. Exclusive Property Offer for {name}', 'asraa-crm' ); ?>">
                                <p class="description"><?php esc_html_e( 'Use {name}, {email}, {phone}, {agent_name} as placeholders.', 'asraa-crm' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="email_body"><?php esc_html_e( 'Message Body', 'asraa-crm' ); ?></label></th>
                            <td>
                                <textarea id="email_body" name="email_body" class="large-text" rows="8" required
                                          placeholder="<?php esc_attr_e( 'Hi {name},\n\nWe have a special offer...', 'asraa-crm' ); ?>"></textarea>
                                <p class="description"><?php esc_html_e( 'HTML is supported. Use {name}, {email}, {budget}, {property_type} as placeholders.', 'asraa-crm' ); ?></p>
                            </td>
                        </tr>
                        <?php endif; ?>

                        <?php if ( 'whatsapp' === $type ) : ?>
                        <tr>
                            <th scope="row"><label for="wa_message"><?php esc_html_e( 'Message', 'asraa-crm' ); ?></label></th>
                            <td>
                                <textarea id="wa_message" name="wa_message" class="large-text" rows="6" required
                                          placeholder="<?php esc_attr_e( 'Hi {name}, we have a great property for you!', 'asraa-crm' ); ?>"></textarea>
                                <p class="description"><?php esc_html_e( 'Use {name}, {phone}, {property_type}, {budget} as placeholders.', 'asraa-crm' ); ?></p>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>

                    <p class="submit">
                        <button type="submit" class="button button-primary">
                            <?php
                            if ( 'email' === $type ) {
                                esc_html_e( 'Send Email Campaign', 'asraa-crm' );
                            } else {
                                esc_html_e( 'Generate WhatsApp Links', 'asraa-crm' );
                            }
                            ?>
                        </button>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=asraa-crm-campaigns' ) ); ?>" class="button">
                            <?php esc_html_e( 'Cancel', 'asraa-crm' ); ?>
                        </a>
                    </p>
                </form>
            </div>

            <?php endif; ?>
        </div>
        <?php
    }

    /* ----------------------------------------------------------------
       HANDLE CAMPAIGN CREATION (POST)
    ---------------------------------------------------------------- */

    private static function handle_campaign_create( $type ) {
        global $wpdb;

        $campaign_name = sanitize_text_field( $_POST['campaign_name'] ?? '' );
        $filter_group  = intval( $_POST['filter_group'] ?? 0 );

        if ( empty( $campaign_name ) ) {
            wp_die( esc_html__( 'Campaign name is required.', 'asraa-crm' ) );
        }

        // Build lead query based on group filter.
        $leads_table = $wpdb->prefix . 'asraa_crm_leads';
        if ( $filter_group ) {
            $leads = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$leads_table} WHERE is_deleted = 0 AND group_id = %d",
                    $filter_group
                ),
                ARRAY_A
            );
        } else {
            $leads = $wpdb->get_results(
                "SELECT * FROM {$leads_table} WHERE is_deleted = 0",
                ARRAY_A
            );
        }

        if ( empty( $leads ) ) {
            wp_die( esc_html__( 'No active leads found for this campaign. Please check your group filter.', 'asraa-crm' ) );
        }

        $campaign_repo = new Asraa_CRM_Bulk_Campaign_Repository();
        $service       = new Asraa_Messaging_Service();

        if ( 'email' === $type ) {
            $subject_tpl = wp_kses_post( $_POST['email_subject'] ?? '' );
            $body_tpl    = wp_kses_post( $_POST['email_body'] ?? '' );

            if ( empty( $subject_tpl ) || empty( $body_tpl ) ) {
                wp_die( esc_html__( 'Subject and body are required for email campaigns.', 'asraa-crm' ) );
            }

            $campaign_id = $campaign_repo->create( [
                'campaign_name' => $campaign_name,
                'message_type'  => 'email',
                'template_id'   => 0,
                'status'        => 'in_progress',
                'leads_count'   => count( $leads ),
                'sent_count'    => 0,
                'created_by'    => get_current_user_id(),
            ] );

            error_log( 'Asraa CRM: email campaign created id=' . $campaign_id . ' name=' . $campaign_name . ' leads=' . count( $leads ) );

            $result = $service->send_bulk_email( $leads, $subject_tpl, $body_tpl, 0, $campaign_id );

            $campaign_repo->update( $campaign_id, [
                'status'     => 'completed',
                'sent_count' => $result['sent'],
            ] );

            asraa_crm_fire_trigger( 'campaign_completed', [
                'id'   => $campaign_id,
                'type' => 'email',
                'sent' => $result['sent'],
            ] );

            $redirect = admin_url(
                'admin.php?page=asraa-crm-campaigns&action=view&id=' . $campaign_id .
                '&sent=' . $result['sent'] . '&failed=' . $result['failed'] . '&skipped=' . $result['skipped']
            );

        } else { // whatsapp
            $message_tpl = sanitize_textarea_field( $_POST['wa_message'] ?? '' );

            if ( empty( $message_tpl ) ) {
                wp_die( esc_html__( 'Message is required for WhatsApp campaigns.', 'asraa-crm' ) );
            }

            $campaign_id = $campaign_repo->create( [
                'campaign_name' => $campaign_name,
                'message_type'  => 'whatsapp',
                'template_id'   => 0,
                'status'        => 'in_progress',
                'leads_count'   => count( $leads ),
                'sent_count'    => 0,
                'created_by'    => get_current_user_id(),
            ] );

            error_log( 'Asraa CRM: whatsapp campaign created id=' . $campaign_id . ' name=' . $campaign_name . ' leads=' . count( $leads ) );

            $sent    = 0;
            $skipped = 0;
            // Store per-lead WhatsApp URLs in campaign meta (transient).
            $wa_links = [];

            foreach ( $leads as $lead ) {
                $phone = preg_replace( '/\D/', '', $lead['phone'] ?? '' );
                if ( empty( $phone ) ) {
                    $skipped++;
                    continue;
                }
                $message    = $service->replace_variables( $message_tpl, $lead );
                $wa_url     = $service->build_whatsapp_url( $lead, $message );
                $wa_links[] = [
                    'lead_id'   => $lead['id'],
                    'lead_name' => $lead['name'],
                    'url'       => $wa_url,
                    'message'   => $message,
                ];
                $service->log_bulk_whatsapp( $lead['id'], $message, 0, $campaign_id );
                $sent++;
            }

            $campaign_repo->update( $campaign_id, [
                'status'     => 'completed',
                'sent_count' => $sent,
            ] );

            // Store links temporarily so the view page can show them.
            set_transient( 'asraa_crm_wa_links_' . $campaign_id, $wa_links, HOUR_IN_SECONDS );

            asraa_crm_fire_trigger( 'campaign_completed', [
                'id'   => $campaign_id,
                'type' => 'whatsapp',
                'sent' => $sent,
            ] );

            $redirect = admin_url(
                'admin.php?page=asraa-crm-campaigns&action=view&id=' . $campaign_id .
                '&sent=' . $sent . '&skipped=' . $skipped
            );
        }

        wp_redirect( $redirect );
        exit;
    }

    /* ----------------------------------------------------------------
       CAMPAIGN DETAIL VIEW
    ---------------------------------------------------------------- */

    private static function render_campaign_detail() {
        $id = intval( $_GET['id'] ?? 0 );
        if ( ! $id ) {
            wp_redirect( admin_url( 'admin.php?page=asraa-crm-campaigns' ) );
            exit;
        }

        $repo     = new Asraa_CRM_Bulk_Campaign_Repository();
        $campaign = $repo->get_by_id( $id );

        if ( ! $campaign ) {
            wp_die( esc_html__( 'Campaign not found.', 'asraa-crm' ) );
        }

        $sent    = intval( $_GET['sent']    ?? $campaign['sent_count'] );
        $failed  = intval( $_GET['failed']  ?? 0 );
        $skipped = intval( $_GET['skipped'] ?? 0 );

        // For WhatsApp campaigns, retrieve the per-lead links if still cached.
        $wa_links = [];
        if ( 'whatsapp' === $campaign['message_type'] ) {
            $wa_links = get_transient( 'asraa_crm_wa_links_' . $id ) ?: [];
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( $campaign['campaign_name'] ); ?></h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=asraa-crm-campaigns' ) ); ?>" class="button" style="margin-bottom:15px;">
                &larr; <?php esc_html_e( 'Back to Campaigns', 'asraa-crm' ); ?>
            </a>

            <?php if ( $sent > 0 || $failed > 0 || $skipped > 0 ) : ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <?php
                    if ( 'email' === $campaign['message_type'] ) {
                        printf(
                            /* translators: 1: sent, 2: failed, 3: skipped */
                            esc_html__( 'Campaign completed: %1$d sent, %2$d failed, %3$d skipped.', 'asraa-crm' ),
                            $sent, $failed, $skipped
                        );
                    } else {
                        printf(
                            /* translators: 1: sent, 2: skipped */
                            esc_html__( 'Campaign completed: %1$d links generated, %2$d skipped.', 'asraa-crm' ),
                            $sent, $skipped
                        );
                    }
                    ?>
                </p>
            </div>
            <?php endif; ?>

            <table class="form-table">
                <tr><th><?php esc_html_e( 'ID', 'asraa-crm' ); ?></th><td><?php echo esc_html( $campaign['id'] ); ?></td></tr>
                <tr><th><?php esc_html_e( 'Type', 'asraa-crm' ); ?></th><td><?php echo esc_html( ucfirst( $campaign['message_type'] ) ); ?></td></tr>
                <tr><th><?php esc_html_e( 'Status', 'asraa-crm' ); ?></th><td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $campaign['status'] ) ) ); ?></td></tr>
                <tr><th><?php esc_html_e( 'Leads targeted', 'asraa-crm' ); ?></th><td><?php echo esc_html( $campaign['leads_count'] ); ?></td></tr>
                <tr><th><?php esc_html_e( 'Sent', 'asraa-crm' ); ?></th><td><?php echo esc_html( $campaign['sent_count'] ); ?></td></tr>
                <tr><th><?php esc_html_e( 'Created', 'asraa-crm' ); ?></th><td><?php echo esc_html( asraa_crm_format_date( $campaign['created_at'] ) ); ?></td></tr>
            </table>

            <?php if ( ! empty( $wa_links ) ) : ?>
            <h2 style="margin-top:20px;"><?php esc_html_e( 'WhatsApp Links', 'asraa-crm' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Click each link to open the conversation in WhatsApp Web. Links expire after 1 hour.', 'asraa-crm' ); ?></p>
            <ul style="margin-top:10px;">
                <?php foreach ( $wa_links as $wl ) : ?>
                <li style="margin-bottom:6px;">
                    <strong><?php echo esc_html( $wl['lead_name'] ); ?></strong> –
                    <a href="<?php echo esc_url( $wl['url'] ); ?>" target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e( 'Open in WhatsApp ↗', 'asraa-crm' ); ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
        <?php
    }
}
