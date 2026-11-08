<?php

defined('ABSPATH') || exit; // Exit if accessed directly

/**
 * Plugin email notifications. Each event listens on an existing plugin
 * action / WP core hook and dispatches a templated HTML email.
 *
 * Templates live in `templates/emails/email-{slug}.php` and are loaded with
 * `workeera_get_template_part('email', '{slug}', 'emails')`, so theme authors
 * can override any template by dropping a matching file into
 * `your-theme/workeera/emails/email-{slug}.php`.
 *
 * Templates use simple `{token}` placeholders that the class replaces before
 * passing the body to `wp_mail`. The full token map is documented in each
 * template file.
 */
class Workeera_Email_Notifications {

    /**
     * Meta key recorded against a job (workeera_metadata, object_type=post) the
     * first time we send a "job published" notification, so we never send a
     * second one for the same job.
     */
    const META_JOB_PUBLISHED_NOTIFIED = '_workeera_publish_notified';

    /**
     * Bind the email-trigger handlers to their job-lifecycle and
     * application-lifecycle hooks.
     */
    public function __construct() {
        // First-time publish of a front-end submitted job.
        add_action( 'transition_post_status', [ $this, 'on_job_status_transition' ], 20, 3 );

        // New application landed on a job.
        add_action( 'workeera_after_job_applied', [ $this, 'on_new_application' ], 20, 4 );

        // Application status changed (single + bulk).
        add_action( 'workeera_after_application_status_change',       [ $this, 'on_application_status_change' ], 20, 3 );
        add_action( 'workeera_after_applications_bulk_status_change', [ $this, 'on_application_bulk_status_change' ], 20, 4 );
    }

    // -------------------------------------------------------------------------
    // Hook handlers
    // -------------------------------------------------------------------------

    /**
     * Fires on every post status change. We only act when a workeera_job moves
     * to `publish` for the first time, and only when its author is NOT a site
     * admin (i.e. the listing was created from the front-end by a regular
     * employer user — admin-created jobs don't need to notify themselves).
     *
     * @param string  $new_status
     * @param string  $old_status
     * @param WP_Post $post
     */
    public function on_job_status_transition( $new_status, $old_status, $post ) {
        if ( ! $post instanceof WP_Post || $post->post_type !== 'workeera_job' ) {
            return;
        }
        if ( $new_status !== 'publish' || $old_status === 'publish' ) {
            return;
        }

        $author_id = (int) $post->post_author;
        if ( $author_id < 1 ) {
            return;
        }
        // Skip when the author is a site admin — admins publishing from
        // wp-admin shouldn't get a "your job is live" mail for their own work.
        if ( user_can( $author_id, 'manage_options' ) ) {
            return;
        }

        // Idempotency: only the first publish gets a notification.
        if ( workeera_metadata_exists( 'post', $post->ID, self::META_JOB_PUBLISHED_NOTIFIED ) ) {
            return;
        }
        workeera_update_object_meta( $post->ID, self::META_JOB_PUBLISHED_NOTIFIED, current_time( 'mysql' ), 'post' );

        $author = get_userdata( $author_id );
        if ( ! $author || empty( $author->user_email ) ) {
            return;
        }

        $tokens = $this->build_job_tokens( $post, $author );

        /* translators: %s: job title */
        $subject = sprintf( esc_html_x( 'Your job "%s" has been published', 'email subject', 'workeera-remote-tech-job-board' ), $post->post_title );

        $this->send( $author->user_email, $subject, 'job-published', $tokens, [
            'event'   => 'job_published',
            'job_id'  => (int) $post->ID,
            'user_id' => $author_id,
        ] );
    }

    /**
     * Notify the employer that owns the job whenever a candidate applies.
     *
     * @param int $application_id
     * @param int $job_id
     * @param int $user_id        Applicant user ID.
     * @param int $candidate_id   Attached candidate post ID.
     */
    public function on_new_application( $application_id, $job_id, $user_id, $candidate_id ) {
        $application = $this->get_application_row( $application_id );
        $job         = get_post( absint( $job_id ) );
        if ( ! $application || ! $job ) {
            return;
        }

        $employer = get_userdata( (int) $job->post_author );
        if ( ! $employer || empty( $employer->user_email ) ) {
            return;
        }

        $tokens = $this->build_application_tokens( $application, $job, $employer );

        /* translators: %s: job title */
        $subject = sprintf( esc_html_x( 'New application for "%s"', 'email subject', 'workeera-remote-tech-job-board' ), $job->post_title );

        $this->send( $employer->user_email, $subject, 'new-application', $tokens, [
            'event'          => 'new_application',
            'application_id' => (int) $application_id,
            'job_id'         => (int) $job_id,
        ] );
    }

    /**
     * Notify the applicant that the employer / admin moved their application
     * to a new status. The applicant's email is read from the application
     * row (set at apply time) rather than the user record so anonymous /
     * legacy applications still receive their notice.
     *
     * @param int    $application_id
     * @param int    $by_user_id
     * @param string $new_status
     */
    public function on_application_status_change( $application_id, $by_user_id, $new_status ) {
        $this->dispatch_status_change_email( (int) $application_id, sanitize_key( $new_status ) );
    }

    /**
     * Bulk variant — iterate and reuse the single-status path.
     *
     * @param int[]  $application_ids
     * @param int    $by_user_id
     * @param string $new_status
     * @param int    $updated_count
     */
    public function on_application_bulk_status_change( $application_ids, $by_user_id, $new_status, $updated_count ) {
        if ( empty( $application_ids ) || ! is_array( $application_ids ) ) {
            return;
        }
        $clean_status = sanitize_key( $new_status );
        foreach ( $application_ids as $app_id ) {
            $this->dispatch_status_change_email( (int) $app_id, $clean_status );
        }
    }

    // -------------------------------------------------------------------------
    // Dispatch + token building
    // -------------------------------------------------------------------------

    /**
     * Build the token map for a single application and send the
     * "application status changed" email to the original applicant.
     *
     * @param int    $application_id Application row ID.
     * @param string $new_status     Sanitised new status key.
     */
    private function dispatch_status_change_email( $application_id, $new_status ) {
        if ( $application_id < 1 ) {
            return;
        }

        $application = $this->get_application_row( $application_id );
        if ( ! $application ) {
            return;
        }

        $applicant_email = ! empty( $application['applicant_email'] ) ? $application['applicant_email'] : '';
        if ( empty( $applicant_email ) || ! is_email( $applicant_email ) ) {
            return;
        }

        $job = get_post( absint( $application['job_id'] ) );
        if ( ! $job ) {
            return;
        }
        $employer = get_userdata( (int) $job->post_author );

        // Ensure tokens reflect the new status that was just persisted.
        $application['status'] = $new_status;

        $tokens = $this->build_application_tokens( $application, $job, $employer );

        $status_label             = $this->status_label( $new_status );
        $tokens['application_status'] = $status_label;

        /* translators: %s: new application status label */
        $subject = sprintf( esc_html_x( 'Your application status: %s', 'email subject', 'workeera-remote-tech-job-board' ), $status_label );

        $this->send( $applicant_email, $subject, 'application-status', $tokens, [
            'event'          => 'application_status_change',
            'application_id' => (int) $application_id,
            'new_status'     => $new_status,
        ] );
    }

    /**
     * Token map for any job-centric template.
     */
    private function build_job_tokens( WP_Post $job, $author = null ) {
        $author_name = '';
        if ( $author && ! empty( $author->display_name ) ) {
            $author_name = $author->display_name;
        }
        return [
            'job_id'       => (string) $job->ID,
            'job_title'    => (string) $job->post_title,
            'job_url'      => (string) get_permalink( $job->ID ),
            'author_name'  => $author_name,
            'site_name'    => (string) get_bloginfo( 'name' ),
            'site_url'     => (string) home_url( '/' ),
            'admin_email'  => (string) get_option( 'admin_email' ),
            'current_date' => date_i18n( get_option( 'date_format' ) ),
        ];
    }

    /**
     * Token map shared by the "new application" and "application status"
     * templates. $application is an associative row from wp_workeera_applications.
     */
    private function build_application_tokens( $application, WP_Post $job, $employer = null ) {
        $tokens = $this->build_job_tokens( $job, $employer );

        global $workeera_plugin_settings;
        $dashboard_page = isset( $workeera_plugin_settings['pages_settings']['user_dashboard_page'] ) ? $workeera_plugin_settings['pages_settings']['user_dashboard_page'] : '';
        $dashboard_url  = ! empty( $dashboard_page ) ? get_permalink( $dashboard_page ) : home_url( '/' );

        $applications_url = add_query_arg( [ 'section-type' => 'applications', 'job_id' => (int) $job->ID ], $dashboard_url );

        $applied_ts    = ! empty( $application['applied_date'] ) ? strtotime( $application['applied_date'] ) : 0;
        $applied_label = $applied_ts ? date_i18n( get_option( 'date_format' ), $applied_ts ) : '';

        $tokens['employer_name']      = $employer && ! empty( $employer->display_name ) ? $employer->display_name : $tokens['author_name'];
        $tokens['candidate_name']     = isset( $application['applicant_name'] )  ? (string) $application['applicant_name']  : '';
        $tokens['candidate_email']    = isset( $application['applicant_email'] ) ? (string) $application['applicant_email'] : '';
        $tokens['candidate_phone']    = isset( $application['applicant_phone'] ) ? (string) $application['applicant_phone'] : '';
        $tokens['application_id']     = isset( $application['application_id'] )  ? (string) $application['application_id']  : '';
        $tokens['application_status'] = $this->status_label( isset( $application['status'] ) ? $application['status'] : '' );
        $tokens['applied_date']       = $applied_label;
        $tokens['applications_url']   = $applications_url;
        $tokens['cover_letter']       = isset( $application['cover_letter'] ) ? (string) $application['cover_letter'] : '';

        return $tokens;
    }

    // -------------------------------------------------------------------------
    // Send + template rendering
    // -------------------------------------------------------------------------

    /**
     * Load `templates/emails/email-{slug}.php`, replace `{tokens}` and send.
     *
     * Hooks:
     *   - `workeera_email_notification_should_send` ($default_true, $template_slug, $context)
     *     filter — return false to skip the send entirely.
     *   - `workeera_email_notification_subject` ($subject, $template_slug, $tokens, $context)
     *   - `workeera_email_notification_headers` ($headers, $template_slug, $tokens, $context)
     *   - `workeera_email_notification_body` ($body, $template_slug, $tokens, $context)
     */
    private function send( $to, $subject, $template_slug, $tokens, $context = [] ) {
        if ( empty( $to ) || ! is_email( $to ) ) {
            return false;
        }
        $context = is_array( $context ) ? $context : [];

        if ( ! apply_filters( 'workeera_email_notification_should_send', true, $template_slug, $context ) ) {
            return false;
        }

        $body = $this->render_template( $template_slug, $tokens );
        if ( empty( $body ) ) {
            return false;
        }

        // Tokens also apply to the subject so a customised subject can
        // include {job_title}, {candidate_name}, etc. Subjects are plain text
        // so we strip tags rather than HTML-escape.
        $subject = $this->replace_tokens( $subject, $tokens, 'plain' );

        $subject = apply_filters( 'workeera_email_notification_subject', $subject, $template_slug, $tokens, $context );
        $body    = apply_filters( 'workeera_email_notification_body',    $body,    $template_slug, $tokens, $context );

        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        $headers = apply_filters( 'workeera_email_notification_headers', $headers, $template_slug, $tokens, $context );

        // Hand wp_mail() a CRLF-joined string rather than an array. Both are
        // valid, but some mail-logging plugins store the raw headers value and
        // pass an array straight into $wpdb->insert(), which triggers a
        // "wpdb::prepare … Unsupported value type (array)" notice. A string
        // keeps wp_mail() behaviour identical while staying compatible.
        if ( is_array( $headers ) ) {
            $headers = implode( "\r\n", $headers );
        }

        return wp_mail( $to, $subject, $body, $headers );
    }

    /**
     * Render a template via `workeera_get_template_part` (which honours theme
     * overrides) and run token replacement on the captured output.
     */
    private function render_template( $slug, $tokens ) {
        ob_start();
        if ( function_exists( 'workeera_get_template_part' ) ) {
            workeera_get_template_part( 'email', $slug, 'emails' );
        }
        $body = ob_get_clean();
        if ( ! is_string( $body ) || $body === '' ) {
            return '';
        }
        return $this->replace_tokens( $body, $tokens, 'html' );
    }

    /**
     * Replace each `{key}` occurrence in $content with $tokens[$key]. Missing
     * tokens are left untouched so theme authors can spot typos.
     *
     * Values are escaped at substitution time based on the target context so
     * applicant-controlled tokens (candidate_name, candidate_email, job_title,
     * cover_letter, etc.) cannot inject HTML / scripts / phishing links into
     * the rendered email body.
     *
     * @param  string $content Template body or subject containing `{tokens}`.
     * @param  array  $tokens  Token map (key => raw string).
     * @param  string $context 'html' for the HTML body, 'plain' for the subject.
     * @return string          $content with tokens replaced and escaped.
     */
    private function replace_tokens( $content, $tokens, $context = 'html' ) {
        if ( ! is_array( $tokens ) || empty( $tokens ) ) {
            return $content;
        }

        // Tokens whose values are URLs — must be passed through esc_url for
        // both href and src usage in the HTML templates.
        $url_token_keys = [ 'site_url', 'job_url', 'applications_url' ];

        $search  = [];
        $replace = [];
        foreach ( $tokens as $key => $value ) {
            $search[] = '{' . $key . '}';
            $value    = (string) $value;

            if ( $context === 'plain' ) {
                // Subjects and other plain-text contexts: strip any HTML so a
                // hostile job title can't sneak markup into a header.
                $replace[] = wp_strip_all_tags( $value );
            } elseif ( in_array( $key, $url_token_keys, true ) ) {
                $replace[] = esc_url( $value );
            } else {
                // Default HTML-body escaping. Safe in both element text and
                // double-quoted attribute contexts (mailto:, title="…", etc.).
                $replace[] = esc_html( $value );
            }
        }
        return str_replace( $search, $replace, $content );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Fetch a single application row from wp_workeera_applications by ID.
     */
    private function get_application_row( $application_id ) {
        $application_id = absint( $application_id );
        if ( $application_id < 1 ) {
            return null;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'workeera_applications';
        $row   = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE application_id = %d LIMIT 1", $application_id ),
            ARRAY_A
        );
        return $row ? $row : null;
    }

    /**
     * Look up the translatable label for a status key. Falls back to the
     * status key when Workeera_Employer_Job_Applications isn't around yet.
     */
    private function status_label( $status ) {
        $status = sanitize_key( $status );
        if ( $status === '' ) {
            return '';
        }
        if ( class_exists( 'Workeera_Employer_Job_Applications' ) ) {
            return Workeera_Employer_Job_Applications::status_badge_label( $status );
        }
        return ucfirst( str_replace( '-', ' ', $status ) );
    }
}

new Workeera_Email_Notifications;
