<?php
/**
 * Email template: Job published
 *
 * Sent to a job's author the first time their listing is moved to "publish"
 * (typically after admin approval). Only fires for non-admin authors.
 *
 * Available tokens:
 *   {site_name}     - The site's name (Settings → General).
 *   {site_url}      - Homepage URL.
 *   {admin_email}   - Site admin email.
 *   {current_date}  - Today's date in the site's date format.
 *   {author_name}   - Display name of the user that posted the job.
 *   {job_id}        - Job post ID.
 *   {job_title}     - Job post title.
 *   {job_url}       - Public permalink to the job listing.
 *
 * To customise, copy this file to:
 *   your-theme/workeera/emails/email-job-published.php
 */

defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{job_title}</title>
</head>
<body style="margin:0;padding:24px;background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:10px;overflow:hidden;border:1px solid #e5e7eb;">
        <tr>
            <td style="background:#2563eb;color:#ffffff;padding:20px 24px;font-size:18px;font-weight:700;">
                {site_name}
            </td>
        </tr>
        <tr>
            <td style="padding:28px 24px;">
                <h1 style="margin:0 0 12px;font-size:22px;color:#0f172a;">Hi {author_name},</h1>
                <p style="margin:0 0 16px;font-size:15px;line-height:1.55;color:#334155;">
                    Good news! Your job posting <strong>"{job_title}"</strong> has been published and is now live on {site_name}.
                </p>
                <p style="margin:0 0 24px;font-size:14px;line-height:1.55;color:#475569;">
                    Candidates can now discover and apply to this listing. We'll let you know whenever someone submits an application.
                </p>
                <p style="margin:0 0 8px;">
                    <a href="{job_url}" style="display:inline-block;background:#2563eb;color:#ffffff;padding:10px 18px;border-radius:6px;text-decoration:none;font-weight:600;font-size:14px;">View your job listing</a>
                </p>
                <p style="margin:24px 0 0;font-size:12px;color:#64748b;">
                    Job ID #{job_id} &middot; {current_date}
                </p>
            </td>
        </tr>
        <tr>
            <td style="background:#f8fafc;padding:16px 24px;border-top:1px solid #e5e7eb;font-size:12px;color:#64748b;">
                You're receiving this because you posted a job on <a href="{site_url}" style="color:#2563eb;text-decoration:none;">{site_name}</a>.
            </td>
        </tr>
    </table>
</body>
</html>
